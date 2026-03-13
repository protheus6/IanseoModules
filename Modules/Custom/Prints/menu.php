<?php




if(!empty($on) AND isset($ret['MODS'])) {
	if (!isset($ret['MODS']['Tools'])) {
        $ret['MODS']['Tools'][] = 'Outils';
    }
	$ret['MODS']['Tools'][] = 'Impression' .'|'.$CFG->ROOT_DIR.'Modules/Custom/Prints/';
}



?>
