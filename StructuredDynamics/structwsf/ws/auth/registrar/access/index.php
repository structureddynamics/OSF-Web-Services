<?php

/*! @ingroup WsAuth Authentication / Registration Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\auth\registrar\access\index.php
    @brief Entry point of a query for the Auth Registration web service
 */

include_once("../../../../../SplClassLoader.php"); 
 
use \StructuredDynamics\structwsf\ws\auth\registrar\access\AuthRegistrarAccess;
use \StructuredDynamics\structwsf\ws\framework\Logger; 

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "64M");

// Check if the HTTP method used by the requester is the good one
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

// IP being registered
$registered_ip = "";

if(isset($_POST['registered_ip']))
{
  $registered_ip = $_POST['registered_ip'];
}

// CRUD access
$crud = "";

if(isset($_POST['crud']))
{
  $crud = $_POST['crud'];
}


// Web service access(es)
$ws_uris = "";

if(isset($_POST['ws_uris']))
{
  $ws_uris = $_POST['ws_uris'];
}

// Dataset access
$dataset = "";

if(isset($_POST['dataset']))
{
  $dataset = $_POST['dataset'];
}

// Type of action
$action = "create";

if(isset($_POST['action']))
{
  $action = $_POST['action'];
}

// URI of the access to update if action=update
$target_access_uri = "";

if(isset($_POST['target_access_uri']))
{
  $target_access_uri = $_POST['target_access_uri'];
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

$ws_araccess =
  new AuthRegistrarAccess($crud, $ws_uris, $dataset, $action, $target_access_uri, 
                          $registered_ip, $requester_ip, $interface);

$ws_araccess->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_araccess->process();

$ws_araccess->ws_respond($ws_araccess->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_araccess->isLoggingEnabled())
{
  $logger = new Logger("auth_registrar_access", 
                       $requester_ip,
                       "?crud=" . $crud . 
                       "&ws_uris=" . $ws_uris . 
                       "&dataset=" . $dataset . 
                       "$action=" . $action . 
                       "&target_access_uri=" . $target_access_uri . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip", 
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER[''] : "HTTP_ACCEPT"),
                       $start_datetime, 
                       $totaltime, 
                       $ws_araccess->pipeline_getResponseHeaderStatus(), 
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>