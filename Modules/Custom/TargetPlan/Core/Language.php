<?php 
namespace Core;

class Language
{
    public string $current = "";
    public string $default = "en";
    public array $lang = array();
    
    
    public function __construct()
    {
        $this->loadLang($this->default);
        $this->current = $this->default;
        if(isset($_COOKIE["UseLanguage"])) {
		$this->current=strtolower($_COOKIE["UseLanguage"]);
        }
        elseif(!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		$langs=explode(',', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']));
		foreach($langs as $lang) {
			$l=explode(';', $lang);
			if(file_exists(LANGDIR. $l[0] . '.ini')) {
                            $this->current=strtolower($l[0]);
                            break;
			}
		}
	}
        
        if($this->current != $this->default){
            $this->loadLang($this->current);
        }
    }
    
    public function loadLang(string $langCode){
        $langFile = LANGDIR. $langCode . '.ini';
        if(file_exists($langFile)){
            foreach (parse_ini_file($langFile) as $key => $value) 
            {
                $this->lang[strtoupper($key)] = $value ;
            }
            
        }  
    }
    
}
