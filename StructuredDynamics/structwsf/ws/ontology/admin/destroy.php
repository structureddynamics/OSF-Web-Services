<?php

/** @defgroup WsOntology Ontology Management Web Service */
//@{

/*! @file \StructuredDynamics\osf\ws\ontology\admin\destroy.php
    @brief Destroy all the OWLAPI instances in tomcat.
    
    If this script is ran, no ontologies will be loaded anymore. You would have to reload all the 
    ontologies in the OWLAPI. You may want to restrict the access to this /admin/ folder in your 
    Apache settings so that not everybody has access to it.
 */

  use \StructuredDynamics\osf\ws\framework\WebService;
 
  /*
      This script destroy the OWLAPI session.
      
      Once destroyed, the session as to be re-initialized by running the init.php script.
  */

  /*
    Get the pool of stories to process
    Can be a URL or a file reference.
  */
  $osf_ini = parse_ini_file(WebService::$osf_ini . "osf.ini", TRUE);

  // Starts the OLAPI process/bridge
  require_once($osf_ini["owlapi"]["bridge_uri"]);

  // Destroy the scones session
  // Second param "false" => we re-use the pre-created session without destroying the previous one
  // third param "0" => it nevers timeout.
  $OwlApiSession = java_session("OWLAPI", false, 0);

  $OwlApiSession->destroy();

  echo "Destroyed...";

//@}

?>