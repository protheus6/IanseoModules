<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');
require_once('Common/Lib/Fun_Phases.inc.php');

CheckTourSession(true);
$PAGE_TITLE= "Prints Helper";

// ── Données tournoi pour les feuilles de marque ───────────────────────────────
$_tourRow = null;
$_rs = safe_r_sql('SELECT ToNumDist FROM Tournament WHERE ToId=' . StrSafe_DB($_SESSION['TourId']));
if (safe_num_rows($_rs) == 1) {
	$_tourRow = safe_fetch($_rs);
	safe_free_result($_rs);
}
$_numDist  = $_tourRow ? intval($_tourRow->ToNumDist) : 1;
$_sessions = GetSessions('Q');

// ── Données pour le tableau des feuilles de marque des finales ───────────────
// Filtres venant des checkboxes
$_fscOnlyToday  = !empty($_GET['fsc_today']);
$_fscUnfinished = !empty($_GET['fsc_unfinished']);
$_fscFilled     = !empty($_GET['fsc_filled']);

// Date du jour pour le filtre OnlyToday
$_fscToday = date('Y-m-d');

// Colonnes = sessions planifiées du programme (FinSchedule), triées par date/heure
// Lignes   = événements individuels — chaque cellule a sa propre phase
$_fscWhere = 'FSTournament=' . StrSafe_DB($_SESSION['TourId']) . ' AND FSScheduledDate>0';
if ($_fscOnlyToday)  $_fscWhere .= ' AND FSScheduledDate=\'' . $_fscToday . '\'';
if ($_fscUnfinished) $_fscWhere .=
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

$_fscQuery = 'SELECT'
	. ' CONCAT(\'I\', DATE_FORMAT(FSScheduledDate,\'%Y-%m-%d\'), DATE_FORMAT(FSScheduledTime,\'%H:%i:%s\')) AS SessionKey,'
	. ' CONCAT(DATE_FORMAT(FSScheduledDate,\'%e %b \'), DATE_FORMAT(FSScheduledTime,\'%H:%i\')) AS SessionLabel,'
	. ' CONCAT(FSScheduledDate, \' \', FSScheduledTime) AS dtOrder,'
	. ' FSEvent AS Event,'
	. ' GrPhase AS Phase,'
	. ' MAX(IF(FinAthlete=0,0,1)) AS Printable'
	. ' FROM FinSchedule'
	. ' INNER JOIN Finals ON FinEvent=FSEvent AND FinMatchNo=FSMatchNo AND FinTournament=FSTournament AND FSTeamEvent=0'
	. ' INNER JOIN Grids ON GrMatchNo=FinMatchNo'
	. ' INNER JOIN Events ON EvCode=FSEvent AND EvTournament=FSTournament AND EvTeamEvent=0'
	. ' WHERE ' . $_fscWhere
	. ' GROUP BY SessionKey, FSEvent, GrPhase'
	. ' ORDER BY dtOrder ASC, FSEvent ASC';
$_fscRs = safe_r_sql($_fscQuery);

// $_fscSessions[SessionKey] = ['label'=>..., 'dtOrder'=>..., 'cells'=>[EvCode => ['phases'=>[], 'printable'=>bool]]]
$_fscSessions = array();
while ($_fscRow = safe_fetch($_fscRs)) {
	$_sk = $_fscRow->SessionKey;
	if (!isset($_fscSessions[$_sk])) {
		$_fscSessions[$_sk] = array(
			'label'  => $_fscRow->SessionLabel,
			'dtOrder'=> $_fscRow->dtOrder,
			'cells'  => array(),
		);
	}
	$_ev = $_fscRow->Event;
	if (!isset($_fscSessions[$_sk]['cells'][$_ev])) {
		$_fscSessions[$_sk]['cells'][$_ev] = array(
			'phases'    => array(),
			'printable' => false,
		);
	}
	$_fscSessions[$_sk]['cells'][$_ev]['phases'][]  = intval($_fscRow->Phase);
	// Printable = true dès qu'au moins un match de cette cellule a un athlète
	if ($_fscRow->Printable) $_fscSessions[$_sk]['cells'][$_ev]['printable'] = true;
}

// Liste de tous les événements présents dans au moins une session
$_fscAllEvents = array();
foreach ($_fscSessions as $_s) {
	foreach (array_keys($_s['cells']) as $_ev) {
		if (!in_array($_ev, $_fscAllEvents)) $_fscAllEvents[] = $_ev;
	}
}
sort($_fscAllEvents);

// Printable global par session : true si au moins une cellule est printable
// (utilisé pour l'icône de la ligne "Toutes catégories")
foreach ($_fscSessions as $_sk => $_s) {
	$_fscSessions[$_sk]['printable'] = false;
	foreach ($_s['cells'] as $_cell) {
		if ($_cell['printable']) { $_fscSessions[$_sk]['printable'] = true; break; }
	}
}

$_fscQRParam   = '';
$_fscFilledParam = $_fscFilled ? '&amp;ScoreFilled=1' : '';

include('Common/Templates/head.php');

$pdf_img = '<img src="../../../Common/Images/pdf.gif" border="0">';

echo '<table class="Tabella">';
echo '<tbody>';

// ── Titre ─────────────────────────────────────────────────────────────────────
echo '<tr><th class="Title">Impressions</th></tr>';

// ── Général ───────────────────────────────────────────────────────────────────
echo '<tr><th class="SubTitle">Général</th></tr>';
echo '<tr><td class="Center" style="padding:8px">';

echo '	<a href="../../../Scheduler/PrnScheduler.php?PageBreaks=&Finalists=1" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Programme</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Partecipants/PrnStatClasses.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Statistiques Catégories</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Partecipants/PrnStatEvents.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Statistiques Épreuves</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Tournament/PrnStaffField.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Liste Arbitres</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Partecipants/PrnCategory.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Liste Archers</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Partecipants/PrnBirthday.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Anniversaires</a>';
echo '</td></tr>';

// ── Greffe ─────────────────────────────────────────────────────────────────────
echo '<tr><th class="SubTitle">Greffe</th></tr>';
echo '<tr><td class="Center" style="padding:8px">';
echo '	<a href="../../../Partecipants/PrnAlphabetical.php?tf=1" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Tous les départs</a>';
foreach ($_sessions as $s) {
	echo '	&nbsp;&nbsp;';
	echo '	<a href="../../../Partecipants/PrnAlphabetical.php?Session=' . $s->SesOrder . '&tf=1" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;' . htmlspecialchars($s->Descr) . '</a>';
}
echo '</td></tr>';

echo '<tr><th class="Title">Qualification</th></tr>';

// ── Feuilles de marque ────────────────────────────────────────────────────────
echo '<tr><th class="SubTitle">Feuilles de marque</th></tr>';
echo '<tr><td class="Center" style="padding:10px">';

// Script AJAX : remplissage auto des cibles au changement de départ
echo '<script type="text/javascript">';
echo 'function scSelectSession(sel) {';
echo '  var ses = sel.value;';
echo '  if(ses == -1) return;';
echo '  var xhr = new XMLHttpRequest();';
echo '  xhr.open("GET", "../../../Qualification/SelectSession.php?Ses=" + ses, true);';
echo '  xhr.onload = function() {';
echo '    if(xhr.status === 200) {';
echo '      var d = JSON.parse(xhr.responseText);';
echo '      if(d.error === 0) {';
echo '        document.getElementById("scFrom").value = d.min;';
echo '        document.getElementById("scTo").value   = d.max;';
echo '      }';
echo '    }';
echo '  };';
echo '  xhr.send();';
echo '}';
echo '</script>';
if(isset($_SESSION['TourLocSubRule']) AND $_SESSION['TourLocSubRule']=='SetFrBeursault') {
	echo '<form method="post" action="../../../Modules/Sets/FR/pdf/PDFScore.php" target="PrintOut" id="frmScoreCards">';
	echo '<input type="hidden" name="ScoreHeader" value="1">';
	echo '<input type="hidden" name="ScoreLogos" value="1">';
} else {
	echo '<form method="post" action="../../../Qualification/PDFScore.php" target="PrintOut" id="frmScoreCards">';
}
echo '<input type="hidden" name="chk_BlockAutoSave" value="1">';
echo '<input type="hidden" name="ScorePageHeaderFooter" value="1">';
echo '<input type="hidden" name="ScoreFlags" value="1">';

// Ligne 1 : Départ + Cibles de/à
echo '<div style="margin-bottom:6px">';
echo '  <label>Départ&nbsp;';
echo '    <select name="x_Session" onchange="scSelectSession(this)">';
echo '      <option value="-1">---</option>';
foreach ($_sessions as $s) {
	echo '<option value="' . $s->SesOrder . '">' . htmlspecialchars($s->Descr) . '</option>';
}
echo '    </select>';
echo '  </label>';
echo '  &nbsp;&nbsp;&nbsp;';
echo '  <label>Cible de&nbsp;<input type="number" name="x_From" id="scFrom" min="1" max="9999" style="width:4em;text-align:center"></label>';
echo '  &nbsp;&nbsp;';
echo '  <label>à&nbsp;<input type="number" name="x_To" id="scTo" min="1" max="9999" style="width:4em;text-align:center"></label>';
echo '</div>';

// Ligne 2 : Distances + Sans cibles vides
echo '<div style="margin-bottom:8px">';
for ($i = 1; $i <= $_numDist; $i++) {
	echo '  <label><input type="checkbox" name="ScoreDist[]" value="' . $i . '" checked>&nbsp;Distance&nbsp;' . $i . '</label>&nbsp;&nbsp;';
}
echo '  &nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;';
echo '  <label><input type="checkbox" name="noEmpty" value="1" checked>&nbsp;Sans cibles vides</label>';
echo '</div>';

// Ligne 3 : Boutons
$btn = 'background:none;border:none;cursor:pointer;padding:4px 8px;vertical-align:middle';



// Bouton 1 : Standard — ScoreDraw=Complete, barcode=1
echo '<button type="submit" class="Link" style="' . $btn . '" onclick="';
echo "document.getElementById('hScoreDraw').value='Complete';";
echo "document.getElementById('hScoreBarcode').value='1';";
echo "document.getElementById('hPersonalScore').value='';";
echo "document.getElementById('hScoreFilled').value='';";
echo '">' . $pdf_img . '&nbsp;Marque Vide</button>';
echo '&nbsp;&nbsp;';

// Bouton 2 : Vierge — ScoreDraw=Draw, pas de barcode
echo '<button type="submit" class="Link" style="' . $btn . '" onclick="';
echo "document.getElementById('hScoreDraw').value='Draw';";
echo "document.getElementById('hScoreBarcode').value='';";
echo "document.getElementById('hPersonalScore').value='';";
echo "document.getElementById('hScoreFilled').value='';";
echo '">' . $pdf_img . '&nbsp;Feuille Vierge</button>';
echo '&nbsp;&nbsp;';

// Bouton 3 : Complète — ScoreDraw=CompleteTotals, PersonalScore=1, ScoreFilled=1, pas de barcode
echo '<button type="submit" class="Link" style="' . $btn . '" onclick="';
echo "document.getElementById('hScoreDraw').value='CompleteTotals';";
echo "document.getElementById('hScoreBarcode').value='';";
echo "document.getElementById('hPersonalScore').value='1';";
echo "document.getElementById('hScoreFilled').value='1';";
echo '">' . $pdf_img . '&nbsp;Marque Complètes</button>';

// Champs cachés dynamiques
echo '<input type="hidden" name="ScoreDraw"    id="hScoreDraw"    value="Complete">';
echo '<input type="hidden" name="ScoreBarcode" id="hScoreBarcode" value="">';
echo '<input type="hidden" name="PersonalScore" id="hPersonalScore" value="">';
echo '<input type="hidden" name="ScoreFilled"  id="hScoreFilled"  value="">';

echo '</form>';
echo '</td></tr>';


// ── Qualifications ────────────────────────────────────────────────────────────
echo '<tr><th class="SubTitle">Résultats Qualification</th></tr>';
echo '<tr><td class="Center" style="padding:8px">';
echo '	<a href="../../../Qualification/PrnIndividualAbs.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Individuel</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Qualification/PrnTeamAbs.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Équipe</a>';
echo '</td></tr>';

echo '<tr><th class="Title">Finales</th></tr>';

// ── Feuilles de marque Finales ────────────────────────────────────────────────
if (!empty($_fscSessions) || $_fscOnlyToday || $_fscUnfinished) {
	echo '<tr><th class="SubTitle">Feuilles de marque Finales</th></tr>';
	echo '<tr><td style="padding:10px;overflow-x:auto">';

	// Barre de filtres
	echo '<form method="get" action="" id="frmFscFilters" style="margin-bottom:10px;display:inline-flex;gap:16px;align-items:center;flex-wrap:wrap">';
	echo '<label style="cursor:pointer">'
		. '<input type="checkbox" name="fsc_today" value="1"' . ($_fscOnlyToday ? ' checked' : '') . ' onchange="document.getElementById(\'frmFscFilters\').submit()">'
		. '&nbsp;Programme du jour'
		. '</label>';
	echo '<label style="cursor:pointer">'
		. '<input type="checkbox" name="fsc_unfinished" value="1"' . ($_fscUnfinished ? ' checked' : '') . ' onchange="document.getElementById(\'frmFscFilters\').submit()">'
		. '&nbsp;Masquer les tours terminés'
		. '</label>';
	echo '<label style="cursor:pointer">'
		. '<input type="checkbox" name="fsc_filled" value="1"' . ($_fscFilled ? ' checked' : '') . ' onchange="document.getElementById(\'frmFscFilters\').submit()">'
		. '&nbsp;Avec scores'
		. '</label>';
	echo '<button type="submit" class="button" style="padding:2px 10px">Actualiser</button>';
	echo '</form>';

	$_fscPdfImgSmall = '<img src="../../../Common/Images/pdf_small.gif" border="0">';
	$_fscPdfImgLarge = '<img src="../../../Common/Images/pdf.gif" border="0">';
	$_fscBaseUrl     = '../../../Final/Individual/PDFScoreMatch.php';

	if (empty($_fscSessions)) {
		echo '<p style="color:#888;font-style:italic">Aucune session à afficher.</p>';
	} else {

	echo '<table class="Tabella" style="width:100%;border-collapse:collapse">';

	// ── En-tête : une colonne par session du programme ───────────────────────
	// Le label indique date+heure seulement (les phases varient par catégorie)
	echo '<tr>';
	echo '<th class="SubTitle">Catégorie</th>';
	foreach ($_fscSessions as $_fscSesKey => $_fscSes) {
		// Séparer date et heure : "26 Feb" et "10:00"
		$_fscLabelParts = explode(' ', trim($_fscSes['label']), 3);
		// _fscLabelParts = ['26', 'Feb', '10:00'] ou ['26', 'Feb']
		$_fscTime = array_pop($_fscLabelParts); // dernière partie = heure
		$_fscDate = implode(' ', $_fscLabelParts); // reste = date
		echo '<th class="SubTitle" style="white-space:nowrap">'
			. htmlspecialchars($_fscDate)
			. '<br>'
			. htmlspecialchars($_fscTime)
			. '</th>';
	}
	echo '</tr>';

	// ── Une ligne par catégorie ───────────────────────────────────────────────
	foreach ($_fscAllEvents as $_fscEvCode) {
		echo '<tr>';
		echo '<td class="Center" style="padding:4px 8px;font-weight:bold">' . htmlspecialchars($_fscEvCode) . '</td>';
		foreach ($_fscSessions as $_fscSesKey => $_fscSes) {
			echo '<td class="Center" style="padding:4px 4px">';
			if (isset($_fscSes['cells'][$_fscEvCode])) {
				$_fscCell     = $_fscSes['cells'][$_fscEvCode];
				$_fscEvPhases = $_fscCell['phases'];
				$_fscCellIcon = $_fscCell['printable'] ? $_fscPdfImgLarge : $_fscPdfImgSmall;
				if (count($_fscEvPhases) === 1) {
					$_fscPhPart = '&amp;Phase=' . $_fscEvPhases[0];
				} else {
					$_fscPhPart = '&amp;Phase[]=' . implode('&amp;Phase[]=', $_fscEvPhases);
				}
				// Info-bulle : phase(s) de cette cellule
				$_fscPhTip = implode('+', array_map(function($p) {
					return get_text(namePhase(max([32,$p]), $p) . '_Phase');
				}, $_fscEvPhases));
				$_fscUrl = $_fscBaseUrl
					. '?Event=' . urlencode($_fscEvCode)
					. $_fscPhPart
					. '&amp;Barcode=1' . $_fscQRParam . $_fscFilledParam;
				echo '<a href="' . $_fscUrl . '" class="Link" target="PrintOut" title="' . htmlspecialchars($_fscPhTip) . '">'
					. $_fscCellIcon
					. '<br><small>' . htmlspecialchars($_fscPhTip) . '</small>'
					. '</a>';
			} else {
				echo '&mdash;';
			}
			echo '</td>';
		}
		echo '</tr>';
	}

	// ── Ligne du bas : toutes catégories par session (via x_Session) ──────────
	echo '<tr>';
	echo '<td class="Center" style="padding:4px 8px;font-style:italic">Toutes catégories</td>';
	foreach ($_fscSessions as $_fscSesKey => $_fscSes) {
		// x_Session seul suffit : PDFScoreMatch filtre tous les matchs de la session
		$_fscUrl = $_fscBaseUrl
			. '?x_Session=' . urlencode($_fscSesKey)
			. '&amp;Barcode=1' . $_fscQRParam . $_fscFilledParam;
		$_fscSesIcon = $_fscSes['printable'] ? $_fscPdfImgLarge : $_fscPdfImgSmall;
		echo '<td class="Center" style="padding:4px 4px">';
		echo '<a href="' . $_fscUrl . '" class="Link" target="PrintOut">'
			. $_fscSesIcon
			. '</a>';
		echo '</td>';
	}
	echo '</tr>';

	echo '</table>';
	} // end if !empty($_fscSessions)
	echo '</td></tr>';
}

// ── Résultats Finales ─────────────────────────────────────────────────────────
echo '<tr><th class="SubTitle">Résultats Finales</th></tr>';
echo '<tr><td class="Center" style="padding:8px">';
echo '	<a href="PrnRankingNoBrk.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Individuel</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="PrnRankingTeamNoBrk.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Équipe</a>';
echo '</td></tr>';

echo '<tr><th class="Title">Fin du concours</th></tr>';
// ── Résultats Finaux ──────────────────────────────────────────────────────────
echo '<tr><th class="SubTitle">Résultats Finaux</th></tr>';
echo '<tr><td class="Center" style="padding:10px">';
echo '	<form method="get" action="PrintFcn.php" target="PrintOut" style="display:inline">';
echo '		<button type="submit" class="Link" style="background:none;border:none;cursor:pointer;padding:0;vertical-align:middle">';
echo '			' . $pdf_img . '&nbsp;Livret des résultats';
echo '		</button>';
echo '		&nbsp;&nbsp;';
echo '		<label style="vertical-align:middle">Nb de places :&nbsp;';
echo '			<input type="number" name="CutRank" min="1" style="width:4em;text-align:center" placeholder="tout">';
echo '		</label>';
echo '		&nbsp;&nbsp;<small style="color:#555"><i>Finales si disponibles, sinon qualifications. Vide = tout afficher.</i></small>';
echo '	</form>';
echo '</td></tr>';
echo '<tr><td class="Center" style="padding:6px 10px">';
echo '	<a href="PrintFcn.php?CutRank=3" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Liste des médailles</a>';
echo '</td></tr>';

echo '</tbody>';
echo '</table>';

include('Common/Templates/tail.php');
