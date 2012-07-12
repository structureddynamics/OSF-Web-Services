<?php

/*! @ingroup WsDataset Dataset Management Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\dataset\create\index.php
    @brief Entry point of a query for the Dataset Create web service
 */
 
include_once("../../../../SplClassLoader.php");   

use \StructuredDynamics\structwsf\ws\dataset\create\DatasetCreate;
use \StructuredDynamics\structwsf\ws\framework\Logger; 
 
// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "64M");

if ($_SERVER['REQUEST_METHOD'] != 'POST') 
{
    header("HTTP/1.1 405 Method Not Allowed");  
    die;
}

// Version of the requested interface to use for this query
$version = "";

if(isset($_POST['version']))
{
  $version = $_POST['version'];
}

// Interface to use for this query
$interface = "default";

if(isset($_POST['interface']))
{
  $interface = $_POST['interface'];
}

// Unique ID for the dataset
$uri = "";

if(isset($_POST['uri']))
{
  $uri = $_POST['uri'];
}

// Title of the dataset (optional)
$title = "";

if(isset($_POST['title']))
{
  $title = $_POST['title'];
}

// Description of the dataset (optional)
$description = "";

if(isset($_POST['description']))
{
  $description = $_POST['description'];
}

// URI of the creator of this dataset (optional)
$creator = "";

if(isset($_POST['creator']))
{
  $creator = $_POST['creator'];
}

// Permissions to set for the "public user" to access this new ontology dataset.
$globalPermissions = "False;False;False;False";

if(isset($_POST['globalPermissions']))
{
  $globalPermissions = $_POST['globalPermissions'];
}

// Web services that can be used to access and manage that dataset. It is list of ";" separated Web services URI
$webservices = "all";

if(isset($_POST['webservices']))
{
  $webservices = $_POST['webservices'];
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

if(isset($_POST['registered_ip']))
{
  $registered_ip = $_POST['registered_ip'];
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

$ws_dc = new DatasetCreate($uri, $title, $description, $creator, $registered_ip, $requester_ip, 
                           $webservices, $globalPermissions, $interface, $version);

$ws_dc->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_dc->process();

$ws_dc->ws_respond($ws_dc->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_dc->isLoggingEnabled())
{
  $logger = new Logger("dataset_create", 
                       $requester_ip,
                       "?uri=" . $uri . 
                       "&title=" . substr($title, 0, 64) . 
                       "&description=" . substr($description, 0, 64) . 
                       "&creator=" . $creator . 
                       "&requester_ip=$requester_ip", 
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime,
                       $ws_dc->pipeline_getResponseHeaderStatus(), 
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>