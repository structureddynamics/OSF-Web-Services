<?php

  error_reporting(E_ALL);
  ini_set('display_errors', '1');

  /*
      This script initialize all the running (and persisted) Gate application sessions.
      These sessions are used simultaneously to process different corpus of documents
      requested by different users.
      
      This init.php script has to be ran each time that tomcat is restarted.
  */
  
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

  // Make sure the scones session is not already opened
  if(is_null(java_values($SconesSession->get("initialized")))) 
  {  

    /** 
      * NOTE, SOMETIMES WHEN WE RUN SOMETHING FOR THE FIRST TIME, THE BRIDGE/TOMCAT CAN'T RESOLVE SOME
      * PATHS. WE HAVE TO CREATE SUCH A DUMMY OBJECT "ONCE" (? huhu) AND HE WILL FIND IT IN THE FUTURE...
      * I HAVE NO IDEA OF WHAT CAUSE THIS ISSUE, IF IT IS RELATED TO GATE ONLY OR NOT.     
      */        
    $test = new java('gate.creole.ontology.OConstants$OntologyFormat');
    
    
    // The session is not yet initialized.
    $SconesSession->put("initialized", FALSE);      
    
    // The number of sessions that have been created for this Scones instance 
    $SconesSession->put("nbSessions", $config_ini["gate"]["nbSessions"]);       
   
    $Gate = java("gate.Gate");
    $PersistenceManager = java("gate.util.persistence.PersistenceManager");
    
    // Initialize GATE
    $Gate->init();
   
    $sessions = array();  

//    $docs = array("document #1", "document #2", "document #3");
    
    for($i = 1; $i <= $config_ini["gate"]["nbSessions"]; $i++)
    {   
      // Load the corpus pipeline from the application (XGAPP) file.
      $corpusController = $PersistenceManager->loadObjectFromFile(
                      new java("java.io.File", $config_ini["gate"]["applicationFile"]));   
 
      // Initialize and save sessions
      $SconesSession->put("session".$i."_used", FALSE);    
      $SconesSession->put("session".$i."_instance", $corpusController);    
    }

    $SconesSession->put("sessions", $sessions);     
    
    $SconesSession->put("initialized", TRUE);      
    
    echo "Initialized...";
  }
  else
  {
    echo "Scones threads are already created. Destroy them first before running this script (using destroy.php)...";
  }
    

?>