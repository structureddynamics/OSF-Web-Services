<?php

/*! @ingroup WsCrud Crud Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\crud\delete\index.php
    @brief Entry point of a query for the Delete web service
 */
 
include_once("../../../../SplClassLoader.php");  
 
use \StructuredDynamics\structwsf\ws\crud\delete\CrudDelete;

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "64M");

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

// Dataset where to index the resource
$dataset = "";

if(isset($_GET['dataset']))
{
  $dataset = $_GET['dataset'];
}

// URI of the resource to delete
$uri = "";

if(isset($_GET['uri']))
{
  $uri = $_GET['uri'];
}

$mode = "soft";

if(isset($_GET['mode']))
{
  $mode = $_GET['mode'];
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

$ws_cruddelete = new CrudDelete($uri, $dataset, $interface, $version, $mode);

$ws_cruddelete->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_cruddelete->process();

$ws_cruddelete->ws_respond($ws_cruddelete->ws_serialize());

//@}

?>