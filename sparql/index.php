<?php

/*! @defgroup WsSparql SPARQL Web Service */
//@{

/*! @file \ws\sparql\index.php
   @brief Entry point of a query for the SPARQL web service
   @details Each time a query is sent to this web service, this index.php script will read the web service class
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
include_once("../framework/db.php");

// Content negotion class
include_once("../framework/Conneg.php");

// The Web Service parent class
include_once("../framework/WebService.php");
include_once("../framework/ProcessorXML.php");
include_once("../framework/Namespaces.php");

include_once("Sparql.php");
include_once("../auth/validator/AuthValidator.php");

include_once("../framework/Logger.php");


// Full text query supporting the Lucene operators
$query = "";

if(isset($_POST['query']))
{
  $query = $_POST['query'];
}

// Datasets to target with the sparql query
$dataset = "";

if(isset($_POST['dataset']))
{
  $dataset = $_POST['dataset'];
}
  
// Datasets to target with the sparql query (optional) -- only used for consistency with the SPARQL protocol
if(isset($_POST['default-graph-uri']) && $dataset == "")
{
  $dataset = $_POST['default-graph-uri'];
}

// Datasets to target with the sparql query (optional) -- only used for consistency with the SPARQL protocol
if(isset($_POST['named-graph-uri']) && $dataset == "")
{
  $dataset = $_POST['named-graph-uri'];
}


// Limit of the number of results to return in the resultset
$limit = 2000;

if(isset($_POST['limit']))
{
  $limit = $_POST['limit'];
}

// Offset of the "sub-resultset" from the total resultset of the query
$offset = 0;

if(isset($_POST['offset']))
{
  $offset = $_POST['offset'];
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

$ws_sparql = new Sparql($query, $dataset, $limit, $offset, $registered_ip, $requester_ip);

$ws_sparql->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_sparql->process();

$ws_sparql->ws_respond($ws_sparql->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);


if($ws_sparql->isLoggingEnabled())
{
  $logger = new Logger("sparql", 
                       $requester_ip,
                       "?query=" . $query . 
                       "&dataset=" . $dataset . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip",
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime, 
                       $ws_sparql->pipeline_getResponseHeaderStatus(),
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>