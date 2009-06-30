<?php

/*! @ingroup WsOntology Ontology Management Web Service  */
//@{ 

/*! @file \ws\ontology\create\index.php
	 @brief Entry point of a query for the Ontology Create web service
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

include_once("../../framework/ClassHierarchy.php");
include_once("../../framework/PropertyHierarchy.php");
include_once("../../framework/RdfClass.php");
include_once("../../framework/RdfProperty.php");


include_once("OntologyCreate.php");
include_once("../../auth/validator/AuthValidator.php");
include_once("../../dataset/read/DatasetRead.php");

include_once("../../framework/Logger.php");


// IP being registered
$registered_ip = "";

if(isset($_POST['registered_ip'])) 
{
    $registered_ip = $_POST['registered_ip'];
}

// Ontology RDF document where resource(s) to be added are described
$ontology = "";

if(isset($_POST['ontology'])) 
{
    $ontology = $_POST['ontology'];
}

// Mime of the Ontology RDF document serialization
$mime = "";

if(isset($_POST['mime'])) 
{
    $mime = $_POST['mime'];
}

// Additional action that can be performed when adding a new ontology: (1) recreate_inference
$action = "";

if(isset($_POST['action'])) 
{
    $action = $_POST['action'];
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

$ws_ontologycreate = new OntologyCreate($ontology, $mime, $action, $registered_ip, $requester_ip);

$ws_ontologycreate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

$ws_ontologycreate->process();

$ws_ontologycreate->ws_respond($ws_ontologycreate->ws_serialize());


$mtime = microtime(); 
$mtime = explode(" ", $mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 


$logger = new Logger("ontology_create", $requester_ip, "?ontology=".substr($ontology, 0, 64)."&mime=".$mime."&action=".$action."&registered_ip=".$registered_ip."&requester_ip=$requester_ip", $_SERVER['HTTP_ACCEPT'], $start_datetime, $totaltime, $ws_ontologycreate->pipeline_getResponseHeaderStatus(), $_SERVER['HTTP_USER_AGENT']);


	//@} 


?>