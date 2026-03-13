/**
 * PlanFinales — Plan de cible des Finales
 * Dépendances : jQuery, Dragula
 */

/* ============================================================
   État global
   ============================================================ */
var pfData              = null;   // {targets, slots, unscheduled, events}
var pfDeletedTrainings  = [];     // fwKeys des entraînements supprimés (envoyés au save)
var pfDrake             = null;   // instance Dragula
var pfShowBlason        = false;  // afficher les blasons SVG
var pfDirty             = false;  // modifications non sauvegardées
var pfUnschedCollapsed  = {};     // {evCode: bool} — groupes réduits dans "Non planifiés"
var pfZoom              = 100;    // niveau de zoom grille en % (50–150)

/* ============================================================
   Ajustement layout (le CSS flex gère la hauteur automatiquement)
   On nettoie seulement les styles inline que le template pourrait imposer.
   ============================================================ */
function pfAdjustLayout() {
    // Effacer toute hauteur inline résiduelle sur le layout
    var layout = document.querySelector('.pf-layout');
    if (layout) layout.style.height = '';
}

/* ============================================================
   Zoom de la grille (slider)
   Met à l'échelle les variables CSS de dimension + la police.
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

    // 1. Mettre à jour les variables CSS (utilisées par tous les éléments non-table)
    var r = document.documentElement;
    r.style.setProperty('--pf-col-slot',    wSlot);
    r.style.setProperty('--pf-col-target',  wTarget);
    r.style.setProperty('--pf-col-add',     wAdd);
    r.style.setProperty('--pf-row-h',       hRow);
    r.style.setProperty('--pf-wave-row-h',  hWave);
    r.style.setProperty('--pf-font-scale',  font);

    // 2. Forcer la largeur directement sur les <col> (table-layout:fixed
    //    ne réagit pas toujours au changement des variables CSS)
    document.querySelectorAll('col.pf-col-slot').forEach(function (c) {
        c.style.width = wSlot;
    });
    document.querySelectorAll('col.pf-col-target').forEach(function (c) {
        c.style.width = wTarget;
    });
    document.querySelectorAll('col.pf-col-add').forEach(function (c) {
        c.style.width = wAdd;
    });

    // 3. Mettre à jour le label et le slider
    var lbl    = document.getElementById('pfZoomLbl');
    var slider = document.getElementById('pfZoomSlider');
    if (lbl)    lbl.textContent = pfZoom + '%';
    if (slider) slider.value   = pfZoom;

    // 4. Persister dans localStorage
    try { localStorage.setItem('pfZoom', pfZoom); } catch (e) {}
	pfUniCellWidth();
}

/* ============================================================
   Initialisation
   ============================================================ */
$(function () {
    pfAdjustLayout();
    $(window).on('resize', pfAdjustLayout);
    // Restaurer le zoom depuis localStorage
    try {
        var savedZoom = parseInt(localStorage.getItem('pfZoom'), 10);
        if (savedZoom >= 50 && savedZoom <= 150) pfZoom = savedZoom;
    } catch (e) {}
    pfSetZoom(pfZoom);
    pfInitHover();
    pfLoad();
});

/* ============================================================
   Ajout / suppression d'entraînements
   ============================================================ */

/** Calcule la couleur rgba d'un bloc entraînement pour un evCode donné.
 *  Cherche d'abord une tuile existante du même event, sinon génère via hash. */
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
        // Bloc entraînement (rgba) → réutiliser directement
        if (existing.type === 'training') return existing.color;
        // Bloc phase (hex) → dériver la version rgba
        var hex = (existing.color + '').replace('#', '');
        if (hex.length === 6) {
            var r = parseInt(hex.slice(0,2), 16);
            var g = parseInt(hex.slice(2,4), 16);
            var b = parseInt(hex.slice(4,6), 16);
            return 'rgba(' + r + ',' + g + ',' + b + ',0.45)';
        }
    }
    // Fallback : hash déterministe sur l'evCode (miroir de PHP generatePastelColor)
    var h = 0;
    for (var j = 0; j < evCode.length; j++) {
        h = (((h << 5) - h) + evCode.charCodeAt(j)) | 0;
    }
    h = Math.abs(h);
    return 'rgba(' + (127 + (h % 128)) + ',' + (127 + ((h >>> 8) % 128)) + ',' + (127 + ((h >>> 16) % 128)) + ',0.45)';
}

/** Ouvre le modal de sélection d'épreuve pour ajouter un entraînement. */
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

/** Crée le bloc entraînement et l'ajoute aux non-planifiés. */
function pfAddTrainingConfirm() {
    var sel       = document.getElementById('pfTrainEvSelect');
    var evCode    = sel.value;
    var teamEvent = parseInt(sel.options[sel.selectedIndex].dataset.te) || 0;
    if (!evCode) return;

    // ID unique côté client (fwKey vide = nouveau → INSERT au save)
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
        fwKey:      ''   // vide → INSERT côté serveur au prochain save
    };
    pfData.unscheduled.push(block);
    pfDirty = true;
    pfCloseTrainModal();
    pfLoadUnscheduled();
    pfRefreshDragula();
}

/** Supprime définitivement un entraînement non-planifié. */
function pfDeleteTraining(blockId, ev) {
    if (ev && ev.stopPropagation) ev.stopPropagation();
    var idx = pfData.unscheduled.findIndex(function (b) { return b.id === blockId; });
    if (idx === -1) return;
    var block = pfData.unscheduled[idx];
    // Si fwKey non vide : marquer pour suppression en base au prochain save
    if (block.fwKey) {
        pfDeletedTrainings.push(block.fwKey);
    }
    pfData.unscheduled.splice(idx, 1);
    pfDirty = true;
    pfLoadUnscheduled();
    pfRefreshDragula();
}

/* ============================================================
   Mise en évidence des tuiles de même catégorie au survol
   ============================================================ */
function pfInitHover() {
    // Délégation sur document : survit aux pfRender() qui recrée les tuiles
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
        pfData = data;
        pfDeletedTrainings = [];   // réinitialiser la liste des suppressions après chargement
        pfNormalizeWaveBlocks();   // auto-séparer blocs multi-vague depuis la BDD
        $('#pfLoading').hide();
        pfRender();
        pfInitDragula();
        pfLoadUnscheduled();
    }).fail(function () {
        $('#pfLoading').text('Erreur lors du chargement des données.');
    });
}

/* ============================================================
   Normalisation des blocs multi-vague (chargement depuis BDD)
   Si un bloc a plus de matchs que de cibles uniques → split en sous-blocs vague
   ============================================================ */
function pfNormalizeWaveBlocks() {
    pfData.slots.forEach(function (slot) {
        var newBlocks = [];
        slot.blocks.forEach(function (blk) {
            if (blk.type !== 'phase' || !blk.matches || blk.matches.length === 0) {
                newBlocks.push(blk);
                return;
            }
            // Compter les cibles uniques effectivement assignées
            var targetSet = {};
            blk.matches.forEach(function (m) { if (m.target > 0) targetSet[m.target] = true; });
            var uniqueCount = Object.keys(targetSet).length;

            if (uniqueCount <= 1 || blk.matches.length <= uniqueCount) {
                // Pas de mode vague (besoin d'au moins 2 cibles distinctes pour créer des vagues)
                newBlocks.push(blk);
                return;
            }

            // Mode vague détecté.
            // On groupe les matchs PAR CIBLE (quelque soit leur ordre en BDD),
            // puis on entrelace : vague 0 = 1er match de chaque cible,
            //                     vague 1 = 2e match de chaque cible, etc.
            // Cela garantit 1 match par cible par vague, sans hypothèse sur l'ordre BDD.
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
   Rendu de la grille
   ============================================================ */
function pfRender() {
    var html = pfBuildTable();
    $('#pfGrid').html(html);
    pfInitSlotEditors();
    // Re-appliquer le zoom sur les <col> recréés par le rendu
    pfSetZoom(pfZoom);
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
		console.log("size: " + w);
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
           + 'Cible ' + targets[ci]
           + '<span class="pf-rm-col" onclick="pfRemoveTarget(' + ci + ')" title="Retirer cette colonne">✕</span>'
           + '</th>';
    }
    h += '<th class="pf-th-add"><input type="button" class="Button" value="+" onclick="pfAddTarget()" title="Ajouter une cible" style="padding:1px 4px;font-size:.8em;"></th>';
    h += '</tr></thead><tbody>';

    for (var si = 0; si < slots.length; si++) {
        h += pfBuildSlotRows(slots[si], si);
    }

    h += '</tbody></table>';
    return h;
}

/* Émet une ou plusieurs <tr> pour un créneau (une par vague) */
function pfBuildSlotRows(slot, slotIdx) {
    // Grouper les blocs par waveRow
    var waveGroups = {};
    slot.blocks.forEach(function (blk) {
        var wr = blk.waveRow || 0;
        if (!waveGroups[wr]) waveGroups[wr] = [];
        waveGroups[wr].push(blk);
    });

    var waveRowNums = Object.keys(waveGroups).map(Number).sort(function (a, b) { return a - b; });
    if (!waveRowNums.length) waveRowNums = [0];  // créneau vide : 1 ligne quand même

    // Respecter le nombre de vagues forcé manuellement (slot.waves)
    var forcedWaves = slot.waves || 1;
    while (waveRowNums.length < forcedWaves) {
        waveRowNums.push(waveRowNums.length);
    }
    var totalWaves = waveRowNums.length;

    var html = '';
    for (var wi = 0; wi < waveRowNums.length; wi++) {
        var wr = waveRowNums[wi];
        html += pfBuildWaveRow(slot, slotIdx, wr, wi, totalWaves, waveGroups[wr] || []);
    }
    return html;
}

/* Émet UN <tr> correspondant à une vague (waveIdx = position visuelle, 0-based) */
function pfBuildWaveRow(slot, slotIdx, waveRow, waveIdx, totalWaves, blocks) {
    var targets = pfData.targets;
    var n       = targets.length;
    var isMultiWave = totalWaves > 1;
    var waveLabels  = ['AB', 'CD', 'EF', 'GH'];

    // Calculer quelles cibles sont occupées par quels blocs (dans cette vague)
    var colMap  = new Array(n).fill(null);
    var skipCol = new Array(n).fill(false);

    for (var bi = 0; bi < blocks.length; bi++) {
        var blk = blocks[bi];
        var tl  = blk.targetList || [];
        if (!tl.length) {
            // Échauffement sans cibles assignées → ne pas afficher dans la grille
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

    // ---- Colonne horaire (seulement pour la 1re ligne de vague, avec rowspan) ----
    if (waveIdx === 0) {
        var rowspanAttr = totalWaves > 1 ? ' rowspan="' + totalWaves + '"' : '';
        h += '<td class="pf-slot-header" data-slot-idx="' + slotIdx + '"' + rowspanAttr + '>'
           + '<span class="pf-slot-rm-btn" onclick="pfRemoveSlot(' + slotIdx + ')" title="Supprimer ce créneau">✕</span>'
           + '<span class="pf-slot-clear-btn" onclick="pfClearSlotBlocks(' + slotIdx + ')" title="Vider la ligne (renvoyer les phases en non-planifiés)">⬇</span>'
		   + '<span class="pf-slot-insert-btn" onclick="pfInsertSlot(' + slotIdx + ')" title="Inséser un créneau">✚</span>'
           + '<div class="pf-slot-content">'
           +   '<div class="pf-slot-display">'
           +     '<span class="pf-slot-date">' + slot.date + '</span>'
           +     '<span class="pf-slot-timerange">'
           +       '<span>' + slot.time + '</span>'
           +       '<span>–</span>'
           +       '<span>' + endTime + '</span>'
           +     '</span>'
           +     '<span class="pf-slot-edit-btn" onclick="pfToggleSlotEdit(this, ' + slotIdx + ')">modifier</span>'
           +   '</div>'
           +   '<div class="pf-slot-inputs">'
           +     '<div><label style="font-size:.8em;">Date :</label>'
           +     '<input type="date" class="pf-in-date" value="' + slot.date + '"></div>'
           +     '<div><label style="font-size:.8em;">Heure :</label>'
           +     '<input type="time" class="pf-in-time" value="' + slot.time + '"></div>'
           +     '<div><label style="font-size:.8em;">Durée :</label>'
           +     '<input type="number" class="pf-in-dur" value="' + slot.duration + '" min="1" max="300" style="width:40px;"> min</div>'
           +     '<div><button onclick="pfApplySlotEdit(this, ' + slotIdx + ')">OK</button>'
           +     ' <button onclick="pfCancelSlotEdit(this)">✕</button></div>'
           +   '</div>'
           + '</div>';
        // Bande verticale de vague (positionnée absolument sur le bord droit de la cellule)
        var stripTitle = isMultiWave ? 'Réduire à 1 vague' : 'Passer en 2 vagues';
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

    // ---- Colonnes cibles ----
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

    // Colonne bouton + (vide, pour correspondre au th "Ajouter cible")
    h += '<td class="pf-cell pf-cell-add"></td>';

    h += '</tr>';
    return h;
}

function pfBuildTile(block, tileId, slotIdx, segIdx, segInfo) {
    var color   = block.color;
    var textClr = pfContrastColor(color);
    var waveLabels = ['AB', 'CD', 'EF', 'GH'];
    var isWaveBlk  = block.baseBlockId !== undefined && block.baseBlockId !== '';

    var h = '<div class="pf-tile" id="' + tileId + '"'
          + ' data-block-id="'  + pfEsc(block.id) + '"'
          + ' data-slot-idx="'  + slotIdx + '"'
          + ' data-seg-idx="'   + (segIdx || 0) + '"'
          + ' data-team-event="' + block.teamEvent + '"'
          + ' data-pf-event="'  + pfEsc(block.event) + '"'
          + ' style="background:' + color + '; color:' + textClr + ';">'
          + '<div class="pf-tile-hdr">';

    // Boutons dans l'en-tête
    h += '<span class="pf-tile-rm" onclick="pfRemoveTile(\'' + pfEsc(tileId) + '\',event)" title="Retirer cette phase">✕</span>';
    // Bouton segment uniquement pour les blocs non-vague
    if (block.type === 'phase' && !isWaveBlk) {
        h += '<span class="pf-tile-seg" onclick="pfSegmentTile(\'' + pfEsc(block.id) + '\',' + slotIdx + ',event)" title="Segmenter">⊕</span>';
    }

    // Titre + badge vague
    var waveTag = isWaveBlk
        ? ' <span class="pf-wave-tag">' + (waveLabels[block.waveRow || 0] || '') + '</span>'
        : '';
    var label = block.type === 'training'
        ? ('<span class="pf-tile-evcode">' + block.event + '</span>'
         + '<span class="pf-tile-phase">Échauffement</span>')
        : ('<span class="pf-tile-evcode">' + block.event + '</span>'
         + '<span class="pf-tile-phase">' + block.phaseName + '</span>');
    h += label + waveTag;
    h += '</div>';

    // Zone blason
    h += '<div class="pf-tile-svg"><img src="' + PF_SVG + 'Empty.svg" alt="" style="max-height:22px;"></div>';

    // Boutons étendre/réduire pour les blocs entraînement
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

    // Corps : matches alignés sur leurs colonnes cibles
    if (block.type === 'phase' && block.matches && block.matches.length) {
        var span     = (segInfo && segInfo.span)          ? segInfo.span     : 1;
        var startCol = (segInfo && segInfo.startCol != null) ? segInfo.startCol : 0;

        // Filtrer les matches de ce segment
        var segs = block._segments || [];
        var seg  = segs[segIdx || 0] || null;
        var matchesToShow = block.matches;
        if (seg) {
            var filtered = block.matches.filter(function (m) {
                return m.target >= seg.startTarget && m.target <= seg.endTarget;
            });
            if (filtered.length) matchesToShow = filtered;
        }

        // Construire un tableau indexé par colonne relative
        var colSlots = new Array(span);
        for (var si2 = 0; si2 < span; si2++) { colSlots[si2] = []; }

        var isTeam    = parseInt(block.teamEvent) === 1;
        var anyPlaced = false;

        if (block.twoPerTarget === false && !isTeam) {
            // 1 archer par cible : pos1 dans la colonne canonique (match.target),
            // pos2 dans la colonne miroir adjacente (match.target + 1).
            matchesToShow.forEach(function (m) {
                var colIdx = pfData.targets.indexOf(m.target);
                var rel    = colIdx - startCol;
                if (rel >= 0 && rel < span) {
                    colSlots[rel].push({ pos1: m.pos1, _solo: true });
                    anyPlaced = true;
                }
                // Miroir = colonne suivante
                var relMirror = rel + 1;
                if (relMirror >= 0 && relMirror < span) {
                    colSlots[relMirror].push({ pos1: m.pos2, _solo: true });
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

        // Si aucune cible assignée → répartir équitablement
        if (!anyPlaced) {
            var step = Math.max(1, Math.floor(span / matchesToShow.length));
            matchesToShow.forEach(function (m, mi) {
                var rel = Math.min(mi * step, span - 1);
                colSlots[rel].push(m);
            });
        }

        h += '<div class="pf-tile-body">';
        for (var sc = 0; sc < span; sc++) {
            h += '<div class="pf-tile-slot">';
            for (var mi = 0; mi < colSlots[sc].length; mi++) {
                var m = colSlots[sc][mi];
                if (isTeam || m._solo) {
                    // Équipe ou "1 archer/cible" : une seule position par colonne
                    h += '<span class="pf-pos pf-pos-solo">' + (m.pos1 || '?') + '</span>';
                } else {
                    h += '<span class="pf-match-box">'
                       + '<span class="pf-pos">' + (m.pos1 || '?') + '</span>'
                       + '<span class="pf-pos-sep">⚔</span>'
                       + '<span class="pf-pos">' + (m.pos2 || '?') + '</span>'
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

            // Zones non-planifiées : toujours accepter (plusieurs tuiles possibles)
            var targetSlot = parseInt(target.getAttribute('data-slot-idx'), 10);
            if (targetSlot === -1) return true;

            // Cellule de grille : refuser si déjà occupée par une autre tuile
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
        pfStatus('Modifications non sauvegardées', '');
    });

    pfDrake.on('drag', function (el) {
        el.setAttribute('data-dragging', '1');
    });
}

function pfRefreshDragula() {
    pfInitDragula();
}

/* ============================================================
   Récupérer un bloc (avec fusion des sous-blocs vague)
   Retire le(s) bloc(s) de leur emplacement et retourne un bloc fusionné.
   ============================================================ */
function pfFetchBlock(blockId, slotIdx) {
    var foundBlk    = null;
    var foundInSlot = null;

    // Chercher dans le slot indiqué
    if (slotIdx >= 0 && pfData.slots[slotIdx]) {
        var slot = pfData.slots[slotIdx];
        var idx  = slot.blocks.findIndex(function (b) { return b.id === blockId; });
        if (idx >= 0) { foundBlk = slot.blocks[idx]; foundInSlot = slot; }
    }

    // Chercher dans unscheduled si non trouvé
    if (!foundBlk) {
        var ui = pfData.unscheduled.findIndex(function (b) { return b.id === blockId; });
        if (ui >= 0) foundBlk = pfData.unscheduled[ui];
    }

    if (!foundBlk) return null;

    // Détecter si le bloc est un sous-bloc de VAGUE (pattern _wN ou id === baseBlockId).
    // UNIQUEMENT les blocs de vague doivent être regroupés par baseBlockId.
    // Tous les autres (segments _sN, blocs originaux) sont récupérés individuellement.
    var waveSubRx      = /^.+_w\d+$/;
    var isWaveSubBlock = waveSubRx.test(foundBlk.id);
    var isWaveMain     = !isWaveSubBlock && foundBlk.baseBlockId
                         && foundBlk.id === foundBlk.baseBlockId;
    var isWaveBlock    = isWaveSubBlock || isWaveMain;

    var baseId = isWaveBlock ? foundBlk.baseBlockId : foundBlk.id;

    // Rassembler les siblings et les retirer de leur emplacement
    var siblings = [];

    if (foundInSlot) {
        var remaining = [];
        foundInSlot.blocks.forEach(function (b) {
            var match = isWaveBlock
                // vague : regrouper tous les sous-blocs partageant le même baseBlockId
                ? (b.baseBlockId === baseId && (b.id === baseId || waveSubRx.test(b.id)))
                // non-vague : uniquement le bloc lui-même
                : (b.id === foundBlk.id);
            if (match) siblings.push(b);
            else remaining.push(b);
        });
        foundInSlot.blocks = remaining;
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

    // Trier par waveRow pour recombiner dans l'ordre
    siblings.sort(function (a, b) { return (a.waveRow || 0) - (b.waveRow || 0); });

    // Fusionner tous les matches
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
   Déplacer un bloc
   ============================================================ */
function pfMoveBlock(blockId, oldSlotIdx, segIdx, newSlotIdx, newColIdx, newWaveRow) {
    var dstSlot = pfData.slots[newSlotIdx];
    if (!dstSlot) return;
    if (isNaN(newWaveRow)) newWaveRow = 0;

    // === Cas spécial : bloc multi-segments sur la grille → ne déplacer que le segment cliqué ===
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

                // Matches de ce segment uniquement
                var movMatches = (blkInSlot.matches || []).filter(function (m) { return oldSegSet[m.target]; });
                for (var mi2 = 0; mi2 < movMatches.length; mi2++) {
                    movMatches[mi2].target = newSegTgts[mi2 % newSegTgts.length];
                }

                if (newSlotIdx === oldSlotIdx) {
                    // Même créneau : mise à jour directe de targetList
                    blkInSlot.targetList = (blkInSlot.targetList || [])
                        .filter(function (t) { return !oldSegSet[t]; })
                        .concat(newSegTgts);
                    blkInSlot.targetList.sort(function (a, b) { return pfData.targets.indexOf(a) - pfData.targets.indexOf(b); });
                } else {
                    // Créneau différent : extraire le segment, l'ajouter dans la destination
                    var blkTpl = JSON.parse(JSON.stringify(blkInSlot));
                    blkInSlot.matches    = (blkInSlot.matches || []).filter(function (m) { return !oldSegSet[m.target]; });
                    blkInSlot.targetList = (blkInSlot.targetList || []).filter(function (t) { return !oldSegSet[t]; });
                    if (!blkInSlot.matches.length) {
                        srcSlot.blocks.splice(srcSlot.blocks.indexOf(blkInSlot), 1);
                    }
                    // Ajouter ou fusionner dans le créneau de destination
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

    // === Cas standard : bloc single-segment ou depuis non-planifiés ===

    // Récupérer le bloc fusionné (retire les siblings de leur source)
    var blk = pfFetchBlock(blockId, oldSlotIdx);
    if (!blk) return;

    // Nouvelle date/heure
    blk._newDate = dstSlot.date;
    blk._newTime = dstSlot.time;

    // Recalculer le span
    var segs, seg, span;
    if (blk.type === 'training') {
        // Conserver le span existant si déjà placé (targetList), sinon 1 par défaut
        span = (blk.targetList && blk.targetList.length) ? blk.targetList.length : 1;
    } else {
        var hasTL = blk.targetList && blk.targetList.length > 0;
        if (hasTL) {
            // Bloc avec cibles connues : utiliser les segments existants
            segs = blk._segments || pfGetContiguousSegments(blk.targetList, pfData.targets);
            seg  = segs[segIdx] || null;
            span = seg ? seg.span : 1;
        } else {
            // Pas de cibles assignées (bloc fusionné / non-planifié) :
            // ignorer _segments (stale), span = nombre de matches
            // × 2 si "1 archer par cible" (chaque match occupe 2 colonnes adjacentes)
            var matchCount = (blk.matches && blk.matches.length) || 1;
            span = blk.twoPerTarget === false ? matchCount * 2 : matchCount;
        }
    }

    // Cibles de destination
    var newTargets = pfData.targets.slice(newColIdx, newColIdx + span);
    if (!newTargets.length) newTargets = [pfData.targets[newColIdx] || 1];

    if (blk.type === 'phase' && blk.matches) {
        // Déterminer les matches de ce segment
        var segDef = segs ? (segs[segIdx] || null) : null;
        var segMatches = (segDef && segDef.startTarget != null && segDef.endTarget != null)
            ? blk.matches.filter(function (m) {
                return m.target >= segDef.startTarget && m.target <= segDef.endTarget;
              })
            : blk.matches;
        if (!segMatches.length) segMatches = blk.matches;

        // Assigner les cibles.
        // Pour "1 archer par cible" (twoPerTarget=false) :
        //   chaque match canonique → colonne paire (0, 2, 4…), la colonne impaire étant
        //   réservée au miroir iAnseo (écrit par le serveur lors du save).
        // Pour "2 archers par cible" (twoPerTarget=true) : assignement habituel (mi % span).
        for (var mi = 0; mi < segMatches.length; mi++) {
            var tgtIdx = (blk.twoPerTarget === false) ? (mi * 2) : (mi % newTargets.length);
            segMatches[mi].target = newTargets[tgtIdx % newTargets.length] || newTargets[0];
        }

        // Nombre de vagues nécessaires
        // On ne crée des sous-lignes de vague que si span > 1 (plusieurs cibles physiques)
        var waveCount = (span > 1) ? Math.ceil(segMatches.length / span) : 1;

        if (waveCount > 1) {
            // Mode vague : créer waveCount sous-blocs
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
            // Pas de vague : bloc unique dans la ligne de vague cible
            blk.waveRow = newWaveRow;
            // Pour twoPerTarget=false (1 archer/cible), chaque match canonique occupe 2 colonnes
            // adjacentes (la sienne + celle du miroir iAnseo). newTargets a déjà été calculé
            // avec span×2, donc on l'utilise directement comme targetList.
            if (blk.twoPerTarget === false) {
                blk.targetList = newTargets.slice();   // [col, col+1] déjà calculé
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
   Gestion des créneaux (lignes)
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
        if (!confirm('Ce créneau contient des phases. Confirmer la suppression ?')) return;

        // Grouper les siblings de vague et fusionner avant de renvoyer en non-planifiés
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

/* Vider tous les blocs d'un créneau et les renvoyer en non-planifiés
   (le créneau lui-même est conservé, vide) */
function pfClearSlotBlocks(slotIdx) {
    var slot = pfData.slots[slotIdx];
    if (!slot || slot.blocks.length === 0) return;

    // Grouper les siblings de vague par baseBlockId et fusionner avant envoi en non-planifiés
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
    slot.waves  = 1;   // réinitialiser le mode vague (créneau vide → 1 vague)
    pfDirty = true;
    pfRender();
    pfRefreshDragula();
    pfLoadUnscheduled();
}

function pfToggleSlotWaves(slotIdx) {
    var slot = pfData.slots[slotIdx];
    if (!slot) return;

    // Nombre de vagues effectivement actives
    var currentWaves = Math.max(
        slot.waves || 1,
        slot.blocks.some(function (b) { return (b.waveRow || 0) > 0; }) ? 2 : 1
    );

    if (currentWaves >= 2) {
        // Réduire à 1 vague : vérifier que la vague CD est vide
        var hasWave1 = slot.blocks.some(function (b) { return (b.waveRow || 0) > 0; });
        if (hasWave1) {
            alert('Retirez les blocs de la vague CD avant de repasser en 1 vague.');
            return;
        }
        slot.waves = 1;
    } else {
        slot.waves = 2;
    }

    // Cascader les slots suivants si demandé (même approche par index que pfApplySlotEdit)
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

/** Étend ou réduit d'une cible un bloc entraînement (delta = +1 ou -1) */
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
        // Étendre : ajouter la cible suivante à droite
        var lastTgt = tl.length ? tl[tl.length - 1] : null;
        var lastIdx = lastTgt !== null ? allTargets.indexOf(lastTgt) : -1;
        if (lastIdx < 0 || lastIdx >= allTargets.length - 1) return;
        tl.push(allTargets[lastIdx + 1]);
    } else {
        // Réduire : supprimer la cible la plus à droite (minimum 1)
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

    // Delta = différence entre ancienne fin et nouvelle fin (heure début + durée × vagues)
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

/** Retourne le nombre effectif de vagues d'un créneau (forcé ou détecté) */
function pfSlotWaveCount(slot) {
    var forced = slot.waves || 1;
    var actual = (slot.blocks || []).some(function (b) { return (b.waveRow || 0) > 0; }) ? 2 : 1;
    return Math.max(forced, actual);
}

/** Lit les 4 valeurs du panneau de config */
function pfGetConfig() {
    return {
        equipeEchauff: parseInt($('#cfgEquipeEchauff').val(), 10) || 15,
        equipeMatch:   parseInt($('#cfgEquipeMatch').val(),   10) || 30,
        indivEchauff:  parseInt($('#cfgIndivEchauff').val(),  10) || 5,
        indivMatch:    parseInt($('#cfgIndivMatch').val(),    10) || 30
    };
}

/** Retourne la durée cible (1 vague) d'un slot selon ses blocs, null si indéterminé */
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

/** Appelé quand une valeur de config durée change */
function pfApplyConfigDuration() {
    if (!pfData || !pfData.slots) return;
    var cfg       = pfGetConfig();
    var autoShift = $('#chkAutoShift').is(':checked');
    console.log('[pfApplyConfigDuration] cfg=', JSON.stringify(cfg), 'autoShift=', autoShift);

    // Ordre chronologique des slots
    var order = pfData.slots.map(function (s, i) { return i; });
    order.sort(function (a, b) {
        var sa = pfData.slots[a], sb = pfData.slots[b];
        return pfTimeDiffMinutes(sb.date + ' ' + sb.time, sa.date + ' ' + sa.time);
    });
    console.log('[pfApplyConfigDuration] order=', order, 'slots=', pfData.slots.map(function(s){ return s.date+' '+s.time+' dur='+s.duration; }));

    order.forEach(function (si, pos) {
        var slot      = pfData.slots[si];
        var targetDur = pfGetSlotTargetDuration(slot, cfg);
        var oldDur    = parseInt(slot.duration, 10) || 0;   // toujours number
        console.log('[pfApplyConfigDuration] pos='+pos+' si='+si+' time='+slot.time+' oldDur='+oldDur+' targetDur='+targetDur+' blocks='+slot.blocks.length);
        if (targetDur === null || targetDur === oldDur) return;

        var waves = pfSlotWaveCount(slot);
        var delta = (targetDur - oldDur) * waves;
        slot.duration = targetDur;
        console.log('[pfApplyConfigDuration] -> delta='+delta+' waves='+waves+', cascade '+( order.length - pos - 1)+' slots');

        if (autoShift && delta !== 0) {
            for (var j = pos + 1; j < order.length; j++) {
                var prev = pfData.slots[order[j]].time;
                pfShiftSlot(pfData.slots[order[j]], delta);
                console.log('[pfApplyConfigDuration]   shift slot '+order[j]+' from '+prev+' to '+pfData.slots[order[j]].time);
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
   Gestion des cibles (colonnes)
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
        if (!confirm('La cible ' + tgt + ' est utilisée par des phases. Confirmer la suppression de la colonne ?')) return;
    }
    pfData.targets.splice(colIdx, 1);
    pfDirty = true;
    pfRender();
    pfRefreshDragula();
}

/* ============================================================
   Retirer un bloc par son id (depuis drop sur zone non-planifiée)
   ============================================================ */
function pfRemoveTileById(blockId, slotIdx) {
    var blk = pfFetchBlock(blockId, slotIdx);
    if (!blk) return;

    blk.targetList = [];
    if (blk.type === 'phase') blk.matches && blk.matches.forEach(function (m) { m.target = 0; });

    pfData.unscheduled.push(blk);
    pfRender();
    pfRefreshDragula();
    pfLoadUnscheduled();
}

/* ============================================================
   Helper : ajoute des matches à un bloc non-planifié existant
   (même blockId) ou crée une nouvelle entrée fusionnée.
   ============================================================ */
function pfAddToUnscheduled(blockId, blkTemplate, matches) {
    // Chercher une entrée existante avec le même id de base
    var existing = null;
    for (var ui = 0; ui < pfData.unscheduled.length; ui++) {
        if (pfData.unscheduled[ui].id === blockId) {
            existing = pfData.unscheduled[ui];
            break;
        }
    }
    if (existing) {
        // Fusionner les matches dans l'entrée existante
        matches.forEach(function (m) {
            existing.matches.push({ matchNo: m.matchNo, pos1: m.pos1, pos2: m.pos2, target: 0 });
        });
        existing.matches.sort(function (a, b) { return a.matchNo - b.matchNo; });
    } else {
        // Créer une nouvelle entrée avec l'id d'origine (pas _r0, _r1…)
        var unBlk = JSON.parse(JSON.stringify(blkTemplate));
        unBlk.id         = blockId;
        unBlk.matches    = matches.map(function (m) {
            return { matchNo: m.matchNo, pos1: m.pos1, pos2: m.pos2, target: 0 };
        });
        unBlk.targetList = [];
        delete unBlk.baseBlockId;
        delete unBlk._segments;
        pfData.unscheduled.push(unBlk);
    }
}

/* ============================================================
   Retirer une tuile (bouton ✕ sur la tuile)
   ============================================================ */
function pfRemoveTile(tileId, e) {
    e && e.stopPropagation();
    var el = document.getElementById(tileId);
    if (!el) return;
    var blockId = el.getAttribute('data-block-id');
    var slotIdx = parseInt(el.getAttribute('data-slot-idx'), 10);
    var segIdx  = parseInt(el.getAttribute('data-seg-idx') || '0', 10);

    // Trouver le bloc sans le retirer (pour tester s'il a plusieurs segments)
    var slot = pfData.slots[slotIdx];
    if (!slot) return;
    var blkRef = null;
    for (var bi = 0; bi < slot.blocks.length; bi++) {
        if (slot.blocks[bi].id === blockId) { blkRef = slot.blocks[bi]; break; }
    }
    if (!blkRef) return;

    var segs = pfGetContiguousSegments(blkRef.targetList || [], pfData.targets);

    if (segs.length > 1 && blkRef.type === 'phase') {
        // Bloc multi-segments (targetList non-contigu) → ne retirer que le segment cliqué
        var seg = segs[segIdx] || segs[0];

        // Cibles appartenant à ce segment visuel
        var segTgts = {};
        pfData.targets.slice(seg.startCol, seg.startCol + seg.span).forEach(function (t) {
            segTgts[t] = true;
        });

        // Séparer les matches : ceux du segment cliqué → unscheduled, les autres → restent
        var removedMatches  = [];
        var remainingMatches = [];
        (blkRef.matches || []).forEach(function (m) {
            if (segTgts[m.target]) removedMatches.push(m);
            else remainingMatches.push(m);
        });

        // Mettre à jour le bloc en place
        blkRef.targetList = (blkRef.targetList || []).filter(function (t) { return !segTgts[t]; });
        blkRef.matches    = remainingMatches;
        if (!blkRef.matches.length) {
            slot.blocks.splice(slot.blocks.indexOf(blkRef), 1);
        }

        // Fusionner le segment retiré dans le bloc non-planifié (ou créer)
        if (removedMatches.length) {
            pfAddToUnscheduled(blockId, blkRef, removedMatches);
        }
    } else {
        // Bloc à segment unique → retirer le bloc entier
        var blk = pfFetchBlock(blockId, slotIdx);
        if (!blk) return;
        blk.targetList = [];
        if (blk.type === 'phase') {
            (blk.matches || []).forEach(function (m) { m.target = 0; });
            pfAddToUnscheduled(blockId, blk, blk.matches || []);
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
   Segmentation : 1 bloc par match, sans popup
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
    if (nMatches < 2) { return; }  // déjà 1 seul match, rien à faire

    var tl = blk.targetList || [];
    var T  = tl.length;
    var N  = nMatches;

    // Distribuer les cibles entre les segments (répartition équitable)
    // Segment i reçoit les cibles de l'index [i*T/N … (i+1)*T/N - 1]
    var newBlocks = blk.matches.map(function (m, i) {
        var seg = JSON.parse(JSON.stringify(blk));
        seg.id      = blk.id + '_s' + i;
        seg.matches = [JSON.parse(JSON.stringify(m))];
        // Supprimer baseBlockId hérité d'une vague précédente :
        // sinon pfFetchBlock utiliserait baseBlockId pour grouper TOUS les segments
        delete seg.baseBlockId;
        delete seg._segments;

        var from = Math.round(i * T / N);
        var to   = Math.round((i + 1) * T / N);
        seg.targetList = (T > 0) ? tl.slice(from, to) : [];

        return seg;
    });

    // Remplacer le bloc original par les segments
    slot.blocks.splice(blkIdx, 1);
    newBlocks.forEach(function (seg) { slot.blocks.push(seg); });

    pfDirty = true;
    pfRender();
    pfRefreshDragula();
    pfLoadUnscheduled();
}

/* ============================================================
   Fusion automatique des segments tous non-planifiés
   ============================================================ */
function pfMergeUnscheduledSegments() {
    var segRx = /^(.+)_s\d+$/;

    // Grouper les segments non-planifiés par base ID
    var groups = {};
    pfData.unscheduled.forEach(function (b) {
        var m = segRx.exec(b.id);
        if (!m) return;
        var baseId = m[1];
        if (!groups[baseId]) groups[baseId] = [];
        groups[baseId].push(b);
    });

    Object.keys(groups).forEach(function (baseId) {
        // Vérifier qu'aucun segment de ce baseId n'est encore sur la grille
        var stillOnGrid = pfData.slots.some(function (slot) {
            return slot.blocks.some(function (b) {
                var m = segRx.exec(b.id);
                return m && m[1] === baseId;
            });
        });
        if (stillOnGrid) return;

        var segs = groups[baseId];
        if (segs.length < 2) return;  // un seul segment, rien à fusionner

        // Trier par index de segment (_s0, _s1, …)
        segs.sort(function (a, b) {
            return parseInt(a.id.replace(/^.+_s/, ''), 10)
                 - parseInt(b.id.replace(/^.+_s/, ''), 10);
        });

        // Fusionner : recombiner tous les matches dans le premier bloc
        var merged = JSON.parse(JSON.stringify(segs[0]));
        merged.id         = baseId;
        merged.targetList = [];
        merged.matches    = [];
        segs.forEach(function (s) {
            (s.matches || []).forEach(function (m) { merged.matches.push(m); });
        });

        // Retirer tous les segments et ajouter le bloc fusionné
        pfData.unscheduled = pfData.unscheduled.filter(function (b) {
            var m = segRx.exec(b.id);
            return !(m && m[1] === baseId);
        });
        pfData.unscheduled.push(merged);
        pfDirty = true;
    });
}

/* ============================================================
   Blocs non planifiés — groupement repliable par catégorie
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
    pfMergeUnscheduledSegments();   // regrouper si tous les segments sont non-planifiés
    var list = pfData.unscheduled;
    var html = '';

    if (!list.length) {
        html = '<em style="color:#999;font-size:.78em;">Aucun bloc non planifié</em>';
    } else {
        // Grouper par code événement en conservant l'ordre d'apparition
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

                // Ajouter l'identifiant des matches pour les segments (≤ 3 matches)
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
   Affichage des blasons
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
        // Supprimer préfixe, suffixes de vague (_w1…), de segment (_seg2…) et de phase (_32…)
        var evCode = blockId
            .replace(/^phase_/, '')
            .replace(/(_w\d+|_seg\d+)*$/, '')
            .replace(/_\d+$/, '');
        var svg = eventFaces[evCode] || 'Empty.svg';
        $(this).find('.pf-tile-svg img').attr('src', PF_SVG + svg);
    });
}

/* ============================================================
   Sauvegarde
   ============================================================ */
function pfSave() {
    pfStatus('Enregistrement...', '');
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
                pfStatus('Enregistré ✔', 'ok');
                if (resp.errors && resp.errors.length) {
                    pfStatus('Enregistré avec ' + resp.errors.length + ' avertissement(s)', 'error');
                }
            } else {
                pfStatus('Erreur : ' + (resp.error || 'inconnue'), 'error');
            }
        },
        error: function () {
            $('#btnSave').prop('disabled', false);
            pfStatus('Erreur réseau', 'error');
        }
    });
}

function pfStatus(msg, cls) {
    var el = $('#pfStatus');
    el.text(msg).removeClass('ok error');
    if (cls) el.addClass(cls);
}

/* ============================================================
   Éditeur inline créneaux (initialisation après rendu)
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
   Utilitaires
   ============================================================ */

/** Calcule les segments contigus dans la liste de cibles par rapport aux colonnes */
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

/** ID unique pour un tile dans le DOM */
function pfTileId(slotIdx, blockId, segIdx) {
    return 'tile_' + slotIdx + '_' + pfEsc(blockId) + '_' + (segIdx || 0);
}

/** Échappe pour attribut HTML */
function pfEsc(str) {
    return (str + '').replace(/[^a-zA-Z0-9_\-]/g, '_');
}

/** Échappe pour texte HTML */
function pfEscHtml(str) {
    return (str + '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/** Couleur de texte contrastée (noir ou blanc) selon la luminosité du fond.
 *  Accepte '#rrggbb' et 'rgba(r,g,b,a)'. */
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

/** Additionne des minutes à HH:MM */
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

/** Différence en minutes entre deux dateTime "YYYY-MM-DD HH:MM" */
function pfTimeDiffMinutes(dt1, dt2) {
    var a = new Date(dt1.replace(' ', 'T') + ':00');
    var b = new Date(dt2.replace(' ', 'T') + ':00');
    return Math.round((b - a) / 60000);
}

/* ============================================================
   Impression
   ============================================================ */
function pfPrint() {
    var gridEl = document.getElementById('pfGrid');
    if (!gridEl) return;

    // Titres depuis la page principale
    var titleParts = [];
    document.querySelectorAll('table.Tabella .Title').forEach(function (el) {
        var t = el.textContent.trim();
        if (t) titleParts.push(t);
    });
    var titleHtml = titleParts.length
        ? titleParts.map(function (t) { return '<div>' + t + '</div>'; }).join('')
        : '<div>Plan de cible — Finales</div>';

    // Mesurer la largeur réelle de la table MAINTENANT (dans la fenêtre principale, déjà rendue)
    // puis calculer le zoom pour qu'elle tienne en A4 paysage (281mm utiles ≈ 1062px à 96dpi)
    var tblMain = gridEl.querySelector('table.pf-table');
    var tableW  = tblMain ? tblMain.scrollWidth : 0;
    var PF_PRINT_WIDTH = 1062; // largeur utile A4 landscape 8mm marges
    var zoom = (tableW > PF_PRINT_WIDTH) ? (PF_PRINT_WIDTH / tableW) : 1;

    // Récupérer tous les liens CSS déjà chargés dans la page
    var cssLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(function (l) { return '<link rel="stylesheet" href="' + l.href + '">'; })
        .join('\n');

    var printStyles = [
        '@page { size: A4 landscape; margin: 8mm; }',
        /* Forcer les couleurs de fond même si "Graphiques d'arrière-plan" est décoché */
        '*, *::before, *::after {',
        '  print-color-adjust: exact !important;',
        '  -webkit-print-color-adjust: exact !important;',
        '}',
        /* Fond blanc — écrase le fond bleu du template */
        'html, body { background: #fff !important; color: #000 !important;'
            + ' margin: 0; padding: 0; font-size: 10px; font-family: sans-serif;'
            + ' zoom: ' + zoom + '; }',
        /* Titre centré et grand */
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
        /* Grille visible : bordures sur toutes les cellules */
        'table.pf-table { border-collapse: collapse !important; }',
        'table.pf-table td, table.pf-table th { border: 1px solid #aaa !important; }',
        /* En-têtes de cibles : fond sombre, texte blanc */
        'table.pf-table th.pf-th-target { background: #1e3a5a !important; color: #fff !important; }',
        /* En-têtes de créneau : léger fond gris */
        'table.pf-table td.pf-slot-header { background: #f0f4f8 !important; }',
        /* Cellules vides : fond très clair pour contraster avec les tuiles */
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
      + '<title>Plan Finales — Impression</title>'
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

/* Avertir si on quitte avec des modifications non sauvegardées */
window.addEventListener('beforeunload', function (e) {
    if (pfDirty) {
        e.preventDefault();
        e.returnValue = 'Des modifications non sauvegardées seront perdues.';
    }
});
