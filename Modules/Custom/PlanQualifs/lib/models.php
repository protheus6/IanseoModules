<?php
/**
 * Modèles pour le module PlanQualifs
 *
 */

// ---------------------------------------------------------------
// QP_TourInfo : infos tournoi + liste des sessions
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
// QP_Blason : informations sur un type de blason
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
    public $imgTaille    = 40; // largeur d'affichage en px (même logique que ImgTFace::taille)
    public $imgNbArcher  = 0;
    public $label        = '';
    public $svgFile      = '0.svg'; //'Empty.svg';
    public $count        = 0; // nb archers
    public $physicalCount = 0; // nb blasons physiques nécessaires
    public $alias        = ''; // nom d'affichage personnalisé (vide = utilise $name)

    // ---------------------------------------------------------------
    // Alias personnalisés par clé targetName-diameter
    // Modifier ici pour renommer un type de blason dans le récap
    // ---------------------------------------------------------------
    public static function aliasForKey(string $key): string
    {
        static $aliasMap = [
            'TrgIndComplete-40'  => 'Blason 40cm',
            'TrgIndSmall-40'     => 'Trispot 40cm',
            'TrgCOIndSmall-40'   => 'Trispot CO 40cm',
            'TrgProAMIndVegasSmall-40'  => 'Vegas 40cm',
            'TrgIndComplete-60'  => 'Blason 60cm',
            'TrgIndSmall-60'     => 'Trispot 60cm',
            'TrgIndComplete-80'  => 'Blason 80cm',
            'TrgCOOutdoor-80'    => 'Blason CO 80cm',
            'TrgOutdoor-80'      => 'Blason 80cm',
            'TrgOutdoor-122'     => 'Blason 122cm',
            'TrgFrBeursault-45'  => 'Beursault 45cm',
        ];
        return $aliasMap[$key] ?? '';
    }

    // Retourne le nom à afficher : alias si défini, sinon $name
    public function displayName(): string
    {
        return ($this->alias !== '') ? $this->alias : $this->name;
    }

    /**
     * Clé de compatibilité physique : deux blasons avec la même clé
     * peuvent coexister sur la même colonne d'une cible.
     * CL et CO trispot 40 sont physiquement identiques → même clé.
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

    // Retourne fichier SVG + taille px pour une clé targetName-diameter
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

    // Retourne la largeur d'affichage en px (identique à ImgTFace::taille)
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

    // Calcule le nb d'archers d'après h+v (logique ImgTFace::getNbArcher)
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
// QP_Participant : archer inscrit
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
    public $distance     = 0;
    public $letter       = '';
    public $targetId     = 0;
    public $blason       = null;  // QP_Blason
    public $isUnassigned = false; // vrai si QuSession = 0 (aucun départ affecté)

    public function getCible(): string
    {
        return ($this->target > 0) ? $this->target . $this->letter : '';
    }

    public function getNomCourt(): string
    {
        return substr($this->prenom, 0, 1) . '.' . $this->nom;
    }

    public function getCategory(): string
    {
        return $this->classe . $this->arme;
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
// QP_Vague : une position sur une cible (A/B/C/D)
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
// QP_Cat : catégorie pour la picking list
// ---------------------------------------------------------------
class QP_Cat
{
    public $name  = '';
    public $count = 0;
}

// ---------------------------------------------------------------
// QP_Session : données complètes d'une session de qualification
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
     * @param int    $tId       ID tournoi
     * @param int    $sessOrder Numéro de session
     * @param int    $tfId      Filtrer par TfId blason (0 = tous)
     * @param int    $cibleNum  Filtrer par numéro de cible (0 = toutes)
     * @param string $cat       Filtrer par catégorie ('' = toutes)
     */
    public function __construct(int $tId, int $sessOrder = 1, int $tfId = 0, int $cibleNum = 0, string $cat = '', string $blasonAlias = '')
    {
        $this->tour  = new QP_TourInfo($tId);
        $this->order = $sessOrder;
        $this->loadSession();
        $this->loadBlasons();
        $this->loadParticipants($tfId, $cibleNum, $cat, $blasonAlias);
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
        // Correspondance targetName-diameter → imgH, imgV, label
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

    private function loadParticipants(int $tfId = 0, int $cibleNum = 0, string $cat = '', string $blasonAlias = '')
    {
        $sql = "SELECT E.EnId, E.EnCode, E.EnDivision, E.EnClass,
                       E.EnCountry, E.EnName, E.EnFirstName,
                       E.EnTargetFace,
                       C.CoName,
                       Q.QuSession, Q.QuTarget, Q.QuLetter,
                       TF.TfId, TF.TfName
                FROM Entries E
                INNER JOIN Countries C
                    ON E.EnCountry = C.CoId AND E.EnTournament = C.CoTournament
                INNER JOIN TargetFaces TF
                    ON E.EnTargetFace = TF.TfId AND E.EnTournament = TF.TfTournament
                INNER JOIN Qualifications Q
                    ON E.EnId = Q.QuId
                WHERE E.EnAthlete = 1 AND E.EnTournament = " . intval($this->tour->id) . "
                  AND Q.QuSession = " . intval($this->order);

        if ($cibleNum > 0) {
            $sql .= " AND Q.QuTarget = " . intval($cibleNum);
        }
        $sql .= " ORDER BY Q.QuTarget, Q.QuLetter";

        $rs = safe_r_sql($sql);
        while ($r = safe_fetch($rs)) {
            // Filtre par blason si demandé
            if ($tfId > 0 && intval($r->TfId) !== $tfId) {
                continue;
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
            $p->blason      = $this->blasons[$p->targetId] ?? null;

            // Filtre par catégorie si demandé
            if ($cat !== '' && $p->getCategory() !== $cat) {
                continue;
            }

            // Filtre par alias de blason (groupe physique) si demandé
            if ($blasonAlias !== '' && ($p->blason === null || $p->blason->displayName() !== $blasonAlias)) {
                continue;
            }

            $catKey = $p->getCategory();
            if (!isset($this->categories[$catKey])) {
                $c        = new QP_Cat();
                $c->name  = $catKey;
                $this->categories[$catKey] = $c;
            }
            $this->categories[$catKey]->count++;

            if (isset($this->blasons[$p->targetId])) {
                $b = $this->blasons[$p->targetId];
                $b->count++;
                // Nb blasons physiques = nb de colonnes distinctes (cible + groupe A/C ou B/D)
                // qui utilisent ce type de blason.
                // Pour imgNbArcher=1 (H1V2, H2V1) : 1 blason par archer → count physique = count archers
                // Pour imgNbArcher>=2 (H2V2, H2V4) : 1 blason par colonne → compter les colonnes uniques
            }
            // Accumulation dans un tableau temporaire pour recalcul post-chargement
            $this->participants[$p->id] = $p;
        }

        // Recalcul physicalCount après chargement complet
        // Pour chaque blason : compter les colonnes distinctes (target+groupe) qui l'utilisent
        $colUsage = []; // [tfId][target-groupe] = true
        foreach ($this->participants as $p) {
            if (!isset($this->blasons[$p->targetId])) continue;
            $b = $this->blasons[$p->targetId];
            if ($b->imgNbArcher <= 1) {
                // 1 blason par archer : physicalCount = count archers
                $b->physicalCount = $b->count;
            } else {
                // 1 blason par colonne : compter les paires (target, groupe AC/BD) distinctes
                $groupe = in_array($p->letter, ['A', 'C']) ? 'AC' : 'BD';
                $key    = $p->target . '-' . $groupe;
                $colUsage[$p->targetId][$key] = true;
                $b->physicalCount = count($colUsage[$p->targetId]);
            }
        }
    }

    /** Liste des blasons utilisés avec count > 0 */
    public function blasonCount(): array
    {
        return array_filter($this->blasons, fn($b) => $b->count > 0);
    }

    /**
     * Liste des blasons regroupés par alias (displayName).
     * Les blasons ayant le même alias sont fusionnés : physicalCount sommé,
     * imgTaille/svgFile/imgNbArcher pris du premier trouvé.
     * Retourne un tableau indexé par alias.
     */
    public function blasonCountGrouped(): array
    {
        $grouped = [];
        foreach ($this->blasons as $b) {
            if ($b->count <= 0) continue;
            $key = $b->displayName();
            if (!isset($grouped[$key])) {
                // Clone léger : copie des propriétés utiles
                $g               = clone $b;
                $g->physicalCount = $b->physicalCount;
                $grouped[$key]   = $g;
            } else {
                $grouped[$key]->physicalCount += $b->physicalCount;
                $grouped[$key]->count         += $b->count;
            }
        }
        return $grouped;
    }

    public function listByCategory(): array
    {
        return $this->categories;
    }
}

// ---------------------------------------------------------------
// QP_Cible : détail d'une cible (pour AJAX)
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
                       TD.TdDist1
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
            $p->distance   = intval($r->TdDist1);
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
            $this->warnLevel = 0; // Libre
            return;
        }
        $full    = ($this->ath > 0 && $count >= $this->ath);
        $structs = array_unique(array_map(fn($p) => $p->structName, $this->participants));
        $oneStruct = (count($structs) === 1);

        // Majorité : une structure représente plus de la moitié des archers présents
        $majority = false;
        foreach ($structs as $struct) {
            $n = count(array_filter($this->participants, fn($p) => $p->structName === $struct));
            if ($n > ($count / 2)) {
                $majority = true;
            }
        }

        if (!$this->distance->sameDistance) {
            $this->warnLevel = 4; // Distances mixtes
        } elseif ($oneStruct) {
            $this->warnLevel = 3; // Structure unique (pleine ou pas)
        } elseif ($majority) {
            $this->warnLevel = 2; // Structure majoritaire
        } elseif ($full) {
            $this->warnLevel = 1; // Complète
        } else {
            $this->warnLevel = 0; // Libre
        }

        // Blason incompatible : priorité maximale (écrase les autres niveaux)
        if ($this->checkBlasonIncompatibility()) {
            $this->warnLevel = 5;
        }
    }

    /**
     * Détecte les incompatibilités de blasons sur la cible :
     * - Deux blasons différents dans la même colonne (A/C ou B/D)
     * - Deux blasons pleine largeur (imgV>=4) différents entre les colonnes
     */
    private function checkBlasonIncompatibility(): bool
    {
        // Uniquement les vagues avec un archer réel (pas overlay)
        $real = array_filter($this->vagues, fn($v) => isset($v->participant) && isset($v->blason) && !$v->overlay);
        if (count($real) <= 1) return false;

        $colAC = array_filter($real, fn($v) => in_array($v->order, [1, 3]));
        $colBD = array_filter($real, fn($v) => in_array($v->order, [2, 4]));

        // Blasons distincts dans la même colonne (par clé physique)
        if (count(array_unique(array_map(fn($v) => $v->blason->physicalCompatKey(), $colAC))) > 1) return true;
        if (count(array_unique(array_map(fn($v) => $v->blason->physicalCompatKey(), $colBD))) > 1) return true;

        // Blasons pleine largeur (imgV>=4) différents entre les deux colonnes
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

        // Propagation intra-colonne uniquement :
        // Si une colonne (A/C ou B/D) a au moins 1 archer, propager son blason
        // aux positions vides de LA MÊME colonne avec overlay=true.
        // Une colonne entièrement vide ne reçoit RIEN (on ne sait pas quel blason viendra).
        $blasonAC = null; // blason de référence pour la colonne A/C (orders 1,3)
        $blasonBD = null; // blason de référence pour la colonne B/D (orders 2,4)
        foreach ($this->vagues as $v) {
            if (isset($v->blason) && !$v->overlay && in_array($v->order, [1, 3]) && $blasonAC === null) {
                $blasonAC = $v->blason;
            }
            if (isset($v->blason) && !$v->overlay && in_array($v->order, [2, 4]) && $blasonBD === null) {
                $blasonBD = $v->blason;
            }
        }
        // Propager uniquement vers les vides de la même colonne
        foreach ($this->vagues as $v) {
            if (isset($v->blason)) continue; // déjà assigné
            if (in_array($v->order, [1, 3]) && $blasonAC !== null) {
                $v->blason  = $blasonAC;
                $v->overlay = true;
            } elseif (in_array($v->order, [2, 4]) && $blasonBD !== null) {
                $v->blason  = $blasonBD;
                $v->overlay = true;
            }
            // Colonne entièrement vide → on ne propage rien
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
     * Retourne true si la cible utilise le layout spécial 3 archers ABC (H1V2) :
     * B en haut-centre, A en bas-gauche, C en bas-droite.
     * S'active quand ath=3 et qu'aucun blason présent n'est d'un type autre que H1V2.
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
// QP_UpdateParticipant : déplacer/affecter un archer
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
        // Chercher dans la session courante OU parmi les archers sans départ (QuSession=0)
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
            // Libérer la place si déjà occupée
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
            // Archer sans départ : affecter au départ courant ET à la cible
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
