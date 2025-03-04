<?php namespace App\Models;

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

class iMatch
{
    public string $name = "";
    public string $category = "";
    
    public array $players = [];
    
    
    public bool $isTeam = false;
    public \DateTime $schedule;
    public string $scheduleDate = "";
    public string $scheduleTime = "";
    public int $duration = 0;
    
    public function getMaxTarget(): int
    {
         if(count($this->players) == 0 ) return 1;
        return max(array_map(fn($m) => $m->getTarget(), $this->players));
        
       
    }
    public function sortPlayers()
    {
        
        usort($this->players, function($a, $b)
        {
            $cmp = $a->iTarget <=> $b->iTarget;
            if($cmp === 0)
            {
                return $a->matchNo <=> $b->matchNo;
            }
            return $cmp;
        });
        // usort($this->players, fn($a, $b) => $a->matchNo <=> $b->matchNo);
    }
    
    public function getMinTarget(): int
    {
        if(count($this->players) == 0 ) return 1;
        return min(array_map(fn($m) => $m->getTarget(), $this->players));
    }
    
    public function getLetters() : string
    {
        $arrLetters = array_map(fn($m) => strtoupper($m->letter), $this->players);
        sort($arrLetters);
        //$arrLetters = usort($arrLetters, fn($a, $b) => strcmp($a , $b));
        $letters = join("",$arrLetters);
        
        $letters = strlen($letters)== 0 ?"AB":$letters;
        
        if(strlen($letters) == 1 ){
            $letters = str_contains($letters, 'AB')?"AB":"CD";
        }
        
        
        return $letters;
    }
    
   

    

    
}


    

