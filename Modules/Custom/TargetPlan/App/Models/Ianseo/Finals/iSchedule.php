<?php namespace App\Models;


class iSchedule
{
    public \DateTime $start;
    public int $duration = 5;
    public string $name = "";
    public bool $isClear = false;
    public int $matchPerTarget = 1;
    
    public function getEnd():\DateTime
    {
       return clone($this->start)->add(new \DateInterval('PT' . $this->duration . 'M'));
    }
            
    
}