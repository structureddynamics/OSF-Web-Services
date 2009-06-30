<?php

/*! @ingroup WsAuth Authentication / Registration Web Service  */
//@{ 

/*! @file \ws\auth\registrar\access\index.php
	 @brief Entry point of a query for the Auth Registration web service
	 @details Each time a query is sent to this web service, this index.php script will create the web service class
				   and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
 */


ini_set("memory_limit","64M");


// Database connectivity procedures
include_once("../../../framework/db.php");

// Content negotion class
include_once("../../../framework/Conneg.php");

// The Web Service parent class
include_once("../../../framework/WebService.php");

include_once("../../../framework/ProcessorXML.php");

include_once("AuthRegistrarAccess.php");
include_once("../../validator/AuthValidator.php");

include_once("../../../framework/Logger.php");


// IP being registered
$registered_ip = "";

if(isset($_POST['registered_ip'])) 
{
    $registered_ip = $_POST['registered_ip'];
}

// CRUD access
$crud = "";

if(isset($_POST['crud'])) 
{
    $crud = $_POST['crud'];
}


// Web service access(es)
$ws_uris = "";

if(isset($_POST['ws_uris'])) 
{
    $ws_uris = $_POST['ws_uris'];
}

// Dataset access
$dataset = "";

if(isset($_POST['dataset'])) 
{
    $dataset = $_POST['dataset'];
}

// Type of action
$action = "create";

if(isset($_POST['action'])) 
{
    $action = $_POST['action'];
}

// URI of the access to update if action=update
$target_access_uri = "";

if(isset($_POST['target_access_uri'])) 
{
    $target_access_uri = $_POST['target_access_uri'];
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

$ws_araccess = new AuthRegistrarAccess($crud, $ws_uris, $dataset, $action, $target_access_uri, $registered_ip, $requester_ip);

$ws_araccess->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

$ws_araccess->process();

$ws_araccess->ws_respond($ws_araccess->ws_serialize());


$mtime = microtime(); 
$mtime = explode(" ", $mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 

$logger = new Logger("auth_registrar_access", $requester_ip, "?crud=".$crud."&ws_uris=".$ws_uris."&dataset=".$dataset."$action=".$action."&target_access_uri=".$target_access_uri."&registered_ip=".$registered_ip."&requester_ip=$requester_ip", $_SERVER['HTTP_ACCEPT'], $start_datetime, $totaltime, $ws_araccess->pipeline_getResponseHeaderStatus(), $_SERVER['HTTP_USER_AGENT']);


	//@} 


?>