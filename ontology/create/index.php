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

ini_set("display_errors",
  "Off"); // Don't display errors to the users. Set it to "On" to see errors for debugging purposes.

ini_set("memory_limit", "256M");

if ($_SERVER['REQUEST_METHOD'] != 'POST') 
{
    header("HTTP/1.1 405 Method Not Allowed");  
    die;
}

// Database connectivity procedures
include_once("../../framework/db.php");

// Content negotion class
include_once("../../framework/Conneg.php");

// The Web Service parent class
include_once("../../framework/WebService.php");

include_once("../../framework/ProcessorXML.php");

include_once("OntologyCreate.php");
include_once("../../auth/validator/AuthValidator.php");
include_once("../../dataset/read/DatasetRead.php");

include_once("../../framework/Logger.php");

include_once("../../framework/OWLOntology.php");

// IP being registered
$registered_ip = "";

if(isset($_POST['registered_ip']))
{
  $registered_ip = $_POST['registered_ip'];
}

// Ontology RDF document where resource(s) to be added are described
$ontologyUri = "";

if(isset($_POST['uri']))
{
  $ontologyUri = $_POST['uri'];
}

// Permissions to set for the "public user" to access this new ontology dataset.
$globalPermissions = "False;True;False;False";

if(isset($_POST['globalPermissions']))
{
  $globalPermissions = $_POST['globalPermissions'];
}

// If this parameter is set, the Ontology Create web service endpoint will index
// the ontology in the normal structWSF data stores. That way, the ontology
// will also become queryable via the standard services such as Search and Browse.
$advancedIndexation = FALSE;

if(isset($_POST['advancedIndexation']))
{
  if(strtolower($_POST['advancedIndexation']) == "false")
  {
    $advancedIndexation = FALSE;
  }
  else
  {
    $advancedIndexation = TRUE;
  }  
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

$ws_ontologycreate =  new OntologyCreate($ontologyUri, $registered_ip, $requester_ip);

$ws_ontologycreate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

// Set the advanced indexation
$ws_ontologycreate->setAdvancedIndexation($advancedIndexation);
  
// Set global permissions
$permissions = explode(";", $globalPermissions);

if(strtolower($permissions[0]) == "false")
{
  $ws_ontologycreate->setGlobalPermissionCreate(FALSE);
}  
else
{
  $ws_ontologycreate->setGlobalPermissionCreate(TRUE);
}

if(strtolower($permissions[1]) == "false")
{
  $ws_ontologycreate->setGlobalPermissionRead(FALSE);
}  
else
{
  $ws_ontologycreate->setGlobalPermissionRead(TRUE);
}

if(strtolower($permissions[2]) == "false")
{
  $ws_ontologycreate->setGlobalPermissionUpdate(FALSE);
}  
else
{
  $ws_ontologycreate->setGlobalPermissionUpdate(TRUE);
}

if(strtolower($permissions[3]) == "false")
{
  $ws_ontologycreate->setGlobalPermissionDelete(FALSE);
}  
else
{
  $ws_ontologycreate->setGlobalPermissionDelete(TRUE);
}
  
  
$ws_ontologycreate->createOntology();

$ws_ontologycreate->ws_respond($ws_ontologycreate->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_ontologycreate->isLoggingEnabled())
{
  $logger = new Logger("ontology_create", 
                       $requester_ip,
                       "?ontology=" . substr($ontology, 0, 64) . 
                       "&mime=" . $mime . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip",
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime, 
                       $ws_ontologycreate->pipeline_getResponseHeaderStatus(),
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>