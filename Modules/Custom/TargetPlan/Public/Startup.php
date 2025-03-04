<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

define('DS', DIRECTORY_SEPARATOR);

define('IANSEOROOT', '../../../');

// Capture the full path of the application. DIRECTORY_SEPARATOR adds a slash to the end of the path
define('ROOT', dirname(__DIR__) . DS);

define('APP', ROOT . 'App' . DS);
define('CORE', ROOT . 'Core' . DS);
define('LANGDIR', CORE. 'Lang' . DS);

define('CONTROLLERS', APP . 'Controllers' . DS);
define('VIEWS', APP . 'Views' . DS);
define('MODELS', APP . 'Models' . DS);
define('PUBLICDIR', ROOT .'Public' . DS);

define('NS_CONTROLLERS', 'App\Controllers');
define('NS_MODELS', 'App\Models');


if(!isset($_SESSION['TourId'])){
    header("Location: ".IANSEOROOT);
    die();  
}

require_once CORE . 'Config.php';


// This is the auto-loader for the Composer dependencies (to update the namespace in your project run: composer dumpautoload).
require_once CORE . 'ClassLoader.php';

// Load application settings (error reporting etc.)


// Load Router class
use Core\Router;

// Launch the application through the Router
define('LANG', (new Core\Language())->lang);  
define('ACL', actualACL());




if(ACL[AclQualification] == AclReadWrite){
    define('ACL_QUALIFSP', true);
}
if((ACL[AclIndividuals] == AclReadWrite && $_SESSION['MenuFinIDo'] ) ||( ACL[AclTeams] == AclReadWrite && $_SESSION['MenuFinTDo'])){
    define('ACL_FINALSP', true);
}


$router = new Router();


