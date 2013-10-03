<?php

/*! @ingroup UserAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\auth\registrar\user\index.php
    @brief Entry point of a query for the Auth Registration User service
 */

include_once("../../../../../SplClassLoader.php"); 
 
use \StructuredDynamics\structwsf\ws\auth\registrar\user\AuthRegistrarUser;

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

// URI of the User to create
$user_uri = "";

if(isset($_GET['user_uri']))
{
  $user_uri = $_GET['user_uri'];
}

// URI of the Group where to register this user
$group_uri = "";

if(isset($_GET['group_uri']))
{
  $group_uri = $_GET['group_uri'];
}

// Can be 'join' or 'leave'
$action = "";

if(isset($_GET['action']))
{
  $action = $_GET['action'];
}

$ws_aru = new AuthRegistrarUser($user_uri, $group_uri, $action, $interface, $version);

$ws_aru->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                    (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_aru->process();

$ws_aru->ws_respond($ws_aru->ws_serialize());

//@}

?>