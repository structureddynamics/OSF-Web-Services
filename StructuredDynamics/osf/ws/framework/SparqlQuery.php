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

class SparqlQuery
{
  private $_endpoint = '';
  
  private $_resultset;
  
  private $_nb_bindings = -1;
  private $_current_binding = -1;
  private $_errorMessage = '';
  
  function __construct($endpoint)
  {
    $this->_endpoint = $endpoint;
  }  
  
  function __destruct() { }
  
  public function query($query)
  {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $this->_endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'query='.urlencode($query));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/sparql-results+json"));
        
    $data = curl_exec($ch);

    $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);     
    
    if(curl_errno($ch) || $httpStatusCode == 400)
    {
      $this->_errorMessage = $data;

      return FALSE;
    }
    else
    {      
      $this->_resultset = json_decode($data, true);
      
      $this->_resultset = $this->_resultset['results']['bindings'];
      $this->_current_binding = -1;
      $this->_nb_bindings = count($this->_resultset);

      return TRUE;
    }    
  }
  
  public function fetch_binding()
  {
    // Move the bindings cursor by one
    $this->_current_binding++;
    
    if($this->_current_binding >= $this->_nb_bindings)
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
  
  public function value($var, $full = FALSE)
  {
    if(array_key_exists($var, $this->_resultset[$this->_current_binding]))
    {
      if($full)
      {
        return($this->_resultset[$this->_current_binding][$var]);  
      }
      else
      {
        return($this->_resultset[$this->_current_binding][$var]['value']);
      }
    }
    else
    {
      return(FALSE);
    }
  }
  
  public function error()
  {
    if(!empty($this->_errorMessage))
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
    return($this->_errorMessage);
  }
}
  
?>