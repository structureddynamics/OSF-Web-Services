<?php

/*! @defgroup WsScones SCONES Web Service */
//@{

/*! @file \ws\scones\index.php
   @brief Entry point of a query for the SCONES web service
   @details Each time a query is sent to this web service, this index.php script will read the web service class
           and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */

 // Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "256M");


// Database connectivity procedures
include_once("../framework/db.php");

// Content negotion class
include_once("../framework/Conneg.php");

// The Web Service parent class
include_once("../framework/WebService.php");
include_once("../framework/ProcessorXML.php");
include_once("../framework/Namespaces.php");

include_once("Scones.php");
include_once("../auth/validator/AuthValidator.php");

include_once("../framework/Logger.php");

// Document content (in non-binary form)
$document = "";

if(isset($_POST['document']))
{
  $document = $_POST['document'];
}

// Document content's MIME type
$docmime = "text/plain";

if(isset($_POST['docmime']))
{
  $docmime = $_POST['docmime'];
}

/* 
  Name of the GATE application used to perform the tagging. This name is pre-defined by the 
  administrator of the node.
*/
$application = "defaultApplication";

if(isset($_POST['application']))
{
  $application = $_POST['application'];
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

$ws_scones = new Sparql($document, $docmime, $application, $registered_ip, $requester_ip);

$ws_scones->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_scones->process();

$ws_scones->ws_respond($ws_scones->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_scones->isLoggingEnabled())
{
  $logger = new Logger("scones", 
                       $requester_ip,
                       "?document=" . md5($documents) . 
                       "&docmime=" . $docmime . 
                       "&application=" . $application . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip",
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime, 
                       $ws_scones->pipeline_getResponseHeaderStatus(),
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>