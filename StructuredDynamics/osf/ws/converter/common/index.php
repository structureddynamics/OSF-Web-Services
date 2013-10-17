<?php

/*! @ingroup WsConverterCommON */
//@{

/*! @file \StructuredDynamics\osf\ws\converter\common\index.php
    @brief Entry point of a query the CommON Converter web service
 */
 
include_once("../../../../SplClassLoader.php");  
 
use \StructuredDynamics\osf\ws\converter\common\ConverterCommON;

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "512M");
set_time_limit(2700);

if ($_SERVER['REQUEST_METHOD'] != 'POST') 
{
  header("HTTP/1.1 405 Method Not Allowed");  
  die;
}

$document = "";

/*
  3 mime choices for the text input:
  
  (1) application/iron+csv
*/

if(isset($_POST['document']))
{
  $document = $_POST['document'];
}

$docmime = "application/iron+csv";

if(isset($_POST['docmime']))
{
  $docmime = str_replace('\"', '"', $_POST['docmime']);
}

$ws_common = new ConverterCommon($document, $docmime);

$ws_common->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                      (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_common->process();

$ws_common->ws_respond($ws_common->ws_serialize());


//@}

?>