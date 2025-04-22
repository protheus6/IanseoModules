<?php namespace App\Models;


class iParticipant
{
    public int $id=0;
    public int $structId=0;
    public int $targetId=0;
    
    public string $license = "";
    public string $structName="";
    public string $arme = "";
    public string $classe = "";
    
    public string $nom = "";
    public string $prenom = "";
    
    public int $target = 0;
    public int $distance = 0;
    public string $letter = "";
    public \App\Models\iBlason $blason;
    
    public function getCible():string
    {
        if($this->target > 0 ) {
            return $this->target.$this->letter;
        }
        return "";
    }
    public function getNomCourt():string
    {
        return substr($this->prenom, 0, 1) . '.' . $this->nom;
    }
    public function getCategory():string
    {
        return $this->classe . $this->arme;
    }
    
}


