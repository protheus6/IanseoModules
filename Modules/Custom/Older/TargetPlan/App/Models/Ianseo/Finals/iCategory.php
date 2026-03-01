<?php namespace App\Models;


use App\Models\RandomPastelColorGenerator;


class iCategory
{
    public string $name;
    public array $warmups = [];
    public array $rounds = [];
    public bool $isTeam = false;
    public string $targetFace = "";
    public int $targetSize = 0;
    
    
    public string $color = "";
     
    public function __construct()
    {
        $this->color = (new RandomPastelColorGenerator())->getNext();
     }
    
    public function getMaxTarget(): int
    {
        if(count($this->rounds) == 0 ) return 1;
        return max(array_map(fn($m) => $m->getMaxTarget(), $this->rounds));
    }
    public function getAbrev():string
    {
        return substr($this->name, -2);
    }
}
