<?php

	/*! @defgroup WsBrowse Browse Web Service */
	//@{ 

/*! @file \ws\browse\index.php
	 @brief Entry point of a query for the Browse web service
	 @details Each time a query is sent to this web service, this index.php script will read the web service class
				   and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
 */


ini_set("memory_limit","64M");

// Database connectivity procedures
include_once("../framework/db.php");

// Content negotion class
include_once("../framework/Conneg.php");

// The Web Service parent class
include_once("../framework/WebService.php");

include_once("../framework/ProcessorXML.php");

include_once("../framework/Solr.php");

include_once("Browse.php");
include_once("../auth/validator/AuthValidator.php");
include_once("../auth/lister/AuthLister.php");

include_once("../framework/Logger.php");


// Full text query supporting the Lucene operators
$attributes = "all";

if(isset($_POST['attributes'])) 
{
    $attributes = $_POST['attributes'];
}

// Types to filter
$types = "all";

if(isset($_POST['types'])) 
{
    $types = $_POST['types'];
}

// Filtering types
$datasets = "all";

if(isset($_POST['datasets'])) 
{
    $datasets = $_POST['datasets'];
}

// Number of items to return
$items = "10";

if(isset($_POST['items'])) 
{
    $items = $_POST['items'];
}

// Where to start the paging in the dataset
$page = "0";

if(isset($_POST['page'])) 
{
    $page = $_POST['page'];
}

// Enable the inference engine
$inference = "on";

if(isset($_POST['inference'])) 
{
    $inference = $_POST['inference'];
}

// Include aggregates
$include_aggregates = "false";

if(isset($_POST['include_aggregates'])) 
{
    $include_aggregates = $_POST['include_aggregates'];
}


// Optional IP
$registered_ip = "";

if(isset($_POST['registered_ip'])) 
{
    $registered_ip = $_POST['registered_ip'];
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

$ws_b = new Browse($attributes, $types, $datasets, $items, $page, $inference, $include_aggregates, $registered_ip, $requester_ip);

$ws_b->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

$ws_b->process();

$ws_b->ws_respond($ws_b->ws_serialize());


$mtime = microtime(); 
$mtime = explode(" ", $mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 


$logger = new Logger("browse", $requester_ip, "?attributes=".$attributes."&datasets=".$datasets."&types=".$types."&items=".$items."&page=".$page."&inference=".$inference."&include_aggregates=".$include_aggregates."&registered_ip=".$registered_ip."&requester_ip=$requester_ip", $_SERVER['HTTP_ACCEPT'], $start_datetime, $totaltime, $ws_b->pipeline_getResponseHeaderStatus(), $_SERVER['HTTP_USER_AGENT']);


	//@} 


?>