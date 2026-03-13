<?php
require_once(dirname(__FILE__, 3) . '/config.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/Fun_Sessions.inc.php');
require_once('Common/Lib/CommonLib.php');

CheckTourSession(true);
checkACL(AclQualification, AclReadWrite);

require_once(__DIR__ . '/lib/models.php');

$sessId = isset($_GET['sessId']) ? intval($_GET['sessId']) : 1;
$sortBy = isset($_GET['sort'])   ? intval($_GET['sort'])   : 0;

$session = new QP_Session($_SESSION['TourId'], $sessId);

// Si session introuvable, prendre la première disponible
if (empty($session->name) && !empty($session->tour->sessions)) {
    $firstSess = reset($session->tour->sessions);
    $sessId    = $firstSess->id;
    $session   = new QP_Session($_SESSION['TourId'], $sessId);
}

// Couleurs par structure (club/pays)
$structColors = [];
$colorPalette = ['#FFD6D6','#D6FFD6','#D6D6FF','#FFFFD6','#FFD6FF','#D6FFFF','#FFE8D6','#E8D6FF','#D6FFE8','#FFD6E8'];
$colorIdx = 0;
foreach ($session->participants as $p) {
    if (!isset($structColors[$p->structId])) {
        $structColors[$p->structId] = $colorPalette[$colorIdx % count($colorPalette)];
        $colorIdx++;
    }
}

$PAGE_TITLE = 'Plan de cible';
$IncludeJquery = true;
$svgBase = $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/svg/';

$JS_SCRIPT = [
    '<link rel="stylesheet" href="' . $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/lib/dragula.min.css">',
    '<link rel="stylesheet" href="' . $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/qualifsP.css">',
    '<script src="' . $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/lib/dragula.min.js"></script>',
    '<script>var QP_ROOT = ' . json_encode($CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/') . ';</script>',
    '<script>var QP_SESS_ID = ' . $sessId . '; var QP_SORT = ' . $sortBy . ';</script>',
    '<script src="' . $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/qualifsP.js"></script>',
];

// Nom et heure d'affichage
$headerName = !empty($session->name) ? $session->name : ('Session ' . $sessId);
$startTime  = '';
if (!empty($session->start)) {
    $ts = strtotime($session->start);
    $startTime = $ts ? date('H:i', $ts) : $session->start;
}

include('Common/Templates/head.php');
?>

<style>
<?php foreach ($structColors as $sId => $color): ?>
.bgstru<?= $sId ?> { background-color: <?= $color ?>; }
<?php endforeach; ?>
</style>

<!-- ============================================================
     En-tête : sélecteurs session / groupement
     ============================================================ -->
<table class="Tabella">
  <tr>
    <th class="Title" colspan="3">
      Plan de cible — <?= htmlspecialchars($session->tour->name) ?>
    </th>
  </tr>
  <tr>
    <td style="width:220px; vertical-align:top; padding:4px;">
      <form method="get">
        <table>
          <tr>
            <td><label for="sessId">Session&nbsp;:</label></td>
            <td>
              <select name="sessId" id="sessId" onchange="this.form.submit()">
                <?php foreach ($session->tour->sessions as $ses): ?>
                  <option value="<?= $ses->id ?>" <?= ($sessId == $ses->id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars(!empty($ses->name) ? $ses->name : 'Session ' . $ses->id) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <td><label for="sort">Grouper&nbsp;:</label></td>
            <td>
              <select name="sort" id="sort" onchange="this.form.submit()">
                <option value="0" <?= ($sortBy == 0) ? 'selected' : '' ?>>Blason</option>
                <option value="1" <?= ($sortBy == 1) ? 'selected' : '' ?>>Catégorie</option>
              </select>
            </td>
          </tr>
        </table>
        <input type="hidden" id="groupBy" value="<?= $sortBy ?>">
      </form>
    </td>
    <td class="Center" style="vertical-align:middle;">
      <strong style="font-size:1.2em;"><?= htmlspecialchars($headerName) ?><?= $startTime ? ' — ' . $startTime : '' ?></strong>
    </td>
    <td style="width:220px; vertical-align:top; padding:4px; text-align:right;">
      <!-- Toggles affichage -->
      <label style="font-size:.85em; display:block; margin-bottom:3px;">
        <input type="checkbox" id="toggleArcher" checked onchange="hideSwitch()">
        Afficher les archers
      </label>
      <label style="font-size:.85em; display:block;">
        <input type="checkbox" id="toggleAffected" checked onchange="hideAffectedSwitch()">
        Afficher les affectés
      </label>
    </td>
  </tr>
</table>

<!-- ============================================================
     Récap blasons + actions
     ============================================================ -->
<div class="qp-bandeau">
  <span class="qp-label">Blasons&nbsp;:</span>
  <span id="recapBlason" class="qp-recap">Chargement...</span>
  <input type="button" class="Button" value="Imprimer les cibles" onclick="printTargets()">
  <input type="button" class="Button" value="Récap global"        onclick="openGlobalRecap()">
  <input type="button" class="Button" value="Commande blasons"    onclick="openOrder()">
</div>

<!-- En-tête impression -->
<div id="printHeader">
  <strong><?= htmlspecialchars($session->tour->name) ?> — <?= htmlspecialchars($headerName) ?><?= $startTime ? ' — ' . $startTime : '' ?></strong>
</div>
<!-- Nom du tournoi seul (utilisé par le récap global) -->
<span id="tourNameOnly" style="display:none"><?= htmlspecialchars($session->tour->name) ?></span>

<!-- Page de garde impression : bilan blasons avec images SVG -->
<div id="printBlasonRecap">
  <div class="pbr-title">Bilan des blasons</div>
  <div id="printBlasonBody"><!-- rempli par AJAX blasonRecapPrint --></div>
</div>

<!-- ============================================================
     Mise en page principale : picking list | cibles
     ============================================================ -->
<div class="qp-layout">

  <!-- Colonne gauche : liste de picking -->
  <div class="qp-picking-col">
    <div id="PickingList" class="qp-picking-list">
      <?php if ($sortBy == 1): ?>
        <!-- Groupé par catégorie -->
        <?php foreach ($session->listByCategory() as $cat): ?>
          <div class="pq-halo-category qp-accordion-item" id="tcat-<?= htmlspecialchars($cat->name) ?>"
		  data-pq-category="<?= $cat->name ?>"
		  >
            <div class="qp-accordion-header"
                 onclick="qpToggle(this)">
              <span><?= htmlspecialchars($cat->name) ?></span>
              <span class="qp-counts">
                (<span class="memberAffectedCount">-</span>/<span class="memberCount">-</span>)
              </span>
              <span class="qp-chevron">▼</span>
            </div>
            <div class="qp-accordion-body qp-hidden">
              <div class="ddsrc"
                   id="blsItem-<?= htmlspecialchars($cat->name) ?>"
                   data-category="<?= htmlspecialchars($cat->name) ?>"
                   data-blason="">
                <div class="blasonContent dragula-container"></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <!-- Groupé par blason -->
        <?php foreach ($session->blasonCount() as $blId => $blason): ?>
          <div class="pq-halo-blason qp-accordion-item" id="tgl-<?= $blId ?>"
		  data-pq-blason="<?= $blId ?>"
		  >
            <div class="qp-accordion-header"
                 onclick="qpToggle(this)">
              <span><?= htmlspecialchars($blason->name) ?></span>
              <span class="qp-counts">
                (<span class="memberAffectedCount">-</span>/<span class="memberCount">-</span>)
              </span>
              <span class="qp-chevron">▼</span>
            </div>
            <div class="qp-accordion-body qp-hidden">
              <div class="ddsrc"
                   id="blsItem-<?= $blId ?>"
                   data-blason="<?= $blId ?>"
                   data-category="">
                <div class="blasonContent dragula-container"></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Colonne droite : zone cibles -->
  <div class="qp-targets-col">
    <input type="hidden" id="departId" value="<?= $sessId ?>">
    <div id="targetsArea" class="qp-targets-area">
      <?php for ($c = 1; $c <= $session->targets; $c++): ?>
        <div id="Cible-<?= $c ?>" class="qp-cible-wrap">
          <input type="hidden" class="cibleNum" value="<?= $c ?>">
          <!-- Carte cible (placeholder, remplacé par AJAX) -->
          <div class="qp-cible-card qp-border-primary">
            <div class="qp-cible-header">
              <span>Cible <?= $c ?></span>
              <span class="btRm" onclick="removeCible(this)" title="Vider la cible">✕</span>
            </div>
            <div class="qp-blasons-row" style="background:cornsilk; min-height:40px; display:flex; align-items:center; justify-content:center;">
              <img src="<?= htmlspecialchars($svgBase . 'Empty.svg') ?>"
                   alt="" style="max-height:40px; max-width:40px; width:auto; height:auto; opacity:.2;">
            </div>
            <div style="text-align:center; padding:2px;">
              <em style="color:#aaa; font-size:.75em;">Chargement...</em>
            </div>
          </div>
          <div id="cb<?= $c ?>" class="qp-cible-names nameArcher qp-border-primary">
            <em style="color:#aaa; font-size:.75em;">Chargement...</em>
          </div>
        </div>
      <?php endfor; ?>
    </div>
  </div>

</div>

<!-- ============================================================
     Modale commande blasons (CSS pur, pas de JS framework)
     ============================================================ -->
<div id="orderModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4);
     align-items:center; justify-content:center; padding:1rem; z-index:2000;">
  <div id="orderCard" style="background:#fff; width:min(800px,95vw); max-height:90vh;
       overflow:auto; border-radius:4px; box-shadow:0 6px 24px rgba(0,0,0,.3); padding:1rem;">
    <button type="button"
            style="position:absolute;top:.5rem;right:.5rem;border:none;background:transparent;font-size:1.2rem;cursor:pointer;"
            onclick="closeOrder()">✕</button>
    <h3 style="text-align:center; margin:0 0 .5rem 0; font-size:1.1em;">Commande de blasons</h3>

    <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; margin-bottom:.5rem;">
      <label style="font-size:.85em;">Coeff global</label>
      <input id="globalCoeff" type="number" step="1" min="0" value="1" style="width:5em;"
             oninput="applyGlobalCoeff(this.value)">
      <input type="button" class="Button" value="Actualiser" onclick="refreshFromRecap()">
      <input type="button" class="Button" value="+ Ligne"    onclick="addOrderRow()">
      <span style="font-size:.75em; color:#666; margin-left:auto;">
        40cm Trispot CO → ×5 &nbsp;|&nbsp; 40cm → ×2 &nbsp;|&nbsp; 60/80cm Unique → ÷4
      </span>
    </div>

    <table class="Tabella" id="orderTable">
      <thead>
        <tr class="Main">
          <th>Type de blason</th>
          <th>Quantité</th>
          <th>Coeff</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="Bold Right">Total général</td>
          <td class="Bold" id="orderGrandTotal">0</td>
        </tr>
      </tfoot>
    </table>

    <div style="text-align:right; margin-top:.5rem;">
      <input type="button" class="Button" value="Copier" onclick="copyOrder()">
      <input type="button" class="Button" value="Fermer" onclick="closeOrder()">
    </div>
  </div>
</div>


<?php include('Common/Templates/tail.php'); ?>
