<?php namespace App\Controllers;

use Core\Controller;
use App\Models\iTournament;
use App\Models\iUpdateT;

class FinalsPController extends Controller
{
    
    public function index()
    {
        if(!isset($_SESSION['TourId'])) $this->Redirect(IANSEOROOT."Main.php"); 
        
        $tournament = new iTournament($_SESSION['TourId']);
        $this->View('index', ['tournament' => $tournament ]);
    }
    
    public function JsonSave()
    {
        $data = json_decode($_POST['events']);
        if($data->tId == $_SESSION['TourId']) {
            $updateT = new iUpdateT($_SESSION['TourId'], $data);
           if($updateT->isValid){
               $updateT->commit();
            }
        }
        
        
        
        
       // $tournament = new iTournament($_SESSION['TourId']);
        $this->PartialView('JsonSave', ['message' => 'Save Ok' ]);    
    }
}