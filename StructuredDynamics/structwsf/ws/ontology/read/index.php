<?php

/** @defgroup WsCrud Crud Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\crud\read\index.php
    @brief Entry point of a query for the Crud Read web service
 */
 
include_once("../../../../SplClassLoader.php");   
 
use \StructuredDynamics\structwsf\ws\ontology\read\OntologyRead;

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "256M");

set_time_limit(2700);

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

// Language of the annotations
$lang = "en";

if(isset($_POST['lang']))
{
  $lang = $_POST['lang'];
}

$reasoner = "true";

if(isset($_POST['reasoner']))
{
  $reasoner = $_POST['reasoner'];
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

$ws_or = new OntologyRead($ontology, $function, $params, $interface, $version, $lang);

$ws_or->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 
                  
if(strtolower($reasoner) == "true" ||
   strtolower($reasoner) == "on"   ||
   strtolower($reasoner) == "1" ) 
{
  $ws_or->useReasoner();     
} 
else
{
  $ws_or->stopUsingReasoner();
}
  
$ws_or->process();

$ws_or->ws_respond($ws_or->ws_serialize());

//@}

?>