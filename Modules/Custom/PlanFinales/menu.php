<?php

if (!empty($on) && (subFeatureAcl($acl, AclCompetition, 'cSchedule') >= AclReadOnly) && ($_SESSION['MenuFinIDo'] || $_SESSION['MenuFinTDo'])) {
    if (!isset($ret['MODS']['Tools'])) {
        $ret['MODS']['Tools'][] = 'Outils';
    }
    $ret['MODS']['Tools'][] = 'Plan des Finales' . '|' . $CFG->ROOT_DIR . 'Modules/Custom/PlanFinales/';
}
