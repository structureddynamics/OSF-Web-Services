<?php

/*! @ingroup WsCrud Crud Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\crud\update\index.php
    @brief Entry point of a query for the Update web service
 */
 
include_once("../../../../SplClassLoader.php");   
 
use \StructuredDynamics\structwsf\ws\crud\update\CrudUpdate;
use \StructuredDynamics\structwsf\ws\framework\Logger; 

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "64M");

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

// Dataset where to index the resource
$dataset = "";

if(isset($_POST['dataset']))
{
  $dataset = $_POST['dataset'];
}

// RDF document where resource(s) to be added are described
$document = "";

if(isset($_POST['document']))
{
  $document = $_POST['document'];
}

// Mime of the RDF document serialization
$mime = "";

if(isset($_POST['mime']))
{
  $mime = $_POST['mime'];
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

$ws_crudupdate = new CrudUpdate($document, $mime, $dataset, $registered_ip, $requester_ip, $interface);

$ws_crudupdate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_crudupdate->process();

$ws_crudupdate->ws_respond($ws_crudupdate->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);


//echo $totaltime."ms\n";

if($ws_crudupdate->isLoggingEnabled())
{
  $logger = new Logger("crud_update", 
                       $requester_ip,
                       "?document=" . substr($document, 0, 64) . 
                       "&mime=" . $mime . 
                       "&dataset=" . $dataset . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip", 
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime,
                       $ws_crudupdate->pipeline_getResponseHeaderStatus(), 
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>