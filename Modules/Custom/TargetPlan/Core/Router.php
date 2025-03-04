<?php
namespace Core;

class Router
{
    // Properties relatives to url
    private $urlController = null;
    private $urlAction = null;
    private $urlParams = array();
    protected $routes = [];
    
    public function InitControllers()
    {
        $this->addRoute(DEFAULT_CONTROLLER);
    }
     public function __construct(){
    $this->splitUrl();		

    if (!isset($this->urlController)) {
        $this->urlController = DEFAULT_CONTROLLER;
        $this->urlAction = DEFAULT_ACTION;
       // $default = NS_CONTROLLERS.'\\'.ucfirst(DEFAULT_CONTROLLER).'Controller';
       // $defaultAction = DEFAULT_ACTION;
      //  $page = new $default;
       // $page->{$defaultAction}();
        
    } 
    if (file_exists(CONTROLLERS . ucfirst($this->urlController) . 'Controller.php')) {
        $controller = NS_CONTROLLERS . '\\' . ucfirst($this->urlController) . 'Controller';
        
        if(!defined("ACL_".strtoupper($this->urlController))){
           header("Location: ".IANSEOROOT."Main.php");
           die();   
        }
        
        $this->urlController = new $controller();

        $this->urlAction = $this->urlAction ?? 'index';
        if (method_exists($this->urlController, $this->urlAction) && is_callable(array($this->urlController, $this->urlAction))) {                

            if (!empty($this->urlParams)) {
                $this->urlController->{$this->urlAction}(...$this->urlParams);

            } else {
                    $this->urlController->{$this->urlAction}();
            }                
        } else {
            if (!isset($this->urlAction)) { 
                $this->urlController->index();
                
            } else {
                $controller = NS_CONTROLLERS . '\\ErrorController';
                $error = new $controller();
                $error->index();
            }
        }
    } else {
        $controller = NS_CONTROLLERS . '\\ErrorController';
        $error = new $controller();
        $error->index();
    }
  }
    
    private function splitUrl()
    {
   	  // Verificar se a url foi setada
        if (isset($_GET['url'])) {
            $url = trim($_GET['url'], '/'); 
            $url = filter_var($url, FILTER_SANITIZE_URL); 
            $url = explode('/', $url);

            $this->urlController = isset($url[0]) ? $url[0] : null; //
            $this->urlAction = isset($url[1]) ? $url[1] : null;

            unset($url[0], $url[1]);
            $this->urlParams = array_values($url);
        }
       foreach($_GET as $key => $value)
       {
           if($key !='url' ) {
                $this->urlParams [$key] = $value;
           }
       }
    }
    
}
