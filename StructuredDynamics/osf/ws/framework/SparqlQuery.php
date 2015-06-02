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

abstract class SparqlQuery
{
  protected $resultset;
  
  protected $wsf;
  
  abstract public function query($query);
  
  abstract public function fetch_binding();
  
  abstract public function value($var);
  
  abstract public function error();
  
  abstract public function errormsg();
  
  abstract public function close();
}
  
?>