<?php

/*! @ingroup WsDataset Dataset Management Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\dataset\read\index.php
    @brief Entry point of a query for the Dataset Read web service
 */
 
include_once("../../../../SplClassLoader.php");   

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "64M");

use \StructuredDynamics\structwsf\ws\dataset\read\DatasetRead;

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

// URI of the dataset to get the description of
// "all" means all datasets visible to that user
$uri = "";

if(isset($_GET['uri']))
{
  $uri = $_GET['uri'];
}

// Optional Meta information
$meta = "false";

if(isset($_GET['meta']))
{
  $meta = $_GET['meta'];
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

$ws_dr = new DatasetRead($uri, $meta, $interface, $version);

$ws_dr->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_dr->process();

$ws_dr->ws_respond($ws_dr->ws_serialize());

//@}

?>