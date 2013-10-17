<?php

/*! @ingroup WsCrud Crud Web Service  */
//@{

/*! @file \StructuredDynamics\osf\ws\crud\create\index.php
    @brief Entry point of a query for the Create web service
 */
 
include_once("../../../../SplClassLoader.php");  
 
use \StructuredDynamics\osf\ws\crud\create\CrudCreate;

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "256M");
set_time_limit(2700);

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

// Dataset where to index the resource
$dataset = "";

if(isset($_POST['dataset']))
{
  $dataset = $_POST['dataset'];
}

// RDF document where resource(s) to be added are described
$document = "";

if(isset($_POST['document']))
{
  $document = $_POST['document'];
}

// Mime of the RDF document serialization
$mime = "";

if(isset($_POST['mime']))
{
  $mime = $_POST['mime'];
}

// Indexation mode ((1) full (2) triplestore (3) searchindex)
$mode = "full";

if(isset($_POST['mode']))
{
  $mode = $_POST['mode'];
}

$ws_crudcreate = new CrudCreate($document, $mime, $mode, $dataset, $interface, $version);

$ws_crudcreate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_crudcreate->process();

$ws_crudcreate->ws_respond($ws_crudcreate->ws_serialize());

//@}

?>