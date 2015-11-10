<?php

/** @defgroup WsFramework Framework for the Web Services */
//@{

/*! @file \StructuredDynamics\osf\ws\framework\SparqlQuery.php
    @brief SPARQL Endpoint querying tool
*/

namespace StructuredDynamics\osf\ws\framework; 

/** SPARQL Database connector to various triple store SPARQL endpoints.
            
    @author Frederick Giasson, Structured Dynamics LLC.
*/

class SparqlQueryHttp extends \StructuredDynamics\osf\ws\framework\SparqlQuery
{
  private $nb_bindings = -1;
  private $current_binding = -1;
  private $errorMessage = '';

  private $http_status_code = 200;
  private $data = '';
  private $ch;  
  
  function __construct(&$wsf)
  {
    $this->wsf = $wsf;
     
    $endpoint = 'http://'. $this->wsf->triplestore_host . ':' .
                           $this->wsf->triplestore_port . '/' .
                           $this->wsf->sparql_endpoint;
    
    $this->ch = curl_init();
    
    curl_setopt($this->ch, CURLOPT_URL, $endpoint);
    curl_setopt($this->ch, CURLOPT_POST, 1);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("Accept: application/sparql-results+json"));
  }  
  
  function __destruct() 
  { 
    $this->close();
  }
  
  public function close()
  {
    curl_close($this->ch);
    unset($this);
  }
  
  public function query($query)
  {
    curl_setopt($this->ch, CURLOPT_POSTFIELDS, 'query='.urlencode($query));
        
    $this->data = curl_exec($this->ch);

    $this->http_status_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);     
    
    if(curl_errno($this->ch) || ($this->http_status_code < 200 || $this->http_status_code >= 300))
    {
      $this->errorMessage = $this->data;
      
      // Reinitialize
      $this->nb_bindings = -1;
      $this->current_binding = -1;      
      $this->resultset = null;
      $this->data = '';

      return FALSE;
    }
    else
    {      
      $this->resultset = json_decode($this->data, true);
      
      $this->resultset = $this->resultset['results']['bindings'];
      $this->current_binding = -1;
      $this->nb_bindings = count($this->resultset);
      
      // Reinitialize
      $this->errorMessage = '';
      $this->data = '';

      return TRUE;
    }       
  }
  
  public function fetch_binding()
  {
    // Move the bindings cursor by one
    $this->current_binding++;
    
    if($this->current_binding >= $this->nb_bindings)
    {
      // No more bindings available
      return(FALSE);
    }
    else
    {
      // There still exists bindings in the resultset
      return(TRUE);
    }
  }
  
  public function value($var)
  {
    if(array_key_exists($var, $this->resultset[$this->current_binding]))
    {
      return($this->resultset[$this->current_binding][$var]['value']);
    }
    else
    {
      return(FALSE);
    }
  }
  
  public function error()
  {
    if(!empty($this->errorMessage))
    {
      return(TRUE);
    }
    else
    {
      return(FALSE);
    }
  }
  
  public function errormsg()
  {
    return($this->errorMessage);
  }
  
  public function http_status_code()
  {
    return($this->http_status_code);
  }
}
  
?>