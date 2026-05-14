<?php
if(!empty($on)) {
    if (subFeatureAcl($acl, AclParticipants, 'pTarget') == AclReadWrite) {
        if (isset($ret['PART']['TARG'])) {
            array_splice($ret['PART']['TARG'], 3, 0, get_text('MenuLM_DragDropTarget') .'|'.$CFG->ROOT_DIR.'Modules/DragDropTarget/Qualification/index.php');
        }
    }
    if (subFeatureAcl($acl, AclCompetition,'cSchedule') == AclReadWrite) {
        if (isset($ret['COMP']['FINI'])) {
            array_splice($ret['COMP']['FINI'], 5, 0, get_text('MenuLM_DragDropTarget') .'|'.$CFG->ROOT_DIR.'Modules/DragDropTarget/Final/index.php');
        }
        if (isset($ret['COMP']['FINT'])) {
            array_splice($ret['COMP']['FINT'], 4, 0, get_text('MenuLM_DragDropTarget') .'|'.$CFG->ROOT_DIR.'Modules/DragDropTarget/Final/index.php');
        }
    }
}
