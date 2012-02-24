<?php

/*! @defgroup WsCrud Crud Web Service */
//@{

/*! @file \ws\crud\read\index.php
   @brief Entry point of a query for the Crud Read web service
   @details Each time a query is sent to this web service, this index.php script will read the web service class
           and will process it. The resultset, or error, will be returned to the user in the HTTP header & body query.
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */

ini_set("display_errors",
  "Off"); // Don't display errors to the users. Set it to "On" to see errors for debugging purposes.

ini_set("memory_limit", "64M");


// Database connectivity procedures
include_once("../../framework/db.php");

// Content negotion class
include_once("../../framework/Conneg.php");

// The Web Service parent class
include_once("../../framework/WebService.php");

include_once("../../framework/ProcessorXML.php");

include_once("CrudRead.php");
include_once("../../auth/validator/AuthValidator.php");

include_once("../../framework/Logger.php");


// URI of the resource to get its description
$uri = "";

if(isset($_GET['uri']))
{
  $uri = $_GET['uri'];
}
elseif(isset($_POST['uri']))
{
  $uri = $_POST['uri'];
}

// URI of the crud to get the description of
$dataset = "";

if(isset($_GET['dataset']))
{
  $dataset = $_GET['dataset'];
}
elseif(isset($_POST['dataset']))
{
  $dataset = $_POST['dataset'];
}

// Include the reference of the resources that links to this resource
$include_linksback = "";

if(isset($_GET['include_linksback']))
{
  $include_linksback = $_GET['include_linksback'];
}
elseif(isset($_POST['include_linksback']))
{
  $include_linksback = $_POST['include_linksback'];
}

// Include the reference of the resources that links to this resource
$include_reification = "";

if(isset($_GET['include_reification']))
{
  $include_reification = $_GET['include_reification'];
}
elseif(isset($_POST['include_reification']))
{
  $include_reification = $_POST['include_reification'];
}

// Include attribute/values of the attributes defined in this list
$include_attributes_list = "";

if(isset($_GET['include_attributes_list']))
{
  $include_attributes_list = $_GET['include_attributes_list'];
}
elseif(isset($_POST['include_attributes_list']))
{
  $include_attributes_list = $_POST['include_attributes_list'];
}

// Optional IP
$registered_ip = "";

if(isset($_GET['registered_ip']))
{
  $registered_ip = $_GET['registered_ip'];
}
elseif(isset($_POST['include_attributes_list']))
{
  $registered_ip = $_GET['include_attributes_list'];
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

$ws_cr = new CrudRead($uri, $dataset, $include_linksback, $include_reification, $registered_ip, $requester_ip, $include_attributes_list);

$ws_cr->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                  (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_cr->process();

$ws_cr->ws_respond($ws_cr->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_cr->isLoggingEnabled())
{
  $logger = new Logger("crud_read", 
                       $requester_ip,
                       "?uri=" . $uri . 
                       "&dataset=" . $dataset . 
                       "&include_linksback=" . $include_linksback . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip", 
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime,
                       $totaltime,
                       $ws_cr->pipeline_getResponseHeaderStatus(), 
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>