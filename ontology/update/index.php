<?php

/*! @ingroup WsOntology Ontology Management Web Service  */
//@{

/*! @file \ws\ontology\update\index.php
   @brief Entry point of a query for the Ontology Update web service
   @details Each time a query is sent to this web service, this index.php script will update the web service class
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

include_once("OntologyUpdate.php");
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
$ontology = "";

if(isset($_POST['ontology']))
{
  $ontology = $_POST['ontology'];
}

// The function to query via the webservice
$function = "";

if(isset($_POST['function']))
{
  $function = $_POST['function'];
}

// The parameters of the function to use
$params = "";

if(isset($_POST['parameters']))
{
  $params = $_POST['parameters'];
}

$reasoner = "true";

if(isset($_POST['reasoner']))
{
  if(strtolower($_POST['reasoner']) == "false")
  {
    $reasoner = FALSE;
  }
  else
  {
    $reasoner = TRUE;
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

$ws_ontologyupdate = new OntologyUpdate($ontology, $registered_ip, $requester_ip);

$ws_ontologyupdate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                              (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

                              
// set reasoner
if($reasoner)
{
  $ws_ontologyupdate->useReasonerForAdvancedIndexation();
}
else
{
  $ws_ontologyupdate->stopUsingReasonerForAdvancedIndexation();
}
                                
                              
$params = explode(";", $params);
$parameters = array();

foreach($params as $param)
{
  $p = explode("=", $param);

  $parameters[strtolower($p[0])] = urldecode($p[1]);
}  
 
switch(strtolower($function))
{
  case "saveontology":
    $ws_ontologyupdate->saveOntology();  
  break;

  case "createorupdateentity":
    $advancedIndexation = FALSE;
     
    if($parameters["advancedindexation"] == "1" || 
       strtolower($parameters["advancedindexation"]) == "true")
    {
      $advancedIndexation = TRUE;
    }
  
    $ws_ontologyupdate->createOrUpdateEntity($parameters["document"], $advancedIndexation);
  break;
  
  case "updateentityuri":
    $advancedIndexation = FALSE;
            
    if($parameters["advancedindexation"] == "1" || 
       strtolower($parameters["advancedindexation"]) == "true")
    {
      $advancedIndexation = TRUE;
    }
  
    $ws_ontologyupdate->updateEntityUri($parameters["olduri"], $parameters["newuri"], $advancedIndexation);
  break;
  

  default:
    $ws_ontologyupdate->returnError(400, "Bad Request", "_201");
  break;         
}     


  
$ws_ontologyupdate->ws_respond($ws_ontologyupdate->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_ontologyupdate->isLoggingEnabled())
{
  $logger = new Logger("ontology_update", 
                       $requester_ip,
                       "?ontology=" . substr($ontology, 0, 64) . 
                       "&mime=" . $mime . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip",
                       $_SERVER['HTTP_ACCEPT'], 
                       $start_datetime, 
                       $totaltime, 
                       $ws_ontologyupdate->pipeline_getResponseHeaderStatus(),
                       $_SERVER['HTTP_USER_AGENT']);
}

//@}

?>