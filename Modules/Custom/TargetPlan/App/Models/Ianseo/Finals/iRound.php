<?php namespace App\Models;


class iRound
{
    public string $name = "";
    public string $step = "";
    public int $athPerTarget = 1;
    public int $matchPerTarget = 1;
    public int $stepNum = 0;
    public bool $keep = true;
    
    public bool $isTeam = false;
    public array $matches = [];
    
     public \DateTime $schedule;
    public int $duration = 0;
    
    
    public function sortMatches()
    {
        usort($this->matches, fn($a, $b) => $a->getMinTarget() <=> $b->getMinTarget());
        foreach ($this->matches as $match) {
            $match->sortPlayers();
        }
    }
    

    public function getMaxTarget(): int
    {
        if(count($this->matches) == 0 ) return 1;
        return max(array_map(fn($m) => $m->getMaxTarget(), $this->matches));
    }
    
    public function getTargetCount(): int
    {
        $numCible = ($this->stepNum ==0)?1:$this->stepNum;
        return ($this->isTeam)?$numCible * 2:$numCible;
    }
    
    public function getMatchesPair() : array
    {
        return array_filter($this->matches, fn($f) => ($f->matchNo)&1  );
    }
    
    public function getMatchNo(): array
    {
        $matchsNo = [];
        foreach($this->matches as $match){
            foreach($match->players as $player){
                $matchsNo[]=$player->matchNo;
            }
        }
        return $matchsNo;
    }
    
    public function getLetters(): string
    {
        $letters = array_values(array_map(fn($m) => $m->getLetters(), $this->matches))[0];
        return strlen($letters)== 0 ?"AB":$letters;
    }
    
}


    

