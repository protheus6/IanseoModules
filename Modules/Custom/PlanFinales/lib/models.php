<?php
/**
 * Modèles pour le module PlanFinales
 * Plan de cible des finales – vue planning (lignes = horaires, colonnes = cibles)
 */

require_once('Common/Fun_Phases.inc.php');

// ---------------------------------------------------------------
// PF_TourInfo
// ---------------------------------------------------------------
class PF_TourInfo
{
    public $id;
    public $name      = '';
    public $code      = '';
    public $startDate = '';
    public $endDate   = '';

    public function __construct(int $tId)
    {
        $this->id = $tId;
        $rs = safe_r_sql("SELECT ToName, ToCode FROM Tournament WHERE ToId=" . intval($tId));
        if ($r = safe_fetch($rs)) {
            $this->name = $r->ToName;
            $this->code = $r->ToCode;
        }
        // Plage de dates depuis DistanceInformation (qualifs)
        $rs2 = safe_r_sql("SELECT MIN(DiDay) minD, MAX(DiDay) maxD
                           FROM DistanceInformation
                           WHERE DiTournament=" . intval($tId)
                           . " AND DiDay IS NOT NULL AND DiDay != '0000-00-00'");
        if ($r2 = safe_fetch($rs2)) {
            $this->startDate = $r2->minD ?? '';
            $this->endDate   = $r2->maxD ?? '';
        }
        // Fallback : dates depuis FinSchedule (finales)
        if (!$this->startDate) {
            $rs3 = safe_r_sql("SELECT MIN(FSScheduledDate) minD, MAX(FSScheduledDate) maxD
                               FROM FinSchedule
                               WHERE FSTournament=" . intval($tId)
                               . " AND FSScheduledDate IS NOT NULL");
            if ($r3 = safe_fetch($rs3)) {
                $this->startDate = $r3->minD ?? '';
                $this->endDate   = $r3->maxD ?? '';
            }
        }
    }
}

// ---------------------------------------------------------------
// PF_Match : un match dans une phase
// ---------------------------------------------------------------
class PF_Match
{
    public $matchNo = 0;
    public $pos1    = 0;   // GrPosition (seed 1)
    public $pos2    = 0;   // GrPosition2 (seed 2)
    public $target  = 0;   // FSTarget (numéro de cible)
    public $letter  = '';  // FsLetter (A/B)
}

// ---------------------------------------------------------------
// PF_Block : un bloc (phase ou entraînement) dans un créneau
// ---------------------------------------------------------------
class PF_Block
{
    public $id         = '';
    public $type       = 'phase';  // 'phase' | 'training'
    public $teamEvent  = 0;
    public $event      = '';
    public $eventLabel = '';
    public $phase      = 0;
    public $phaseName  = '';
    public $color      = '#cccccc';
    public $targetList = [];
    public $matches    = [];
    public $fwKey        = '';    // clé pour UPDATE FinWarmup
    public $waveRow      = 0;    // 0 = vague A (AB), 1 = vague B (CD)
    public $baseBlockId  = '';   // id du bloc de base (même pour bloc A et sous-bloc _w1)
    public $twoPerTarget = true; // false = 1 archer par cible (EvFinalAthTarget bit=0)
}

// ---------------------------------------------------------------
// PF_Slot : un créneau horaire (ligne du planning)
// ---------------------------------------------------------------
class PF_Slot
{
    public $id       = '';
    public $date     = '';
    public $time     = '';
    public $duration = 30;
    public $waves    = 1;   // 1 = mode normal, 2 = mode AB/CD (2 vagues)
    public $blocks   = [];
}

// ---------------------------------------------------------------
// PF_Plan : lecture du plan depuis FinSchedule + FinWarmup
// ---------------------------------------------------------------
class PF_Plan
{
    public $tour;
    public $targets     = [];
    public $slots       = [];
    public $unscheduled = [];

    private $eventColorMap = [];

    public function __construct(int $tId)
    {
        $this->tour = new PF_TourInfo($tId);
        $this->build();
    }

    // Génère une couleur pastel aléatoire mais déterministe (seedée sur l'evCode).
    // Chaque composante R/G/B est tirée dans [127, 254] → toujours pastel.
    // Le seed garantit que le même evCode donne toujours la même couleur.
    private function generatePastelColor(string $evCode): string
    {
        $seed = abs(crc32($evCode));
        mt_srand($seed);
        $r = mt_rand(0, 127) + 127;
        $g = mt_rand(0, 127) + 127;
        $b = mt_rand(0, 127) + 127;
        mt_srand(); // remet le générateur en mode vraiment aléatoire
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    // Retourne la couleur pastel d'un event (même couleur pour toutes les phases).
    // $isTeam conservé pour compatibilité d'appel mais n'influe plus sur la palette.
    private function getEventColor(string $evCode, bool $isTeam): string
    {
        if (!isset($this->eventColorMap[$evCode])) {
            $this->eventColorMap[$evCode] = $this->generatePastelColor($evCode);
        }
        return $this->eventColorMap[$evCode];
    }

    // Retourne la couleur d'échauffement : même teinte que l'event, rendue translucide (rgba).
    private function getTrainColor(string $evCode, bool $isTeam): string
    {
        $base = $this->getEventColor($evCode, $isTeam);
        $r = hexdec(substr($base, 1, 2));
        $g = hexdec(substr($base, 3, 2));
        $b = hexdec(substr($base, 5, 2));
        return sprintf('rgba(%d,%d,%d,0.45)', $r, $g, $b);
    }

    private function build()
    {
        $tId      = $this->tour->id;
        $slotsMap = [];
        $usedTargets = [];

        // --- Phases individuelles ---
        $sqlInd = "SELECT e.EvCode, e.EvEventName, e.EvFinalFirstPhase, e.EvFinalAthTarget,
                          g.GrPhase, g.GrMatchNo, g.GrPosition, g.GrPosition2,
                          fs.FSScheduledDate, fs.FSScheduledTime, fs.FSScheduledLen,
                          fs.FSTarget, fs.FsLetter
                   FROM Finals f
                   INNER JOIN Grids g ON f.FinMatchNo = g.GrMatchNo
                   INNER JOIN Events e ON f.FinEvent = e.EvCode
                       AND e.EvTournament = " . intval($tId) . "
                       AND e.EvTeamEvent = '0'
                   LEFT JOIN FinSchedule fs
                       ON f.FinEvent = fs.FSEvent
                       AND f.FinMatchNo = fs.FSMatchNo
                       AND (fs.FSTeamEvent = '0' OR fs.FSTeamEvent IS NULL)
                       AND f.FinTournament = fs.FSTournament
                   WHERE f.FinTournament = " . intval($tId) . "
                   ORDER BY e.EvProgr, g.GrPhase DESC, g.GrMatchNo";
        $this->buildPhaseBlocks($sqlInd, 0, $slotsMap, $usedTargets);

        // --- Phases équipes ---
        $sqlTeam = "SELECT e.EvCode, e.EvEventName, e.EvFinalFirstPhase, e.EvFinalAthTarget,
                           g.GrPhase, g.GrMatchNo, g.GrPosition, g.GrPosition2,
                           fs.FSScheduledDate, fs.FSScheduledTime, fs.FSScheduledLen,
                           fs.FSTarget, fs.FsLetter
                    FROM TeamFinals tf
                    INNER JOIN Grids g ON tf.TFMatchNo = g.GrMatchNo
                    INNER JOIN Events e ON tf.TFEvent = e.EvCode
                        AND e.EvTournament = " . intval($tId) . "
                        AND e.EvTeamEvent = '1'
                    LEFT JOIN FinSchedule fs
                        ON tf.TFEvent = fs.FSEvent
                        AND tf.TFMatchNo = fs.FSMatchNo
                        AND (fs.FSTeamEvent = '1' OR fs.FSTeamEvent IS NULL)
                        AND tf.TFTournament = fs.FSTournament
                    WHERE tf.TFTournament = " . intval($tId) . "
                    ORDER BY e.EvProgr, g.GrPhase DESC, g.GrMatchNo";
        $this->buildPhaseBlocks($sqlTeam, 1, $slotsMap, $usedTargets);

        // --- Entraînements (FinWarmup) ---
        $sqlWarm = "SELECT fw.FwDay, fw.FwTime, fw.FwDuration, fw.FwTargets,
                           fw.FwTeamEvent, fw.FwEvent, fw.FwMatchTime, fw.FwOptions,
                           e.EvEventName
                    FROM FinWarmup fw
                    LEFT JOIN Events e ON fw.FwEvent = e.EvCode
                        AND e.EvTournament = " . intval($tId) . "
                    WHERE fw.FwTournament = " . intval($tId) . "
                    ORDER BY fw.FwDay, fw.FwTime";
        $rs = safe_r_sql($sqlWarm);
        while ($r = safe_fetch($rs)) {
            $date = $r->FwDay;
            $time = substr($r->FwTime, 0, 5);
            $dur  = intval($r->FwDuration);
            $key  = $date . '|' . $time . '|' . $dur;

            if (!isset($slotsMap[$key])) {
                $slotsMap[$key] = ['date' => $date, 'time' => $time, 'duration' => $dur, 'blocks' => []];
            }

            $targets = [];
            if (!empty($r->FwTargets)) {
                $fwStr = trim($r->FwTargets);
                if (strpos($fwStr, ',') !== false) {
                    // Format liste : "1,2,3,4,5"
                    foreach (explode(',', $fwStr) as $t) {
                        $t = intval(trim($t));
                        if ($t > 0) { $targets[] = $t; }
                    }
                } elseif (strpos($fwStr, '-') !== false) {
                    // Format plage : "1-12"
                    $parts = explode('-', $fwStr, 2);
                    $a = intval($parts[0]);
                    $b = intval($parts[1]);
                    if ($a > 0 && $b >= $a) {
                        for ($i = $a; $i <= $b; $i++) { $targets[] = $i; }
                    }
                } else {
                    // Format count : "12" → cibles 1 à 12
                    $count = intval($fwStr);
                    if ($count > 1) {
                        for ($i = 1; $i <= $count; $i++) { $targets[] = $i; }
                    } elseif ($count === 1) {
                        // "1" ambigu → fallback sur toutes les cibles utilisées
                        $targets = array_keys($usedTargets);
                    }
                }
                $targets = array_values(array_unique($targets));
                sort($targets);
                foreach ($targets as $t) { $usedTargets[$t] = true; }
            }
            // Si FwTargets est vide, on laisse $targets = [] → le bloc ne sera pas affiché dans la grille.

            $isTeam = intval($r->FwTeamEvent) === 1;
            $block = new PF_Block();
            $block->type       = 'training';
            $block->teamEvent  = intval($r->FwTeamEvent);
            $block->event      = $r->FwEvent;
            $block->eventLabel = !empty($r->EvEventName) ? $r->EvEventName : $r->FwEvent;
            $block->color      = $this->getTrainColor($r->FwEvent, $isTeam);
            $block->targetList = $targets;
            $block->id         = 'trn_' . $r->FwTeamEvent . '_' . preg_replace('/\W/', '', $r->FwEvent)
                                 . '_' . str_replace('-', '', $date) . '_' . str_replace(':', '', $time);
            $block->fwKey      = $date . '|' . $r->FwTime . '|' . intval($r->FwTeamEvent)
                                 . '|' . $r->FwEvent . '|' . $r->FwMatchTime;

            $slotsMap[$key]['blocks'][] = $block;
        }

        // --- Cibles à afficher ---
        $maxT = count($usedTargets) > 0 ? max(array_keys($usedTargets)) : 0;
        $maxT = max($maxT, 16);
        $this->targets = range(1, $maxT);

        // --- Charger config persistée (cibles + créneaux vides) ---
        $saved = $this->loadSavedConfig();
        if (!empty($saved['targets'])) {
            $this->targets = $saved['targets'];
        }
        // Fusionner les créneaux vides sauvegardés
        foreach ($saved['emptySlots'] ?? [] as $es) {
            $key = $es['date'] . '|' . $es['time'] . '|' . intval($es['duration']);
            if (!isset($slotsMap[$key])) {
                $slotsMap[$key] = ['date' => $es['date'], 'time' => $es['time'],
                                   'duration' => intval($es['duration']), 'blocks' => []];
            }
        }

        // --- Trier et créer les PF_Slot ---
        ksort($slotsMap);
        foreach ($slotsMap as $key => $sd) {
            $slot           = new PF_Slot();
            $slot->id       = 'slot_' . str_replace(['|', '-', ':'], '_', $key);
            $slot->date     = $sd['date'];
            $slot->time     = $sd['time'];
            $slot->duration = $sd['duration'];
            $slot->waves    = $sd['waves'] ?? 1;
            $slot->blocks   = $sd['blocks'];
            $this->slots[]  = $slot;
        }
    }

    private function buildPhaseBlocks(string $sql, int $teamEvent, array &$slotsMap, array &$usedTargets)
    {
        $rs = safe_r_sql($sql);
        $evPhases = []; // [evCode][phase] = [...]

        while ($r = safe_fetch($rs)) {
            $evCode  = $r->EvCode;
            $phase   = intval($r->GrPhase);
            $matchNo = intval($r->GrMatchNo);
            $pos     = intval($r->GrPosition);
            $target  = intval($r->FSTarget);

            if (!isset($evPhases[$evCode][$phase])) {
                // EvFinalAthTarget : bitmask — bit e = 1 signifie "2 archers par cible"
                // bit e correspond à GrPhase p avec : e=0 pour p=0, e=floor(log2(p))+1 pour p>0
                $athTarget = intval($r->EvFinalAthTarget ?? 0);
                $e = ($phase <= 1) ? $phase : (int)floor(log($phase, 2)) + 1;
                $twoPerTarget = (bool)(($athTarget >> $e) & 1);

                $evPhases[$evCode][$phase] = [
                    'eventLabel'   => $r->EvEventName,
                    'teamEvent'    => $teamEvent,
                    'startPhase'   => intval($r->EvFinalFirstPhase),
                    'phase'        => $phase,
                    'twoPerTarget' => $twoPerTarget,
                    'matchMap'     => [],   // [matchNo] => PF_Match  (une entrée par match réel)
                    'schedByMatch' => [],   // [matchNo] => ['date'=>..,'time'=>..,'dur'=>..]
                ];
            }

            // Grids a UNE ligne par archer dans le match.
            // On groupe par GrMatchNo : la 1re ligne donne pos1, la 2e donne pos2.
            if (!isset($evPhases[$evCode][$phase]['matchMap'][$matchNo])) {
                $m          = new PF_Match();
                $m->matchNo = $matchNo;
                $m->pos1    = $pos;
                $m->pos2    = 0;
                $m->target  = $target;
                $m->letter  = $r->FsLetter ?? '';
                $evPhases[$evCode][$phase]['matchMap'][$matchNo] = $m;
                // Stocker le schedule FinSchedule par matchNo (1re rencontre seulement)
                if (!empty($r->FSScheduledDate)) {
                    $evPhases[$evCode][$phase]['schedByMatch'][$matchNo] = [
                        'date' => $r->FSScheduledDate,
                        'time' => substr($r->FSScheduledTime ?? '00:00:00', 0, 5),
                        'dur'  => intval($r->FSScheduledLen) ?: 30,
                    ];
                }
            } else {
                // 2e archer de ce match → c'est pos2
                $evPhases[$evCode][$phase]['matchMap'][$matchNo]->pos2 = $pos;
            }

            if ($target > 0) {
                $usedTargets[$target] = true;
            }
        }

        foreach ($evPhases as $evCode => $phases) {
            $phaseCount = count($phases);  // nb de phases distinctes pour cet événement

            foreach ($phases as $phase => $pd) {

                // --- Ignorer les phases sans positions réelles ---
                $hasRealPositions = false;
                foreach ($pd['matchMap'] as $m) {
                    if ($m->pos1 > 0) { $hasRealPositions = true; break; }
                }
                if (!$hasRealPositions) continue;

                // --- Pas de bracket compétitif si une seule phase ---
                if ($phaseCount === 1) continue;

                // --- Épreuves individuelles : calculer l'adversaire et dédupliquer ---
                // Grids a UNE ligne par archer (GrMatchNo unique par archer).
                // Formule WA : adversaire = N+1-pos1  (N = max des seeds = taille du bracket).
                // On déduplique via clé de paire canonique min_max pour éviter d'avoir
                // à la fois "1⚔16" et "16⚔1" (ou "1⚔?" issu d'une entrée non filtrée).
                if ($pd['teamEvent'] === 0 && !empty($pd['matchMap'])) {
                    $validPos = array_filter(
                        array_map(fn($m) => $m->pos1, array_values($pd['matchMap'])),
                        fn($p) => $p > 0
                    );
                    if (!empty($validPos)) {
                        $uniqueSeeds = array_values($validPos);
                        sort($uniqueSeeds);

                        if (count($uniqueSeeds) === 2) {
                            // Exactement 2 archers (Bronze, Or petit bracket…) :
                            // ils s'affrontent directement — formule WA inapplicable.
                            $p1 = $uniqueSeeds[0];
                            $p2 = $uniqueSeeds[1];
                            $firstMatch = null;
                            foreach ($pd['matchMap'] as $m) {
                                if ($m->pos1 === $p1) { $firstMatch = $m; break; }
                            }
                            if (!$firstMatch) $firstMatch = clone reset($pd['matchMap']);
                            $firstMatch->pos1 = $p1;
                            $firstMatch->pos2 = $p2;
                            $matches = [$firstMatch];
                        } else {
                            // Formule WA : adversaire = N+1-pos1
                            $n            = max($uniqueSeeds);
                            $pairsSeen    = [];
                            $indivMatches = [];
                            foreach ($pd['matchMap'] as $m) {
                                if ($m->pos1 <= 0) continue;
                                $opp = $n + 1 - $m->pos1;
                                // Ignorer seeds hors bracket ou self-match
                                if ($opp <= 0 || $opp > $n || $opp === $m->pos1) continue;
                                $p1      = min($m->pos1, $opp);
                                $p2      = max($m->pos1, $opp);
                                $pairKey = $p1 . '_' . $p2;
                                if (!isset($pairsSeen[$pairKey])) {
                                    $pairsSeen[$pairKey] = true;
                                    $m->pos1 = $p1;
                                    $m->pos2 = $p2;
                                    $indivMatches[] = $m;
                                }
                            }
                            $matches = $indivMatches;
                        }
                    } else {
                        $matches = [];
                    }
                } else {
                    $matches = array_values($pd['matchMap']);
                }

                $rawName   = namePhase($pd['startPhase'], $phase);
                $phaseName = $this->formatPhaseName(intval($rawName));

                $block             = new PF_Block();
                $block->id         = 'phase_' . $evCode . '_' . $phase;
                $block->type       = 'phase';
                $block->teamEvent  = $pd['teamEvent'];
                $block->event      = $evCode;
                $block->eventLabel = $pd['eventLabel'];
                $block->phase      = $phase;
                $block->phaseName  = $phaseName;
                $block->color        = $this->getEventColor($evCode, (bool)$pd['teamEvent']);
                $block->matches      = $matches;
                $block->twoPerTarget = $pd['twoPerTarget'];

                // Construire targetList.
                // Pour "1 archer par cible" (twoPerTarget=false) : chaque match canonique
                // occupe sa propre cible ET la cible du miroir (canonical+1 dans iAnseo).
                $targets = [];
                foreach ($matches as $m) {
                    if ($m->target > 0) {
                        $targets[] = $m->target;
                        if (!$pd['twoPerTarget']) {
                            $targets[] = $m->target + 1;  // cible du matchNo miroir (pair+1)
                        }
                    }
                }
                $targets = array_values(array_unique($targets));
                sort($targets);
                $block->targetList = $targets;

                // Déterminer le créneau horaire à partir du match CANONIQUE (matches[0]),
                // pas du premier matchNo rencontré dans la requête (ORDER BY GrMatchNo).
                // Cela évite de lire le schedule d'un match "miroir" iAnseo au lieu du
                // schedule sauvegardé par PlanFinales sur le match canonique.
                $canonMatchNo = isset($matches[0]) ? $matches[0]->matchNo : null;
                $sched = ($canonMatchNo !== null) ? ($pd['schedByMatch'][$canonMatchNo] ?? null) : null;
                if (!$sched) {
                    // Repli : premier schedule disponible pour cette phase
                    foreach ($pd['schedByMatch'] as $s) { $sched = $s; break; }
                }
                $slotDate = $sched['date'] ?? null;
                $slotTime = $sched['time'] ?? '00:00';
                $slotDur  = $sched['dur']  ?? 30;

                // Un bloc va dans un créneau seulement s'il a une date ET au moins une cible.
                // Sans cible, même s'il a une date, il est invisible sur la grille → non-planifié.
                if ($slotDate !== null && !empty($targets)) {
                    $key = $slotDate . '|' . $slotTime . '|' . $slotDur;
                    if (!isset($slotsMap[$key])) {
                        $slotsMap[$key] = ['date' => $slotDate, 'time' => $slotTime,
                                           'duration' => $slotDur, 'blocks' => [], 'waves' => 1];
                    }

                    // --- Mode 2 vagues (AB/CD) : FsLetter='B' sur certains matchs ---
                    $hasWaveB = !empty(array_filter($matches, fn($m) => $m->letter === 'B'));
                    if ($hasWaveB) {
                        $slotsMap[$key]['waves'] = 2;

                        $matchesA = array_values(array_filter($matches, fn($m) => $m->letter !== 'B'));
                        $matchesB = array_values(array_filter($matches, fn($m) => $m->letter === 'B'));

                        $baseId = $block->id;  // ex : "phase_ScratchHCO_2"

                        // Sous-bloc vague A (waveRow=0)
                        if (!empty($matchesA)) {
                            $blockA              = clone $block;
                            $blockA->matches     = $matchesA;
                            $blockA->targetList  = array_values(array_unique(array_filter(
                                array_map(fn($m) => $m->target, $matchesA)
                            )));
                            sort($blockA->targetList);
                            $blockA->waveRow     = 0;
                            $blockA->baseBlockId = $baseId;  // ← pfFetchBlock en a besoin
                            $slotsMap[$key]['blocks'][] = $blockA;
                        }

                        // Sous-bloc vague B (waveRow=1), id suffixé _w1
                        if (!empty($matchesB)) {
                            $blockB              = clone $block;
                            $blockB->id          = $baseId . '_w1';
                            $blockB->matches     = $matchesB;
                            $blockB->targetList  = array_values(array_unique(array_filter(
                                array_map(fn($m) => $m->target, $matchesB)
                            )));
                            sort($blockB->targetList);
                            $blockB->waveRow     = 1;
                            $blockB->baseBlockId = $baseId;  // ← pfFetchBlock en a besoin
                            $slotsMap[$key]['blocks'][] = $blockB;
                        }
                    } else {
                        // Mode 1 vague normal
                        $slotsMap[$key]['blocks'][] = $block;
                    }
                } else {
                    $this->unscheduled[] = $block;
                }
            }
        }
    }

    private function formatPhaseName(int $phase): string
    {
        switch ($phase) {
            case 0:  return 'Or';
            case 1:  return 'Bronze';
            case 2:  return '1/2';
            case 4:  return '1/4';
            case 8:  return '1/8';
            case 16: return '1/16';
            case 24: return '1/24';
            case 32: return '1/32';
            case 48: return '1/48';
            case 64: return '1/64';
            default: return '1/' . $phase;
        }
    }

    // --- Config persistée (liste cibles + créneaux vides) ---
    private function getConfigPath(): string
    {
        return __DIR__ . '/../data/plan_' . intval($this->tour->id) . '.json';
    }

    public function loadSavedConfig(): array
    {
        $path = $this->getConfigPath();
        if (!file_exists($path)) return [];
        $json = @file_get_contents($path);
        if (!$json) return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public function saveConfig(array $targets, array $emptySlots)
    {
        $path = $this->getConfigPath();
        file_put_contents($path, json_encode([
            'targets'    => $targets,
            'emptySlots' => $emptySlots,
        ]));
    }

    // --- Liste des épreuves disponibles (pour le sélecteur d'entraînement) ---
    public function getAvailableEvents(): array
    {
        $rs = safe_r_sql("SELECT EvCode, EvEventName, EvTeamEvent
                          FROM Events
                          WHERE EvTournament=" . intval($this->tour->id) . "
                          ORDER BY EvProgr, EvCode");
        $events = [];
        while ($r = safe_fetch($rs)) {
            $events[] = [
                'code'      => $r->EvCode,
                'label'     => get_text($r->EvEventName, '', '', true),
                'teamEvent' => intval($r->EvTeamEvent),
            ];
        }
        return $events;
    }

    // --- Export JSON pour le JS ---
    public function toJson(): array
    {
        $result = [
            'tourName'    => $this->tour->name,
            'dateRange'   => $this->tour->startDate . ' — ' . $this->tour->endDate,
            'targets'     => $this->targets,
            'events'      => $this->getAvailableEvents(),
            'slots'       => [],
            'unscheduled' => [],
        ];

        foreach ($this->slots as $slot) {
            $sd = [
                'id'       => $slot->id,
                'date'     => $slot->date,
                'time'     => $slot->time,
                'duration' => $slot->duration,
                'waves'    => $slot->waves,
                'blocks'   => [],
            ];
            foreach ($slot->blocks as $block) {
                $sd['blocks'][] = $this->blockToArray($block);
            }
            $result['slots'][] = $sd;
        }

        foreach ($this->unscheduled as $block) {
            $result['unscheduled'][] = $this->blockToArray($block);
        }

        return $result;
    }

    private function blockToArray(PF_Block $block): array
    {
        $arr = [
            'id'           => $block->id,
            'type'         => $block->type,
            'teamEvent'    => $block->teamEvent,
            'event'        => $block->event,
            'eventLabel'   => get_text($block->eventLabel, '', '', true),
            'color'        => $block->color,
            'targetList'   => array_values($block->targetList),
            'waveRow'      => $block->waveRow,
            'twoPerTarget' => $block->twoPerTarget,
        ];
        // baseBlockId : émis uniquement pour les blocs en mode 2-vagues (A et _w1).
        // pfFetchBlock s'en sert pour regrouper les siblings de vague.
        if ($block->baseBlockId !== '') {
            $arr['baseBlockId'] = $block->baseBlockId;
        }

        if ($block->type === 'phase') {
            $arr['phase']     = $block->phase;
            $arr['phaseName'] = $block->phaseName;
            $arr['matches']   = array_map(fn($m) => [
                'matchNo' => $m->matchNo,
                'pos1'    => $m->pos1,
                'pos2'    => $m->pos2,
                'target'  => $m->target,
            ], $block->matches);
        } else {
            $arr['fwKey'] = $block->fwKey;
        }

        return $arr;
    }
}

// ---------------------------------------------------------------
// PF_Saver : sauvegarde du plan vers FinSchedule + FinWarmup
// ---------------------------------------------------------------
class PF_Saver
{
    private $tId;

    public function __construct(int $tId)
    {
        $this->tId = $tId;
    }

    // Supprime un entraînement FinWarmup identifié par son fwKey
    private function deleteTraining(string $fwKey): void
    {
        $parts = explode('|', $fwKey);
        if (count($parts) < 5) return;
        [$origDate, $origTime, $teamEvent, $event, $matchTime] = $parts;
        safe_w_sql("DELETE FROM FinWarmup
            WHERE FwTournament=" . intval($this->tId) . "
            AND FwDay="       . StrSafe_DB($origDate) . "
            AND FwTime="      . StrSafe_DB($origTime) . "
            AND FwTeamEvent=" . intval($teamEvent) . "
            AND FwEvent="     . StrSafe_DB($event) . "
            AND FwMatchTime=" . StrSafe_DB($matchTime));
    }

    public function save(array $data): array
    {
        $errors = [];
        $plan   = new PF_Plan($this->tId);

        // --- Supprimer les entraînements marqués comme supprimés côté client ---
        foreach ($data['deletedTrainings'] ?? [] as $fwKey) {
            if (is_string($fwKey) && $fwKey !== '') {
                $this->deleteTraining($fwKey);
            }
        }

        // Sauvegarder la liste des cibles et des créneaux vides
        $targets    = array_map('intval', $data['targets'] ?? []);
        $emptySlots = [];
        foreach ($data['slots'] ?? [] as $slot) {
            if (empty($slot['blocks'])) {
                $emptySlots[] = [
                    'date'     => $slot['date'],
                    'time'     => $slot['time'],
                    'duration' => intval($slot['duration']),
                ];
            }
        }
        $plan->saveConfig($targets, $emptySlots);

        // --- Pré-scan : recenser tous les matchNos canoniques par (event, teamEvent, phase) ---
        // Permet de supprimer les matchNos "miroirs" iAnseo (partenaires non-canoniques)
        // sans toucher aux matchNos canoniques des autres sous-blocs (ex. vague A vs vague B).
        $phaseCanonicals = [];  // ['evCode|te|phase'] => ['evCode','te','phase','canonicals'=[]]
        foreach ($data['slots'] ?? [] as $slot) {
            foreach ($slot['blocks'] ?? [] as $block) {
                if (($block['type'] ?? '') !== 'phase') continue;
                $k = $block['event'] . '|' . intval($block['teamEvent']) . '|' . intval($block['phase'] ?? 0);
                if (!isset($phaseCanonicals[$k])) {
                    $phaseCanonicals[$k] = [
                        'evCode'     => $block['event'],
                        'teamEvent'  => intval($block['teamEvent']),
                        'phase'      => intval($block['phase'] ?? 0),
                        'canonicals' => [],
                    ];
                }
                foreach ($block['matches'] ?? [] as $m) {
                    $phaseCanonicals[$k]['canonicals'][] = intval($m['matchNo']);
                }
            }
        }
        // Les blocs non-planifiés → plus aucun canonical → tous les matchNos seront supprimés
        foreach ($data['unscheduled'] ?? [] as $block) {
            if (($block['type'] ?? '') !== 'phase') continue;
            $k = $block['event'] . '|' . intval($block['teamEvent']) . '|' . intval($block['phase'] ?? 0);
            if (!isset($phaseCanonicals[$k])) {
                $phaseCanonicals[$k] = [
                    'evCode'     => $block['event'],
                    'teamEvent'  => intval($block['teamEvent']),
                    'phase'      => intval($block['phase'] ?? 0),
                    'canonicals' => [],
                ];
            }
            // canonicals reste [] → tous les matchNos de la phase seront effacés
        }

        // --- Supprimer TOUS les matchNos pour les phases NON-PLANIFIÉES (canonicals=[]) ---
        // Pour les phases planifiées, saveBlock gère lui-même les miroirs
        // (DELETE ou UPSERT selon "1 archer par cible" vs "2 archers par cible").
        foreach ($phaseCanonicals as $info) {
            if (!empty($info['canonicals'])) continue;  // phase planifiée → géré dans saveBlock
            $evCodeSafe  = StrSafe_DB($info['evCode']);
            $allMatchNos = $this->getPhaseMatchNos($evCodeSafe, $info['phase'], $info['teamEvent']);
            foreach ($allMatchNos as $mn) {
                safe_w_sql("DELETE FROM FinSchedule
                    WHERE FSTournament=" . intval($this->tId) . "
                    AND FSEvent=$evCodeSafe AND FSMatchNo=" . intval($mn));
            }
        }

        // --- Traiter les blocs planifiés (DELETE+INSERT des matchNos canoniques) ---
        foreach ($data['slots'] ?? [] as $slot) {
            $date  = $slot['date']     ?? '';
            $time  = $slot['time']     ?? '';
            $dur   = intval($slot['duration'] ?? 30);
            $waves = intval($slot['waves']    ?? 1);
            foreach ($slot['blocks'] ?? [] as $block) {
                $this->saveBlock($block, $date, $time, $dur, $waves, $errors);
            }
        }

        // --- Nettoyer les blocs non planifiés (supprimer les matchNos canoniques restants) ---
        foreach ($data['unscheduled'] ?? [] as $block) {
            if ($block['type'] === 'phase') {
                $this->clearPhase($block, $errors);
            }
        }

        return $errors;
    }

    /**
     * Retourne tous les GrMatchNo associés à une phase (event + GrPhase)
     * en passant par Finals (individuel) ou TeamFinals (équipe).
     * Utilisé pour identifier les matchNos "miroirs" iAnseo à nettoyer.
     */
    private function getPhaseMatchNos(string $evCodeSafe, int $phase, int $teamEvent): array
    {
        if ($teamEvent === 1) {
            $sql = "SELECT DISTINCT g.GrMatchNo
                    FROM Grids g
                    INNER JOIN TeamFinals tf ON tf.TFMatchNo = g.GrMatchNo
                        AND tf.TFTournament=" . intval($this->tId) . "
                        AND tf.TFEvent=$evCodeSafe
                    WHERE g.GrPhase=$phase";
        } else {
            $sql = "SELECT DISTINCT g.GrMatchNo
                    FROM Grids g
                    INNER JOIN Finals f ON f.FinMatchNo = g.GrMatchNo
                        AND f.FinTournament=" . intval($this->tId) . "
                        AND f.FinEvent=$evCodeSafe
                    WHERE g.GrPhase=$phase";
        }
        $rs  = safe_r_sql($sql);
        $nos = [];
        while ($r = safe_fetch($rs)) { $nos[] = intval($r->GrMatchNo); }
        return $nos;
    }

    private function saveBlock(array $block, string $date, string $time, int $dur, int $waves, array &$errors)
    {
        if ($block['type'] === 'phase') {
            $evCode    = StrSafe_DB($block['event']);
            $teamEvent = intval($block['teamEvent']);
            $dateSql   = StrSafe_DB($date);
            $timeSql   = StrSafe_DB($time . ':00');
            // FsLetter : 'A' pour vague 0 (AB), 'B' pour vague 1 (CD)
            $waveRow = intval($block['waveRow'] ?? 0);
            $letter  = $waveRow > 0 ? "'B'" : "'A'";

            foreach ($block['matches'] ?? [] as $match) {
                $matchNo = intval($match['matchNo']);
                $target  = intval($match['target']);

                // Supprimer TOUS les enregistrements existants pour ce match
                // (quelle que soit la valeur de FSTeamEvent, y compris NULL).
                // Cela élimine les doublons potentiels créés par iAnseo ou
                // des sauvegardes précédentes, garantissant un seul enregistrement propre.
                safe_w_sql("DELETE FROM FinSchedule
                    WHERE FSTournament=" . intval($this->tId) . "
                    AND FSEvent=$evCode AND FSMatchNo=$matchNo");

                // Insérer l'enregistrement correct avec FSTeamEvent normalisé.
                safe_w_sql("INSERT INTO FinSchedule
                    (FSTournament,FSEvent,FSMatchNo,FSTeamEvent,FSScheduledDate,FSScheduledTime,FSScheduledLen,FSTarget,FsLetter)
                    VALUES(" . intval($this->tId) . ",$evCode,$matchNo,$teamEvent,
                           $dateSql,$timeSql," . intval($dur) . "," .
                           ($target > 0 ? intval($target) : 'NULL') . ",$letter)");
            }

            // --- Miroirs iAnseo (matchNo canonical+1) ---
            // Convention iAnseo : matchNo pair = canonical, matchNo impair = miroir.
            // Pour "1 archer par cible" (twoPerTarget=false) : le miroir doit avoir
            // sa propre entrée FinSchedule à la cible adjacente (cible du canonical+1).
            // Pour "2 archers par cible" (twoPerTarget=true) : le miroir est supprimé.
            $twoPerTarget = ($block['twoPerTarget'] ?? true);
            $evCodeStr    = $block['event'];
            $phase        = intval($block['phase'] ?? 0);
            $allMatchNos  = $this->getPhaseMatchNos(StrSafe_DB($evCodeStr), $phase, $teamEvent);
            $canonicalNos = array_map('intval', array_column($block['matches'] ?? [], 'matchNo'));
            $mirrorNos    = array_values(array_diff($allMatchNos, $canonicalNos));

            if (!$twoPerTarget && !empty($mirrorNos)) {
                // 1 archer par cible : UPSERT chaque miroir à la cible canonical+1.
                // On construit un index canonical → target pour retrouver la cible miroir.
                $canonicalTargetMap = [];
                foreach ($block['matches'] ?? [] as $m) {
                    $canonicalTargetMap[intval($m['matchNo'])] = intval($m['target']);
                }
                foreach ($mirrorNos as $mn) {
                    // Le canonical correspondant = mn-1 (convention iAnseo pair/impair)
                    $canonicalMn  = $mn - 1;
                    $canonTgt     = $canonicalTargetMap[$canonicalMn] ?? 0;
                    $mirrorTarget = ($canonTgt > 0) ? $canonTgt + 1 : 0;

                    safe_w_sql("DELETE FROM FinSchedule
                        WHERE FSTournament=" . intval($this->tId) . "
                        AND FSEvent=$evCode AND FSMatchNo=$mn");
                    if ($mirrorTarget > 0) {
                        safe_w_sql("INSERT INTO FinSchedule
                            (FSTournament,FSEvent,FSMatchNo,FSTeamEvent,FSScheduledDate,FSScheduledTime,FSScheduledLen,FSTarget,FsLetter)
                            VALUES(" . intval($this->tId) . ",$evCode,$mn,$teamEvent,
                                   $dateSql,$timeSql," . intval($dur) . ",$mirrorTarget,$letter)");
                    }
                }
            } else {
                // 2 archers par cible : les miroirs n'ont pas besoin d'entrée séparée
                foreach ($mirrorNos as $mn) {
                    safe_w_sql("DELETE FROM FinSchedule
                        WHERE FSTournament=" . intval($this->tId) . "
                        AND FSEvent=$evCode AND FSMatchNo=$mn");
                }
            }

            // Mettre à jour EvMatchMultipleMatches dans Events.
            // Formule identique à PhaseDetails-actions.php : masque = max(1, phase × 2).
            // Le créneau est en mode 2-vagues ($waves > 1) → activer le bit pour TOUS
            // les blocs qu'il contient, quelle que soit leur waveRow ou baseBlockId.
            $phase     = intval($block['phase'] ?? 0);
            $phaseMask = max(1, $phase * 2);
            if ($waves > 1) {
                safe_w_sql("UPDATE Events
                    SET EvMatchMultipleMatches = EvMatchMultipleMatches | $phaseMask
                    WHERE EvTournament=" . intval($this->tId) . "
                    AND EvTeamEvent=$teamEvent AND EvCode=$evCode");
            } else {
                safe_w_sql("UPDATE Events
                    SET EvMatchMultipleMatches = EvMatchMultipleMatches & ~$phaseMask
                    WHERE EvTournament=" . intval($this->tId) . "
                    AND EvTeamEvent=$teamEvent AND EvCode=$evCode");
            }
        } elseif ($block['type'] === 'training') {
            $fwKey     = $block['fwKey'] ?? '';
            $targets   = array_map('intval', $block['targetList'] ?? []);
            $targetStr = implode(',', $targets);

            if (!$fwKey) {
                // Nouveau bloc créé dans PlanFinales → INSERT
                $evCode    = StrSafe_DB($block['event'] ?? '');
                $teamEvent = intval($block['teamEvent'] ?? 0);
                // FwMatchTime unique : utilise un hash de l'instant pour éviter les conflits de clé
                $matchTime = date('H:i:s', abs(crc32(uniqid('pf', true))) % 86400);
                safe_w_sql("INSERT INTO FinWarmup
                    (FwTournament, FwDay, FwTime, FwDuration, FwTargets, FwTeamEvent, FwEvent, FwMatchTime, FwOptions)
                    VALUES (" . intval($this->tId) . ", " . StrSafe_DB($date) . ",
                    " . StrSafe_DB($time . ':00') . ", " . intval($dur) . ",
                    " . StrSafe_DB($targetStr) . ", $teamEvent, $evCode,
                    " . StrSafe_DB($matchTime) . ", 'PF')");
                return;
            }

            $parts = explode('|', $fwKey);
            if (count($parts) < 5) return;
            [$origDate, $origTime, $teamEvent, $event, $matchTime] = $parts;

            safe_w_sql("UPDATE FinWarmup SET
                FwDay="     . StrSafe_DB($date) . ",
                FwTime="    . StrSafe_DB($time . ':00') . ",
                FwDuration=" . intval($dur) . ",
                FwTargets=" . StrSafe_DB($targetStr) . "
                WHERE FwTournament=" . intval($this->tId) . "
                AND FwDay="        . StrSafe_DB($origDate) . "
                AND FwTime="       . StrSafe_DB($origTime) . "
                AND FwTeamEvent="  . intval($teamEvent) . "
                AND FwEvent="      . StrSafe_DB($event) . "
                AND FwMatchTime="  . StrSafe_DB($matchTime));
        }
    }

    private function clearPhase(array $block, array &$errors)
    {
        $evCode    = StrSafe_DB($block['event']);
        $teamEvent = intval($block['teamEvent']);
        foreach ($block['matches'] ?? [] as $match) {
            $matchNo = intval($match['matchNo']);
            // Supprimer TOUS les enregistrements (y compris doublons FSTeamEvent=NULL)
            // pour ce match. Un match non-planifié n'a pas besoin d'enregistrement
            // dans FinSchedule (PlanFinales utilise un LEFT JOIN).
            safe_w_sql("DELETE FROM FinSchedule
                WHERE FSTournament=" . intval($this->tId) . "
                AND FSEvent=$evCode AND FSMatchNo=$matchNo");
        }

        // Phase non-planifiée → désactiver le bit EvMatchMultipleMatches
        $phase     = intval($block['phase'] ?? 0);
        $phaseMask = max(1, $phase * 2);
        safe_w_sql("UPDATE Events
            SET EvMatchMultipleMatches = EvMatchMultipleMatches & ~$phaseMask
            WHERE EvTournament=" . intval($this->tId) . "
            AND EvTeamEvent=$teamEvent AND EvCode=$evCode");
    }
}
