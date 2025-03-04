<?php namespace App\Models;


class iTournament
{
    public int $id;
    public string $code;
    public string $name;
    public string $shortName;
    public array $categories = [];
    
    public \DateTime $dtFrom;
    public \DateTime $dtTo;
    
    public bool $hasTeam = false;
    
    public int $defaultWarmupI=0;
    public int $defaultWarmupT=0;
    public int $defaultMatchI=0;
    public int $defaultMatchT=0;
    
    public \DateTime $dtStart;
    public int $roundsCount;
    
    public array $planSchedules = [];
    
    public function __construct(int $tId)
    {
        $this->id = $tId;
        $this->GetInfo($tId);
        $this->GetFinalsIndiv($tId);
        $this->GetFinalsTeam($tId);
        $this->categories = array_filter($this->categories, fn($c) => count($c->rounds)>1);
       
        
        
        $this->GetWarmups($tId);
        
        $this->MakeSchedule();
        
        
        foreach ($this->categories as $category) 
        {
            foreach ($category->rounds as $round) 
            {
                $aLetters = array_unique(array_map(fn($m) => $m->getLetters(), $round->matches));
                if(count($aLetters) > 1){
                    foreach ($aLetters as $letters) 
                    {
                        $matches =array_filter($round->matches, fn($m) => $m->getLetters() == $letters); 
                        $nRound = new iRound();
                        $nRound->name = $round->name;
                        $nRound->step = $round->step;//."-".$letters;
                        $nRound->athPerTarget = $round->athPerTarget;
                        $nRound->matchPerTarget = $round->matchPerTarget;
                        $nRound->stepNum = $round->stepNum;
                        $nRound->keep = $round->keep;
                        $nRound->isTeam = $round->isTeam;
                        $nRound->matches = $matches;
                        $nRound->schedule=$round->schedule;
                        $nRound->duration = $round->duration;
                        
                        $nRound->sortMatches();
                        $category->rounds[] = $nRound; //$nRound->stepNum."-".$letters
                             
                    }
                    $round->keep = false; 
                    
                }
                else {
                   $round->sortMatches(); 
                }
              
                
            }
            $t = "";
            $category->rounds = array_filter($category->rounds, fn($r) => $r->keep);
            $p="klm";
        }
        
        usort($this->categories, fn($a, $b) => $a->name <=> $b->name);
        
    }
    
    
    
    
    public function GetInfo(int $tId)
    {
        $sqlInfos =  "SELECT ToId, ToCode, ToName, ToNameShort, ToWhenFrom, ToWhenTo"
                    ." FROM Tournament"
                    ." WHERE ToId= {$tId}";
        $stmtInfos = safe_r_sql($sqlInfos);
        
        while ($row=safe_fetch($stmtInfos)) {
            $this->name = $row->ToName;
            $this->shortName = $row->ToNameShort;
            $this->code = $row->ToCode;
            $this->dtFrom =   isset($row->ToWhenFrom) ? \DateTime::createFromFormat("Y-m-d", $row->ToWhenFrom): new \DateTime('0000-00-00 00:00:00');
            $this->dtTo =   isset($row->ToWhenTo) ? \DateTime::createFromFormat("Y-m-d", $row->ToWhenTo): new \DateTime('0000-00-00 00:00:00');
        }
        
        
    }
    
    
    public function GetFinalsIndiv(int $tId)
    {
       $defaultDt = new \DateTime('0000-00-00 00:00:00');
       $sqlFinalsIndiv = "SELECT f.FinEvent,f.FinMatchNo
                                ,g.GrPosition,g.GrPhase
                                ,s.FSTarget,s.FSLetter,s.FSScheduledDate,s.FSScheduledTime,s.FSScheduledLen
                                ,e.EvFinalAthTarget,e.EvMatchMultipleMatches, e.EvTargetSize
                                ,t.TarDescr
                         FROM Finals f
                         LEFT JOIN Grids g ON FinMatchNo=GrMatchNo
                         LEFT JOIN FinSchedule s on FSEvent=FinEvent AND FSMatchNo=FinMatchNo AND FSTournament=FinTournament
                         LEFT JOIN Events e on evcode=FinEvent AND EvTournament=FinTournament
                         LEFT JOIN Targets t ON e.EvFinalTargetType = t.TarId
                         WHERE FinTournament = {$tId}
                         ORDER BY FinEvent And  FinMatchNo";
        $stmtFinalsIndiv = safe_r_sql($sqlFinalsIndiv);
         
        $isFirst = true;
         // Process Finals Indiv
        while ($row=safe_fetch($stmtFinalsIndiv)) {
            $name = $row->FinEvent;
            $step = $row->GrPhase;
            
            $date = $row->FSScheduledDate; //YYYY-MM-DD
            $time = $row->FSScheduledTime; // HH:MM:SS
            $duration = isset($row->FSScheduledLen)?$row->FSScheduledLen:0; //MM
            $letter =  isset($row->FSLetter)?$row->FSLetter:"";
            $letter = (strlen($letter) ==4)?substr($letter, -1) :"";
           
            $DateTime = isset($time) ? \DateTime::createFromFormat("Y-m-d H:i:s", $date." ".$time): new \DateTime('0000-00-00 00:00:00');
            $shortDateTime =isset($time) ? $DateTime->format("YmdHis"):"";
            
            $matchNo = $row->FinMatchNo;
            $matchIndex =($matchNo&1)? $matchNo -1 :$matchNo;
            
            if($isFirst){
                $this->defaultMatchI = $duration;
                $isFirst = false;
            }
            
            if (!isset($this->categories[$name])) {
                $category = new iCategory();
                $category->isTeam = false;
                $category->name = $name;
                $category->targetFace=$row->TarDescr;
                $category->targetSize= intval($row->EvTargetSize) ;
                $this->categories[$name] = $category;
                
                //$round = new iRound();
                //$round->isTeam = false;
                //$round->name = $name;
                //$round->step = "Entrainement";
               // $this->categories[$name]->rounds[-1] = $round; 
            }
            
            
            if (!isset($this->categories[$name]->rounds[$step])) {
                $round = new iRound();
                $round->isTeam = false;
                $round->name = $name;
                $round->stepNum = $step;
                $round->step = $this->GetNameByStep($step);
                $round->schedule = $DateTime;
                $round->duration = $duration;
                
                $round->athPerTarget = ( max(1,$step*2) & $row-> EvFinalAthTarget )? 2: 1    ;
                $round->matchPerTarget = ( max(1,$step*2) & $row-> EvMatchMultipleMatches )? 2: 1 ;
                
                $this->categories[$name]->rounds[$step] = $round;
            }
            
            if (!isset($this->categories[$name]->rounds[$step]->matches[$matchIndex])) {
                $match = new iMatch();
                $match->category = $name;
                $match->isTeam = false;
                $match->schedule = $DateTime;
                $match->duration = $duration;
                $this->categories[$name]->rounds[$step]->matches[$matchIndex] = $match;
            }
            
            $player = new iPlayer();
            $player->matchNo = $matchNo;
            $player->cutPlace = $row->GrPosition;
            $player->category = $name;
            $player->iTarget = isset($row->FSTarget)?$row->FSTarget:0;
            $player->letter = $letter;
            $player->isTeam = false;
            $this->categories[$name]->rounds[$step]->matches[$matchIndex]->players[$matchNo] = $player;
         
            if (!isset($this->planSchedules[$shortDateTime]) && $DateTime != $defaultDt) {
                $schedule = new iSchedule();
                $schedule->start = $DateTime;
                $schedule->duration = $duration;
                $schedule->name = "Match";
                $schedule->isClear = false;
                $schedule->matchPerTarget = ( max(1,$step*2) & $row-> EvMatchMultipleMatches )? 2: 1 ;
                $this->planSchedules[$shortDateTime] = $schedule;
            }
        }
    }
    
    public function GetFinalsTeam(int $tId){
        $defaultDt = new \DateTime('0000-00-00 00:00:00');
        $sqlTeamFinals = "SELECT f.TfEvent, f.TfMatchNo, f.TfTournament
                                ,g.GrPosition,g.GrPhase
                                ,s.FSTarget,s.FSLetter,s.FSScheduledDate,s.FSScheduledTime,s.FSScheduledLen
                                ,e.EvFinalAthTarget,e.EvMatchMultipleMatches, e.EvTargetSize
                                ,t.TarDescr 
                         FROM TeamFinals f
                         JOIN Grids g ON TfMatchNo=GrMatchNo
                         JOIN FinSchedule s on FSEvent=TfEvent AND FSMatchNo=TfMatchNo AND FSTournament=TfTournament
                         LEFT JOIN Events e on evcode=TfEvent AND EvTournament=TfTournament
                         LEFT JOIN Targets t ON e.EvFinalTargetType = t.TarId
                         WHERE TfTournament = {$tId} 
                         ORDER BY TfMatchNo";
        $stmtFinalsTeam = safe_r_sql($sqlTeamFinals);
        
        $isFirst = true;
        // Process Finals Team
        while ($row=safe_fetch($stmtFinalsTeam)) {
            $name = $row->TfEvent;
            $step = $row->GrPhase;
            
            $letter =  isset($row->FSLetter)?$row->FSLetter:"";
            $letter = (strlen($letter) ==4)?substr($letter, -1) :"";
            
            $date = $row->FSScheduledDate; //YYYY-MM-DD
            $time = $row->FSScheduledTime; // HH:MM:SS
            $duration = $row->FSScheduledLen; //MM
            $DateTime = \DateTime::createFromFormat("Y-m-d H:i:s", $date." ".$time);
            $shortDateTime = $DateTime->format("YmdHis");
            
            $matchNo = $row->TfMatchNo;
            $matchIndex =($matchNo&1)? $matchNo -1 :$matchNo;
            
            if($isFirst){
                $this->defaultMatchT = $duration;
                $isFirst = false;
            }
            
            if (!isset($this->categories[$name])) {
                $category = new iCategory();
                $category->isTeam = true;
                $category->name = $name;
                $category->targetFace=$row->TarDescr;
                $category->targetSize= intval($row->EvTargetSize);
                $this->categories[$name] = $category;
                
                
                //$round = new iRound();
                //$round->isTeam = true;
                //$round->name = $name;
                //$round->step = "Entrainement";
                //$this->categories[$name]->rounds[-1] = $round; 
            }
            
            
            if (!isset($this->categories[$name]->rounds[$step])) {
                $round = new iRound();
                $round->isTeam = true;
                $round->name = $name;
                $round->stepNum = $step;
                $round->step = $this->GetNameByStep($step);
                $round->schedule = $DateTime;
                $round->duration = $duration;
                
                $round->athPerTarget = ( max(1,$step*2) & $row-> EvFinalAthTarget )? 2: 1    ;
                $round->matchPerTarget = ( max(1,$step*2) & $row-> EvMatchMultipleMatches )? 2: 1 ;
                
                $this->categories[$name]->rounds[$step] = $round;
            }   
            
            
             if (!isset($this->categories[$name]->rounds[$step]->matches[$matchIndex])) {
                $match = new iMatch();
                $match->category = $name;
                $match->isTeam = true;
                $match->schedule = $DateTime;
                $match->duration = $duration;
                $this->categories[$name]->rounds[$step]->matches[$matchIndex] = $match;
            }
            
            $player = new iPlayer();
            $player->matchNo = $matchNo;
            $player->cutPlace = $row->GrPosition;
            $player->category = $name;
            $player->iTarget = $row->FSTarget;
            $player->letter = $letter;
            $player->isTeam = true;
            $this->categories[$name]->rounds[$step]->matches[$matchIndex]->players[$matchNo] = $player;
            
            if (!isset($this->planSchedules[$shortDateTime]) && $DateTime != $defaultDt) {
                $schedule = new iSchedule();
                $schedule->start = $DateTime;
                $schedule->duration = $duration;
                $schedule->name = "Match";
                $schedule->isClear = false;
                $schedule->matchPerTarget = ( max(1,$step*2) & $row-> EvMatchMultipleMatches )? 2: 1 ;
                $this->planSchedules[$shortDateTime] = $schedule;
            }
        }
        
    }
    
    public function GetWarmups(int $tId){
        $defaultDt = new \DateTime('0000-00-00 00:00:00');
        $sqlWarmups = "SELECT FwEvent, FwTargets, FwDay, FwTime, FwDuration, FwMatchTime, FwTeamEvent 
                        FROM FinWarmup 
                        WHERE FwTournament = {$tId} 
                        ORDER BY FwEvent";
        
        $stmtWarmups = safe_r_sql($sqlWarmups);
        
        
        $isFirstT = true;
        $isFirstI = true;
        // Process Warmups
        while ($row=safe_fetch($stmtWarmups)){
            $name = $row->FwEvent;
            
            $IsTeam = $this->categories[$name]->isTeam;
            $date = $row->FwDay; //YYYY-MM-DD
            $time = $row->FwTime; // HH:MM:SS
            $duration = $row->FwDuration; //MM
            $DateTime = \DateTime::createFromFormat("Y-m-d H:i:s", $date." ".$time);
            $shortDateTime = $DateTime->format("YmdHis");
            
            $targets = $row->FwTargets;
            
            if($isFirstT && $IsTeam){
                $this->defaultWarmupT = $duration;
                $isFirstT = false;
            }
            if($isFirstI && !$IsTeam){
                $this->defaultWarmupI = $duration;
                $isFirstI = false;
            }
            
            
            if (isset($this->categories[$name])) {
                $iwarmup = new iWarmup();
                $iwarmup->name = "Entrainement";
                $iwarmup->category = $name;
                $iwarmup->isTeam = $this->categories[$name]->isTeam;
                $iwarmup->targets = $targets;
                $iwarmup->schedule = $DateTime;
                $iwarmup->duration = $duration;
                $this->categories[$name]->warmups[] = $iwarmup;
            }
            
            if (!isset($this->planSchedules[$shortDateTime]) && $DateTime != $defaultDt && strlen($targets) > 0 ) {
                $schedule = new iSchedule();
                $schedule->start = $DateTime;
                $schedule->duration = $duration;
                $schedule->name = "Entrainement";
                $schedule->isClear = false;
                $this->planSchedules[$shortDateTime] = $schedule;
            }
        }
        
    }
    
    
    
    
    
    public function MakeSchedule(){
        
        
        usort($this->planSchedules, fn($a, $b) => $a->start <=> $b->start);
                
        $prevSchedule = null;
        
        foreach ($this->planSchedules as $iSch) {
            if(!isset($prevSchedule)){
               $prevSchedule = $iSch;
               continue;
            }
            $dtEnd = (clone $prevSchedule->start)->modify("+{$prevSchedule->duration} minutes");
            if ( $dtEnd <   $iSch->start)
            {
                $duration = $dtEnd->diff($iSch->start);  
                $waitSch = new iSchedule();
                $waitSch->start  = $dtEnd;
                $waitSch->duration = $duration->i;
                $waitSch->isClear = true;
                $waitSch->name = "Pause";
                $this->planSchedules[] = $waitSch; 
            }
            $prevSchedule = $iSch;
        }
        usort($this->planSchedules, fn($a, $b) => $a->start <=> $b->start); 
    }
    
    
     public function getMaxTarget(): int
    {
        $tCount = 0; 
         if(count($this->categories) != 0 ){
             $tCount = max(array_map(fn($f) => $f->getMaxTarget(), $this->categories));
         }
         
         if($tCount<5)
         {
            $tCount = 5; 
         }
         
        return $tCount ;
    }
    
    
    
    
    
    
    
    
    
    
    public function GetStepFromMatchNo(int $no) : int
    {
        if($no <= 1 ) { return 0; } // final Or
        if ($no&1) { $no--;} // si impair fait -1
        $x = $no/2 ;
        $p = floor(log($x)/log(2));
        $step = 2^$p;
        return $step    ;    
    }
    
    public function GetNameByStep(int $step) : string
    {
        if($step == 0) { return "Or";}
        if($step == 1) { return "Bronze";}
        return "1/".$step;
    }
    
    
}