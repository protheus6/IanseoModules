<?php namespace App\Models;


class iUpdateParticipant
{
    public object $tour;
    
    public string $name = "";
    
    public int $targets =0;
    public int $ath =0;
    public int $order =0;
    
    public iParticipant $participant;
    
    private array $letters = array(1 => "A",2 => "B",3 => "C",4 => "D",5 => "E",6 => "F",7 => "G",8 => "H",);
    
     public function __construct(int $tId, int $PartId,int $sessOrder = 1)
    {
        $this->tour = new iTourInfo($tId);
        $this->order = $sessOrder;
        $this->GetSession();
        $this->GetParticipant($PartId);
        
    }
    
    
    private function GetSession()
    {
         $sqlSessDi = "SELECT D.DiSession, D.DiDistance, D.DiDay, D.DiWarmStart, D.DiStart,
                                    S.SesOrder, S.SesName, S.SesTar4Session, S.SesAth4Target	
                        FROM DistanceInformation D
                        INNER JOIN Session S on S.SesOrder = D.DiSession AND S.SesTournament = D.DiTournament
                        WHERE DiTournament = {$this->tour->id} AND SesOrder = {$this->order} 
                        ORDER BY DiSession And  DiDistance";
        $stmtSessDi = safe_r_sql($sqlSessDi);
        $isFirst = true;
        while ($row=safe_fetch($stmtSessDi)) {
            
            if($isFirst){
                $this->name = $row->SesName;
                $this->targets =intval($row->SesTar4Session);
                $this->ath =intval($row->SesAth4Target);
               $isFirst = false;
            }
        }
    }
    
    
    
    private function GetParticipant(int $PartId){
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
                        WHERE EnTournament =  {$this->tour->id} 
                                AND Q.QuSession = {$this->order} 
                                AND E.EnId = {$PartId}";
         
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
            $participant->letter = $row->QuLetter;
            $this->participant = $participant;
        }
        
       
    }

    public function updateParticipant(string $cNum, string $cLetter)
    {
        
        if($this->participant->id == 0) return; 
        $targetNo = $this->order . str_pad($cNum, 3, '0', STR_PAD_LEFT) .$this->letters[$cLetter] ;
        
        if($cNum == ""){
            $targetNo = "";
            $cLetter = "";
            $cNum = "0";
        }else {
            foreach ($this->getExistParticipant($cNum,$cLetter) as $partId){
                $sqlUpdate = "UPDATE Qualifications Q
                                    SET Q.QuTarget = '0',
                                        Q.QuLetter = '',
                                        Q.QuTargetNo = ''   
                                    WHERE Q.QuId = '{$partId}'
                                    AND Q.QuSession = '{$this->order}'";
                try {
                    $Rs=safe_w_sql($sqlUpdate);
                } catch (Exception $e){
                    $mesg = $e->getMessage();
                }
                
            }
            
        }
        
        
        $sqlUpdate = "UPDATE Qualifications Q
                                SET Q.QuTarget = '{$cNum}',
                                    Q.QuLetter = '{$this->letters[$cLetter]}',
                                    Q.QuTargetNo = '{$targetNo}'   
                                WHERE Q.QuId = '{$this->participant->id}'
                                AND Q.QuSession = '{$this->order}'";
        try {
            $Rs=safe_w_sql($sqlUpdate);
        } catch (Exception $e){
            $mesg = $e->getMessage();
        }        
    }
    
    private function getExistParticipant(string $cNum, string $cLetter) : array
    {
        $arr = [];
        $sqlPart = "SELECT E.EnId
                        FROM Entries E
			INNER JOIN Qualifications Q ON E.EnId=Q.QuId
                        WHERE EnTournament = {$this->tour->id}
                                AND Q.QuSession = '{$this->order}' 
                                AND Q.QuTarget = '{$cNum}'
                                AND Q.QuLetter = '{$this->letters[$cLetter]}'";
         
        $stmtPart = safe_r_sql($sqlPart);
        while ($row=safe_fetch($stmtPart)) {
            $arr[] = intval($row->EnId);
        }
        return $arr;
    }
    
    
    
}
       
   
    

