<?php namespace App\Controllers;

use Core\Controller;
use App\Models\iSession;
use App\Models\iCible;
use App\Models\iUpdateParticipant;

class QualifsPController extends Controller
{
    
    public function index(int $sessId=1,int $sort=0) 
    {
        if(!isset($_SESSION['TourId'])) $this->Redirect(IANSEOROOT."Main.php");
        $qualif = new iSession($_SESSION['TourId'],$sessId);
        $this->View('index', ['session' => $qualif,'sortBy' => $sort ]);
    }
    
    public function BlasonList(int $sessId, string $tfId){
        $qualif = new iSession($_SESSION['TourId'],$sessId,$tfId);
        $this->PartialView('BlasonList', ['session' => $qualif ]);
    }
    
    public function PickingList(int $sessId, int $sort, string $tfId="",string $cat=""){
        $qualif = new iSession($_SESSION['TourId'],$sessId,$tfId,0,$cat);
        if($sort == 1) {
            $this->PartialView('ListByCat', ['session' => $qualif ]);
        }else {
             $this->PartialView('ListByBlason', ['session' => $qualif ]);
        }
       
    }
    
    public function Cible(int $sessId, int $cibleNum){
        $cible = new iCible($_SESSION['TourId'],$sessId,$cibleNum);
        $this->PartialView('Cible', ['cible' => $cible]);
    }
    
    public function BlasonRecap(int $sessId){
        $qualif = new iSession($_SESSION['TourId'],$sessId);
        $this->PartialView('BlasonRecap', ['session' => $qualif ]);
    }
        
     public function MoveArcher(int $sessId, int $archerId, string $cNum="",string $cLetter=""){
        $updatePart = new iUpdateParticipant($_SESSION['TourId'],$archerId,$sessId);
        $updatePart->updateParticipant($cNum,$cLetter);
        $this->Ok();
       // $this->PartialView('Cible', ['cible' => $cible]);
    }  
    
    public function ClearCible(int $sessId, int $cibleNum){
        $cible = new iCible($_SESSION['TourId'],$sessId,$cibleNum);
        $cible->Clear();
        $this->Ok();
        
    }
      
}