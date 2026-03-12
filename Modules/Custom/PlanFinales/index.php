<?php
require_once(dirname(__FILE__, 3) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');
require_once('Common/Lib/CommonLib.php');

CheckTourSession(true);
checkACL(AclCompetition, AclReadOnly);

// Infos tournoi
$tourId = intval($_SESSION['TourId']);
$rsTour = safe_r_sql("SELECT ToName, ToCode FROM Tournament WHERE ToId=" . $tourId);
$tour   = safe_fetch($rsTour);
$tourName = $tour ? htmlspecialchars($tour->ToName) : '';
$tourCode = $tour ? htmlspecialchars($tour->ToCode) : '';

// Plage de dates (depuis DistanceInformation ou FinSchedule)
$dateRange = '';
$rsDates = safe_r_sql("SELECT MIN(DiDay) minD, MAX(DiDay) maxD
                        FROM DistanceInformation
                        WHERE DiTournament=" . $tourId
                        . " AND DiDay IS NOT NULL AND DiDay != '0000-00-00'");
if ($rd = safe_fetch($rsDates)) {
    if ($rd->minD) $dateRange = $rd->minD . ($rd->maxD && $rd->maxD !== $rd->minD ? ' — ' . $rd->maxD : '');
}
if (!$dateRange) {
    $rsDates2 = safe_r_sql("SELECT MIN(FSScheduledDate) minD, MAX(FSScheduledDate) maxD
                             FROM FinSchedule WHERE FSTournament=" . $tourId
                             . " AND FSScheduledDate IS NOT NULL");
    if ($rd2 = safe_fetch($rsDates2)) {
        if ($rd2->minD) $dateRange = $rd2->minD . ($rd2->maxD && $rd2->maxD !== $rd2->minD ? ' — ' . $rd2->maxD : '');
    }
}

// Paramètres de durée par défaut pour la configuration
$equipeEchauffement = 15;
$equipeMatch        = 30;
$indivEchauffement  = 5;
$indivMatch         = 30;

$PAGE_TITLE    = 'Plan de cible — Finales';
$IncludeJquery = true;

$svgBase = $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/svg/';
$pfRoot  = $CFG->ROOT_DIR . 'Modules/Custom/PlanFinales/';

$JS_SCRIPT = [
    '<link rel="stylesheet" href="' . $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/lib/dragula.min.css">',
    '<link rel="stylesheet" href="' . $pfRoot . 'finalesP.css">',
    '<script src="' . $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/lib/dragula.min.js"></script>',
    '<script>var PF_ROOT = ' . json_encode($pfRoot) . ';
             var PF_SVG  = ' . json_encode($svgBase) . ';
             var PF_AJAX = ' . json_encode($pfRoot . 'ajax.php') . ';
    </script>',
    '<script src="' . $pfRoot . 'finalesP.js"></script>',
];

include('Common/Templates/head.php');
?>

<!-- =====================================================================
     En-tête
     ===================================================================== -->
<table class="Tabella" style="width:100%;">
  <tr>
    <th class="Title" colspan="3">
      Plan de cible — Finales — <?= $tourCode ?> / <?= $tourName ?>
    </th>
  </tr>
  <tr>
    <th class="Title" colspan="3"><?= htmlspecialchars($dateRange) ?></th>
  </tr>
</table>

<!-- =====================================================================
     Barre d'outils
     ===================================================================== -->
<div class="pf-toolbar">
  <input type="button" class="Button" id="btnSave" value="Enregistrer" onclick="pfSave()">
  <label class="pf-toolbar-lbl">
    <input type="checkbox" id="chkAutoShift" checked>
    Mettre à jour les horaires suivants
  </label>
  <label class="pf-toolbar-lbl">
    <input type="checkbox" id="chkBlason" onchange="pfToggleBlasons(this.checked)">
    Afficher les blasons
  </label>
  <input type="button" class="Button" value="🖨 Imprimer" onclick="pfPrint()" title="Imprimer le plan de cible">
  <span id="pfStatus" class="pf-status"></span>
</div>

<!-- =====================================================================
     Mise en page : configuration | grille
     ===================================================================== -->
<div class="pf-layout">

  <!-- ---- Panneau de configuration (gauche) ---- -->
  <div class="pf-config-col">
    <div class="pf-config-panel">
      <div class="pf-config-title">Configuration</div>

      <div class="pf-config-section">
        <strong>Équipe</strong>
        <div class="pf-config-row">
          <label>Échauffement</label>
          <input type="number" id="cfgEquipeEchauff" value="<?= $equipeEchauffement ?>" min="1" max="120" style="width:4em;" oninput="pfApplyConfigDuration()">
          <span>min</span>
        </div>
        <div class="pf-config-row">
          <label>Match</label>
          <input type="number" id="cfgEquipeMatch" value="<?= $equipeMatch ?>" min="1" max="120" style="width:4em;" oninput="pfApplyConfigDuration()">
          <span>min</span>
        </div>
      </div>

      <div class="pf-config-section">
        <strong>Individuel</strong>
        <div class="pf-config-row">
          <label>Échauffement</label>
          <input type="number" id="cfgIndivEchauff" value="<?= $indivEchauffement ?>" min="1" max="120" style="width:4em;" oninput="pfApplyConfigDuration()">
          <span>min</span>
        </div>
        <div class="pf-config-row">
          <label>Match</label>
          <input type="number" id="cfgIndivMatch" value="<?= $indivMatch ?>" min="1" max="120" style="width:4em;" oninput="pfApplyConfigDuration()">
          <span>min</span>
        </div>
      </div>

      <!-- Phases non planifiées -->
      <div class="pf-config-section" id="unscheduledPanel">
        <div class="pf-unsched-header">
          <strong>Non planifiés</strong>
          <button class="pf-add-train-btn" onclick="pfOpenTrainModal()" title="Ajouter un bloc d'échauffement">＋ Échauffement</button>
        </div>
        <div id="unscheduledList" class="pf-unscheduled-list">
          <em style="color:#999; font-size:.8em;">Chargement...</em>
        </div>
      </div>

      <!-- Modal : choisir l'épreuve pour un nouvel entraînement -->
      <div id="pfTrainModal" class="pf-train-modal" style="display:none;">
        <div class="pf-train-modal-inner">
          <div class="pf-train-modal-title">Ajouter un échauffement</div>
          <label class="pf-train-modal-lbl">Épreuve
            <select id="pfTrainEvSelect" class="pf-train-ev-select"></select>
          </label>
          <div class="pf-train-modal-btns">
            <button class="Button" onclick="pfAddTrainingConfirm()">Ajouter</button>
            <button class="Button" onclick="pfCloseTrainModal()">Annuler</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ---- Zone de grille (droite) ---- -->
  <div class="pf-grid-col">
    <!-- Boutons d'action sur la grille -->
    <div class="pf-grid-actions">
      <input type="button" class="Button" value="+ Créneau" onclick="pfAddSlot()" title="Ajouter un créneau horaire">
      <input type="button" class="Button" value="+ Cible"   onclick="pfAddTarget()" title="Ajouter une colonne cible">
      <span class="pf-zoom-control" title="Zoom de la grille">
        🔍
        <input type="range" id="pfZoomSlider" min="50" max="150" step="5" value="100"
               oninput="pfSetZoom(this.value)">
        <span id="pfZoomLbl">100%</span>
      </span>
    </div>

    <!-- La grille elle-même -->
    <div id="pfGridWrap" class="pf-grid-wrap">
      <div id="pfLoading" style="padding:20px; color:#666;">Chargement du plan...</div>
      <div id="pfGrid"></div>
    </div>
  </div>

</div><!-- .pf-layout -->

<?php include('Common/Templates/tail.php'); ?>
