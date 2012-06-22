<?php

/*! @ingroup WsTracker Tracker Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\tracker\create\index.php
    @brief Entry point of a query for the Tracker Create web service
 */
 
include_once("../../../../SplClassLoader.php");   
 
use \StructuredDynamics\structwsf\ws\tracker\create\TrackerCreate;
use \StructuredDynamics\structwsf\ws\framework\Logger; 

// Don't display errors to the users. Set it to "On" to see errors for debugging purposes.
ini_set("display_errors", "Off"); 

ini_set("memory_limit", "128M");
set_time_limit(2700);

if ($_SERVER['REQUEST_METHOD'] != 'POST') 
{
    header("HTTP/1.1 405 Method Not Allowed");  
    die;
}

// IP being registered
$registered_ip = "";

if(isset($_POST['registered_ip']))
{
  $registered_ip = $_POST['registered_ip'];
}

// Dataset where the record is indexed
$fromDataset = "";

if(isset($_POST['from_dataset']))
{
  $fromDataset = $_POST['from_dataset'];
}

// Record that got changed
$record = "";

if(isset($_POST['record']))
{
  $record = $_POST['record'];
}

// Action that has been performed on the record
$action = "";

if(isset($_POST['action']))
{
  $action = $_POST['action'];
}

// Serialization of the state (usually RDF description) of the record prior the performance of the action on the record.
$previousState = "";

if(isset($_POST['previous_state']))
{
  $previousState = $_POST['previous_state'];
}

// MIME type of the serialization of the previous state of a record. Usually, application/rdf+xml or application/rdf+n3.
$previousStateMime = "";

if(isset($_POST['previous_state_mime']))
{
  $previousStateMime = $_POST['previous_state_mime'];
}

// Performer of the action on the target record.
$performer = "";

if(isset($_POST['performer']))
{
  $performer = $_POST['performer'];
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

$ws_trackercreate = new TrackerCreate($fromDataset, $record, $action, $previousState, $previousStateMime, $performer, $registered_ip, $requester_ip);

$ws_trackercreate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                             (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                             (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                             (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

$ws_trackercreate->process();

$ws_trackercreate->ws_respond($ws_trackercreate->ws_serialize());

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);

if($ws_trackercreate->isLoggingEnabled())
{
  $logger = new Logger("tracker_create", 
                       $requester_ip,
                       "?from_dataset=" . urlencode($fromDataset) . 
                       "&record=" . urlencode($record) . 
                       "&action=" . $action . 
                       "&previous_state=". 
                       "&previous_state_mime=" . $previousStateMime . 
                       "&performer=" . urlencode($performer) . 
                       "&registered_ip=" . $registered_ip . 
                       "&requester_ip=$requester_ip", 
                       (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""),
                       $start_datetime, 
                       $totaltime,
                       $ws_trackercreate->pipeline_getResponseHeaderStatus(), 
                       (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""));
}

//@}

?>