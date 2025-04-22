<?php namespace App\Models;


class iCible
{
    public object $tour;
    public int $num =0;
    public int $ath = 0;
    public int $order;
    
    // Warn Level:
    // 0 -> empty (Blue)
    // 1 -> Ok (Green)
    // 2 -> Warning (Orange)
    // 3 -> Error (Red)
    public int $warnLevel=0;
            
    
    public \App\Models\iDistance $distance;
    public array $participants = [];
    public array $blasons = [];
    public array $vagues = [];
    
    private array $labels = array(1 => "A",2 => "B",3 => "C",4 => "D",5 => "E",6 => "F",7 => "G",8 => "H",);
    
     public function __construct(int $tId,int $sessOrder = 1,int $CibleNum=0)
    {
        $this->tour = new iTourInfo($tId);
        $this->order = $sessOrder;
        $this->num = $CibleNum;
        $this->distance = new iDistance();
        $this->GetSession();
        $this->GetBlasons();
        $this->GetParticipants($this->num);
        $this->MakeVagues();
        $this->SetDistance();
        $this->SetWarnLevel();
        
    }
    
    private function GetSession()
    {
         $sqlSessDi = "SELECT D.DiSession, D.DiDistance, D.DiDay, D.DiWarmStart, D.DiStart,
                                    S.SesOrder, S.SesName, S.SesTar4Session, S.SesAth4Target	
                        FROM DistanceInformation D
                        INNER JOIN Session S on S.SesType = 'Q' AND S.SesOrder = D.DiSession AND S.SesTournament = D.DiTournament
                        WHERE DiTournament = {$this->tour->id} AND SesOrder = {$this->order} 
                        ORDER BY DiSession And  DiDistance";
        $stmtSessDi = safe_r_sql($sqlSessDi);
        $isFirst = true;
        while ($row=safe_fetch($stmtSessDi)) {
            
            if($isFirst){
                $this->ath =intval($row->SesAth4Target);
                
                $this->distance->id =$row->DiDistance;
                $this->distance->day =$row->DiDay;
                $this->distance->warmStart =$row->DiWarmStart;
                $this->distance->start =$row->DiStart;
                $this->distance->targets =intval($row->SesTar4Session);
                $this->distance->ath =intval($row->SesAth4Target);
            
                $this->distance->distance = 0;
                $this->distance->sameDistance = true;
        
                $isFirst = false;
            }
        }
          
    }
     private function SetDistance()
    {
         
        $distList  = array_unique(array_map(fn($m) => $m->distance, $this->participants));
        
        
        if(count($distList) == 1)
        {
            $this->distance->distance = reset($distList);
            $this->distance->sameDistance = true;
        }
        else if(count($distList) >1)
        {
            $this->distance->distance = reset($distList);
            $this->distance->sameDistance = false;
        }
    }
    
   
    private function GetParticipants(int $CibleNum = 0){
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
                            TF.TfT8,TF.TfW8,
                            TD.Td1,TD.Td2,TD.Td3,TD.Td4,
                            TD.Td5,TD.Td6,TD.Td7,TD.Td8,
                            TD.TdDist1,TD.TdDist2,
                            TD.TdDist3,TD.TdDist4,
                            TD.TdDist5,TD.TdDist6,
                            TD.TdDist7,TD.TdDist8
                            
                        FROM Entries E
                        INNER JOIN Countries C on E.EnCountry = C.CoId AND E.EnTournament = C.CoTournament
                        INNER JOIN TargetFaces TF on E.EnTargetFace = TF.TfId AND E.EnTournament = TF.TfTournament
                        INNER JOIN Qualifications Q ON E.EnId=Q.QuId
                        INNER JOIN TournamentDistances TD on EnTournament=TdTournament 
                                    AND concat(trim(E.EnDivision),trim(E.EnClass)) LIKE TdClasses
                                    
                        WHERE EnTournament =  {$this->tour->id} AND Q.QuSession = {$this->order} ";
         
         
         if($CibleNum != 0){
            $sqlPart .=" AND Q.QuTarget = {$CibleNum} "; 
         } 
        $sqlPart .= "ORDER BY QuTarget And QuLetter";
         
        $stmtPart = safe_r_sql($sqlPart);
        while ($row=safe_fetch($stmtPart)) {
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
            $participant->distance = intval($row->TdDist1);
            $participant->letter = $row->QuLetter;
            $this->participants[$participant->id] = $participant;
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
           $blason->img = $img;
           $this->blasons[$blason->id] = $blason;
           
       }
    }
    
    private function SetWarnLevel()
    {
        $structList = array_unique(array_map(fn($m) => $m->structName, $this->participants));
        
         if(count($this->participants) == $this->ath)
        {
            $this->warnLevel = 1;
        }
        foreach ($structList as $struct) 
        {
           $nbarcher = count(array_filter($this->participants, fn($m) => $m->structName == $struct));
           if($nbarcher > ($this->ath / 2))
            {
                $this->warnLevel = 2;
            }
        }
        
        if(count($structList) == 1 )
        {
            $this->warnLevel = 3;
        }
        if(!$this->distance->sameDistance)
        {
            $this->warnLevel = 4;
        }
        
    }
    
    
    
    public function blasonCount () : array
    {
        $rArr = [];
        foreach ($this->blasons as $blason){
            if(!isset($rArr[$blason->targetName]))
            {
                $rArr[$blason->targetName] =$blason->img; //  IMG_BLASON[$blason->targetName.'-'.$blason->diameter];
            }
            $rArr[$blason->targetName]->count +=$blason->count;
        }
        return $rArr;
    }
    
    
    public function MakeVagues()
    {
        for($vague = 1; $vague <= $this->ath ; $vague++) {
            $oVague = new iVague();
            $oVague->target = $this->num;
            $oVague->order = $vague;
            $oVague->label = $this->labels[$vague];
            $parts = current(array_filter($this->participants, fn($m) => $m->target ==$this->num && $m->letter == $oVague->label));
            if( $parts != false )
            {
                $oVague->participant = $parts;
                $oVague->blason = $this->blasons[$oVague->participant->targetId];
            }
            $this->vagues[$vague] = $oVague;
        }
        
        if (count(array_filter($this->vagues, function($v) { return isset($v->blason);})) > 0
            && count(array_filter($this->vagues, function($v) {return isset($v->blason);})) < 4) 
        {
         
            $countOrder1or3 = count(array_filter($this->vagues, function($v) {
                return isset($v->blason) && ($v->order == 1 || $v->order == 3);
            }));
            $countOrder2or4 = count(array_filter($this->vagues, function($v) {
                return isset($v->blason) && ($v->order == 2 || $v->order == 4);
            }));

            if ($countOrder1or3 == 1) {
                $vagueIn = current(array_filter($this->vagues, function($v) {
                    return isset($v->blason) && ($v->order == 1 || $v->order == 3);
                }));

                $vagueNull = current(array_filter($this->vagues, function($v) {
                    return !isset($v->blason)&& ($v->order == 1 || $v->order == 3);
                }));

                if (isset($vagueNull) && $vagueNull != false ) { // Check if vagueNull is found
                    $vagueNull->blason = $vagueIn->blason;
                    $vagueNull->overlay = ($vagueIn->blason->img->h == 1 || $vagueIn->blason->img->v == 1) ? true : false;
                }
            }



            if ($countOrder2or4 == 1) {
                $vagueIn = current(array_filter($this->vagues, function($v) {
                    return isset($v->blason) && ($v->order == 2 || $v->order == 4);
                }));

                $vagueNull = current(array_filter($this->vagues, function($v) {
                    return !isset($v->blason) && ($v->order == 2 || $v->order == 4);
                }));

                if (isset($vagueNull)&& $vagueNull != false) { // Check if vagueNull is found
                    $vagueNull->blason = $vagueIn->blason;
                    $vagueNull->overlay = ($vagueIn->blason->img->h == 1 || $vagueIn->blason->img->v == 1) ? true : false;
                }
            }

      
        }   
        
        
        
    }
    
    
    public function getMaxNbArchers() : int 
    {
        $noNull =  array_filter($this->vagues, fn($f) => isset($f->blason)  );
        if(!isset($noNull)) return 0;
        $nb =  max(array_map(fn($m) => $m->blason->img->getNbArcher(),$noNull));
        return $nb;
        
    }
    
    
    public function getVagueFromNb(int $nb) : iVague 
    {
        $noNull =  array_filter($this->vagues, fn($f) => isset($f->blason)  );
        if(!isset($noNull)) return null;
        return current(array_filter($noNull, fn($f) => $f->blason->img->getNbArcher() == $nb ));
    }
    
    public function GetVaguesOrdered() : array 
    {
        if(count($this->vagues) > 4 ){
            return [$this->vagues];
        }else {
            $vAC = array_filter($this->vagues, function($c) {return $c->order == 1 || $c->order == 3;});
            $vBD = array_filter($this->vagues, function($c) {return $c->order == 2 || $c->order == 4;});
            $vAC = array_values($vAC);
            $vBD = array_values($vBD);
            return [$vAC, $vBD];
         }
    }
    
    public function Clear()
    {
        
        foreach ($this->participants as $participant){
            
            $sqlUpdate = "UPDATE Qualifications Q
                                SET Q.QuTarget = '0',
                                    Q.QuLetter = '',
                                    Q.QuTargetNo = ''   
                                WHERE Q.QuId = '{$participant->id}'
                                AND Q.QuTarget = {$this->num}
                                AND Q.QuSession = '{$this->order}'";
                                    
            try {
               $Rs=safe_w_sql($sqlUpdate);
            } catch (Exception $e){
               $mesg = $e->getMessage();
            } 
        }
    }
}
       
   
    

