<?php

/*! @ingroup WsAuth Authentication / Registration Web Service  */
//@{

/*! @file \StructuredDynamics\osf\ws\auth\lister\index.php
    @brief Entry point of a query for the Auth Validator web service
 */
 
include_once("../../../../SplClassLoader.php"); 
  
use \StructuredDynamics\osf\ws\auth\lister\AuthLister;
 
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

// Type of the thing to be listed
$mode = "dataset";

if(isset($_GET['mode']))
{
  $mode = $_GET['mode'];
}

$group = "";

if(isset($_GET['group']))
{
  $group = $_GET['group'];
}

$dataset = "";

if(isset($_GET['dataset']))
{
  $dataset = $_GET['dataset'];
}


$target_webservice = "all";

if(isset($_GET['target_webservice']))
{
  $target_webservice = $_GET['target_webservice'];
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

$ws_al = new AuthLister($mode, $dataset, $group, $target_webservice, $interface, $version);

$ws_al->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_al->process();

$ws_al->ws_respond($ws_al->ws_serialize());

//@}

?>