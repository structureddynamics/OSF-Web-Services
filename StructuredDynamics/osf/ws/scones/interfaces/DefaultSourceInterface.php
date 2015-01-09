<?php
  
  namespace StructuredDynamics\osf\ws\scones\interfaces; 
  
  use \StructuredDynamics\osf\framework\Namespaces;  
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "3.0";
    }   
    
    public function processInterface()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        $ch = curl_init();
      
        $headers = array( "Content-Type: application/json" );    

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, trim($this->ws->scones_endpoint, '/') . '/tag/concept/' . $this->ws->type . ($this->ws->stemming ? '/stemming' : ''));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->ws->document);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $data = curl_exec($ch);

        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);     
        
        if(curl_errno($ch) || 
           $httpStatusCode == 400 ||
           $httpStatusCode !== 200)
        {
          $this->errorMessage = 'An unexpected behavior occured with the Scones taggers.';
          $this->errorMessageDebug = htmlentities($data);
          return FALSE;
        }
        else
        {
//          $json = json_decode($data);
          
          $this->ws->annotatedDocument = $data;
        }            
      }     
    }
  }
?>
