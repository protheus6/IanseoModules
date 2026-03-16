<?php
/**
 * AjaxFinStatus.php — Retourne le statut (done/ongoing/waiting) de chaque
 * cellule du tableau des feuilles de marque des finales (individuelles + équipes).
 * Appelé en AJAX par Index.php pour le rafraîchissement automatique.
 *
 * Réponse JSON :
 * { "ind":  { "SessionKey": { "EvCode": "done|ongoing|waiting", ... }, ... },
 *   "team": { "SessionKey": { "EvCode": "done|ongoing|waiting", ... }, ... } }
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');
require_once('Common/Lib/Fun_Phases.inc.php');

CheckTourSession(true);
header('Content-Type: application/json; charset=utf-8');

// ── Récupération des filtres (identiques à ceux de Index.php) ────────────────
$fscOnlyToday  = !empty($_GET['fsc_today']);
$fscUnfinished = !empty($_GET['fsc_unfinished']);
$fscToday      = date('Y-m-d');
$fstOnlyToday  = !empty($_GET['fst_today']);
$fstUnfinished = !empty($_GET['fst_unfinished']);
$fstToday      = date('Y-m-d');

// ═══════════════════════════════════════════════════════════════════════════════
// FINALES INDIVIDUELLES
// ═══════════════════════════════════════════════════════════════════════════════
$fscWhere = 'FSTournament=' . StrSafe_DB($_SESSION['TourId']) . ' AND FSScheduledDate>0';
if ($fscOnlyToday)  $fscWhere .= ' AND FSScheduledDate=\'' . $fscToday . '\'';
if ($fscUnfinished) $fscWhere .=
	' AND (FSEvent, FSMatchNo) IN ('
	. ' SELECT f1.FinEvent, f1.FinMatchNo FROM Finals f1'
	. ' INNER JOIN Finals f2 ON f2.FinEvent=f1.FinEvent AND f2.FinMatchNo=f1.FinMatchNo+1 AND f2.FinTournament=f1.FinTournament'
	. ' INNER JOIN Events ON EvTournament=f1.FinTournament AND EvCode=f1.FinEvent AND EvTeamEvent=0'
	. ' INNER JOIN Grids ON GrMatchNo=f1.FinMatchNo'
	. ' WHERE f1.FinTournament=' . StrSafe_DB($_SESSION['TourId'])
	. '   AND f1.FinMatchNo%2=0'
	. '   AND ((GREATEST(f1.FinAthlete,f2.FinAthlete)=0 AND GrPhase<=EvFinalFirstPhase)'
	. '     OR (GREATEST(f1.FinWinLose,f2.FinWinLose,f1.FinTie,f2.FinTie)=0 AND GREATEST(f1.FinAthlete,f2.FinAthlete)>0))'
	. ')';

$fscQuery = 'SELECT'
	. ' CONCAT(\'I\', DATE_FORMAT(FSScheduledDate,\'%Y-%m-%d\'), DATE_FORMAT(FSScheduledTime,\'%H:%i:%s\')) AS SessionKey,'
	. ' FSEvent AS Event,'
	. ' CASE WHEN MAX(IF(f1.FinAthlete=0,0,1))=0 THEN \'waiting\''
	. '      WHEN MAX(IF(GREATEST(f1.FinWinLose,COALESCE(f2.FinWinLose,0),f1.FinTie,COALESCE(f2.FinTie,0))=0,1,0))=0 THEN \'done\''
	. '      ELSE \'ongoing\' END AS Status'
	. ' FROM FinSchedule'
	. ' INNER JOIN Finals AS f1 ON f1.FinEvent=FSEvent AND f1.FinMatchNo=FSMatchNo AND f1.FinTournament=FSTournament AND (FSTeamEvent=0 OR FSTeamEvent IS NULL)'
	. ' LEFT JOIN Finals AS f2 ON f2.FinEvent=FSEvent AND f2.FinMatchNo=FSMatchNo^1 AND f2.FinTournament=FSTournament'
	. ' INNER JOIN Grids ON GrMatchNo=f1.FinMatchNo'
	. ' INNER JOIN Events ON EvCode=FSEvent AND EvTournament=FSTournament AND EvTeamEvent=0'
	. ' WHERE ' . $fscWhere
	. ' GROUP BY SessionKey, FSEvent';
$fscRs = safe_r_sql($fscQuery);

$ind = array();
while ($row = safe_fetch($fscRs)) {
	$sk = $row->SessionKey;
	$ev = $row->Event;
	if (!isset($ind[$sk])) $ind[$sk] = array();
	$ind[$sk][$ev] = $row->Status;
}

// Statut global par cellule = pire statut parmi ses phases (déjà fait par GROUP BY)
// Statut global par session
$indSes = array();
foreach ($ind as $sk => $cells) {
	$worst = 'done';
	foreach ($cells as $st) {
		if ($st === 'waiting') { $worst = 'waiting'; break; }
		if ($st === 'ongoing') $worst = 'ongoing';
	}
	$indSes[$sk] = $worst;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FINALES PAR ÉQUIPES
// ═══════════════════════════════════════════════════════════════════════════════
$fstWhere = 'FSTournament=' . StrSafe_DB($_SESSION['TourId']) . ' AND FSScheduledDate>0 AND FSTeamEvent!=0';
if ($fstOnlyToday)  $fstWhere .= ' AND FSScheduledDate=\'' . $fstToday . '\'';
if ($fstUnfinished) $fstWhere .=
	' AND (FSEvent, FSMatchNo) IN ('
	. ' SELECT f1.TfEvent, f1.TfMatchNo FROM TeamFinals f1'
	. ' INNER JOIN TeamFinals f2 ON f2.TfEvent=f1.TfEvent AND f2.TfMatchNo=f1.TfMatchNo+1 AND f2.TfTournament=f1.TfTournament'
	. ' INNER JOIN Events ON EvTournament=f1.TfTournament AND EvCode=f1.TfEvent AND EvTeamEvent!=0'
	. ' INNER JOIN Grids ON GrMatchNo=f1.TfMatchNo'
	. ' WHERE f1.TfTournament=' . StrSafe_DB($_SESSION['TourId'])
	. '   AND f1.TfMatchNo%2=0'
	. '   AND ((GREATEST(f1.TfTeam,f2.TfTeam)=0 AND GrPhase<=EvFinalFirstPhase)'
	. '     OR (GREATEST(f1.TfWinLose,f2.TfWinLose,f1.TfTie,f2.TfTie)=0 AND GREATEST(f1.TfTeam,f2.TfTeam)>0))'
	. ')';

$fstQuery = 'SELECT'
	. ' CONCAT(\'T\', DATE_FORMAT(FSScheduledDate,\'%Y-%m-%d\'), DATE_FORMAT(FSScheduledTime,\'%H:%i:%s\')) AS SessionKey,'
	. ' FSEvent AS Event,'
	. ' CASE WHEN MAX(IF(tf1.TfTeam=0,0,1))=0 THEN \'waiting\''
	. '      WHEN MAX(IF(GREATEST(tf1.TfWinLose,COALESCE(tf2.TfWinLose,0),tf1.TfTie,COALESCE(tf2.TfTie,0))=0,1,0))=0 THEN \'done\''
	. '      ELSE \'ongoing\' END AS Status'
	. ' FROM FinSchedule'
	. ' INNER JOIN TeamFinals AS tf1 ON tf1.TfEvent=FSEvent AND tf1.TfMatchNo=FSMatchNo AND tf1.TfTournament=FSTournament AND FSTeamEvent!=0'
	. ' LEFT JOIN TeamFinals AS tf2 ON tf2.TfEvent=FSEvent AND tf2.TfMatchNo=FSMatchNo^1 AND tf2.TfTournament=FSTournament'
	. ' INNER JOIN Grids ON GrMatchNo=tf1.TfMatchNo'
	. ' INNER JOIN Events ON EvCode=FSEvent AND EvTournament=FSTournament AND EvTeamEvent!=0'
	. ' WHERE ' . $fstWhere
	. ' GROUP BY SessionKey, FSEvent';
$fstRs = safe_r_sql($fstQuery);

$team = array();
while ($row = safe_fetch($fstRs)) {
	$sk = $row->SessionKey;
	$ev = $row->Event;
	if (!isset($team[$sk])) $team[$sk] = array();
	$team[$sk][$ev] = $row->Status;
}

$teamSes = array();
foreach ($team as $sk => $cells) {
	$worst = 'done';
	foreach ($cells as $st) {
		if ($st === 'waiting') { $worst = 'waiting'; break; }
		if ($st === 'ongoing') $worst = 'ongoing';
	}
	$teamSes[$sk] = $worst;
}

// ═══════════════════════════════════════════════════════════════════════════════
// Sortie JSON
// ═══════════════════════════════════════════════════════════════════════════════
echo json_encode(array(
	'ind'     => $ind,
	'indSes'  => $indSes,
	'team'    => $team,
	'teamSes' => $teamSes,
));
