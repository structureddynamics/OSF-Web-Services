<?php

/*! @ingroup WsRevision Revisioning Web Service   */
//@{

/*! @file \StructuredDynamics\structwsf\ws\revision\diff\index.php
    @brief Entry point of a query for the Revision: Diff web service
 */
 
include_once("../../../../SplClassLoader.php"); 
  
use \StructuredDynamics\structwsf\ws\revision\diff\RevisionDiff;
 
// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "64M");

// Check if the HTTP method used by the requester is the good one
if ($_SERVER['REQUEST_METHOD'] != 'GET') 
{
  header("HTTP/1.1 405 Method Not Allowed");  
  die;
}

// Interface to use for this query
$interface = "default";

if(isset($_GET['interface']))
{
  $interface = $_GET['interface'];
}

// Version of the requested interface to use for this query
$version = "";

if(isset($_GET['version']))
{
  $version = $_GET['version'];
}

$registered_ip = "";

if(isset($_GET['registered_ip']))
{
  $registered_ip = $_GET['registered_ip'];
}

$dataset = "";

if(isset($_GET['dataset']))
{
  $dataset = $_GET['dataset'];
}

$lrevuri = "";

if(isset($_GET['lrevuri']))
{
  $lrevuri = $_GET['lrevuri'];
}

$rrevuri = "";

if(isset($_GET['rrevuri']))
{
  $rrevuri = $_GET['rrevuri'];
}

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

$ws_rd = new RevisionDiff($lrevuri, $rrevuri, $dataset, $registered_ip, $requester_ip, $interface, $version);

$ws_rd->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_rd->process();

$ws_rd->ws_respond($ws_rd->ws_serialize());

//@}

?>