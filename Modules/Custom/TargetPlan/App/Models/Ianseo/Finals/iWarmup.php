<?php namespace App\Models;


class iWarmup
{
    public string $name = "Entrainement";
    public string $category = "";
    public bool $isTeam = false;
    
    public string $targets = "";
    
    public \DateTime $schedule;
    public int $duration = 0;

    
    public function getTarget(): int
    {
        return strlen($this->targets) === 0 ? 0 : intval(explode("-", $this->targets)[0]);
    }
    public function getTargetCount(): int
    {
        return strlen($this->targets) === 0 ? 0 : intval(explode("-", $this->targets)[1]) - intval(explode("-", $this->targets)[0]) + 1;
    }
    
    
}