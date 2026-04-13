<?php
require_once(dirname(__FILE__, 3) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');
require_once('Common/Lib/CommonLib.php');

CheckTourSession(true);
checkACL(AclCompetition, AclReadOnly);

require_once(__DIR__ . '/lib/models.php');

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$tourId = intval($_SESSION['TourId']);

switch ($action) {

    // ------------------------------------------------------------------
    // getData : retourne le plan complet (créneaux + blocs + cibles)
    // ------------------------------------------------------------------
    case 'getData':
        $plan = new PF_Plan($tourId);
        echo json_encode($plan->toJson());
        break;

    // ------------------------------------------------------------------
    // save : sauvegarde le plan (POST JSON)
    // ------------------------------------------------------------------
    case 'save':
        checkACL(AclCompetition, AclReadWrite);
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            break;
        }
        $saver  = new PF_Saver($tourId);
        $errors = $saver->save($data);
        echo json_encode(['ok' => true, 'errors' => $errors]);
        break;

    // ------------------------------------------------------------------
    // debugFS : retourne les enregistrements FinSchedule du tournoi
    // (debug uniquement – à supprimer en production)
    // ------------------------------------------------------------------
    case 'debugFS':
        $rs   = safe_r_sql("SELECT FSEvent, FSMatchNo, FSTeamEvent,
                                   FSScheduledDate, FSScheduledTime, FSTarget, FsLetter
                            FROM FinSchedule
                            WHERE FSTournament=" . intval($tourId) . "
                            ORDER BY FSEvent, FSMatchNo");
        $rows = [];
        while ($r = safe_fetch($rs)) {
            $rows[] = [
                'ev'     => $r->FSEvent,
                'match'  => $r->FSMatchNo,
                'team'   => $r->FSTeamEvent,
                'date'   => $r->FSScheduledDate,
                'time'   => $r->FSScheduledTime,
                'target' => $r->FSTarget,
                'letter' => $r->FsLetter,
            ];
        }
        echo json_encode(['count' => count($rows), 'rows' => $rows]);
        break;

    // ------------------------------------------------------------------
    // getBlasonSvg : retourne le SVG d'un blason (pour affichage dans les tuiles)
    // ------------------------------------------------------------------
    case 'getTargetFaces':
        // Retourne la liste des TargetFaces
        $rs = safe_r_sql("SELECT T.TarId, T.TarDescr,E.EvTargetSize
                          FROM Events E
                          LEFT JOIN Targets T ON E.EvFinalTargetType = T.TarId 
                          WHERE E.EvTournament=" . intval($tourId)."
                          ORDER BY T.TarId");
        $faces = [];
        while ($r = safe_fetch($rs)) {
            $key  = $r->TarDescr . '-' . intval($r->EvTargetSize);
            $svgMap = [
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
            $faces[$r->TarId] = [
                'id'      => $r->TarId,
                'name'    => $r->TarDescr,
                'svg'     => $svgMap[$key] ?? '0.svg',
                'classes' => 'None',
            ];
        }
        // Associer chaque événement à son TargetFace
        $evFaces = [];
        $rs2 = safe_r_sql("SELECT EvCode, EvTeamEvent,EvFinalTargetType
                           FROM Events 
                           WHERE EvTournament = " . intval($tourId) . "
                           AND EvFinalFirstPhase != 0");
        while ($r = safe_fetch($rs2)) {
            $tfId = intval($r->EvFinalTargetType);
            $evFaces[$r->EvCode] = isset($faces[$tfId]) ? $faces[$tfId]['svg'] : '0.svg';
        }
        echo json_encode(['faces' => $faces, 'eventFaces' => $evFaces]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
        break;
}
