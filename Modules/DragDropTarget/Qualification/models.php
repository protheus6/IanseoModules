<?php
/**
 * Models for the PlanQualifs module
 *
 */

// ---------------------------------------------------------------
// QP_TourInfo : tournament info + session list
// ---------------------------------------------------------------
class QP_TourInfo
{
    public $id;
    public $code      = '';
    public $name      = '';
    public $shortName = '';
    public $sessions  = [];

    public function __construct(int $tId)
    {
        $this->id = $tId;
        $this->load();
    }

    private function load()
    {
        $sql = "SELECT ToId, ToCode, ToName, ToNameShort
                FROM Tournament
                WHERE ToId = " . intval($this->id);
        $rs = safe_r_sql($sql);
        if ($r = safe_fetch($rs)) {
            $this->name      = $r->ToName;
            $this->shortName = $r->ToNameShort;
            $this->code      = $r->ToCode;
        }

        $sql = "SELECT SesOrder, SesName
                FROM Session
                WHERE SesTournament = " . intval($this->id) . "
                AND SesType = 'Q'
                ORDER BY SesOrder";
        $rs = safe_r_sql($sql);
        while ($r = safe_fetch($rs)) {
            $s       = new stdClass();
            $s->id   = intval($r->SesOrder);
            $s->name = $r->SesName;
            $this->sessions[$s->id] = $s;
        }
    }
}

// ---------------------------------------------------------------
// QP_Blason : target face type information
// ---------------------------------------------------------------
class QP_Blason
{
    public $id           = 0;
    public $name         = '';
    public $classes      = '';
    public $targetName   = '';
    public $targetId     = 0;
    public $diameter     = 0;
    public $imgH         = 1;
    public $imgV         = 2;
    public $imgTaille    = 40; // display width in px (same logic as ImgTFace::taille)
    public $imgNbArcher  = 0;
    public $label        = '';
    public $svgFile      = '0.svg'; //'Empty.svg';
    public $count        = 0; // archer count
    public $physicalCount = 0; // number of physical target faces needed
    public $alias        = ''; // custom display name (empty = uses $name)
    public $distances    = []; // [dist => dist] unique distances in meters

    // ---------------------------------------------------------------
    // Custom aliases by targetName-diameter key
    // Edit here to rename a target face type in the summary
    // ---------------------------------------------------------------
    public static function aliasForKey(string $key): string
    {
        static $aliasMap = [];
        if(empty($aliasMap)) {
            $aliasMap = [
                'TrgIndComplete-40'  => get_text('TrgIndComplete'). ' 40cm',
                'TrgIndSmall-40'     => get_text('TrgIndSmall'). ' 40cm',
                'TrgCOIndSmall-40'   => get_text('TrgCOIndSmall'). ' 40cm',
                'TrgProAMIndVegasSmall-40'  => 'Vegas 40cm',
                'TrgIndComplete-60'  => get_text('TrgIndComplete'). ' 60cm',
                'TrgIndSmall-60'     => get_text('TrgIndSmall'). ' 60cm',
                'TrgIndComplete-80'  => get_text('TrgIndComplete'). ' 80cm',
                'TrgCOOutdoor-80'    => get_text('TrgCOOutdoor'). ' 80cm',
                'TrgOutdoor-80'      => get_text('TargetFace'). ' 80cm',
                'TrgOutdoor-122'     => get_text('TargetFace'). ' 122cm',
                'TrgFrBeursault-45'  => get_text('TrgFrBeursault'). ' 45cm',
            ];
        }

        return $aliasMap[$key] ?? '';
    }

    // Returns the display name: alias if set, otherwise $name
    public function displayName(): string
    {
        return ($this->alias !== '') ? $this->alias : $this->name;
    }

    /**
     * Physical compatibility key: two target faces with the same key
     * can coexist on the same column of a target.
     * CL and CO trispot 40 are physically identical → same key.
     */
    public function physicalCompatKey(): string
    {
        static $compatMap = [
            'TrgIndSmall-40'   => 'trispot40',
            'TrgCOIndSmall-40' => 'trispot40',
        ];
        $key = $this->targetName . '-' . $this->diameter;
        return $compatMap[$key] ?? $key;
    }

    // Returns SVG file + px size for a targetName-diameter key
    public static function svgForKey(string $key): string
    {
        static $svgMap = [
            'TrgIndComplete-40'  => '1.svg', //'D40.svg',
            'TrgIndSmall-40'     => '2.svg', //'D40TCL.svg',
            'TrgCOIndSmall-40'   =>  '4.svg', //'D40TCO.svg',
            'TrgProAMIndVegasSmall-40'  => '16.svg', //'D40V.svg',
            'TrgIndComplete-60'  => '1.svg', //'D60.svg',
            'TrgIndSmall-60'     => '2.svg', //'D60T.svg',
            'TrgIndComplete-80'  => '1.svg', //'D80.svg',
            'TrgCOOutdoor-80'    => '9.svg', //'D80R.svg',
            'TrgOutdoor-80'      => '1.svg', //'D80.svg',
            'TrgOutdoor-122'     => '5.svg', //'D122.svg',
            'TrgFrBeursault-45'  => '27.svg',//'Beursault.svg',
        ];
        return $svgMap[$key] ?? '0.svg'; //'Empty.svg';
    }

    // Returns the display width in px (same as ImgTFace::taille)
    public static function tailleForKey(string $key): int
    {
        static $tailleMap = [
            'TrgIndComplete-40'  => 40,
            'TrgIndSmall-40'     => 20,
            'TrgCOIndSmall-40'   => 20,
            'TrgProAMIndVegasSmall-40'  => 40,
            'TrgIndComplete-60'  => 60,
            'TrgIndSmall-60'     => 30,
            'TrgIndComplete-80'  => 80,
            'TrgCOOutdoor-80'    => 40,
            'TrgOutdoor-80'      => 80,
            'TrgOutdoor-122'     => 122,
            'TrgFrBeursault-45'  => 70,
        ];
        return $tailleMap[$key] ?? 40;
    }

    // Calculates archer count from h+v (ImgTFace::getNbArcher logic)
    public function calcNbArcher()
    {
        $sum = $this->imgV + $this->imgH;
        switch ($sum) {
            case 3: $this->imgNbArcher = 1; break;
            case 4: $this->imgNbArcher = 2; break;
            case 6: $this->imgNbArcher = 4; break;
            default: $this->imgNbArcher = 0;
        }
    }
}

// ---------------------------------------------------------------
// QP_Participant : registered archer
// ---------------------------------------------------------------
class QP_Participant
{
    public $id           = 0;
    public $structId     = 0;
    public $license      = '';
    public $structName   = '';
    public $arme         = '';
    public $classe       = '';
    public $nom          = '';
    public $prenom       = '';
    public $target       = 0;
    public $distance     = 0; // distance in meters (TournamentDistances.TdDist1 or Td1)
    public $letter       = '';
    public $targetId     = 0;
    public $blason       = null;  // QP_Blason
    public $isUnassigned = false; // true if QuSession = 0 (no session assigned)

    public function getCible(): string
    {
        return ($this->target > 0) ? $this->target . $this->letter : '';
    }

    public function getNomCourt(): string
    {
        return mb_substr($this->prenom, 0, 1) . '.' . $this->nom;
    }

    public function getCategory(): string
    {
        return $this->arme . $this->classe;
    }
}

// ---------------------------------------------------------------
// QP_Distance
// ---------------------------------------------------------------
class QP_Distance
{
    public $day          = '';
    public $warmStart    = '';
    public $start        = '';
    public $targets      = 0;
    public $distance     = 0;
    public $sameDistance = true;
    public $ath          = 0;
    public $id           = 0;
}

// ---------------------------------------------------------------
// QP_Vague : a position on a target (A/B/C/D)
// ---------------------------------------------------------------
class QP_Vague
{
    public $target  = 0;
    public $order   = 0;
    public $label   = '';
    public $overlay = false;
    public $participant = null; // QP_Participant|null
    public $blason      = null; // QP_Blason|null
}

// ---------------------------------------------------------------
// QP_Cat : category for the picking list
// ---------------------------------------------------------------
class QP_Cat
{
    public $name      = '';
    public $count     = 0;
    public $distances = []; // [dist => dist] unique distances in meters
}

// ---------------------------------------------------------------
// QP_Session : complete data for a qualification session
// ---------------------------------------------------------------
class QP_Session
{
    public $tour;            // QP_TourInfo
    public $name         = '';
    public $day          = '';
    public $warmStart    = '';
    public $start        = '';
    public $targets      = 0;
    public $ath          = 0;
    public $order        = 0;
    public $distances    = [];
    public $participants = [];
    public $blasons      = [];
    public $categories   = [];

    /**
     * @param int    $tId       Tournament ID
     * @param int    $sessOrder Session number
     * @param int    $tfId      Filter by face TfId (0 = all)
     * @param int    $cibleNum  Filter by target number (0 = all)
     * @param string $cat       Filter by category ('' = all)
     */
    public function __construct(int $tId, int $sessOrder = 1, int $tfId = 0, int $cibleNum = 0, string $cat = '', string $blasonAlias = '', int $distFilter = 0)
    {
        $this->tour  = new QP_TourInfo($tId);
        $this->order = $sessOrder;
        $this->loadSession();
        $this->loadBlasons();
        $this->loadParticipants($tfId, $cibleNum, $cat, $blasonAlias, $distFilter);
        usort($this->participants, fn($a, $b) => strcmp($a->structName, $b->structName));
        usort($this->categories,  fn($a, $b) => strcmp($a->name, $b->name));
    }

    private function loadSession()
    {
        $sql = "SELECT D.DiSession, D.DiDistance, D.DiDay, D.DiWarmStart, D.DiStart,
                       S.SesOrder, S.SesName, S.SesTar4Session, S.SesAth4Target
                FROM DistanceInformation D
                INNER JOIN Session S
                    ON S.SesType = 'Q'
                    AND S.SesOrder = D.DiSession
                    AND S.SesTournament = D.DiTournament
                WHERE D.DiTournament = " . intval($this->tour->id) . "
                  AND S.SesOrder = " . intval($this->order) . "
                ORDER BY D.DiSession, D.DiDistance";
        $rs = safe_r_sql($sql);
        $first = true;
        while ($r = safe_fetch($rs)) {
            if ($first) {
                $this->name      = $r->SesName;
                $this->day       = $r->DiDay;
                $this->warmStart = $r->DiWarmStart;
                $this->start     = $r->DiStart;
                $this->targets   = intval($r->SesTar4Session);
                $this->ath       = intval($r->SesAth4Target);
                $first = false;
            }
            $d              = new QP_Distance();
            $d->id          = $r->DiDistance;
            $d->day         = $r->DiDay;
            $d->warmStart   = $r->DiWarmStart;
            $d->start       = $r->DiStart;
            $d->targets     = intval($r->SesTar4Session);
            $d->ath         = intval($r->SesAth4Target);
            $this->distances[$d->id] = $d;
        }
    }

    private function loadBlasons()
    {
        // targetName-diameter → imgH, imgV, label mapping
        static $imgMap = [
            'TrgIndComplete-40'  => [1, 2, '⌀40'],
            'TrgIndSmall-40'     => [2, 1, 'CL'],
            'TrgCOIndSmall-40'   => [2, 1, 'CO'],
            'TrgProAMIndVegasSmall-40'  => [1, 2, 'Vegas'],
            'TrgIndComplete-60'  => [2, 2, '⌀60'],
            'TrgIndSmall-60'     => [2, 2, '⌀60T'],
            'TrgIndComplete-80'  => [2, 4, '⌀80'],
            'TrgCOOutdoor-80'    => [1, 2, '⌀80CO'],
            'TrgOutdoor-80'      => [2, 4, '⌀80'],
            'TrgOutdoor-122'     => [2, 4, '⌀122'],
            'TrgFrBeursault-45'  => [2, 4, 'Beursault'],
        ];

        $sql = "SELECT TF.TfId, TF.TfName, TF.TfClasses,
                       TF.TfT1, TF.TfW1,
                       T.TarId, T.TarDescr
                FROM TargetFaces TF
                INNER JOIN Targets T ON T.TarId = TF.TfT1
                WHERE TF.TfTournament = " . intval($this->tour->id) . "
                ORDER BY TF.TfId";
        $rs = safe_r_sql($sql);
        while ($r = safe_fetch($rs)) {
            $b              = new QP_Blason();
            $b->id          = intval($r->TfId);
            $b->name        = $r->TfName;
            $b->classes     = $r->TfClasses;
            $b->targetName  = $r->TarDescr;
            $b->targetId    = intval($r->TarId);
            $b->diameter    = intval($r->TfW1);
            $key            = $r->TarDescr . '-' . intval($r->TfW1);
            if (isset($imgMap[$key])) {
                $b->imgH  = $imgMap[$key][0];
                $b->imgV  = $imgMap[$key][1];
                $b->label = $imgMap[$key][2];
            } else {
                $b->imgH  = 1;
                $b->imgV  = 2;
                $b->label = '⌀' . $b->diameter;
            }
            $b->svgFile   = QP_Blason::svgForKey($key);
            $b->imgTaille = QP_Blason::tailleForKey($key);
            $b->alias     = QP_Blason::aliasForKey($key);
            $b->calcNbArcher();
            $this->blasons[$b->id] = $b;
        }
    }

    private function loadParticipants(int $tfId = 0, int $cibleNum = 0, string $cat = '', string $blasonAlias = '', int $distFilter = 0)
    {
        $sql = "SELECT E.EnId, E.EnCode, E.EnDivision, E.EnClass,
                       E.EnCountry, E.EnName, E.EnFirstName,
                       E.EnTargetFace,
                       C.CoName,
                       Q.QuSession, Q.QuTarget, Q.QuLetter,
                       TF.TfId, TF.TfName,
                       TD.TdDist1,
					   TD.Td1
                FROM Entries E
                INNER JOIN Countries C
                    ON E.EnCountry = C.CoId AND E.EnTournament = C.CoTournament
                INNER JOIN TargetFaces TF
                    ON E.EnTargetFace = TF.TfId AND E.EnTournament = TF.TfTournament
                INNER JOIN Qualifications Q
                    ON E.EnId = Q.QuId
                LEFT JOIN TournamentDistances TD
                    ON E.EnTournament = TD.TdTournament
                    AND CONCAT(TRIM(E.EnDivision), TRIM(E.EnClass)) LIKE TD.TdClasses
                WHERE E.EnAthlete = 1 AND E.EnTournament = " . intval($this->tour->id) . "
                  AND Q.QuSession = " . intval($this->order);

        if ($cibleNum > 0) {
            $sql .= " AND Q.QuTarget = " . intval($cibleNum);
        }
        $sql .= " ORDER BY Q.QuTarget, Q.QuLetter";

        $rs = safe_r_sql($sql);
        while ($r = safe_fetch($rs)) {
            // Filter by target face if requested
            if ($tfId > 0 && intval($r->TfId) !== $tfId) {
                continue;
            }
			$rDistance = intval($r->TdDist1);
			if($rDistance === 0) {
				$rDistance = intval($r->Td1);
			}
            $p              = new QP_Participant();
            $p->id          = intval($r->EnId);
            $p->structId    = intval($r->EnCountry);
            $p->targetId    = intval($r->TfId);
            $p->nom         = $r->EnFirstName;
            $p->prenom      = $r->EnName;
            $p->license     = $r->EnCode;
            $p->structName  = $r->CoName;
            $p->arme        = $r->EnDivision;
            $p->classe      = $r->EnClass;
            $p->target      = intval($r->QuTarget);
            $p->letter      = $r->QuLetter;
            $p->distance    = $rDistance;
            $p->blason      = $this->blasons[$p->targetId] ?? null;

            // Filter by category if requested
            if ($cat !== '' && $p->getCategory() !== $cat) {
                continue;
            }

            // Filter by target face alias (physical group) if requested
            if ($blasonAlias !== '' && ($p->blason === null || $p->blason->displayName() !== $blasonAlias)) {
                continue;
            }

            // Filter by distance if requested
            if ($distFilter > 0 && $p->distance !== $distFilter) {
                continue;
            }

            $catKey = $p->getCategory();
            if (!isset($this->categories[$catKey])) {
                $c        = new QP_Cat();
                $c->name  = $catKey;
                $this->categories[$catKey] = $c;
            }
            $this->categories[$catKey]->count++;
            if ($p->distance > 0) {
                $this->categories[$catKey]->distances[$p->distance] = $p->distance;
            }

            if (isset($this->blasons[$p->targetId])) {
                $b = $this->blasons[$p->targetId];
                $b->count++;
                if ($p->distance > 0) {
                    $b->distances[$p->distance] = $p->distance;
                }
                // Physical face count = number of distinct columns (target + group A/C or B/D)
                // using this face type.
                // For imgNbArcher=1 (H1V2, H2V1): 1 face per archer → physical count = archer count
                // For imgNbArcher>=2 (H2V2, H2V4): 1 face per column → count distinct columns
            }
            // Accumulate into a temporary array for post-load recalculation
            $this->participants[$p->id] = $p;
        }

        // Recalculate physicalCount after full load
        $colUsage   = []; // [tfId][target-column] → 60cm faces etc. (1 face per AC/BD column)
        $cibleUsage = []; // [tfId][target]        → large faces (1 face per target: 122, 80…)
        foreach ($this->participants as $p) {
            if (!isset($this->blasons[$p->targetId])) continue;
            $b = $this->blasons[$p->targetId];
            if ($b->imgNbArcher <= 1) {
                // 1 face per archer (40cm full, trispot, CO…)
                $b->physicalCount = $b->count;
            } elseif ($b->imgV >= 4) {
                // Full-target face (122cm, 80cm outdoor…):
                // all archers on the same target share 1 physical face.
                // Unplaced archers (target=0): unique key per archer to avoid under-counting.
                $cibleKey = $p->target > 0 ? $p->target : ('u' . $p->id);
                $cibleUsage[$p->targetId][$cibleKey] = true;
                $b->physicalCount = count($cibleUsage[$p->targetId]);
            } else {
                // Face per column (60cm…): 1 face per (target, AC/BD column) pair
                $groupe = in_array($p->letter, ['A', 'C']) ? 'AC' : 'BD';
                $key    = $p->target . '-' . $groupe;
                $colUsage[$p->targetId][$key] = true;
                $b->physicalCount = count($colUsage[$p->targetId]);
            }
        }
    }

    /** List of target faces in use with count > 0 */
    public function blasonCount(): array
    {
        return array_filter($this->blasons, fn($b) => $b->count > 0);
    }

    /**
     * List of target faces grouped by alias (displayName).
     * Faces sharing the same alias are merged: physicalCount summed,
     * imgTaille/svgFile/imgNbArcher taken from the first match.
     * Returns an array indexed by alias.
     */
    public function blasonCountGrouped(): array
    {
        $grouped = [];
        foreach ($this->blasons as $b) {
            if ($b->count <= 0) continue;
            $key = $b->displayName();
            if (!isset($grouped[$key])) {
                $g                = clone $b;
                $g->physicalCount = $b->physicalCount;
                $grouped[$key]    = $g;
            } else {
                $grouped[$key]->physicalCount += $b->physicalCount;
                $grouped[$key]->count         += $b->count;
                $grouped[$key]->distances     += $b->distances;
            }
        }
        return $grouped;
    }

    public function listByCategory(): array
    {
        return $this->categories;
    }

    /**
     * List of distinct (alias, distance) groups for the target face accordion.
     * The same face type shot at different distances produces N separate entries.
     * Returns [ key => ['alias'=>str, 'distance'=>int, 'blason'=>QP_Blason] ]
     * sorted by alias then ascending distance.
     */
    public function blasonDistanceGroups(): array
    {
        $groups = [];
        foreach ($this->participants as $p) {
            if (!isset($this->blasons[$p->targetId])) continue;
            $b     = $this->blasons[$p->targetId];
            $alias = $b->displayName();
            $dist  = $p->distance;
            $key   = $alias . '||' . $dist;
            if (!isset($groups[$key])) {
                $groups[$key] = ['alias' => $alias, 'distance' => $dist, 'blason' => $b];
            }
        }
        uasort($groups, function ($a, $b) {
            $cmp = strcmp($a['alias'], $b['alias']);
            return $cmp !== 0 ? $cmp : ($a['distance'] - $b['distance']);
        });
        return $groups;
    }
}

// ---------------------------------------------------------------
// QP_Cible : target detail (for AJAX)
// ---------------------------------------------------------------
class QP_Cible
{
    public $tour;
    public $num       = 0;
    public $ath       = 0;
    public $order     = 0;
    public $warnLevel = 0;
    public $distance;
    public $participants = [];
    public $blasons      = [];
    public $vagues       = [];

    private static $labels = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H'];

    public function __construct(int $tId, int $sessOrder = 1, int $cibleNum = 0)
    {
        $this->tour     = new QP_TourInfo($tId);
        $this->order    = $sessOrder;
        $this->num      = $cibleNum;
        $this->distance = new QP_Distance();
        $this->loadSession();
        $this->loadBlasons();
        $this->loadParticipants($cibleNum);
        $this->makeVagues();
        $this->setDistance();
        $this->setWarnLevel();
    }

    private function loadSession()
    {
        $sql = "SELECT D.DiDistance, D.DiDay, D.DiWarmStart, D.DiStart,
                       S.SesOrder, S.SesTar4Session, S.SesAth4Target
                FROM DistanceInformation D
                INNER JOIN Session S
                    ON S.SesType = 'Q'
                    AND S.SesOrder = D.DiSession
                    AND S.SesTournament = D.DiTournament
                WHERE D.DiTournament = " . intval($this->tour->id) . "
                  AND S.SesOrder = " . intval($this->order) . "
                ORDER BY D.DiSession, D.DiDistance";
        $rs = safe_r_sql($sql);
        if ($r = safe_fetch($rs)) {
            $this->ath                   = intval($r->SesAth4Target);
            $this->distance->id          = $r->DiDistance;
            $this->distance->day         = $r->DiDay;
            $this->distance->warmStart   = $r->DiWarmStart;
            $this->distance->start       = $r->DiStart;
            $this->distance->targets     = intval($r->SesTar4Session);
            $this->distance->ath         = intval($r->SesAth4Target);
        }
    }

    private function loadBlasons()
    {
        static $imgMap = [
            'TrgIndComplete-40'  => [1, 2, '⌀40'],
            'TrgIndSmall-40'     => [2, 1, 'CL'],
            'TrgCOIndSmall-40'   => [2, 1, 'CO'],
            'TrgProAMIndVegasSmall-40'  => [1, 2, 'Vegas'],
            'TrgIndComplete-60'  => [2, 2, '⌀60'],
            'TrgIndSmall-60'     => [2, 2, '⌀60T'],
            'TrgIndComplete-80'  => [2, 4, '⌀80'],
            'TrgCOOutdoor-80'    => [1, 2, '⌀80CO'],
            'TrgOutdoor-80'      => [2, 4, '⌀80'],
            'TrgOutdoor-122'     => [2, 4, '⌀122'],
            'TrgFrBeursault-45'  => [2, 4, 'Beursault'],
        ];
        $sql = "SELECT TF.TfId, TF.TfName, TF.TfClasses,
                       TF.TfT1, TF.TfW1, T.TarId, T.TarDescr
                FROM TargetFaces TF
                INNER JOIN Targets T ON T.TarId = TF.TfT1
                WHERE TF.TfTournament = " . intval($this->tour->id) . "
                ORDER BY TF.TfId";
        $rs = safe_r_sql($sql);
        while ($r = safe_fetch($rs)) {
            $b             = new QP_Blason();
            $b->id         = intval($r->TfId);
            $b->name       = $r->TfName;
            $b->classes    = $r->TfClasses;
            $b->targetName = $r->TarDescr;
            $b->targetId   = intval($r->TarId);
            $b->diameter   = intval($r->TfW1);
            $key           = $r->TarDescr . '-' . intval($r->TfW1);
            if (isset($imgMap[$key])) {
                $b->imgH  = $imgMap[$key][0];
                $b->imgV  = $imgMap[$key][1];
                $b->label = $imgMap[$key][2];
            } else {
                $b->imgH  = 1;
                $b->imgV  = 2;
                $b->label = '⌀' . $b->diameter;
            }
            $b->svgFile   = QP_Blason::svgForKey($key);
            $b->imgTaille = QP_Blason::tailleForKey($key);
            $b->alias     = QP_Blason::aliasForKey($key);
            $b->calcNbArcher();
            $this->blasons[$b->id] = $b;
        }
    }

    private function loadParticipants(int $cibleNum)
    {
        $sql = "SELECT E.EnId, E.EnCode, E.EnDivision, E.EnClass,
                       E.EnCountry, E.EnName, E.EnFirstName, E.EnTargetFace,
                       C.CoName,
                       Q.QuSession, Q.QuTarget, Q.QuLetter,
                       TF.TfId,
                       TD.TdDist1,
                       TD.Td1
                FROM Entries E
                INNER JOIN Countries C
                    ON E.EnCountry = C.CoId AND E.EnTournament = C.CoTournament
                INNER JOIN TargetFaces TF
                    ON E.EnTargetFace = TF.TfId AND E.EnTournament = TF.TfTournament
                INNER JOIN Qualifications Q
                    ON E.EnId = Q.QuId
                INNER JOIN TournamentDistances TD
                    ON E.EnTournament = TD.TdTournament
                    AND CONCAT(TRIM(E.EnDivision), TRIM(E.EnClass)) LIKE TD.TdClasses
                WHERE E.EnAthlete = 1 AND E.EnTournament = " . intval($this->tour->id) . "
                  AND Q.QuSession = " . intval($this->order);
        if ($cibleNum > 0) {
            $sql .= " AND Q.QuTarget = " . intval($cibleNum);
        }
        $sql .= " ORDER BY Q.QuTarget, Q.QuLetter";

        $rs = safe_r_sql($sql);
        while ($r = safe_fetch($rs)) {
			$rDistance = intval($r->TdDist1);
			if($rDistance === 0) {
				$rDistance = intval($r->Td1);
			}
            $p             = new QP_Participant();
            $p->id         = intval($r->EnId);
            $p->structId   = intval($r->EnCountry);
            $p->targetId   = intval($r->TfId);
            $p->nom        = $r->EnFirstName;
            $p->prenom     = $r->EnName;
            $p->license    = $r->EnCode;
            $p->structName = $r->CoName;
            $p->arme       = $r->EnDivision;
            $p->classe     = $r->EnClass;
            $p->target     = intval($r->QuTarget);
            $p->distance   =  $rDistance;
            $p->letter     = $r->QuLetter;
            $p->blason     = $this->blasons[$p->targetId] ?? null;
            $this->participants[$p->id] = $p;
            if (isset($this->blasons[$p->targetId])) {
                $this->blasons[$p->targetId]->count++;
            }
        }
    }

    private function setDistance()
    {
        $distList = array_unique(array_map(fn($p) => $p->distance, $this->participants));
        if (count($distList) === 1) {
            $this->distance->distance     = reset($distList);
            $this->distance->sameDistance = true;
        } elseif (count($distList) > 1) {
            $this->distance->distance     = reset($distList);
            $this->distance->sameDistance = false;
        }
    }

    private function setWarnLevel()
    {
        $count = count($this->participants);
        if ($count === 0) {
            $this->warnLevel = 0; // Free
            return;
        }
        $full    = ($this->ath > 0 && $count >= $this->ath);
        $structs = array_unique(array_map(fn($p) => $p->structName, $this->participants));
        $oneStruct = (count($structs) === 1);

        // Majority: one structure represents more than half of the present archers
        $majority = false;
        foreach ($structs as $struct) {
            $n = count(array_filter($this->participants, fn($p) => $p->structName === $struct));
            if ($n > ($count / 2)) {
                $majority = true;
            }
        }

        if (!$this->distance->sameDistance) {
            $this->warnLevel = 4; // Mixed distances
        } elseif ($oneStruct) {
            $this->warnLevel = 3; // Single structure (full or not)
        } elseif ($majority) {
            $this->warnLevel = 2; // Majority structure
        } elseif ($full) {
            $this->warnLevel = 1; // Full
        } else {
            $this->warnLevel = 0; // Free
        }

        // Incompatible face: maximum priority (overrides other levels)
        if ($this->checkBlasonIncompatibility()) {
            $this->warnLevel = 5;
        }

        // Duplicate position: two archers on the same letter
        $letters = array_filter(array_map(fn($p) => $p->letter, $this->participants), fn($l) => $l !== '');
        if (count($letters) !== count(array_unique($letters))) {
            $this->warnLevel = 6;
        }
    }

    /**
     * Detects face incompatibilities on the target:
     * - Two different faces in the same column (A/C or B/D)
     * - Two different full-width faces (imgV>=4) across columns
     */
    private function checkBlasonIncompatibility(): bool
    {
        // Only waves with a real archer (not overlay)
        $real = array_filter($this->vagues, fn($v) => isset($v->participant) && isset($v->blason) && !$v->overlay);
        if (count($real) <= 1) return false;

        $colAC = array_filter($real, fn($v) => in_array($v->order, [1, 3]));
        $colBD = array_filter($real, fn($v) => in_array($v->order, [2, 4]));

        // Distinct faces in the same column (by physical key)
        if (count(array_unique(array_map(fn($v) => $v->blason->physicalCompatKey(), $colAC))) > 1) return true;
        if (count(array_unique(array_map(fn($v) => $v->blason->physicalCompatKey(), $colBD))) > 1) return true;

        // Different full-width faces (imgV>=4) across the two columns
        $bAC = !empty($colAC) ? array_values($colAC)[0]->blason : null;
        $bBD = !empty($colBD) ? array_values($colBD)[0]->blason : null;
        if ($bAC && $bBD && $bAC->physicalCompatKey() !== $bBD->physicalCompatKey()) {
            if ($bAC->imgV >= 4 || $bBD->imgV >= 4) return true;
        }

        return false;
    }

    public function makeVagues()
    {
        for ($i = 1; $i <= $this->ath; $i++) {
            $v          = new QP_Vague();
            $v->target  = $this->num;
            $v->order   = $i;
            $v->label   = self::$labels[$i] ?? (string)$i;
            foreach ($this->participants as $p) {
                if ($p->target == $this->num && $p->letter === $v->label) {
                    $v->participant = $p;
                    $v->blason      = $p->blason;
                    break;
                }
            }
            $this->vagues[$i] = $v;
        }

        // Intra-column propagation only:
        // If a column (A/C or B/D) has at least 1 archer, propagate its face
        // to the empty positions of THE SAME column with overlay=true.
        // A completely empty column receives NOTHING (we don't know which face will come).
        $blasonAC = null; // reference face for the A/C column (orders 1,3)
        $blasonBD = null; // reference face for the B/D column (orders 2,4)
        foreach ($this->vagues as $v) {
            if (isset($v->blason) && !$v->overlay && in_array($v->order, [1, 3]) && $blasonAC === null) {
                $blasonAC = $v->blason;
            }
            if (isset($v->blason) && !$v->overlay && in_array($v->order, [2, 4]) && $blasonBD === null) {
                $blasonBD = $v->blason;
            }
        }
        // Propagate only to empty slots in the same column
        foreach ($this->vagues as $v) {
            if (isset($v->blason)) continue; // already assigned
            if (in_array($v->order, [1, 3]) && $blasonAC !== null) {
                $v->blason  = $blasonAC;
                $v->overlay = true;
            } elseif (in_array($v->order, [2, 4]) && $blasonBD !== null) {
                $v->blason  = $blasonBD;
                $v->overlay = true;
            }
            // Completely empty column → propagate nothing
        }
    }

    public function getVaguesOrdered(): array
    {
        if (count($this->vagues) > 4) {
            return [$this->vagues];
        }
        $vAC = array_values(array_filter($this->vagues, fn($v) => in_array($v->order, [1, 3])));
        $vBD = array_values(array_filter($this->vagues, fn($v) => in_array($v->order, [2, 4])));
        return [$vAC, $vBD];
    }

    /**
     * Returns true if the target uses the special 3-archer ABC layout (H1V2):
     * B top-center, A bottom-left, C bottom-right.
     * Activates when ath=3 and no present face is of a type other than H1V2.
     */
    public function is3ArcherH1V2Layout(): bool
    {
        if ($this->ath !== 3) return false;
        foreach ($this->vagues as $v) {
            if (isset($v->blason) && !$v->overlay) {
                if ($v->blason->imgH !== 1 || $v->blason->imgV !== 2) return false;
            }
        }
        return true;
    }

    public function clear()
    {
        foreach ($this->participants as $p) {
            $sql = "UPDATE Qualifications
                    SET QuTarget = '0', QuLetter = '', QuTargetNo = ''
                    WHERE QuId = " . intval($p->id) . "
                      AND QuTarget = " . intval($this->num) . "
                      AND QuSession = " . intval($this->order);
            safe_w_sql($sql);
        }
    }
}

// ---------------------------------------------------------------
// QP_UpdateParticipant : move/assign an archer
// ---------------------------------------------------------------
class QP_UpdateParticipant
{
    private $tour;
    private $participant = null;
    private $order;
    private static $letters = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H'];

    public function __construct(int $tId, int $partId, int $sessOrder = 1)
    {
        $this->tour  = new QP_TourInfo($tId);
        $this->order = $sessOrder;
        $this->loadParticipant($partId);
    }

    private function loadParticipant(int $partId)
    {
        // Look in the current session OR among archers without a session (QuSession=0)
        $sql = "SELECT E.EnId, Q.QuTarget, Q.QuLetter, Q.QuSession
                FROM Entries E
                INNER JOIN Qualifications Q ON E.EnId = Q.QuId
                WHERE E.EnAthlete = 1 AND  E.EnTournament = " . intval($this->tour->id) . "
                  AND (Q.QuSession = " . intval($this->order) . " OR Q.QuSession = 0)
                  AND E.EnId = " . intval($partId);
        $rs = safe_r_sql($sql);
        if ($r = safe_fetch($rs)) {
            $p               = new QP_Participant();
            $p->id           = intval($r->EnId);
            $p->target       = intval($r->QuTarget);
            $p->letter       = $r->QuLetter;
            $p->isUnassigned = (intval($r->QuSession) === 0);
            $this->participant = $p;
        }
    }

    public function updateParticipant(int $cNum, int $cLetter)
    {
        if (!$this->participant) return;

        $letterStr = self::$letters[$cLetter] ?? '';

        if ($cNum === 0) {
            $targetNo  = '';
            $letterStr = '';
        } else {
            $targetNo = $this->order . str_pad((string)$cNum, 3, '0', STR_PAD_LEFT) . $letterStr;
            // Free the spot if already occupied
            $existing = $this->getExistingAtSpot($cNum, $letterStr);
            foreach ($existing as $eid) {
                $sql = "UPDATE Qualifications
                        SET QuTarget = '0', QuLetter = '', QuTargetNo = ''
                        WHERE QuId = " . intval($eid) . "
                          AND QuSession = " . intval($this->order);
                safe_w_sql($sql);
            }
        }

        $cNumSql    = ($cNum === 0) ? "'0'" : intval($cNum);
        $letterSafe = StrSafe_DB($letterStr);
        $tnoSafe    = StrSafe_DB($targetNo);

        if ($this->participant->isUnassigned && $cNum > 0) {
            // Unassigned archer: assign to the current session AND to the target
            $sql = "UPDATE Qualifications
                    SET QuSession = " . intval($this->order) . ",
                        QuTarget = $cNumSql,
                        QuLetter = $letterSafe,
                        QuTargetNo = $tnoSafe
                    WHERE QuId = " . intval($this->participant->id);
        } else {
            $sql = "UPDATE Qualifications
                    SET QuTarget = $cNumSql,
                        QuLetter = $letterSafe,
                        QuTargetNo = $tnoSafe
                    WHERE QuId = " . intval($this->participant->id) . "
                      AND QuSession = " . intval($this->order);
        }
        safe_w_sql($sql);
    }

    private function getExistingAtSpot(int $cNum, string $letter): array
    {
        $arr = [];
        $sql = "SELECT E.EnId
                FROM Entries E
                INNER JOIN Qualifications Q ON E.EnId = Q.QuId
                WHERE E.EnAthlete = 1 AND E.EnTournament = " . intval($this->tour->id) . "
                  AND Q.QuSession = " . intval($this->order) . "
                  AND Q.QuTarget = " . intval($cNum) . "
                  AND Q.QuLetter = " . StrSafe_DB($letter);
        $rs = safe_r_sql($sql);
        while ($r = safe_fetch($rs)) {
            $arr[] = intval($r->EnId);
        }
        return $arr;
    }
}
