<?php

/*! @ingroup WsDataset Dataset Management Web Service  */
//@{

/*! @file \StructuredDynamics\osf\ws\dataset\create\index.php
    @brief Entry point of a query for the Dataset Create web service
 */
 
include_once("../../../../SplClassLoader.php");   

use \StructuredDynamics\osf\ws\dataset\create\DatasetCreate;
 
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

// Web services that can be used to access and manage that dataset. It is list of ";" separated Web services URI
$webservices = "all";

if(isset($_POST['webservices']))
{
  $webservices = $_POST['webservices'];
}

$ws_dc = new DatasetCreate($uri, $title, $description, $creator, $webservices, $interface, $version);

$ws_dc->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_dc->process();

$ws_dc->ws_respond($ws_dc->ws_serialize());

//@}

?>