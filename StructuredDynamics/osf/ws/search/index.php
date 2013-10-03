<?php

/** @defgroup WsSearch Search Web Service */
//@{

/*! @file \StructuredDynamics\osf\ws\search\index.php
    @brief Entry point of a query for the Search web service
 */
 
// Auto-load classes
include_once("../../../SplClassLoader.php"); 
 
use \StructuredDynamics\osf\ws\search\Search;

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

// Types boost
$typesBoost = "";

if(isset($_POST['types_boost']))
{
  $typesBoost = $_POST['types_boost'];
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

// Attributes boost
$attributesBoost = "";

if(isset($_POST['attributes_boost']))
{
  $attributesBoost = $_POST['attributes_boost'];
}

// Extended Filters
$extendedFilters = "";

if(isset($_POST['extended_filters']))
{
  $extendedFilters = $_POST['extended_filters'];
}

// Filtering datasets
$datasets = "all";

if(isset($_POST['datasets']))
{
  $datasets = $_POST['datasets'];
}

// Datasets boost
$datasetsBoost = "";

if(isset($_POST['datasets_boost']))
{
  $datasetsBoost = $_POST['datasets_boost'];
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

// Include spellchecking suggestions
$spellcheck = FALSE;

if(isset($_POST['spellcheck']))
{
  $spellcheck = $_POST['spellcheck'];
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

$searchRestrictions = array();

if(isset($_POST['search_restrictions']))
{
  $searchRestrictions = $_POST['search_restrictions'];
}

$includeScores = "false";

if(isset($_POST['include_scores']))
{
  $includeScores = $_POST['include_scores'];
}

$defaultOperator = 'and';

if(isset($_POST['default_operator']))
{
  $defaultOperator = $_POST['default_operator'];
}

$attributesPhraseBoost = '';

if(isset($_POST['attributes_phrase_boost']))
{
  $attributesPhraseBoost = $_POST['attributes_phrase_boost'];
}
 
$phraseBoostDistance = 0;

if(isset($_POST['phrase_boost_distance']))
{
  $phraseBoostDistance = $_POST['phrase_boost_distance'];
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
                   $distanceFilter, $rangeFilter, $aggregate_attributes, 
                   $attributesBooleanOperator, $includeAttributesList,$aggregateAttributesObjectType,
                   $aggregateAttributesNb, $resultsLocationAggregator, $interface, $version, $lang,
                   $sort, $extendedFilters, $typesBoost, $attributesBoost, $datasetsBoost, $searchRestrictions,
                   $includeScores, $defaultOperator, $attributesPhraseBoost, $phraseBoostDistance,
                   $spellcheck);

$ws_s->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                 (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                 (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                 (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_s->process();

$ws_s->ws_respond($ws_s->ws_serialize());

//@}

?>