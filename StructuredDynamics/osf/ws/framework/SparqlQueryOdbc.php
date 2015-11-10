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

class SparqlQueryOdbc extends \StructuredDynamics\osf\ws\framework\SparqlQuery
{  
  /** Database host address */
  private $host;
  
  /** Database username */
  private $username;
  
  /** Database password */
  private $password;
  
  /** Database DSN */
  private $dsn;
  
  /** Database connection link */
  private $link;
  
  private $query;
  
  private $error = '';
  
  function __construct(&$wsf)
  {
    $this->wsf = $wsf;
    
    $this->username = $wsf->triplestore_username;
    $this->password = $wsf->triplestore_password;
    $this->dsn = $wsf->triplestore_dsn;
    $this->host = $wsf->triplestore_host;
    
    ini_set("odbc.default_cursortype", "0");
    $this->link = odbc_connect($this->dsn, $this->username, $this->password);
    
    if($this->link === FALSE)
    {
      $this->error = 'Connection error';
    }
  }  
  
  public function close(){}
  
  
  public function query($query)
  {
    $this->error = '';
    
    if(stripos($query, "DB.DBA.TTLP_MT") === FALSE &&
       stripos($query, "DB.DBA.RDF_LOAD_RDFXML_MT") === FALSE)
    {
      $this->query = "sparql " . $query;
    }
    else
    {
      $this->query = $query;
    }
    
    if($this->wsf->virtuoso_disable_transaction_log && 
       (stripos($query, "DB.DBA.TTLP_MT") !==  FALSE ||
        stripos($query, "DB.DBA.RDF_LOAD_RDFXML_MT") !== FALSE))
    {
      $this->resultset = odbc_exec($this->link, 'log_enable(2)');
    }
    
    $this->resultset = odbc_exec($this->link, $this->query);
    
    if($this->resultset === FALSE)
    {
      $this->error = 'Query execution error';
      
      return(FALSE);
    }
    
    return(TRUE);
  }
  
  public function fetch_binding()
  {
    return(odbc_fetch_row($this->resultset));
  }

  public function value($var)
  {    
    $value = '';
    $fieldID = odbc_field_num($this->resultset, $var);
    
    if($fieldID === FALSE)
    {
      $this->error = 'Field numumber fetching error';
      
      return(FALSE);
    }
    
    $value = odbc_result($this->resultset, $fieldID);
    
    if($value === FALSE)
    {
      $this->error = 'Value fetching error. Field ID: '. $fieldID;
      
      return(FALSE);
    }
    
    // Handle possible big values
    if($value != "" && 
       odbc_field_len($this->resultset, $fieldID) > ini_get("odbc.defaultlrl"))
    {
      while(($chunk = odbc_result($this->resultset, $fieldID)) !== FALSE)
      {
        $value .= $chunk;
      } 
    }

    return($value);       
  }
  
  public function error()
  {
    if(!empty($this->error)/* || !empty(odbc_error($this->link))*/)
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
    return(odbc_errormsg($this->link) . "\n\n" . $this->error);
  }  
}
  
?>