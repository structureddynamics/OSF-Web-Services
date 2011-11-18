<?php

/*! @ingroup WsConverterBibtex */
//@{

/*! @file \ws\converter\bibtex\index.php
   @brief Entry point of a query the Converter Bibtex web service
   @details Each time a query is sent to this web service, this index.php script will create the web service class
           and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.
   
   \n\n\n
 */

error_reporting(0);

ini_set("memory_limit", "64M");

// Check if the HTTP method used by the requester is the good one
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

// Loading the Named Entities Extraction web service
include_once("ConverterBibtex.php");
include_once("BibtexParser.php");
include_once("BibtexParserCsv.php");
include_once("BibtexItem.php");

include_once("../../framework/Logger.php");

$document = "";
$url = "";

/*
  3 mime choices for the text input:
  
  (1) application/x-bibtex
  (2) application/rdf+xml
  (3) application/rdf+n3
*/

$base_uri = "http://www.baseuri.com/resource/";

if(isset($_POST['document']))
{
  $document = str_replace('\"', '"', $_POST['document']);
}

$docmime = "application/x-bibtex";

if(isset($_POST['docmime']))
{
  $docmime = $_POST['docmime'];
}

if(isset($_POST['base_uri']))
{
  $base_uri = $_POST['base_uri'];
}

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

$ws_cbibtex = new ConverterBibtex($document, $docmime, $base_uri, $registered_ip, $requester_ip);

$ws_cbibtex->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                       (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                       (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                       (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_cbibtex->process();

$ws_cbibtex->ws_respond($ws_cbibtex->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_cbibtex->isLoggingEnabled())
{
  $logger = new Logger("converter/bibtex", 
                       $requester_ip,
                       "?text=-&type=" . $type . 
                       "&base_uri=" . $base_uri . 
                       "&requester_ip=$requester_ip", 
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime,
                       $ws_cbibtex->pipeline_getResponseHeaderStatus(), 
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>