<?php namespace App\Models;


class iUpdateT
{
    public int $id;
    public string $code;
    public string $name;
    public string $shortName;
    public \DateTime $dtFrom;
    public \DateTime $dtTo;
    
    public bool $isValid;
    
    public int $eventsToUpdate = 0;
    public int $phasesToUpdate = 0;
    public int $warmUpToUpdate = 0;
    
    public array $events = [];
    public array $warmUps = [];
    public array $phases = [];
    
    public function __construct(int $tId, object $inData)
    {
        $this->id = $tId;
        $this->code = $inData->tCode;
        $this->isValid = $this->getInfo($tId);
       if($this->isValid)
       {
           try {
                $this->prepare($inData);
                foreach($this->phases as $phase)
                {
                   $this->checkPhase($phase);
                }
                foreach($this->events as $event)
                {
                   $this->checkEvent($event);
                }
                foreach($this->warmUps as $warmUp)
                {
                   $this->checkWarmUp($warmUp);
                }
            } catch (Exception $e){
                $mesg = $e->getMessage();
            }
            
       }
       
    }
    
    
    
    
    private function getInfo(int $tId):bool
    {
        $sqlInfos =  "SELECT ToId, ToCode, ToName, ToNameShort, ToWhenFrom, ToWhenTo
                     FROM Tournament
                     WHERE ToId= {$tId}";
        $stmtInfos = safe_r_sql($sqlInfos);
        
        while ($row=safe_fetch($stmtInfos)) {
            if($this->code != $row->ToCode) {
                  return false;
            }
            $this->name = $row->ToName;
            $this->shortName = $row->ToNameShort;
            $this->code = $row->ToCode;
            $this->dtFrom =   isset($row->ToWhenFrom) ? \DateTime::createFromFormat("Y-m-d", $row->ToWhenFrom): new \DateTime();
            $this->dtTo =   isset($row->ToWhenTo) ? \DateTime::createFromFormat("Y-m-d", $row->ToWhenTo): new \DateTime();
        }
        return true;
        
        
    }
    
    
    private function prepare(object $Data)
    {
        $defaultDt = new \DateTime('0000-00-00 00:00:00');
        foreach($Data->events as $evt)
        {
            usort($evt->players, fn($a, $b) => $a->order <=> $b->order);
            
            $phase = new uPhase();
            $phase->phase = intval($evt->step);
            $phase->matchPerTarget = intval($evt->matchPerTarget); 
            $phase->athPerTarget = intval($evt->athPerTarget);
            $phase->EvCode = $evt->category;
            $phase->EvTeamEvent = $evt->isTeam;
            $this->phases[] = $phase;
            
            $nextcible = false;
            $cible = ($evt->cible != "0")?$evt->cible:"";
            if(!isset($evt->duration)){
                $t="klm";
            }
            
            foreach($evt->players as $player)
            {
                $DateTime = \DateTime::createFromFormat("YmdHi", $evt->schedule);
               
                $event = new uEvent();
                
                $event->FSEvent = $evt->category;
                
                $event->FSMatchNo = $player->matchNo;
                $event->FSTarget = ($cible != "")?str_pad($cible, 3, "0", STR_PAD_LEFT):"";
                $event->FSLetter = $event->FSTarget . $player->letter;

                $event->FSTeamEvent = ($evt->isTeam == 1)?1:0;
                $event->FSScheduledLen = $evt->duration;
                $event->FSScheduledDate = ($DateTime == false ||  $DateTime == $defaultDt)? "":$DateTime->format("Y-m-d");
                $event->FSScheduledTime = ($DateTime == false ||  $DateTime == $defaultDt)?"":$DateTime->format("H:i:s");
                $this->events[] = $event;
                if($cible != ""){
                    if($evt->athPerTarget != 1 ){
                       if($nextcible) {
                            $cible++;  
                        }
                        $nextcible = !$nextcible;
                    }else {
                        $cible++;
                    }
                }
            }
            
        }
        
        foreach($Data->warmups as $wp)
        {
            $oriDateTime = \DateTime::createFromFormat("YmdHi", $wp->originaSchedule);
            $DateTime = \DateTime::createFromFormat("YmdHi", $wp->schedule);
            $MatchTime = \DateTime::createFromFormat("YmdHi", $wp->schedule);
            if($MatchTime != false ){
                $MatchTime->add(new \DateInterval('PT' . $wp->duration . 'M'));
            }
            
            $cibles = [];
            $cibles[]= intval($wp->cible);
            if(intval($wp->cible) != 0 )
            {
                $cibleCount = intval($wp->cibleCount);
                if($cibleCount > 1)
                {
                   $cibles[] = intval($wp->cible) + $cibleCount - 1;
                }
            }
                    
            
            $warmup = new uWarmUp();
            $warmup->FwEvent = $wp->category;
            $warmup->FwTeamEvent= $wp->isTeam;
            
            $warmup->FwDay = ($DateTime == false ||  $DateTime == $defaultDt)? "":$DateTime->format("Y-m-d");
            $warmup->FwTime = ($DateTime == false ||  $DateTime == $defaultDt)?"":$DateTime->format("H:i:s");
            $warmup->OriTime = ($oriDateTime == false ||  $oriDateTime == $defaultDt)?"":$oriDateTime->format("H:i:s");
            
            $warmup->FwDuration = $wp->duration;
            $warmup->FwMatchTime = ($MatchTime == false )?"":$MatchTime->format("H:i:s");
            
            $warmup->FwTargets = (intval($wp->cible) == 0 )? "": implode("-", $cibles);
            
    
            $this->warmUps[] = $warmup;
        }
    }
   
    
    private function checkEvent(uEvent $evt)
    {

         $sqlCheckEvent = "SELECT FSEvent, FSTeamEvent, FSMatchNo,
                       FSTarget, FSLetter,
                       FSScheduledDate, FSScheduledTime, FSScheduledLen
                        FROM FinSchedule
                         WHERE FSEvent = '{$evt->FSEvent}'
                         AND FSTournament = '{$this->id}' 
                         AND FSMatchNo = '{$evt->FSMatchNo}'
                         AND FSTeamEvent = '{$evt->FSTeamEvent}' 
                         LIMIT 1";
                        
        $row = safe_fetch(safe_r_sql($sqlCheckEvent));
        $isEmpty = (strlen($evt->FSTarget.$evt->FSLetter.$evt->FSScheduledDate.$evt->FSScheduledTime) ==0)?true:false;
        if(!isset($row) && !$isEmpty){
             $evt->insertNeeded = true;
        }else {
            
                if($row->FSTarget != $evt->FSTarget) $evt->updateNeeded = true;
                if($row->FSLetter != $evt->FSLetter) $evt->updateNeeded = true;
                if($row->FSScheduledDate != $evt->FSScheduledDate) $evt->updateNeeded = true;
                if($row->FSScheduledTime != $evt->FSScheduledTime) $evt->updateNeeded = true;
                if($row->FSScheduledLen != $evt->FSScheduledLen) $evt->updateNeeded = true;
        }
        if($evt->updateNeeded || $evt->insertNeeded ) {
            $this->eventsToUpdate++;
        }
    }
    
    private function checkPhase (uPhase $phase)
    {
        $sqlCheckPhase = "SELECT e.EvFinalAthTarget,e.EvMatchMultipleMatches 
                            FROM Events e
                            WHERE EvTournament = '{$this->id}'
                            AND EvTeamEvent='{$phase->EvTeamEvent}'
                            AND EvCode='{$phase->EvCode}'";
     
         $row = safe_fetch(safe_r_sql($sqlCheckPhase));  
        
        $athPerTarget = ( max(1,$phase->phase*2) & $row-> EvFinalAthTarget )? 2: 1;
        $matchPerTarget = ( max(1,$phase->phase*2) & $row-> EvMatchMultipleMatches )? 2: 1 ;
        if($phase->athPerTarget != $athPerTarget) $phase->updateNeeded = true;
        if($phase->matchPerTarget != $matchPerTarget) $phase->updateNeeded = true;
        if($phase->updateNeeded) {
            $this->phasesToUpdate++;
        }
    }
    
    private function checkWarmUp(uWarmUp $wp)
    {

         $sqlCheckWarmUp = "SELECT FwTargets, FwDay, FwTime, FwDuration, FwMatchTime
                            FROM FinWarmup
                            WHERE FwTournament = '{$this->id}'
                            AND FwEvent = '{$wp->FwEvent}'
                            AND FwTime = '{$wp->OriTime}'
                            AND FwTeamEvent = '{$wp->FwTeamEvent}'
                                
                            LIMIT 1";
                        
        $row = safe_fetch(safe_r_sql($sqlCheckWarmUp));
        if(!isset($row)) return;
        if($row->FwTargets != $wp->FwTargets) $wp->updateNeeded = true;
        if($row->FwDay != $wp->FwDay) $wp->updateNeeded = true;
        if($row->FwTime != $wp->FwTime) $wp->updateNeeded = true;
        if($row->FwDuration != $wp->FwDuration) $wp->updateNeeded = true;
        if($row->FwMatchTime != $wp->FwMatchTime) $wp->updateNeeded = true;
        
        if($wp->updateNeeded) {
            $this->warmUpToUpdate++;
        }
    }
    
    
    public function commit()
    {
        try {
            foreach (array_filter($this->phases, fn($f) => $f->updateNeeded) as $phase) 
            {
                $this->updatePhaseAthTarget($phase);
                $this->updatePhaseMatchTarget($phase);
            }
            foreach (array_filter($this->events, fn($f) => $f->updateNeeded) as $evt) {
                $this->updateEvent($evt);
            }
            foreach (array_filter($this->events, fn($f) => $f->insertNeeded) as $evt) {
                $this->insertEvent($evt);
            }
            foreach (array_filter($this->warmUps, fn($f) => $f->updateNeeded) as $warmup) {
                $this->updateWarmUp($warmup);
            }
        
        } catch (Exception $e){
            $mesg = $e->getMessage();
        }
    }
    
    public function updateEvent(uEvent $evt)
    {
        if(!$evt->updateNeeded) return;
        $sqlUpdate = "UPDATE FinSchedule
                        SET FSTarget = '{$evt->FSTarget}',
                            FSLetter = '{$evt->FSLetter}',
                            FSScheduledDate = '{$evt->FSScheduledDate}',
                            FSScheduledTime = '{$evt->FSScheduledTime}',
                            FSScheduledLen = '{$evt->FSScheduledLen}' 
                        WHERE FSEvent = '{$evt->FSEvent}'
                        AND FSTournament = '{$this->id}' 
                        AND FSMatchNo = '{$evt->FSMatchNo}' 
                        AND FSTeamEvent = '{$evt->FSTeamEvent}'";
        try {
            $Rs=safe_w_sql($sqlUpdate);
        } catch (Exception $e){
            $mesg = $e->getMessage();
        }
    }
    public function insertEvent(uEvent $evt)
    {
        if(!$evt->insertNeeded) return;
        $scDate = ($evt->FSScheduledDate !="")?$evt->FSScheduledDate:"0000-00-00 00:00:00";
                
        $sqlInsert = "INSERT INTO FinSchedule
                        (FSEvent,FSTeamEvent,FSMatchNo,FSTournament,
                         FSTarget, FSLetter, 
                         FSScheduledDate, FSScheduledTime, FSScheduledLen,
                         FSGroup, FsOdfMatchName, FsLJudge, FsTJudge, FSTimestamp)
                         VALUES 
                         (
                         '{$evt->FSEvent}','{$evt->FSTeamEvent}','{$evt->FSMatchNo}','{$this->id}',
                         '{$evt->FSTarget}','{$evt->FSLetter}',
                         '{$scDate}','{$evt->FSScheduledTime}', '{$evt->FSScheduledLen}',
                          0,0,0,0,'0000-00-00 00:00:00'   
                            )";
                        
        try {
            $Rs=safe_w_sql($sqlInsert);
        } catch (Exception $e){
            $mesg = $e->getMessage();
        }
        
    }
    
    public function updatePhaseAthTarget(uPhase $phase)
    {
        if(!$phase->updateNeeded) return;
        $PhaseNum=max(1,intval($phase->phase)*2);
        switch($phase->athPerTarget) 
        {
                case 1:
                        $SQL="EvFinalAthTarget=EvFinalAthTarget & ~{$PhaseNum}";
                        break;
                case 2:
                        $SQL="EvFinalAthTarget=EvFinalAthTarget | $PhaseNum";
                        break;
                default:
                        return;
        }
        
        $sqlUpdate = "UPDATE Events 
                        SET $SQL 
                        WHERE EvTournament='{$this->id}' 
                        AND EvTeamEvent='{$phase->EvTeamEvent}' 
                        AND EvCode='{$phase->EvCode}'";

        try {
            $Rs=safe_w_sql($sqlUpdate);
        } catch (Exception $e){
            $mesg = $e->getMessage();
        }
    }
    
    public function updatePhaseMatchTarget(uPhase $phase)
    {
        if(!$phase->updateNeeded) return;
        $PhaseNum=max(1,intval($phase->phase)*2);
        switch($phase->matchPerTarget) 
        {
                case 1:
                        $SQL="EvMatchMultipleMatches=EvMatchMultipleMatches & ~{$PhaseNum}";
                        break;
                case 2:
                        $SQL="EvMatchMultipleMatches=EvMatchMultipleMatches | $PhaseNum";
                        break;
                default:
                        return;
        }
        
        $sqlUpdate = "UPDATE Events 
                        SET $SQL 
                        WHERE EvTournament='{$this->id}' 
                        AND EvTeamEvent='{$phase->EvTeamEvent}' 
                        AND EvCode='{$phase->EvCode}'";
        try {
            $Rs=safe_w_sql($sqlUpdate);
        } catch (Exception $e){
            $mesg = $e->getMessage();
        }
    }
    
     public function updateWarmUp(uWarmUp $wp)
    {
        if(!$wp->updateNeeded) return;
        $sqlUpdate = "UPDATE FinWarmup
                        SET FwTargets = '{$wp->FwTargets}',
                            FwDay = '{$wp->FwDay}',
                            FwTime = '{$wp->FwTime}',
                            FwDuration = '{$wp->FwDuration}',
                            FwMatchTime = '{$wp->FwMatchTime}'
                        WHERE FwTournament = '{$this->id}'
                        AND FwEvent = '{$wp->FwEvent}'
                        AND FwTime = '{$wp->OriTime}'
                        AND FwTeamEvent = '{$wp->FwTeamEvent}'";
        try {
            $Rs=safe_w_sql($sqlUpdate);
        } catch (Exception $e){
            $mesg = $e->getMessage();
        }
        
    }
    
    
}

class uEvent
{
    public bool $updateNeeded = false;
    public bool $insertNeeded = false;
    public string $FSEvent; //name (categorie)
    public string $FSScheduledDate; //date YYYY-MM-DD
    public string $FSScheduledTime; //time HH:MM:SS
    public string $FSScheduledLen ; // duration
    
    public string $FSTeamEvent; // 1
    
    public string $FSMatchNo; // 3
    public string $FSLetter;  //014A
    public string $FSTarget; //014
}

class uPhase
{
    public bool $updateNeeded = false;
    public int $phase;
    public int $matchPerTarget; 
    public int $athPerTarget;
    public string $EvCode;
    public string $EvTeamEvent;
}
class uWarmUp
{
    public bool $updateNeeded = false;
    public string $OriTime; //time HH:MM:SS 
    public string $FwDay; //date YYYY-MM-DD
    public string $FwTime; //time HH:MM:SS
    public string $FwDuration ; // duration
    public string $FwMatchTime; //time HH:MM:SS
    public string $FwTargets; //target 4-7
    
    public string $FwEvent;
    public string $FwTeamEvent;
}