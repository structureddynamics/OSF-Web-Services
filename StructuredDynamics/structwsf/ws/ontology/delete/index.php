<?php

/*! @ingroup WsOntology Ontology Management Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\ontology\delete\index.php
    @brief Entry point of a query for the Ontology Delete web service
 */
 
include_once("../../../../SplClassLoader.php");   
 
use \StructuredDynamics\structwsf\ws\ontology\delete\OntologyDelete;
use \StructuredDynamics\structwsf\ws\framework\Logger; 

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "256M");

if ($_SERVER['REQUEST_METHOD'] != 'POST') 
{
    header("HTTP/1.1 405 Method Not Allowed");  
    die;
}

// IP being registered
$registered_ip = "";

if(isset($_POST['registered_ip']))
{
  $registered_ip = $_POST['registered_ip'];
}

// URI of the the ontology to query
$ontology = "";

if(isset($_POST['ontology']))
{
  $ontology = $_POST['ontology'];
}

// The function to query via the webservice
$function = "";

if(isset($_POST['function']))
{
  $function = $_POST['function'];
}

// The parameters of the function to use
$params = "";

if(isset($_POST['parameters']))
{
  $params = $_POST['parameters'];
}

$mtime = microtime();
$mtime = explode(' ', $mtime);
$mtime = $mtime[1] + $mtime[0];
$starttime = $mtime;

$start_datetime = date("Y-m-d h:i:s");

$requester_ip = "0.0.0.0";

if(isset($_SERVER['REMOTE_ADDR']))
{
  $requester_ip = $_SERVER['REMOTE_ADDR'];
}

$ws_ontologydelete = new OntologyDelete($ontology, $registered_ip, $requester_ip);

$ws_ontologydelete->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 


$params = explode(";", $params);
$parameters = array();

foreach($params as $param)
{
  $p = explode("=", $param);

  $parameters[$p[0]] = $p[1];
}
  
switch(strtolower($function))
{
  case "deleteontology":
    $ws_ontologydelete->deleteOntology();
  break;

  case "deleteclass":
    $ws_ontologydelete->deleteClass($parameters["uri"]);
  break;

  // Delete an annotation, object or datatype property from the ontology
  case "deleteproperty":
    $ws_ontologydelete->deleteProperty($parameters["uri"]);
  break;

  case "deletenamedindividual":
    $ws_ontologydelete->deleteNamedIndividual($parameters["uri"]);
  break;
  
  
  default:
    $ws_ontologydelete->returnError(400, "Bad Request", "_200");
  break;
}  

$ws_ontologydelete->ws_respond($ws_ontologydelete->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_ontologydelete->isLoggingEnabled())
{
  $logger = new Logger("ontology_delete", 
                       $requester_ip,
                       "?ontology=" . substr($ontology, 0, 64) . 
                       "&mime=" . $mime . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip",
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime, 
                       $ws_ontologydelete->pipeline_getResponseHeaderStatus(),
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>