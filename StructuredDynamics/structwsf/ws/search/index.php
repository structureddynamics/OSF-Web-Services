<?php

/** @defgroup WsSearch Search Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\search\index.php
    @brief Entry point of a query for the Search web service
 */
 
// Auto-load classes
include_once("../../../SplClassLoader.php"); 
 
use \StructuredDynamics\structwsf\ws\search\Search;
use \StructuredDynamics\structwsf\ws\framework\Logger; 

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "64M");

if ($_SERVER['REQUEST_METHOD'] != 'POST') 
{
    header("HTTP/1.1 405 Method Not Allowed");  
    die;
}

// Interface to use for this query
$interface = "default";

if(isset($_POST['interface']))
{
  $interface = $_POST['interface'];
}

// Version of the requested interface to use for this query
$version = "";

if(isset($_POST['version']))
{
  $version = $_POST['version'];
}

// Full text query supporting the Lucene operators
$query = "";

if(isset($_POST['query']))
{
  $query = $_POST['query'];
}

// Types to filter
$types = "all";

if(isset($_POST['types']))
{
  $types = $_POST['types'];
}

// Global boolean operator
$attributesBooleanOperator = "and";

if(isset($_POST['attributes_boolean_operator']))
{
  $attributesBooleanOperator = $_POST['attributes_boolean_operator'];
}

// Attributes to filter
$attributes = "all";

if(isset($_POST['attributes']))
{
  $attributes = $_POST['attributes'];
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

// Language of the returned results
$lang = "en";

if(isset($_POST['lang']))
{
  $lang = $_POST['lang'];
}

// Sorting criterias
$sort = "";

if(isset($_POST['sort']))
{
  $sort = $_POST['sort'];
}

// Include aggregates
$include_aggregates = "false";

if(isset($_POST['include_aggregates']))
{
  $include_aggregates = $_POST['include_aggregates'];
}

// Include aggregates
$aggregate_attributes = "";

if(isset($_POST['aggregate_attributes']))
{
  $aggregate_attributes = $_POST['aggregate_attributes'];
}

// Distance Filter
$distanceFilter = "";

if(isset($_POST['distance_filter']))
{
  $distanceFilter = $_POST['distance_filter'];
}

// Range Filter
$rangeFilter = "";

if(isset($_POST['range_filter']))
{
  $rangeFilter = $_POST['range_filter'];
}

$resultsLocationAggregator = "";

if(isset($_POST['results_location_aggregator']))
{
  $resultsLocationAggregator = $_POST['results_location_aggregator'];
}

// Attributes URIs list to include in the returned resultset.
$includeAttributesList = "";

if(isset($_POST['include_attributes_list']))
{
  $includeAttributesList = $_POST['include_attributes_list'];
}

$aggregateAttributesObjectType = "literal";

if(isset($_POST['aggregate_attributes_object_type']))
{
  $aggregateAttributesObjectType = $_POST['aggregate_attributes_object_type'];
}

$aggregateAttributesNb = 10;

if(isset($_POST['aggregate_attributes_object_nb']))
{
  $aggregateAttributesNb = $_POST['aggregate_attributes_object_nb'];
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

$ws_s = new Search($query, $types, $attributes, $datasets, $items, $page, $inference, $include_aggregates, 
                   $registered_ip, $requester_ip, $distanceFilter, $rangeFilter, $aggregate_attributes, 
                   $attributesBooleanOperator, $includeAttributesList,$aggregateAttributesObjectType,
                   $aggregateAttributesNb, $resultsLocationAggregator, $interface, $version, $lang,
                   $sort);

$ws_s->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                 (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                 (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                 (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_s->process();

$ws_s->ws_respond($ws_s->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_s->isLoggingEnabled())
{
  $logger = new Logger("search", 
                       $requester_ip,
                       "?query=" . $query . 
                       "&datasets=" . $datasets . 
                       "&types=" . $types . 
                       "&items=" . $items . 
                       "&page=" . $page . 
                       "&inference=" . $inference . 
                       "&include_aggregates=" . $include_aggregates . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip" . 
                       "&distance_filter=$distanceFilter" . 
                       "&range_filter=$rangeFilter",
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime, 
                       $ws_s->pipeline_getResponseHeaderStatus(),
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>