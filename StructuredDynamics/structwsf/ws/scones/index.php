<?php

/** @defgroup WsScones SCONES Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\scones\index.php
    @brief Entry point of a query for the SCONES web service
 */
 
include_once("../../../SplClassLoader.php");   
 
use \StructuredDynamics\structwsf\ws\scones\Scones;
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

$ws_scones = new Scones($document, $docmime, $application, $registered_ip, $requester_ip, 
                        $interface, $version);

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