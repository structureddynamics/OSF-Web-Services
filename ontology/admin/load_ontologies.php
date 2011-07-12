<?php

// Script to be loaded offline to create the classes and properties structures.

include_once("../framework/Conneg.php");

include_once("../framework/WebService.php");
include_once("../framework/ProcessorXML.php");
include_once("../framework/db.php");
include_once("../framework/Namespaces.php");
include_once("../auth/validator/AuthValidator.php");
include_once("../framework/ClassHierarchy.php");
include_once("../framework/PropertyHierarchy.php");
include_once("../framework/RdfClass.php");
include_once("../framework/RdfProperty.php");
include_once("../framework/WebService.php");
include_once("../framework/WebServiceQuerier.php");

include_once("create/OntologyCreate.php");

// Init the conneg structure to communicate with the ontologyCreate web service endpoint.
$_SERVER['HTTP_ACCEPT'] = "application/rdf+xml;q=1; text/*, text/html, text/html;level=1";
$_SERVER['HTTP_ACCEPT_CHARSET'] = "iso-8859-5, unicode-1-1;q=0.8, utf-8;q=1";
$_SERVER['HTTP_ACCEPT_ENCODING'] = "gzip;q=1.0, identity; q=1, *;q=0.5";
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = "da, en-gb;q=0.8, en;q=0.7";

$data_ini = parse_ini_file(WebService::$data_ini . "data.ini", TRUE);
$network_ini = parse_ini_file(WebService::$network_ini . "network.ini", TRUE);

// Properly setup the connection to the virtuoso server
$db = new DB_Virtuoso($data_ini["triplestore"]["username"], $data_ini["triplestore"]["password"],
  $data_ini["triplestore"]["dsn"], $data_ini["triplestore"]["host"]);

$ontologiesFilesPath = $data_ini["ontologies"]["ontologies_files_folder"];

// Before doing anything, lets remove the ontologies graph & ontologies-inference rules & graphs

$rulesSetURI = "wsf_inference_rule".ereg_replace("[^A-Za-z0-9]", "", $network_ini["network"]["wsf_base_url"]);
$db->query("exst('rdfs_rule_set('".$rulesSetURI."', '" . $data_ini["datasets"]["wsf_graph"] . "ontologies/inferred/', 1)')");
$db->query("exst('sparql clear graph <" . $data_ini["datasets"]["wsf_graph"] . "ontologies/>')");
$db->query("exst('sparql clear graph <" . $data_ini["datasets"]["wsf_graph"] . "ontologies/inferred/>')");

$db->close();

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
          } // end try
          catch(Exception $ex) {
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
