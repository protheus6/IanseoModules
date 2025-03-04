<?php namespace App\Models;


class iPlayer
{
    public string $category = "";
    
    public int $cutPlace=0;
    public int $matchNo;
    public string $iTarget = "";
    public string $letter = "";

    public bool $isTeam = false;
    
    
     public function getTarget(): int
    {
        return strlen($this->iTarget) === 0 ? 0 : intval($this->iTarget);
    }

    

    

    
}


    

