<?php
  
  namespace StructuredDynamics\structwsf\ws\crud\create\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \ARC2;
  use \StructuredDynamics\structwsf\ws\framework\Solr;
  use \StructuredDynamics\structwsf\ws\framework\ClassHierarchy;
  use \StructuredDynamics\structwsf\ws\framework\ClassNode;
  use \StructuredDynamics\structwsf\ws\framework\PropertyHierarchy;
  use \StructuredDynamics\structwsf\ws\framework\propertyNode;
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "1.0";
    }
    
    public function processInterface()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        $this->ws->validateQuery();

        // If the query is still valid
        if($this->ws->conneg->getStatus() == 200)
        {
          // Get triples from ARC for some offline processing.
          include_once("../../framework/arc2/ARC2.php");
          $parser = ARC2::getRDFParser();
          $parser->parse($this->ws->dataset, $this->ws->document);
          $rdfxmlSerializer = ARC2::getRDFXMLSerializer();

          $resourceIndex = $parser->getSimpleIndex(0);

          if(count($parser->getErrors()) > 0)
          {
            $errorsOutput = "";
            $errors = $parser->getErrors();

            foreach($errors as $key => $error)
            {
              $errorsOutput .= "[Error #$key] $error\n";
            }

            $this->ws->conneg->setStatus(400);
            $this->ws->conneg->setStatusMsg("Bad Request");
            $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, $errorsOutput,
              $this->ws->errorMessenger->_301->level);

            return;
          }

          // First: check for a void:Dataset description to add to the "dataset description graph" of structWSF
          $break = FALSE;
          $datasetUri = "";

          foreach($resourceIndex as $resource => $description)
          {
            foreach($description as $predicate => $values)
            {
              if($predicate == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
              {
                foreach($values as $value)
                {
                  if($value["type"] == "uri" && $value["value"] == "http://rdfs.org/ns/void#Dataset")
                  {
                    $datasetUri = $resource;
                    break;
                  }
                }
              }

              if($break)
              {
                break;
              }
            }

            if($break)
            {
              break;
            }
          }


          // Second: get all the reification statements
          $break = FALSE;
          $statementsUri = array();

          foreach($resourceIndex as $resource => $description)
          {
            foreach($description as $predicate => $values)
            {
              if($predicate == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
              {
                foreach($values as $value)
                {
                  if($value["type"] == "uri" && $value["value"] == "http://www.w3.org/1999/02/22-rdf-syntax-ns#Statement")
                  {
                    array_push($statementsUri, $resource);
                    break;
                  }
                }
              }

              if($break)
              {
                break;
              }
            }

            if($break)
            {
              break;
            }
          }

          // Third, get all references of all instance records resources (except for the statement resources)
          $irsUri = array();

          foreach($resourceIndex as $resource => $description)
          {
            if($resource != $datasetUri && array_search($resource, $statementsUri) === FALSE)
            {
              array_push($irsUri, $resource);
            }
          }
          
          // Fourth: Track the record description changes
          if($this->ws->track_create === TRUE)
          {
            foreach($irsUri as $uri)
            {
              // First check if the record is already existing for this record, within this dataset.
              $ws_cr = new CrudRead($uri, $this->ws->dataset, FALSE, TRUE, $this->ws->registered_ip, $this->ws->requester_ip);
              
              $ws_cr->ws_conneg("application/rdf+xml", "utf-8", "identity", "en");

              $ws_cr->process();

              $oldRecordDescription = $ws_cr->ws_serialize();
              
              $ws_cr_error = $ws_cr->pipeline_getError();
              
              if($ws_cr->pipeline_getResponseHeaderStatus() == 400 && $ws_cr_error->id == "WS-CRUD-READ-300")
              {
                // The record is not existing within this dataset, so we simply move-on
                continue;
              }          
              elseif($ws_cr->pipeline_getResponseHeaderStatus() != 200)
              {
                // An error occured. Since we can't get the past state of a record, we have to send an error
                // for the CrudCreate call since we can't create a tracking record for this record.
                $this->ws->conneg->setStatus(400);
                $this->ws->conneg->setStatusMsg("Bad Request");
                $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
                  $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, 
                  "We can't create a track record for the following record: $uri",
                  $this->ws->errorMessenger->_305->level);
                  
                break;
              }    
              
              $endpoint = "";
              if($this->ws->tracking_endpoint != "")
              {
                // We send the query to a remove tracking endpoint
                $endpoint = $this->ws->tracking_endpoint."create/";
              }
              else
              {
                // We send the query to a local tracking endpoint
                $endpoint = $this->ws->wsf_base_url."/ws/tracker/create/";
              }

              $wsq = new WebServiceQuerier($endpoint, "post",
                "text/xml", "from_dataset=" . urlencode($this->ws->dataset) .
                "&record=" . urlencode($uri) .
                "&action=create" .
                "&previous_state=" . urlencode($oldRecordDescription) .
                "&previous_state_mime=" . urlencode("application/rdf+xml") .
                "&performer=" . urlencode($this->ws->registered_ip) .
                "&registered_ip=self");

              if($wsq->getStatus() != 200)
              {
                $this->ws->conneg->setStatus($wsq->getStatus());
                $this->ws->conneg->setStatusMsg($wsq->getStatusMessage());
                /*
                $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
                  $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, odbc_errormsg(),
                  $this->ws->errorMessenger->_302->level);                
                */
              }

              unset($wsq);              
            }
          }        
          
          // If the query is still valid
          if($this->ws->conneg->getStatus() == 200)        
          {
            // Index all the instance records in the dataset
            if($this->ws->mode == "full" || $this->ws->mode == "triplestore")
            {
              $irs = array();

              foreach($irsUri as $uri)
              {
                $irs[$uri] = $resourceIndex[$uri];
              }
              
              $this->ws->db->query("DB.DBA.RDF_LOAD_RDFXML_MT('"
                . str_replace("'", "\'", $rdfxmlSerializer->getSerializedIndex($irs)) . "', '" . $this->ws->dataset . "', '"
                . $this->ws->dataset . "')");

              if(odbc_error())
              {
                $this->ws->conneg->setStatus(400);
                $this->ws->conneg->setStatusMsg("Bad Request");
                $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
                  $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, odbc_errormsg(),
                  $this->ws->errorMessenger->_302->level);

                return;
              }

              unset($irs);

              // Index all the reification statements into the statements graph
              $statements = array();

              foreach($statementsUri as $uri)
              {
                $statements[$uri] = $resourceIndex[$uri];
              }

              $this->ws->db->query("DB.DBA.RDF_LOAD_RDFXML_MT('"
                . str_replace("'", "\'", $rdfxmlSerializer->getSerializedIndex($statements)) . "', '" . $this->ws->dataset
                  . "reification/', '" . $this->ws->dataset . "reification/')");

              if(odbc_error())
              {
                $this->ws->conneg->setStatus(400);
                $this->ws->conneg->setStatusMsg("Bad Request");
                $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
                  $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, odbc_errormsg(),
                  $this->ws->errorMessenger->_302->level);
                return;
              }

              unset($statements);

              /* Link the dataset description of the file, by using the wsf:meta property, 
                 to its internal description (dataset graph description)
              */
              if($datasetUri != "")
              {
                $datasetRes[$datasetUri] = $resourceIndex[$datasetUri];

                $datasetRes[$this->ws->dataset] =
                  array( "http://purl.org/ontology/wsf#meta" => array( array ("value" => $datasetUri, "type" => "uri") ) );

                $datasetDescription = $resourceIndex[$datasetRes];

                /*  Make the link between the dataset description and its "meta" description (all other 
                    information than its basic description)
                */
                $this->ws->db->query("DB.DBA.RDF_LOAD_RDFXML_MT('"
                  . str_replace("'", "\'", $rdfxmlSerializer->getSerializedIndex($datasetRes)) . "', '" . $this->ws->wsf_graph
                    . "datasets/', '" . $this->ws->wsf_graph . "datasets/')");

                if(odbc_error())
                {
                  $this->ws->conneg->setStatus(400);
                  $this->ws->conneg->setStatusMsg("Bad Request");
                  $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
                    $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, "",
                    $this->ws->errorMessenger->_302->level);
                  return;
                }

                unset($datasetRes);
              }
            }
            
            // Index everything in Solr
            if($this->ws->mode == "full" || $this->ws->mode == "searchindex")
            {
              $labelProperties = array (Namespaces::$iron . "prefLabel", Namespaces::$iron . "altLabel",
                Namespaces::$skos_2008 . "prefLabel", Namespaces::$skos_2008 . "altLabel",
                Namespaces::$skos_2004 . "prefLabel", Namespaces::$skos_2004 . "altLabel", Namespaces::$rdfs . "label",
                Namespaces::$dcterms . "title", Namespaces::$foaf . "name", Namespaces::$foaf . "givenName",
                Namespaces::$foaf . "family_name");

              $descriptionProperties = array (Namespaces::$iron . "description", Namespaces::$dcterms . "description",
                Namespaces::$skos_2008 . "definition", Namespaces::$skos_2004 . "definition");

              $filename = rtrim($this->ws->ontological_structure_folder, "/") . "/classHierarchySerialized.srz";
              
              $file = fopen($filename, "r");
              $classHierarchy = fread($file, filesize($filename));
              $classHierarchy = unserialize($classHierarchy);                        
              fclose($file);
              
              if($classHierarchy === FALSE)
              {
                $this->ws->conneg->setStatus(500);
                $this->ws->conneg->setStatusMsg("Internal Error");
                $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
                  $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, "",
                  $this->ws->errorMessenger->_306->level);
                return;
              }

              // Index in Solr

              $solr = new Solr($this->ws->wsf_solr_core, $this->ws->solr_host, $this->ws->solr_port, $this->ws->fields_index_folder);

              // Used to detect if we will be creating a new field. If we are, then we will
              // update the fields index once the new document will be indexed.
              $indexedFields = $solr->getFieldsIndex();  
              $newFields = FALSE;            
              
              foreach($irsUri as $subject)
              {
                // Skip Bnodes indexation in Solr
                // One of the prerequise is that each records indexed in Solr (and then available in Search and Browse)
                // should have a URI. Bnodes are simply skiped.

                if(stripos($subject, "_:arc") !== FALSE)
                {
                  continue;
                }

                $add = "<add><doc><field name=\"uid\">" . md5($this->ws->dataset . $subject) . "</field>";
                $add .= "<field name=\"uri\">".$this->ws->xmlEncode($subject)."</field>";
                $add .= "<field name=\"dataset\">" . $this->ws->dataset . "</field>";

                // Get types for this subject.
                $types = array();

                foreach($resourceIndex[$subject]["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"] as $value)
                {
                  array_push($types, $value["value"]);

                  $add .= "<field name=\"type\">" . $this->ws->xmlEncode($value["value"]) . "</field>";
                  $add .= "<field name=\"" . urlencode("http://www.w3.org/1999/02/22-rdf-syntax-ns#type") . "_attr_facets\">" . $this->ws->xmlEncode($value["value"])
                    . "</field>";
                  
                }

                // get the preferred and alternative labels for this resource
                $prefLabelFound = FALSE;

                foreach($labelProperties as $property)
                {
                  if(isset($resourceIndex[$subject][$property]) && !$prefLabelFound)
                  {
                    $prefLabelFound = TRUE;
                    $add .= "<field name=\"prefLabel\">" . $this->ws->xmlEncode($resourceIndex[$subject][$property][0]["value"])
                      . "</field>";
                    $add .= "<field name=\"prefLabelAutocompletion\">" . $this->ws->xmlEncode($resourceIndex[$subject][$property][0]["value"])
                      . "</field>";
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$iron . "prefLabel") . "</field>";
                    
                    $add .= "<field name=\"" . urlencode($this->ws->xmlEncode(Namespaces::$iron . "prefLabel")) . "_attr_facets\">" . $this->ws->xmlEncode($resourceIndex[$subject][$property][0]["value"])
                      . "</field>";
                  }
                  elseif(isset($resourceIndex[$subject][$property]))
                  {
                    foreach($resourceIndex[$subject][$property] as $value)
                    {
                      $add .= "<field name=\"altLabel\">" . $this->ws->xmlEncode($value["value"]) . "</field>";
                      $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$iron . "altLabel") . "</field>";
                      $add .= "<field name=\"" . urlencode($this->ws->xmlEncode(Namespaces::$iron . "altLabel")) . "_attr_facets\">" . $this->ws->xmlEncode($value["value"])
                        . "</field>";
                    }
                  }
                }
                
                // If no labels are found for this resource, we use the ending of the URI as the label
                if(!$prefLabelFound)
                {
                  if(strrpos($subject, "#"))
                  {
                    $add .= "<field name=\"prefLabel\">" . substr($subject, strrpos($subject, "#") + 1) . "</field>";                   
                    $add .= "<field name=\"prefLabelAutocompletion\">" . substr($subject, strrpos($subject, "#") + 1) . "</field>";                   
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$iron . "prefLabel") . "</field>";
                    $add .= "<field name=\"" . urlencode($this->ws->xmlEncode(Namespaces::$iron . "prefLabel")) . "_attr_facets\">" . $this->ws->xmlEncode(substr($subject, strrpos($subject, "#") + 1))
                      . "</field>";
                  }
                  elseif(strrpos($subject, "/"))
                  {
                    $add .= "<field name=\"prefLabel\">" . substr($subject, strrpos($subject, "/") + 1) . "</field>";                   
                    $add .= "<field name=\"prefLabelAutocompletion\">" . substr($subject, strrpos($subject, "/") + 1) . "</field>";                   
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$iron . "prefLabel") . "</field>";
                    $add .= "<field name=\"" . urlencode($this->ws->xmlEncode(Namespaces::$iron . "prefLabel")) . "_attr_facets\">" . $this->ws->xmlEncode(substr($subject, strrpos($subject, "/") + 1))
                      . "</field>";
                  }
                }

                // get the description of the resource
                foreach($descriptionProperties as $property)
                {
                  if(isset($resourceIndex[$subject][$property]))
                  {
                    $add .= "<field name=\"description\">"
                      . $this->ws->xmlEncode($resourceIndex[$subject][$property][0]["value"]) . "</field>";
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$iron . "description") . "</field>";
                    $add .= "<field name=\"" . urlencode($this->ws->xmlEncode(Namespaces::$iron . "description")) . "_attr_facets\">" . $this->ws->xmlEncode($resourceIndex[$subject][$property][0]["value"])
                      . "</field>";
                    break;
                  }
                }

                // Add the prefURL if available
                if(isset($resourceIndex[$subject][Namespaces::$iron . "prefURL"]))
                {
                  $add .= "<field name=\"prefURL\">"
                          . $this->ws->xmlEncode($resourceIndex[$subject][Namespaces::$iron . "prefURL"][0]["value"]) . "</field>";
                  $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$iron . "prefURL") . "</field>";
                  $add .= "<field name=\"" . urlencode($this->ws->xmlEncode(Namespaces::$iron . "prefURL")) . "_attr_facets\">" . $this->ws->xmlEncode($resourceIndex[$subject][Namespaces::$iron . "prefURL"][0]["value"])
                    . "</field>";
                }

                // If enabled, and supported by the structWSF setting, let's add any lat/long positionning to the index.
                if($this->ws->geoEnabled)
                {
                  // Check if there exists a lat-long coordinate for that resource.
                  if(isset($resourceIndex[$subject][Namespaces::$geo."lat"]) &&
                     isset($resourceIndex[$subject][Namespaces::$geo."long"]))
                  {  
                    $lat = str_replace(",", ".", $resourceIndex[$subject][Namespaces::$geo."lat"][0]["value"]);
                    $long = str_replace(",", ".", $resourceIndex[$subject][Namespaces::$geo."long"][0]["value"]);
                    
                    // Add Lat/Long
                    $add .= "<field name=\"lat\">". 
                               $this->ws->xmlEncode($lat). 
                            "</field>";
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$geo."lat") . "</field>";
                            
                    $add .= "<field name=\"long\">". 
                               $this->ws->xmlEncode($long). 
                            "</field>";
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$geo."long") . "</field>";
                                                
                    // Add hashcode
                            
                    $add .= "<field name=\"geohash\">". 
                                 "$lat,$long".
                            "</field>"; 
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$sco."geohash") . "</field>";                          
                            
                    // Add cartesian tiers                   
                                    
                    // Note: Cartesian tiers are not currently supported. The Lucene Java API
                    //       for this should be ported to PHP to enable this feature.                                
                  }
                  
                  $coordinates = array();
                  
                  // Check if there is a polygonCoordinates property
                  if(isset($resourceIndex[$subject][Namespaces::$sco."polygonCoordinates"]))
                  {  
                    foreach($resourceIndex[$subject][Namespaces::$sco."polygonCoordinates"] as $polygonCoordinates)
                    {
                      $coordinates = explode(" ", $polygonCoordinates["value"]);
                      
                      $add .= "<field name=\"polygonCoordinates\">". 
                                 $this->ws->xmlEncode($polygonCoordinates["value"]). 
                              "</field>";   
                      $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$sco."polygonCoordinates") . "</field>";                                             
                    }
                  }
                  
                  // Check if there is a polylineCoordinates property
                  if(isset($resourceIndex[$subject][Namespaces::$sco."polylineCoordinates"]))
                  {  
                    foreach($resourceIndex[$subject][Namespaces::$sco."polylineCoordinates"] as $polylineCoordinates)
                    {
                      $coordinates = array_merge($coordinates, explode(" ", $polylineCoordinates["value"]));
                      
                      $add .= "<field name=\"polylineCoordinates\">". 
                                 $this->ws->xmlEncode($polylineCoordinates["value"]). 
                              "</field>";   
                      $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$sco."polylineCoordinates") . "</field>";                   
                    }
                  }
                  
                    
                  if(count($coordinates) > 0)
                  { 
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$geo."lat") . "</field>";
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$geo."long") . "</field>";
                    
                    foreach($coordinates as $key => $coordinate)
                    {
                      $points = explode(",", $coordinate);
                      
                      if($points[0] != "" && $points[1] != "")
                      {
                        // Add Lat/Long
                        $add .= "<field name=\"lat\">". 
                                   $this->ws->xmlEncode($points[1]). 
                                "</field>";
                                
                        $add .= "<field name=\"long\">". 
                                   $this->ws->xmlEncode($points[0]). 
                                "</field>";
                                
                        // Add altitude
                        if(isset($points[2]) && $points[2] != "")
                        {
                          $add .= "<field name=\"alt\">". 
                                     $this->ws->xmlEncode($points[2]). 
                                  "</field>";
                          if($key == 0)
                          {
                            $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$geo."alt") . "</field>";
                          }
                        }
                                                        
                        // Add hashcode
                        $add .= "<field name=\"geohash\">". 
                                   $points[1].",".$points[0].
                                "</field>"; 
                                
                        if($key == 0)
                        {
                          $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$sco."geohash") . "</field>";
                        }
                                
                                
                        // Add cartesian tiers                   
                                        
                        // Note: Cartesian tiers are not currently supported. The Lucene Java API
                        //       for this should be ported to PHP to enable this feature.           
                      }                                         
                    }
                  }                
                  
                  // Check if there is any geonames:locatedIn assertion for that resource.
                  if(isset($resourceIndex[$subject][Namespaces::$geonames."locatedIn"]))
                  {  
                    $add .= "<field name=\"located_in\">". 
                               $this->ws->xmlEncode($resourceIndex[$subject][Namespaces::$geonames."locatedIn"][0]["value"]). 
                            "</field>";         

                    $add .= "<field name=\"" . urlencode($this->ws->xmlEncode(Namespaces::$geonames . "locatedIn")) . "_attr_facets\">" . $this->ws->xmlEncode($resourceIndex[$subject][Namespaces::$geonames."locatedIn"][0]["value"])
                      . "</field>";                                                 
                  }
                  
                  // Check if there is any wgs84_pos:alt assertion for that resource.
                  if(isset($resourceIndex[$subject][Namespaces::$geo."alt"]))
                  {  
                    $add .= "<field name=\"alt\">". 
                               $this->ws->xmlEncode($resourceIndex[$subject][Namespaces::$geo."alt"][0]["value"]). 
                            "</field>";                                
                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode(Namespaces::$geo."alt") . "</field>";
                  }                
                }

                // Get properties with the type of the object
                
                foreach($resourceIndex[$subject] as $predicate => $values)
                {
                  if(array_search($predicate, $labelProperties) === FALSE && 
                     array_search($predicate, $descriptionProperties) === FALSE && 
                     $predicate != Namespaces::$iron."prefURL" &&
                     $predicate != Namespaces::$geo."long" &&
                     $predicate != Namespaces::$geo."lat" &&
                     $predicate != Namespaces::$geo."alt" &&
                     $predicate != Namespaces::$sco."polygonCoordinates" &&
                     $predicate != Namespaces::$sco."polylineCoordinates") // skip label & description & prefURL properties
                  {
                    foreach($values as $value)
                    {
                      if($value["type"] == "literal")
                      {
                        // Detect if the field currently exists in the fields index 
                        if(!$newFields && 
                           array_search(urlencode($predicate) . "_attr", $indexedFields) === FALSE &&
                           array_search(urlencode($predicate) . "_attr_date", $indexedFields) === FALSE &&
                           array_search(urlencode($predicate) . "_attr_int", $indexedFields) === FALSE &&
                           array_search(urlencode($predicate) . "_attr_float", $indexedFields) === FALSE)
                        {
                          $newFields = TRUE;
                        }
                        
                        // Check the datatype of the datatype property
                        $filename = rtrim($this->ws->ontological_structure_folder, "/") . "/propertyHierarchySerialized.srz";
                        
                        $file = fopen($filename, "r");
                        $propertyHierarchy = fread($file, filesize($filename));
                        $propertyHierarchy = unserialize($propertyHierarchy);                        
                        fclose($file);
                        
                        if($propertyHierarchy === FALSE)
                        {
                          $this->ws->conneg->setStatus(500);   
                          $this->ws->conneg->setStatusMsg("Internal Error");
                          $this->ws->conneg->setError($this->ws->errorMessenger->_310->id, $this->ws->errorMessenger->ws,
                            $this->ws->errorMessenger->_310->name, $this->ws->errorMessenger->_310->description, "",
                            $this->ws->errorMessenger->_310->level);
                          return;
                        }                         
                        
                        $property = $propertyHierarchy->getProperty($predicate);

                        if(is_array($property->range) && array_search("http://www.w3.org/2001/XMLSchema#dateTime", $property->range) !== FALSE)
                        {
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr_date\">" . $this->ws->xmlEncode($this->safeDate($value["value"]))
                            . "</field>";
                        }
                        elseif(is_array($property->range) && array_search("http://www.w3.org/2001/XMLSchema#int", $property->range) !== FALSE ||
                               is_array($property->range) && array_search("http://www.w3.org/2001/XMLSchema#integer", $property->range) !== FALSE)
                        {
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr_int\">" . $this->ws->xmlEncode($value["value"])
                            . "</field>";
                        }
                        elseif(is_array($property->range) && array_search("http://www.w3.org/2001/XMLSchema#float", $property->range) !== FALSE)
                        {
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr_float\">" . $this->ws->xmlEncode($value["value"])
                            . "</field>";
                        }
                        else
                        {
                          // By default, the datatype used is a literal/string
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr\">" . $this->ws->xmlEncode($value["value"])
                            . "</field>";                          
                        }
                        
                        $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode($predicate) . "</field>";
                        $add .= "<field name=\"" . urlencode($predicate) . "_attr_facets\">" . $this->ws->xmlEncode($value["value"])
                          . "</field>";

                        /* 
                           Check if there is a reification statement for that triple. If there is one, we index it in 
                           the index as:
                           <property> <text>
                           Note: Eventually we could want to update the Solr index to include a new "reifiedText" field.
                        */
                        foreach($statementsUri as $statementUri)
                        {
                          if($resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"][0]["value"]
                            == $subject
                              && $resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"][0][
                                "value"] == $predicate
                              && $resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#object"][0][
                                "value"] == $value["value"])
                          {
                            foreach($resourceIndex[$statementUri] as $reiPredicate => $reiValues)
                            {
                              if($reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type"
                                && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"
                                && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"
                                && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#object")
                              {
                                foreach($reiValues as $reiValue)
                                {
                                  if($reiValue["type"] == "literal")
                                  {
                                    // Attribute used to reify information to a statement.
                                    $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_attr\">"
                                      . $this->ws->xmlEncode($predicate) .
                                      "</field>";

                                    $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_obj\">"
                                      . $this->ws->xmlEncode($value["value"]) .
                                      "</field>";

                                    $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_value\">"
                                      . $this->ws->xmlEncode($reiValue["value"]) .
                                      "</field>";

                                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode($reiPredicate) . "</field>";
                                  }
                                }
                              }
                            }
                          }
                        }
                      }
                      elseif($value["type"] == "uri")
                      {
                        // Detect if the field currently exists in the fields index 
                        if(!$newFields && array_search(urlencode($predicate) . "_attr", $indexedFields) === FALSE)
                        {
                          $newFields = TRUE;
                        }                      
                        
                        // If it is an object property, we want to bind labels of the resource referenced by that
                        // object property to the current resource. That way, if we have "paul" -- know --> "bob", and the
                        // user send a seach query for "bob", then "paul" will be returned as well.
                        $query = $this->ws->db->build_sparql_query("select ?p ?o from <" . $this->ws->dataset . "> where {<"
                          . $value["value"] . "> ?p ?o.}", array ('p', 'o'), FALSE);

                        $resultset3 = $this->ws->db->query($query);

                        $subjectTriples = array();

                        while(odbc_fetch_row($resultset3))
                        {
                          $p = odbc_result($resultset3, 1);
                          $o = $this->ws->db->odbc_getPossibleLongResult($resultset3, 2);

                          if(!isset($subjectTriples[$p]))
                          {
                            $subjectTriples[$p] = array();
                          }

                          array_push($subjectTriples[$p], $o);
                        }

                        unset($resultset3);

                        // We allign all label properties values in a single string so that we can search over all of them.
                        $labels = "";

                        foreach($labelProperties as $property)
                        {
                          if(isset($subjectTriples[$property]))
                          {
                            $labels .= $subjectTriples[$property][0] . " ";
                          }
                        }
                        
                        // Detect if the field currently exists in the fields index 
                        if(!$newFields && array_search(urlencode($predicate) . "_attr_obj", $indexedFields) === FALSE)
                        {
                          $newFields = TRUE;
                        }
                        
                        // Let's check if this URI refers to a know class record in the ontological structure.
                        if($labels == "")                                                                                       
                        {
                          if(isset($classHierarchy->classes[$value["value"]]))
                          {
                            $labels .= $classHierarchy->classes[$value["value"]]->label." ";
                          }
                        }

                        if($labels != "")
                        {
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr_obj\">" . $this->ws->xmlEncode($labels)
                            . "</field>";
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr_obj_uri\">"
                            . $this->ws->xmlEncode($value["value"]) . "</field>";
                          $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode($predicate) . "</field>";
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr_facets\">" . $this->ws->xmlEncode($labels)
                            . "</field>";                        
                        }
                        else
                        {
                          // If no label is found, we may want to manipulate the ending of the URI to create
                          // a "temporary" pref label for that object, and then to index it as a search string.
                          $pos = strripos($value["value"], "#");
                          
                          if($pos !== FALSE)
                          {
                            $temporaryLabel = substr($value["value"], $pos + 1);
                          }
                          else
                          {
                            $pos = strripos($value["value"], "/");

                            if($pos !== FALSE)
                            {
                              $temporaryLabel = substr($value["value"], $pos + 1);
                            }
                          }
                          
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr_obj\">" . $this->ws->xmlEncode($temporaryLabel)
                            . "</field>";
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr_obj_uri\">"
                            . $this->ws->xmlEncode($value["value"]) . "</field>";
                          $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode($predicate) . "</field>";
                          $add .= "<field name=\"" . urlencode($predicate) . "_attr_facets\">" . $this->ws->xmlEncode($temporaryLabel)
                            . "</field>";
                        }

                        /* 
                          Check if there is a reification statement for that triple. If there is one, we index it in the 
                          index as:
                          <property> <text>
                          Note: Eventually we could want to update the Solr index to include a new "reifiedText" field.
                        */
                        $statementAdded = FALSE;

                        foreach($statementsUri as $statementUri)
                        {
                          if($resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"][0]["value"]
                            == $subject
                              && $resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"][0][
                                "value"] == $predicate
                              && $resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#object"][0][
                                "value"] == $value["value"])
                          {
                            foreach($resourceIndex[$statementUri] as $reiPredicate => $reiValues)
                            {
                              if($reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type"
                                && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"
                                && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"
                                && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#object")
                              {
                                foreach($reiValues as $reiValue)
                                {
                                  if($reiValue["type"] == "literal")
                                  {
                                    // Attribute used to reify information to a statement.
                                    $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_attr_obj\">"
                                      . $this->ws->xmlEncode($predicate) .
                                      "</field>";

                                    $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_obj\">"
                                      . $this->ws->xmlEncode($value["value"]) .
                                      "</field>";

                                    $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_value\">"
                                      . $this->ws->xmlEncode($reiValue["value"]) .
                                      "</field>";

                                    $add .= "<field name=\"attribute\">" . $this->ws->xmlEncode($reiPredicate) . "</field>";
                                    $statementAdded = TRUE;
                                    break;
                                  }
                                }
                              }

                              if($statementAdded)
                              {
                                break;
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }

                // Get all types by inference
                $inferredTypes = array();
                
                foreach($types as $type)
                {
                  $superClasses = $classHierarchy->getSuperClasses($type);

                  foreach($superClasses as $sc)
                  {
                    if(array_search($sc->name, $inferredTypes) === FALSE)
                    {
                      array_push($inferredTypes, $sc->name);
                    }
                  }                 
                }
                
                foreach($inferredTypes as $sc)
                {
                  $add .= "<field name=\"inferred_type\">" . $this->ws->xmlEncode($sc) . "</field>";
                }              
                
                $add .= "</doc></add>";

                if(!$solr->update($add))
                {
                  $this->ws->conneg->setStatus(500);
                  $this->ws->conneg->setStatusMsg("Internal Error");
                  $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
                    $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, "",
                    $this->ws->errorMessenger->_303->level);
                  return;
                }
              }

              if($this->ws->solr_auto_commit === FALSE)
              {
                if(!$solr->commit())
                {
                  $this->ws->conneg->setStatus(500);
                  $this->ws->conneg->setStatusMsg("Internal Error");
                  $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
                    $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, "",
                    $this->ws->errorMessenger->_304->level);
                  return;
                }
              }
              
              // Update the fields index if a new field as been detected.
              if($newFields)
              {
                $solr->updateFieldsIndex();
              }
            }
          /*        
                  // Optimisation can be time consuming "on-the-fly" (which decrease user's experience)
                  if(!$solr->optimize())
                  {
                    $this->ws->conneg->setStatus(500);
                    $this->ws->conneg->setStatusMsg("Internal Error");
                    $this->ws->conneg->setStatusMsgExt("Error #crud-create-106");
                    return;          
                  }
          */
          }
        }
      }      
    }
  }
?>
