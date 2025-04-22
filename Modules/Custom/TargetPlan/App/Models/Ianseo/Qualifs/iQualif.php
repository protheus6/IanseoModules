<?php namespace App\Models;


class iQualif
{
    public int $id;
    public array $sessions = [];
    
    public string $code;
    public string $name;
    public string $shortName;
    
    public \DateTime $dtFrom;
    public \DateTime $dtTo;
    
    public function __construct(int $tId)
    {
        $this->id = $tId;
        $this->GetInfo();
        
        $this->GetSessions();
        
    }
    
    
    private function GetInfo()
    {
        $sqlInfos =  "SELECT ToId, ToCode, ToName, ToNameShort, ToWhenFrom, ToWhenTo"
                    ." FROM Tournament"
                    ." WHERE ToId= {$this->id}";
        $stmtInfos = safe_r_sql($sqlInfos);
        
        while ($row=safe_fetch($stmtInfos)) {
            $this->name = $row->ToName;
            $this->shortName = $row->ToNameShort;
            $this->code = $row->ToCode;
            $this->dtFrom =   isset($row->ToWhenFrom) ? \DateTime::createFromFormat("Y-m-d", $row->ToWhenFrom): new \DateTime('0000-00-00 00:00:00');
            $this->dtTo =   isset($row->ToWhenTo) ? \DateTime::createFromFormat("Y-m-d", $row->ToWhenTo): new \DateTime('0000-00-00 00:00:00');
        }
        
        
    }
    
    private function GetSessions()
    {
        $defaultDt = new \DateTime('0000-00-00 00:00:00');
        $sqlSessDi = "SELECT D.DiSession, D.DiDistance, D.DiDay, D.DiWarmStart, D.DiStart,
                                    S.SesOrder, S.SesName, S.SesTar4Session, S.SesAth4Target	
                                FROM DistanceInformation D
                                INNER JOIN Session S on S.SesType = 'Q' AND S.SesOrder = D.DiSession AND S.SesTournament = D.DiTournament
                                WHERE DiTournament = {$this->id}
                         ORDER BY DiSession And  DiDistance";
        $stmtSessDi = safe_r_sql($sqlSessDi);
        while ($row=safe_fetch($stmtSessDi)) {
            $sessOrder = intval($row->DiSession);
            
            if (!isset($this->sessions[$sessOrder])) {
                $session = new iSession();
                $session->name = $row->SesName;
                $session->day =$row->DiDay;
                $session->warmStart =$row->DiWarmStart;
                $session->start =$row->DiStart;
                $session->targets =intval($row->SesTar4Session);
                $session->ath =intval($row->SesAth4Target);
                $session->order =$sessOrder;
                $this->sessions[$sessOrder] = $session;
                $this->GetParticipants($sessOrder);
            }
            
            $distance = new iDistance();
            $distance->id =$row->DiDistance;
            $distance->day =$row->DiDay;
            $distance->warmStart =$row->DiWarmStart;
            $distance->start =$row->DiStart;
            $distance->targets =intval($row->SesTar4Session);
            $distance->ath =intval($row->SesAth4Target);
            
            $this->sessions[$sessOrder]->distances[$distance->id] = $distance;
            
            
        }
          
    }
    
    private function GetParticipants(int $sessOrder){
         if (!isset($this->sessions[$sessOrder])) return;
         
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

                        WHERE EnTournament =  {$this->id} AND Q.QuSession = {$sessOrder}
                        ORDER BY QuTarget And  QuLetter";
        $stmtPart = safe_r_sql($sqlPart);
        while ($row=safe_fetch($stmtPart)) {
            $participant = new iParticipant();
            $participant->id=intval($row->EnId);
            $participant->structId=intval($row->EnCountry);
            $participant->targetId=intval($row->TfId);

            $participant->license = $row->EnCode;
            $participant->structName=$row->CoName;
            $participant->arme = $row->EnDivision;
            $participant->classe = $row->EnClass;
            $participant->target = intval($row->QuTarget);
            $participant->distance = intval($row->TdDist1);
            $participant->letter = $row->QuLetter;
            $this->sessions[$sessOrder]->participants[$participant->id] = $participant;
        }
        
       
    }
    
}