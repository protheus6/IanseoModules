<?php namespace Core;

class ImgTFace
{
    public string $id = "";
    public string $nom = "";
    public string $img = "";
    public string $label = "";
    public int $h = 0;
    public int $v = 0;
    public int $taille = 0;
    public int $count = 0;
    
    public function __construct(string $id,string $nom,string $label,int $h,int $v,int $taille, string $img)
    {
        $this->id = $id;
        $this->nom = $nom;
        $this->img = $img;
        $this->label = $label;
        $this->h = $h;
        $this->v = $v;
        $this->taille = $taille;
    }
    public function getNbArcher() {
        $sum = $this->v + $this->h; //

        switch ($sum) {
            case 3:
                return 1;
            case 4:
                return 2;
            case 6:
                return 4;
            default:
                return 0;
        }
    }
}

$imagesBlason = [];
$imagesBlason['Empty'] = new ImgTFace('Empty','','-',1,2,40,'Empty.svg');

$imagesBlason['TrgIndComplete-40'] = new ImgTFace('TrgIndComplete-40','40cm Unique','⌀40',1,2,40,'D40.svg');
$imagesBlason['TrgIndSmall-40'] = new ImgTFace('TrgIndSmall-40','40cm TriSpot CL','CL',2,1,20,'D40TCL.svg');
$imagesBlason['TrgCOIndSmall-40'] = new ImgTFace('TrgCOIndSmall-40','40cm TriSpot CO','CO',2,1,20,'D40TCO.svg');

$imagesBlason['vegas-40'] = new ImgTFace('vegas-40','40cm Vegas','Vegas',1,2,40,'D40V.svg');

$imagesBlason['TrgIndComplete-60'] = new ImgTFace('TrgIndComplete-60','60cm Unique','⌀60',2,2,60,'D60.svg');
$imagesBlason['TrgIndSmall-60'] = new ImgTFace('TrgIndSmall-60','60cm TriSpot','⌀60T',2,2,30,'D60T.svg');

$imagesBlason['TrgIndComplete-80'] = new ImgTFace('TrgIndComplete-80','80cm Unique','⌀80',2,4,80,'D80.svg'); 
$imagesBlason['TrgCOOutdoor-80'] = new ImgTFace('TrgCOOutdoor-80','80cm Réduit','⌀80CO',1,2,40,'D80R.svg'); 
$imagesBlason['TrgOutdoor-80'] = new ImgTFace('TrgOutdoor-80','80cm Unique','⌀80',2,4,80,'D80.svg'); 
$imagesBlason['TrgOutdoor-122'] = new ImgTFace('TrgOutdoor-122','122cm','⌀122',2,4,122,'D122.svg'); 

$imagesBlason['TrgFrBeursault-45'] = new ImgTFace('TrgFrBeursault-45','Beursault','⌀45',2,4,70,'Beursault.svg'); 

define('IMG_BLASON', $imagesBlason);
