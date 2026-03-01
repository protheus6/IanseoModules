/* ============================================================
   QualifsP — Plan de cible
   Dépendances : jQuery (ianseo), Dragula (CDN)
   Aucune dépendance Bootstrap.
   QP_ROOT, QP_SESS_ID, QP_SORT : injectés par index.php
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
    blasonRecap();
    loadPickingList($('#PickingList'));
    $('[id^=Cible-]').each(function () { getCible(this); });
    loadDragula();
});

/* ----------------------------------------------------------
   Accordion natif (sans Bootstrap)
---------------------------------------------------------- */
function qpToggle(header) {
    var item = $(header).closest('.qp-accordion-item');
    var body = item.find('.qp-accordion-body').first();
    var isOpen = item.hasClass('qp-open');

    if (isOpen) {
        body.addClass('qp-hidden');
        item.removeClass('qp-open');
    } else {
        body.removeClass('qp-hidden');
        item.addClass('qp-open');
        // Charger le contenu si pas encore fait
        var container = body.find('[id^=blsItem-]');
        if (container.length && container.find('.blasonContent').children().length === 0) {
            loadOnePickingItem(container[0]);
        }
    }
}

/* ----------------------------------------------------------
   Récap blasons (bandeau + bilan impression)
---------------------------------------------------------- */
function blasonRecap() {
    $.get(QP_ROOT + 'ajax.php', {
        action: 'blasonRecap',
        sessId: $('#departId').val()
    }, function (data) {
        $('#recapBlason').html(data);
        updatePrintBlasonRecap();
    });
}

function updatePrintBlasonRecap() {
    var items = parseRecapBlasons();
    var tbody = document.getElementById('printBlasonBody');
    var totalEl = document.getElementById('printBlasonTotal');
    if (!tbody || !totalEl) return;
    tbody.innerHTML = '';
    var grand = 0;
    items.forEach(function (it) {
        var tr = document.createElement('tr');
        var tdFace = document.createElement('td');
        var tdQty  = document.createElement('td');
        tdFace.textContent = it.face;
        tdQty.textContent  = it.count;
        tr.appendChild(tdFace);
        tr.appendChild(tdQty);
        tbody.appendChild(tr);
        grand += it.count;
    });
    totalEl.textContent = grand;
}

/* ----------------------------------------------------------
   Picking list complète
---------------------------------------------------------- */
function loadPickingList(container) {
    $(container).find('[id^=blsItem-]').each(function () {
        loadOnePickingItem(this);
    });
}

function loadOnePickingItem(elt) {
    $.get(QP_ROOT + 'ajax.php', {
        action:  'pickingList',
        sessId:  $('#departId').val(),
        tfId:    $(elt).data('blason')   || '',
        cat:     $(elt).data('category') || '',
        sort:    QP_SORT
    }, function (data) {
        $(elt).find('.blasonContent').html(data);
        var total    = $(elt).find('.blasonContent').children().length;
        var affected = $(elt).find('.blasonContent .affected').length;
        $(elt).closest('.qp-accordion-item').find('.memberCount').text(total);
        $(elt).closest('.qp-accordion-item').find('.memberAffectedCount').text(affected);
        hideAffectedSwitch();
        loadDragula();
    });
}

/* ----------------------------------------------------------
   Détail d'une cible
---------------------------------------------------------- */
function getCible(item) {
    var id   = $(item).attr('id') || '';          // "Cible-2"
    var cNum = id.replace('Cible-', '');           // "2"
    if (!cNum || isNaN(parseInt(cNum))) return;
    $.get(QP_ROOT + 'ajax.php', {
        action:   'cible',
        sessId:   $('#departId').val(),
        cibleNum: cNum,
        _ts:      Date.now()
    }, function (data) {
        $(item).html(data);
        loadDragula();
    });
}

/* ----------------------------------------------------------
   Déplacer un archer
---------------------------------------------------------- */
function moveArcher(archer, source, target) {
    var cNum    = (target && ($(target).closest('[id^=Cible-]').attr('id') || '').replace('Cible-', '')) || '0';
    var cLetter = (target && $(target).parent().find('.cibleLetter').val()) || '0';
    $.get(QP_ROOT + 'ajax.php', {
        action:   'moveArcher',
        sessId:   $('#departId').val(),
        archerId: $(archer).find('.archerId').val(),
        cNum:     cNum,
        cLetter:  cLetter
    }, function () {
        if (target) getCible($(target).parent().closest('[id^=Cible-]'));
        if (source) getCible($(source).parent().closest('[id^=Cible-]'));
        var oldCible = $(archer).find('input.cibleNum').val();
        if (oldCible && oldCible !== '0') getCible($('#Cible-' + oldCible));
        loadPickingList($('#PickingList'));
        blasonRecap();
    });
}

/* ----------------------------------------------------------
   Vider une cible
---------------------------------------------------------- */
function removeCible(item) {
    var cible    = $(item).closest('[id^=Cible-]');
    var cibleNum = (cible.attr('id') || '').replace('Cible-', '');
    $.get(QP_ROOT + 'ajax.php', {
        action:   'clearCible',
        sessId:   $('#departId').val(),
        cibleNum: cibleNum,
        _ts:      Date.now()
    }, function () {
        getCible(cible);
        loadPickingList($('#PickingList'));
        blasonRecap();
    });
}

/* ----------------------------------------------------------
   Toggles affichage archers / affectés
---------------------------------------------------------- */
function hideSwitch() {
    if ($('#toggleArcher').prop('checked')) {
        $('.nameArcher').show();
    } else {
        $('.nameArcher').hide();
    }
}

function hideAffectedSwitch() {
    if ($('#toggleAffected').prop('checked')) {
        $('#PickingList .affected').show();
    } else {
        $('#PickingList .affected').hide();
    }
}

/* ----------------------------------------------------------
   Halo survol blason
---------------------------------------------------------- */
function haloBlason(elt) {
    var id = $(elt).closest('.qp-accordion-item').attr('id');
    if (id) $('.' + id).addClass('halo');
}
function haloBlasonOut(elt) {
    var id = $(elt).closest('.qp-accordion-item').attr('id');
    if (id) $('.' + id).removeClass('halo');
}
function haloCat(elt) {
    var id = $(elt).closest('.qp-accordion-item').attr('id');
    if (id) $('.' + id).addClass('halo');
}
function haloCatOut(elt) {
    var id = $(elt).closest('.qp-accordion-item').attr('id');
    if (id) $('.' + id).removeClass('halo');
}
function haloStruct(elt) {
    var sid = $(elt).data('struct');
    if (sid !== undefined) $('.bgstru' + sid).addClass('halobgstru');
}
function haloStructOut(elt) {
    var sid = $(elt).data('struct');
    if (sid !== undefined) $('.bgstru' + sid).removeClass('halobgstru');
}

/* ----------------------------------------------------------
   Impression
---------------------------------------------------------- */
function printTargets() {
    window.print();
}

/* ----------------------------------------------------------
   Dragula (drag & drop)
---------------------------------------------------------- */
var drakeInstance = null;

function loadDragula() {
    if (drakeInstance) { drakeInstance.destroy(); }

    drakeInstance = dragula({
        isContainer: function (el) {
            return el.classList.contains('dragula-container');
        },
        accepts: function (el, target) {
            var srcCls = $(el).find('.blasonType').val();
            if (!srcCls) return true;
            if (!$(target).attr('class') || $(target).attr('class').indexOf('acc-') === -1) return true;
            return target.classList.contains(srcCls);
        },
        removeOnSpill: true
    });

    drakeInstance.on('drop', function (el, target, source) {
        moveArcher(el, source, target);
    });
    drakeInstance.on('remove', function (el, container, source) {
        moveArcher(el, source, null);
    });
}

/* ============================================================
   Commande blasons (modale CSS pur)
   ============================================================ */
function getAutoCoeff(face) {
    var f = String(face || '').toLowerCase().trim();
    if (!f) return 1;
    if (f.indexOf('trispot co') !== -1) return 5;
    if (f.indexOf('40') !== -1) return 2;
    return 1;
}
function isBundleBy4(face) {
    var f = String(face || '').toLowerCase().trim();
    return f.indexOf('60cm unique') !== -1 || f.indexOf('80cm unique') !== -1;
}

function openOrder() {
    buildOrderTable(true);
    var modal = document.getElementById('orderModal');
    modal.style.display = 'flex';
}
function closeOrder() {
    document.getElementById('orderModal').style.display = 'none';
}
function refreshFromRecap() { buildOrderTable(true); }

function buildOrderTable(force) {
    var tbody = document.querySelector('#orderTable tbody');
    if (!force && tbody.children.length > 0) { updateGrandTotal(); return; }
    tbody.innerHTML = '';
    var items = parseRecapBlasons();
    if (!items.length) {
        addOrderRow('Blason', 0, 1);
    } else {
        items.forEach(function (it) { addOrderRow(it.face, it.count, getAutoCoeff(it.face)); });
    }
    updateGrandTotal();
}

function addOrderRow(face, count, coeff) {
    face  = face  !== undefined ? face  : '';
    count = count !== undefined ? count : 0;
    coeff = coeff !== undefined ? coeff : 1;

    var tbody = document.querySelector('#orderTable tbody');
    var tr = document.createElement('tr');

    function makeInput(type, val, w) {
        var i = document.createElement('input');
        i.type = type; i.value = val;
        if (w) i.style.width = w;
        if (type === 'number') { i.step = '1'; i.min = '0'; }
        return i;
    }

    var inpFace  = makeInput('text',   face,  '100%');
    var inpQty   = makeInput('number', count, '5em');
    var inpCoeff = makeInput('number', coeff, '4em');

    inpFace.addEventListener('input',  function () { inpCoeff.value = getAutoCoeff(inpFace.value); updateRowTotal(tr); });
    inpQty.addEventListener('input',   function () { updateRowTotal(tr); });
    inpCoeff.addEventListener('input', function () { updateRowTotal(tr); });

    function td(child) { var c = document.createElement('td'); c.appendChild(child); return c; }
    var tdT = document.createElement('td'); tdT.textContent = '0';

    tr.appendChild(td(inpFace)); tr.appendChild(td(inpQty));
    tr.appendChild(td(inpCoeff)); tr.appendChild(tdT);
    tbody.appendChild(tr);
    updateRowTotal(tr);
}

function updateRowTotal(tr, doGrand) {
    doGrand = doGrand !== false;
    var face  = tr.children[0].querySelector('input').value;
    var qty   = Number(tr.children[1].querySelector('input').value) || 0;
    var coeff = Number(tr.children[2].querySelector('input').value) || 0;
    var qtyEff = isBundleBy4(face) ? Math.ceil(qty / 4) : qty;
    tr.children[3].textContent = Math.round(qtyEff * coeff);
    if (doGrand) updateGrandTotal();
}

function updateGrandTotal() {
    var sum = 0;
    document.querySelectorAll('#orderTable tbody tr').forEach(function (tr) {
        sum += Number(tr.children[3].textContent) || 0;
    });
    document.getElementById('orderGrandTotal').textContent = sum;
}

function applyGlobalCoeff(val) {
    var v = Number(val);
    document.querySelectorAll('#orderTable tbody tr').forEach(function (tr) {
        tr.children[2].querySelector('input').value = (isFinite(v) && v >= 0) ? v : 1;
        updateRowTotal(tr, false);
    });
    updateGrandTotal();
}

function parseRecapBlasons() {
    var root = document.getElementById('recapBlason');
    if (!root) return [];
    var items = [];
    root.querySelectorAll('.qp-recap-item').forEach(function (el) {
        var face  = el.getAttribute('data-face');
        var count = Number(el.getAttribute('data-count'));
        if (face && isFinite(count)) items.push({ face: face, count: count });
    });
    return items;
}

async function copyOrder() {
    var rows = Array.from(document.querySelectorAll('#orderTable tbody tr')).map(function (tr) {
        return [
            tr.children[0].querySelector('input').value,
            tr.children[1].querySelector('input').value,
            tr.children[2].querySelector('input').value,
            tr.children[3].textContent
        ].join('\t');
    });
    var text = ['Type de blason\tQuantité\tCoeff\tTotal']
               .concat(rows)
               .concat(['Total général\t\t\t' + document.getElementById('orderGrandTotal').textContent])
               .join('\n');
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
    }
    alert('Commande copiée dans le presse-papiers.');
}

/* Fermer la modale en cliquant sur le fond */
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('orderModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeOrder();
        });
    }
});
