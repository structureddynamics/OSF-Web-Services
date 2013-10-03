<?php

/*! @ingroup WsOntology Ontology Management Web Service  */
//@{

/*! @file \StructuredDynamics\osf\ws\ontology\create\index.php
    @brief Entry point of a query for the Ontology Create web service
 */
 
include_once("../../../../SplClassLoader.php");   
 
use \StructuredDynamics\osf\ws\ontology\create\OntologyCreate;

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

// Ontology RDF document where resource(s) to be added are described
$ontologyUri = "";

if(isset($_POST['uri']))
{
  $ontologyUri = $_POST['uri'];
}

// If this parameter is set, the Ontology Create web service endpoint will index
// the ontology in the normal OSF data stores. That way, the ontology
// will also become queryable via the standard services such as Search and Browse.
$advancedIndexation = FALSE;

if(isset($_POST['advancedIndexation']))
{
  if(strtolower($_POST['advancedIndexation']) == "false")
  {
    $advancedIndexation = FALSE;
  }
  else
  {
    $advancedIndexation = TRUE;
  }  
}

$reasoner = "true";

if(isset($_POST['reasoner']))
{
  if(strtolower($_POST['reasoner']) == "false")
  {
    $reasoner = FALSE;
  }
  else
  {
    $reasoner = TRUE;
  }  
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

$ws_ontologycreate =  new OntologyCreate($ontologyUri, $interface, $version);

$ws_ontologycreate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

// Set the advanced indexation
$ws_ontologycreate->setAdvancedIndexation($advancedIndexation);

// set reasoner
if($reasoner)
{
  $ws_ontologycreate->useReasonerForAdvancedIndexation();
}
else
{
  $ws_ontologycreate->stopUsingReasonerForAdvancedIndexation();
}
  
  
$ws_ontologycreate->createOntology();

$ws_ontologycreate->ws_respond($ws_ontologycreate->ws_serialize());

//@}

?>