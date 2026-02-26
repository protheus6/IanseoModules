<!-- Icônes Bootstrap (chevrons + imprimante) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
<?php
foreach ($session->getStructColor() as $sId => $color) {
?>
<?php echo '.bgstru'.$sId;?> { background-color: <?php echo $color;?>; }
<?php
    }
?>
/* Chevrons à droite des blocs select */
.form-floating.mb-2 { position: relative; }
.form-floating.mb-2 select.form-control,
.form-floating.mb-2 select.form-select {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background-image: none;
  padding-right: 2rem;
}
.form-floating.mb-2 select::-ms-expand { display: none; }
.select-arrow {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: var(--bs-secondary-color, #6c757d);
  font-size: 1rem;
  line-height: 1;
}
/* Bandeaux (recap + impression + commande) */
.band-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem; margin-bottom: 0.1rem; }
.band-block {
  --band-h: clamp(2.5rem, 2vw + 2rem, 3.5rem);
  min-height: var(--band-h);
  padding: 0.25rem 0.25rem;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
#printBlock .bi-printer { font-size: calc(var(--band-h) - 0.75rem); line-height: 1; }
#printBlock .btn { height: calc(var(--band-h) - 0.75rem); display: inline-flex; align-items: center; padding: 0 0.75rem; }
#orderBlock .bi-bullseye { font-size: calc(var(--band-h) - 0.75rem); line-height: 1; color: #0d6efd; }
#orderBlock .btn { height: calc(var(--band-h) - 0.75rem); }

/* Dessin de la cible — robuste en impression, sans halo */
.target-drawing { padding: 0.175rem; gap: 0.175rem; }
.target-face {
  position: relative;
  width: 100%;
  height: 0;
  padding-top: 100%; /* carré à l'écran */
}
.target-face::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 1px solid #000;
  background:
    radial-gradient(circle,
      #ffd700 0%,
      #ffd700 20%,   /* jaune */
      #ff3b3b 20%,
      #ff3b3b 40%,   /* rouge */
      #1e90ff 40%,
      #1e90ff 60%,   /* bleu */
      #000000 60%,
      #000000 80%,   /* noir */
      #ffffff 80%,
      #ffffff 100%   /* blanc */
    );
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
/* Masquage des affectations (archers) quand .no-assignments est actif */
.no-assignments #targetsArea .assignment-list,
.no-assignments #targetsArea .archer,
.no-assignments #targetsArea .archer-item,
.no-assignments #targetsArea .archer-line,
.no-assignments #targetsArea .affected,
.no-assignments #targetsArea .is-affected,
.no-assignments #targetsArea [data-affected="1"] {
  display: none !important;
}
/* Header d'impression (comme avant, fixe en haut). */
#printHeader { display: none; }
.print-title {
  font-size: 2rem;
  font-weight: 200;
  text-align: center;
  margin: 0;
}
/* Styles d'impression: header fixe et ~90% pour les cibles */
@media print {
  :root { --print-header-h: 1.75cm; }
  @page { margin: 0; }
  body * { visibility: hidden; }
  #printHeader, #printHeader * { visibility: visible; }
  #targetsArea, #targetsArea * { visibility: visible; }
  /* Header fixe en haut de page */
  #printHeader {
    display: block;
    position: fixed;
    top: 0; left: 0; width: 100%;
    height: var(--print-header-h);
    padding: 0.5cm 1cm 0.4cm 1cm;
    box-sizing: border-box;
    background: #fff;
    border-bottom: 1px solid #000;
  }
  /* Zone cibles sous le header, occupe le reste (~90%) */
  #targetsArea {
    position: absolute;
    left: 0;
    top: var(--print-header-h);
    width: 100%;
    height: calc(100vh - var(--print-header-h));
    overflow: visible !important;
    padding: 0 1cm 1cm 1cm !important;
    box-sizing: border-box;
  }
  /* Actions et annexes non imprimées */
  #targetsArea .btRm,
  #targetsArea [id^="cb"] {
    display: none !important;
  }
  /* Masquer toutes les affectations d'archers */
  #targetsArea .assignment-list,
  #targetsArea .archer,
  #targetsArea .archer-item,
  #targetsArea .archer-line,
  #targetsArea .affected,
  #targetsArea .is-affected,
  #targetsArea [data-affected="1"] {
    display: none !important;
  }
  /* Dessin compact à l'impression (taille fixe, sans halo) */
  .target-drawing {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    gap: 0.3cm;
    border: none !important;
    padding: 0 !important;
  }
  .target-face {
    width: 1cm !important;
    height: 1cm !important;
    padding-top: 0 !important;
  }
  .target-face::before { border: 1px solid #000; }
  .cibleNum {
    page-break-inside: avoid;
    break-inside: avoid;
    margin-bottom: 0.6cm;
    border-radius: 0.2cm;
    box-shadow: none !important;
  }
  /* Ne pas imprimer la modale de commande */
  #orderModal { display: none !important; }
  /* Contraste des bordures à l'impression */
  .border, .border-1, .border-2, .border-dark, .border-primary {
    border-color: #000 !important;
  }
}

/* --------- Modale "Commande blasons" sans dépendances JS externes --------- */
#orderModal {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.35);
  display: none; /* toggled via JS */
  align-items: center; justify-content: center;
  padding: 1rem;
  z-index: 1050;
}
#orderCard {
  background: #fff;
  width: min(800px, 95vw);
  max-height: 90vh;
  overflow: auto;
  border-radius: 0.5rem;
  box-shadow: 0 10px 30px rgba(0,0,0,0.25);
  padding: 0.75rem 1rem 1rem;
}
#orderCard h3 {
  font-size: 1.15rem; margin: 0 0 0.5rem 0; text-align: center;
}
#orderControls {
  display: flex; gap: 0.5rem; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;
}
#orderControls .left, #orderControls .right { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
#orderTable {
  width: 100%; border-collapse: collapse; font-size: 0.95rem;
}
#orderTable th, #orderTable td {
  border: 1px solid #dee2e6;
  padding: 0.4rem 0.5rem;
  text-align: left;
}
#orderTable tfoot td { font-weight: 600; }
.order-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.75rem; }
.order-muted { color: #6c757d; font-size: 0.875rem; }
.order-input { width: 6rem; }
.order-type { min-width: 8rem; }
.order-btn { cursor: pointer; }
.order-close {
  position: absolute; top: 0.5rem; right: 0.5rem; border: none; background: transparent; font-size: 1.25rem; line-height: 1; cursor: pointer;
}
</style>

<?php
// Fallback: si la session n'a pas de nom, afficher "Session {id}"
$headerName = !empty($session->name)
  ? $session->name
  : ('Session '.(isset($session->id) ? $session->id : $session->order));
// Heure de début au format HH:MM
$startTime = '';
if (!empty($session->start)) {
    $ts = strtotime($session->start);
    $startTime = $ts ? date('H:i', $ts) : $session->start;
}
?>
<h2><?php echo LANG['PLAN_FOR']; ?> <?php echo $session->tour->name ?></h2>
<div class="row">
  <div class="col-6 col-sm-3">
    <form>
      <div class="form-floating mb-2">
        <select class="form-control form-control-sm" name="sessId" id="DepartNum" placeholder="" onchange="this.form.submit()">
          <?php
          foreach ($session->tour->sessions as $ses) {
            $displayName = !empty($ses->name) ? $ses->name : ('Session '.$ses->id);
            if ($session->order == $ses->id) {
              echo '<option value="'.$ses->id.'" selected >'.$displayName.'</option>';
            } else {
              echo '<option value="'.$ses->id.'">'.$displayName.'</option>';
            }
          }
          ?>    
        </select>
        <label for="DepartNum"><?php echo LANG['SESSION']; ?></label>
        <i class="bi bi-chevron-down select-arrow" aria-hidden="true"></i>
      </div>
      <div class="form-floating mb-2">
        <select class="form-control form-control-sm" name="sort" id="sortBy" placeholder="" onchange="this.form.submit()">
          <option value="0" <?php echo ($sortBy == 0 )?"selected":""; ?> ><?php echo LANG['TARGET_FACE']; ?></option>
          <option value="1" <?php echo ($sortBy == 1 )?"selected":""; ?> ><?php echo LANG['CATEGORIES']; ?></option>
        </select>
        <label for="sortBy"><?php echo LANG['GROUPBY']; ?></label>
        <i class="bi bi-chevron-down select-arrow" aria-hidden="true"></i>
      </div>
    </form>
    <br />
    <input type="hidden" value="<?php echo $sortBy; ?>" id="groupBy" />
  </div>
  <div class="col-9 text-center">
    <h1>
      <?php echo $headerName; ?> - <?php echo $startTime; ?>
    </h1>
  </div>
</div>
<br/>
<div class="container-fluid p-2 m-3">
  <div class="row">
    <div class="col-2 shadow p-2 border rounded-3 overflow-y-auto" style="height:90vh;">
      <div class="form-check form-switch text-start" style="font-size: small; font-weight: normal;">
        <input class="form-check-input" onclick="hideSwitch();" type="checkbox" role="switch" id="toggleArcher" checked="checked">
        <label class="form-check-label"><?php echo LANG['SHOW_ARCHERS']; ?></label>
      </div>
      <div class="form-check form-switch text-start" style="font-size: small; font-weight: normal;">
        <input class="form-check-input" onclick="hideAffectedSwitch();" type="checkbox" role="switch" id="toggleAffected" checked="checked">
        <label class="form-check-label"><?php echo LANG['SHOW_AFFECTED']; ?></label>
      </div>
      <div class="accordion" id="PickingList">
        <?php
        switch ($sortBy) {
          case 0:
            include (VIEWS.$ControlerName.DS."PickingListByBlasons.php");
            break;
          case 1:
            include (VIEWS.$ControlerName.DS."PickingListByCat.php");
            break;
        }
        ?>
      </div>
    </div>
    <div class="col-10">
      <!-- Bandeaux -->
      <div class="band-row">
        <div id="recapBlason" class="band-block border border-2 p-1 border-primary rounded-3">
          <p class="placeholder-glow text-center mb-0">
            <!-- Exemple recommandé:
                 <span class="recap-item" data-face="40cm TriSpot CO" data-count="8">40cm TriSpot CO: 8</span> -->
            <span class="placeholder w-75"></span>
            <span class="placeholder w-25"></span>
            <span class="placeholder w-50"></span>
          </p>
        </div>
        <div id="printBlock" class="band-block border border-2 border-primary rounded-3">
          <i class="bi bi-printer" aria-hidden="true"></i>
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="printTargets()" aria-label="Imprimer les cibles" title="Imprimer les cibles">
            Impression
          </button>
        </div>
        <!-- Commande blasons -->
        <div id="orderBlock" class="band-block border border-2 border-primary rounded-3" role="button" onclick="openOrder()" title="Commande blasons">
          <i class="bi bi-bullseye" aria-hidden="true"></i>
          <button type="button" class="btn btn-outline-secondary btn-sm order-btn">Commande blasons</button>
        </div>
      </div>

      <!-- Header dédié à l'impression (fixe, titre) -->
      <div id="printHeader">
        <h1 class="print-title"><?php echo $headerName; ?> — <?php echo $startTime; ?></h1>
      </div>

      <!-- Zone des cibles -->
      <div id="targetsArea" class="d-flex flex-sm-wrap align-content-start overflow-y-auto m-2" style="height:80vh;">
        <input type="hidden" id="departId" value="<?php echo $session->order; ?>" />
        <?php for($cible = 1; $cible <= $session->targets ; $cible++) { ?>
          <div id="Cible-<?php echo $cible; ?>" class="d-flex flex-column p-0 shadow cibleNum">
            <input type="hidden" id="cibleNum" value="<?php echo $cible; ?>" />
            <div class="d-flex flex-column contain border border-2 p-1 border-primary rounded-3">
              <!-- Entête de la cible -->
              <div class="text-center position-relative target-header">
                <span>Cible <?php echo $cible; ?></span>
                <span class="btRm position-absolute top-0 end-0 badge border border-light rounded-circle bg-light p-0" onclick="removeCible(this)">
                  <i class="bi bi-x-circle" style="color: red;font-size: medium;"></i>
                </span>
              </div>
              <!-- Dessin de la cible -->
              <div class="d-flex flex-row border border-dark rounded-3 text-center target-drawing">
                <div class="flex-fill border border-dark rounded-start-3 p-2">
                  <div class="target-face"></div>
                </div>
                <div class="flex-fill border border-dark rounded-end-3 p-2">
                  <div class="target-face"></div>
                </div>
              </div>
              <!-- Type de cible (si disponible) -->
              <div class="target-type mt-1 text-center">
                <small class="text-muted"><?php /* echo $targetTypeLabel; */ ?>Type de cible</small>
              </div>
              <!-- Affectations d'archers (masquées en print et .no-assignments) -->
              <div class="d-flex flex-column flex-fill assignment-list" style="background-color: cornsilk;">
                <p class="placeholder-glow text-center">
                  <span class="placeholder w-50"></span>
                  <span class="placeholder w-25"></span>
                  <span class="placeholder w-25"></span>
                  <span class="placeholder w-50"></span>
                  <span class="placeholder w-50"></span>
                  <span class="placeholder w-25"></span>
                  <span class="placeholder w-25"></span>
                  <span class="placeholder w-50"></span>
                </p>
              </div>
            </div>
            <!-- Bloc complémentaire: supposé contenir des détails d'archers -->
            <div id="cb<?php echo $cible; ?>" class="d-flex flex-column contain border border-1 p-1 border-primary rounded-3 assignment-list">
              <p class="placeholder-glow text-center">
                <span class="placeholder w-50"></span>
                <span class="placeholder w-25"></span>
                <span class="placeholder w-25"></span>
                <span class="placeholder w-50"></span>
                <span class="placeholder w-50"></span>
                <span class="placeholder w-25"></span>
                <span class="placeholder w-25"></span>
                <span class="placeholder w-50"></span>
              </p>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<!-- Modale Commande blasons -->
<div id="orderModal" aria-hidden="true">
  <div id="orderCard">
    <button class="order-close" type="button" onclick="closeOrder()" aria-label="Fermer">×</button>
    <h3>Commande de blasons</h3>
    <div id="orderControls">
      <div class="left">
        <label for="globalCoeff" class="order-muted">Coeff global</label>
        <input id="globalCoeff" class="form-control form-control-sm order-input" type="number" step="1" min="0" value="1" oninput="applyGlobalCoeff(this.value)" />
        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="refreshFromRecap()" title="Actualiser depuis le récap">Actualiser</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="addOrderRow()" title="Ajouter une ligne">Ajouter une ligne</button>
      </div>
      <div class="right order-muted">
        Règles auto: Balons de 40 → coeff 2 // Trispots Poulies → coeff 5; 60 et 80 Unique → quantité ÷ 4 puis arrondi à l’entier supérieur.
      </div>
    </div>

    <table id="orderTable">
      <thead>
        <tr>
          <th>Type de blason</th>
          <th>Quantité</th>
          <th>Coefficient</th>
          <th>Total à commander</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="3">Total général</td>
          <td id="orderGrandTotal">0</td>
        </tr>
      </tfoot>
    </table>

    <div class="order-actions">
      <button class="btn btn-sm btn-outline-primary" type="button" onclick="copyOrder()">Copier</button>
      <button class="btn btn-sm btn-outline-secondary" type="button" onclick="printOrder()">Imprimer la commande (non implémenté)</button>
      <button class="btn btn-sm btn-primary" type="button" onclick="closeOrder()">Fermer</button>
    </div>
  </div>
</div>

<script>
/**
* Impression: masque uniquement les affectations (archers) en appliquant .no-assignments,
* puis restaure l'état initial après impression.
*/
function printTargets() {
  const body = document.body;
  body.classList.add('no-assignments');
  const restore = () => {
    body.classList.remove('no-assignments');
    window.removeEventListener('afterprint', restore);
    if (mql && 'removeListener' in mql) mql.removeListener(mqlListener);
    if (mql && 'removeEventListener' in mql) mql.removeEventListener('change', mqlListener);
  };
  window.addEventListener('afterprint', restore);
  const mql = window.matchMedia ? window.matchMedia('print') : null;
  const mqlListener = (e) => { if (!e.matches) restore(); };
  if (mql) {
    if ('addListener' in mql) mql.addListener(mqlListener);
    else if ('addEventListener' in mql) mql.addEventListener('change', mqlListener);
  }
  window.print();
}

/* ------------------- Commande blasons ------------------- */

/* Règles auto de coefficient:
   - libellé EXACT/contient '40cm TriSpot CO' → coeff = 5 (prioritaire)
   - libellé contient '40' → coeff = 2
   - sinon coeff = 1
*/
function getAutoCoeff(faceLabel) {
  const f = String(faceLabel || '').toLowerCase().trim();
  if (!f) return 1;
  if (f === '40cm trispot co' || f.includes('40cm trispot co')) return 5;
  if (f.includes('40')) return 2;
  return 1;
}

/* Règle “bundle x4”: pour 60cm Unique et 80cm Unique, commande = ceil(qty / 4) * coeff */
function isBundleBy4(faceLabel) {
  const f = String(faceLabel || '').toLowerCase().trim();
  return (f === '60cm unique' || f.includes('60cm unique') ||
          f === '80cm unique' || f.includes('80cm unique'));
}

function openOrder() {
  buildOrderTable(true);
  document.getElementById('orderModal').style.display = 'flex';
}
function closeOrder() {
  document.getElementById('orderModal').style.display = 'none';
}

function refreshFromRecap() {
  buildOrderTable(true);
}

function buildOrderTable(forceRefresh) {
  const tbody = document.querySelector('#orderTable tbody');
  if (!forceRefresh && tbody.children.length > 0) { updateGrandTotal(); return; }
  tbody.innerHTML = '';
  const items = parseRecapBlasons();
  if (!items.length) {
    addOrderRow('Blason', 0, 1);
  } else {
    items.forEach(it => addOrderRow(it.face, it.count, getAutoCoeff(it.face)));
  }
  updateGrandTotal();
}

function addOrderRow(face = '', count = 0, coeff = 1) {
  const tbody = document.querySelector('#orderTable tbody');
  const tr = document.createElement('tr');

  const tdFace = document.createElement('td');
  const tdQty = document.createElement('td');
  const tdCoeff = document.createElement('td');
  const tdTotal = document.createElement('td');

  const inpFace = document.createElement('input');
  inpFace.type = 'text';
  inpFace.className = 'form-control form-control-sm order-type';
  inpFace.value = face;
  inpFace.addEventListener('input', () => {
    const auto = getAutoCoeff(inpFace.value);
    tr.children[2].querySelector('input').value = auto;
    updateRowTotal(tr);
  });

  const inpQty = document.createElement('input');
  inpQty.type = 'number';
  inpQty.className = 'form-control form-control-sm order-input';
  inpQty.step = '1'; inpQty.min = '0';
  inpQty.value = count;
  inpQty.addEventListener('input', () => updateRowTotal(tr));

  const inpCoeff = document.createElement('input');
  inpCoeff.type = 'number';
  inpCoeff.className = 'form-control form-control-sm order-input';
  inpCoeff.step = '1'; inpCoeff.min = '0';
  inpCoeff.value = coeff;
  inpCoeff.addEventListener('input', () => updateRowTotal(tr));

  tdFace.appendChild(inpFace);
  tdQty.appendChild(inpQty);
  tdCoeff.appendChild(inpCoeff);
  tdTotal.textContent = '0';

  tr.appendChild(tdFace);
  tr.appendChild(tdQty);
  tr.appendChild(tdCoeff);
  tr.appendChild(tdTotal);

  tbody.appendChild(tr);
  updateRowTotal(tr);
}

function applyGlobalCoeff(val) {
  const v = Number(val);
  document.querySelectorAll('#orderTable tbody tr').forEach(tr => {
    const coeff = tr.children[2].querySelector('input');
    coeff.value = isFinite(v) && v >= 0 ? v : 1;
    updateRowTotal(tr, false);
  });
  updateGrandTotal();
}

function updateRowTotal(tr, updateGrand = true) {
  const face = tr.children[0].querySelector('input').value;
  const qtyInput = tr.children[1].querySelector('input');
  const coeffInput = tr.children[2].querySelector('input');

  const qty = Number(qtyInput.value) || 0;
  const coeff = Number(coeffInput.value) || 0;

  // Ajustement “bundle x4” pour 60cm Unique / 80cm Unique
  const qtyEffective = isBundleBy4(face) ? Math.ceil(qty / 4) : qty;

  const total = Math.round(qtyEffective * coeff);
  tr.children[3].textContent = total;

  if (updateGrand) updateGrandTotal();
}

function updateGrandTotal() {
  let sum = 0;
  document.querySelectorAll('#orderTable tbody tr').forEach(tr => {
    sum += Number(tr.children[3].textContent) || 0;
  });
  document.getElementById('orderGrandTotal').textContent = sum;
}

function parseRecapBlasons() {
  const root = document.getElementById('recapBlason');
  if (!root) return [];
  const items = [];

  // 1) Format recommandé: .recap-item data-face="40cm TriSpot CO" data-count="8"
  root.querySelectorAll('.recap-item').forEach(el => {
    const face = el.getAttribute('data-face');
    const countAttr = el.getAttribute('data-count');
    const count = Number(countAttr);
    if (face && isFinite(count)) items.push({ face, count });
  });
  if (items.length) return items;

  // 2) Parsing du texte brut (ex: "40cm TriSpot CO: 8", "60cm Unique (5)")
  const text = root.innerText || '';
  const tokens = text.split(/[\n,;]+/).map(t => t.trim()).filter(Boolean);
  tokens.forEach(tok => {
    const m = tok.match(/^(.+?)\s*(?:[:x\-–(])\s*(\d+)\)?$/i);
    if (m) {
      const face = m[1].trim();
      const count = Number(m[2]);
      if (face && isFinite(count)) items.push({ face, count });
    }
  });
  return items;
}

async function copyOrder() {
  const rows = Array.from(document.querySelectorAll('#orderTable tbody tr'))
    .map(tr => {
      const face = tr.children[0].querySelector('input').value;
      const qty = tr.children[1].querySelector('input').value;
      const coeff = tr.children[2].querySelector('input').value;
      const total = tr.children[3].textContent;
      return `${face}\t${qty}\t${coeff}\t${total}`;
    });
  const header = 'Type de blason\tQuantité\tCoefficient\tTotal à commander';
  const footer = `Total général\t\t\t${document.getElementById('orderGrandTotal').textContent}`;
  const text = [header].concat(rows).concat([footer]).join('\n');
  try {
    await navigator.clipboard.writeText(text);
    alert('Commande copiée dans le presse-papiers.');
  } catch (e) {
    // Fallback
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    alert('Commande copiée dans le presse-papiers.');
  }
}

function printOrder() {
  const card = document.getElementById('orderCard').cloneNode(true);
  // Nettoyage: enlever le bouton de fermeture de la copie
  const closeBtn = card.querySelector('.order-close');
  if (closeBtn) closeBtn.remove();

  const w = window.open('', '_blank', 'noopener,noreferrer');
  if (!w) return;
  const css = `
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Arial, sans-serif; margin: 1cm; }
    h3 { text-align: center; margin: 0 0 0.5cm 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
    tfoot td { font-weight: 600; }
    .order-actions, .order-close, #orderControls { display: none !important; }
  `;
  w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Commande blasons</title><style>'+css+'</style></head><body></body></html>');
  w.document.body.appendChild(card);
  w.document.close();
  w.focus();
  w.print();
  w.close();
}
/* Fermer la modale par clic sur fond */
document.getElementById('orderModal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) closeOrder();
});
</script>