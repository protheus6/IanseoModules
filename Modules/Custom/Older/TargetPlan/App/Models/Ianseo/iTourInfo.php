<?php namespace App\Models;


class iTourInfo
{
    public int $id;
    
    public string $code;
    public string $name;
    public string $shortName;
    
    public \DateTime $dtFrom;
    public \DateTime $dtTo;
    
    public array $sessions = [];
    
    public function __construct(int $tId)
    {
        $this->id = $tId;
        $this->GetInfo();
        
        
    }
    
    
    private function GetInfo()
    {
        $sqlInfos =  "SELECT ToId, ToCode, ToName, ToNameShort, ToWhenFrom, ToWhenTo"
                    ." FROM Tournament"
                    ." WHERE ToId= {$this->id}";
        $stmtInfos = safe_r_sql($sqlInfos);
        
        while ($row=safe_fetch($stmtInfos)) {
            $this->name = $row->ToName;
            $this->shortName = $row->ToNameShort;
            $this->code = $row->ToCode;
            $this->dtFrom =   isset($row->ToWhenFrom) ? \DateTime::createFromFormat("Y-m-d", $row->ToWhenFrom): new \DateTime('0000-00-00 00:00:00');
            $this->dtTo =   isset($row->ToWhenTo) ? \DateTime::createFromFormat("Y-m-d", $row->ToWhenTo): new \DateTime('0000-00-00 00:00:00');
        }
        
        $sqlSess =  "SELECT SesOrder, SesName
                    FROM Session
                    WHERE SesTournament = {$this->id}
                    ORDER BY SesOrder";
        $stmtSess = safe_r_sql($sqlSess);
        
        while ($row=safe_fetch($stmtSess)) {
            $sesI = new SesInfo();
            $sesI->id = $row->SesOrder;
            $sesI->name = $row->SesName;
        $this->sessions[$row->SesOrder] = $sesI;
        }
    }
    
    
    
}
class SesInfo {
        public int $id =0;
        public string $name = "";   
    }