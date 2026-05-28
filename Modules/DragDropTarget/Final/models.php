<?php
/**
 * Models for the PlanFinales module
 * Finals target plan – planning view (rows = time slots, columns = targets)
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
        // Date range from DistanceInformation (qualifications)
        $rs2 = safe_r_sql("SELECT MIN(DiDay) minD, MAX(DiDay) maxD
                           FROM DistanceInformation
                           WHERE DiTournament=" . intval($tId)
                           . " AND DiDay IS NOT NULL AND DiDay != '0000-00-00'");
        if ($r2 = safe_fetch($rs2)) {
            $this->startDate = $r2->minD ?? '';
            $this->endDate   = $r2->maxD ?? '';
        }
        // Fallback: dates from FinSchedule (finals)
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
// PF_Match : a match within a phase
// ---------------------------------------------------------------
class PF_Match
{
    public $matchNo      = 0;
    public $pos1         = 0;   // GrPosition (seed 1)
    public $pos2         = 0;   // GrPosition2 (seed 2)
    public $target       = 0;   // FSTarget (target number, canonical side)
    public $letter       = '';  // FsLetter (A/B)
    public $mirrorTarget = 0;   // FSTarget of the real mirror (0 = unknown → use target+1)
}

// ---------------------------------------------------------------
// PF_Block : a block (phase or training) within a time slot
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
    public $fwKey        = '';    // key for FinWarmup UPDATE
    public $waveRow      = 0;    // 0 = wave A (AB), 1 = wave B (CD)
    public $baseBlockId  = '';   // base block id (same for block A and sub-block _w1)
    public $twoPerTarget = true; // false = 1 archer per target (EvFinalAthTarget bit=0)
    public $canonOnly  = false; // true = canonical half-tile (_s0) after ×1 segmentation
    public $mirrorOnly = false; // true = mirror half-tile (_s1) after ×1 segmentation
}

// ---------------------------------------------------------------
// PF_Slot : a time slot (planning row)
// ---------------------------------------------------------------
class PF_Slot
{
    public $id       = '';
    public $date     = '';
    public $time     = '';
    public $duration = 30;
    public $waves    = 1;   // 1 = normal mode, 2 = AB/CD mode (2 waves)
    public $blocks   = [];
}

// ---------------------------------------------------------------
// PF_Plan : reads the plan from FinSchedule + FinWarmup
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

    // Generates a random but deterministic pastel color (seeded on evCode).
    // Each R/G/B component is drawn from [127, 254] → always pastel.
    // The seed ensures the same evCode always produces the same color.
    private function generatePastelColor(string $evCode): string
    {
        $seed = abs(crc32($evCode));
        mt_srand($seed);
        $r = mt_rand(0, 127) + 127;
        $g = mt_rand(0, 127) + 127;
        $b = mt_rand(0, 127) + 127;
        mt_srand(); // restore the generator to truly random mode
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    // Returns the pastel color for an event (same color across all phases).
    // $isTeam kept for call compatibility but no longer affects the palette.
    private function getEventColor(string $evCode, bool $isTeam): string
    {
        if (!isset($this->eventColorMap[$evCode])) {
            $this->eventColorMap[$evCode] = $this->generatePastelColor($evCode);
        }
        return $this->eventColorMap[$evCode];
    }

    // Returns the warm-up color: same hue as the event, rendered translucent (rgba).
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


        // --- Individual phases ---
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

        // --- Team phases ---
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

        // --- Warm-ups (FinWarmup) ---
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
                    // List format: "1,2,3,4,5"
                    foreach (explode(',', $fwStr) as $t) {
                        $t = intval(trim($t));
                        if ($t > 0) { $targets[] = $t; }
                    }
                } elseif (strpos($fwStr, '-') !== false) {
                    // Range format: "1-12"
                    $parts = explode('-', $fwStr, 2);
                    $a = intval($parts[0]);
                    $b = intval($parts[1]);
                    if ($a > 0 && $b >= $a) {
                        for ($i = $a; $i <= $b; $i++) { $targets[] = $i; }
                    }
                } else {
                    // Count format: "12" → targets 1 to 12
                    $count = intval($fwStr);
                    if ($count > 1) {
                        for ($i = 1; $i <= $count; $i++) { $targets[] = $i; }
                    } elseif ($count === 1) {
                        // "1" is ambiguous → fallback to all used targets
                        $targets = array_keys($usedTargets);
                    }
                }
                $targets = array_values(array_unique($targets));
                sort($targets);
                foreach ($targets as $t) { $usedTargets[$t] = true; }
            }
            // If FwTargets is empty, leave $targets = [] → the block will not appear in the grid.

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

        // --- Targets to display ---
        $maxT = count($usedTargets) > 0 ? max(array_keys($usedTargets)) : 0;
        $maxT = max($maxT, 16);
        $this->targets = range(1, $maxT);


        // --- Sort and create PF_Slot entries ---
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
                // EvFinalAthTarget : bitmask — bit e = 1 means "2 archers per target"
                // bit e corresponds to GrPhase p: e=0 for p=0, e=floor(log2(p))+1 for p>0
                $athTarget = intval($r->EvFinalAthTarget ?? 0);
                $e = ($phase <= 1) ? $phase : (int)floor(log($phase, 2)) + 1;
                $twoPerTarget = (bool)(($athTarget >> $e) & 1);

                $evPhases[$evCode][$phase] = [
                    'eventLabel'   => $r->EvEventName,
                    'teamEvent'    => $teamEvent,
                    'startPhase'   => intval($r->EvFinalFirstPhase),
                    'phase'        => $phase,
                    'twoPerTarget' => $twoPerTarget,
                    'matchMap'     => [],   // [matchNo] => PF_Match  (one entry per real match)
                    'schedByMatch' => [],   // [matchNo] => ['date'=>..,'time'=>..,'dur'=>..]
                ];
            }

            // Grids has ONE row per archer per match.
            // Group by GrMatchNo: the 1st row gives pos1, the 2nd gives pos2.
            if (!isset($evPhases[$evCode][$phase]['matchMap'][$matchNo])) {
                $m          = new PF_Match();
                $m->matchNo = $matchNo;
                $m->pos1    = $pos;
                $m->pos2    = 0;
                $m->target  = $target;
                $m->letter  = $r->FsLetter ?? '';
                $evPhases[$evCode][$phase]['matchMap'][$matchNo] = $m;
                // Store the FinSchedule schedule per matchNo (first encounter only)
                if (!empty($r->FSScheduledDate)) {
                    $evPhases[$evCode][$phase]['schedByMatch'][$matchNo] = [
                        'date' => $r->FSScheduledDate,
                        'time' => substr($r->FSScheduledTime ?? '00:00:00', 0, 5),
                        'dur'  => intval($r->FSScheduledLen) ?: 30,
                    ];
                }
            } else {
                // 2nd archer of this match → this is pos2
                $evPhases[$evCode][$phase]['matchMap'][$matchNo]->pos2 = $pos;
            }

            if ($target > 0) {
                $usedTargets[$target] = true;
            }
        }

        // --- Detect ×1 split tiles (non-consecutive mirror target) ---
        // For each canonical matchNo of an individual ×1 phase, fetch the mirror
        // target (matchNo+1) from FinSchedule. If mirrorTarget ≠ canonTarget+1 or
        // if the slots differ → the tile has been split → two separate sub-blocks.
        // [evCode][mirrorMatchNo => ['target','date','time','dur']]
        // IMPORTANT: indexed by (evCode, matchNo) to avoid collisions between events
        // that share the same matchNo values (e.g. ScratchHCO and ScratchFCO both have
        // matchNo=4 — without the evCode key, one overwrites the other causing false splits).
        //
        // IMPORTANT 2: for some brackets (e.g. Bronze in ×1), matchNo+1 may coincide
        // with the CANONICAL matchNo of another phase (e.g. Bronze matchNo=3, Semis matchNo=4).
        // In that case, matchNo+1 is not a real mirror — including it in mirrorSchedule
        // would produce a false split by reading the FinSchedule slot of the other phase.
        // We therefore filter out any mirrorNo that is itself a canonical of any phase.
        $allPhaseCanonicalsPerEv = [];  // [evCode][matchNo] = true
        foreach ($evPhases as $evCode => $phases) {
            $allPhaseCanonicalsPerEv[$evCode] = [];
            foreach ($phases as $phase => $pd) {
                foreach ($pd['matchMap'] as $matchNo => $m) {
                    $allPhaseCanonicalsPerEv[$evCode][intval($matchNo)] = true;
                }
            }
        }

        $mirrorSchedule   = [];
        $mirrorNosToFetch = [];  // [evCode => [mirrorMatchNo, ...]]
        foreach ($evPhases as $evCode => $phases) {
            foreach ($phases as $phase => $pd) {
                if ($pd['twoPerTarget'] || $pd['teamEvent'] !== 0) continue;
                foreach ($pd['matchMap'] as $matchNo => $m) {
                    if ($m->target > 0) {
                        $mirrorNo = intval($matchNo) + 1;
                        // Do not look for a mirror if mirrorNo is itself a canonical
                        // of another phase (avoids the false Bronze↔Semis split).
                        if (!isset($allPhaseCanonicalsPerEv[$evCode][$mirrorNo])) {
                            $mirrorNosToFetch[$evCode][] = $mirrorNo;
                        }
                    }
                }
            }
        }
        foreach ($mirrorNosToFetch as $evCode => $nos) {
            $inList = implode(',', array_unique($nos));
            $evCodeSafe = StrSafe_DB($evCode);
            $rsm = safe_r_sql("SELECT FSMatchNo, FSTarget, FSScheduledDate, FSScheduledTime, FSScheduledLen
                               FROM FinSchedule
                               WHERE FSTournament=" . intval($this->tour->id) . "
                               AND FSEvent=$evCodeSafe
                               AND FSMatchNo IN ($inList)");
            while ($rm = safe_fetch($rsm)) {
                $mirrorSchedule[$evCode][intval($rm->FSMatchNo)] = [
                    'target' => intval($rm->FSTarget),
                    'date'   => $rm->FSScheduledDate ?? null,
                    'time'   => substr($rm->FSScheduledTime ?? '00:00:00', 0, 5),
                    'dur'    => intval($rm->FSScheduledLen) ?: 30,
                ];
            }
        }

        // Fetch the real target of "blocked" mirrors (mirrorNo = canonical of another phase).
        // These mirrors are excluded from split detection, but their FSTarget is needed to
        // correctly compute the targetList (case where mirror target ≠ canonical target + 1).
        $blockedMirrorNos  = [];  // [evCode => [mirrorNo, ...]]
        $blockedMirrorTgts = [];  // [evCode][mirrorNo] = target
        foreach ($evPhases as $evCode => $phases) {
            foreach ($phases as $phase => $pd) {
                if ($pd['twoPerTarget'] || $pd['teamEvent'] !== 0) continue;
                foreach ($pd['matchMap'] as $matchNo => $m) {
                    if ($m->target > 0) {
                        $mirrorNo = intval($matchNo) + 1;
                        if (isset($allPhaseCanonicalsPerEv[$evCode][$mirrorNo])) {
                            $blockedMirrorNos[$evCode][] = $mirrorNo;
                        }
                    }
                }
            }
        }
        foreach ($blockedMirrorNos as $evCode => $nos) {
            $inList = implode(',', array_unique($nos));
            $evCodeSafe = StrSafe_DB($evCode);
            $rsm = safe_r_sql("SELECT FSMatchNo, FSTarget FROM FinSchedule
                               WHERE FSTournament=" . intval($this->tour->id) . "
                               AND FSEvent=$evCodeSafe AND FSMatchNo IN ($inList)");
            while ($rm = safe_fetch($rsm)) {
                $blockedMirrorTgts[$evCode][intval($rm->FSMatchNo)] = intval($rm->FSTarget);
            }
        }
        // Store the real mirror target on each affected PF_Match
        foreach ($evPhases as $evCode => $phases) {
            foreach ($phases as $phase => $pd) {
                foreach ($pd['matchMap'] as $matchNo => $m) {
                    $mirrorNo = intval($matchNo) + 1;
                    if (isset($blockedMirrorTgts[$evCode][$mirrorNo])) {
                        $m->mirrorTarget = $blockedMirrorTgts[$evCode][$mirrorNo];
                    }
                }
            }
        }

        foreach ($evPhases as $evCode => $phases) {
            $phaseCount = count($phases);  // number of distinct phases for this event

            foreach ($phases as $phase => $pd) {

                // --- Skip phases with no real positions ---
                $hasRealPositions = false;
                foreach ($pd['matchMap'] as $m) {
                    if ($m->pos1 > 0) { $hasRealPositions = true; break; }
                }
                if (!$hasRealPositions) continue;

                // --- No competitive bracket when there is only one phase ---
                if ($phaseCount === 1) continue;

                // --- Individual events: compute the opponent and de-duplicate ---
                // Grids has ONE row per archer (unique GrMatchNo per archer).
                // WA formula: opponent = N+1-pos1  (N = max seed = bracket size).
                // De-duplicate via canonical min_max pair key to avoid having
                // both "1⚔16" and "16⚔1" (or "1⚔?" from unfiltered entries).
                if ($pd['teamEvent'] === 0 && !empty($pd['matchMap'])) {
                    $validPos = array_filter(
                        array_map(fn($m) => $m->pos1, array_values($pd['matchMap'])),
                        fn($p) => $p > 0
                    );
                    if (!empty($validPos)) {
                        $uniqueSeeds = array_values($validPos);
                        sort($uniqueSeeds);

                        if (count($uniqueSeeds) === 2) {
                            // Exactly 2 archers (Bronze, Gold small bracket…):
                            // they face each other directly — WA formula not applicable.
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
                            // WA formula: opponent = N+1-pos1
                            $n            = max($uniqueSeeds);
                            $pairsSeen    = [];
                            $indivMatches = [];
                            foreach ($pd['matchMap'] as $m) {
                                if ($m->pos1 <= 0) continue;
                                $opp = $n + 1 - $m->pos1;
                                // Skip seeds outside the bracket or self-match
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

                // --- Separate split matches (mirror at non-consecutive target) ---
                // For individual ×1 phases: if the mirror matchNo (N+1) is in a
                // different slot or at a different target than canonical+1 → split tile.
                $splitPairs = [];
                if (!$pd['twoPerTarget'] && $pd['teamEvent'] === 0) {
                    $filteredMatches = [];
                    foreach ($matches as $m) {
                        $mirrorNo   = $m->matchNo + 1;
                        $canonSched = $pd['schedByMatch'][$m->matchNo] ?? null;
                        if ($m->target > 0 && isset($mirrorSchedule[$evCode][$mirrorNo]) && $canonSched) {
                            $mi        = $mirrorSchedule[$evCode][$mirrorNo];
                            $canonKey  = $canonSched['date'] . '|' . $canonSched['time'] . '|' . $canonSched['dur'];
                            $mirrorKey = ($mi['date'] ?? '') . '|' . ($mi['time'] ?? '') . '|' . ($mi['dur'] ?? 30);
                            // Real split: valid mirror target (>0) and non-consecutive,
                            // OR different slots (non-null mirror date).
                            // If the mirror target is 0/NULL (unassigned), it is not a split
                            // (incomplete data — the next save will correct the mirror target).
                            $isSplit = $mi['target'] > 0
                                       && (($mi['target'] !== $m->target + 1)
                                           || ($mi['date'] !== null && $canonKey !== $mirrorKey));
                            if ($isSplit) {
                                $splitPairs[] = ['match' => $m, 'mirrorInfo' => $mi, 'canonSched' => $canonSched];
                            } else {
                                $filteredMatches[] = $m;
                            }
                        } else {
                            $filteredMatches[] = $m;
                        }
                    }
                    $matches = $filteredMatches;
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

                // Mark individual ×1 blocks whose mirrorNo (matchNo+1) is the canonical
                // of another phase (e.g. Bronze matchNo=3, mirrorNo=4 = Semis).
                // In that case, no separate "mirror" FinSchedule entry exists: the match spans
                // 2 columns (canonical + canonical+1) but has only one FinSchedule entry.
                // The client uses this flag to disable segmentation (_s0/_s1).
                if (!$pd['twoPerTarget'] && $pd['teamEvent'] === 0 && !empty($matches)) {
                    $allMirrorsAreCanonicals = true;
                    foreach ($matches as $m) {
                        $mirNo = $m->matchNo + 1;
                        if (!isset($allPhaseCanonicalsPerEv[$evCode][$mirNo])) {
                            $allMirrorsAreCanonicals = false;
                            break;
                        }
                    }
                    if ($allMirrorsAreCanonicals) {
                        $block->noMirrorMatchNo = true;
                    }
                }

                // Build targetList.
                // For "1 archer per target" (twoPerTarget=false) individual: each canonical
                // match occupies its own target AND the mirror target (canonical+1 in iAnseo).
                // For teams: no mirror — each team has its own matchNo in FinSchedule
                // and occupies its canonical target directly.
                $targets = [];
                foreach ($matches as $m) {
                    if ($m->target > 0) {
                        $targets[] = $m->target;
                        if (!$pd['twoPerTarget'] && $pd['teamEvent'] === 0) {
                            // Use the real mirror target if known, otherwise canonical+1
                            $mirTgt = ($m->mirrorTarget > 0) ? $m->mirrorTarget : $m->target + 1;
                            $targets[] = $mirTgt;
                        }
                    }
                }
                $targets = array_values(array_unique($targets));
                sort($targets);
                $block->targetList = $targets;

                // Determine the time slot from the CANONICAL match (matches[0]),
                // not from the first matchNo encountered in the query (ORDER BY GrMatchNo).
                // This avoids reading the schedule of an iAnseo "mirror" match instead of
                // the schedule saved by PlanFinales for the canonical match.
                $canonMatchNo = isset($matches[0]) ? $matches[0]->matchNo : null;
                $sched = ($canonMatchNo !== null) ? ($pd['schedByMatch'][$canonMatchNo] ?? null) : null;
                if (!$sched) {
                    // Fallback: first available schedule for this phase
                    foreach ($pd['schedByMatch'] as $s) { $sched = $s; break; }
                }
                $slotDate = $sched['date'] ?? null;
                $slotTime = $sched['time'] ?? '00:00';
                $slotDur  = $sched['dur']  ?? 30;

                // A block goes into a slot only if it has a date AND at least one target.
                // Without a target, even with a date, it is invisible on the grid → unscheduled.
                if ($slotDate !== null && !empty($targets)) {
                    $key = $slotDate . '|' . $slotTime . '|' . $slotDur;
                    if (!isset($slotsMap[$key])) {
                        $slotsMap[$key] = ['date' => $slotDate, 'time' => $slotTime,
                                           'duration' => $slotDur, 'blocks' => [], 'waves' => 1];
                    }

                    // --- 2-wave mode (AB/CD): FsLetter='B' on some matches ---
                    $hasWaveB = !empty(array_filter($matches, fn($m) => $m->letter === 'B'));
                    if ($hasWaveB) {
                        $slotsMap[$key]['waves'] = 2;

                        $matchesA = array_values(array_filter($matches, fn($m) => $m->letter !== 'B'));
                        $matchesB = array_values(array_filter($matches, fn($m) => $m->letter === 'B'));

                        $baseId = $block->id;  // e.g. "phase_ScratchHCO_2"

                        // Wave A sub-block (waveRow=0)
                        if (!empty($matchesA)) {
                            $blockA          = clone $block;
                            $blockA->matches = $matchesA;
                            $tgtsA = [];
                            foreach ($matchesA as $m) {
                                if ($m->target > 0) {
                                    $tgtsA[] = $m->target;
                                    if (!$pd['twoPerTarget'] && $pd['teamEvent'] === 0) {
                                        $tgtsA[] = $m->target + 1;
                                    }
                                }
                            }
                            $blockA->targetList  = array_values(array_unique($tgtsA));
                            sort($blockA->targetList);
                            $blockA->waveRow     = 0;
                            $blockA->baseBlockId = $baseId;
                            $slotsMap[$key]['blocks'][] = $blockA;
                        }

                        // Wave B sub-block (waveRow=1), id suffixed with _w1
                        if (!empty($matchesB)) {
                            $blockB          = clone $block;
                            $blockB->id      = $baseId . '_w1';
                            $blockB->matches = $matchesB;
                            $tgtsB = [];
                            foreach ($matchesB as $m) {
                                if ($m->target > 0) {
                                    $tgtsB[] = $m->target;
                                    if (!$pd['twoPerTarget'] && $pd['teamEvent'] === 0) {
                                        $tgtsB[] = $m->target + 1;
                                    }
                                }
                            }
                            $blockB->targetList  = array_values(array_unique($tgtsB));
                            sort($blockB->targetList);
                            $blockB->waveRow     = 1;
                            $blockB->baseBlockId = $baseId;
                            $slotsMap[$key]['blocks'][] = $blockB;
                        }
                    } else {
                        // Normal 1-wave mode
                        $slotsMap[$key]['blocks'][] = $block;
                    }
                } else {
                    if (!empty($matches)) $this->unscheduled[] = $block;
                }

                // --- Sub-blocks for split matches ---
                foreach ($splitPairs as $sp) {
                    $sm          = $sp['match'];
                    $mi          = $sp['mirrorInfo'];
                    $canonSched  = $sp['canonSched'];

                    // Canonical sub-block (_s0): pos1 at the canonical target
                    $blockS0             = clone $block;
                    $blockS0->id         = $block->id . '_s0';
                    $blockS0->matches    = [$sm];
                    $blockS0->targetList = [$sm->target];
                    $blockS0->canonOnly  = true;
                    $usedTargets[$sm->target] = true;
                    $keyC = $canonSched['date'] . '|' . $canonSched['time'] . '|' . $canonSched['dur'];
                    if (!isset($slotsMap[$keyC])) {
                        $slotsMap[$keyC] = ['date' => $canonSched['date'], 'time' => $canonSched['time'],
                                            'duration' => $canonSched['dur'], 'blocks' => [], 'waves' => 1];
                    }
                    $slotsMap[$keyC]['blocks'][] = $blockS0;

                    // Mirror sub-block (_s1): pos2 at the mirror target
                    $blockS1             = clone $block;
                    $blockS1->id         = $block->id . '_s1';
                    $blockS1->matches    = [$sm];
                    $blockS1->targetList = [$mi['target']];
                    $blockS1->mirrorOnly = true;
                    $usedTargets[$mi['target']] = true;
                    if ($mi['date']) {
                        $keyM = $mi['date'] . '|' . $mi['time'] . '|' . $mi['dur'];
                        if (!isset($slotsMap[$keyM])) {
                            $slotsMap[$keyM] = ['date' => $mi['date'], 'time' => $mi['time'],
                                                'duration' => $mi['dur'], 'blocks' => [], 'waves' => 1];
                        }
                        $slotsMap[$keyM]['blocks'][] = $blockS1;
                    } else {
                        $this->unscheduled[] = $blockS1;
                    }
                }
            }
        }
    }

    private function formatPhaseName(int $phase): string
    {
        switch ($phase) {
            case 0:  return get_text('MedalGold');
            case 1:  return get_text('MedalBronze');
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


    // --- List of available events (for the warm-up selector) ---
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

    // --- JSON export for the JS ---
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
        // baseBlockId: emitted only for blocks in 2-wave mode (A and _w1).
        // pfFetchBlock uses it to group wave siblings.
        if ($block->baseBlockId !== '') {
            $arr['baseBlockId'] = $block->baseBlockId;
        }
        // Half-tiles from ×1 segmentation: flags sent to the JS so
        // the save knows what to write (canonical only / mirror only).
        if ($block->canonOnly)  $arr['_canonOnly']  = true;
        if ($block->mirrorOnly) $arr['_mirrorOnly'] = true;
        // ×1 block with no real mirror (e.g. Bronze: mirrorNo=matchNo+1 is the Semis canonical).
        // The JS disables the ⊕ segmentation button for these blocks.
        if (!empty($block->noMirrorMatchNo)) $arr['noMirrorMatchNo'] = true;

        if ($block->type === 'phase') {
            $arr['phase']     = $block->phase;
            $arr['phaseName'] = $block->phaseName;
            $arr['matches']   = array_map(fn($m) => [
                'matchNo' => $m->matchNo,
                'pos1'    => $m->pos1,
                'pos2'        => $m->pos2,
                'target'      => $m->target,
                'mirrorTarget'=> $m->mirrorTarget ?: null,
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

    // Deletes a FinWarmup training entry identified by its fwKey
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

        // --- Delete trainings marked as deleted on the client side ---
        foreach ($data['deletedTrainings'] ?? [] as $fwKey) {
            if (is_string($fwKey) && $fwKey !== '') {
                $this->deleteTraining($fwKey);
            }
        }

        // Update EvFinalAthTarget in the database for each phase where twoPerTarget was changed
        $athUpdates = [];  // [evCode => [e => bool]]
        $allBlocks  = [];
        foreach ($data['slots'] ?? [] as $slot) {
            foreach ($slot['blocks'] ?? [] as $b) { $allBlocks[] = $b; }
        }
        foreach ($data['unscheduled'] ?? [] as $b) { $allBlocks[] = $b; }
        foreach ($allBlocks as $b) {
            if (($b['type'] ?? '') !== 'phase') continue;
            $evCode = $b['event'] ?? '';
            $phase  = intval($b['phase'] ?? 0);
            $e = ($phase <= 1) ? $phase : (int)floor(log($phase, 2)) + 1;
            $athUpdates[$evCode][$e] = (bool)($b['twoPerTarget'] ?? true);
        }
        foreach ($athUpdates as $evCode => $bits) {
            $evCodeSafe = StrSafe_DB($evCode);
            foreach ($bits as $e => $twoPerTarget) {
                $bit = 1 << $e;
                if ($twoPerTarget) {
                    safe_w_sql("UPDATE Events SET EvFinalAthTarget = EvFinalAthTarget | " . intval($bit) .
                               " WHERE EvCode=$evCodeSafe AND EvTournament=" . intval($this->tId));
                } else {
                    $clearMask = 0xFF & ~$bit;
                    safe_w_sql("UPDATE Events SET EvFinalAthTarget = EvFinalAthTarget & " . intval($clearMask) .
                               " WHERE EvCode=$evCodeSafe AND EvTournament=" . intval($this->tId));
                }
            }
        }

        // --- Pre-scan: collect all canonical matchNos by (event, teamEvent, phase) ---
        // Allows deleting iAnseo "mirror" matchNos (non-canonical partners)
        // without touching canonical matchNos of other sub-blocks (e.g. wave A vs wave B).
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
        // Unscheduled blocks → no canonicals → all matchNos will be deleted
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
            // canonicals stays [] → all matchNos for the phase will be cleared
        }

        // --- Delete ALL matchNos for UNSCHEDULED phases (canonicals=[]) ---
        // For scheduled phases, saveBlock handles the mirrors itself
        // (DELETE or UPSERT depending on "1 archer per target" vs "2 archers per target").
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

        // --- Process scheduled blocks (DELETE+INSERT canonical matchNos) ---
        foreach ($data['slots'] ?? [] as $slot) {
            $date  = $slot['date']     ?? '';
            $time  = $slot['time']     ?? '';
            $dur   = intval($slot['duration'] ?? 30);
            $waves = intval($slot['waves']    ?? 1);
            foreach ($slot['blocks'] ?? [] as $block) {
                $this->saveBlock($block, $date, $time, $dur, $waves, $errors, $phaseCanonicals);
            }
        }

        // --- Clean up unscheduled blocks (delete remaining canonical matchNos) ---
        foreach ($data['unscheduled'] ?? [] as $block) {
            if ($block['type'] === 'phase') {
                $this->clearPhase($block, $errors);
            }
        }

        return $errors;
    }

    /**
     * Returns all GrMatchNo values associated with a phase (event + GrPhase)
     * via Finals (individual) or TeamFinals (team).
     * Used to identify iAnseo "mirror" matchNos to clean up.
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

    private function saveBlock(array $block, string $date, string $time, int $dur, int $waves, array &$errors, array $phaseCanonicals = [])
    {
        if ($block['type'] === 'phase') {
            $evCode    = StrSafe_DB($block['event']);
            $teamEvent = intval($block['teamEvent']);
            $dateSql   = StrSafe_DB($date);
            $timeSql   = StrSafe_DB($time . ':00');
            // FsLetter: 'A' for wave 0 (AB), 'B' for wave 1 (CD)
            $waveRow = intval($block['waveRow'] ?? 0);
            $letter  = $waveRow > 0 ? "'B'" : "'A'";

            $isCanonOnly  = !empty($block['_canonOnly']);
            $isMirrorOnly = !empty($block['_mirrorOnly']);

            if ($isMirrorOnly) {
                // Mirror half-tile: save ONLY the mirror matchNo (canonical+1)
                // at the canonical+1 target in this slot.
                // The canonical N is not touched here (handled by _s0/_canonOnly).
                //
                // Safety net: if noMirrorMatchNo=true (e.g. Bronze whose mirrorNo = Semis canonical),
                // write nothing — there is no mirror FinSchedule entry for this match.
                if (!empty($block['noMirrorMatchNo'])) {
                    return;
                }
                foreach ($block['matches'] ?? [] as $match) {
                    $matchNo     = intval($match['matchNo']);
                    $mirrorNo    = $matchNo + 1;
                    $canonTarget = intval($match['target']);
                    $mirrorTarget = $canonTarget + 1;
                    safe_w_sql("DELETE FROM FinSchedule
                        WHERE FSTournament=" . intval($this->tId) . "
                        AND FSEvent=$evCode AND FSMatchNo=$mirrorNo");
                    safe_w_sql("INSERT INTO FinSchedule
                        (FSTournament,FSEvent,FSMatchNo,FSTeamEvent,FSScheduledDate,FSScheduledTime,FSScheduledLen,FSTarget,FsLetter)
                        VALUES(" . intval($this->tId) . ",$evCode,$mirrorNo,$teamEvent,
                               $dateSql,$timeSql," . intval($dur) . ",$mirrorTarget,$letter)");
                }
            } else {
                foreach ($block['matches'] ?? [] as $match) {
                    $matchNo = intval($match['matchNo']);
                    $target  = intval($match['target']);

                    safe_w_sql("DELETE FROM FinSchedule
                        WHERE FSTournament=" . intval($this->tId) . "
                        AND FSEvent=$evCode AND FSMatchNo=$matchNo");

                    safe_w_sql("INSERT INTO FinSchedule
                        (FSTournament,FSEvent,FSMatchNo,FSTeamEvent,FSScheduledDate,FSScheduledTime,FSScheduledLen,FSTarget,FsLetter)
                        VALUES(" . intval($this->tId) . ",$evCode,$matchNo,$teamEvent,
                               $dateSql,$timeSql," . intval($dur) . "," .
                               ($target > 0 ? intval($target) : 'NULL') . ",$letter)");
                }

                if (!$isCanonOnly) {
                    // --- iAnseo mirrors (canonical+1 matchNo) ---
                    // iAnseo convention: even matchNo = canonical, odd matchNo = mirror.
                    // For "1 archer per target" (twoPerTarget=false): the mirror must have
                    // its own FinSchedule entry at the adjacent target (canonical+1 target).
                    // For "2 archers per target" (twoPerTarget=true): the mirror is deleted.
                    //
                    // IMPORTANT (segments): a segmented block yields several sub-blocks with the same
                    // event+phase. Each saveBlock must only process mirrors WHOSE CANONICAL
                    // belongs to THIS sub-block — otherwise the matchNos of other sub-blocks would
                    // be wrongly deleted during the mirrorNos = allMatchNos - canonicalNos calculation.
                    // We therefore use the full set of canonicals for the phase (all segments
                    // combined) to compute mirrorNos, and for twoPerTarget=false only process
                    // mirrors whose canonical is in THIS sub-block.
                    $twoPerTarget  = ($block['twoPerTarget'] ?? true);
                    $evCodeStr     = $block['event'];
                    $phase         = intval($block['phase'] ?? 0);
                    $allMatchNos   = $this->getPhaseMatchNos(StrSafe_DB($evCodeStr), $phase, $teamEvent);
                    $canonicalNos  = array_map('intval', array_column($block['matches'] ?? [], 'matchNo'));
                    $phaseKey      = $block['event'] . '|' . intval($block['teamEvent']) . '|' . $phase;
                    $allCanonicals = !empty($phaseCanonicals[$phaseKey]['canonicals'])
                                     ? array_map('intval', $phaseCanonicals[$phaseKey]['canonicals'])
                                     : $canonicalNos;
                    $mirrorNos     = array_values(array_diff($allMatchNos, $allCanonicals));

                    if (!$twoPerTarget && !empty($mirrorNos)) {
                        // 1 archer per target: UPSERT the mirror of THIS block's canonical only.
                        $canonicalTargetMap = [];
                        foreach ($block['matches'] ?? [] as $m) {
                            $canonicalTargetMap[intval($m['matchNo'])] = intval($m['target']);
                        }
                        foreach ($mirrorNos as $mn) {
                            $canonicalMn = $mn - 1;
                            $canonTgt    = $canonicalTargetMap[$canonicalMn] ?? 0;
                            if ($canonTgt <= 0) continue;
                            $mirrorTarget = $canonTgt + 1;
                            safe_w_sql("DELETE FROM FinSchedule
                                WHERE FSTournament=" . intval($this->tId) . "
                                AND FSEvent=$evCode AND FSMatchNo=$mn");
                            safe_w_sql("INSERT INTO FinSchedule
                                (FSTournament,FSEvent,FSMatchNo,FSTeamEvent,FSScheduledDate,FSScheduledTime,FSScheduledLen,FSTarget,FsLetter)
                                VALUES(" . intval($this->tId) . ",$evCode,$mn,$teamEvent,
                                       $dateSql,$timeSql," . intval($dur) . ",$mirrorTarget,$letter)");
                        }
                    } else {
                        foreach ($mirrorNos as $mn) {
                            safe_w_sql("DELETE FROM FinSchedule
                                WHERE FSTournament=" . intval($this->tId) . "
                                AND FSEvent=$evCode AND FSMatchNo=$mn");
                        }
                    }
                }
            }

            // Update EvMatchMultipleMatches in Events.
            // Same formula as PhaseDetails-actions.php: mask = max(1, phase × 2).
            // The slot is in 2-wave mode ($waves > 1) → activate the bit for ALL
            // blocks it contains, regardless of their waveRow or baseBlockId.
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
                // New block created in PlanFinales → INSERT
                $evCode    = StrSafe_DB($block['event'] ?? '');
                $teamEvent = intval($block['teamEvent'] ?? 0);
                // Unique FwMatchTime: uses a timestamp hash to avoid key conflicts
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
            // Delete ALL records (including FSTeamEvent=NULL duplicates)
            // for this match. An unscheduled match does not need a record
            // in FinSchedule (PlanFinales uses a LEFT JOIN).
            safe_w_sql("DELETE FROM FinSchedule
                WHERE FSTournament=" . intval($this->tId) . "
                AND FSEvent=$evCode AND FSMatchNo=$matchNo");
        }

        // Unscheduled phase → deactivate the EvMatchMultipleMatches bit
        $phase     = intval($block['phase'] ?? 0);
        $phaseMask = max(1, $phase * 2);
        safe_w_sql("UPDATE Events
            SET EvMatchMultipleMatches = EvMatchMultipleMatches & ~$phaseMask
            WHERE EvTournament=" . intval($this->tId) . "
            AND EvTeamEvent=$teamEvent AND EvCode=$evCode");
    }
}
