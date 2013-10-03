<?php

/*! @ingroup WsDataset Dataset Management Web Service  */
//@{

/*! @file \StructuredDynamics\osf\ws\dataset\update\index.php
    @brief Entry point of a query for the Dataset Update web service
 */

include_once("../../../../SplClassLoader.php");  
 
use \StructuredDynamics\osf\ws\dataset\update\DatasetUpdate;

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

// Version of the requested interface to use for this query
$version = "";

if(isset($_POST['version']))
{
  $version = $_POST['version'];
}

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

$ws_du = new DatasetUpdate($uri, $title, $description, $contributors, $modified, $interface, $version);

$ws_du->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_du->process();

$ws_du->ws_respond($ws_du->ws_serialize());

//@}

?>