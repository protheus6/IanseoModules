/* ============================================================
   QualifsP — Target plan
   Dependencies: jQuery (ianseo), Dragula (CDN)
   QP_ROOT, QP_SESS_ID, QP_SORT: injected by index.php
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
    loadUnassignedSection();
    $('[id^=Cible-]').each(function () { getCible(this); });
    loadDragula();
    pqInitHoverStructure();
	pqInitHoverCategory();
	pqInitHoverBlason();
	pqInitHoverArcherOnCible();
    $('#qpSearch').on('input', filterPickingList);
});



/* ----------------------------------------------------------
   Native accordion (without Bootstrap)
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
        // Load content if not yet done (lazy loading)
        var container = body.find('[id^=blsItem-]');
        if (container.length && container.find('.blasonContent').children().length === 0) {
            if (container.attr('id') === 'blsItem-unassigned') {
                loadUnassignedSection();
            } else {
                loadOnePickingItem(container[0]);
            }
        }
    }
}

/* ----------------------------------------------------------
   Target face summary (banner + print cover page)
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
   Full picking list (session groups)
---------------------------------------------------------- */
function loadPickingList(container) {
    $(container).find('[id^=blsItem-]').not('#blsItem-unassigned').each(function () {
        loadOnePickingItem(this);
    });
}

function loadOnePickingItem(elt) {
    $.get(QP_ROOT + 'ajax.php', {
        action:          'pickingList',
        sessId:          $('#departId').val(),
        tfId:            $(elt).data('blason')          || '',
        blasonAlias:     $(elt).data('blasonAlias')     || '',
        blasonDistance:  $(elt).data('blasonDistance')  || 0,
        cat:             $(elt).data('category')        || '',
        sort:            QP_SORT
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
   "Without session" section
---------------------------------------------------------- */
function loadUnassignedSection() {
    var elt = document.getElementById('blsItem-unassigned');
    if (!elt) return;
    $.get(QP_ROOT + 'ajax.php', {
        action: 'unassignedList',
        sessId: $('#departId').val()
    }, function (data) {
        $(elt).find('.blasonContent').html(data);
        var total = $(elt).find('.blasonContent .qp-picker-item').length;
        var section = $('#tgl-unassigned');
        section.find('.memberCount').text(total);
        section.find('.memberAffectedCount').text(0);
        if (total > 0) {
            section.find('.memberAffectedCount').addClass('memberAffectedCount-hl');
            section.show();
        } else {
            section.find('.memberAffectedCount').removeClass('memberAffectedCount-hl');
            section.hide();
        }
        filterPickingList();
        loadDragula();
    });
}

/* ----------------------------------------------------------
   Target detail
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
   Move an archer
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
        loadUnassignedSection();
        blasonRecap();
    });
}

/* ----------------------------------------------------------
   Clear a target
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
        loadUnassignedSection();
        blasonRecap();
    });
}

/* ----------------------------------------------------------
   Target drag & drop (content swap)
---------------------------------------------------------- */
var _draggedCibleNum = null;
var _insertDst       = null;

$(document).on('dragstart', '.qp-cible-move-handle', function (e) {
    var wrap = $(this).closest('[id^=Cible-]');
    _draggedCibleNum = (wrap.attr('id') || '').replace('Cible-', '');
    _insertDst = null;
    e.originalEvent.dataTransfer.effectAllowed = 'move';
    e.originalEvent.dataTransfer.setData('text/plain', _draggedCibleNum);
    wrap.addClass('qp-cible-dragging');
});

$(document).on('dragend', '.qp-cible-move-handle', function () {
    $('.qp-cible-wrap').removeClass('qp-cible-dragging qp-cible-drop-after qp-cible-drop-before');
    _draggedCibleNum = null;
    _insertDst = null;
});

$(document).on('dragover', '.qp-cible-wrap', function (e) {
    if (!_draggedCibleNum) return;
    e.preventDefault();
    e.originalEvent.dataTransfer.dropEffect = 'move';
    var cNum    = parseInt(($(this).attr('id') || '').replace('Cible-', ''));
    var srcNum  = parseInt(_draggedCibleNum);
    $('.qp-cible-wrap').removeClass('qp-cible-drop-after qp-cible-drop-before');
    if (cNum !== srcNum) {
        // Line on the right when moving forward, on the left when moving back
        $(this).addClass(cNum > srcNum ? 'qp-cible-drop-after' : 'qp-cible-drop-before');
        _insertDst = String(cNum);
    } else {
        _insertDst = null;
    }
});

$(document).on('drop', '.qp-cible-wrap', function (e) {
    e.preventDefault();
    $('.qp-cible-wrap').removeClass('qp-cible-drop-after qp-cible-drop-before');
    var src = _draggedCibleNum;
    var dst = _insertDst;
    if (!src || !dst || src === dst) return;
    var minC = Math.min(parseInt(src), parseInt(dst));
    var maxC = Math.max(parseInt(src), parseInt(dst));
    $.get(QP_ROOT + 'ajax.php', {
        action: 'moveCible',
        sessId: $('#departId').val(),
        src:    src,
        dst:    dst
    }, function () {
        for (var c = minC; c <= maxC; c++) {
            getCible($('#Cible-' + c)[0]);
        }
        loadPickingList($('#PickingList'));
        blasonRecap();
    });
});

/* ----------------------------------------------------------
   Unassign all targets from the session
---------------------------------------------------------- */
function clearAllCibles() {
    if (!confirm(Lng_UnassignAll)) return;
    $.get(QP_ROOT + 'ajax.php', {
        action: 'clearSession',
        sessId: $('#departId').val()
    }, function () {
        $('[id^=Cible-]').each(function () { getCible(this); });
        loadPickingList($('#PickingList'));
        loadUnassignedSection();
        blasonRecap();
    });
}

/* ----------------------------------------------------------
   Clear a target with confirmation
---------------------------------------------------------- */
function removeCibleConfirm(btn) {
    var cNum = ($(btn).closest('[id^=Cible-]').attr('id') || '').replace('Cible-', '');
    if (!confirm(Lng_UnassignAllOfTarget.replace("__TARGET__", cNum))) return;
    removeCible(btn);
}

/* ----------------------------------------------------------
   Archer / assigned display toggles
---------------------------------------------------------- */
function hideSwitch() {
    if ($('#toggleArcher').prop('checked')) {
        $('.nameArcher').show();
    } else {
        $('.nameArcher').hide();
    }
}

function hideAffectedSwitch() {
    // If a search is active, let filterPickingList manage visibility
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
   Picking list search (live filter on name + structure)
---------------------------------------------------------- */
function filterPickingList() {
    var q            = ($('#qpSearch').val() || '').toLowerCase().trim();
    var showAffected = $('#toggleAffected').prop('checked');

    $('#qpSearchClear').toggle(q.length > 0);

    if (!q) {
        // No search: show all then apply the "assigned" filter
        $('#PickingList .qp-picker-item, #tgl-unassigned .qp-picker-item').show();
        $('#PickingList .qp-accordion-item').not('#tgl-unassigned').show();
        if (!showAffected) {
            $('#PickingList .affected').hide();
        }
        // Unassigned section: show again if not empty
        var unassignedCount = $('#tgl-unassigned .qp-picker-item').length;
        if (unassignedCount > 0) $('#tgl-unassigned').show();
        return;
    }

    // Filter each archer by name and structure (session + unassigned sections)
    $('#PickingList .qp-picker-item, #tgl-unassigned .qp-picker-item').each(function () {
		var license   = ($(this).data('pq-license')        || '').toLowerCase();
        var name   = ($(this).data('pq-name')        || '').toLowerCase();
        var struct = ($(this).data('pq-struct-name') || '').toLowerCase();
        var match  = name.indexOf(q) !== -1 || struct.indexOf(q) !== -1 || license.indexOf(q) !== -1;
        var isAffected = $(this).hasClass('affected');
        $(this).toggle(match && (showAffected || !isAffected));
    });

    // Show/hide each session group depending on whether it contains matching archers
    // NB: we check the item's inline style (not :visible which depends on ancestors)
    // #tgl-unassigned is excluded and handled separately below
    $('#PickingList .qp-accordion-item').not('#tgl-unassigned').each(function () {
        var hasMatch = $(this).find('.qp-picker-item').filter(function () {
            return this.style.display !== 'none';
        }).length > 0;
        $(this).toggle(hasMatch);
        if (hasMatch) {
            $(this).find('.qp-accordion-body').removeClass('qp-hidden');
            $(this).addClass('qp-open');
        }
    });

    // Section unassigned
    var hasUnassignedMatch = $('#tgl-unassigned .qp-picker-item').filter(function () {
        return this.style.display !== 'none';
    }).length > 0;
    $('#tgl-unassigned').toggle(hasUnassignedMatch);
    if (hasUnassignedMatch) {
        $('#tgl-unassigned .qp-accordion-body').removeClass('qp-hidden');
        $('#tgl-unassigned').addClass('qp-open');
    }
}

function clearSearch() {
    $('#qpSearch').val('').trigger('input');
}

/* ----------------------------------------------------------
   Target face hover halo
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
        var blasonAlias = $(this).data('pq-blason');
        var distance    = $(this).data('pq-distance') || 0; // 0 = no distance filter
        $('.pq-halo-archer').each(function () {
            var aliasMatch = $(this).data('pq-blason') === blasonAlias;
            var distMatch  = !distance || Number($(this).data('pq-distance')) === distance;
            if (aliasMatch && distMatch) {
                $(this).addClass('pq-archer-hl').removeClass('pq-archer-dim');
            } else {
                $(this).addClass('pq-archer-dim').removeClass('pq-archer-hl');
            }
        });
        $('.pq-halo-blason').each(function () {
            // data-pq-blason-alias present on target images (numeric ID in data-pq-blason)
            // data-pq-blason alone (alias string) on accordion items
            var aliasMatch = $(this).data('pq-blason') === blasonAlias;
            var distMatch  = !distance || Number($(this).data('pq-distance')) === distance;
            if (aliasMatch && distMatch) {
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
   Physical face highlight when hovering an archer on the target
---------------------------------------------------------- */
function pqInitHoverArcherOnCible() {
    $(document).on('mouseenter', '.qp-cible-names .pq-halo-archer', function () {
        var order = String($(this).closest('.qp-vague-slot').find('.cibleLetter').val());
        if (!order) return;
        var $card = $(this).closest('.qp-cible-names').siblings('.qp-cible-card');
        $card.find('.pq-halo-blason').each(function () {
            var orders = String($(this).data('vagueOrders') || '');
            var match  = orders.split(',').indexOf(order) !== -1;
            $(this).toggleClass('pq-blason-hl',  match);
            $(this).toggleClass('pq-blason-dim', !match && orders !== '');
        });
    });
    $(document).on('mouseleave', '.qp-cible-names .pq-halo-archer', function () {
        var $card = $(this).closest('.qp-cible-names').siblings('.qp-cible-card');
        $card.find('.pq-halo-blason').removeClass('pq-blason-hl pq-blason-dim');
    });
}




/* ----------------------------------------------------------
   Global summary (print in new window)
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
          + '<title>'+ Lng_GlobalRecap +'</title>'
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
          + '<h2>' + tourName + '<span class="subtitle">'+ Lng_GlobalRecap +'</span></h2>'
          + data
          + '</body></html>'
        );
        win.document.close();
        win.focus();
        win.onload = function () { win.print(); };
    });
}

/* ----------------------------------------------------------
   Printing
---------------------------------------------------------- */
var PRINT_PER_PAGE = 16; // targets per page

function printTargets() {
    window.print();
}

(function () {
    function injectPrintHeaders() {
        $('.qp-print-page-header').remove();
        var headerHtml = document.getElementById('printHeader')
                       ? document.getElementById('printHeader').innerHTML : '';
        if (!headerHtml) return;

        // Cover page header: injected at the start of #printBlasonRecap, no break before
        var recap = document.getElementById('printBlasonRecap');
        if (recap) {
            $(recap).prepend(
                $('<div class="qp-print-page-header qp-print-page-header--first"></div>').html(headerHtml)
            );
        }

        // Target page headers: one per group of PRINT_PER_PAGE targets, with page break
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
   Target face order (pure CSS modal)
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
    var text = [Lng_FaceType +'\t'+ Lng_Quantity +'\t'+ Lng_Coeff +'\t'+ Lng_Total]
               .concat(rows)
               .concat([GrandTotal +'\t\t\t' + document.getElementById('orderGrandTotal').textContent])
               .join('\n');
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
    }
    alert(Lng_CopyCart);
}

/* Close the modal by clicking on the backdrop */
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('orderModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeOrder();
        });
    }
});

/* ----------------------------------------------------------
   PopEdit — archer editing in a modal iframe
   opener = null in an iframe → PopEdit does not reload
   the main page. window.close() is intercepted.
---------------------------------------------------------- */
$(function () {
    var _peArcherId = 0;
    var _peCibleNum = 0;

    window.openPopEdit = function (archerId, cibleNum) {
        _peArcherId = +archerId;
        _peCibleNum = +cibleNum || 0;
        var url = QP_POPEDIT_URL
                + '?id=' + _peArcherId
                + '&ses=' + QP_SESS_ID
                + '&tar=';
        document.getElementById('qpPeIframe').src = url;
        document.getElementById('qpPeModal').style.display = 'flex';
    };

    window.closePopEditModal = function () {
        document.getElementById('qpPeModal').style.display = 'none';
        document.getElementById('qpPeIframe').src = 'about:blank';
        $('[id^=Cible-]').each(function () { getCible(this); });
        loadPickingList($('#PickingList'));
        loadUnassignedSection();
        blasonRecap();
    };

    /* Intercept window.close() in the iframe after each load */
    document.getElementById('qpPeIframe').addEventListener('load', function () {
        try {
            var iw = this.contentWindow;
            iw.opener = null;  // prevents PopEdit from reloading the parent page
            iw.close  = function () { window.closePopEditModal(); };
        } catch (e) {}
    });

    /* Close on ESC */
    $(document).on('keydown.qpPeModal', function (e) {
        if (e.key === 'Escape'
                && document.getElementById('qpPeModal').style.display !== 'none') {
            window.closePopEditModal();
        }
    });

    /* Close by clicking on the backdrop */
    document.getElementById('qpPeModal').addEventListener('click', function (e) {
        if (e.target === this) window.closePopEditModal();
    });

    /* Double-click on an archer in the picking list */
    $(document).on('dblclick', '.qp-src-card', function (e) {
        e.stopPropagation();
        var item     = $(this).closest('.qp-picker-item');
        var athId    = item.find('input.archerId').val();
        var cibleNum = parseInt(item.find('input.cibleNum').val()) || 0;
        if (athId) window.openPopEdit(athId, cibleNum);
    });

    /* Double-click on an archer in a target */
    $(document).on('dblclick', '#targetsArea .disptrg', function (e) {
        e.stopPropagation();
        var item     = $(this).closest('.qp-picker-item');
        var athId    = item.find('input.archerId').val();
        var cibleNum = parseInt(item.find('input.cibleNum').val()) || 0;
        if (athId) window.openPopEdit(athId, cibleNum);
    });

    /* Delete an archer — prevent drag on mousedown */
    $(document).on('mousedown', '.qp-del-archer', function (e) {
        e.stopPropagation();
    });

    $(document).on('click', '.qp-del-archer', function (e) {
        e.stopPropagation();
        var item  = $(this).closest('.qp-picker-item');
        var athId = item.find('input.archerId').val();
        var name  = item.data('pq-name') || ('archer #' + athId);
        if (!athId) return;
        if (!confirm(Lng_RemoveArcher.replace('__ARCHER__', name))) return;
        $.get(QP_ROOT + 'ajax.php', { action: 'deleteArcher', athId: athId, sessId: QP_SESS_ID })
            .always(function () {
                $('[id^=Cible-]').each(function () { getCible(this); });
                loadPickingList($('#PickingList'));
                loadUnassignedSection();
                blasonRecap();
            });
    });
});
