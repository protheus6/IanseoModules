<?php
require_once(dirname(__FILE__, 3) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');
require_once('Common/Lib/CommonLib.php');

CheckTourSession(true);
checkACL(AclQualification, AclReadWrite);

require_once(__DIR__ . '/lib/models.php');

$action   = isset($_GET['action'])   ? $_GET['action']          : '';
$sessId   = isset($_GET['sessId'])   ? intval($_GET['sessId'])   : 1;
$tourId   = $_SESSION['TourId'];

// Chemin URL vers le dossier svg/
$svgBase = $CFG->ROOT_DIR . 'Modules/Custom/PlanQualifs/svg/';

switch ($action) {

    // ---------------------------------------------------------------
    // Récap global : tableau tous départs × tous types de blasons
    // ---------------------------------------------------------------
    case 'blasonRecapGlobal':
        $tour     = new QP_TourInfo($tourId);
        $sessions = $tour->sessions; // [ sessOrder => stdClass(id, name) ]

        // Charger chaque session et agréger par alias
        // Structure : $matrix[alias] = ['blason'=>QP_Blason, 'sessions'=>[sessOrder=>physicalCount]]
        $matrix   = [];
        $sessLabels = [];
        foreach ($sessions as $sOrder => $sInfo) {
            $sess  = new QP_Session($tourId, $sOrder);
            $label = !empty($sInfo->name) ? $sInfo->name : 'Départ ' . $sOrder;
            $sessLabels[$sOrder] = $label;
            foreach ($sess->blasonCountGrouped() as $alias => $blason) {
                if (!isset($matrix[$alias])) {
                    $matrix[$alias] = ['blason' => $blason, 'sessions' => []];
                }
                $matrix[$alias]['sessions'][$sOrder] = $blason->physicalCount;
                // Garder le blason avec le plus grand imgTaille pour l'image
                if ($blason->imgTaille > $matrix[$alias]['blason']->imgTaille) {
                    $matrix[$alias]['blason'] = $blason;
                }
            }
        }

        if (empty($matrix)) {
            echo '<em style="color:#999;">Aucun blason affecté.</em>';
            break;
        }

        $maxTaille = 1;
        foreach ($matrix as $row) {
            if ($row['blason']->imgTaille > $maxTaille) $maxTaille = $row['blason']->imgTaille;
        }
        $cellSize = $maxTaille;

        $html  = '<table class="Tabella" style="border-collapse:collapse;font-size:.88em;">';
        $html .= '<thead><tr class="Main">';
        $html .= '<th>Blason</th><th>Type</th>';
        foreach ($sessLabels as $sOrder => $label) {
            $html .= '<th style="text-align:center;">' . htmlspecialchars($label) . '</th>';
        }
        $html .= '<th style="text-align:center;">Total</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($matrix as $alias => $row) {
            $blason   = $row['blason'];
            $svgUrl   = $svgBase . $blason->svgFile;
            $imgSize  = $blason->imgTaille;
            $imgStyle = 'width:' . $imgSize . 'px; height:auto; display:block; margin:auto;';
            $total    = 0;

            $html .= '<tr>';
            $html .= '<td style="text-align:center;vertical-align:middle;width:' . $cellSize . 'px;">'
                   . '<img src="' . htmlspecialchars($svgUrl) . '" style="' . $imgStyle . '" alt="' . htmlspecialchars($alias) . '">'
                   . '</td>';
            $html .= '<td style="vertical-align:middle;">' . htmlspecialchars($alias) . '</td>';
            foreach ($sessLabels as $sOrder => $label) {
                $qty   = $row['sessions'][$sOrder] ?? 0;
                $total += $qty;
                $html .= '<td style="text-align:center;vertical-align:middle;font-weight:' . ($qty > 0 ? 'bold' : 'normal') . ';color:' . ($qty > 0 ? '#000' : '#bbb') . ';">' . ($qty > 0 ? $qty : '—') . '</td>';
            }
            $html .= '<td style="text-align:center;vertical-align:middle;font-weight:bold;background:#f5f5f5;">' . $total . '</td>';
            $html .= '</tr>';
        }

        // Ligne totaux par session
        $html .= '<tr style="background:#eee;">';
        $html .= '<td colspan="2" style="text-align:right;font-weight:bold;">Total</td>';
        $grandTotal = 0;
        foreach ($sessLabels as $sOrder => $label) {
            $colTotal = 0;
            foreach ($matrix as $row) { $colTotal += $row['sessions'][$sOrder] ?? 0; }
            $grandTotal += $colTotal;
            $html .= '<td style="text-align:center;font-weight:bold;">' . $colTotal . '</td>';
        }
        $html .= '<td style="text-align:center;font-weight:bold;background:#ddd;">' . $grandTotal . '</td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';
        echo $html;
        break;

    // ---------------------------------------------------------------
    // Récap blasons : badges texte dans le bandeau
    // ---------------------------------------------------------------
    case 'blasonRecap':
        $session = new QP_Session($tourId, $sessId);
        $items   = $session->blasonCountGrouped();
        $html    = '';
        foreach ($items as $blId => $blason) {
            $html .= '<span class="pq-halo-blason qp-recap-item"'
                   . ' data-blason-id="' . $blId . '"'
                   . ' data-pq-blason="' . $blason->id . '"'
                   . ' data-face="'     . htmlspecialchars($blason->displayName()) . '"'
                   . ' data-count="'   . $blason->physicalCount . '">'
                   . htmlspecialchars($blason->displayName()) . '&nbsp;: <strong>' . $blason->physicalCount . '</strong>'
                   . '</span> ';
        }
        echo $html ?: '<em style="color:#999;">Aucun blason</em>';
        break;

    // ---------------------------------------------------------------
    // Page de garde impression : récap blasons avec images SVG
    // ---------------------------------------------------------------
    case 'blasonRecapPrint':
        $session   = new QP_Session($tourId, $sessId);
        $items     = $session->blasonCountGrouped();
        // Même logique que les cibles : imgTaille comme référence
        $maxTaille = 1;
        foreach ($items as $blason) {
            if ($blason->imgTaille > $maxTaille) $maxTaille = $blason->imgTaille;
        }
        $cellSize = $maxTaille; // la cellule fait la taille du plus grand imgTaille
        $html    = '<table class="pbr-print-table">';
        $html   .= '<thead><tr>'
                 . '<th>Blason</th>'
                 . '<th>Type</th>'
                 . '<th>Archers/blason</th>'
                 . '<th>Quantité</th>'
                 . '</tr></thead><tbody>';
        foreach ($items as $blId => $blason) {
            $svgUrl   = $svgBase . $blason->svgFile;
            $imgSize  = $blason->imgTaille; // même valeur que dans les cibles
            $imgStyle = 'width:' . $imgSize . 'px; height:auto; display:block; margin:auto;';
            $html   .= '<tr>'
                     . '<td class="pbr-svg-cell" style="width:' . $cellSize . 'px;height:' . $cellSize . 'px;text-align:center;vertical-align:middle;">'
                     . '<img src="' . htmlspecialchars($svgUrl) . '"'
                     . ' style="' . $imgStyle . '"'
                     . ' alt="'   . htmlspecialchars($blason->displayName()) . '">'
                     . '</td>'
                     . '<td>' . htmlspecialchars($blason->displayName()) . '</td>'
                     . '<td style="text-align:center;">' . $blason->imgNbArcher . '</td>'
                     . '<td style="text-align:center;font-weight:bold;">' . $blason->physicalCount . '</td>'
                     . '</tr>';
        }
        $html .= '</tbody></table>';
        echo $html ?: '<em style="color:#999;">Aucun blason</em>';
        break;

    // ---------------------------------------------------------------
    // Picking list : liste des archers (par blason ou par catégorie)
    // ---------------------------------------------------------------
    case 'pickingList':
        $tfId   = isset($_GET['tfId'])  ? intval($_GET['tfId'])  : 0;
        $cat    = isset($_GET['cat'])   ? trim($_GET['cat'])      : '';
        $sort   = isset($_GET['sort'])  ? intval($_GET['sort'])   : 0;
        $session = new QP_Session($tourId, $sessId, $tfId, 0, $cat);

        foreach ($session->participants as $item):
            $bgcol    = 'bgstru' . $item->structId;
            $affected = $item->target > 0;
            $blasonType = isset($item->blason)
                ? 'acc-' . $item->blason->imgH . '-' . $item->blason->imgV
                : '';
            ?>
            <div class="pq-halo-archer qp-picker-item<?= $affected ? ' affected' : '' ?>"
			data-pq-struct="<?= $item->structId ?>"
			data-pq-category="<?= $cat ?>"
			data-pq-blason="<?= $item->blason->id ?>"
			>
              <input type="hidden" class="archerId"   value="<?= $item->id ?>">
              <input type="hidden" class="cibleNum"   value="<?= $item->target ?>">
              <input type="hidden" class="blasonType" value="<?= $blasonType ?>">
              <!-- Ligne visible dans zone cible (ddtrg) -->
              <div class="<?= $bgcol ?> disptrg" data-struct="<?= $item->structId ?>">
                <span class="archers"><?= htmlspecialchars($item->getCategory() . ' - ' . $item->getNomCourt()) ?></span>
              </div>
              <!-- Ligne visible dans picking list (dispsrc) -->
              <div class="dispsrc <?= $bgcol ?> qp-src-card"
                   data-struct="<?= $item->structId ?>">
                <?php if ($affected): ?>
                  <span class="qp-check">✔</span>
                <?php endif; ?>
                <span class="archers">
                  <?php
                    if ($sort == 1) {
                        echo htmlspecialchars($item->getNomCourt() . ' (' . $item->getCible() . ')');
                    } else {
                        echo htmlspecialchars($item->getCategory() . ' — ' . $item->getNomCourt() . ' (' . $item->getCible() . ')');
                    }
                  ?>
                </span><br>
                <span class="archers" style="color:#555;"><?= htmlspecialchars($item->structName) ?></span>
              </div>
            </div>
            <?php
        endforeach;
        break;

    // ---------------------------------------------------------------
    // Détail d'une cible
    // ---------------------------------------------------------------
    case 'cible':
        $cibleNum = isset($_GET['cibleNum']) ? intval($_GET['cibleNum']) : 0;
        $cible    = new QP_Cible($tourId, $sessId, $cibleNum);
        qp_render_cible($cible, $svgBase);
        break;

    // ---------------------------------------------------------------
    // Déplacer un archer
    // ---------------------------------------------------------------
    case 'moveArcher':
        $archerId = isset($_GET['archerId']) ? intval($_GET['archerId']) : 0;
        $cNum     = isset($_GET['cNum'])     ? intval($_GET['cNum'])     : 0;
        $cLetter  = isset($_GET['cLetter'])  ? intval($_GET['cLetter'])  : 0;
        $upd = new QP_UpdateParticipant($tourId, $archerId, $sessId);
        $upd->updateParticipant($cNum, $cLetter);
        http_response_code(200);
        break;

    // ---------------------------------------------------------------
    // Vider une cible
    // ---------------------------------------------------------------
    case 'clearCible':
        $cibleNum = isset($_GET['cibleNum']) ? intval($_GET['cibleNum']) : 0;
        $cible    = new QP_Cible($tourId, $sessId, $cibleNum);
        $cible->clear();
        http_response_code(200);
        break;

    default:
        http_response_code(400);
        echo 'Action inconnue';
        break;
}

// ---------------------------------------------------------------
// Rendu HTML d'une cible
// ---------------------------------------------------------------
function qp_render_cible(QP_Cible $cible, string $svgBase = '')
{
    $warnColors = [0 => 'primary', 1 => 'success', 2 => 'warning', 3 => 'danger', 4 => 'danger'];
    $warnLabels = [0 => 'libre', 1 => 'Complet', 2 => 'Struct majoritaire', 3 => 'Structure unique', 4 => 'Dist. mixtes'];
    $wc = $warnColors[$cible->warnLevel] ?? 'primary';
    $wl = $warnLabels[$cible->warnLevel] ?? '';
    ?>
    <input type="hidden" class="cibleNum" value="<?= $cible->num ?>">

    <!-- Carte principale -->
    <div class="qp-cible-card qp-border-<?= $wc ?>">
      <span class="qp-warn-badge qp-bg-<?= $wc ?>"><?= htmlspecialchars($wl) ?></span>

      <div class="qp-cible-header">
        <span>Cible <?= $cible->num ?> (<?= $cible->distance->distance ?>m)</span>
        <span class="btRm" onclick="removeCible(this)" title="Vider">✕</span>
      </div>

      <!-- Étiquettes vagues -->
      <div class="qp-vagues-labels">
        <?php if (count($cible->vagues) == 4): ?>
          <span class="qp-vague-label"><?= $cible->vagues[1]->label ?>/<?= $cible->vagues[3]->label ?></span>
          <span class="qp-vague-label"><?= $cible->vagues[2]->label ?>/<?= $cible->vagues[4]->label ?></span>
        <?php else: ?>
          <span class="qp-vague-label"><?= implode('/', array_map(fn($v) => $v->label, $cible->vagues)) ?></span>
        <?php endif; ?>
      </div>

      <?php
      /*
       * Zone blasons — logique identique à TargetPlan/CibleUnique.php :
       * - Flexbox row, chaque colonne = flex:1
       * - Image : width:[taille]px, hauteur auto (le viewBox fait le reste)
       * - Les colonnes correspondent à getVaguesOrdered() (groupes A/C et B/D)
       * - Un blason avec imgH=2 s'affiche en full-width (une seule colonne fusionnée)
       */
      $hasBlason     = count(array_filter($cible->vagues, fn($v) => isset($v->blason))) > 0;
      $vaguesOrdered = $cible->getVaguesOrdered();

      /*
       * Règles d'affichage selon H/V :
       *   H2 V1 → 1 archer/blason, 2 blasons côte à côte (row)
       *   H1 V2 → 1 archer/blason, 2 blasons empilés verticalement (column)
       *   H2 V2 → 2 archers/blason, 1 blason par colonne
       *   H2 V4 → 4+ archers/blason, 1 blason pleine largeur
       *
       * Discriminant : imgH
       *   imgH=1 → 1 archer/blason, plusieurs images empilées dans la colonne
       *   imgH=2 + imgV=1 → 1 archer/blason, plusieurs images côte à côte
       *   imgH=2 + imgV>=2 → N archers/blason, 1 seule image par colonne
       *   imgH=2 + imgV>=4 → blason unique pleine largeur
       */
      $colBlasons = []; // [colIdx] => [ ['blason'=>..., 'overlay'=>...], ... ]
      foreach ($vaguesOrdered as $colIdx => $vaguesOrder) {
          $colBlasons[$colIdx] = [];
          // Détecter H et V de la colonne (depuis le premier blason trouvé)
          $colImgH = 2; $colImgV = 2;
          foreach ($vaguesOrder as $vague) {
              if (isset($vague->blason)) {
                  $colImgH = $vague->blason->imgH;
                  $colImgV = $vague->blason->imgV;
                  break;
              }
          }

          // Plusieurs images par colonne : H=1 (empilé) ou H=2+V=1 (côte à côte)
          $multiplePerCol = ($colImgH === 1) || ($colImgH >= 2 && $colImgV === 1);

          if ($multiplePerCol) {
              // 1 archer par blason : 1 image par vague (réelle ou overlay)
              // La colonne entièrement vide (sans aucun blason) n'affiche rien
              foreach ($vaguesOrder as $vague) {
                  if (isset($vague->blason)) {
                      $colBlasons[$colIdx][] = ['blason' => $vague->blason, 'overlay' => $vague->overlay];
                  }
              }
          } else {
              // N archers par blason : 1 seule image par colonne
              foreach ($vaguesOrder as $vague) {
                  if (isset($vague->blason)) {
                      // overlay seulement si aucun archer réel dans cette colonne
                      $hasReal = false;
                      foreach ($vaguesOrder as $v2) {
                          if (isset($v2->blason) && !$v2->overlay) { $hasReal = true; break; }
                      }
                      $colBlasons[$colIdx][] = ['blason' => $vague->blason, 'overlay' => !$hasReal];
                      break;
                  }
              }
          }
      }

      // Blason unique pleine largeur : imgV>=4, même blason dans les 2 cols
      $blasonUnique = null;
      $col0first = $colBlasons[0][0]['blason'] ?? null;
      $col1first = $colBlasons[1][0]['blason'] ?? null;
      if ($col0first && $col1first
          && $col0first->id === $col1first->id
          && $col0first->imgV >= 4) {
          $blasonUnique = $col0first;
      }

      ?>
      <!-- Représentation blasons : hauteur fixe CSS (voir .qp-blasons-row) -->
      <div class="qp-blasons-row">
        <?php if ($hasBlason): ?>
          <?php if ($blasonUnique): ?>
            <!-- Blason pleine largeur (imgV>=4) -->
            <?php $hasRealArcher = count(array_filter($cible->vagues, fn($v) => isset($v->blason) && !$v->overlay)) > 0; ?>
            <div class="pq-halo-blason" style="flex:1; display:flex; flex-direction:column; align-items:center; <?= !$hasRealArcher ? 'opacity:.35;' : '' ?>"
			data-pq-blason="<?= $blasonUnique->id ?>"
			>
              <?php if ($svgBase): ?>
                <img src="<?= htmlspecialchars($svgBase . $blasonUnique->svgFile) ?>"
                     alt="<?= htmlspecialchars($blasonUnique->label) ?>"
                     title="<?= htmlspecialchars($blasonUnique->name) ?>"
                     style="width:<?= $blasonUnique->imgTaille ?>px; height:auto; display:block; margin:auto;">
              <?php endif; ?>
              <div style="font-size:.68em; text-align:center; color:#555; line-height:1.1;">
                <?= htmlspecialchars($blasonUnique->label) ?>
              </div>
            </div>
          <?php else: ?>
            <!-- Blasons par colonne -->
            <?php foreach ($colBlasons as $colIdx => $entries): ?>
              <?php
              $firstBlason = $entries[0]['blason'] ?? null;
              // H2 V1 → côte à côte (row)
              // H1 V2 → empilés (column)
              // H2 V2+ → 1 seul blason, column par défaut
              $colDirection = ($firstBlason && $firstBlason->imgH >= 2 && $firstBlason->imgV === 1)
                              ? 'row' : 'column';
              ?>
              <div style="flex:1; display:flex; flex-direction:<?= $colDirection ?>; align-items:center; justify-content:center; gap:2px;">
                <?php foreach ($entries as $entry):
                  $blason    = $entry['blason'];
                  $isOverlay = $entry['overlay'];
                  ?>
                  <?php if ($svgBase): ?>
                    <div class="pq-halo-blason" style="<?= $isOverlay ? 'opacity:.35;' : '' ?>display:flex; flex-direction:column; align-items:center;"
					data-pq-blason="<?= $blason->id ?>"
					>
                      <img src="<?= htmlspecialchars($svgBase . $blason->svgFile) ?>"
                           alt="<?= htmlspecialchars($blason->label) ?>"
                           title="<?= htmlspecialchars($blason->name) ?>"
                           style="width:<?= $blason->imgTaille ?>px; height:auto; display:block; margin:auto;">
                      <div style="font-size:.68em; text-align:center; color:#555; line-height:1.1;">
                        <?= htmlspecialchars($blason->label) ?>
                      </div>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php else: ?>
          <!-- Cible vide -->
          <?php if ($svgBase): ?>
            <img src="<?= htmlspecialchars($svgBase . 'Empty.svg') ?>"
                 alt="Vide"
                 style="width:40px; height:auto; opacity:.2; display:block; margin:auto;">
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Zone noms archers (drag & drop) -->
    <div id="cb<?= $cible->num ?>" class="qp-cible-names nameArcher qp-border-<?= $wc ?>">
      <?php foreach ($cible->getVaguesOrdered() as $vaguesOrder): ?>
        <?php foreach ($vaguesOrder as $vague): ?>
          <?php
            $bgcol      = isset($vague->participant) ? 'bgstru' . $vague->participant->structId : '';
            $blasonType = isset($vague->blason) ? 'acc-' . $vague->blason->imgH . '-' . $vague->blason->imgV : '';
            $catClass   = isset($vague->participant) ? 'tcat-' . htmlspecialchars($vague->participant->getCategory()) : '';
          ?>
          <div class="qp-vague-slot <?= isset($vague->blason) ? 'tgl-' . $vague->blason->id : '' ?> <?= $catClass ?>">
            <input type="hidden" class="cibleNum"    value="<?= $vague->target ?>">
            <input type="hidden" class="cibleLetter" value="<?= $vague->order ?>">
            <div class="qp-vague-slot-label"><?= $vague->label ?></div>
            <div class="dragula-container ddtrg <?= $blasonType ?>" style="min-height:50px;">
              <?php if (isset($vague->participant)): ?>
                <div class="pq-halo-archer qp-picker-item" id="archer-container"
				data-pq-struct="<?= $vague->participant->structId ?>"
				data-pq-category="<?= $vague->participant->getCategory() ?>"
				data-pq-blason="<?= $vague->blason->id ?>"
				>
                  <input type="hidden" class="blasonType" value="<?= $blasonType ?>">
                  <input type="hidden" class="archerId"   value="<?= $vague->participant->id ?>">
                  <input type="hidden" class="cibleNum"   value="<?= $vague->participant->target ?>">
                  <div class="<?= $bgcol ?> disptrg"
                       data-struct="<?= $vague->participant->structId ?>"
					   >
                    <span class="archers">
                      <?= htmlspecialchars($vague->participant->getCategory() . ' — ' . $vague->participant->getNomCourt()) ?>
                    </span>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
    <?php
}
?>
