<?php
  
  namespace StructuredDynamics\osf\ws\ontology\update\interfaces; 
  
  use \StructuredDynamics\osf\framework\Namespaces;  
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  use \StructuredDynamics\osf\ws\framework\OWLOntology;
  use \StructuredDynamics\osf\ws\ontology\read\OntologyRead;
  use \StructuredDynamics\osf\ws\crud\delete\CrudDelete;
  use \StructuredDynamics\osf\ws\crud\create\CrudCreate;
  use \StructuredDynamics\osf\ws\crud\update\CrudUpdate;
  use \StructuredDynamics\osf\ws\revision\update\RevisionUpdate;
  use \StructuredDynamics\osf\ws\revision\lister\RevisionLister;
  use \StructuredDynamics\osf\framework\Resultset;
  use \ARC2;
  use \Exception;
  
  class DefaultSourceInterface extends SourceInterface
  {
    private $OwlApiSession = null;    
    
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "3.0";
    }
    
    private function invalidateOntologiesCache()
    {
      if($this->ws->memcached_enabled)
      {
        $this->ws->invalidateCache('crud-read');
        $this->ws->invalidateCache('search');
        $this->ws->invalidateCache('sparql');  
        $this->ws->invalidateCache('ontology-read:getserialized');
        $this->ws->invalidateCache('ontology-read:getclass');
        $this->ws->invalidateCache('ontology-read:getclasses');
        $this->ws->invalidateCache('ontology-read:getnamedindividual');
        $this->ws->invalidateCache('ontology-read:getnamedindividuals');
        $this->ws->invalidateCache('ontology-read:getsubclasses');
        $this->ws->invalidateCache('ontology-read:getsuperclasses');
        $this->ws->invalidateCache('ontology-read:getequivalentclasses');
        $this->ws->invalidateCache('ontology-read:getdisjointclasses');
        $this->ws->invalidateCache('ontology-read:getontologies');
        $this->ws->invalidateCache('ontology-read:getloadedontologies');
        $this->ws->invalidateCache('ontology-read:getserializedclasshierarchy');
        $this->ws->invalidateCache('ontology-read:getserializedpropertyhierarchy');
        $this->ws->invalidateCache('ontology-read:getironxmlschema');
        $this->ws->invalidateCache('ontology-read:getironjsonschema');
        $this->ws->invalidateCache('ontology-read:getproperty');
        $this->ws->invalidateCache('ontology-read:getproperties');
        $this->ws->invalidateCache('ontology-read:getsubproperties');
        $this->ws->invalidateCache('ontology-read:getsuperproperties');
        $this->ws->invalidateCache('ontology-read:getequivalentproperties');
        $this->ws->invalidateCache('ontology-read:getdisjointproperties');      
        $this->ws->invalidateCache('auth-validator');
        $this->ws->invalidateCache('auth-lister:dataset');
        $this->ws->invalidateCache('auth-lister:ws');
        $this->ws->invalidateCache('auth-lister:groups');
        $this->ws->invalidateCache('auth-lister:group_users');
        $this->ws->invalidateCache('auth-lister:access_user');
        $this->ws->invalidateCache('auth-lister:access_dataset');
        $this->ws->invalidateCache('auth-lister:access_group');
        $this->ws->invalidateCache('dataset-read');
        $this->ws->invalidateCache('dataset-read:all');
        $this->ws->invalidateCache('revision-read');
        $this->ws->invalidateCache('revision-lister');
        $this->ws->invalidateCache('crud-property');
        $this->ws->invalidateCache('class-superclasses');
      }
    }    
    
    /**
    * 
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    private function getOntologyReference()
    {
      try
      {
        $this->ws->ontology = new OWLOntology($this->ws->ontologyUri, $this->OwlApiSession, TRUE, strtolower($this->ws->owlapiReasoner));
      }
      catch(Exception $e)
      {
        $this->ws->returnError(400, "Bad Request", "_300");
      }    
    }   
    
    private function in_array_r($needle, $haystack) 
    {
      foreach($haystack as $item) 
      {
        if($item === $needle || (is_array($item) && $this->in_array_r($needle, $item))) 
        {
          return TRUE;
        }
      }

      return FALSE;
    }
    
    /**
    * 
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    private function initiateOwlBridgeSession()
    {
      // Starts the OWLAPI process/bridge
      require_once($this->ws->owlapiBridgeURI);

      // Create the OWLAPI session object that could have been persisted on the OWLAPI instance.
      // Second param "false" => we re-use the pre-created session without destroying the previous one
      // third param "0" => it nevers timeout.
      if($this->OwlApiSession == null)
      {
        $this->OwlApiSession = java_session("OWLAPI", false, 0);
      }    
    }    
    
    /**
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    private function isValid()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        return(TRUE);
      }
      
      return(FALSE);    
    }    
    
    /**
    * Tag an ontology as being saved. This simply removes the "ontologyModified" annotation property.
    * The ontology has to be saved, on some local system, of the requester. That system has to 
    * export the ontology after calling "saveOntology", and save its serialization somewhere.
    * 
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    public function saveOntology()
    {
      $this->initiateOwlBridgeSession();

      $this->getOntologyReference();
      
      if($this->isValid())      
      {
        // Remove the "ontologyModified" annotation property value
        $this->ws->ontology->removeOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");
        
        $this->invalidateOntologiesCache();
      }
    }
    
    /**
    * Create a new, or update an existing entity based on the input RDF document.
    * 
    * @param mixed $document
    * @param mixed $advancedIndexation
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    public function createOrUpdateEntity($document, $advancedIndexation)
    {
      $this->initiateOwlBridgeSession();

      $this->getOntologyReference();
      
      if($this->isValid())      
      { 
        // Now read the RDF file that we got as input to update the ontology with it.
        // Basically, we list all the entities (classes, properties and instance)
        // and we update each of them, one by one, in both the OWLAPI instance
        // and OSF if the advancedIndexation is enabled.
        include_once("../../framework/arc2/ARC2.php");
        $parser = ARC2::getRDFParser();
        $parser->parse($this->ws->ontology->getBaseUri(), $document);
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
        
        // Get all entities
        foreach($resourceIndex as $uri => $description)
        {         
          $types = array();
          $literalValues = array();
          $objectValues = array();    
         
          foreach($description as $predicate => $values)
          {
            switch($predicate)
            {
              case Namespaces::$rdf."type":
                foreach($values as $value)
                {
                  array_push($types, $value["value"]);
                }
              break;
              
              default:
                foreach($values as $value)
                {
                  if($value["type"] == "literal")
                  {
                    if(!isset($literalValues[$predicate]))
                    {
                      $literalValues[$predicate] = array();
                    }
                    
                    if($this->in_array_r(Namespaces::$owl."Class", $description[Namespaces::$rdf."type"]))
                    {
                      array_push($literalValues[$predicate], $value['value']);  
                    }
                    elseif($this->in_array_r(Namespaces::$owl."DatatypeProperty", $description[Namespaces::$rdf."type"]) ||
                           $this->in_array_r(Namespaces::$owl."ObjectProperty", $description[Namespaces::$rdf."type"]) ||
                           $this->in_array_r(Namespaces::$owl."AnnotationProperty", $description[Namespaces::$rdf."type"]) ||
                           $this->in_array_r(Namespaces::$owl."Ontology", $description[Namespaces::$rdf."type"]))
                    {                    
                      array_push($literalValues[$predicate], $value);  
                    }
                  }
                  else
                  {
                    if(!isset($objectValues[$predicate]))
                    {
                      $objectValues[$predicate] = array();
                    }
                    
                    array_push($objectValues[$predicate], $value["value"]);                      
                  }
                }                
              break;
            }
          }
   
          // Call different API calls depending what we are manipulating
          if($this->in_array_r(Namespaces::$owl."Ontology", $description[Namespaces::$rdf."type"]))
          {
            $this->ws->ontology->updateOntology($literalValues, $objectValues); 
            
            // Make sure advanced indexation is off when updating an ontology's description
            $advancedIndexation = FALSE;
          }
          elseif($this->in_array_r(Namespaces::$owl."Class", $description[Namespaces::$rdf."type"]))
          {
            $this->ws->ontology->updateClass($uri, $literalValues, $objectValues); 
          }
          elseif($this->in_array_r(Namespaces::$owl."DatatypeProperty", $description[Namespaces::$rdf."type"]) ||
                 $this->in_array_r(Namespaces::$owl."ObjectProperty", $description[Namespaces::$rdf."type"]) ||
                 $this->in_array_r(Namespaces::$owl."AnnotationProperty", $description[Namespaces::$rdf."type"]))
          {
            foreach($types as $type)
            {
              if(!isset($objectValues[Namespaces::$rdf."type"]))
              {
                $objectValues[Namespaces::$rdf."type"] = array();
              }
              
              array_push($objectValues[Namespaces::$rdf."type"], $type);      
            }
          
            $this->ws->ontology->updateProperty($uri, $literalValues, $objectValues);   
          }
          else
          {
            $this->ws->ontology->updateNamedIndividual($uri, $types, $literalValues, $objectValues);   
          }
          
          // Call different API calls depending what we are manipulating
          if($advancedIndexation == TRUE)
          {          
            include_once("../../framework/arc2/ARC2.php");
            $rdfxmlParser = ARC2::getRDFParser();
            $rdfxmlSerializer = ARC2::getRDFXMLSerializer();
            
            $resourcesIndex = $rdfxmlParser->getSimpleIndex(0);
            
            // Index the entity to update
            $rdfxmlParser->parse($uri, $rdfxmlSerializer->getSerializedIndex(array($uri => $resourceIndex[$uri])));
            $rIndex = $rdfxmlParser->getSimpleIndex(0);
            $resourcesIndex = ARC2::getMergedIndex($resourcesIndex, $rIndex);                    
            
            // Check if the entity got punned
            $entities = $this->ws->ontology->_getEntities($uri);
            
            if(count($entities) > 1)
            {
              // The entity got punned.
              $isClass = FALSE;
              $isProperty = FALSE;
              $isNamedEntity = FALSE;
              
              
              foreach($entities as $entity)
              {
                if((boolean)java_values($entity->isOWLClass()))
                {
                  $isClass = TRUE;
                }              
                
                if((boolean)java_values($entity->isOWLDataProperty()) ||
                   (boolean)java_values($entity->isOWLObjectProperty()) ||
                   (boolean)java_values($entity->isOWLAnnotationProperty()))
                {
                  $isProperty = TRUE;
                }
                
                if((boolean)java_values($entity->isOWLNamedIndividual()))
                { 
                  $isNamedEntity = TRUE;
                }             
              }
              
              $queries = array();
              
              if($description[Namespaces::$rdf."type"][0]["value"] != Namespaces::$owl."Class" && $isClass)
              {
                array_push($queries, array("function" => "getClass", "params" => "uri=".$uri));
              }
              
              if($description[Namespaces::$rdf."type"][0]["value"] != Namespaces::$owl."DatatypeProperty" && 
                 $description[Namespaces::$rdf."type"][0]["value"] != Namespaces::$owl."ObjectProperty" &&
                 $description[Namespaces::$rdf."type"][0]["value"] != Namespaces::$owl."AnnotationProperty" &&
                 $isProperty)
              {
                array_push($queries, array("function" => "getProperty", "params" => "uri=".$uri));
              }
              
              if($description[Namespaces::$rdf."type"][0]["value"] != Namespaces::$owl."NamedIndividual" && $isNamedEntity)
              {
                array_push($queries, array("function" => "getNamedIndividual", "params" => "uri=".$uri));
              }            
              
              foreach($queries as $query)
              {
                // Get the class description of the current punned entity
                $ontologyRead = new OntologyRead($this->ws->ontologyUri, $query["function"], $query["params"]);

                // Since we are in pipeline mode, we have to set the owlapisession using the current one.
                // otherwise the java bridge will return an error
                $ontologyRead->setOwlApiSession($this->OwlApiSession);                                                    
                                  
                $ontologyRead->ws_conneg("application/rdf+xml", 
                                        (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                        (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                        (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : ""));

                if($this->ws->reasoner)
                {
                  $ontologyRead->useReasoner(); 
                }  
                else
                {
                  $ontologyRead->stopUsingReasoner();
                }                                     
                                       
                $ontologyRead->process();
                
                if($ontologyRead->pipeline_getResponseHeaderStatus() != 200)
                {
                  $this->ws->conneg->setStatus($ontologyRead->pipeline_getResponseHeaderStatus());
                  $this->ws->conneg->setStatusMsg($ontologyRead->pipeline_getResponseHeaderStatusMsg());
                  $this->ws->conneg->setStatusMsgExt($ontologyRead->pipeline_getResponseHeaderStatusMsgExt());
                  $this->ws->conneg->setError($ontologyRead->pipeline_getError()->id, $ontologyRead->pipeline_getError()->webservice,
                    $ontologyRead->pipeline_getError()->name, $ontologyRead->pipeline_getError()->description,
                    $ontologyRead->pipeline_getError()->debugInfo, $ontologyRead->pipeline_getError()->level);

                  return;
                } 
                
                $entitySerialized = $ontologyRead->pipeline_serialize();
                
                $rdfxmlParser->parse($uri, $entitySerialized);
                $rIndex = $rdfxmlParser->getSimpleIndex(0);
                $resourcesIndex = ARC2::getMergedIndex($resourcesIndex, $rIndex);                
                
                unset($ontologyRead);            
              }
            }                   
            
            switch($description[Namespaces::$rdf."type"][0]["value"])
            {
              case Namespaces::$owl."Class":
              case Namespaces::$owl."DatatypeProperty":
              case Namespaces::$owl."ObjectProperty":
              case Namespaces::$owl."AnnotationProperty":
              case Namespaces::$owl."NamedIndividual":
              default:
              
                // We have to check if this entity to update is punned. If yes, we have to merge all the
                // punned descriptison together before updating them in OSF (Virtuoso and Solr).
                // otherwise we will loose information in these other systems.
                
                // Once we start the ontology creation process, we have to make sure that even if the server
                // loose the connection with the user the process will still finish.
                ignore_user_abort(true);

                // However, maybe there is an issue with the server handling that file tht lead to some kind of infinite or near
                // infinite loop; so we have to limit the execution time of this procedure to 45 mins.
                set_time_limit(2700);                
                
                $serializedResource = $rdfxmlSerializer->getSerializedIndex($resourcesIndex);
                
                // Update the classes and properties into the Solr index
                // Every time OntologyUpdate is called, a new revision will be created
                $crudUpdate = new CrudUpdate($serializedResource, "application/rdf+xml", $this->ws->ontologyUri);

                $crudUpdate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                                       (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                       (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                       (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

                $crudUpdate->process();
                
                if($crudUpdate->pipeline_getResponseHeaderStatus() != 200)
                {
                  if($crudUpdate->pipeline_getError()->id == 'WS-CRUD-UPDATE-315')
                  {
                    // If the WS-CRUD-UPDATE-315 error is returned, it means that we are creating
                    // this new entity into OSF, so we have to re-issue the query using
                    // the CRUD: Create endpoint
                    $crudCreate = new CrudCreate($serializedResource, "application/rdf+xml", 'full', $this->ws->ontologyUri);

                    $crudCreate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                                           (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                           (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                           (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

                    $crudCreate->process();
                    
                    if($crudCreate->pipeline_getResponseHeaderStatus() != 200)
                    {   
                      $this->ws->conneg->setStatus($crudCreate->pipeline_getResponseHeaderStatus());
                      $this->ws->conneg->setStatusMsg($crudCreate->pipeline_getResponseHeaderStatusMsg());
                      $this->ws->conneg->setStatusMsgExt($crudCreate->pipeline_getResponseHeaderStatusMsgExt());
                      $this->ws->conneg->setError($crudCreate->pipeline_getError()->id, $crudCreate->pipeline_getError()->webservice,
                        $crudCreate->pipeline_getError()->name, $crudCreate->pipeline_getError()->description,
                        $crudCreate->pipeline_getError()->debugInfo, $crudCreate->pipeline_getError()->level);

                      return;
                    }                                     
                  }
                  elseif($crudUpdate->pipeline_getError()->id == 'WS-CRUD-UPDATE-313')
                  {
                    // If the WS-CRUD-UPDATE-313 error is returned, it means that we are
                    // trying to re-create an entity that was previously existing and that
                    // got updated (and so, for which a revision got created). What we do in
                    // in this case is that we re-publish the latest revision and than we
                    // that we create a new version using what we got as input with this call.

                    reset($resourcesIndex);
                    $recordUri = key($resourcesIndex);                    
                    
                    $revisionLister = new RevisionLister($recordUri, $this->ws->ontologyUri, 'short');
                    
                    $revisionLister->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                                               (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                               (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                               (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

                    $revisionLister->process();
                    
                    if($revisionLister->pipeline_getResponseHeaderStatus() != 200)
                    {
                      $this->ws->conneg->setStatus($revisionLister->pipeline_getResponseHeaderStatus());
                      $this->ws->conneg->setStatusMsg($revisionLister->pipeline_getResponseHeaderStatusMsg());
                      $this->ws->conneg->setStatusMsgExt($revisionLister->pipeline_getResponseHeaderStatusMsgExt());
                      $this->ws->conneg->setError($revisionLister->pipeline_getError()->id, $revisionLister->pipeline_getError()->webservice,
                        $revisionLister->pipeline_getError()->name, $revisionLister->pipeline_getError()->description,
                        $revisionLister->pipeline_getError()->debugInfo, $revisionLister->pipeline_getError()->level);

                      return;                      
                    }
                    
                    $resultset = new Resultset();
                    
                    $resultset->importStructXMLResultset($revisionLister->pipeline_getResultset());
                    
                    $resultset = $resultset->getResultset();
                    
                    reset($resultset['unspecified']);
                    
                    $revisionUri = key($resultset['unspecified']);
                    
                    // Re-publish the latest version of the record
                    $revisionUpdate = new RevisionUpdate($revisionUri, $this->ws->ontologyUri, 'published');
                    
                    $revisionUpdate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                                               (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                               (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                               (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

                    $revisionUpdate->process();
                    
                    if($revisionUpdate->pipeline_getResponseHeaderStatus() != 200)
                    {       
                      $this->ws->conneg->setStatus($revisionUpdate->pipeline_getResponseHeaderStatus());
                      $this->ws->conneg->setStatusMsg($revisionUpdate->pipeline_getResponseHeaderStatusMsg());
                      $this->ws->conneg->setStatusMsgExt($revisionUpdate->pipeline_getResponseHeaderStatusMsgExt());
                      $this->ws->conneg->setError($revisionUpdate->pipeline_getError()->id, $revisionUpdate->pipeline_getError()->webservice,
                        $revisionUpdate->pipeline_getError()->name, $revisionUpdate->pipeline_getError()->description,
                        $revisionUpdate->pipeline_getError()->debugInfo, $revisionUpdate->pipeline_getError()->level);

                      return;
                    }                       
                    
                    // Now create a new published version of that record according to this Ontology: Update query
                    $reCrudUpdate = new CrudUpdate($serializedResource, "application/rdf+xml", $this->ws->ontologyUri);

                    $reCrudUpdate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                                             (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                             (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                             (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

                    $reCrudUpdate->process();
                    
                    if($reCrudUpdate->pipeline_getResponseHeaderStatus() != 200)
                    {       
                      $this->ws->conneg->setStatus($reCrudUpdate->pipeline_getResponseHeaderStatus());
                      $this->ws->conneg->setStatusMsg($reCrudUpdate->pipeline_getResponseHeaderStatusMsg());
                      $this->ws->conneg->setStatusMsgExt($reCrudUpdate->pipeline_getResponseHeaderStatusMsgExt());
                      $this->ws->conneg->setError($reCrudUpdate->pipeline_getError()->id, $reCrudUpdate->pipeline_getError()->webservice,
                        $reCrudUpdate->pipeline_getError()->name, $reCrudUpdate->pipeline_getError()->description,
                        $reCrudUpdate->pipeline_getError()->debugInfo, $reCrudUpdate->pipeline_getError()->level);

                      return;
                    }                    
                  }
                  else
                  {
                    $this->ws->conneg->setStatus($crudUpdate->pipeline_getResponseHeaderStatus());
                    $this->ws->conneg->setStatusMsg($crudUpdate->pipeline_getResponseHeaderStatusMsg());
                    $this->ws->conneg->setStatusMsgExt($crudUpdate->pipeline_getResponseHeaderStatusMsgExt());
                    $this->ws->conneg->setError($crudUpdate->pipeline_getError()->id, $crudUpdate->pipeline_getError()->webservice,
                      $crudUpdate->pipeline_getError()->name, $crudUpdate->pipeline_getError()->description,
                      $crudUpdate->pipeline_getError()->debugInfo, $crudUpdate->pipeline_getError()->level);

                    return;
                  }
                } 
                
                unset($crudUpdate);              
              
  /*            
                // Once we start the ontology creation process, we have to make sure that even if the server
                // loose the connection with the user the process will still finish.
                ignore_user_abort(true);

                // However, maybe there is an issue with the server handling that file tht lead to some kind of infinite or near
                // infinite loop; so we have to limit the execution time of this procedure to 45 mins.
                set_time_limit(2700);  
                
                $ser = ARC2::getTurtleSerializer();
                $serializedResource = $ser->getSerializedIndex(array($uri => $resourceIndex[$uri]));
                
                // Update the classes and properties into the Solr index
                $crudUpdate = new CrudUpdate($serializedResource, "application/rdf+n3", $this->ws->ontologyUri);

                $crudUpdate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
                  $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                $crudUpdate->process();
                
                if($crudUpdate->pipeline_getResponseHeaderStatus() != 200)
                {
                  $this->ws->conneg->setStatus($crudUpdate->pipeline_getResponseHeaderStatus());
                  $this->ws->conneg->setStatusMsg($crudUpdate->pipeline_getResponseHeaderStatusMsg());
                  $this->ws->conneg->setStatusMsgExt($crudUpdate->pipeline_getResponseHeaderStatusMsgExt());
                  $this->ws->conneg->setError($crudUpdate->pipeline_getError()->id, $crudUpdate->pipeline_getError()->webservice,
                    $crudUpdate->pipeline_getError()->name, $crudUpdate->pipeline_getError()->description,
                    $crudUpdate->pipeline_getError()->debugInfo, $crudUpdate->pipeline_getError()->level);

                  return;
                } 
                
                unset($crudUpdate);  
  */              
                            
              break;            
            }          
          }          
        }
        
        // Update the name of the file of the ontology to mark it as "changed"
        $this->ws->ontology->addOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");    
        
        $this->invalidateOntologiesCache();
      }
    }    
    
    /**
    * Update the URI of an entity
    * 
    * @param mixed $oldUri
    * @param mixed $newUri
    * @param mixed $advancedIndexation
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    public function updateEntityUri($oldUri, $newUri, $advancedIndexation)
    { 
      $this->initiateOwlBridgeSession();

      $this->getOntologyReference();
      
      if($this->isValid())      
      {
        if($oldUri == "")
        {
          $this->ws->returnError(400, "Bad Request", "_202");
          return;              
        }          
        if($newUri == "")
        {
          $this->ws->returnError(400, "Bad Request", "_203");
          return;              
        }      
        
        $this->ws->ontology->updateEntityUri($oldUri, $newUri);
        
        if($advancedIndexation === TRUE)
        {   
          // Find the type of entity manipulated here
          $entity = $this->ws->ontology->_getEntity($newUri);
          
          $function = "";
          $params = "";
          
          if((boolean)java_values($entity->isOWLClass()))
          {
            $function = "getClass";
            $params = "uri=".$newUri;
          }
          elseif((boolean)java_values($entity->isOWLDataProperty()) ||
             (boolean)java_values($entity->isOWLObjectProperty()) ||
             (boolean)java_values($entity->isOWLAnnotationProperty()))
          {
            $function = "getProperty";
            $params = "uri=".$newUri;
          }
          elseif((boolean)java_values($entity->isNamedIndividual()))
          {
            $function = "getNamedIndividual";
            $params = "uri=".$newUri;
          }
          else
          {
            return;
          }
          
          // Get the description of the newly updated entity.
          $ontologyRead = new OntologyRead($this->ws->ontologyUri, $function, $params);

          // Since we are in pipeline mode, we have to set the owlapisession using the current one.
          // otherwise the java bridge will return an error
          $ontologyRead->setOwlApiSession($this->OwlApiSession);                                                    
                            
          $ontologyRead->ws_conneg("application/rdf+xml", 
                                   (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                   (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                   (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : ""));

          if($this->ws->reasoner)
          {
            $ontologyRead->useReasoner(); 
          }  
          else
          {
            $ontologyRead->stopUsingReasoner();
          }                               
                                 
          $ontologyRead->process();
          
          if($ontologyRead->pipeline_getResponseHeaderStatus() != 200)
          {
            $this->ws->conneg->setStatus($ontologyRead->pipeline_getResponseHeaderStatus());
            $this->ws->conneg->setStatusMsg($ontologyRead->pipeline_getResponseHeaderStatusMsg());
            $this->ws->conneg->setStatusMsgExt($ontologyRead->pipeline_getResponseHeaderStatusMsgExt());
            $this->ws->conneg->setError($ontologyRead->pipeline_getError()->id, $ontologyRead->pipeline_getError()->webservice,
              $ontologyRead->pipeline_getError()->name, $ontologyRead->pipeline_getError()->description,
              $ontologyRead->pipeline_getError()->debugInfo, $ontologyRead->pipeline_getError()->level);

            return;
          } 
          
          $entitySerialized = $ontologyRead->pipeline_serialize();
          
          unset($ontologyRead);  

          // Delete the old entity in Solr        
          // Update the classes and properties into the Solr index
          // Use the default 'soft' mode such that we keep all the ontologies changes by default
          // This means that we "unpublish" the current record, with the current URI. We will always
          // keep information about that record, with that URI, even if the URI of the record is change
          // using this function
          $crudDelete = new CrudDelete($oldUri, $this->ws->ontologyUri);

          $crudDelete->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                                 (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                 (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                 (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

          $crudDelete->process();
          
          if($crudDelete->pipeline_getResponseHeaderStatus() != 200)
          {
            $this->ws->conneg->setStatus($crudDelete->pipeline_getResponseHeaderStatus());
            $this->ws->conneg->setStatusMsg($crudDelete->pipeline_getResponseHeaderStatusMsg());
            $this->ws->conneg->setStatusMsgExt($crudDelete->pipeline_getResponseHeaderStatusMsgExt());
            $this->ws->conneg->setError($crudDelete->pipeline_getError()->id, $crudDelete->pipeline_getError()->webservice,
              $crudDelete->pipeline_getError()->name, $crudDelete->pipeline_getError()->description,
              $crudDelete->pipeline_getError()->debugInfo, $crudDelete->pipeline_getError()->level);

            return;
          } 
          
          unset($crudDelete);                
          
          // Add the new entity in Solr

          // Update the classes and properties into the Solr index
          $crudCreate = new CrudCreate($entitySerialized, "application/rdf+xml", "full", $this->ws->ontologyUri);

          $crudCreate->ws_conneg((isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ""), 
                                 (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : ""), 
                                 (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ""), 
                                 (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "")); 

          $crudCreate->process();
          
          if($crudCreate->pipeline_getResponseHeaderStatus() != 200)
          {
            $this->ws->conneg->setStatus($crudCreate->pipeline_getResponseHeaderStatus());
            $this->ws->conneg->setStatusMsg($crudCreate->pipeline_getResponseHeaderStatusMsg());
            $this->ws->conneg->setStatusMsgExt($crudCreate->pipeline_getResponseHeaderStatusMsgExt());
            $this->ws->conneg->setError($crudCreate->pipeline_getError()->id, $crudCreate->pipeline_getError()->webservice,
              $crudCreate->pipeline_getError()->name, $crudCreate->pipeline_getError()->description,
              $crudCreate->pipeline_getError()->debugInfo, $crudCreate->pipeline_getError()->level);

            return;
          } 
          
          unset($crudCreate);                   
        }
            
        // Update the name of the file of the ontology to mark it as "changed"
        $this->ws->ontology->addOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");
        
        $this->invalidateOntologiesCache();
      }
    }
    
    
    public function processInterface()
    {
    }
  }
?>
