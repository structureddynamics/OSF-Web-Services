<?php

/*! @ingroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\auth\registrar\ws\index.php
    @brief Entry point of a query for the Auth Registration web service
 */

include_once("../../../../../SplClassLoader.php"); 
 
use \StructuredDynamics\structwsf\ws\auth\registrar\ws\AuthRegistrarWs;

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


// Title of the service
$title = "";

if(isset($_GET['title']))
{
  $title = $_GET['title'];
}

// Endpoint URL of the service
$endpoint = "";

if(isset($_GET['endpoint']))
{
  $endpoint = $_GET['endpoint'];
}


// Crud usage of the service
$crud_usage = "";

if(isset($_GET['crud_usage']))
{
  $crud_usage = $_GET['crud_usage'];
}

// URI of the service
$ws_uri = "";

if(isset($_GET['ws_uri']))
{
  $ws_uri = $_GET['ws_uri'];
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

$ws_arws = new AuthRegistrarWs($title, $endpoint, $crud_usage, $ws_uri, nterface, $version);

$ws_arws->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_arws->process();

$ws_arws->ws_respond($ws_arws->ws_serialize());

//@}

?>