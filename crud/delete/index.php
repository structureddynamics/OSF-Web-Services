<?php

/*! @ingroup WsCrud Crud Web Service  */
//@{ 

/*! @file \ws\crud\delete\index.php
	 @brief Entry point of a query for the Delete web service
	 @details Each time a query is sent to this web service, this index.php script will delete the web service class
				   and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
 */

ini_set("display_errors", "Off");		// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("memory_limit","64M");


// Database connectivity procedures
include_once("../../framework/db.php");

// Content negotion class
include_once("../../framework/Conneg.php");

// The Web Service parent class
include_once("../../framework/WebService.php");
include_once("../../framework/Namespaces.php");

include_once("../../framework/ProcessorXML.php");

include_once("../../framework/Solr.php");


include_once("CrudDelete.php");
include_once("../../auth/validator/AuthValidator.php");
include_once("../../dataset/read/DatasetRead.php");

include_once("../../framework/Logger.php");


// IP being registered
$registered_ip = "";

if(isset($_GET['registered_ip'])) 
{
    $registered_ip = $_GET['registered_ip'];
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

$ws_cruddelete = new CrudDelete($uri, $dataset, $registered_ip, $requester_ip);

$ws_cruddelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

$ws_cruddelete->process();

$ws_cruddelete->ws_respond($ws_cruddelete->ws_serialize());


$mtime = microtime(); 
$mtime = explode(" ", $mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 


$logger = new Logger("crud_delete", $requester_ip, "?uri=".$uri."&dataset=".$dataset."&registered_ip=".$registered_ip."&requester_ip=$requester_ip", $_SERVER['HTTP_ACCEPT'], $start_datetime, $totaltime, $ws_cruddelete->pipeline_getResponseHeaderStatus(), $_SERVER['HTTP_USER_AGENT']);


	//@} 


?>