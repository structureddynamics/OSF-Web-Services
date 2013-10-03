<?php

/** @defgroup WsSparql SPARQL Web Service */
//@{

/*! @file \StructuredDynamics\osf\ws\sparql\index.php
    @brief Entry point of a query for the SPARQL web service
 */
 
include_once("../../../SplClassLoader.php");   
 
use \StructuredDynamics\osf\ws\sparql\Sparql;

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "256M");

if ($_SERVER['REQUEST_METHOD'] != 'POST') 
{
  header("HTTP/1.1 405 Method Not Allowed");  
  die;
}

// Interface to use for this query
$interface = "default";

if(isset($_POST['interface']))
{
  $interface = $_POST['interface'];
}

// Version of the requested interface to use for this query
$version = "";

if(isset($_POST['version']))
{
  $version = $_POST['version'];
}

// Full text query supporting the Lucene operators
$query = "";

if(isset($_POST['query']))
{
  $query = $_POST['query'];
}

// Datasets to target with the sparql query
$dataset = "";

if(isset($_POST['dataset']))
{
  $dataset = $_POST['dataset'];
}
  
// Datasets to target with the sparql query (optional) -- only used for consistency with the SPARQL protocol
if(isset($_POST['default-graph-uri']) && $dataset == "")
{
  $dataset = $_POST['default-graph-uri'];
}

// Datasets to target with the sparql query (optional) -- only used for consistency with the SPARQL protocol
if(isset($_POST['named-graph-uri']) && $dataset == "")
{
  $dataset = $_POST['named-graph-uri'];
}

$parameters = "";

if(isset($_SERVER['REQUEST_URI']))
{
  $parameters = $_SERVER['REQUEST_URI'];

  $pos = strpos($parameters, "?");

  if($pos !== FALSE)
  {
    $parameters = substr($parameters, $pos, strlen($parameters) - $pos);
  }
}
elseif(isset($_SERVER['PHP_SELF']))
{
  $parameters = $_SERVER['PHP_SELF'];
}

$ws_sparql = new Sparql($query, $dataset, $interface, $version);

$ws_sparql->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_sparql->process();

$ws_sparql->ws_respond($ws_sparql->ws_serialize());

//@}

?>