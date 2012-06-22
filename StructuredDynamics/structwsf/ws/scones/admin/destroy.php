<?php
  
  /*
      This script destroy the Scones session.
      
      Once destroyed, the session as to be re-initialized by running the init.php script.
  */
  
  /*
    Get the pool of stories to process
    Can be a URL or a file reference.
  */
  $config_ini = parse_ini_file("../config.ini", TRUE);   

  // Starts the GATE process/bridge  
  require_once($config_ini["gate"]["gateBridgeURI"]);
  
  // Destroy the scones session
  // Second param "false" => we re-use the pre-created session without destroying the previous one
  // third param "0" => it nevers timeout.
  $SconesSession = java_session($config_ini["gate"]["sessionName"], false, 0);   

  $SconesSession->destroy();
  
  echo "Destroyed..." ;
?>
