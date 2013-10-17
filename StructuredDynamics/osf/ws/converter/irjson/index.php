<?php

/*! @ingroup WsConverterIrJSON */
//@{
 
/*! @file \StructuredDynamics\osf\ws\converter\irjson\index.php
    @brief Entry point of a query the irJSON Converter web service
 */

include_once("../../../../SplClassLoader.php"); 
 
use \StructuredDynamics\osf\ws\converter\irjson\ConverterIrJSON;
 
// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "64M");

if ($_SERVER['REQUEST_METHOD'] != 'POST') 
{
  header("HTTP/1.1 405 Method Not Allowed");  
  die;
}            

$document = "";

/*
  3 mime choices for the text input:
  
  (1) application/iron+json
  (2) application/rdf+xml
  (3) application/rdf+n3
*/

if(isset($_POST['document']))
{
  $document = $_POST['document'];
}

$docmime = "application/iron+json";

if(isset($_POST['docmime']))
{
  $docmime = str_replace('\"', '"', $_POST['docmime']);
}

$include_dataset_description = "false";

if(isset($_POST['include_dataset_description']))
{
  $include_dataset_description = strtolower($_POST['include_dataset_description']);
}

$ws_irv = new ConverterIrJSON($document, $docmime, $include_dataset_description);

$ws_irv->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                   (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                   (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                   (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_irv->process();

$ws_irv->ws_respond($ws_irv->ws_serialize());

//@}

?>