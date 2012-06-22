<?php

  $config_ini = parse_ini_file("../config.ini", TRUE);   

  // Starts the GATE process/bridge  
  require_once($config_ini["gate"]["gateBridgeURI"]);

  // Attach to the screen sessions
  // Second param "false" => we re-use the pre-created session without destroying the previous one
  // third param "0" => it nevers timeout.
  $SconesSession = java_session($config_ini["gate"]["sessionName"], false, 0);   
  
  for($i = 1; $i <= $config_ini["gate"]["nbSessions"]; $i++)
  {   
    if(!is_null($SconesSession->get("session".$i."_instance")) &&
        $SconesSession->get("session".$i."_instance")->__signature != NULL && 
        $SconesSession->get("session".$i."_instance")->__signature != "php.java.bridge.Request\$PhpNull")
    {
      echo "Sessions ID: #".$i."<br>\n";
      
      echo "Used: ".(java_values($SconesSession->get("session".$i."_used")) ? "TRUE" : "FALSE")."<br>\n";

      $corpus = java_values($SconesSession->get("session".$i."_instance"))->getCorpus();        
      
      $nbDocuments = java_values($corpus->size());
      
      echo "Number of documents: ".$nbDocuments."<br>\n";

      echo "<br><br>\n\n";    
    }
  }
  
?>