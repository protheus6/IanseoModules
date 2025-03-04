<?php namespace Core;
//ini_set('display_errors',1);
//ini_set('display_startup_errors',1);
//error_reporting(-1);


class Controller
{
    protected function Name()
    {
        $ctrlName =  preg_replace('/^.*\\\/', '', get_class($this));
        $ctrlName =  preg_replace('/Controller$/', '', $ctrlName);
        return $ctrlName;
    }
    protected function View($view, $data = [])
    {
        
        extract($data);

      //  include VIEWS."_Shared/header.php";
        
        $ControlerName = $this->Name();
        ob_start();
        include VIEWS.$this->Name()."/$view.php";
        $ViewContent = ob_get_clean();
        include VIEWS."_Shared/_Layout.php";
    }
    protected function PartialView($view, $data = [])
    {
        extract($data);
        $ControlerName = $this->Name();
        include VIEWS.$this->Name()."/$view.php";
    }
    
    protected function JsonResponse($data = [])
    {
        header('Content-type: application/json');
        echo json_encode($data);
    }
    
    protected function Ok()
    {
        header('Status: 200');
    }
    protected function NotFound()
    {
        header('Status: 404');
    }
    
    protected function Redirect($url)
    {
        header("Location: ".$url);
        die(); 
    }
    
    
    
}