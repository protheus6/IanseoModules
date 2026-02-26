<?php

require_once(dirname(__FILE__, 4) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');

$JSON=array('error'=>1, 'min'=>'', 'max'=>'','coalesce'=>false);
if (!CheckTourSession() or !hasFullACL(AclQualification, '', AclReadOnly)) {
	JsonOut($JSON);
}

if (isset($_REQUEST['Ses'])) {
    $Session = GetSessions('Q', false, $_REQUEST['Ses'].'_Q', $_SESSION['TourId']);
	if (count($Session)==1) {
		$JSON['min']=intval($Session[0]->SesFirstTarget);
		$JSON['max']=$Session[0]->SesFirstTarget+$Session[0]->SesTar4Session-1;
		$JSON['error']=0;
		$JSON['coalesce']=(($Session[0]->SesAth4Target<=2) ? '<input id="x_Coalesce" name="x_Coalesce" type="checkbox" value="1">' . get_text('CoalesceScorecards', 'Tournament').'<div>' . get_text('CoalesceScorecardsTip', 'Tournament').'</div>' : '');
	}
}

JsonOut($JSON);
