<?php

if (!empty($on) && (subFeatureAcl($acl, AclQualification, '') > AclReadOnly )) {
	if (!isset($ret['MODS']['Tools'])) {
        $ret['MODS']['Tools'][] = 'Outils';
    }
	$ret['MODS']['Tools'][] = 'Plan de cible' .'|'.$CFG->ROOT_DIR.'Modules/Custom/PlanQualifs/';
}

?>