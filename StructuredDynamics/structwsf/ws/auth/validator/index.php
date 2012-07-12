<?php

/*! @ingroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\auth\validator\index.php
    @brief Entry point of a query for the Auth Validator web service
 */

include_once("../../../../SplClassLoader.php"); 
 
use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
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

// Version of the requested interface to use for this query
$version = "";

if(isset($_POST['version']))
{
  $version = $_POST['version'];
}

// IP of the requester
$ip = "";

if(isset($_POST['ip']))
{
  $ip = $_POST['ip'];
}

// Requested dataset(s)
$datasets = "";

if(isset($_POST['datasets']))
{
  $datasets = $_POST['datasets'];
}

// URI of the requested web service
$ws_uri = "";

if(isset($_POST['ws_uri']))
{
  $ws_uri = $_POST['ws_uri'];
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

$ws_av = new AuthValidator($ip, $datasets, $ws_uri, $interface, $version);

$ws_av->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_av->process();

$ws_av->ws_respond($ws_av->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_av->isLoggingEnabled())
{
  $logger = new Logger("auth_validator", 
                       $requester_ip, 
                       "?ip=" . $ip . 
                       "&datasets=" . $datasets . 
                       "&ws_uri=" . $ws_uri,
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime, 
                       $ws_av->pipeline_getResponseHeaderStatus(),
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>