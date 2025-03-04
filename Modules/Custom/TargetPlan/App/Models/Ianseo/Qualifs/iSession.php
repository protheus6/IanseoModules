<?php namespace App\Models;


class iSession
{
    public object $tour;
    
    public string $name = "";
    public string $day ="";
    public string $warmStart ="";
    public string $start ="";
    
    public int $targets =0;
    public int $ath =0;
    public int $order =0;
    
    public array $distances = [];
    public array $participants = [];
    public array $blasons = [];
    public array $categories = [];
    
     public function __construct(int $tId,int $sessOrder = 1, string $TargetId ="",int $CibleNum=0,string $cat="")
    {
        $this->tour = new iTourInfo($tId);
        $this->order = $sessOrder;
        $this->GetSession();
        $this->GetBlasons();
        $this->GetParticipants($TargetId,$CibleNum,$cat);
        usort($this->participants, fn($a, $b) => $a->structName <=> $b->structName);
        usort($this->categories, fn($a, $b) => $a->name <=> $b->name);
        
    }
    
    
    private function GetSession()
    {
         $sqlSessDi = "SELECT D.DiSession, D.DiDistance, D.DiDay, D.DiWarmStart, D.DiStart,
                                    S.SesOrder, S.SesName, S.SesTar4Session, S.SesAth4Target	
                        FROM DistanceInformation D
                        INNER JOIN Session S on S.SesType = 'Q' AND  S.SesOrder = D.DiSession AND S.SesTournament = D.DiTournament 
                        WHERE DiTournament = {$this->tour->id} AND SesOrder = {$this->order} 
                        ORDER BY DiSession And  DiDistance";
        $stmtSessDi = safe_r_sql($sqlSessDi);
        $isFirst = true;
        while ($row=safe_fetch($stmtSessDi)) {
            
            if($isFirst){
                $this->name = $row->SesName;
                $this->day =$row->DiDay;
                $this->warmStart =$row->DiWarmStart;
                $this->start =$row->DiStart;
                $this->targets =intval($row->SesTar4Session);
                $this->ath =intval($row->SesAth4Target);
               $isFirst = false;
            }
            $distance = new iDistance();
            $distance->id =$row->DiDistance;
            $distance->day =$row->DiDay;
            $distance->warmStart =$row->DiWarmStart;
            $distance->start =$row->DiStart;
            $distance->targets =intval($row->SesTar4Session);
            $distance->ath =intval($row->SesAth4Target);
            
            $this->distances[$distance->id] = $distance;
              
        }
          
    }
    
    
    
    private function GetParticipants(string $TfId = "",int $CibleNum = 0, string $cat=""){
         $sqlPart = "SELECT E.EnId,E.EnCode,E.EnDivision, E.EnClass,
                            E.EnCountry,E.EnName,E.EnFirstName,
                            E.EnAthlete,E.EnIndClEvent,E.EnTargetFace,
                            C.CoName,
                            Q.QuSession,Q.QuTarget,Q.QuLetter,
                            TF.TfId,TF.TfName,
                            TF.TfT1,TF.TfW1,
                            TF.TfT2,TF.TfW2,
                            TF.TfT3,TF.TfW3,
                            TF.TfT4,TF.TfW4,
                            TF.TfT5,TF.TfW5,
                            TF.TfT6,TF.TfW6,
                            TF.TfT7,TF.TfW7,
                            TF.TfT8,TF.TfW8
      
                        FROM Entries E
                        INNER JOIN Countries C on E.EnCountry = C.CoId AND E.EnTournament = C.CoTournament
                        INNER JOIN TargetFaces TF on E.EnTargetFace = TF.TfId AND E.EnTournament = TF.TfTournament
                        INNER JOIN Qualifications Q ON E.EnId=Q.QuId
                        WHERE EnTournament =  {$this->tour->id} AND Q.QuSession = {$this->order} ";
         
             
         
         if($CibleNum != 0){
            $sqlPart .=" AND Q.QuTarget = {$CibleNum} "; 
         } 
        $sqlPart .= "ORDER BY QuTarget And  QuLetter";
         
        $stmtPart = safe_r_sql($sqlPart);
        while ($row=safe_fetch($stmtPart)) {
            if(strlen($TfId) != 0){
                $targetId = $this->blasons[$row->EnTargetFace]->targetName.'-'.$this->blasons[$row->EnTargetFace]->diameter;
                if($TfId != $targetId) continue;
            } 
            $participant = new iParticipant();
            $participant->id=intval($row->EnId);
            $participant->structId=intval($row->EnCountry);
            $participant->targetId=intval($row->TfId);

            $participant->nom=$row->EnFirstName;
            $participant->prenom=$row->EnName;
            
            $participant->license = $row->EnCode;
            $participant->structName=$row->CoName;
            $participant->arme = $row->EnDivision;
            $participant->classe = $row->EnClass;
            $participant->target = intval($row->QuTarget);
            $participant->letter = $row->QuLetter;
            $participant->blason = $this->blasons[$row->EnTargetFace];
            
            
            if(!isset($this->categories[$participant->getCategory()])){
                $catNew = new iCat();
                $catNew->name = $participant->getCategory();
                $catNew->img = $this->blasons[$row->EnTargetFace]->img;
                $catNew->blason = $this->blasons[$row->EnTargetFace];
                $this->categories[$catNew->name] = $catNew;
            }
            if($cat != ""){
                if($participant->getCategory() == $cat){
                    $this->participants[$participant->id] = $participant;
                }
            }
            else {
                 $this->participants[$participant->id] = $participant;
            }
            $this->categories[$participant->getCategory()]->count++;
            $this->blasons[$row->EnTargetFace]->count++;
        }
        
       
    }
    
    private function GetBlasons () {
          $sqlBl = "SELECT TF.TfId, TF.TfName, TF.TfClasses,
                                TF.TfT1,TF.TfW1,
                                T.TarId,T.TarDescr
                        FROM TargetFaces TF
                        INNER JOIN Targets T ON T.TarId = TF.TfT1
                        WHERE TfTournament = {$this->tour->id}  
                        ORDER BY TfId";
        $stmtBl = safe_r_sql($sqlBl);
       while ($row=safe_fetch($stmtBl)) {
           
           $img = IMG_BLASON['Empty'];
           $imgName = $row->TarDescr.'-'.$row->TfW1;
           if(isset(IMG_BLASON[$imgName])){
             $img = IMG_BLASON[$imgName];  
           }
           $blason = new iBlason();
           $blason->id =intval($row->TfId);
           $blason->name =$row->TfName;
           $blason->classes =$row->TfClasses;
           $blason->targetName =$row->TarDescr;
           $blason->targetId =intval($row->TarId);
           $blason->diameter =intval($row->TfW1);
           $blason->distanceOrder = 0;
           $blason->img = $img ;
           $this->blasons[$blason->id] = $blason;
           
       }
    }
    
    
    public function blasonCount() : array
    {
        $rArr = [];
        foreach ($this->blasons as $blason){
            if(!isset($rArr[$blason->targetName.$blason->diameter]))
            {
                $rArr[$blason->targetName.$blason->diameter] = $blason->img;
            }
            $rArr[$blason->targetName.$blason->diameter]->count +=$blason->count;
        }
        return array_filter($rArr, fn($f) => $f->count >0   ); 
    }
    
    public function listByBlason() : array
    {
        $rArr = [];
        foreach ($this->blasons as $blason){
            if(!isset($rArr[$blason->targetName.$blason->diameter]))
            {
                $rArr[$blason->targetName.$blason->diameter] = $blason->img;
            }
            $rArr[$blason->targetName.$blason->diameter]->count +=$blason->count;
        }
        return array_filter($rArr, fn($f) => $f->count >0   ); 
    }
    
        public function listByCategory() : array
    {
       // $rArr = [];
        
      //  foreach(array_unique(array_map(fn($m) => $m->getCategory(), $this->participants)) as $cat){
       //   $rArr[] = $cat;
       // }
        
        
        return $this->categories; 
    }
    
    
    
    public function blasonById(string $id) : array
    {
        $rArr = [];
        foreach (array_filter($this->blasons, fn($c) => $c->id == $id) as $blason){
            if(!isset($rArr[$blason->targetName.$blason->diameter]))
            {
                $rArr[$blason->targetName.$blason->diameter] = $blason->img;
            }
            $rArr[$blason->targetName.$blason->diameter]->count +=$blason->count;
        }
        return array_filter($rArr, fn($f) => $f->count >0   ); 
    }
    
    public function blasonByCat(string $cat) : array
    {
        return array_filter($this->participants, fn($c) => $c->getCategory() == $cat);
    }
    
    
    
    public function getStructColor(): array
    {
        $rArr = [];
        $color = new RandomPastelColorGenerator();
        foreach(array_unique(array_map(fn($m) => $m->structId, $this->participants)) as $sId){
          $rArr[$sId] = $color->getNext();
        }
        return $rArr;
         
    }
}
       
   
    

