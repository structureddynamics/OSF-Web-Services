<?php
  /*
      This script reset all the running (and persisted) Gate application sessions.
  */

  error_reporting(E_ALL);
  ini_set('display_errors', '1');
  
  /*
    Get the pool of stories to process
    Can be a URL or a file reference.
  */
  $config_ini = parse_ini_file("../config.ini", TRUE);   

  // Starts the GATE process/bridge  
  require_once($config_ini["gate"]["gateBridgeURI"]);
  
  // Create a Scones session where wewill save the Gate objects (started & loaded Gate application).
  // Second param "false" => we re-use the pre-created session without destroying the previous one
  // third param "0" => it nevers timeout.
  $SconesSession = java_session($config_ini["gate"]["sessionName"], false, 0);    

  for($i = 1; $i <= $config_ini["gate"]["nbSessions"]; $i++)
  {
    $SconesSession->put("session".$i."_used", FALSE);
  }
   
   echo "Threads reseted...";
    

?>