<?php

/*! @ingroup GroupAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\osf\ws\auth\registrar\group\index.php
    @brief Entry point of a query for the Auth Registration Group service
 */

include_once("../../../../../SplClassLoader.php"); 
 
use \StructuredDynamics\osf\ws\auth\registrar\group\AuthRegistrarGroup;

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

// ID of the Group to create
$group_uri = "";

if(isset($_GET['group_uri']))
{
  $group_uri = $_GET['group_uri'];
}

// URI of the Application where this group belongs
$app_id = "";

if(isset($_GET['app_id']))
{
  $app_id = $_GET['app_id'];
}

// Can be 'create' or 'delete'
$action = "";

if(isset($_GET['action']))
{
  $action = $_GET['action'];
}

$ws_arg = new AuthRegistrarGroup($group_uri, $app_id, $action, $interface, $version);

$ws_arg->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_arg->process();

$ws_arg->ws_respond($ws_arg->ws_serialize());

//@}

?>