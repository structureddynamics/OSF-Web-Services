<?php
  
  namespace StructuredDynamics\structwsf\ws\ontology\create\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\framework\OWLOntology;
  use \StructuredDynamics\structwsf\ws\auth\lister\AuthLister;
  use \StructuredDynamics\structwsf\ws\framework\ProcessorXML;
  use \StructuredDynamics\structwsf\ws\dataset\create\DatasetCreate;
  use \StructuredDynamics\structwsf\framework\WebServiceQuerier;
  use \StructuredDynamics\structwsf\ws\ontology\delete\OntologyDelete;
  use \StructuredDynamics\structwsf\ws\ontology\read\OntologyRead;
  use \StructuredDynamics\structwsf\ws\crud\create\CrudCreate;
  use \ARC2;
  use \Exception;

  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "1.0";
    }
    
    /**
    * Return the ID used as a session ID, within tomcat, for the ontology
    * 
    * @param string $uri URI of the ontology to load
    * 
    * @return Session ID of the ontology
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    private function getOntologySessionID($uri)
    {
      return("ontology__".preg_replace("/[^a-zA-Z0-9]/", "_", $uri));
    }   
    
    private function n3Encode($string)
    {
      return(trim(str_replace(array( "\\" ), "\\\\", $string), '"'));
    } 
    

    /** Get the description for a resource (class, property, instance).
    
        @param $description the internal representation of the resource. The structure of this array is:
        
        $classDescription = array(
                                   "predicate-uri" => array(
                                                            array(
                                                                    "value" => "the value of the predicate",
                                                                    "type" => "the type of the value",
                                                                    "lang" => "language reference of the value (if literal)"
                                                                 ),
                                                            array(...)
                                                          ),
                                   "..." => array(...)
                                 )      
                                 
        @return returns a description for that resource. "No description available" if none are described in the 
                resource's description                               

        @author Frederick Giasson, Structured Dynamics LLC.
    */    
    private function getDescription($description)
    {
      if(isset($description[Namespaces::$iron . "description"]))
      {
        return $description[Namespaces::$iron . "description"][0]["value"];
      }

      if(isset($description[Namespaces::$skos_2008 . "definition"]))
      {
        return $description[Namespaces::$skos_2008 . "definition"][0]["value"];
      }

      if(isset($description[Namespaces::$skos_2004 . "definition"]))
      {
        return $description[Namespaces::$skos_2004 . "definition"][0]["value"];
      }

      if(isset($description[Namespaces::$rdfs . "comment"]))
      {
        return $description[Namespaces::$rdfs . "comment"][0]["value"];
      }

      if(isset($description[Namespaces::$dcterms . "description"]))
      {
        return $description[Namespaces::$dcterms . "description"][0]["value"];
      }

      if(isset($description[Namespaces::$dc . "description"]))
      {
        return $description[Namespaces::$dc . "description"][0]["value"];
      }

      return "No description available";
    }
      
    /** Get the preferred label for a resource (class, proeperty, instance).
    
        @param $uri the URI of the resource for which we are looking for a preferred label. This URI is
                        used to try to create a label if nothing can be used in its own description (this is the fallback)
        @param $description the internal representation of the resource. The structure of this array is:
        
        $classDescription = array(
                                   "predicate-uri" => array(
                                                            array(
                                                                    "value" => "the value of the predicate",
                                                                    "type" => "the type of the value",
                                                                    "lang" => "language reference of the value (if literal)"
                                                                 ),
                                                            array(...)
                                                          ),
                                   "..." => array(...)
                                 )      

        @author Frederick Giasson, Structured Dynamics LLC.
    */  
    private function getLabel($uri, $description)
    {
      if(isset($description[Namespaces::$iron . "prefLabel"]))
      {
        return $description[Namespaces::$iron . "prefLabel"][0]["value"];
      }

      if(isset($description[Namespaces::$skos_2008 . "prefLabel"]))
      {
        return $description[Namespaces::$skos_2008 . "prefLabel"][0]["value"];
      }

      if(isset($description[Namespaces::$skos_2004 . "prefLabel"]))
      {
        return $description[Namespaces::$skos_2004 . "prefLabel"][0]["value"];
      }

      if(isset($description[Namespaces::$rdfs . "label"]))
      {
        return $description[Namespaces::$rdfs . "label"][0]["value"];
      }

      if(isset($description[Namespaces::$dcterms . "title"]))
      {
        return $description[Namespaces::$dcterms . "title"][0]["value"];
      }

      if(isset($description[Namespaces::$dc . "title"]))
      {
        return $description[Namespaces::$dc . "title"][0]["value"];
      }

      // Find the base URI of the ontology
      $pos = strripos($uri, "#");

      if($pos === FALSE)
      {
        $pos = strripos($uri, "/");
      }

      if($pos !== FALSE)
      {
        $pos++;
      }

      $resource = substr($uri, $pos, strlen($uri) - $pos);

      // Remove non alpha-num and replace them by spaces
      $resource = preg_replace("/[^A-Za-z0-9]/", " ", $resource);

      // Split upper-case words into seperate words
      $resourceArr = preg_split('/(?=[A-Z])/', $resource);
      $resource = implode(" ", $resourceArr);

      return $resource;
    }
    
    /** Update all ontological structures used by the WSF

        @author Frederick Giasson, Structured Dynamics LLC.
    */
    public function createOntology()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        $this->ws->validateQuery();

        // If the query is still valid
        if($this->ws->conneg->getStatus() == 200)
        {  
          // Starts the OWLAPI process/bridge
          require_once($this->ws->owlapiBridgeURI);

          // Create the OWLAPI session object that could have been persisted on the OWLAPI instance.
          // Second param "false" => we re-use the pre-created session without destroying the previous one
          // third param "0" => it nevers timeout.
          $OwlApiSession = java_session("OWLAPI", false, 0);

          $register = java_values($OwlApiSession->get("ontologiesRegister"));
          
          // Check if the ontology is already existing
          if(!is_null(java_values($OwlApiSession->get($this->getOntologySessionID($this->ws->ontologyUri)))) ||
             ($register != NULL && array_search($this->getOntologySessionID($this->ws->ontologyUri), $register) !== FALSE)) 
          {
            $this->ws->returnError(400, "Bad Request", "_302", "");
            
            return;
          }        
          
          try
          {
            $ontology = new OWLOntology($this->ws->ontologyUri, $OwlApiSession, FALSE, strtolower($this->ws->owlapiReasoner));
          }
          catch(Exception $e)
          {           
            $this->ws->returnError(400, "Bad Request", "_300", (string)java_values($e));

            return;
          }

          // Get the description of the ontology
          $ontologyDescription = $ontology->getOntologyDescription();

          $ontologyName = $this->getLabel($this->ws->ontologyUri, $ontologyDescription);
          $ontologyDescription = $this->getDescription($ontologyDescription);

          // Get the list of webservices that will be accessible for this ontology dataset.
          $authLister = new AuthLister("ws", $this->ws->ontologyUri, $this->ws->requester_ip, $this->ws->wsf_local_ip);

          $authLister->pipeline_conneg($this->ws->conneg->getAccept(), $this->ws->conneg->getAcceptCharset(),
            $this->ws->conneg->getAcceptEncoding(), $this->ws->conneg->getAcceptLanguage());

          $authLister->process();

          if($authLister->pipeline_getResponseHeaderStatus() != 200)
          {
            $this->ws->conneg->setStatus($authLister->pipeline_getResponseHeaderStatus());
            $this->ws->conneg->setStatusMsg($authLister->pipeline_getResponseHeaderStatusMsg());
            $this->ws->conneg->setStatusMsgExt($authLister->pipeline_getResponseHeaderStatusMsgExt());
            $this->ws->conneg->setError($authLister->pipeline_getError()->id, $authLister->pipeline_getError()->webservice,
              $authLister->pipeline_getError()->name, $authLister->pipeline_getError()->description,
              $authLister->pipeline_getError()->debugInfo, $authLister->pipeline_getError()->level);

            return;
          }

          /* Get all web services */
          $webservices = "";

          $xml = new ProcessorXML();
          $xml->loadXML($authLister->pipeline_getResultset());

          $webServiceElements = $xml->getXPath('//predicate/object[attribute::type="wsf:WebService"]');

          foreach($webServiceElements as $element)
          {
            if(stristr($xml->getURI($element), "/wsf/ws/search/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/browse/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/sparql/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/crud/create/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/crud/update/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/crud/delete/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/crud/read/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/revision/delete/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/revision/diff/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/revision/lister/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/revision/read/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/revision/update/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/ontology/create/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/ontology/read/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/ontology/update/") !== FALSE
              || stristr($xml->getURI($element), "/wsf/ws/ontology/delete/") !== FALSE)
            {
              $webservices .= $xml->getURI($element) . ";";
            }
          }

          $webservices = rtrim($webservices, ";");

          unset($xml);
          unset($authLister);

          // Create a new dataset for this ontology
          $globalPermissions = "";
          
          if($this->ws->globalPermissionCreate === FALSE)
          {
            $globalPermissions .= "False;";
          }
          else
          {
            $globalPermissions .= "True;";
          }
          
          if($this->ws->globalPermissionRead === FALSE)
          {
            $globalPermissions .= "False;";
          }
          else
          {
            $globalPermissions .= "True;";
          }
          
          if($this->ws->globalPermissionUpdate === FALSE)
          {
            $globalPermissions .= "False;";
          }
          else
          {
            $globalPermissions .= "True;";
          }
          
          if($this->ws->globalPermissionDelete === FALSE)
          {
            $globalPermissions .= "False";
          }
          else
          {
            $globalPermissions .= "True";
          }
          
          $datasetCreate =
            new DatasetCreate($this->ws->ontologyUri, $ontologyName, $ontologyDescription, "", $this->ws->registered_ip,
              $this->ws->requester_ip, $webservices, $globalPermissions);

          $datasetCreate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
            $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

          $datasetCreate->process();

          if($datasetCreate->pipeline_getResponseHeaderStatus() != 200)
          {
            if($datasetCreate->pipeline_getError()->id != "WS-DATASET-CREATE-202")
            {
              $this->ws->conneg->setStatus($datasetCreate->pipeline_getResponseHeaderStatus());
              $this->ws->conneg->setStatusMsg($datasetCreate->pipeline_getResponseHeaderStatusMsg());
              $this->ws->conneg->setStatusMsgExt($datasetCreate->pipeline_getResponseHeaderStatusMsgExt());
              $this->ws->conneg->setError($datasetCreate->pipeline_getError()->id,
                $datasetCreate->pipeline_getError()->webservice, $datasetCreate->pipeline_getError()->name,
                $datasetCreate->pipeline_getError()->description, $datasetCreate->pipeline_getError()->debugInfo,
                $datasetCreate->pipeline_getError()->level);
            }

            // If the dataset already exists, then we simply stop the processing of the advancedIndexation
            // mode. This means that the tomcat instance has been rebooted, and that the datasets
            // have been leaved there, and that a procedure, normally using the advancedIndexation mode
            // is currently being re-processed.

            return;
          }

          unset($datasetCreate);
          
          // Tag the new dataset as being a dataset that host an ontology description
          $query = "insert into <" . $this->ws->wsf_graph . "datasets/>
                  {
                    <" . $this->ws->ontologyUri . "> <http://purl.org/ontology/wsf#holdOntology> \"true\" .
                  }";

          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_301->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, odbc_errormsg(),
              $this->ws->errorMessenger->_301->level);

            return;
          }        

          // Check if we want to enable the advanced indexation: so, if we want to import all the ontologies 
          // description into the other structWSF data stores to enable search and filtering using the other
          // endpoints such as search, sparql, read, etc.
          if($this->ws->advancedIndexation)
          {          
            // Once we start the ontology creation process, we have to make sure that even if the server
            // loose the connection with the user the process will still finish.
            ignore_user_abort(true);

            // However, maybe there is an issue with the server handling that file tht lead to some kind of infinite 
            // or near infinite loop; so we have to limit the execution time of this procedure to 45 mins.
            set_time_limit(86400);    

            // Get the description of the classes, properties and named individuals of this ontology.
                      
            // Check the size of the Ontology file to import. If the size is bigger than 8MB, then we will
            // use another method that incurs some Virtuoso indexing. If it is the case, you have to make sure
            // that Virtuoso is properly configured so that it can access (DirsAllowed Virtuoso config option)
            // the folder where the ontology file has been saved.

            if(filesize($this->ws->ontologyUri) > 8000000)
            {
              $sliceSize = 100;          

              // Import the big file into Virtuoso  
              $sqlQuery = "DB.DBA.RDF_LOAD_RDFXML_MT(file_to_string_output('".str_replace("file://localhost", "", $this->ws->ontologyUri)."'),'".$this->ws->ontologyUri."/import','".$this->ws->ontologyUri."/import')";
              
              $resultset = $this->ws->db->query($sqlQuery);
              
              if(odbc_error())
              {
                // If there is an error, try to load it using the Turtle parser
                $sqlQuery = "DB.DBA.TTLP_MT(file_to_string_output('".str_replace("file://localhost", "", $this->ws->ontologyUri)."'),'".$this->ws->ontologyUri."/import','".$this->ws->ontologyUri."/import')";
                
                $resultset = $this->ws->db->query($sqlQuery);
                
                if(odbc_error())
                {
    //            echo "Error: can't import the file: $file, into the triple store.\n";
    //            return;
                }            
              }    
              
              unset($resultset);     

              // count the number of records
              $sparqlQuery = "
              
                select count(distinct ?s) as ?nb from <".$this->ws->ontologyUri."/import>
                where
                {
                  ?s a ?o .
                }
              
              ";

              $resultset = $this->ws->db->query($this->ws->db->build_sparql_query($sparqlQuery, array ('nb'), FALSE));
              
              $nb = odbc_result($resultset, 1);

              unset($resultset);
              
              $nbRecordsDone = 0;
              
              while($nbRecordsDone < $nb && $nb > 0)
              {
                // Create slices of 100 records.
                $sparqlQuery = "
                  
                  select ?s ?p ?o (DATATYPE(?o)) as ?otype (LANG(?o)) as ?olang
                  where 
                  {
                    {
                      select distinct ?s from <".$this->ws->ontologyUri."/import> 
                      where 
                      {
                        ?s a ?type.
                      } 
                      limit ".$sliceSize." 
                      offset ".$nbRecordsDone."
                    } 
                    
                    ?s ?p ?o
                  }
                
                ";

                $resultset = $this->ws->db->query($this->ws->db->build_sparql_query($sparqlQuery, array ('s', 'p', 'o', 'otype', 'olang'), FALSE));
                
                if(odbc_error())
                {
    //              echo "Error: can't get records slices.\n";
    //              return;
                }          
                
                $crudCreates = "";
                $crudUpdates = "";
                $crudDeletes = array();
                
                $rdfDocumentN3 = "";
                
                $currentSubject = "";
                $subjectDescription = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";

                while(odbc_fetch_row($resultset))
                {
                  $s = odbc_result($resultset, 1);
                  $p = odbc_result($resultset, 2);
                  $o = $this->ws->db->odbc_getPossibleLongResult($resultset, 3);
                  $otype = odbc_result($resultset, 4);
                  $olang = odbc_result($resultset, 5);
                  
                  if($otype != "" || $olang != "")
                  {
                    $subjectDescription .= "<$s> <$p> \"\"\"".$this->n3Encode($o)."\"\"\" .\n";
                  }
                  else
                  {
                    $subjectDescription .= "<$s> <$p> <$o> .\n";
                  }
                }  
                
                unset($resultset);  

               
                $wsq = new WebServiceQuerier(rtrim($this->ws->wsf_base_url, "/") . "/ws/crud/create/", "post",
                  "application/rdf+xml", 
                  "document=" . urlencode($subjectDescription) .
                  "&dataset=" . urlencode($this->ws->ontologyUri) .
                  "&mime=" . urlencode("application/rdf+n3") .
                  "&mode=full" .
                  "&registered_ip=" . urlencode($this->ws->registered_ip));

                if($wsq->getStatus() != 200)
                {
                  $this->ws->conneg->setStatus($wsq->getStatus());
                  $this->ws->conneg->setStatusMsg($wsq->getStatusMessage());
                  $this->ws->conneg->setStatusMsgExt($wsq->getStatusMessageDescription());
                  /*
                  $this->ws->conneg->setError($wsq->pipeline_getError()->id,
                    $crudCreate->pipeline_getError()->webservice, $crudCreate->pipeline_getError()->name,
                    $crudCreate->pipeline_getError()->description, $crudCreate->pipeline_getError()->debugInfo,
                    $crudCreate->pipeline_getError()->level);               
                  */
                  
                  // In case of error, we delete the dataset we previously created.
                  $ontologyDelete = new OntologyDelete($this->ws->ontologyUri, $this->ws->registered_ip, $this->ws->requester_ip);

                  $ontologyDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
                    $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                  $ontologyDelete->deleteOntology();

                  if($ontologyDelete->pipeline_getResponseHeaderStatus() != 200)
                  {
                    $this->ws->conneg->setStatus($ontologyDelete->pipeline_getResponseHeaderStatus());
                    $this->ws->conneg->setStatusMsg($ontologyDelete->pipeline_getResponseHeaderStatusMsg());
                    $this->ws->conneg->setStatusMsgExt($ontologyDelete->pipeline_getResponseHeaderStatusMsgExt());
                    $this->ws->conneg->setError($ontologyDelete->pipeline_getError()->id,
                      $ontologyDelete->pipeline_getError()->webservice, $ontologyDelete->pipeline_getError()->name,
                      $ontologyDelete->pipeline_getError()->description, $ontologyDelete->pipeline_getError()->debugInfo,
                      $ontologyDelete->pipeline_getError()->level);

                    //return;
                  }

                  //return;              
                }              
                
                $nbRecordsDone += $sliceSize;
              }
            
              // Now delete the graph we used to import the file

              $sqlQuery = "sparql clear graph <".$this->ws->ontologyUri."/import>";
              
              $resultset = $this->ws->db->query($sqlQuery);

              if(odbc_error())
              {
    //            echo "Error: can't delete the graph sued for importing the file\n";
    //            return;
              }    
              
              unset($resultset);    
            }
            else
            {
              $nbClasses = $ontology->getNbClasses();
              $sliceSize = 200;
              
              // Note: in OntologyCreate, we have to merge all the classes, properties and named individuals
              //       together. This is needed to properly handle possible punning used in imported ontologies.
              //       If we don't do this, and that a resource is both a class and an individual, then only
              //       the individual will be in the Solr index because it would overwrite the Class 
              //       record document with the same URI.
              
              include_once("../../framework/arc2/ARC2.php");
              $rdfxmlParser = ARC2::getRDFParser();
              $rdfxmlSerializer = ARC2::getRDFXMLSerializer();
              
              $resourcesIndex = $rdfxmlParser->getSimpleIndex(0);
              
              for($i = 0; $i < $nbClasses; $i += $sliceSize)
              {
                $ontologyRead =
                  new OntologyRead($this->ws->ontologyUri, "getClasses", "mode=descriptions;limit=$sliceSize;offset=$i",
                    $this->ws->registered_ip, $this->ws->requester_ip);

                // Since we are in pipeline mode, we have to set the owlapisession using the current one.
                // otherwise the java bridge will return an error
                $ontologyRead->setOwlApiSession($OwlApiSession);

                $ontologyRead->ws_conneg("application/rdf+xml", $_SERVER['HTTP_ACCEPT_CHARSET'],
                  $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                if($this->ws->reasoner)
                {
                  $ontologyRead->useReasoner(); 
                }  
                else
                {
                  $ontologyRead->stopUsingReasoner();
                }
                  
                $ontologyRead->process();

                $classesRDF = $ontologyRead->ws_serialize();

                $rdfxmlParser->parse($this->ws->ontologyUri, $classesRDF);
                $resourceIndex = $rdfxmlParser->getSimpleIndex(0);
                $resourcesIndex = ARC2::getMergedIndex($resourcesIndex, $resourceIndex);
                
                unset($ontologyRead);
              }

              $nbProperties = 0;
              $nbProperties += $ontology->getNbObjectProperties();
              $nbProperties += $ontology->getNbDataProperties();
              $nbProperties += $ontology->getNbAnnotationProperties();
              $sliceSize = 200;

              for($i = 0; $i < $nbProperties; $i += $sliceSize)
              {
                $ontologyRead = new OntologyRead($this->ws->ontologyUri, "getProperties",
                  "mode=descriptions;limit=$sliceSize;offset=$i;type=all", $this->ws->registered_ip, $this->ws->requester_ip);

                // Since we are in pipeline mode, we have to set the owlapisession using the current one.
                // otherwise the java bridge will return an error
                $ontologyRead->setOwlApiSession($OwlApiSession);

                $ontologyRead->ws_conneg("application/rdf+xml", $_SERVER['HTTP_ACCEPT_CHARSET'],
                  $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                if($this->ws->reasoner)
                {
                  $ontologyRead->useReasoner(); 
                }  
                else
                {
                  $ontologyRead->stopUsingReasoner();
                }                
                  
                $ontologyRead->process();

                $propertiesRDF = $ontologyRead->ws_serialize();

                $rdfxmlParser->parse($this->ws->ontologyUri, $propertiesRDF);
                $resourceIndex = $rdfxmlParser->getSimpleIndex(0);
                $resourcesIndex = ARC2::getMergedIndex($resourcesIndex, $resourceIndex);            
                
                unset($ontologyRead);
              }

              $nbNamedIndividuals = $ontology->getNbNamedIndividuals();
              $sliceSize = 200;

              for($i = 0; $i < $nbNamedIndividuals; $i += $sliceSize)
              {
                $ontologyRead = new OntologyRead($this->ws->ontologyUri, "getNamedIndividuals",
                  "classuri=all;mode=descriptions;limit=$sliceSize;offset=$i", $this->ws->registered_ip, $this->ws->requester_ip);

                // Since we are in pipeline mode, we have to set the owlapisession using the current one.
                // otherwise the java bridge will return an error
                $ontologyRead->setOwlApiSession($OwlApiSession);

                $ontologyRead->ws_conneg("application/rdf+xml", $_SERVER['HTTP_ACCEPT_CHARSET'],
                  $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                if($this->ws->reasoner)
                {
                  $ontologyRead->useReasoner(); 
                }  
                else
                {
                  $ontologyRead->stopUsingReasoner();
                }                
                  
                $ontologyRead->process();

                $namedIndividualsRDF = $ontologyRead->ws_serialize();
                
                $rdfxmlParser->parse($this->ws->ontologyUri, $namedIndividualsRDF);
                $resourceIndex = $rdfxmlParser->getSimpleIndex(0);
                $resourcesIndex = ARC2::getMergedIndex($resourcesIndex, $resourceIndex);              

                unset($ontologyRead);
              }
              
              // Now, let's index the resources of this ontology within structWSF (for the usage of browse, search 
              // and sparql)
              
              // Split the aggregated resources in multiple slices
              $nbResources = count($resourcesIndex);
              $sliceSize = 200;
                                             
              for($i = 0; $i < $nbResources; $i += $sliceSize)
              {
                $slicedResourcesIndex = array_slice($resourcesIndex, $i, $sliceSize);
                
                $resourcesRDF = $rdfxmlSerializer->getSerializedIndex($slicedResourcesIndex);
                
                $crudCreate =
                  new CrudCreate($resourcesRDF, "application/rdf+xml", "full", $this->ws->ontologyUri, $this->ws->registered_ip,
                    $this->ws->requester_ip);

                $crudCreate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
                  $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                $crudCreate->process();

                if($crudCreate->pipeline_getResponseHeaderStatus() != 200)
                {
                  $this->ws->conneg->setStatus($crudCreate->pipeline_getResponseHeaderStatus());
                  $this->ws->conneg->setStatusMsg($crudCreate->pipeline_getResponseHeaderStatusMsg());
                  $this->ws->conneg->setStatusMsgExt($crudCreate->pipeline_getResponseHeaderStatusMsgExt());
                  $this->ws->conneg->setError($crudCreate->pipeline_getError()->id,
                    $crudCreate->pipeline_getError()->webservice, $crudCreate->pipeline_getError()->name,
                    $crudCreate->pipeline_getError()->description, $crudCreate->pipeline_getError()->debugInfo,
                    $crudCreate->pipeline_getError()->level);

                  // In case of error, we delete the dataset we previously created.
                  $ontologyDelete = new OntologyDelete($this->ws->ontologyUri, $this->ws->registered_ip, $this->ws->requester_ip);

                  $ontologyDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
                    $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                  $ontologyDelete->deleteOntology();

                  if($ontologyDelete->pipeline_getResponseHeaderStatus() != 200)
                  {
                    $this->ws->conneg->setStatus($ontologyDelete->pipeline_getResponseHeaderStatus());
                    $this->ws->conneg->setStatusMsg($ontologyDelete->pipeline_getResponseHeaderStatusMsg());
                    $this->ws->conneg->setStatusMsgExt($ontologyDelete->pipeline_getResponseHeaderStatusMsgExt());
                    $this->ws->conneg->setError($ontologyDelete->pipeline_getError()->id,
                      $ontologyDelete->pipeline_getError()->webservice, $ontologyDelete->pipeline_getError()->name,
                      $ontologyDelete->pipeline_getError()->description, $ontologyDelete->pipeline_getError()->debugInfo,
                      $ontologyDelete->pipeline_getError()->level);

                    return;
                  }

                  return;
                }

                unset($crudCreate);             
              }
            }
          }
        }
      }
    }          
    
    public function processInterface()
    {
    }
  }
?>
