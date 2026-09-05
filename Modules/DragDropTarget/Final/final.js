/**
 * PlanFinales — Finals target plan
 * Dependencies: jQuery, Dragula
 */

/* ============================================================
   Global state
   ============================================================ */
var pfData              = null;   // {targets, slots, unscheduled, events}
var pfDeletedTrainings  = [];     // fwKeys of deleted trainings (sent on save)
var pfDrake             = null;   // Dragula instance
var pfShowBlason        = false;  // display SVG target faces
var pfDirty             = false;  // unsaved changes
var pfUnschedCollapsed  = {};     // {evCode: bool} — collapsed groups in the unscheduled panel
var pfZoom              = 100;    // grid zoom level in % (50–150)
var pfConflictIds       = {};     // {event|phase|teamEvent: message} — detected order conflicts

/* ============================================================
   Layout adjustment (CSS flex handles height automatically)
   Only clears inline styles that the template might impose.
   ============================================================ */
function pfAdjustLayout() {
    // Clear any residual inline height on the layout
    var layout = document.querySelector('.pf-layout');
    if (layout) layout.style.height = '';
}

/* ============================================================
   Grid zoom (slider)
   Scales the CSS dimension variables + font.
   ============================================================ */
function pfSetZoom(val) {
    pfZoom = parseInt(val, 10);
    var f  = pfZoom / 100;

    var wSlot   = Math.round(140 * f) + 'px';
    var wTarget = Math.round(75  * f) + 'px';
    var wAdd    = Math.round(28  * f) + 'px';
    var hRow    = Math.round(90  * f) + 'px';
    var hWave   = Math.round(68  * f) + 'px';
    var font    = (0.88 * f).toFixed(3) + 'em';

    // 1. Update CSS variables (used by all non-table elements)
    var r = document.documentElement;
    r.style.setProperty('--pf-col-slot',    wSlot);
    r.style.setProperty('--pf-col-target',  wTarget);
    r.style.setProperty('--pf-col-add',     wAdd);
    r.style.setProperty('--pf-row-h',       hRow);
    r.style.setProperty('--pf-wave-row-h',  hWave);
    r.style.setProperty('--pf-font-scale',  font);

    // 2. Force width directly on <col> elements (table-layout:fixed
    //    does not always react to CSS variable changes)
    document.querySelectorAll('col.pf-col-slot').forEach(function (c) {
        c.style.width = wSlot;
    });
    document.querySelectorAll('col.pf-col-target').forEach(function (c) {
        c.style.width = wTarget;
    });
    document.querySelectorAll('col.pf-col-add').forEach(function (c) {
        c.style.width = wAdd;
    });

    // 3. Update the label and slider
    var lbl    = document.getElementById('pfZoomLbl');
    var slider = document.getElementById('pfZoomSlider');
    if (lbl)    lbl.textContent = pfZoom + '%';
    if (slider) slider.value   = pfZoom;

    // 4. Persist in localStorage
    try { localStorage.setItem('pfZoom', pfZoom); } catch (e) {}
	pfUniCellWidth();
}

/* ============================================================
   Initialisation
   ============================================================ */
$(function () {
    pfAdjustLayout();
    $(window).on('resize', pfAdjustLayout);
    // Restore zoom from localStorage
    try {
        var savedZoom = parseInt(localStorage.getItem('pfZoom'), 10);
        if (savedZoom >= 50 && savedZoom <= 150) pfZoom = savedZoom;
    } catch (e) {}
    pfSetZoom(pfZoom);
    pfInitHover();
    pfLoad();
});

/* ============================================================
   Add / delete trainings
   ============================================================ */

/** Computes the rgba color of a training block for a given evCode.
 *  First looks for an existing tile of the same event, otherwise generates via hash. */
function pfGetTrainingColor(evCode) {
    var allBlocks = [];
    (pfData.slots || []).forEach(function (s) {
        (s.blocks || []).forEach(function (b) { allBlocks.push(b); });
    });
    (pfData.unscheduled || []).forEach(function (b) { allBlocks.push(b); });

    var existing = null;
    for (var i = 0; i < allBlocks.length; i++) {
        if (allBlocks[i].event === evCode) { existing = allBlocks[i]; break; }
    }
    if (existing) {
        // Training block (rgba) → reuse directly
        if (existing.type === 'training') return existing.color;
        // Phase block (hex) → derive the rgba version
        var hex = (existing.color + '').replace('#', '');
        if (hex.length === 6) {
            var r = parseInt(hex.slice(0,2), 16);
            var g = parseInt(hex.slice(2,4), 16);
            var b = parseInt(hex.slice(4,6), 16);
            return 'rgba(' + r + ',' + g + ',' + b + ',0.45)';
        }
    }
    // Fallback: deterministic hash on evCode (mirrors PHP generatePastelColor)
    var h = 0;
    for (var j = 0; j < evCode.length; j++) {
        h = (((h << 5) - h) + evCode.charCodeAt(j)) | 0;
    }
    h = Math.abs(h);
    return 'rgba(' + (127 + (h % 128)) + ',' + (127 + ((h >>> 8) % 128)) + ',' + (127 + ((h >>> 16) % 128)) + ',0.45)';
}

/** Opens the event selection modal to add a training. */
function pfOpenTrainModal() {
    var sel = document.getElementById('pfTrainEvSelect');
    sel.innerHTML = '';
    (pfData.events || []).forEach(function (ev) {
        var opt = document.createElement('option');
        opt.value       = ev.code;
        opt.dataset.te  = ev.teamEvent;
        opt.textContent = ev.code + (ev.label ? '  — ' + ev.label : '');
        sel.appendChild(opt);
    });
    document.getElementById('pfTrainModal').style.display = '';
}

function pfCloseTrainModal() {
    document.getElementById('pfTrainModal').style.display = 'none';
}

/** Creates the training block and adds it to the unscheduled list. */
function pfAddTrainingConfirm() {
    var sel       = document.getElementById('pfTrainEvSelect');
    var evCode    = sel.value;
    var teamEvent = parseInt(sel.options[sel.selectedIndex].dataset.te) || 0;
    if (!evCode) return;

    // Unique client-side ID (empty fwKey = new → INSERT on save)
    var uid = 'trn_new_' + evCode.replace(/\W/g,'_') + '_' + Date.now();
    var block = {
        id:         uid,
        type:       'training',
        teamEvent:  teamEvent,
        event:      evCode,
        eventLabel: evCode,
        color:      pfGetTrainingColor(evCode),
        targetList: [],
        waveRow:    0,
        fwKey:      ''   // empty → server-side INSERT on next save
    };
    pfData.unscheduled.push(block);
    pfDirty = true;
    pfCloseTrainModal();
    pfLoadUnscheduled();
    pfRefreshDragula();
}

/** Permanently deletes an unscheduled training. */
function pfDeleteTraining(blockId, ev) {
    if (ev && ev.stopPropagation) ev.stopPropagation();
    var idx = pfData.unscheduled.findIndex(function (b) { return b.id === blockId; });
    if (idx === -1) return;
    var block = pfData.unscheduled[idx];
    // If fwKey is non-empty: mark for deletion in the database on next save
    if (block.fwKey) {
        pfDeletedTrainings.push(block.fwKey);
    }
    pfData.unscheduled.splice(idx, 1);
    pfDirty = true;
    pfLoadUnscheduled();
    pfRefreshDragula();
}

/* ============================================================
   Highlight tiles of the same category on hover
   ============================================================ */
function pfInitHover() {
    // Delegated on document: survives pfRender() calls that recreate tiles
    $(document).on('mouseenter', '.pf-tile', function () {
        var evCode = $(this).data('pfEvent');
        $('.pf-tile').each(function () {
            if ($(this).data('pfEvent') === evCode) {
                $(this).addClass('pf-tile-hl').removeClass('pf-tile-dim');
            } else {
                $(this).addClass('pf-tile-dim').removeClass('pf-tile-hl');
            }
        });
    });
    $(document).on('mouseleave', '.pf-tile', function () {
        $('.pf-tile').removeClass('pf-tile-hl pf-tile-dim');
    });
}

function pfLoad() {
    $.getJSON(PF_AJAX + '?action=getData', function (data) {
        pfData = data.data;
        pfDeletedTrainings = [];   // reset the deletion list after loading
        pfNormalizeWaveBlocks();   // auto-split multi-wave blocks from the database
        $('#pfLoading').hide();
        pfRender();
        pfInitDragula();
        pfLoadUnscheduled();
    }).fail(function () {
        $('#pfLoading').text(Lng_ErrorLoadingData);
    });
}

/* ============================================================
   Multi-wave block normalisation (loading from DB)
   If a block has more matches than unique targets → split into wave sub-blocks
   ============================================================ */
function pfNormalizeWaveBlocks() {
    pfData.slots.forEach(function (slot) {
        var newBlocks = [];
        slot.blocks.forEach(function (blk) {
            if (blk.type !== 'phase' || !blk.matches || blk.matches.length === 0) {
                newBlocks.push(blk);
                return;
            }
            // Count actually assigned unique targets
            var targetSet = {};
            blk.matches.forEach(function (m) { if (m.target > 0) targetSet[m.target] = true; });
            var uniqueCount = Object.keys(targetSet).length;

            if (uniqueCount <= 1 || blk.matches.length <= uniqueCount) {
                // No wave mode (requires at least 2 distinct targets to create waves)
                newBlocks.push(blk);
                return;
            }

            // Wave mode detected.
            // Group matches BY TARGET (regardless of their order in the DB),
            // then interleave: wave 0 = 1st match of each target,
            //                  wave 1 = 2nd match of each target, etc.
            // This guarantees 1 match per target per wave, with no assumption about DB order.
            var sortedTargets = Object.keys(targetSet).map(Number).sort(function (a, b) { return a - b; });
            var targetGroups  = {};
            sortedTargets.forEach(function (t) { targetGroups[t] = []; });
            blk.matches.forEach(function (m) {
                if (m.target > 0 && targetGroups[m.target] !== undefined) {
                    targetGroups[m.target].push(m);
                }
            });

            var waveCount = Math.max.apply(null, sortedTargets.map(function (t) { return targetGroups[t].length; }));
            var baseId    = blk.id;

            for (var wv = 0; wv < waveCount; wv++) {
                var waveMatches = [];
                sortedTargets.forEach(function (t) {
                    if (targetGroups[t][wv]) waveMatches.push(targetGroups[t][wv]);
                });
                if (!waveMatches.length) continue;
                var waveBlk          = JSON.parse(JSON.stringify(blk));
                waveBlk.id           = wv === 0 ? baseId : (baseId + '_w' + wv);
                waveBlk.baseBlockId  = baseId;
                waveBlk.waveRow      = wv;
                waveBlk.matches      = waveMatches;
                waveBlk.targetList   = sortedTargets.slice();
                newBlocks.push(waveBlk);
            }
        });
        slot.blocks = newBlocks;
    });
}

/* ============================================================
   Grid rendering
   ============================================================ */
function pfRender() {
    // Save scroll position before replacing the DOM
    var container  = document.querySelector('.pf-grid-wrap');
    var scrollLeft = container ? container.scrollLeft : 0;
    var scrollTop  = container ? container.scrollTop  : 0;

    pfComputeConflicts();
    var html = pfBuildTable();
    $('#pfGrid').html(html);
    pfInitSlotEditors();
    // Re-apply zoom on the <col> elements recreated by the render
    pfSetZoom(pfZoom);

    // Restore scroll position
    if (container) {
        container.scrollLeft = scrollLeft;
        container.scrollTop  = scrollTop;
    }
}

/* ============================================================
   Order conflict detection (child phase before parent phase)
   ============================================================ */

/** Returns the prerequisite phase number for a given phase.
 *  Hierarchy: 1/16(16) → 1/8(8) → 1/4(4) → 1/2(2) → Gold(0) + Bronze(1) */
function pfGetPrereqPhase(phase) {
    if (phase === 0 || phase === 1) return 2;   // Or / Bronze ← demi-finale
    if (phase >= 2) return phase * 2;            // 1/2 ← 1/4, 1/4 ← 1/8, etc.
    return null;
}

/** Start timestamp (ms) of a time slot. */
function pfSlotStartMs(slot) {
    return new Date(slot.date + 'T' + slot.time + ':00').getTime();
}

/** End timestamp (ms) of a time slot (all waves included). */
function pfSlotEndMs(slot) {
    var waves = parseInt(slot.waves, 10) || 1;
    var dur   = parseInt(slot.duration, 10) || 0;
    return pfSlotStartMs(slot) + waves * dur * 60000;
}

/** Rebuilds pfConflictIds from pfData.
 *  Key: "event|phase|teamEvent" → conflict message.
 *  A block is in conflict if its slot starts before the end of its parent-phase slot
 *  for the same event. */
function pfComputeConflicts() {
    pfConflictIds = {};
    if (!pfData || !pfData.slots) return;

    // Collect all placed blocks (phase type only)
    var placed = [];
    pfData.slots.forEach(function (slot) {
        (slot.blocks || []).forEach(function (blk) {
            if (blk.type === 'phase') {
                placed.push({ block: blk, slot: slot });
            }
        });
    });

    placed.forEach(function (item) {
        var blk  = item.block;
        var slot = item.slot;
        var phase = parseInt(blk.phase, 10);
        var prereqPhase = pfGetPrereqPhase(phase);
        if (prereqPhase === null || isNaN(prereqPhase)) return;

        var childStart = pfSlotStartMs(slot);

        placed.forEach(function (prereqItem) {
            var pb = prereqItem.block;
            var ps = prereqItem.slot;
            if (pb === blk) return;
            if (pb.event !== blk.event) return;
            if (String(pb.teamEvent) !== String(blk.teamEvent)) return;
            if (parseInt(pb.phase, 10) !== prereqPhase) return;

            var prereqEnd = pfSlotEndMs(ps);
            if (prereqEnd > childStart) {
                var key = blk.event + '|' + blk.phase + '|' + blk.teamEvent;
                pfConflictIds[key] = (pb.phaseName || ('Phase ' + prereqPhase))
                    + ' doit se terminer avant '
                    + (blk.phaseName || ('Phase ' + phase));
            }
        });
    });
}

function pfUniCellWidth() {
	var $table = $('#pfGrid table').first();
	if(!$table.length) return;
	
	var $cells =  $table.find('.pf-th-target');
	$cells.css("width","auto");
	$cells.css("min-width","auto");
	
	let max = 0;
	$cells.each(function () {
		var w = $(this).outerWidth();
		if(w > max) max = w;
	});
	
	$cells.css("min-width", max + "px");
	$cells.css("width", max + "px");
}

function pfBuildTable() {
    var targets = pfData.targets;
    var slots   = pfData.slots;

    var h = '<table class="pf-table">';

    // Colgroup
    h += '<colgroup>';
    h += '<col class="pf-col-slot">';
    for (var cg = 0; cg < targets.length; cg++) {
        h += '<col class="pf-col-target">';
    }
    h += '<col class="pf-col-add">';
    h += '</colgroup>';

    h += '<thead><tr>';
    h += '<th class="pf-th-slot">&nbsp;</th>';
    for (var ci = 0; ci < targets.length; ci++) {
        h += '<th class="pf-th-target" data-col="' + ci + '">'
           + Lng_Target +' ' + targets[ci]
           + '<span class="pf-rm-col" onclick="pfRemoveTarget(' + ci + ')" title="'+ Lng_RemoveTarget +'">✕</span>'
           + '</th>';
    }
    h += '<th class="pf-th-add"><input type="button" class="Button" value="+" onclick="pfAddTarget()" title="'+ Lng_AddTarget +'" style="padding:1px 4px;font-size:.8em;"></th>';
    h += '</tr></thead><tbody>';

    for (var si = 0; si < slots.length; si++) {
        h += pfBuildSlotRows(slots[si], si);
    }

    h += '</tbody></table>';
    return h;
}

/* Emits one or more <tr> elements for a time slot (one per wave) */
function pfBuildSlotRows(slot, slotIdx) {
    // Group blocks by waveRow
    var waveGroups = {};
    slot.blocks.forEach(function (blk) {
        var wr = blk.waveRow || 0;
        if (!waveGroups[wr]) waveGroups[wr] = [];
        waveGroups[wr].push(blk);
    });

    var waveRowNums = Object.keys(waveGroups).map(Number).sort(function (a, b) { return a - b; });
    if (!waveRowNums.length) waveRowNums = [0];  // empty slot: still render 1 row

    // Honour the manually forced wave count (slot.waves)
    var forcedWaves = slot.waves || 1;
    // Fill in missing indices 0..forcedWaves-1.
    // NOTE: we cannot push(waveRowNums.length) because if waveRowNums=[1]
    // (only wave CD exists), this would produce [1,1] instead of [0,1].
    for (var wri = 0; wri < forcedWaves; wri++) {
        if (waveRowNums.indexOf(wri) < 0) waveRowNums.push(wri);
    }
    waveRowNums.sort(function (a, b) { return a - b; });
    var totalWaves = waveRowNums.length;

    var html = '';
    for (var wi = 0; wi < waveRowNums.length; wi++) {
        var wr = waveRowNums[wi];
        html += pfBuildWaveRow(slot, slotIdx, wr, wi, totalWaves, waveGroups[wr] || []);
    }
    return html;
}

/* Emits ONE <tr> for a wave (waveIdx = visual position, 0-based) */
function pfBuildWaveRow(slot, slotIdx, waveRow, waveIdx, totalWaves, blocks) {
    var targets = pfData.targets;
    var n       = targets.length;
    var isMultiWave = totalWaves > 1;
    var waveLabels  = ['AB', 'CD', 'EF', 'GH'];

    // Compute which targets are occupied by which blocks (in this wave)
    var colMap  = new Array(n).fill(null);
    var skipCol = new Array(n).fill(false);

    for (var bi = 0; bi < blocks.length; bi++) {
        var blk = blocks[bi];
        var tl  = blk.targetList || [];
        if (!tl.length) {
            // Training with no assigned targets → do not display in the grid
            continue;
        }

        var segs = pfGetContiguousSegments(tl, targets);
        blk._segments = segs;

        for (var sg = 0; sg < segs.length; sg++) {
            var seg    = segs[sg];
            var startC = seg.startCol;
            var span   = seg.span;
            colMap[startC] = { block: blk, segIdx: sg, segStart: seg.startTarget, segEnd: seg.endTarget, span: span };
            for (var k = startC + 1; k < startC + span; k++) {
                skipCol[k] = true;
            }
        }
    }

    var endTime = pfAddMinutes(slot.time, slot.duration * totalWaves);
    var trClass = isMultiWave ? ' class="pf-wave-row"' : '';
    var h = '<tr' + trClass + ' data-slot-idx="' + slotIdx + '" data-wave-row="' + waveRow + '">';

    // ---- Time column (first wave row only, with rowspan) ----
    if (waveIdx === 0) {
        var rowspanAttr = totalWaves > 1 ? ' rowspan="' + totalWaves + '"' : '';
        h += '<td class="pf-slot-header" data-slot-idx="' + slotIdx + '"' + rowspanAttr + '>'
           + '<span class="pf-slot-rm-btn" onclick="pfRemoveSlot(' + slotIdx + ')" title="'+ Lng_RemoveSlot +'">✕</span>'
           + '<span class="pf-slot-clear-btn" onclick="pfClearSlotBlocks(' + slotIdx + ')" title="'+ Lng_ClearSlot +'">⬇</span>'
		   + '<span class="pf-slot-insert-btn" onclick="pfInsertSlot(' + slotIdx + ')" title="'+ Lng_AddSlot +'">✚</span>'
           + '<div class="pf-slot-content">'
           +   '<div class="pf-slot-display">'
           +     '<span class="pf-slot-date">' + slot.date + '</span>'
           +     '<span class="pf-slot-timerange">'
           +       '<span>' + slot.time + '</span>'
           +       '<span>–</span>'
           +       '<span>' + endTime + '</span>'
           +     '</span>'
           +     '<span class="pf-slot-edit-btn" onclick="pfToggleSlotEdit(this, ' + slotIdx + ')">'+ Lng_CmdModify +'</span>'
           +   '</div>'
           +   '<div class="pf-slot-inputs">'
           +     '<div><label style="font-size:.8em;">'+ Lng_Date +' :</label>'
           +     '<input type="date" class="pf-in-date" value="' + slot.date + '"></div>'
           +     '<div><label style="font-size:.8em;">'+ Lng_Hour +' :</label>'
           +     '<input type="time" class="pf-in-time" value="' + slot.time + '"></div>'
           +     '<div><label style="font-size:.8em;">'+ Lng_Length +' :</label>'
           +     '<input type="number" class="pf-in-dur" value="' + slot.duration + '" min="1" max="300" style="width:40px;"> min</div>'
           +     '<div><button onclick="pfApplySlotEdit(this, ' + slotIdx + ')">'+ Lng_CmdOk +'</button>'
           +     ' <button onclick="pfCancelSlotEdit(this)">✕</button></div>'
           +   '</div>'
           + '</div>';
        // Vertical wave strip (positioned absolutely on the right edge of the cell)
        var stripTitle = isMultiWave ? Lng_Switch1Wave : Lng_Switch2Waves;
        h += '<div class="pf-slot-wave-strip" onclick="pfToggleSlotWaves(' + slotIdx + ')" title="' + stripTitle + '">';
        if (isMultiWave) {
            for (var wl = 0; wl < totalWaves; wl++) {
                h += '<div class="pf-wave-strip-lbl">' + (waveLabels[wl] || ('V' + (wl + 1))) + '</div>';
            }
        } else {
            h += '<div class="pf-wave-strip-lbl">AB</div>';
        }
        h += '</div>';
        h += '</td>';
    }

    // ---- Target columns ----
    for (var ci = 0; ci < n; ci++) {
        if (skipCol[ci]) continue;

        var cm = colMap[ci];
        if (cm) {
            var blkRef  = cm.block;
            var spanVal = cm.span;
            var tileId  = pfTileId(slotIdx, blkRef.id, cm.segIdx);
            h += '<td class="pf-cell" colspan="' + spanVal + '"'
               + ' data-slot-idx="' + slotIdx + '"'
               + ' data-col-from="' + ci + '"'
               + ' data-col-to="' + (ci + spanVal - 1) + '">'
               + '<div class="pf-drop-zone pf-dz"'
               + ' data-slot-idx="' + slotIdx + '"'
               + ' data-col="' + ci + '"'
               + ' data-wave-row="' + waveRow + '">'
               + pfBuildTile(blkRef, tileId, slotIdx, cm.segIdx, { startCol: ci, span: spanVal })
               + '</div>'
               + '</td>';
        } else {
            h += '<td class="pf-cell" data-slot-idx="' + slotIdx + '" data-col="' + ci + '">'
               + '<div class="pf-drop-zone pf-dz"'
               + ' data-slot-idx="' + slotIdx + '"'
               + ' data-col="' + ci + '"'
               + ' data-wave-row="' + waveRow + '"></div>'
               + '</td>';
        }
    }

    // + button column (empty, to match the "Add target" th)
    h += '<td class="pf-cell pf-cell-add"></td>';

    h += '</tr>';
    return h;
}

function pfBuildTile(block, tileId, slotIdx, segIdx, segInfo) {
    var color   = block.color;
    var textClr = pfContrastColor(color);
    var waveLabels = ['AB', 'CD', 'EF', 'GH'];
    var isWaveBlk  = block.baseBlockId !== undefined && block.baseBlockId !== '';

    // Pre-compute order conflict to inject class on the root div
    var conflictMsg = '';
    if (block.type === 'phase') {
        var ck = block.event + '|' + block.phase + '|' + block.teamEvent;
        conflictMsg = pfConflictIds[ck] || '';
    }
    var conflictClass = conflictMsg ? ' pf-tile--conflict' : '';

    var h = '<div class="pf-tile' + conflictClass + '" id="' + tileId + '"'
          + ' data-block-id="'  + pfEsc(block.id) + '"'
          + ' data-slot-idx="'  + slotIdx + '"'
          + ' data-seg-idx="'   + (segIdx || 0) + '"'
          + ' data-team-event="' + block.teamEvent + '"'
          + ' data-pf-event="'  + pfEsc(block.event) + '"'
          + ' style="background:' + color + '; color:' + textClr + ';">';

    // Conflict banner at the top of the tile (before the header)
    if (conflictMsg) {
        h += '<div class="pf-conflict-badge" title="' + pfEscHtml(conflictMsg) + '">⚠ '+ Lng_Conflict +'</div>';
    }

    h += '<div class="pf-tile-hdr">';

    // Buttons in the header
    h += '<span class="pf-tile-rm" onclick="pfRemoveTile(\'' + pfEsc(tileId) + '\',event)" title="'+ Lng_RemovePhase +'">✕</span>';
    // Segmentation button (non-wave phase blocks only, if the tile is segmentable)
    if (block.type === 'phase' && !isWaveBlk) {
        var nM = (block.matches || []).length;
        // No segmentation on _s0/_s1 half-tiles (already segmented).
        // No segmentation on ×1 blocks with no real mirror (noMirrorMatchNo=true):
        // for these blocks, matchNo+1 belongs to another phase — creating a _s1 would corrupt
        // that other phase's data on save.
        var canSeg = !block._canonOnly && !block._mirrorOnly &&
                     ((nM > 1) || (nM === 1 && block.twoPerTarget === false && !block.noMirrorMatchNo && (block.targetList || []).length >= 2));
        if (canSeg) {
            h += '<span class="pf-tile-seg" onclick="pfSegmentTile(\'' + pfEsc(block.id) + '\',' + slotIdx + ',event)" title="'+ Lng_Split +'">⊕</span>';
        }
    }
    // Toggle 1/2-archer-per-target button (all individual phase blocks, wave or not)
    if (block.type === 'phase' && parseInt(block.teamEvent) !== 1) {
        var twoLbl   = block.twoPerTarget === false ? '×1' : '×2';
        var twoTitle = block.twoPerTarget === false
            ? Lng_Switch2Archers
            : Lng_Switch1Archer;
        h += '<span class="pf-tile-two" onclick="pfToggleTwoPerTarget(\'' + pfEsc(block.id) + '\',' + slotIdx + ',event)" title="' + twoTitle + '">' + twoLbl + '</span>';
    }

    // Title + wave badge
    var waveTag = isWaveBlk
        ? ' <span class="pf-wave-tag">' + (waveLabels[block.waveRow || 0] || '') + '</span>'
        : '';
    var label = block.type === 'training'
        ? ('<span class="pf-tile-evcode">' + block.event + '</span>'
         + '<span class="pf-tile-phase">'+ Lng_WarmUp +'</span>')
        : ('<span class="pf-tile-evcode">' + block.event + '</span>'
         + '<span class="pf-tile-phase">' + block.phaseName + '</span>');
    h += label + waveTag;
    h += '</div>';

    // Target face area
    h += '<div class="pf-tile-svg"><img src="' + PF_SVG + '0.svg" alt="" style="max-height:22px;"></div>';

    // Expand/shrink buttons for training blocks
    if (block.type === 'training') {
        var curSpan   = (segInfo && segInfo.span) ? segInfo.span : 1;
        var allTgts   = pfData.targets;
        var tl        = block.targetList || [];
        var lastTgt   = tl.length ? tl[tl.length - 1] : null;
        var canExpand = lastTgt !== null && allTgts.indexOf(lastTgt) < allTgts.length - 1;
        var canShrink = curSpan > 1;
        h += '<div class="pf-training-resize">';
        h += '<span class="pf-tr-btn' + (canShrink ? '' : ' pf-tr-disabled') + '"'
           + (canShrink ? ' onclick="pfTrainingResize(' + slotIdx + ',\'' + pfEsc(block.id) + '\',-1,event)"' : '')
           + ' title="Réduire d\'une cible">◀</span>';
        h += '<span class="pf-tr-btn' + (canExpand ? '' : ' pf-tr-disabled') + '"'
           + (canExpand ? ' onclick="pfTrainingResize(' + slotIdx + ',\'' + pfEsc(block.id) + '\',1,event)"' : '')
           + ' title="Étendre d\'une cible">▶</span>';
        h += '</div>';
    }

    // Body: matches aligned to their target columns
    if (block.type === 'phase' && block.matches && block.matches.length) {
        var span     = (segInfo && segInfo.span)          ? segInfo.span     : 1;
        var startCol = (segInfo && segInfo.startCol != null) ? segInfo.startCol : 0;

        // Filter matches for this segment
        var segs = block._segments || [];
        var seg  = segs[segIdx || 0] || null;
        var matchesToShow = block.matches;
        if (seg) {
            var filtered = block.matches.filter(function (m) {
                return m.target >= seg.startTarget && m.target <= seg.endTarget;
            });
            if (filtered.length) matchesToShow = filtered;
        }

        // Build an array indexed by relative column
        var colSlots = new Array(span);
        for (var si2 = 0; si2 < span; si2++) { colSlots[si2] = []; }

        var isTeam    = parseInt(block.teamEvent) === 1;
        var anyPlaced = false;

        if (block.twoPerTarget === false && !isTeam) {
            // 1 archer per target.
            // _mirrorOnly → always pos2 in col 0 (mirror target = drop column).
            // _canonOnly  → always pos1 in col 0 (canonical target = drop column).
            // Normal tile → pos1 in the match.target column, pos2 in the next column.
            matchesToShow.forEach(function (m) {
                if (block._mirrorOnly) {
                    // Mirror = even archer (position B = top target in normal convention,
                    // or bottom target in the forced/inverted case)
                    var mirrorArcher = ((m.pos1 % 2) !== 0) ? m.pos2 : m.pos1;
                    colSlots[0].push({ pos1: mirrorArcher, _solo: true });
                    anyPlaced = true;
                } else if (block._canonOnly) {
                    // Canonical = odd archer (position A)
                    var canonArcher = ((m.pos1 % 2) !== 0) ? m.pos1 : m.pos2;
                    colSlots[0].push({ pos1: canonArcher, _solo: true });
                    anyPlaced = true;
                } else {
                    // Target assigned.
                    // iAnseo convention: the odd archer is always at the canonical target (m.target).
                    // The even archer is at the mirror target (m.mirrorTarget if known, otherwise m.target+1).
                    // The lowest target is displayed on the left.
                    var colIdx      = pfData.targets.indexOf(m.target);
                    var rel         = colIdx - startCol;
                    var oddArcher   = ((m.pos1 % 2) !== 0) ? m.pos1 : m.pos2;
                    var evenArcher  = ((m.pos1 % 2) !== 0) ? m.pos2 : m.pos1;
                    var mirTgt      = m.mirrorTarget || 0;
                    var mirColIdx   = mirTgt > 0 ? pfData.targets.indexOf(mirTgt) : colIdx + 1;
                    var relMirror   = mirColIdx - startCol;
                    // Place the odd archer at its canonical target
                    if (rel >= 0 && rel < span) {
                        colSlots[rel].push({ pos1: oddArcher, _solo: true });
                        anyPlaced = true;
                    }
                    // Place the even archer at its mirror target
                    if (relMirror >= 0 && relMirror < span && relMirror !== rel) {
                        colSlots[relMirror].push({ pos1: evenArcher, _solo: true });
                        anyPlaced = true;
                    }
                }
            });
        } else {
            matchesToShow.forEach(function (m) {
                var colIdx = pfData.targets.indexOf(m.target);
                var rel    = colIdx - startCol;
                if (rel >= 0 && rel < span) {
                    colSlots[rel].push(m);
                    anyPlaced = true;
                }
            });
        }

        // No target assigned → distribute evenly
        // For individual ×1 mode: odd=left, even=right rule (2 columns per match)
        // For ×2 and team mode: one match per slot, pos1/pos2 order handled at render time
        if (!anyPlaced) {
            if (block.twoPerTarget === false && !isTeam) {
                // ×1 unassigned: distribute in column pairs with odd on the left
                matchesToShow.forEach(function (m, mi) {
                    var leftPos  = ((m.pos1 % 2) !== 0) ? m.pos1 : m.pos2;
                    var rightPos = ((m.pos1 % 2) !== 0) ? m.pos2 : m.pos1;
                    var relL = Math.min(mi * 2,     span - 1);
                    var relR = Math.min(mi * 2 + 1, span - 1);
                    colSlots[relL].push({ pos1: leftPos,  _solo: true });
                    if (relR !== relL) colSlots[relR].push({ pos1: rightPos, _solo: true });
                });
            } else {
                var step = Math.max(1, Math.floor(span / matchesToShow.length));
                matchesToShow.forEach(function (m, mi) {
                    var rel = Math.min(mi * step, span - 1);
                    colSlots[rel].push(m);
                });
            }
        }

        h += '<div class="pf-tile-body">';
        for (var sc = 0; sc < span; sc++) {
            h += '<div class="pf-tile-slot">';
            for (var mi = 0; mi < colSlots[sc].length; mi++) {
                var m = colSlots[sc][mi];
                if (isTeam || m._solo) {
                    // Team or "1 archer/target": a single position per column
                    h += '<span class="pf-pos pf-pos-solo">' + (m.pos1 || '?') + '</span>';
                } else {
                    // ×2: convention always applied — odd position on the left (A), even on the right (B)
                    var leftPos  = ((m.pos1 % 2) !== 0) ? m.pos1 : m.pos2;
                    var rightPos = ((m.pos1 % 2) !== 0) ? m.pos2 : m.pos1;
                    h += '<span class="pf-match-box">'
                       + '<span class="pf-pos">' + (leftPos  || '?') + '</span>'
                       + '<span class="pf-pos-sep">⚔</span>'
                       + '<span class="pf-pos">' + (rightPos || '?') + '</span>'
                       + '</span>';
                }
            }
            h += '</div>';
        }
        h += '</div>';
    }

    h += '</div>';
    return h;
}

/* ============================================================
   Dragula
   ============================================================ */
function pfInitDragula() {
    if (pfDrake) { pfDrake.destroy(); pfDrake = null; }

    var containers = Array.from(document.querySelectorAll('.pf-dz'));

    pfDrake = dragula(containers, {
        revertOnSpill: true,
        copy: false,
        accepts: function (el, target) {
            if (!target.classList.contains('pf-dz')) return false;

            // Unscheduled zones: always accept (multiple tiles allowed)
            var targetSlot = parseInt(target.getAttribute('data-slot-idx'), 10);
            if (targetSlot === -1) return true;

            // Grid cell: reject if already occupied by another tile
            var others = Array.from(target.querySelectorAll('.pf-tile'))
                             .filter(function (t) { return t !== el; });
            return others.length === 0;
        }
    });

    pfDrake.on('drop', function (el, target, source) {
        var blockId   = el.getAttribute('data-block-id');
        var newSlot   = parseInt(target.getAttribute('data-slot-idx'), 10);
        var newCol    = parseInt(target.getAttribute('data-col'), 10);
        var oldSlot   = parseInt(el.getAttribute('data-slot-idx'), 10);
        var segIdx    = parseInt(el.getAttribute('data-seg-idx') || '0', 10);
        var newWaveRow = parseInt(target.getAttribute('data-wave-row') || '0', 10);

        if (newSlot === -1) {
            pfRemoveTileById(blockId, oldSlot);
        } else {
            pfMoveBlock(blockId, oldSlot, segIdx, newSlot, newCol, newWaveRow);
        }
        pfDirty = true;
        pfStatus(Lng_ChangesNotSaved, '');
    });

    pfDrake.on('drag', function (el) {
        el.setAttribute('data-dragging', '1');
    });
}

function pfRefreshDragula() {
    pfInitDragula();
}

/* ============================================================
   Fetch a block (merging wave sub-blocks)
   Removes the block(s) from their location and returns a merged block.
   ============================================================ */
function pfFetchBlock(blockId, slotIdx) {
    var foundBlk    = null;
    var foundInSlot = null;

    // Look in the specified slot
    if (slotIdx >= 0 && pfData.slots[slotIdx]) {
        var slot = pfData.slots[slotIdx];
        var idx  = slot.blocks.findIndex(function (b) { return b.id === blockId; });
        if (idx >= 0) { foundBlk = slot.blocks[idx]; foundInSlot = slot; }
    }

    // Look in unscheduled if not found
    if (!foundBlk) {
        var ui = pfData.unscheduled.findIndex(function (b) { return b.id === blockId; });
        if (ui >= 0) foundBlk = pfData.unscheduled[ui];
    }

    if (!foundBlk) return null;

    // Detect whether the block is a WAVE sub-block (pattern _wN or id === baseBlockId).
    // ONLY wave blocks should be grouped by baseBlockId.
    // All others (segments _sN, original blocks) are fetched individually.
    var waveSubRx      = /^.+_w\d+$/;
    var isWaveSubBlock = waveSubRx.test(foundBlk.id);
    var isWaveMain     = !isWaveSubBlock && foundBlk.baseBlockId
                         && foundBlk.id === foundBlk.baseBlockId;
    var isWaveBlock    = isWaveSubBlock || isWaveMain;

    var baseId = isWaveBlock ? foundBlk.baseBlockId : foundBlk.id;

    // Gather siblings and remove them from their location
    var siblings = [];

    if (foundInSlot) {
        // Block in a scheduled slot: remove ONLY this block.
        // Wave sub-blocks (AB / CD) are managed independently in the grid;
        // they are not merged here to avoid accidentally moving siblings.
        foundInSlot.blocks = foundInSlot.blocks.filter(function (b) { return b.id !== foundBlk.id; });
        return foundBlk;
    } else {
        var uRemaining = [];
        pfData.unscheduled.forEach(function (b) {
            var match = isWaveBlock
                ? (b.baseBlockId === baseId && (b.id === baseId || waveSubRx.test(b.id)))
                : (b.id === foundBlk.id);
            if (match) siblings.push(b);
            else uRemaining.push(b);
        });
        pfData.unscheduled = uRemaining;
    }

    if (!siblings.length) return null;

    // Sort by waveRow to recombine in order
    siblings.sort(function (a, b) { return (a.waveRow || 0) - (b.waveRow || 0); });

    // Merge all matches
    var merged = siblings[0];
    var allMatches = [];
    siblings.forEach(function (b) {
        (b.matches || []).forEach(function (m) { allMatches.push(m); });
    });
    merged.id          = baseId;
    merged.matches     = allMatches;
    merged.targetList  = [];
    merged.waveRow     = 0;
    delete merged.baseBlockId;

    return merged;
}

/* ============================================================
   Move a block
   ============================================================ */
function pfMoveBlock(blockId, oldSlotIdx, segIdx, newSlotIdx, newColIdx, newWaveRow) {
    var dstSlot = pfData.slots[newSlotIdx];
    if (!dstSlot) return;
    if (isNaN(newWaveRow)) newWaveRow = 0;

    // === Special case: multi-segment block on the grid → move only the clicked segment ===
    var srcSlot = (oldSlotIdx >= 0) ? pfData.slots[oldSlotIdx] : null;
    if (srcSlot) {
        var blkInSlot = null;
        for (var bi2 = 0; bi2 < srcSlot.blocks.length; bi2++) {
            if (srcSlot.blocks[bi2].id === blockId) { blkInSlot = srcSlot.blocks[bi2]; break; }
        }
        if (blkInSlot && blkInSlot.type === 'phase') {
            var segsCheck = pfGetContiguousSegments(blkInSlot.targetList || [], pfData.targets);
            if (segsCheck.length > 1) {
                var movSeg    = segsCheck[segIdx] || segsCheck[0];
                var oldSegTgts = pfData.targets.slice(movSeg.startCol, movSeg.startCol + movSeg.span);
                var oldSegSet  = {};
                oldSegTgts.forEach(function (t) { oldSegSet[t] = true; });
                var newSegTgts = pfData.targets.slice(newColIdx, newColIdx + movSeg.span);
                if (!newSegTgts.length) return;

                // Matches for this segment only
                var movMatches = (blkInSlot.matches || []).filter(function (m) { return oldSegSet[m.target]; });
                for (var mi2 = 0; mi2 < movMatches.length; mi2++) {
                    movMatches[mi2].target = newSegTgts[mi2 % newSegTgts.length];
                    movMatches[mi2].mirrorTarget = 0;  // reset: canonical+1 convention applies after move
                }

                if (newSlotIdx === oldSlotIdx) {
                    // Same slot: direct update of targetList
                    blkInSlot.targetList = (blkInSlot.targetList || [])
                        .filter(function (t) { return !oldSegSet[t]; })
                        .concat(newSegTgts);
                    blkInSlot.targetList.sort(function (a, b) { return pfData.targets.indexOf(a) - pfData.targets.indexOf(b); });
                } else {
                    // Different slot: extract the segment and add it to the destination
                    var blkTpl = JSON.parse(JSON.stringify(blkInSlot));
                    blkInSlot.matches    = (blkInSlot.matches || []).filter(function (m) { return !oldSegSet[m.target]; });
                    blkInSlot.targetList = (blkInSlot.targetList || []).filter(function (t) { return !oldSegSet[t]; });
                    if (!blkInSlot.matches.length) {
                        srcSlot.blocks.splice(srcSlot.blocks.indexOf(blkInSlot), 1);
                    }
                    // Add or merge into the destination slot
                    var dstExisting = null;
                    for (var di2 = 0; di2 < dstSlot.blocks.length; di2++) {
                        if (dstSlot.blocks[di2].id === blockId) { dstExisting = dstSlot.blocks[di2]; break; }
                    }
                    if (dstExisting) {
                        movMatches.forEach(function (m) { dstExisting.matches.push(m); });
                        dstExisting.targetList = (dstExisting.targetList || []).concat(newSegTgts);
                        dstExisting.targetList.sort(function (a, b) { return pfData.targets.indexOf(a) - pfData.targets.indexOf(b); });
                    } else {
                        blkTpl.matches    = movMatches.slice();
                        blkTpl.targetList = newSegTgts.slice();
                        blkTpl.date       = dstSlot.date;
                        blkTpl.time       = dstSlot.time;
                        blkTpl.waveRow    = newWaveRow;
                        delete blkTpl.baseBlockId;
                        delete blkTpl._segments;
                        dstSlot.blocks.push(blkTpl);
                    }
                }

                pfDirty = true;
                pfRender();
                pfRefreshDragula();
                pfLoadUnscheduled();
                return;
            }
        }
    }

    // === Standard case: single-segment block or from unscheduled ===

    // Fetch the merged block (removes siblings from their source)
    var blk = pfFetchBlock(blockId, oldSlotIdx);
    if (!blk) return;

    // New date/time
    blk._newDate = dstSlot.date;
    blk._newTime = dstSlot.time;

    // For teams, the iAnseo mirror (canonical+1) does not apply:
    // each team has its own matchNo in FinSchedule.
    var isTeamBlk = parseInt(blk.teamEvent) === 1;

    // Recalculate the span
    var segs, seg, span;
    if (blk.type === 'training') {
        // Keep existing span if already placed (targetList), otherwise default to 1
        span = (blk.targetList && blk.targetList.length) ? blk.targetList.length : 1;
    } else {
        var hasTL = blk.targetList && blk.targetList.length > 0;
        if (hasTL) {
            // Block with known targets: use existing segments
            segs = blk._segments || pfGetContiguousSegments(blk.targetList, pfData.targets);
            seg  = segs[segIdx] || null;
            span = seg ? seg.span : 1;
        } else {
            // No targets assigned (merged / unscheduled block):
            // ignore _segments (stale), span = number of matches
            // × 2 if "1 archer per target" (each match occupies 2 adjacent columns)
            var matchCount = (blk.matches && blk.matches.length) || 1;
            // The mirror (×2) only applies to individuals; for teams, 1 target/match.
            // Half-tile _s0/_s1: always 1 column (do not double).
            if (blk._canonOnly || blk._mirrorOnly) {
                span = 1;
            } else {
                span = (blk.twoPerTarget === false && !isTeamBlk) ? matchCount * 2 : matchCount;
            }
        }
    }

    // Destination targets
    var newTargets = pfData.targets.slice(newColIdx, newColIdx + span);
    if (!newTargets.length) newTargets = [pfData.targets[newColIdx] || 1];

    if (blk.type === 'phase' && blk.matches) {
        // Determine the matches for this segment
        var segDef = segs ? (segs[segIdx] || null) : null;
        var segMatches = (segDef && segDef.startTarget != null && segDef.endTarget != null)
            ? blk.matches.filter(function (m) {
                return m.target >= segDef.startTarget && m.target <= segDef.endTarget;
              })
            : blk.matches;
        if (!segMatches.length) segMatches = blk.matches;

        // Assign targets.
        // For "1 archer per target" (twoPerTarget=false):
        //   each canonical match → even column (0, 2, 4…), the odd column being
        //   reserved for the iAnseo mirror (written by the server on save).
        // Special case: mirror half-tile (_s1 after splitting a ×1 match):
        //   m.target absent from targetList → the drop column is the mirror column.
        //   Canonical target = mirror column - 1.
        // For "2 archers per target" (twoPerTarget=true): standard assignment (mi % span).
        // Explicit flag takes priority over the heuristic (target absent from targetList).
        // _mirrorOnly = mirror half-tile (_s1), _canonOnly = canonical half-tile (_s0).
        var isMirrorHalf;
        if (blk._mirrorOnly) {
            isMirrorHalf = true;
        } else if (blk._canonOnly) {
            isMirrorHalf = false;
        } else {
            isMirrorHalf = (blk.twoPerTarget === false && !isTeamBlk
                            && segMatches.length === 1
                            && segMatches[0].target > 0   // target=0 = unscheduled → always canonical
                            && (blk.targetList || []).indexOf(segMatches[0].target) < 0);
        }
        for (var mi = 0; mi < segMatches.length; mi++) {
            if (isMirrorHalf) {
                // Drop column = mirror → canonical = mirror - 1
                segMatches[mi].target = (newTargets[0] || 2) - 1;
            } else {
                var tgtIdx = (blk.twoPerTarget === false && !isTeamBlk) ? (mi * 2) : (mi % newTargets.length);
                segMatches[mi].target = newTargets[tgtIdx % newTargets.length] || newTargets[0];
            }
            // Reset mirrorTarget: after a move, the standard convention
            // (mirror = canonical+1) applies — the old value would be stale.
            segMatches[mi].mirrorTarget = 0;
        }

        // Number of waves needed
        // Wave sub-rows are created only when span > 1 (multiple physical targets)
        var waveCount = (span > 1) ? Math.ceil(segMatches.length / span) : 1;

        if (waveCount > 1) {
            // Wave mode: create waveCount sub-blocks
            var baseId = blk.id;
            for (var wv = 0; wv < waveCount; wv++) {
                var waveMatchSlice = segMatches.slice(wv * span, (wv + 1) * span);
                var waveBlk          = JSON.parse(JSON.stringify(blk));
                waveBlk.id           = wv === 0 ? baseId : (baseId + '_w' + wv);
                waveBlk.baseBlockId  = baseId;
                waveBlk.waveRow      = wv;
                waveBlk.targetList   = newTargets.slice();
                waveBlk.date         = dstSlot.date;
                waveBlk.time         = dstSlot.time;
                waveBlk.matches      = waveMatchSlice.map(function (m) {
                    return { matchNo: m.matchNo, pos1: m.pos1, pos2: m.pos2, target: m.target };
                });
                dstSlot.blocks.push(waveBlk);
            }
        } else {
            // No wave: single block in the target wave row
            blk.waveRow = newWaveRow;
            // For twoPerTarget=false (1 archer/target), each canonical match occupies 2 adjacent
            // columns (its own + the iAnseo mirror column). newTargets was already computed
            // with span×2, so it is used directly as targetList.
            if (blk.twoPerTarget === false && !isTeamBlk) {
                blk.targetList = newTargets.slice();   // [col, col+1] already computed (individuals only)
            } else {
                blk.targetList = blk.matches.map(function (m) { return m.target; })
                                             .filter(function (t) { return t > 0; });
            }
            blk.date = dstSlot.date;
            blk.time = dstSlot.time;
            dstSlot.blocks.push(blk);
        }
    } else if (blk.type === 'training') {
        blk.targetList = newTargets;
        blk.waveRow    = newWaveRow;
        blk.date       = dstSlot.date;
        blk.time       = dstSlot.time;
        dstSlot.blocks.push(blk);
    }

    pfDirty = true;
    pfRender();
    pfRefreshDragula();
    pfLoadUnscheduled();
}

/* ============================================================
   Slot management (rows)
   ============================================================ */
function pfAddSlot() {
    var lastSlot = pfData.slots[pfData.slots.length - 1];
    var date = lastSlot ? lastSlot.date : pfTodayStr();
    var time = lastSlot ? pfAddMinutes(lastSlot.time, lastSlot.duration * pfSlotWaveCount(lastSlot)) : '09:00';

    var newSlot = {
        id:       'slot_new_' + Date.now(),
        date:     date,
        time:     time,
        duration: 30,
        blocks:   []
    };
    pfData.slots.push(newSlot);
    pfDirty = true;
    pfRender();
    pfRefreshDragula();
}

function pfInsertSlot(slotIdx) {
    var currentSlot = pfData.slots[slotIdx];
    var date = currentSlot ? currentSlot.date : pfTodayStr();
    var time = currentSlot ? pfAddMinutes(currentSlot.time, currentSlot.duration * pfSlotWaveCount(currentSlot)) : '09:00';
	
	var curDate=currentSlot.date;
	var curTime=pfAddMinutes(currentSlot.time, currentSlot.duration * pfSlotWaveCount(currentSlot));
	
    

    var newSlot = {
        id:       'slot_new_' + Date.now(),
        date:     date,
        time:     time,
        duration: 30,
        blocks:   []
    };


    pfData.slots.splice(slotIdx + 1, 0,newSlot);
	
	if ($('#chkAutoShift').is(':checked')  && slotIdx + 2 < pfData.slots.length) {
        for (var si = slotIdx + 2; si < pfData.slots.length; si++) {
            pfData.slots[si] = pfShiftSlot(pfData.slots[si], 30);
        }
    }
	
	
	
    pfDirty = true;
    pfRender();
    pfRefreshDragula();
}
function pfRemoveSlot(slotIdx) {
    var slot = pfData.slots[slotIdx];
	var Delta = 0 - slot.duration
    if (!slot) return;
    if (slot.blocks.length > 0) {
        if (!confirm(Lng_ConfirmRemoveSlot)) return;

        // Group wave siblings and merge before sending to unscheduled
        var baseIdMap = {};
        slot.blocks.forEach(function (b) {
            var bid = b.baseBlockId || b.id;
            if (!baseIdMap[bid]) baseIdMap[bid] = [];
            baseIdMap[bid].push(b);
        });
        Object.keys(baseIdMap).forEach(function (bid) {
            var group = baseIdMap[bid].sort(function (a, b2) { return (a.waveRow || 0) - (b2.waveRow || 0); });
            var merged      = group[0];
            var allMatches  = [];
            group.forEach(function (b) {
                (b.matches || []).forEach(function (m) { m.target = 0; allMatches.push(m); });
            });
            merged.id          = bid;
            merged.matches     = allMatches;
            merged.targetList  = [];
            merged.waveRow     = 0;
            delete merged.baseBlockId;
            pfData.unscheduled.push(merged);
        });
    }
    pfData.slots.splice(slotIdx, 1);
	
	if ($('#chkAutoShift').is(':checked') && slotIdx < pfData.slots.length - 1) {
        for (var si = slotIdx ; si < pfData.slots.length; si++) {
            pfData.slots[si] = pfShiftSlot(pfData.slots[si], Delta);
        }
    }
    pfDirty = true;
    pfRender();
    pfRefreshDragula();
    pfLoadUnscheduled();
}

/* Empty all blocks from a slot and send them to unscheduled
   (the slot itself is kept, empty) */
function pfClearSlotBlocks(slotIdx) {
    var slot = pfData.slots[slotIdx];
    if (!slot || slot.blocks.length === 0) return;

    // Group wave siblings by baseBlockId and merge before sending to unscheduled
    var baseIdMap = {};
    slot.blocks.forEach(function (b) {
        var bid = b.baseBlockId || b.id;
        if (!baseIdMap[bid]) baseIdMap[bid] = [];
        baseIdMap[bid].push(b);
    });
    Object.keys(baseIdMap).forEach(function (bid) {
        var group  = baseIdMap[bid].sort(function (a, b2) { return (a.waveRow || 0) - (b2.waveRow || 0); });
        var merged = group[0];
        var allMatches = [];
        group.forEach(function (b) {
            (b.matches || []).forEach(function (m) { m.target = 0; allMatches.push(m); });
        });
        merged.id         = bid;
        merged.matches    = allMatches;
        merged.targetList = [];
        merged.waveRow    = 0;
        delete merged.baseBlockId;
        pfData.unscheduled.push(merged);
    });

    slot.blocks = [];
    slot.waves  = 1;   // reset wave mode (empty slot → 1 wave)
    pfDirty = true;
    pfRender();
    pfRefreshDragula();
    pfLoadUnscheduled();
}

function pfToggleSlotWaves(slotIdx) {
    var slot = pfData.slots[slotIdx];
    if (!slot) return;

    // Number of currently active waves
    var currentWaves = Math.max(
        slot.waves || 1,
        slot.blocks.some(function (b) { return (b.waveRow || 0) > 0; }) ? 2 : 1
    );

    if (currentWaves >= 2) {
        // Reduce to 1 wave: check that the CD wave is empty
        var hasWave1 = slot.blocks.some(function (b) { return (b.waveRow || 0) > 0; });
        if (hasWave1) {
            alert(Lng_AlertSwitch1Wave);
            return;
        }
        slot.waves = 1;
    } else {
        slot.waves = 2;
    }

    // Cascade following slots if requested (same index approach as pfApplySlotEdit)
    var newWaves = slot.waves;
    var delta    = slot.duration * (newWaves - currentWaves);
    if ($('#chkAutoShift').is(':checked') && delta !== 0) {
        for (var si = slotIdx + 1; si < pfData.slots.length; si++) {
            pfShiftSlot(pfData.slots[si], delta);
        }
    }

    pfDirty = true;
    pfRender();
    pfRefreshDragula();
}

/** Expands or shrinks a training block by one target (delta = +1 or -1) */
function pfTrainingResize(slotIdx, blockId, delta, evt) {
    if (evt) evt.stopPropagation();
    var slot = pfData.slots[slotIdx];
    if (!slot) return;
    var blk = null;
    for (var i = 0; i < slot.blocks.length; i++) {
        if (slot.blocks[i].id === blockId) { blk = slot.blocks[i]; break; }
    }
    if (!blk || blk.type !== 'training') return;

    var tl         = (blk.targetList || []).slice();
    var allTargets = pfData.targets;

    if (delta > 0) {
        // Expand: add the next target to the right
        var lastTgt = tl.length ? tl[tl.length - 1] : null;
        var lastIdx = lastTgt !== null ? allTargets.indexOf(lastTgt) : -1;
        if (lastIdx < 0 || lastIdx >= allTargets.length - 1) return;
        tl.push(allTargets[lastIdx + 1]);
    } else {
        // Shrink: remove the rightmost target (minimum 1)
        if (tl.length <= 1) return;
        tl.pop();
    }

    blk.targetList = tl;
    pfDirty = true;
    pfRender();
    pfRefreshDragula();
}

function pfToggleSlotEdit(btn, slotIdx) {
    var cell = $(btn).closest('.pf-slot-header');
    cell.toggleClass('editing');
}

function pfCancelSlotEdit(btn) {
    var cell = $(btn).closest('.pf-slot-header');
    cell.removeClass('editing');
}

function pfApplySlotEdit(btn, slotIdx) {
    var cell    = $(btn).closest('.pf-slot-header');
    var newDate = cell.find('.pf-in-date').val();
    var newTime = cell.find('.pf-in-time').val();
    var newDur  = parseInt(cell.find('.pf-in-dur').val(), 10) || 30;

    var slot    = pfData.slots[slotIdx];
    var oldDate = slot.date;
    var oldTime = slot.time;
    var oldDur  = slot.duration;
    var waves   = pfSlotWaveCount(slot);

    // Delta = difference between old end and new end (start time + duration × waves)
    var deltaStart = pfTimeDiffMinutes(oldDate + ' ' + oldTime, newDate + ' ' + newTime);
    var deltaDur   = (newDur - oldDur) * waves;
    var delta      = deltaStart + deltaDur;

    slot.date     = newDate;
    slot.time     = newTime;
    slot.duration = newDur;

    if ($('#chkAutoShift').is(':checked') && delta !== 0) {
        for (var si = slotIdx + 1; si < pfData.slots.length; si++) {
            pfData.slots[si] = pfShiftSlot(pfData.slots[si], delta);
        }
    }

    pfDirty = true;
    pfRender();
    pfRefreshDragula();
}

/** Returns the effective wave count of a slot (forced or detected) */
function pfSlotWaveCount(slot) {
    var forced = slot.waves || 1;
    var actual = (slot.blocks || []).some(function (b) { return (b.waveRow || 0) > 0; }) ? 2 : 1;
    return Math.max(forced, actual);
}

/** Reads the 4 values from the config panel */
function pfGetConfig() {
    return {
        equipeEchauff: parseInt($('#cfgEquipeEchauff').val(), 10) || 15,
        equipeMatch:   parseInt($('#cfgEquipeMatch').val(),   10) || 30,
        indivEchauff:  parseInt($('#cfgIndivEchauff').val(),  10) || 5,
        indivMatch:    parseInt($('#cfgIndivMatch').val(),    10) || 30
    };
}

/** Returns the target duration (1 wave) of a slot based on its blocks, null if undetermined */
function pfGetSlotTargetDuration(slot, cfg) {
    var blocks = slot.blocks || [];
    if (!blocks.length) return null;
    var hasTraining = blocks.some(function (b) { return b.type === 'training'; });
    var hasTeam     = blocks.some(function (b) { return parseInt(b.teamEvent) === 1; });
    var hasIndiv    = blocks.some(function (b) { return parseInt(b.teamEvent) === 0; });
    if (hasTraining && hasTeam)   return cfg.equipeEchauff;
    if (hasTraining && hasIndiv)  return cfg.indivEchauff;
    if (!hasTraining && hasTeam)  return cfg.equipeMatch;
    if (!hasTraining && hasIndiv) return cfg.indivMatch;
    return null;
}

/** Called when a config duration value changes */
function pfApplyConfigDuration() {
    if (!pfData || !pfData.slots) return;
    var cfg       = pfGetConfig();
    var autoShift = $('#chkAutoShift').is(':checked');

    // Chronological order of slots
    var order = pfData.slots.map(function (s, i) { return i; });
    order.sort(function (a, b) {
        var sa = pfData.slots[a], sb = pfData.slots[b];
        return pfTimeDiffMinutes(sb.date + ' ' + sb.time, sa.date + ' ' + sa.time);
    });
    
    order.forEach(function (si, pos) {
        var slot      = pfData.slots[si];
        var targetDur = pfGetSlotTargetDuration(slot, cfg);
        var oldDur    = parseInt(slot.duration, 10) || 0;   // always a number
        if (targetDur === null || targetDur === oldDur) return;

        var waves = pfSlotWaveCount(slot);
        var delta = (targetDur - oldDur) * waves;
        slot.duration = targetDur;
        
        if (autoShift && delta !== 0) {
            for (var j = pos + 1; j < order.length; j++) {
                var prev = pfData.slots[order[j]].time;
                pfShiftSlot(pfData.slots[order[j]], delta);
            }
        }
    });

    pfDirty = true;
    pfRender();
    pfRefreshDragula();
}

function pfShiftSlot(slot, deltaMin) {
    var dt   = new Date(slot.date + 'T' + slot.time + ':00');
    dt.setMinutes(dt.getMinutes() + deltaMin);
    slot.date = pfDateStr(dt);
    slot.time = pfTimeStr(dt);
    return slot;
}

/* ============================================================
   Target management (columns)
   ============================================================ */
function pfAddTarget() {
    var maxT = pfData.targets.length > 0 ? Math.max.apply(null, pfData.targets) : 0;
    pfData.targets.push(maxT + 1);
    pfDirty = true;
    pfRender();
    pfRefreshDragula();
}

function pfRemoveTarget(colIdx) {
    var tgt = pfData.targets[colIdx];
    if (tgt === undefined) return;
    var used = pfData.slots.some(function (slot) {
        return slot.blocks.some(function (b) {
            return (b.targetList || []).indexOf(tgt) >= 0;
        });
    });
    if (used) {
        if (!confirm(Lng_Target +' ' + tgt + ' '+ Lng_ConfirmRemoveTarget )) return;
    }
    pfData.targets.splice(colIdx, 1);
    pfDirty = true;
    pfRender();
    pfRefreshDragula();
}

/* ============================================================
   Remove a block by its id (from drop on unscheduled zone)
   ============================================================ */
function pfRemoveTileById(blockId, slotIdx) {
    var blk = pfFetchBlock(blockId, slotIdx);
    if (!blk) return;

    blk.targetList = [];
    if (blk.type === 'phase') {
        blk.matches && blk.matches.forEach(function (m) { m.target = 0; });
        // Half-tile _s0/_s1: merge into the base block (without _s0/_s1 suffix)
        var unschId = (blk._canonOnly || blk._mirrorOnly)
            ? blockId.replace(/_s[01]$/, '')
            : blockId;
        pfAddToUnscheduled(unschId, blk, blk.matches || []);
    } else {
        pfData.unscheduled.push(blk);
    }
    pfRender();
    pfRefreshDragula();
    pfLoadUnscheduled();
}

/* ============================================================
   Helper: adds matches to an existing unscheduled block
   (same blockId) or creates a new merged entry.
   ============================================================ */
function pfAddToUnscheduled(blockId, blkTemplate, matches) {
    // Look for an existing entry with the same base id
    var existing = null;
    for (var ui = 0; ui < pfData.unscheduled.length; ui++) {
        if (pfData.unscheduled[ui].id === blockId) {
            existing = pfData.unscheduled[ui];
            break;
        }
    }
    if (existing) {
        // Merge matches into the existing entry (deduplicate by matchNo)
        matches.forEach(function (m) {
            var isDup = (existing.matches || []).some(function (em) { return em.matchNo === m.matchNo; });
            if (!isDup) {
                (existing.matches = existing.matches || []).push(
                    { matchNo: m.matchNo, pos1: m.pos1, pos2: m.pos2, target: 0 }
                );
            }
        });
        existing.matches.sort(function (a, b) { return a.matchNo - b.matchNo; });
        // Clear the half-tile flags (both halves are now reunited)
        delete existing._canonOnly;
        delete existing._mirrorOnly;
    } else {
        // Create a new entry with the original id (not _r0, _r1…)
        var unBlk = JSON.parse(JSON.stringify(blkTemplate));
        unBlk.id         = blockId;
        unBlk.matches    = matches.map(function (m) {
            return { matchNo: m.matchNo, pos1: m.pos1, pos2: m.pos2, target: 0 };
        });
        // Deduplicate by matchNo (case of _s0/_s1 with same match)
        var seenNos = {};
        unBlk.matches = unBlk.matches.filter(function (m) {
            if (seenNos[m.matchNo]) return false;
            seenNos[m.matchNo] = true;
            return true;
        });
        unBlk.targetList = [];
        delete unBlk.baseBlockId;
        delete unBlk._segments;
        // Clear half-tile flags so the block is treated normally
        delete unBlk._canonOnly;
        delete unBlk._mirrorOnly;
        pfData.unscheduled.push(unBlk);
    }
}

/* ============================================================
   Remove a tile (✕ button on the tile)
   ============================================================ */
function pfRemoveTile(tileId, e) {
    e && e.stopPropagation();
    var el = document.getElementById(tileId);
    if (!el) return;
    var blockId = el.getAttribute('data-block-id');
    var slotIdx = parseInt(el.getAttribute('data-slot-idx'), 10);
    var segIdx  = parseInt(el.getAttribute('data-seg-idx') || '0', 10);

    // Find the block without removing it (to test whether it has multiple segments)
    var slot = pfData.slots[slotIdx];
    if (!slot) return;
    var blkRef = null;
    for (var bi = 0; bi < slot.blocks.length; bi++) {
        if (slot.blocks[bi].id === blockId) { blkRef = slot.blocks[bi]; break; }
    }
    if (!blkRef) return;

    var segs = pfGetContiguousSegments(blkRef.targetList || [], pfData.targets);

    if (segs.length > 1 && blkRef.type === 'phase') {
        // Multi-segment block (non-contiguous targetList) → remove only the clicked segment
        var seg = segs[segIdx] || segs[0];

        // Targets belonging to this visual segment
        var segTgts = {};
        pfData.targets.slice(seg.startCol, seg.startCol + seg.span).forEach(function (t) {
            segTgts[t] = true;
        });

        // Split matches: those from the clicked segment → unscheduled, others → stay
        var removedMatches  = [];
        var remainingMatches = [];
        (blkRef.matches || []).forEach(function (m) {
            if (segTgts[m.target]) removedMatches.push(m);
            else remainingMatches.push(m);
        });

        // Update the block in place
        blkRef.targetList = (blkRef.targetList || []).filter(function (t) { return !segTgts[t]; });
        blkRef.matches    = remainingMatches;
        if (!blkRef.matches.length) {
            slot.blocks.splice(slot.blocks.indexOf(blkRef), 1);
        }

        // Merge the removed segment into the unscheduled block (or create one)
        if (removedMatches.length) {
            pfAddToUnscheduled(blockId, blkRef, removedMatches);
        }
    } else {
        // Single-segment block → remove the entire block
        var blk = pfFetchBlock(blockId, slotIdx);
        if (!blk) return;
        blk.targetList = [];
        if (blk.type === 'phase') {
            (blk.matches || []).forEach(function (m) { m.target = 0; });
            // Half-tile _s0/_s1: merge into the base block (without _s0/_s1 suffix)
            var unschId = (blk._canonOnly || blk._mirrorOnly)
                ? blockId.replace(/_s[01]$/, '')
                : blockId;
            pfAddToUnscheduled(unschId, blk, blk.matches || []);
        } else {
            pfData.unscheduled.push(blk);
        }
    }

    pfDirty = true;
    pfRender();
    pfRefreshDragula();
    pfLoadUnscheduled();
}

/* ============================================================
   Segmentation: 1 block per match, no popup
   ============================================================ */
function pfSegmentTile(blockId, slotIdx, e) {
    e && e.stopPropagation();
    var slot = pfData.slots[slotIdx];
    if (!slot) return;
    var blkIdx = -1;
    var blk = null;
    slot.blocks.forEach(function (b, i) { if (b.id === blockId) { blk = b; blkIdx = i; } });
    if (!blk || blk.type !== 'phase') return;

    var nMatches = blk.matches.length;
    var tl = blk.targetList || [];
    var T  = tl.length;

    var newBlocks;
    if (nMatches === 1 && blk.twoPerTarget === false) {
        // ×1 (1 archer/target): 1 match occupies 2 columns (canonical + mirror).
        // Split into 2 sub-tiles: canonical column (pos1) + mirror column (pos2).
        newBlocks = [0, 1].map(function (i) {
            var seg = JSON.parse(JSON.stringify(blk));
            seg.id         = blk.id + '_s' + i;
            seg.matches    = [JSON.parse(JSON.stringify(blk.matches[0]))];
            delete seg.baseBlockId;
            delete seg._segments;
            seg.targetList = tl.slice(i, i + 1);
            // Mark for server-side persistence
            if (i === 0) { seg._canonOnly  = true;  delete seg._mirrorOnly; }
            else          { seg._mirrorOnly = true;  delete seg._canonOnly;  }
            return seg;
        });
    } else if (nMatches >= 2) {
        // Normal case: distribute targets across segments (fair distribution)
        // Segment i receives targets at index [i*T/N … (i+1)*T/N - 1]
        var N = nMatches;
        newBlocks = blk.matches.map(function (m, i) {
        var seg = JSON.parse(JSON.stringify(blk));
        seg.id      = blk.id + '_s' + i;
        seg.matches = [JSON.parse(JSON.stringify(m))];
        // Remove inherited baseBlockId from a previous wave:
        // otherwise pfFetchBlock would use baseBlockId to group ALL segments
        delete seg.baseBlockId;
        delete seg._segments;

        var from = Math.round(i * T / N);
        var to   = Math.round((i + 1) * T / N);
        seg.targetList = (T > 0) ? tl.slice(from, to) : [];

        return seg;
        });
    } else {
        return;  // nMatches < 2 and twoPerTarget !== false → nothing to do
    }

    // Replace the original block with the segments
    slot.blocks.splice(blkIdx, 1);
    newBlocks.forEach(function (seg) { slot.blocks.push(seg); });

    pfDirty = true;
    pfRender();
    pfRefreshDragula();
    pfLoadUnscheduled();
}

/* ============================================================
   Toggle 1 archer / 2 archers per target
   ============================================================ */
function pfToggleTwoPerTarget(blockId, slotIdx, e) {
    e && e.stopPropagation();
    var blk = null;
    if (slotIdx >= 0 && pfData.slots[slotIdx]) {
        pfData.slots[slotIdx].blocks.forEach(function (b) {
            if (b.id === blockId) blk = b;
        });
    }
    if (!blk || blk.type !== 'phase') return;

    // Toggle: false (×1) → true (×2) and vice versa
    var newVal = (blk.twoPerTarget === false);   // false→true, true→false

    // Apply twoPerTarget + rebuild targetList for a given block
    function applyToggle(b) {
        b.twoPerTarget = newVal;
        if (b.matches && b.matches.length > 0) {
            var tSet = {}, tl = [];
            b.matches.forEach(function (m) {
                if (m.target > 0 && !tSet[m.target]) {
                    tSet[m.target] = true;
                    tl.push(m.target);
                    if (!newVal) {                      // ×1: add the mirror column
                        var mirror = m.target + 1;
                        if (!tSet[mirror]) { tSet[mirror] = true; tl.push(mirror); }
                    }
                }
            });
            tl.sort(function (a, b) {
                return pfData.targets.indexOf(a) - pfData.targets.indexOf(b);
            });
            b.targetList = tl;
        }
    }

    applyToggle(blk);

    // Propagate to wave siblings (same baseBlockId) in the same slot
    var baseId = blk.baseBlockId || blk.id;
    pfData.slots[slotIdx].blocks.forEach(function (b) {
        if (b !== blk && b.type === 'phase') {
            var bBase = b.baseBlockId || b.id;
            if (bBase === baseId) applyToggle(b);
        }
    });

    pfDirty = true;
    pfStatus(Lng_ChangesNotSaved, '');
    pfRender();
    pfRefreshDragula();
}

/* ============================================================
   Automatic merge of all-unscheduled segments
   ============================================================ */
function pfMergeUnscheduledSegments() {
    var segRx = /^(.+)_s\d+$/;

    // Group unscheduled segments by base ID
    var groups = {};
    pfData.unscheduled.forEach(function (b) {
        var m = segRx.exec(b.id);
        if (!m) return;
        var baseId = m[1];
        if (!groups[baseId]) groups[baseId] = [];
        groups[baseId].push(b);
    });

    Object.keys(groups).forEach(function (baseId) {
        // Check that no segment for this baseId is still on the grid
        var stillOnGrid = pfData.slots.some(function (slot) {
            return slot.blocks.some(function (b) {
                var m = segRx.exec(b.id);
                return m && m[1] === baseId;
            });
        });
        if (stillOnGrid) return;

        var segs = groups[baseId];
        if (segs.length < 2) return;  // single segment, nothing to merge

        // Sort by segment index (_s0, _s1, …)
        segs.sort(function (a, b) {
            return parseInt(a.id.replace(/^.+_s/, ''), 10)
                 - parseInt(b.id.replace(/^.+_s/, ''), 10);
        });

        // Merge: recombine all matches into the first block
        var merged = JSON.parse(JSON.stringify(segs[0]));
        merged.id         = baseId;
        merged.targetList = [];
        merged.matches    = [];
        delete merged._canonOnly;
        delete merged._mirrorOnly;
        var seenMatchNos = {};
        segs.forEach(function (s) {
            (s.matches || []).forEach(function (m) {
                // Deduplicate by matchNo (case of _s0/_s1 with same match ×1)
                if (!seenMatchNos[m.matchNo]) {
                    seenMatchNos[m.matchNo] = true;
                    merged.matches.push({ matchNo: m.matchNo, pos1: m.pos1, pos2: m.pos2, target: 0 });
                }
            });
        });

        // Remove all segments and add the merged block
        pfData.unscheduled = pfData.unscheduled.filter(function (b) {
            var m = segRx.exec(b.id);
            return !(m && m[1] === baseId);
        });
        pfData.unscheduled.push(merged);
        pfDirty = true;
    });
}

/* ============================================================
   Unscheduled blocks — collapsible grouping by category
   ============================================================ */
function pfToggleUnschedGroup(evCode) {
    pfUnschedCollapsed[evCode] = !pfUnschedCollapsed[evCode];
    var body  = document.getElementById('pfUG_' + evCode);
    var arrow = document.getElementById('pfUGA_' + evCode);
    if (body)  body.style.display   = pfUnschedCollapsed[evCode] ? 'none' : '';
    if (arrow) arrow.textContent    = pfUnschedCollapsed[evCode] ? '▶' : '▼';
    pfRefreshDragula();
}

function pfLoadUnscheduled() {
    pfMergeUnscheduledSegments();   // regroup if all segments are unscheduled
    var list = pfData.unscheduled;
    var html = '';

    if (!list.length) {
        html = '<em style="color:#999;font-size:.78em;">Aucun bloc non planifié</em>';
    } else {
        // Group by event code preserving order of appearance
        var groups     = {};
        var groupOrder = [];
        list.forEach(function (b) {
            if (!groups[b.event]) {
                groups[b.event] = { color: b.color, blocks: [] };
                groupOrder.push(b.event);
            }
            groups[b.event].blocks.push(b);
        });

        groupOrder.forEach(function (evCode) {
            var g         = groups[evCode];
            var collapsed = !!pfUnschedCollapsed[evCode];
            var arrow     = collapsed ? '▶' : '▼';
            var bodyDisp  = collapsed ? 'display:none;' : '';

            html += '<div class="pf-unsched-grp">'
                  + '<div class="pf-unsched-grp-hdr" onclick="pfToggleUnschedGroup(\'' + pfEsc(evCode) + '\')">'
                  + '<span id="pfUGA_' + pfEsc(evCode) + '" class="pf-unsched-grp-arrow">' + arrow + '</span>'
                  + '<strong>' + pfEscHtml(evCode) + '</strong>'
                  + '<span class="pf-unsched-grp-count">(' + g.blocks.length + ')</span>'
                  + '</div>'
                  + '<div class="pf-drop-zone pf-dz" '
                  +   'id="pfUG_' + pfEsc(evCode) + '" '
                  +   'data-slot-idx="-1" data-col="-1" data-wave-row="0" '
                  +   'style="' + bodyDisp + '">';

            g.blocks.forEach(function (b) {
                var color   = b.color;
                var textClr = pfContrastColor(color);
                var label   = b.type === 'training'
                    ? ('Échauff. ' + b.event)
                    : (b.event + ' ' + (b.phaseName || ''));

                // Add match identifiers for segments (≤ 3 matches)
                var matchInfo = '';
                if (b.type === 'phase' && b.matches && b.matches.length && b.matches.length <= 3) {
                    var isTeam = parseInt(b.teamEvent) === 1;
                    matchInfo = b.matches.map(function (m) {
                        return isTeam
                            ? (m.pos1 || '?')
                            : (m.pos1 || '?') + '⚔' + (m.pos2 || '?');
                    }).join(' ');
                }

                var rmBtn = (b.type === 'training')
                    ? '<span class="pf-ut-rm" onclick="pfDeleteTraining(\'' + pfEsc(b.id) + '\',event)" title="Supprimer cet échauffement">✕</span>'
                    : '';
                html += '<div class="pf-unsched-tile pf-tile" '
                      + 'id="unsched_' + pfEsc(b.id) + '" '
                      + 'data-block-id="' + pfEsc(b.id) + '" '
                      + 'data-slot-idx="-1" data-seg-idx="0" '
                      + 'data-pf-event="' + pfEsc(b.event) + '" '
                      + 'style="background:' + color + ';color:' + textClr + ';">'
                      + rmBtn
                      + '<span class="pf-ut-label">' + pfEscHtml(label) + '</span>'
                      + (matchInfo ? '<span class="pf-ut-match">' + pfEscHtml(matchInfo) + '</span>' : '')
                      + '</div>';
            });

            html += '</div>'   // .pf-dz (group body)
                  + '</div>';  // .pf-unsched-grp
        });
    }

    $('#unscheduledList').html(html);
    pfRefreshDragula();
}

/* ============================================================
   Target face display
   ============================================================ */
function pfToggleBlasons(show) {
    pfShowBlason = show;
    if (show) {
        $('body').addClass('pf-show-blason');
        $.getJSON(PF_AJAX + '?action=getTargetFaces', function (data) {
            pfApplyBlasons(data.eventFaces || {});
        });
    } else {
        $('body').removeClass('pf-show-blason');
    }
}

function pfApplyBlasons(eventFaces) {
    $('.pf-tile').each(function () {
        var blockId = $(this).attr('data-block-id') || '';
        // Strip prefix, wave suffixes (_w1…), segment suffixes (_seg2…) and phase suffixes (_32…)
        var evCode = blockId
            .replace(/^phase_/, '')
            .replace(/(_w\d+|_seg\d+)*$/, '')
            .replace(/_\d+$/, '');
        var svg = eventFaces[evCode] || '0.svg';
        $(this).find('.pf-tile-svg img').attr('src', PF_SVG + svg);
    });
}

/* ============================================================
   Save
   ============================================================ */
function pfSave() {
    pfStatus(Lng_Saving, '');
    $('#btnSave').prop('disabled', true);

    var payload = {
        targets:          pfData.targets,
        slots:            pfData.slots,
        unscheduled:      pfData.unscheduled,
        deletedTrainings: pfDeletedTrainings
    };

    $.ajax({
        url:         PF_AJAX + '?action=save',
        method:      'POST',
        contentType: 'application/json',
        data:        JSON.stringify(payload),
        success: function (resp) {
            $('#btnSave').prop('disabled', false);
            if (resp.ok) {
                pfDirty = false;
                pfStatus(Lng_Saved +' ✔', 'ok');
                if (resp.errors && resp.errors.length) {
                    pfStatus(Lng_SavedWithError +' ' + resp.errors.length, 'error');
                }
            } else {
                pfStatus(Lng_Error +' : ' + (resp.error || Lng_Unknown), 'error');
            }
        },
        error: function () {
            $('#btnSave').prop('disabled', false);
            pfStatus(Lng_ErrorSave, 'error');
        }
    });
}

function pfStatus(msg, cls) {
    var el = $('#pfStatus');
    el.text(msg).removeClass('ok error');
    if (cls) el.addClass(cls);
}

/* ============================================================
   Inline slot editor (initialisation after render)
   ============================================================ */
function pfInitSlotEditors() {
    $('#pfGrid').find('.pf-slot-inputs input').on('keydown', function (e) {
        if (e.key === 'Enter') {
            $(this).closest('.pf-slot-header').find('button:first').trigger('click');
        } else if (e.key === 'Escape') {
            $(this).closest('.pf-slot-header').find('button:last').trigger('click');
        }
    });
}

/* ============================================================
   Utilities
   ============================================================ */

/** Computes the contiguous segments in the target list relative to the columns */
function pfGetContiguousSegments(targetList, allTargets) {
    if (!targetList || !targetList.length) return [];

    var tSet = {};
    targetList.forEach(function (t) { tSet[t] = true; });

    var segs = [];
    var inSeg = false;
    var curSeg = null;

    for (var ci = 0; ci < allTargets.length; ci++) {
        var t = allTargets[ci];
        if (tSet[t]) {
            if (!inSeg) {
                curSeg = { startCol: ci, startTarget: t, endTarget: t, span: 1 };
                inSeg = true;
            } else {
                curSeg.endTarget = t;
                curSeg.span++;
            }
        } else {
            if (inSeg) {
                segs.push(curSeg);
                inSeg = false;
                curSeg = null;
            }
        }
    }
    if (inSeg && curSeg) segs.push(curSeg);

    return segs.length ? segs : [{ startCol: 0, startTarget: allTargets[0] || 1, endTarget: allTargets[0] || 1, span: 1 }];
}

/** Unique DOM ID for a tile */
function pfTileId(slotIdx, blockId, segIdx) {
    return 'tile_' + slotIdx + '_' + pfEsc(blockId) + '_' + (segIdx || 0);
}

/** Escapes for an HTML attribute */
function pfEsc(str) {
    return (str + '').replace(/[^a-zA-Z0-9_\-]/g, '_');
}

/** Escapes for HTML text */
function pfEscHtml(str) {
    return (str + '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/** Contrasting text colour (black or white) based on background luminosity.
 *  Accepts '#rrggbb' and 'rgba(r,g,b,a)'. */
function pfContrastColor(color) {
    var r, g, b;
    var m = (color + '').match(/rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)/i);
    if (m) {
        r = parseInt(m[1]); g = parseInt(m[2]); b = parseInt(m[3]);
    } else {
        var hex = (color + '').replace('#', '');
        if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        r = parseInt(hex.slice(0,2),16);
        g = parseInt(hex.slice(2,4),16);
        b = parseInt(hex.slice(4,6),16);
    }
    var lum = (0.299*r + 0.587*g + 0.114*b) / 255;
    return lum > 0.55 ? '#222' : '#fff';
}

/** Adds minutes to HH:MM */
function pfAddMinutes(time, min) {
    var parts = (time || '00:00').split(':');
    var h = parseInt(parts[0], 10) || 0;
    var m = parseInt(parts[1], 10) || 0;
    m += parseInt(min, 10) || 0;
    h += Math.floor(m / 60);
    m = m % 60;
    return pfPad2(h % 24) + ':' + pfPad2(m);
}

function pfPad2(n) { return n < 10 ? '0' + n : '' + n; }

function pfTodayStr() {
    var d = new Date();
    return d.getFullYear() + '-' + pfPad2(d.getMonth()+1) + '-' + pfPad2(d.getDate());
}

function pfDateStr(dt) {
    return dt.getFullYear() + '-' + pfPad2(dt.getMonth()+1) + '-' + pfPad2(dt.getDate());
}

function pfTimeStr(dt) {
    return pfPad2(dt.getHours()) + ':' + pfPad2(dt.getMinutes());
}

/** Difference in minutes between two "YYYY-MM-DD HH:MM" dateTime strings */
function pfTimeDiffMinutes(dt1, dt2) {
    var a = new Date(dt1.replace(' ', 'T') + ':00');
    var b = new Date(dt2.replace(' ', 'T') + ':00');
    return Math.round((b - a) / 60000);
}

/* ============================================================
   Print
   ============================================================ */
function pfPrint() {
    var gridEl = document.getElementById('pfGrid');
    if (!gridEl) return;

    // Titles from the main page
    var titleParts = [];
    document.querySelectorAll('table.Tabella .Title').forEach(function (el) {
        var t = el.textContent.trim();
        if (t) titleParts.push(t);
    });
    var titleHtml = titleParts.length
        ? titleParts.map(function (t) { return '<div>' + t + '</div>'; }).join('')
        : '<div>'+ Lng_PrintFOP +' — '+ Lng_OrisFinals +'</div>';

    // Measure the actual table width NOW (in the main window, already rendered)
    // then compute the zoom so it fits on A4 landscape (281mm usable ≈ 1062px at 96dpi)
    var tblMain = gridEl.querySelector('table.pf-table');
    var tableW  = tblMain ? tblMain.scrollWidth : 0;
    var PF_PRINT_WIDTH = 1062; // largeur utile A4 landscape 8mm marges
    var zoom = (tableW > PF_PRINT_WIDTH) ? (PF_PRINT_WIDTH / tableW) : 1;

    // Retrieve all CSS links already loaded on the page
    var cssLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(function (l) { return '<link rel="stylesheet" href="' + l.href + '">'; })
        .join('\n');

    var printStyles = [
        '@page { size: A4 landscape; margin: 8mm; }',
        /* Force background colours even if "Background graphics" is unchecked */
        '*, *::before, *::after {',
        '  print-color-adjust: exact !important;',
        '  -webkit-print-color-adjust: exact !important;',
        '}',
        /* White background — overrides the template's blue background */
        'html, body { background: #fff !important; color: #000 !important;'
            + ' margin: 0; padding: 0; font-size: 10px; font-family: sans-serif;'
            + ' zoom: ' + zoom + '; }',
        /* Centred large title */
        '.pf-print-title {'
            + ' text-align: center;'
            + ' margin-bottom: 10px;'
            + ' color: #1e3a5a;'
            + ' line-height: 1.4; }',
        '.pf-print-title div:first-child { font-size: 1.6em; font-weight: bold; }',
        '.pf-print-title div:not(:first-child) { font-size: 1.1em; font-weight: normal; }',
        '.pf-tile-rm, .pf-tile-seg, .pf-training-resize,',
        '.pf-slot-wave-strip, .pf-slot-inputs, .pf-slot-edit-btn,',
        '.pf-rm-col, .pf-slot-rm-btn, .pf-th-add, .pf-cell-add { display: none !important; }',
        '.pf-grid-wrap { overflow: visible !important; max-height: none !important; height: auto !important; }',
        'td.pf-slot-header { position: static !important; }',
        ':root {'
            + ' --pf-col-slot: 90px;'    /* 140px → 90px */
            + ' --pf-col-target: 52px;'  /* 75px → 52px */
            + ' --pf-row-h: 72px;'       /* 90px → 72px */
            + ' --pf-wave-row-h: 54px;'  /* 68px → 54px */
            + ' }',
        '.pf-slot-display { min-height: 0 !important; }',
        '.pf-tile { break-inside: avoid; page-break-inside: avoid; }',
        /* Visible grid: borders on all cells */
        'table.pf-table { border-collapse: collapse !important; }',
        'table.pf-table td, table.pf-table th { border: 1px solid #aaa !important; }',
        /* Target headers: dark background, white text */
        'table.pf-table th.pf-th-target { background: #1e3a5a !important; color: #fff !important; }',
        /* Slot headers: light grey background */
        'table.pf-table td.pf-slot-header { background: #f0f4f8 !important; }',
        /* Empty cells: very light background to contrast with tiles */
        'table.pf-table td.pf-cell:not(:has(.pf-tile)) { background: #fafafa !important; }',
    ].join('\n');

    var win = window.open('', '_blank', 'width=1400,height=900');
    if (!win) {
        alert('Autorisez les popups pour imprimer.');
        return;
    }

    win.document.write(
        '<!DOCTYPE html><html><head>'
      + '<meta charset="utf-8">'
      + '<title>'+ Lng_PrintFOP +' — '+ Lng_Print +'</title>'
      + cssLinks
      + '<style>' + printStyles + '</style>'
      + '</head><body>'
      + '<div class="pf-print-title">' + titleHtml + '</div>'
      + gridEl.innerHTML
      + '<script>window.addEventListener("load", function () { window.print(); });<\/script>'
      + '</body></html>'
    );
    win.document.close();
}

/* Warn when leaving with unsaved changes */
window.addEventListener('beforeunload', function (e) {
    if (pfDirty) {
        e.preventDefault();
        e.returnValue = Lng_LostUnsaved;
    }
});
