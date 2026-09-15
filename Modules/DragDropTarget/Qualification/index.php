<?php
require_once(dirname(__FILE__, 3) . '/config.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/Fun_Sessions.inc.php');
require_once('Common/Lib/CommonLib.php');

CheckTourSession(true);
checkACL(AclQualification, AclReadWrite);

require_once(__DIR__ . '/models.php');

$sessId = isset($_GET['sessId']) ? intval($_GET['sessId']) : 1;
$sortBy = isset($_GET['sort'])   ? intval($_GET['sort'])   : 0;

$session = new QP_Session($_SESSION['TourId'], $sessId);



// If session not found, use the first available one
if (($session->targets + $session->ath )==0 && !empty($session->tour->sessions)) {
    $firstSess = reset($session->tour->sessions);
    $sessId    = $firstSess->id;
    $session   = new QP_Session($_SESSION['TourId'], $sessId);
}

// Colors per structure (club/country) — includes all tournament participants
$structColors = [];
$colorPalette = ['#FFD6D6','#D6FFD6','#D6D6FF','#FFFFD6','#FFD6FF','#D6FFFF','#FFE8D6','#E8D6FF','#D6FFE8','#FFD6E8'];
$colorIdx = 0;
// Current session
foreach ($session->participants as $p) {
    if (!isset($structColors[$p->structId])) {
        $structColors[$p->structId] = $colorPalette[$colorIdx % count($colorPalette)];
        $colorIdx++;
    }
}
// Archers sans départ (absents de la session courante)
$rsAllStruct = safe_r_sql("SELECT EnCountry
    FROM Entries
    INNER JOIN Qualifications ON EnId = QuId and QuSession = 0
    WHERE EnAthlete = 1 AND EnTournament = {$_SESSION['TourId']}
    group by EnCountry");
while ($rAllStruct = safe_fetch($rsAllStruct)) {
    $sid = intval($rAllStruct->EnCountry);
    if (!isset($structColors[$sid])) {
        $structColors[$sid] = $colorPalette[$colorIdx % count($colorPalette)];
        $colorIdx++;
    }
}

$PAGE_TITLE = get_text('MenuLM_DragDropTarget');
$IncludeJquery = true;
//$svgBase = $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/svg/';
$svgBase = $CFG->ROOT_DIR . 'Common/Images/Targets/';

$JS_SCRIPT = [
    '<link rel="stylesheet" href="' . $CFG->ROOT_DIR . 'Modules/DragDropTarget/lib/dragula.min.css">',
    '<link rel="stylesheet" href="' . $CFG->ROOT_DIR . 'Modules/DragDropTarget/Qualification/qualification.css">',
    '<script src="' . $CFG->ROOT_DIR . 'Modules/DragDropTarget/lib/dragula.min.js"></script>',
    phpVars2js([
        'QP_ROOT' => $CFG->ROOT_DIR . 'Modules/DragDropTarget/Qualification/',
        'QP_SESS_ID' => $sessId,
        'QP_SORT' => $sortBy,
        'QP_POPEDIT_URL' => $CFG->ROOT_DIR . 'Partecipants/PopEdit.php',
        'QP_DELROW_URL' => $CFG->ROOT_DIR . 'Partecipants/DeleteRow.php',
    ]),
	 phpVars2js([
        'Lng_UnassignAllOfSession' =>  get_text('UnassignAllOfSession','DragDropTarget'),
        'Lng_UnassignAllOfTarget' =>  get_text('UnassignAllOfTarget','DragDropTarget'),
        'Lng_GlobalRecap' =>  get_text('GlobalRecap','DragDropTarget'),
        'Lng_FaceType' =>  get_text('FaceType','DragDropTarget'),
        'Lng_Quantity' =>  get_text('Quantity','DragDropTarget'),
        'Lng_Coeff' =>  get_text('Coeff','DragDropTarget'),
        'Lng_Total' =>  get_text('Total'),
        'Lng_GrandTotal' =>  get_text('GrandTotal','DragDropTarget'),
        'Lng_CopyCart' =>  get_text('CopyCart','DragDropTarget'),
		'Lng_RemoveArcher' =>  get_text('RemoveArcher','DragDropTarget')
,    ]),
    '<script src="' . $CFG->ROOT_DIR . 'Modules/DragDropTarget/Qualification/qualification.js"></script>',
];

// Display name and time
$headerName = !empty($session->name) ? $session->name : sprintf('%s %d', get_text('Session'), $sessId);
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
     Header: session / grouping selectors
     ============================================================ -->
<table class="Tabella">
  <tr>
    <th class="Title" colspan="3">
      <?= $PAGE_TITLE ?>
    </th>
  </tr>
  <tr>
    <td style="width:220px; vertical-align:top; padding:4px;">
      <form method="get">
        <table>
          <tr>
            <td><label for="sessId"><?= get_text('Session') ?>&nbsp;:</label></td>
            <td>
              <select name="sessId" id="sessId" onchange="this.form.submit()">
                <?php foreach ($session->tour->sessions as $ses): ?>
                  <option value="<?= $ses->id ?>" <?= ($sessId == $ses->id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars(!empty($ses->name) ? $ses->name : sprintf('%s %d', get_text('Session'), $ses->id)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <td><label for="sort"><?= get_text('GroupBy','Tournament') ?>&nbsp;:</label></td>
            <td>
              <select name="sort" id="sort" onchange="this.form.submit()">
                <option value="0" <?= ($sortBy == 0) ? 'selected' : '' ?>><?= get_text('TargetFace') ?></option>
                <option value="1" <?= ($sortBy == 1) ? 'selected' : '' ?>><?= get_text('Classes', 'Tournament') ?></option>
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
      <!-- Display toggles -->
      <label style="font-size:.85em; display:block; margin-bottom:3px;">
        <input type="checkbox" id="toggleArcher" checked onchange="hideSwitch()">
        <?= get_text('ShowParticipants', 'Tournament') ?>
      </label>
    </td>
  </tr>
</table>

<!-- ============================================================
     Target face summary + actions
     ============================================================ -->
<div class="qp-bandeau">
  <span class="qp-label"><?= get_text('Target') ?>&nbsp;:</span>
  <span id="recapBlason" class="qp-recap"><?= get_text('Loading', 'Tournament') ?></span>
  <input type="button" class="Button" value="<?= htmlspecialchars(get_text('PrintTargets', 'Tournament')) ?>" onclick="printTargets()">
  <input type="button" class="Button" value="<?= htmlspecialchars(get_text('PrintTargetFacesSummary', 'Tournament')) ?>"          onclick="openGlobalRecap()">
  <input type="button" class="Button" value="<?= htmlspecialchars(get_text('TargetFacesOrder', 'Tournament')) ?>" onclick="openOrder()">
  <input type="button" class="Button" value="+ <?= htmlspecialchars(get_text('AddParticipant', 'Tournament')) ?>" onclick="openPopEdit(0, 0)" style="font-weight:bold;">
  <input type="button" class="Button" value="<?= htmlspecialchars(get_text('TargetAssErase', 'Tournament')) ?>" onclick="clearAllCibles()" style="color:#c00; font-weight:bold;">
</div>

<!-- Print header -->
<div id="printHeader">
  <strong><?= htmlspecialchars($session->tour->name) ?> — <?= htmlspecialchars($headerName) ?><?= $startTime ? ' — ' . $startTime : '' ?></strong>
</div>
<!-- Tournament name only (used by the global summary) -->
<span id="tourNameOnly" style="display:none"><?= htmlspecialchars($session->tour->name) ?></span>

<!-- Print cover page: target face summary with SVG images -->
<div id="printBlasonRecap">
  <div class="pbr-title"><?= get_text('PrintTargetFacesSummaryHeader', 'Tournament') ?></div>
  <div id="printBlasonBody"><!-- filled by AJAX blasonRecapPrint --></div>
</div>

<!-- ============================================================
     Main layout: picking list | targets
     ============================================================ -->
<div class="qp-layout">

  <!-- Left column: picking list -->
  <div class="qp-picking-col">
      <label style="font-size:.85em; display:block; margin-bottom: 4px;">
        <input type="checkbox" id="toggleAffected" checked onchange="hideAffectedSwitch()">
        <?= get_text('ShowAssignedParticipants', 'Tournament') ?>
      </label>
    <div class="qp-search-wrap">
      <input type="text" id="qpSearch" placeholder="<?= htmlspecialchars(get_text('DragDropSearchPlaceholder', 'Tournament')) ?>…" autocomplete="off">
      <span id="qpSearchClear" title="<?= htmlspecialchars(get_text('CmdClear')) ?>" onclick="clearSearch()">✕</span>
    </div>
    <div id="PickingList" class="qp-picking-list">
      <?php if ($sortBy == 1): ?>
        <!-- Grouped by category -->
        <?php foreach ($session->listByCategory() as $cat):
          $catDists = $cat->distances; ksort($catDists);
          $catDistStr = !empty($catDists) ? ' - ' . implode('/', $catDists) . 'm' : '';
        ?>
          <div class="pq-halo-category qp-accordion-item" id="tcat-<?= htmlspecialchars($cat->name) ?>"
		  data-pq-category="<?= $cat->name ?>"
		  >
            <div class="qp-accordion-header"
                 onclick="qpToggle(this)">
              <span><?= htmlspecialchars($cat->name . $catDistStr) ?></span>
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
        <!-- Grouped by target face (physical type) × distance -->
        <?php $blasonIdx = 0; foreach ($session->blasonDistanceGroups() as $group): $blasonIdx++;
          $alias    = $group['alias'];
          $distance = $group['distance'];
          $label    = $alias . ($distance > 0 ? ' - ' . $distance . 'm' : '');
        ?>
          <div class="pq-halo-blason qp-accordion-item" id="tgl-<?= $blasonIdx ?>"
		  data-pq-blason="<?= htmlspecialchars($alias, ENT_QUOTES) ?>"
		  data-pq-distance="<?= $distance ?>"
		  >
            <div class="qp-accordion-header"
                 onclick="qpToggle(this)">
              <span><?= htmlspecialchars($label) ?></span>
              <span class="qp-counts">
                (<span class="memberAffectedCount">-</span>/<span class="memberCount">-</span>)
              </span>
              <span class="qp-chevron">▼</span>
            </div>
            <div class="qp-accordion-body qp-hidden">
              <div class="ddsrc"
                   id="blsItem-<?= $blasonIdx ?>"
                   data-blason=""
                   data-blason-alias="<?= htmlspecialchars($alias, ENT_QUOTES) ?>"
                   data-blason-distance="<?= $distance ?>"
                   data-category="">
                <div class="blasonContent dragula-container"></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- Archers without session section (always shown, loaded separately) -->
      <div class="qp-accordion-item qp-unassigned-section" id="tgl-unassigned">
        <div class="qp-accordion-header qp-unassigned-header" onclick="qpToggle(this)">
          <span><?= get_text('WithoutSession', 'DragDropTarget') ?></span>
          <span class="qp-counts">
            (<span class="memberAffectedCount">0</span>/<span class="memberCount">?</span>)
          </span>
          <span class="qp-chevron">▼</span>
        </div>
        <div class="qp-accordion-body qp-hidden">
          <div class="ddsrc" id="blsItem-unassigned" data-unassigned="1" data-blason="" data-blason-alias="" data-category="">
            <div class="blasonContent dragula-container"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right column: target zone -->
  <div class="qp-targets-col">
    <input type="hidden" id="departId" value="<?= $sessId ?>">
    <div id="targetsArea" class="qp-targets-area">
      <?php for ($c = 1; $c <= $session->targets; $c++): ?>
        <div id="Cible-<?= $c ?>" class="qp-cible-wrap">
          <input type="hidden" class="cibleNum" value="<?= $c ?>">
          <!-- Target card (placeholder, replaced by AJAX) -->
          <div class="qp-cible-card qp-border-primary">
            <div class="qp-cible-header">
              <span><?= get_text('Target') ?> <?= $c ?></span>
              <span class="btRm" onclick="removeCibleConfirm(this)" title="<?= get_text('UnassignTarget', 'DragDropTarget') ?>">✕</span>
            </div>
            <div class="qp-blasons-row" style="background:cornsilk; min-height:40px; display:flex; align-items:center; justify-content:center;">
              <img src="<?= htmlspecialchars($svgBase . '0.svg') ?>"
                   alt="" style="max-height:40px; max-width:40px; width:auto; height:auto; opacity:.2;">
            </div>
            <div style="text-align:center; padding:2px;">
              <em style="color:#aaa; font-size:.75em;"><?= get_text('Loading', 'Tournament') ?></em>
            </div>
          </div>
          <div id="cb<?= $c ?>" class="qp-cible-names nameArcher qp-border-primary">
            <em style="color:#aaa; font-size:.75em;"><?= get_text('Loading', 'Tournament') ?></em>
          </div>
        </div>
      <?php endfor; ?>
    </div>
  </div>

</div>

<!-- ============================================================
     Target face order modal (pure CSS, no JS framework)
     ============================================================ -->
<div id="orderModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.4);
     align-items:center; justify-content:center; padding:1rem; z-index:2000;">
  <div id="orderCard" style="background:#fff; width:min(800px,95vw); max-height:90vh;
       overflow:auto; border-radius:4px; box-shadow:0 6px 24px rgba(0,0,0,.3); padding:1rem;">
    <button type="button"
            style="position:absolute;top:.5rem;right:.5rem;border:none;background:transparent;font-size:1.2rem;cursor:pointer;"
            onclick="closeOrder()">✕</button>
    <h3 style="text-align:center; margin:0 0 .5rem 0; font-size:1.1em;"><?= get_text('OrderFaces', 'DragDropTarget') ?></h3>

    <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; margin-bottom:.5rem;">
      <label style="font-size:.85em;"><?= get_text('GlobalCoeff', 'DragDropTarget') ?></label>
      <input id="globalCoeff" type="number" step="1" min="0" value="1" style="width:5em;"
             oninput="applyGlobalCoeff(this.value)">
      <input type="button" class="Button" value="<?= get_text('CmdRefresh') ?>" onclick="refreshFromRecap()">
      <input type="button" class="Button" value="<?= get_text('AddRow', 'DragDropTarget') ?>"    onclick="addOrderRow()">
      <span style="font-size:.75em; color:#666; margin-left:auto;">
        40cm Trispot CO → ×5 &nbsp;|&nbsp; 40cm → ×2 &nbsp;|&nbsp; 60/80cm Unique → ÷4
      </span>
    </div>

    <table class="Tabella" id="orderTable">
      <thead>
        <tr class="Main">
          <th><?= get_text('FaceType', 'DragDropTarget') ?></th>
          <th><?= get_text('Quantity', 'DragDropTarget') ?></th>
          <th><?= get_text('Coeff', 'DragDropTarget') ?></th>
          <th><?= get_text('Total') ?></th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="Bold Right"><?= get_text('GrandTotal', 'DragDropTarget') ?></td>
          <td class="Bold" id="orderGrandTotal">0</td>
        </tr>
      </tfoot>
    </table>

    <div style="text-align:right; margin-top:.5rem;">
      <input type="button" class="Button" value="<?= htmlspecialchars(get_text('Copy', 'DragDropTarget')) ?>" onclick="copyOrder()">
      <input type="button" class="Button" value="<?= htmlspecialchars(get_text('Close')) ?>" onclick="closeOrder()">
    </div>
  </div>
</div>


<!-- ============================================================
     PopEdit modal (iframe — opener=null → no page reload)
     ============================================================ -->
<div id="qpPeModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5);
     align-items:center; justify-content:center; z-index:3000;">
  <div style="background:#fff; width:min(960px,96vw); height:92vh; border-radius:4px;
       box-shadow:0 8px 32px rgba(0,0,0,.4); display:flex; flex-direction:column;
       overflow:hidden; position:relative;">
    <button onclick="closePopEditModal()"
            title="Fermer sans rafraîchir"
            style="position:absolute;top:.35rem;right:.5rem;border:none;background:transparent;
                   font-size:1.3rem;cursor:pointer;z-index:1;line-height:1;">✕</button>
    <iframe id="qpPeIframe" src="about:blank"
            style="flex:1; border:none; width:100%; height:100%;"></iframe>
  </div>
</div>

<?php include('Common/Templates/tail.php'); ?>
