<?php

/*! @ingroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \ws\auth\registrar\ws\index.php
   @brief Entry point of a query for the Auth Registration web service
   @details Each time a query is sent to this web service, this index.php script will create the web service class
           and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */

ini_set("display_errors",
  "Off"); // Don't display errors to the users. Set it to "On" to see errors for debugging purposes.

ini_set("memory_limit", "64M");


// Database connectivity procedures
include_once("../../../framework/db.php");

// Content negotion class
include_once("../../../framework/Conneg.php");

// The Web Service parent class
include_once("../../../framework/WebService.php");

include_once("../../../framework/ProcessorXML.php");

include_once("AuthRegistrarWs.php");
include_once("../../validator/AuthValidator.php");

include_once("../../../framework/Logger.php");


// Title of the service
$title = "";

if(isset($_GET['title']))
{
  $title = $_GET['title'];
}

// Endpoint URL of the service
$endpoint = "";

if(isset($_GET['endpoint']))
{
  $endpoint = $_GET['endpoint'];
}


// Crud usage of the service
$crud_usage = "";

if(isset($_GET['crud_usage']))
{
  $crud_usage = $_GET['crud_usage'];
}

// URI of the service
$ws_uri = "";

if(isset($_GET['ws_uri']))
{
  $ws_uri = $_GET['ws_uri'];
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

// Optional IP
$registered_ip = "";

if(isset($_GET['registered_ip']))
{
  $registered_ip = $_GET['registered_ip'];
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

$ws_arws = new AuthRegistrarWs($title, $endpoint, $crud_usage, $ws_uri, $registered_ip, $requester_ip);

$ws_arws->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_arws->process();

$ws_arws->ws_respond($ws_arws->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_arws->isLoggingEnabled())
{
  $logger = new Logger("auth_registrar_ws", 
                       $requester_ip,
                       "?title=" . substr($mode, 0, 64) . 
                       "&endpoint=" . $endpoint . 
                       "&crud_usage=" . $crud_usage . 
                       "&ws_uri=" . $ws_uri . 
                       "&requester_ip=$requester_ip",
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER[''] : "HTTP_ACCEPT"),
                       $start_datetime, 
                       $totaltime, 
                       $ws_arws->pipeline_getResponseHeaderStatus(),
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER[''] : "HTTP_USER_AGENT"));
}

//@}

?>