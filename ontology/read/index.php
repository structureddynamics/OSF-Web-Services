<?php

/*! @defgroup WsCrud Crud Web Service */
//@{

/*! @file \ws\crud\read\index.php
   @brief Entry point of a query for the Crud Read web service
   @details Each time a query is sent to this web service, this index.php script will read the web service class
           and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */

ini_set("display_errors", "Off"); // Don't display errors to the users. Set it to "On" to see errors for debugging purposes.

ini_set("memory_limit", "256M");

set_time_limit(2700);

// Database connectivity procedures
include_once("../../framework/db.php");

// Content negotion class
include_once("../../framework/Conneg.php");

// The Web Service parent class
include_once("../../framework/WebService.php");

include_once("../../framework/ProcessorXML.php");
include_once("../../framework/Namespaces.php");

include_once("OntologyRead.php");
include_once("../../auth/validator/AuthValidator.php");

include_once("../../framework/Logger.php");

include_once("../../framework/OWLOntology.php");

include_once("../../framework/ClassHierarchy.php");  
include_once("../../framework/PropertyHierarchy.php");  

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

$reasoner = "false";

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

$ws_or = new OntologyRead($ontology, $function, $params, $registered_ip, $requester_ip);

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