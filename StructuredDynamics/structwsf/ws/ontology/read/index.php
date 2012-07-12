<?php

/** @defgroup WsCrud Crud Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\crud\read\index.php
    @brief Entry point of a query for the Crud Read web service
 */
 
include_once("../../../../SplClassLoader.php");   
 
use \StructuredDynamics\structwsf\ws\ontology\read\OntologyRead;
use \StructuredDynamics\structwsf\ws\framework\Logger; 

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

$reasoner = "true";

if(isset($_POST['reasoner']))
{
  $reasoner = $_POST['reasoner'];
}
 
// Optional IP
$registered_ip = "";

if(isset($_POST['registered_ip']))
{
  $registered_ip = $_POST['registered_ip'];
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

$ws_or = new OntologyRead($ontology, $function, $params, $registered_ip, $requester_ip, 
                          $interface, $version);

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

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_or->isLoggingEnabled())
{
  $logger = new Logger( "ontology_read", 
                        $requester_ip,
                        "?ontology=" . $ontology . 
                        "&function=" . $function . 
                        "&parameters=" . $params . 
                        "&registered_ip="
                        . $registered_ip . 
                        "&requester_ip=$requester_ip",
                        (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                        $start_datetime, 
                        $totaltime,
                        $ws_or->pipeline_getResponseHeaderStatus(), 
                        (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>