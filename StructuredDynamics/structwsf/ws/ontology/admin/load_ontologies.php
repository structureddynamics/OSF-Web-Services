<?php

// Script to be loaded offline to create the classes and properties structures.

use \Exception;
use \StructuredDynamics\structwsf\framework\WebServiceQuerier;
use \StructuredDynamics\structwsf\ws\framework\WebService;

// Init the conneg structure to communicate with the ontologyCreate web service endpoint.
$_SERVER['HTTP_ACCEPT'] = "application/rdf+xml;q=1; text/*, text/html, text/html;level=1";
$_SERVER['HTTP_ACCEPT_CHARSET'] = "iso-8859-5, unicode-1-1;q=0.8, utf-8;q=1";
$_SERVER['HTTP_ACCEPT_ENCODING'] = "gzip;q=1.0, identity; q=1, *;q=0.5";
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = "da, en-gb;q=0.8, en;q=0.7";

$data_ini = parse_ini_file(WebService::$data_ini . "data.ini", TRUE);
$network_ini = parse_ini_file(WebService::$network_ini . "network.ini", TRUE);
$ontologiesFilesPath = $data_ini["ontologies"]["ontologies_files_folder"];

IndexOntologiesDirectory($ontologiesFilesPath);

function IndexOntologiesDirectory($dir)
{
  global $network_ini;
  $count = 0;
  $fail = 0;

  if($handler = opendir($dir))
  {
    while(($sub = readdir($handler)) !== FALSE)
    {
      if($sub != "." && $sub != "..")
      {
        if(is_file($dir . "/" . $sub))
        {
          $uri = "file://localhost" . $dir . "/" . $sub;
          
          echo "Processing ontology file: $uri\n";

          $exts = split( "[/\\.]", $sub );
          $n = count($exts) - 1;
          $mimetype = ( $exts[$n] == "n3" || $exts[$n] == "ttl" ) ? "n3" : "xml" ;

          try {
            
            $wsq = new WebServiceQuerier($network_ini["network"]["wsf_base_url"] . "/ws/ontology/create/", "post",
              "application/rdf+$mimetype",
              "&uri=" . urlencode($uri) .
              "&globalPermision=False;True;False;False" .
              "&advancedIndexation=True" .
              "&registered_ip=" . urlencode("self")
            );
            
            if($wsq->getStatus() != 200)
            {
              echo "Web service error: (status: " . strip_tags($wsq->getStatus()) . ") "
              . strip_tags($wsq->getStatusMessage()) . " - " . strip_tags($wsq->getStatusMessageDescription());
            }
            else
            {
              echo "Successfully loaded: $uri\n";
            }
          }
          catch(Exception $ex) 
          {
            echo $e->getMessage();
            $fail++;
          }
          unset($wsq);
          $count++;
        }
        elseif(is_dir($dir . "/" . $sub))
        {
          IndexOntologiesDirectory($dir . "/" . $sub);
        }
      }
    }
    closedir($handler);
  }
  echo "Processing complete.\n";
  echo "Successfully uploaded " . ($count-$fail) . " of $count ontology files ($fail upload failures).\n";
}
?>
