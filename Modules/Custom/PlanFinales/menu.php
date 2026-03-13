<?php

if (!empty($on) && (subFeatureAcl($acl, AclCompetition, 'cSchedule') >= AclReadOnly)) {
    if (!isset($ret['MODS']['Tools'])) {
        $ret['MODS']['Tools'][] = 'Outils';
    }
    $ret['MODS']['Tools'][] = 'Plan des Finales' . '|' . $CFG->ROOT_DIR . 'Modules/Custom/PlanFinales/';
}
