<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');
require_once('Common/Lib/Fun_Phases.inc.php');

CheckTourSession(true);
$PAGE_TITLE= "Prints Helper";

// ── Données tournoi pour les feuilles de marque ───────────────────────────────
$_tourRow = null;
$_rs = safe_r_sql('SELECT ToNumDist, ToType FROM Tournament WHERE ToId=' . StrSafe_DB($_SESSION['TourId']));
if (safe_num_rows($_rs) == 1) {
	$_tourRow = safe_fetch($_rs);
	safe_free_result($_rs);
}
$_numDist  = $_tourRow ? intval($_tourRow->ToNumDist) : 1;
// ToType=3 : TAE 70m/50m - 2 distances → afficher les liens spécifiques TAEDI/TAEDN
$_isTae    = $_tourRow ? (intval($_tourRow->ToType) === 3) : false;

// ── ClIds TAE : groupés par type (TAEDI = last char F/H, TAEDN = last char W/M) ──
// Les divisions = armes (CL/CO/BB…), le genre est encodé dans les Classes (S1F, U18H, S2W, S1M…)
// $_taeClsIds['TAEDI'] = ClIds se terminant par F ou H
// $_taeClsIds['TAEDN'] = ClIds se terminant par W ou M
$_taeClsIds = array('TAEDI' => array(), 'TAEDN' => array());
if ($_isTae) {
	$_taeRs = safe_r_sql(
		"SELECT ClId FROM Classes"
		. ' WHERE ClTournament=' . StrSafe_DB($_SESSION['TourId'])
		. " AND ClAthlete=1"
		. " AND (ClId LIKE '%F' OR ClId LIKE '%H' OR ClId LIKE '%W' OR ClId LIKE '%M')"
		. " ORDER BY ClId"
	);
	while ($_tRow = safe_fetch($_taeRs)) {
		$_lc = strtoupper(substr(trim($_tRow->ClId), -1));
		if ($_lc === 'F' || $_lc === 'H') $_taeClsIds['TAEDI'][] = $_tRow->ClId;
		else                               $_taeClsIds['TAEDN'][] = $_tRow->ClId;
	}
	safe_free_result($_taeRs);
}

$_sessions = GetSessions('Q');

// ── Mode ISK-NG ──────────────────────────────────────────────────────────────
$_iskMode     = getModuleParameter('ISK-NG', 'Mode', '');
$_iskIsNgLite = ($_iskMode === 'ng-lite');

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
	. ' MAX(IF(f1.FinAthlete=0,0,1)) AS Printable,'
	. ' CASE WHEN MAX(IF(f1.FinAthlete=0,0,1))=0 THEN \'waiting\''
	. '      WHEN MAX(IF(GREATEST(f1.FinWinLose,COALESCE(f2.FinWinLose,0),f1.FinTie,COALESCE(f2.FinTie,0))=0,1,0))=0 THEN \'done\''
	. '      ELSE \'ongoing\' END AS Status'
	. ' FROM FinSchedule'
	. ' INNER JOIN Finals AS f1 ON f1.FinEvent=FSEvent AND f1.FinMatchNo=FSMatchNo AND f1.FinTournament=FSTournament AND (FSTeamEvent=0 OR FSTeamEvent IS NULL)'
	. ' LEFT JOIN Finals AS f2 ON f2.FinEvent=FSEvent AND f2.FinMatchNo=FSMatchNo^1 AND f2.FinTournament=FSTournament'
	. ' INNER JOIN Grids ON GrMatchNo=f1.FinMatchNo'
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
			'statuses'  => array(),
		);
	}
	$_fscSessions[$_sk]['cells'][$_ev]['phases'][]    = intval($_fscRow->Phase);
	$_fscSessions[$_sk]['cells'][$_ev]['statuses'][]  = $_fscRow->Status;
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

// Printable global par session + statut visuel de chaque cellule et de la session
foreach ($_fscSessions as $_sk => $_s) {
	$_fscSessions[$_sk]['printable'] = false;
	$_fscSessions[$_sk]['status']    = 'done';
	foreach ($_s['cells'] as $_cev => $_cell) {
		if ($_cell['printable']) $_fscSessions[$_sk]['printable'] = true;
		// Statut de la cellule = le pire statut parmi ses phases
		$_cst = 'done';
		foreach ($_cell['statuses'] as $_st) {
			if ($_st === 'waiting') { $_cst = 'waiting'; break; }
			if ($_st === 'ongoing') $_cst = 'ongoing';
		}
		$_fscSessions[$_sk]['cells'][$_cev]['status'] = $_cst;
		// Statut session = le pire parmi les cellules
		if ($_fscSessions[$_sk]['status'] !== 'waiting') {
			if ($_cst === 'waiting') $_fscSessions[$_sk]['status'] = 'waiting';
			elseif ($_cst === 'ongoing') $_fscSessions[$_sk]['status'] = 'ongoing';
		}
	}
}

$_fscQRParam     = ($_iskIsNgLite && !$_fscFilled) ? '&amp;QRCode[]=ISK-NG' : '';
$_fscFilledParam = $_fscFilled ? '&amp;ScoreFilled=1' : '';

// ── Données pour le tableau des feuilles de marque des finales PAR ÉQUIPE ────
$_fstOnlyToday  = !empty($_GET['fst_today']);
$_fstUnfinished = !empty($_GET['fst_unfinished']);
$_fstFilled     = !empty($_GET['fst_filled']);

$_fstToday = date('Y-m-d');

$_fstWhere = 'FSTournament=' . StrSafe_DB($_SESSION['TourId']) . ' AND FSScheduledDate>0 AND FSTeamEvent!=0';
if ($_fstOnlyToday)  $_fstWhere .= ' AND FSScheduledDate=\'' . $_fstToday . '\'';
if ($_fstUnfinished) $_fstWhere .=
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

$_fstQuery = 'SELECT'
	. ' CONCAT(\'T\', DATE_FORMAT(FSScheduledDate,\'%Y-%m-%d\'), DATE_FORMAT(FSScheduledTime,\'%H:%i:%s\')) AS SessionKey,'
	. ' CONCAT(DATE_FORMAT(FSScheduledDate,\'%e %b \'), DATE_FORMAT(FSScheduledTime,\'%H:%i\')) AS SessionLabel,'
	. ' CONCAT(FSScheduledDate, \' \', FSScheduledTime) AS dtOrder,'
	. ' FSEvent AS Event,'
	. ' GrPhase AS Phase,'
	. ' MAX(IF(tf1.TfTeam=0,0,1)) AS Printable,'
	. ' CASE WHEN MAX(IF(tf1.TfTeam=0,0,1))=0 THEN \'waiting\''
	. '      WHEN MAX(IF(GREATEST(tf1.TfWinLose,COALESCE(tf2.TfWinLose,0),tf1.TfTie,COALESCE(tf2.TfTie,0))=0,1,0))=0 THEN \'done\''
	. '      ELSE \'ongoing\' END AS Status'
	. ' FROM FinSchedule'
	. ' INNER JOIN TeamFinals AS tf1 ON tf1.TfEvent=FSEvent AND tf1.TfMatchNo=FSMatchNo AND tf1.TfTournament=FSTournament AND FSTeamEvent!=0'
	. ' LEFT JOIN TeamFinals AS tf2 ON tf2.TfEvent=FSEvent AND tf2.TfMatchNo=FSMatchNo^1 AND tf2.TfTournament=FSTournament'
	. ' INNER JOIN Grids ON GrMatchNo=tf1.TfMatchNo'
	. ' INNER JOIN Events ON EvCode=FSEvent AND EvTournament=FSTournament AND EvTeamEvent!=0'
	. ' WHERE ' . $_fstWhere
	. ' GROUP BY SessionKey, FSEvent, GrPhase'
	. ' ORDER BY dtOrder ASC, FSEvent ASC';
$_fstRs = safe_r_sql($_fstQuery);

$_fstSessions = array();
while ($_fstRow = safe_fetch($_fstRs)) {
	$_sk = $_fstRow->SessionKey;
	if (!isset($_fstSessions[$_sk])) {
		$_fstSessions[$_sk] = array(
			'label'  => $_fstRow->SessionLabel,
			'dtOrder'=> $_fstRow->dtOrder,
			'cells'  => array(),
		);
	}
	$_ev = $_fstRow->Event;
	if (!isset($_fstSessions[$_sk]['cells'][$_ev])) {
		$_fstSessions[$_sk]['cells'][$_ev] = array(
			'phases'    => array(),
			'printable' => false,
			'statuses'  => array(),
		);
	}
	$_fstSessions[$_sk]['cells'][$_ev]['phases'][]   = intval($_fstRow->Phase);
	$_fstSessions[$_sk]['cells'][$_ev]['statuses'][] = $_fstRow->Status;
	if ($_fstRow->Printable) $_fstSessions[$_sk]['cells'][$_ev]['printable'] = true;
}

$_fstAllEvents = array();
foreach ($_fstSessions as $_s) {
	foreach (array_keys($_s['cells']) as $_ev) {
		if (!in_array($_ev, $_fstAllEvents)) $_fstAllEvents[] = $_ev;
	}
}
sort($_fstAllEvents);

foreach ($_fstSessions as $_sk => $_s) {
	$_fstSessions[$_sk]['printable'] = false;
	$_fstSessions[$_sk]['status']    = 'done';
	foreach ($_s['cells'] as $_cev => $_cell) {
		if ($_cell['printable']) $_fstSessions[$_sk]['printable'] = true;
		$_cst = 'done';
		foreach ($_cell['statuses'] as $_st) {
			if ($_st === 'waiting') { $_cst = 'waiting'; break; }
			if ($_st === 'ongoing') $_cst = 'ongoing';
		}
		$_fstSessions[$_sk]['cells'][$_cev]['status'] = $_cst;
		if ($_fstSessions[$_sk]['status'] !== 'waiting') {
			if ($_cst === 'waiting') $_fstSessions[$_sk]['status'] = 'waiting';
			elseif ($_cst === 'ongoing') $_fstSessions[$_sk]['status'] = 'ongoing';
		}
	}
}

$_fstFilledParam = $_fstFilled ? '&amp;ScoreFilled=1' : '';
$_fstQRParam     = ($_iskIsNgLite && !$_fstFilled) ? '&amp;QRCode[]=ISK-NG' : '';

$IncludeJquery = true;
include('Common/Templates/head.php');

$pdf_img = '<img src="../../../Common/Images/pdf.gif" border="0">';

// ── Styles et script accordéon ────────────────────────────────────────────────
echo '<style>
/* ── Accordéon grandes sections (Title) ── */
.acc-title { cursor:pointer; user-select:none; }
.acc-title th { position: relative; text-align: center; }
.acc-title th::after {
    content: "▲";
    position: absolute;
    right: 10px; top: 50%;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px; height: 22px;
    background: rgba(255,255,255,0.25);
    border-radius: 50%;
    font-size: 0.7em;
    transition: transform 0.25s ease;
}
.acc-title.collapsed th::after { transform: translateY(-50%) rotate(180deg); }

/* ── Accordéon sous-sections (SubTitle) ── */
.acc-header { cursor:pointer; user-select:none; }
.acc-header th { position: relative; text-align: center; }
.acc-header th::after {
    content: "▲";
    position: absolute;
    right: 10px; top: 50%;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px; height: 20px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    font-size: 0.65em;
    transition: transform 0.25s ease;
}
.acc-header.collapsed th::after { transform: translateY(-50%) rotate(180deg); }

.acc-body.collapsed { display: none; }

/* ── Statut des cellules feuilles de marque ── */
.cell-done    { background:#d4edda !important; outline:2px solid #28a745; border-radius:4px; transition: background 0.5s ease, outline-color 0.5s ease; }
.cell-ongoing { background:#cce5ff !important; outline:2px solid #0066cc; border-radius:4px; transition: background 0.5s ease, outline-color 0.5s ease; }
.cell-waiting { background:#fff3cd !important; outline:2px solid #d4a017; border-radius:4px; transition: background 0.5s ease, outline-color 0.5s ease; }
</style>';
echo '<script>
$(function(){

    // ── Lecture de l\'état accordéon depuis l\'URL ────────────────────────────
    // Format : acc=titre1,titre2,... (titres des sections collapsed, encodés)
    function getCollapsedFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var raw = params.get("acc");
        if (!raw) return [];
        try { return JSON.parse(decodeURIComponent(raw)); } catch(e) { return []; }
    }

    function buildAccParam() {
        var collapsed = [];
        $(".acc-title, .acc-header").each(function(){
            if ($(this).hasClass("collapsed")) {
                collapsed.push($(this).find("th").first().text().trim());
            }
        });
        return encodeURIComponent(JSON.stringify(collapsed));
    }

    // Injecter le paramètre acc= dans un formulaire avant submit
    function injectAccParam(form) {
        var existing = form.querySelector("input[name=\'acc\']");
        if (existing) existing.parentNode.removeChild(existing);
        var inp = document.createElement("input");
        inp.type = "hidden";
        inp.name = "acc";
        inp.value = buildAccParam();
        form.appendChild(inp);
    }

    // ── Restauration au chargement ───────────────────────────────────────────
    var collapsed = getCollapsedFromUrl();
    if (collapsed.length > 0) {
        // Passe 1 : grandes sections
        $(".acc-title").each(function(){
            var id = $(this).find("th").first().text().trim();
            if (collapsed.indexOf(id) !== -1) {
                $(this).addClass("collapsed");
                $(this).nextUntil(".acc-title").addClass("collapsed");
            }
        });
        // Passe 2 : sous-sections visibles uniquement
        $(".acc-header").each(function(){
            if ($(this).hasClass("collapsed")) return; // déjà caché par parent
            var id = $(this).find("th").first().text().trim();
            if (collapsed.indexOf(id) !== -1) {
                $(this).addClass("collapsed");
                $(this).nextUntil(".acc-header, .acc-title").addClass("collapsed");
            }
        });
    }

    // ── Clics accordéon ──────────────────────────────────────────────────────
    $(".acc-header").on("click", function(){
        $(this).toggleClass("collapsed");
        $(this).nextUntil(".acc-header, .acc-title").toggleClass("collapsed");
    });
    $(".acc-title").on("click", function(){
        $(this).toggleClass("collapsed");
        $(this).nextUntil(".acc-title").toggleClass("collapsed");
    });

    // ── Injection du paramètre acc= dans les formulaires de filtres ──────────
    $("#frmFscFilters, #frmFstFilters").on("submit", function(){
        injectAccParam(this);
    });
});

// Appelé par onchange des checkboxes (inline) — injecte acc= puis soumet
function accSaveAndSubmit(formId) {
    var form = document.getElementById(formId);
    // Construire la liste des collapsed
    var collapsed = [];
    document.querySelectorAll(".acc-title, .acc-header").forEach(function(el){
        if (el.classList.contains("collapsed")) {
            var th = el.querySelector("th");
            if (th) collapsed.push(th.textContent.trim());
        }
    });
    // Injecter dans le formulaire
    var existing = form.querySelector("input[name=\'acc\']");
    if (existing) existing.parentNode.removeChild(existing);
    var inp = document.createElement("input");
    inp.type = "hidden"; inp.name = "acc";
    inp.value = encodeURIComponent(JSON.stringify(collapsed));
    form.appendChild(inp);
    form.submit();
}
</script>';

echo '<table class="Tabella">';
echo '<tbody>';

// ── Titre ─────────────────────────────────────────────────────────────────────
echo '<tr class="acc-title"><th class="Title">Impressions</th></tr>';

// ── Général ───────────────────────────────────────────────────────────────────
echo '<tr class="acc-header"><th class="SubTitle">Général</th></tr>';
echo '<tr class="acc-body"><td class="Center" style="padding:8px">';

echo '	<a href="../../../Scheduler/PrnScheduler.php?PageBreaks=&Finalists=1" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Programme</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Partecipants/PrnStatClasses.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Statistiques Catégories</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Partecipants/PrnStatEvents.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Statistiques Épreuves</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Tournament/PrnStaffField.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Liste Arbitres</a>';
echo '	&nbsp;&nbsp;';

echo '	<a href="../../../Partecipants/PrnBirthday.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Anniversaires</a>';

echo '	<br >';
echo '	<a style="margin:5px" href="../../../Partecipants/PrnCategory.php" class="Link generalCatLink" id="generalCatLink" target="PrintOut">' . $pdf_img . '&nbsp;Liste Archers</a>';
echo '	<br style="margin:6px 0 2px">';
echo '	<label style="vertical-align:middle;font-size:0.9em">Région/Dépt :&nbsp;';
echo '		<input type="text" id="generalCoFilter" maxlength="4" pattern="\d{2}(\d{2})?" style="width:5em;text-align:center" placeholder="ex:&nbsp;0893">';
echo '	</label>'; 	


echo '</td></tr>';
echo '<script>
(function(){
    function _updateCatLink() {
        var cf = (document.getElementById("generalCoFilter") || {}).value || "";
        var a = document.getElementById("generalCatLink");
        if (!a) return;
        if (cf.trim()) {
            a.href = "PrnCategoryRegion.php?CoFilter=" + encodeURIComponent(cf.trim());
        } else {
            a.href = "../../../Partecipants/PrnCategory.php";
        }
    }
    var inp = document.getElementById("generalCoFilter");
    if (inp) inp.addEventListener("input", _updateCatLink);
})();
</script>';

// ── Greffe ─────────────────────────────────────────────────────────────────────
echo '<tr class="acc-header"><th class="SubTitle">Greffe</th></tr>';
echo '<tr class="acc-body"><td class="Center" style="padding:8px">';
echo '	<a href="../../../Partecipants/PrnAlphabetical.php?tf=1" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Tous les départs</a>';
foreach ($_sessions as $s) {
	echo '	&nbsp;&nbsp;';
	echo '	<a href="../../../Partecipants/PrnAlphabetical.php?Session=' . $s->SesOrder . '&amp;tf=1" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;' . htmlspecialchars($s->Descr) . '</a>';
}
echo '</td></tr>';

// ── Paiements ─────────────────────────────────────────────────────────────────────
echo '<tr class="acc-header  collapsed"><th class="SubTitle">Status Paiements</th></tr>';
echo '<tr class="acc-body collapsed"><td class="Center" style="padding:8px">';
echo '	<a href="../../../Accreditation/PrnSession.php?OperationType=Payments&Submit=Ok" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Tous les départs</a>';
foreach ($_sessions as $s) {
	echo '	&nbsp;&nbsp;';
	echo '	<a href="../../../Accreditation/PrnSession.php?OperationType=Payments&Submit=Ok&Session=' . $s->SesOrder . '" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;' . htmlspecialchars($s->Descr) . '</a>';
}
echo '</td></tr>';


// ── Control materiel ─────────────────────────────────────────────────────────────────────
echo '<tr class="acc-header collapsed"><th class="SubTitle">Control matériel</th></tr>';
echo '<tr class="acc-body collapsed"><td class="Center" style="padding:8px">';
echo '	<a href="../../../Accreditation/PrnSession.php?OperationType=ControlMaterial&Submit=Ok" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Tous les départs</a>';
foreach ($_sessions as $s) {
	echo '	&nbsp;&nbsp;';
	echo '	<a href="../../../Accreditation/PrnSession.php?OperationType=ControlMaterial&Submit=Ok&Session=' . $s->SesOrder . '" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;' . htmlspecialchars($s->Descr) . '</a>';
}
echo '</td></tr>';

// ── TAE DI / DN (section conditionnelle ToType=3) ─────────────────────────────
if ($_isTae) {
	echo '<tr class="acc-title"><th class="Title">TAE DI / DN</th></tr>';
	echo '<tr class="acc-header"><th class="SubTitle">Listes &amp; Résultats</th></tr>';
	echo '<tr class="acc-body"><td class="Center" style="padding:8px">';
	foreach ($_taeClsIds as $_taeKey => $_taeClsList) {
		if (empty($_taeClsList)) continue;
		$_taeParts = array();
		foreach ($_taeClsList as $_taeClsId) {
			$_taeParts[] = 'Classes[]=' . rawurlencode($_taeClsId);
		}
		$_taeQStr = implode('&amp;', $_taeParts);
		echo '	<a href="PrnAlphabeticalTAE.php?' . $_taeQStr . '" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Liste Archers ' . htmlspecialchars($_taeKey) . '</a>';
		echo '	&nbsp;&nbsp;';
		echo '	<a href="../../../Qualification/PrnComplete.php?' . $_taeQStr . '" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Résultat ' . htmlspecialchars($_taeKey) . '</a>';
		echo '	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	}
	echo '</td></tr>';
}

echo '<tr class="acc-title"><th class="Title">Qualification</th></tr>';

// ── Feuilles de marque ────────────────────────────────────────────────────────
echo '<tr class="acc-header"><th class="SubTitle">Feuilles de marque</th></tr>';
echo '<tr class="acc-body"><td class="Center" style="padding:10px">';

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
echo "var _q=document.getElementById('hQRCode');if(_q)_q.disabled=false;";
echo '">' . $pdf_img . '&nbsp;Marque</button>';
echo '&nbsp;&nbsp;';

// Bouton 2 : Vierge — ScoreDraw=Draw, pas de barcode
echo '<button type="submit" class="Link" style="' . $btn . '" onclick="';
echo "document.getElementById('hScoreDraw').value='Draw';";
echo "document.getElementById('hScoreBarcode').value='';";
echo "document.getElementById('hPersonalScore').value='';";
echo "document.getElementById('hScoreFilled').value='';";
echo "var _q=document.getElementById('hQRCode');if(_q)_q.disabled=true;";
echo '">' . $pdf_img . '&nbsp;Feuille Vierge</button>';
echo '&nbsp;&nbsp;';

// Bouton 3 : Complète — ScoreDraw=CompleteTotals, PersonalScore=1, ScoreFilled=1, pas de barcode
echo '<button type="submit" class="Link" style="' . $btn . '" onclick="';
echo "document.getElementById('hScoreDraw').value='CompleteTotals';";
echo "document.getElementById('hScoreBarcode').value='';";
echo "document.getElementById('hPersonalScore').value='1';";
echo "document.getElementById('hScoreFilled').value='1';";
echo "var _q=document.getElementById('hQRCode');if(_q)_q.disabled=true;";
echo '">' . $pdf_img . '&nbsp;Marque + scores</button>';

// Champs cachés dynamiques
echo '<input type="hidden" name="ScoreDraw"    id="hScoreDraw"    value="Complete">';
echo '<input type="hidden" name="ScoreBarcode" id="hScoreBarcode" value="">';
echo '<input type="hidden" name="PersonalScore" id="hPersonalScore" value="">';
echo '<input type="hidden" name="ScoreFilled"  id="hScoreFilled"  value="">';
if ($_iskIsNgLite) {
	echo '<input type="hidden" name="QRCode[]" id="hQRCode" value="ISK-NG" disabled>';
}

echo '</form>';
echo '</td></tr>';


// ── Qualifications ────────────────────────────────────────────────────────────
echo '<tr class="acc-header"><th class="SubTitle">Résultats Qualification</th></tr>';
echo '<tr class="acc-body"><td class="Center" style="padding:8px">';
echo '	<a href="../../../Qualification/PrnShootoff.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Shoot-Off/PF</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Qualification/PrnIndividualAbs.php" class="Link" target="PrintOut" id="qualIndLink">' . $pdf_img . '&nbsp;Individuel</a>';
echo '	&nbsp;&nbsp;';
echo '	<a href="../../../Qualification/PrnTeamAbs.php" class="Link" target="PrintOut" id="qualTeamLink">' . $pdf_img . '&nbsp;Équipe</a>';
echo '	<br style="margin:6px 0 2px">';

echo '	<label style="vertical-align:middle;font-size:0.9em">Nb places :&nbsp;';
echo '		<input type="number" id="qualCutRank" min="1" style="width:4em;text-align:center" placeholder="tout">';
echo '	</label>';
echo '	&nbsp;&nbsp;';
echo '	<label style="vertical-align:middle;font-size:0.9em">Région/Dépt :&nbsp;';
echo '		<input type="text" id="qualCoFilter" maxlength="4" pattern="\d{2}(\d{2})?" style="width:5em;text-align:center" placeholder="ex:&nbsp;0893">';
echo '	</label>';
echo '	&nbsp;&nbsp;';
echo '	<label style="vertical-align:middle;cursor:pointer;font-size:0.9em">';
echo '		<input type="checkbox" id="qualReRank" value="1">&nbsp;Refaire le classement';
echo '	</label>';

echo '</td></tr>';
echo '<script>
(function(){
    function _updateQualLinks() {
        var cf = (document.getElementById("qualCoFilter")  || {}).value   || "";
        var rr = (document.getElementById("qualReRank")    || {}).checked || false;
        var cr = (document.getElementById("qualCutRank")   || {}).value   || "";
        var lnkI = document.getElementById("qualIndLink");
        var lnkT = document.getElementById("qualTeamLink");
        if (!lnkI || !lnkT) return;
        if (cf.trim() || rr || cr.trim()) {
            var qs = [];
            if (cf.trim())  qs.push("CoFilter=" + encodeURIComponent(cf.trim()));
            if (rr)         qs.push("ReRank=1");
            if (cr.trim())  qs.push("CutRank=" + encodeURIComponent(cr.trim()));
            var qstr = qs.join("&");
            lnkI.href = "PrnQualIndRegion.php?"  + qstr;
            lnkT.href = "PrnQualTeamRegion.php?" + qstr;
        } else {
            lnkI.href = "../../../Qualification/PrnIndividualAbs.php";
            lnkT.href = "../../../Qualification/PrnTeamAbs.php";
        }
    }
    ["qualCoFilter","qualCutRank"].forEach(function(id){
        var el = document.getElementById(id);
        if (el) el.addEventListener("input", _updateQualLinks);
    });
    var chk = document.getElementById("qualReRank");
    if (chk) chk.addEventListener("change", _updateQualLinks);
})();
</script>';


if ($_SESSION['MenuFinIDo'] ) {
	
	// ── Finales Individuelles ────────────────────────────────────────────────────────────
	echo '<tr class="acc-title"><th class="Title">Finales Individuelles</th></tr>';

	// ── Feuilles de marque Finales ────────────────────────────────────────────────
	if (!empty($_fscSessions) || $_fscOnlyToday || $_fscUnfinished) {
		echo '<tr class="acc-header"><th class="SubTitle">Feuilles de marque Finales Individuelles</th></tr>';
		echo '<tr class="acc-body"><td style="padding:10px;overflow-x:auto">';

		// Barre de filtres
		echo '<form method="get" action="" id="frmFscFilters" style="margin-bottom:10px;display:inline-flex;gap:16px;align-items:center;flex-wrap:wrap">';
		echo '<label style="cursor:pointer">'
			. '<input type="checkbox" name="fsc_today" value="1"' . ($_fscOnlyToday ? ' checked' : '') . ' onchange="accSaveAndSubmit(\'frmFscFilters\')">'
			. '&nbsp;Programme du jour'
			. '</label>';
		echo '<label style="cursor:pointer">'
			. '<input type="checkbox" name="fsc_unfinished" value="1"' . ($_fscUnfinished ? ' checked' : '') . ' onchange="accSaveAndSubmit(\'frmFscFilters\')">'
			. '&nbsp;Masquer les tours terminés'
			. '</label>';
		echo '<label style="cursor:pointer">'
			. '<input type="checkbox" name="fsc_filled" value="1"' . ($_fscFilled ? ' checked' : '') . ' onchange="accSaveAndSubmit(\'frmFscFilters\')">'
			. '&nbsp;Avec scores'
			. '</label>';
		// Préserver les filtres équipe actifs
		if ($_fstOnlyToday)  echo '<input type="hidden" name="fst_today" value="1">';
		if ($_fstUnfinished) echo '<input type="hidden" name="fst_unfinished" value="1">';
		if ($_fstFilled)     echo '<input type="hidden" name="fst_filled" value="1">';
		echo '<button type="submit" class="button" style="padding:2px 10px">Actualiser</button>';
		echo '</form>';

		$_fscPdfImgSmall = '<img src="../../../Common/Images/pdf_small.gif" border="0">';
		$_fscPdfImgLarge = '<img src="../../../Common/Images/pdf.gif" border="0">';
		$_fscBaseUrl     = '../../../Final/Individual/PDFScoreMatch.php';

		if (empty($_fscSessions)) {
			echo '<p style="color:#888;font-style:italic">Aucune session à afficher.</p>';
		} else {

		// Légende statuts
		echo '<div style="margin-left:10px;display:inline-flex;gap:10px;align-items:center;font-size:0.82em">';
		echo '<span style="background:#d4edda;outline:2px solid #28a745;border-radius:3px;padding:2px 8px">&#10003;&nbsp;Terminé</span>';
		echo '<span style="background:#cce5ff;outline:2px solid #0066cc;border-radius:3px;padding:2px 8px">&#9654;&nbsp;En cours</span>';
		echo '<span style="background:#fff3cd;outline:2px solid #d4a017;border-radius:3px;padding:2px 8px">&#9679;&nbsp;En attente</span>';
		echo '</div>';

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
			$_fscCatUrl = $_fscBaseUrl. '?Event=' . urlencode($_fscEvCode).'&amp;ScoreFilled=1';
			echo '<tr>';
			echo '<td class="Center" style="padding:4px 8px;font-weight:bold">';
			echo '<a href="' . $_fscCatUrl . '" class="Link" target="PrintOut" title="' . htmlspecialchars($_fscEvCode) .'">'
						. htmlspecialchars($_fscEvCode) . ' '. $_fscPdfImgSmall
						. '</a>';
			echo ' </td>';
			foreach ($_fscSessions as $_fscSesKey => $_fscSes) {
				if (isset($_fscSes['cells'][$_fscEvCode])) {
					$_fscCell      = $_fscSes['cells'][$_fscEvCode];
					$_fscCellClass = 'cell-' . ($_fscCell['status'] ?? 'waiting');
					echo '<td class="Center ' . $_fscCellClass . '" style="padding:4px 4px" data-section="ind" data-ses="' . htmlspecialchars($_fscSesKey) . '" data-ev="' . htmlspecialchars($_fscEvCode) . '">';
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
					echo '<a href="' . $_fscUrl . '" class="Link" target="PrintOut" title="' . htmlspecialchars($_fscPhTip) .'">'
						. $_fscCellIcon
						. '<br><small>' . htmlspecialchars($_fscPhTip) . '</small>'
						. '</a>';
					echo '</td>';
				} else {
					echo '<td class="Center" style="padding:4px 4px">&mdash;</td>';
				}
			}
			echo '</tr>';
		}
		
				echo '<tr>';
		echo '<th class="SubTitle"></th>';
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


		// ── Ligne du bas : toutes catégories par session (via x_Session) ──────────
		$_fscAllCatUrl = $_fscBaseUrl. '?ScoreFilled=1';
		echo '<tr>';
		echo '<td class="Center" style="padding:4px 8px;font-weight:bold;font-style:italic">';
		echo '<a href="' . $_fscAllCatUrl . '" class="Link" target="PrintOut" title="Toutes catégories">'
					.'Toutes catégories '. $_fscPdfImgSmall
					. '</a>';
		echo ' </td>';
		
		
		foreach ($_fscSessions as $_fscSesKey => $_fscSes) {
			// x_Session seul suffit : PDFScoreMatch filtre tous les matchs de la session
			$_fscUrl = $_fscBaseUrl
				. '?x_Session=' . urlencode($_fscSesKey)
				. '&amp;Barcode=1' . $_fscQRParam . $_fscFilledParam;
			$_fscSesIcon  = $_fscSes['printable'] ? $_fscPdfImgLarge : $_fscPdfImgSmall;
			$_fscSesClass = 'cell-' . ($_fscSes['status'] ?? 'waiting');
			echo '<td class="Center ' . $_fscSesClass . '" style="padding:4px 4px" data-section="ind" data-ses="' . htmlspecialchars($_fscSesKey) . '" data-ses-total="1">';
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
	echo '<tr class="acc-header"><th class="SubTitle">Résultats Finales</th></tr>';
	echo '<tr class="acc-body"><td class="Center" style="padding:8px">';
	echo '	<a href="PrnRankingNoBrk.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Individuel</a>';
	echo '</td></tr>';

}


if ($_SESSION['MenuFinTDo'] ) {

	// ── Finales par Équipes ────────────────────────────────────────────────────────────
	echo '<tr class="acc-title"><th class="Title">Finales par Équipes</th></tr>';

	// ── Feuilles de marque Finales PAR ÉQUIPE ────────────────────────────────────
	if (!empty($_fstSessions) || $_fstOnlyToday || $_fstUnfinished) {
		echo '<tr class="acc-header"><th class="SubTitle">Feuilles de marque Finales par Équipes</th></tr>';
		echo '<tr class="acc-body"><td style="padding:10px;overflow-x:auto">';

		echo '<form method="get" action="" id="frmFstFilters" style="margin-bottom:10px;display:inline-flex;gap:16px;align-items:center;flex-wrap:wrap">';
		echo '<label style="cursor:pointer">'
			. '<input type="checkbox" name="fst_today" value="1"' . ($_fstOnlyToday ? ' checked' : '') . ' onchange="accSaveAndSubmit(\'frmFstFilters\')">'
			. '&nbsp;Programme du jour'
			. '</label>';
		echo '<label style="cursor:pointer">'
			. '<input type="checkbox" name="fst_unfinished" value="1"' . ($_fstUnfinished ? ' checked' : '') . ' onchange="accSaveAndSubmit(\'frmFstFilters\')">'
			. '&nbsp;Masquer les tours terminés'
			. '</label>';
		echo '<label style="cursor:pointer">'
			. '<input type="checkbox" name="fst_filled" value="1"' . ($_fstFilled ? ' checked' : '') . ' onchange="accSaveAndSubmit(\'frmFstFilters\')">'
			. '&nbsp;Avec scores'
			. '</label>';
		// Préserver les filtres individuels actifs
		if ($_fscOnlyToday)  echo '<input type="hidden" name="fsc_today" value="1">';
		if ($_fscUnfinished) echo '<input type="hidden" name="fsc_unfinished" value="1">';
		if ($_fscFilled)     echo '<input type="hidden" name="fsc_filled" value="1">';
		echo '<button type="submit" class="button" style="padding:2px 10px">Actualiser</button>';
		echo '</form>';

		$_fstPdfImgSmall = '<img src="../../../Common/Images/pdf_small.gif" border="0">';
		$_fstPdfImgLarge = '<img src="../../../Common/Images/pdf.gif" border="0">';
		$_fstBaseUrl     = '../../../Final/Team/PDFScoreMatch.php';

		if (empty($_fstSessions)) {
			echo '<p style="color:#888;font-style:italic">Aucune session à afficher.</p>';
		} else {

		// Légende statuts
		echo '<div style="margin-left:8px;display:inline-flex;gap:10px;align-items:center;font-size:0.82em">';
		echo '<span style="background:#d4edda;outline:2px solid #28a745;border-radius:3px;padding:2px 8px">&#10003;&nbsp;Terminé</span>';
		echo '<span style="background:#cce5ff;outline:2px solid #0066cc;border-radius:3px;padding:2px 8px">&#9654;&nbsp;En cours</span>';
		echo '<span style="background:#fff3cd;outline:2px solid #d4a017;border-radius:3px;padding:2px 8px">&#9679;&nbsp;En attente</span>';
		echo '</div>';

		echo '<table class="Tabella" style="width:100%;border-collapse:collapse">';

		// En-tête
		echo '<tr>';
		echo '<th class="SubTitle">Catégorie</th>';
		foreach ($_fstSessions as $_fstSesKey => $_fstSes) {
			$_fstLabelParts = explode(' ', trim($_fstSes['label']), 3);
			$_fstTime = array_pop($_fstLabelParts);
			$_fstDate = implode(' ', $_fstLabelParts);
			echo '<th class="SubTitle" style="white-space:nowrap">'
				. htmlspecialchars($_fstDate)
				. '<br>'
				. htmlspecialchars($_fstTime)
				. '</th>';
		}
		echo '</tr>';

		// Lignes par catégorie
		foreach ($_fstAllEvents as $_fstEvCode) {
			$_fstCatUrl = $_fstBaseUrl. '?Event=' . urlencode($_fstEvCode).'&amp;ScoreFilled=1';
			echo '<tr>';
			echo '<td class="Center" style="padding:4px 8px;font-weight:bold">';
			echo '<a href="' . $_fstCatUrl . '" class="Link" target="PrintOut" title="' . htmlspecialchars($_fstEvCode) .'">'
						. htmlspecialchars($_fstEvCode) . ' '. $_fstPdfImgSmall
						. '</a>';
			echo ' </td>';
			
			
			
			
			foreach ($_fstSessions as $_fstSesKey => $_fstSes) {
				if (isset($_fstSes['cells'][$_fstEvCode])) {
					$_fstCell      = $_fstSes['cells'][$_fstEvCode];
					$_fstCellClass = 'cell-' . ($_fstCell['status'] ?? 'waiting');
					echo '<td class="Center ' . $_fstCellClass . '" style="padding:4px 4px" data-section="team" data-ses="' . htmlspecialchars($_fstSesKey) . '" data-ev="' . htmlspecialchars($_fstEvCode) . '">';
					$_fstEvPhases = $_fstCell['phases'];
					$_fstCellIcon = $_fstCell['printable'] ? $_fstPdfImgLarge : $_fstPdfImgSmall;
					if (count($_fstEvPhases) === 1) {
						$_fstPhPart = '&amp;Phase=' . $_fstEvPhases[0];
					} else {
						$_fstPhPart = '&amp;Phase[]=' . implode('&amp;Phase[]=', $_fstEvPhases);
					}
					$_fstPhTip = implode('+', array_map(function($p) {
						return get_text(namePhase(max([32,$p]), $p) . '_Phase');
					}, $_fstEvPhases));
					$_fstUrl = $_fstBaseUrl
						. '?Event=' . urlencode($_fstEvCode)
						. $_fstPhPart
						. '&amp;Barcode=1' . $_fstQRParam . $_fstFilledParam;
					echo '<a href="' . $_fstUrl . '" class="Link" target="PrintOut" title="' . htmlspecialchars($_fstPhTip) . '">'
						. $_fstCellIcon
						. '<br><small>' . htmlspecialchars($_fstPhTip) . '</small>'
						. '</a>';
					echo '</td>';
				} else {
					echo '<td class="Center" style="padding:4px 4px">&mdash;</td>';
				}
			}
			echo '</tr>';
		}
		echo '<tr>';
		echo '<th class="SubTitle">Catégorie</th>';
		foreach ($_fstSessions as $_fstSesKey => $_fstSes) {
			$_fstLabelParts = explode(' ', trim($_fstSes['label']), 3);
			$_fstTime = array_pop($_fstLabelParts);
			$_fstDate = implode(' ', $_fstLabelParts);
			echo '<th class="SubTitle" style="white-space:nowrap">'
				. htmlspecialchars($_fstDate)
				. '<br>'
				. htmlspecialchars($_fstTime)
				. '</th>';
		}
		echo '</tr>';

		// Ligne toutes catégories
		$_fstAllCatUrl = $_fstBaseUrl. '?ScoreFilled=1';
		
		echo '<tr>';
		echo '<td class="Center" style="padding:4px 8px;font-weight:bold;font-style:italic">';
		echo '<a href="' . $_fstAllCatUrl . '" class="Link" target="PrintOut" title="Toutes catégories">'
					.'Toutes catégories '. $_fstPdfImgSmall
					. '</a>';
		echo ' </td>';
		
		
		foreach ($_fstSessions as $_fstSesKey => $_fstSes) {
			$_fstUrl = $_fstBaseUrl
				. '?x_Session=' . urlencode($_fstSesKey)
				. '&amp;Barcode=1' . $_fstQRParam . $_fstFilledParam;
			$_fstSesIcon  = $_fstSes['printable'] ? $_fstPdfImgLarge : $_fstPdfImgSmall;
			$_fstSesClass = 'cell-' . ($_fstSes['status'] ?? 'waiting');
			echo '<td class="Center ' . $_fstSesClass . '" style="padding:4px 4px" data-section="team" data-ses="' . htmlspecialchars($_fstSesKey) . '" data-ses-total="1">';
			echo '<a href="' . $_fstUrl . '" class="Link" target="PrintOut">'
				. $_fstSesIcon
				. '</a>';
			echo '</td>';
		}
		echo '</tr>';

		echo '</table>';
		} // end if !empty($_fstSessions)
		echo '</td></tr>';
	}

	// ── Résultats Finales ─────────────────────────────────────────────────────────
	echo '<tr class="acc-header"><th class="SubTitle">Résultats Finales</th></tr>';
	echo '<tr class="acc-body"><td class="Center" style="padding:8px">';
	echo '	<a href="PrnRankingTeamNoBrk.php" class="Link" target="PrintOut">' . $pdf_img . '&nbsp;Équipe</a>';
	echo '</td></tr>';

}



echo '<tr class="acc-title"><th class="Title">Fin du concours</th></tr>';
// ── Résultats Finaux ──────────────────────────────────────────────────────────
echo '<tr class="acc-header"><th class="SubTitle">Résultats Finaux</th></tr>';
echo '<tr class="acc-body"><td class="Center" style="padding:10px">';
echo '	<form method="get" action="PrintFcn.php" target="PrintOut" style="display:inline">';
echo '		<button type="submit" class="Link" style="background:none;border:none;cursor:pointer;padding:0;vertical-align:middle">';
echo '			' . $pdf_img . '&nbsp;Livret des résultats';
echo '		</button>';
echo '		&nbsp;&nbsp;';
echo '		<label style="vertical-align:middle">Nb de places :&nbsp;';
echo '			<input type="number" name="CutRank" id="finCutRank" min="1" style="width:4em;text-align:center" placeholder="tout">';
echo '		</label>';
echo '		&nbsp;&nbsp;';
echo '		<label style="vertical-align:middle">Région/Dépt :&nbsp;';
echo '			<input type="text" name="CoFilter" id="finCoFilter" maxlength="4" pattern="\d{2}(\d{2})?" style="width:5em;text-align:center" placeholder="ex:&nbsp;0893">';
echo '		</label>';
echo '		&nbsp;&nbsp;';
echo '		<label style="vertical-align:middle;cursor:pointer">';
echo '			<input type="checkbox" name="ReRank" id="finReRank" value="1">';
echo '			&nbsp;Refaire le classement';
echo '		</label>';
echo '	</form>';
echo '</td></tr>';
echo '<tr class="acc-body"><td class="Center" style="padding:6px 10px">';
echo '	<a href="PrintFcn.php?CutRank=3" class="Link" target="PrintOut" id="finLinkMedailles">' . $pdf_img . '&nbsp;Liste des médailles</a>';
echo '</td></tr>';
echo '<script>
(function(){
    function _updateMedailles() {
        var lnk = document.getElementById("finLinkMedailles");
        if (!lnk) return;
        var v  = (document.getElementById("finCoFilter") || {}).value   || "";
        var rr = (document.getElementById("finReRank")   || {}).checked || false;
        var qs = "PrintFcn.php?CutRank=3";
        if (v.trim())  qs += "&CoFilter=" + encodeURIComponent(v.trim());
        if (rr)        qs += "&ReRank=1";
        lnk.href = qs;
    }
    var inp = document.getElementById("finCoFilter");
    var chk = document.getElementById("finReRank");
    if (inp) inp.addEventListener("input",  _updateMedailles);
    if (chk) chk.addEventListener("change", _updateMedailles);
})();
</script>';

echo '</tbody>';
echo '</table>';

echo '<script>
(function(){
    var REFRESH_MS = 15000;
    // Construire la query-string des filtres actifs
    var qs = [];
    if (document.querySelector("input[name=fsc_today]:checked"))      qs.push("fsc_today=1");
    if (document.querySelector("input[name=fsc_unfinished]:checked")) qs.push("fsc_unfinished=1");
    if (document.querySelector("input[name=fst_today]:checked"))      qs.push("fst_today=1");
    if (document.querySelector("input[name=fst_unfinished]:checked")) qs.push("fst_unfinished=1");
    var ajaxUrl = "AjaxFinStatus.php" + (qs.length ? "?" + qs.join("&") : "");

    function updateCellClass(td, newStatus) {
        var want = "cell-" + newStatus;
        if (td.classList.contains(want)) return;
        td.classList.remove("cell-done", "cell-ongoing", "cell-waiting");
        td.classList.add(want);
    }

    function refreshStatuses() {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", ajaxUrl, true);
        xhr.onload = function() {
            if (xhr.status !== 200) return;
            try { var data = JSON.parse(xhr.responseText); } catch(e) { return; }

            // Individual cells
            if (data.ind) {
                for (var ses in data.ind) {
                    for (var ev in data.ind[ses]) {
                        var td = document.querySelector("td[data-section=ind][data-ses=\"" + ses + "\"][data-ev=\"" + ev + "\"]");
                        if (td) updateCellClass(td, data.ind[ses][ev]);
                    }
                }
            }
            // Individual session totals
            if (data.indSes) {
                for (var ses in data.indSes) {
                    var td = document.querySelector("td[data-section=ind][data-ses=\"" + ses + "\"][data-ses-total]");
                    if (td) updateCellClass(td, data.indSes[ses]);
                }
            }
            // Team cells
            if (data.team) {
                for (var ses in data.team) {
                    for (var ev in data.team[ses]) {
                        var td = document.querySelector("td[data-section=team][data-ses=\"" + ses + "\"][data-ev=\"" + ev + "\"]");
                        if (td) updateCellClass(td, data.team[ses][ev]);
                    }
                }
            }
            // Team session totals
            if (data.teamSes) {
                for (var ses in data.teamSes) {
                    var td = document.querySelector("td[data-section=team][data-ses=\"" + ses + "\"][data-ses-total]");
                    if (td) updateCellClass(td, data.teamSes[ses]);
                }
            }
        };
        xhr.send();
    }

    setInterval(refreshStatuses, REFRESH_MS);
})();
</script>';

include('Common/Templates/tail.php');
