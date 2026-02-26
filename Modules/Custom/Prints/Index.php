<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');

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

echo '<form method="post" action="../../../Qualification/PDFScore.php" target="PrintOut" id="frmScoreCards">';
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
// ── Finales ───────────────────────────────────────────────────────────────────
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
