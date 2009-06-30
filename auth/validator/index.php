<?php

/*! @ingroup WsAuth Authentication / Registration Web Service */
//@{ 

/*! @file \ws\auth\validator\index.php
	 @brief Entry point of a query for the Auth Validator web service
	 @details Each time a query is sent to this web service, this index.php script will create the web service class
				   and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
 */


ini_set("memory_limit","64M");


// Database connectivity procedures
include_once("../../framework/db.php");

// Content negotion class
include_once("../../framework/Conneg.php");

// The Web Service parent class
include_once("../../framework/WebService.php");

include_once("../../framework/ProcessorXML.php");

include_once("AuthValidator.php");

include_once("../../framework/Logger.php");


// IP of the requester
$ip = "";

if(isset($_POST['ip'])) 
{
    $ip = $_POST['ip'];
}

// Requested dataset(s)
$datasets = "";

if(isset($_POST['datasets'])) 
{
    $datasets = $_POST['datasets'];
}

// URI of the requested web service
$ws_uri = "";

if(isset($_POST['ws_uri'])) 
{
    $ws_uri = $_POST['ws_uri'];
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

$ws_av = new AuthValidator($ip, $datasets, $ws_uri);

$ws_av->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

$ws_av->process();

$ws_av->ws_respond($ws_av->ws_serialize());


$mtime = microtime(); 
$mtime = explode(" ", $mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 


$logger = new Logger("auth_validator", $requester_ip, "?ip=".$ip."&datasets=".$datasets."&ws_uri=".$ws_uri, $_SERVER['HTTP_ACCEPT'], $start_datetime, $totaltime, $ws_av->pipeline_getResponseHeaderStatus(), $_SERVER['HTTP_USER_AGENT']);


	//@} 


?>