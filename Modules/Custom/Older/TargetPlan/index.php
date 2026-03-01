<?php
//define('debug',true);	
//define('IN_PHP', true);
//ini_set('display_errors',1);
//ini_set('display_startup_errors',1);
    

//error_reporting(-1);
//ini_set("display_errors", "1");
//error_reporting(E_ALL);
//ini_set('display_errors', 'on');
//ini_set('display_startup_errors', 'on');
//ini_set("log_errors", "1");

$IanseoRoot = dirname(dirname(dirname(__FILE__)));
require_once($IanseoRoot . '/config.php');
require_once('Common/Fun_Number.inc.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/Fun_Sessions.inc.php');

CheckTourSession(true);
checkACL(AclQualification,AclReadWrite); 

require_once('Public/Startup.php');






