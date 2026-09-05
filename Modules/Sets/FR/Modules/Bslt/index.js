

function CalcQuals(enId) {
    
    var dist = 1;
   
    if (!enId) return;

    // Récupérer les valeurs
    var qu1   = parseInt(document.getElementById('d_Qu_1_' + enId)?.value) || 0;
    var qu2   = parseInt(document.getElementById('d_Qu_2_' + enId)?.value) || 0;
    var xnine = parseInt(document.getElementById('d_QuD' + dist + 'Xnine_' + enId)?.value) || 0;
    var gold  = parseInt(document.getElementById('d_QuD' + dist + 'Gold_' + enId)?.value) || 0;

    // Calcul arrows manquantes : 40 - (qu1 + qu2 + xnine + gold)
    var arrow = qu1 + qu2 + xnine + gold;
    
    
    var arrowField = document.getElementById('d_QuArrow_' + enId);
    if (arrowField) arrowField.value = arrow;

    // Calcul score : qu1 + (qu2 * 2) + (xnine * 3) + (gold * 4)
    var score = qu1 + (qu2 * 2) + (xnine * 3) + (gold * 4);
    var scoreField = document.getElementById('d_QuD' + dist + 'Score_' + enId);
    if (scoreField) scoreField.value = score;

    // Validation : arrow doit être >= 0 et <= 40
    var row = document.getElementById('Row_' + enId);
    if (row) {
        if (arrow < 0 || arrow > 40) {
            row.style.outline = '2px solid red';
            var btnId = 'force_btn_' + enId;
            if (!document.getElementById(btnId)) {
                var scoreCell = scoreField.parentElement;
                var forceBtn = document.createElement('button');
                forceBtn.id = btnId;
                forceBtn.type = 'button';
                forceBtn.className = 'btn btn-sm btn-warning';
                forceBtn.textContent = 'Forcer';
                forceBtn.style.marginLeft = '5px';
                forceBtn.onclick = function() {
                    ForceUpdate(enId);
                };
                scoreCell.appendChild(forceBtn);
            }
        } else {
           ForceUpdate(enId);
        }
    }
}

function ForceUpdate(enId) {
    var row = document.getElementById('Row_' + enId);
    if (row) row.style.outline = '';
    
    var btnId = 'force_btn_' + enId;
    var btn = document.getElementById(btnId);
    if (btn) btn.remove();
    
    UpdateQuals('d_QuArrow_' + enId);
    UpdateQuals('d_QuD1Score_' + enId);
    UpdateQuals('d_QuD1Xnine_' + enId);
    UpdateQuals('d_QuD1Gold_' + enId);
    
}

