<?php

/*! @ingroup WsDataset Dataset Management Web Service  */
//@{

/*! @file \ws\dataset\update\index.php
   @brief Entry point of a query for the Dataset Update web service
   @details Each time a query is sent to this web service, this index.php script will update the web service class
           and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */

ini_set("display_errors",
  "Off"); // Don't display errors to the users. Set it to "On" to see errors for debugging purposes.

ini_set("memory_limit", "64M");


// Database connectivity procedures
include_once("../../framework/db.php");

// Content negotion class
include_once("../../framework/Conneg.php");

// The Web Service parent class
include_once("../../framework/WebService.php");

include_once("../../framework/ProcessorXML.php");

include_once("DatasetUpdate.php");
include_once("../../auth/validator/AuthValidator.php");
include_once("../../auth/registrar/access/AuthRegistrarAccess.php");

include_once("../../framework/Logger.php");


// URI for the dataset
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

  if($title == "")
  {
    $title = "-delete-";
  }
}

// Description of the dataset (optional)
$description = "";

if(isset($_POST['description']))
{
  $description = $_POST['description'];

  if($description == "")
  {
    $description = "-delete-";
  }
}

// List of contributor URIs (optional)
$contributors = "";

if(isset($_POST['contributors']))
{
  $contributors = $_POST['contributors'];

  if($contributors == "")
  {
    $contributors = "-delete-";
  }
}

// Modification date (optional)
$modified = "";

if(isset($_POST['modified']))
{
  $modified = $_POST['modified'];

  if($modified == "")
  {
    $modified = "-delete-";
  }
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

$ws_du = new DatasetUpdate($uri, $title, $description, $contributors, $modified, $registered_ip, $requester_ip);

$ws_du->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_du->process();

$ws_du->ws_respond($ws_du->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_du->isLoggingEnabled())
{
  $logger = new Logger("dataset_update", 
                       $requester_ip,
                       "?uri=" . $uri . 
                       "&title=" . substr($title, 0, 64) . 
                       "&description=" . substr($description, 0, 64) . 
                       "&modified=" . $modified . 
                       "&requester_ip=$requester_ip", 
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime,
                       $ws_du->pipeline_getResponseHeaderStatus(), 
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>