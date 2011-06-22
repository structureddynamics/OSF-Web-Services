<?php

/*! @ingroup WsOntology Ontology Management Web Service  */
//@{

/*! @file \ws\ontology\delete\index.php
   @brief Entry point of a query for the Ontology Delete web service
   @details Each time a query is sent to this web service, this index.php script will create the web service class
           and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */

ini_set("display_errors",
  "Off"); // Don't display errors to the users. Set it to "On" to see errors for debugging purposes.

ini_set("memory_limit", "256M");

// Database connectivity procedures
include_once("../../framework/db.php");

// Content negotion class
include_once("../../framework/Conneg.php");

// The Web Service parent class
include_once("../../framework/WebService.php");

include_once("../../framework/ProcessorXML.php");

include_once("OntologyDelete.php");

include_once("../../auth/validator/AuthValidator.php");

include_once("../../framework/Logger.php");

include_once("../../framework/OWLOntology.php");

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

$ws_ontologydelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
  $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);


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
    if(!isset($parameters["uri"]) || $parameters["uri"] == "")
    {
      $ws_ontologydelete->returnError(400, "Bad Request", "_201");
    }
    
    $ws_ontologydelete->deleteClass($parameters["uri"]);
  break;

  // Delete an annotation, object or datatype property from the ontology
  case "deleteproperty":
    if(!isset($parameters["uri"]) || $parameters["uri"] == "")
    {
      $ws_ontologydelete->returnError(400, "Bad Request", "_201");
    }

    $ws_ontologydelete->deleteProperty($parameters["uri"]);
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

$logger = new Logger("ontology_delete", $requester_ip,
  "?ontology=" . substr($ontology, 0, 64) . "&mime=" . $mime . "&registered_ip=" . $registered_ip
  . "&requester_ip=$requester_ip",
  $_SERVER['HTTP_ACCEPT'], $start_datetime, $totaltime, $ws_ontologydelete->pipeline_getResponseHeaderStatus(),
  $_SERVER['HTTP_USER_AGENT']);


//@}

?>