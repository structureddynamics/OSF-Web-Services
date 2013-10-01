<?php

/*! @ingroup WsOntology Ontology Management Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\ontology\delete\index.php
    @brief Entry point of a query for the Ontology Delete web service
 */
 
include_once("../../../../SplClassLoader.php");   
 
use \StructuredDynamics\structwsf\ws\ontology\delete\OntologyDelete;

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

$ws_ontologydelete = new OntologyDelete($ontology, $interface, $version);

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

//@}

?>