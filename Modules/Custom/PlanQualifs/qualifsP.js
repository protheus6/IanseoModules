/* ============================================================
   QualifsP — Plan de cible
   Dépendances : jQuery (ianseo), Dragula (CDN)
   Aucune dépendance Bootstrap.
   QP_ROOT, QP_SESS_ID, QP_SORT : injectés par index.php
   ============================================================ */

/* document.addEventListener('DOMContentLoaded', function () {
    blasonRecap();
    loadPickingList($('#PickingList'));
    $('[id^=Cible-]').each(function () { getCible(this); });
    loadDragula();

}); */

$(function () {
    blasonRecap();
    loadPickingList($('#PickingList'));
    $('[id^=Cible-]').each(function () { getCible(this); });
    loadDragula();
    pqInitHoverStructure();
	pqInitHoverCategory();
	pqInitHoverBlason();
    $('#qpSearch').on('input', filterPickingList);
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
   Récap blasons (bandeau + page de garde impression)
---------------------------------------------------------- */
function blasonRecap() {
    var sessId = $('#departId').val();
    $.get(QP_ROOT + 'ajax.php', {
        action: 'blasonRecap',
        sessId: sessId
    }, function (data) {
        $('#recapBlason').html(data);
    });
    $.get(QP_ROOT + 'ajax.php', {
        action: 'blasonRecapPrint',
        sessId: sessId
    }, function (data) {
        $('#printBlasonBody').html(data);
    });
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
        action:       'pickingList',
        sessId:       $('#departId').val(),
        tfId:         $(elt).data('blason')       || '',
        blasonAlias:  $(elt).data('blasonAlias')  || '',
        cat:          $(elt).data('category')     || '',
        sort:         QP_SORT
    }, function (data) {
        $(elt).find('.blasonContent').html(data);
        var total    = $(elt).find('.blasonContent').children().length;
        var affected = $(elt).find('.blasonContent .affected').length;
        $(elt).closest('.qp-accordion-item').find('.memberCount').text(total);
        $(elt).closest('.qp-accordion-item').find('.memberAffectedCount').text(affected);
		if(affected != total) {
			$(elt).closest('.qp-accordion-item').find('.memberAffectedCount').addClass('memberAffectedCount-hl');
		}
		else {
			$(elt).closest('.qp-accordion-item').find('.memberAffectedCount').removeClass('memberAffectedCount-hl');
		}
        hideAffectedSwitch();
        filterPickingList();
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
    // Si une recherche est active, laisser filterPickingList gérer la visibilité
    if (($('#qpSearch').val() || '').trim()) {
        filterPickingList();
        return;
    }
    if ($('#toggleAffected').prop('checked')) {
        $('#PickingList .affected').show();
    } else {
        $('#PickingList .affected').hide();
    }
}

/* ----------------------------------------------------------
   Recherche dans la picking list (filtre live sur nom + structure)
---------------------------------------------------------- */
function filterPickingList() {
    var q            = ($('#qpSearch').val() || '').toLowerCase().trim();
    var showAffected = $('#toggleAffected').prop('checked');

    $('#qpSearchClear').toggle(q.length > 0);

    if (!q) {
        // Pas de recherche : tout afficher puis appliquer le filtre "affectés"
        $('#PickingList .qp-picker-item').show();
        $('#PickingList .qp-accordion-item').show();
        if (!showAffected) {
            $('#PickingList .affected').hide();
        }
        return;
    }

    // Filtrer chaque archer sur nom et structure
    $('#PickingList .qp-picker-item').each(function () {
        var name   = ($(this).data('pq-name')        || '').toLowerCase();
        var struct = ($(this).data('pq-struct-name') || '').toLowerCase();
        var match  = name.indexOf(q) !== -1 || struct.indexOf(q) !== -1;
        var isAffected = $(this).hasClass('affected');
        $(this).toggle(match && (showAffected || !isAffected));
    });

    // Afficher/masquer chaque groupe selon s'il contient des archers correspondants
    // NB : on contrôle le style inline de l'item (pas :visible qui dépend des ancêtres)
    $('#PickingList .qp-accordion-item').each(function () {
        var hasMatch = $(this).find('.qp-picker-item').filter(function () {
            return this.style.display !== 'none';
        }).length > 0;
        $(this).toggle(hasMatch);
        if (hasMatch) {
            $(this).find('.qp-accordion-body').removeClass('qp-hidden');
            $(this).addClass('qp-open');
        }
    });
}

function clearSearch() {
    $('#qpSearch').val('').trigger('input');
}

/* ----------------------------------------------------------
   Halo survol blason
---------------------------------------------------------- */

function pqInitHoverStructure() {
    $(document).on('mouseenter', '.pq-halo-archer', function () {
        var structData = $(this).data('pq-struct');
        $('.pq-halo-archer').each(function () {
            if ($(this).data('pq-struct') === structData) {
                $(this).addClass('pq-archer-hl').removeClass('pq-archer-dim');
            } else {
                $(this).addClass('pq-archer-dim').removeClass('pq-archer-hl');
            }
        });
    });
    $(document).on('mouseleave', '.pq-halo-archer', function () {
        $('.pq-halo-archer').removeClass('pq-archer-hl pq-archer-dim');
    });
}

function pqInitHoverCategory() {
    $(document).on('mouseenter', '.pq-halo-category', function () {
        var catData = $(this).data('pq-category');
        $('.pq-halo-archer').each(function () {
            if ($(this).data('pq-category') === catData) {
                $(this).addClass('pq-archer-hl').removeClass('pq-archer-dim');
            } else {
                $(this).addClass('pq-archer-dim').removeClass('pq-archer-hl');
            }
        });
    });
    $(document).on('mouseleave', '.pq-halo-category', function () {
        $('.pq-halo-archer').removeClass('pq-archer-hl pq-archer-dim');
    });
}

function pqInitHoverBlason() {
    $(document).on('mouseenter', '.pq-halo-blason', function () {
        var blasonAlias = $(this).data('pq-blason'); // contient l'alias (type physique)
        $('.pq-halo-archer').each(function () {
            if ($(this).data('pq-blason-alias') === blasonAlias) {
                $(this).addClass('pq-archer-hl').removeClass('pq-archer-dim');
            } else {
                $(this).addClass('pq-archer-dim').removeClass('pq-archer-hl');
            }
        });
        $('.pq-halo-blason').each(function () {
            if ($(this).data('pq-blason') === blasonAlias) {
                $(this).addClass('pq-archer-hl').removeClass('pq-archer-dim');
            } else {
                $(this).addClass('pq-archer-dim').removeClass('pq-archer-hl');
            }
        });
    });
    $(document).on('mouseleave', '.pq-halo-blason', function () {
        $('.pq-halo-archer').removeClass('pq-archer-hl pq-archer-dim');
        $('.pq-halo-blason').removeClass('pq-archer-hl pq-archer-dim');
    });
}




/* ----------------------------------------------------------
   Récap global (impression dans nouvelle fenêtre)
---------------------------------------------------------- */
function openGlobalRecap() {
    $.get(QP_ROOT + 'ajax.php', {
        action: 'blasonRecapGlobal',
        sessId: $('#departId').val()
    }, function (data) {
        var tourName = document.getElementById('tourNameOnly')
                     ? document.getElementById('tourNameOnly').textContent.trim()
                     : (document.getElementById('printHeader')
                        ? document.getElementById('printHeader').innerText.trim() : '');
        var win = window.open('', '_blank', 'width=900,height=700');
        win.document.write(
            '<!DOCTYPE html><html><head><meta charset="utf-8">'
          + '<title>Récap global blasons</title>'
          + '<style>'
          + 'body { font-family: Arial, sans-serif; font-size: 11pt; margin: 1cm; }'
          + 'h2 { font-size: 1.1em; margin-bottom: .4cm; border-bottom: 2px solid #000; padding-bottom: .2cm; text-align: left; }'
          + 'h2 .subtitle { display: block; text-align: center; font-size: .95em; font-weight: normal; margin-top: .1cm; }'
          + 'table { border-collapse: collapse; width: 100%; font-size: .95em; }'
          + 'th, td { border: 1px solid #999; padding: 4px 10px; text-align: left; vertical-align: middle; }'
          + 'th { background: #eee; font-weight: bold; text-align: center; }'
          + 'td.num { text-align: center; }'
          + 'tr:last-child td { font-weight: bold; background: #f0f0f0; }'
          + 'img { display: block; margin: auto; }'
          + '@media print { @page { margin: 1cm; } }'
          + '</style>'
          + '</head><body>'
          + '<h2>' + tourName + '<span class="subtitle">Récap global des blasons</span></h2>'
          + data
          + '</body></html>'
        );
        win.document.close();
        win.focus();
        win.onload = function () { win.print(); };
    });
}

/* ----------------------------------------------------------
   Impression
---------------------------------------------------------- */
var PRINT_PER_PAGE = 16; // cibles par page

function printTargets() {
    window.print();
}

(function () {
    function injectPrintHeaders() {
        $('.qp-print-page-header').remove();
        var headerHtml = document.getElementById('printHeader')
                       ? document.getElementById('printHeader').innerHTML : '';
        if (!headerHtml) return;

        // En-tête page de garde : injecté au début de #printBlasonRecap, sans saut avant
        var recap = document.getElementById('printBlasonRecap');
        if (recap) {
            $(recap).prepend(
                $('<div class="qp-print-page-header qp-print-page-header--first"></div>').html(headerHtml)
            );
        }

        // En-têtes pages cibles : une par groupe de PRINT_PER_PAGE cibles, avec saut de page
        var wraps = $('#targetsArea .qp-cible-wrap');
        for (var i = 0; i < wraps.length; i += PRINT_PER_PAGE) {
            $(wraps[i]).before(
                $('<div class="qp-print-page-header"></div>').html(headerHtml)
            );
        }
    }

    window.addEventListener('beforeprint', injectPrintHeaders);
    window.addEventListener('afterprint',  function () {
        $('.qp-print-page-header').remove();
    });
})();

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
