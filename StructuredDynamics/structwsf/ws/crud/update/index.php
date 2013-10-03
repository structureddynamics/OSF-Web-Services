<?php

/*! @ingroup WsCrud Crud Web Service  */
//@{

/*! @file \StructuredDynamics\osf\ws\crud\update\index.php
    @brief Entry point of a query for the Update web service
 */
 
include_once("../../../../SplClassLoader.php");   
 
use \StructuredDynamics\osf\ws\crud\update\CrudUpdate;

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

// Specify if we want to create a new revision or not for the updated record
$revision = "true";

if(isset($_POST['revision']))
{
  $revision = $_POST['revision'];
}

// Publication lifecycle stage of the record
$lifecycle = "published";

if(isset($_POST['lifecycle']))
{
  $lifecycle = $_POST['lifecycle'];
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

$ws_crudupdate = new CrudUpdate($document, $mime, $dataset, $interface, $version, $lifecycle, $revision);

$ws_crudupdate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                          (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_crudupdate->process();

$ws_crudupdate->ws_respond($ws_crudupdate->ws_serialize());

//@}

?>