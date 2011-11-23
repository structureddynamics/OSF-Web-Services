<?php

/*! @defgroup WsOntology Ontology Management Web Service */
//@{

/*! @file \ws\ontology\update\OntologyUpdate.php
   @brief Update an ontology
   @description Update any resource of an ontology. It may be a class, object property, datatype property,
                annotation property or instances. Updating an ontology here means adding or modifying
                any resource in the ontology.

  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*! @brief Update an ontology
    @description Update any resource of an ontology. It may be a class, object property, datatype property,
                 annotation property or instances. Updating an ontology here means adding or modifying
                 any resource in the ontology.       
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class OntologyUpdate extends WebService
{
  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /*! @brief IP being registered */
  private $registered_ip = "";

  /*! @brief URI where the web service can fetch the ontology document */
  private $ontologyUri = "";
  
  /*! @brief Ontology object. */
  private $ontology;  

  /*! @brief Requester's IP used for request validation */
  private $requester_ip = "";
  
  private $OwlApiSession = null;
  
  /*! @brief enable/disable the reasoner when doing advanced indexation */
  private $reasoner = TRUE;  
  
  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/ontology/update/",
                        "_200": {
                          "id": "WS-ONTOLOGY-UPDATE-200",
                          "level": "Warning",
                          "name": "No Ontology URI defined for this request",
                          "description": "No Ontology URI defined for this request"
                        },
                        "_201": {
                          "id": "WS-ONTOLOGY-UPDATE-201",
                          "level": "Warning",
                          "name": "Unknown function call",
                          "description": "The function call being requested is unknown or unsupported by this Ontology Read web service endpoint"
                        },                        
                        "_202": {
                          "id": "WS-ONTOLOGY-UPDATE-202",
                          "level": "Warning",
                          "name": "The oldUri parameter not defined for this request",
                          "description": "You forgot to mention the oldUri parameter for this update request"
                        },                        
                        "_203": {
                          "id": "WS-ONTOLOGY-UPDATE-203",
                          "level": "Warning",
                          "name": "The newUri parameter not defined for this request",
                          "description": "You forgot to mention the newUri parameter for this update request"
                        },                        
                        "_300": {
                          "id": "WS-ONTOLOGY-UPDATE-300",
                          "level": "Error",
                          "name": "Can\'t load the ontology",
                          "description": "The ontology can\'t be loaded by the endpoint"
                        },
                        "_301": {
                          "id": "WS-CRUD-CREATE-301",
                          "level": "Warning",
                          "name": "Can\'t parse RDF document",
                          "description": "Can\'t parse the specified RDF document"
                        }                        
                      }';


  /*!   @brief Constructor
       @details   Initialize the Ontology Update
          
      @param[in] $ontology URI of the ontology where to delete something
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($ontologyUri, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->registered_ip = $registered_ip;
    $this->requester_ip = $requester_ip;
    
    $this->ontologyUri = $ontologyUri;
    
    if($this->registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
    }

    if(strtolower(substr($this->registered_ip, 0, 4)) == "self")
    {
      $pos = strpos($this->registered_ip, "::");

      if($pos !== FALSE)
      {
        $account = substr($this->registered_ip, $pos + 2, strlen($this->registered_ip) - ($pos + 2));

        $this->registered_ip = $requester_ip . "::" . $account;
      }
      else
      {
        $this->registered_ip = $requester_ip;
      }
    }

    $this->uri = $this->wsf_base_url . "/wsf/ws/ontology/update/";
    $this->title = "Ontology Update Web Service";
    $this->crud_usage = new CrudUsage(TRUE, FALSE, TRUE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/ontology/update/";

    $this->dtdURL = "auth/OntologyUpdate.dtd";

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct()
  {
    parent::__destruct();

    if(isset($this->db))
    {
      @$this->db->close();
    }
  }

  /*!   @brief Validate a query to this web service
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    $ws_av = new AuthValidator($this->requester_ip, $this->wsf_graph . "ontologies/", $this->uri);

    $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
      $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

    $ws_av->process();

    if($ws_av->pipeline_getResponseHeaderStatus() != 200)
    {
      // If he doesn't, then check if he has access to the dataset itself
      $ws_av2 = new AuthValidator($this->requester_ip, $this->ontologyUri, $this->uri);

      $ws_av2->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
      
      $ws_av2->process();

      if($ws_av2->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($ws_av2->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($ws_av2->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($ws_av2->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($ws_av2->pipeline_getError()->id, $ws_av2->pipeline_getError()->webservice,
          $ws_av2->pipeline_getError()->name, $ws_av2->pipeline_getError()->description,
          $ws_av2->pipeline_getError()->debugInfo, $ws_av2->pipeline_getError()->level);

        return;
      }      
    }

    // If the system send a query on the behalf of another user, we validate that other user as well
    if($this->registered_ip != $this->requester_ip)
    {
      // Validation of the "registered_ip" to make sure the user of this system has the rights
      $ws_av = new AuthValidator($this->registered_ip, $this->wsf_graph . "ontologies/", $this->uri);

      $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_av->process();

      if($ws_av->pipeline_getResponseHeaderStatus() != 200)
      {
        // If he doesn't, then check if he has access to the dataset itself
        $ws_av2 = new AuthValidator($this->registered_ip, $this->ontologyUri, $this->uri);

        $ws_av2->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
          $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
        
        $ws_av2->process();

        if($ws_av2->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->conneg->setStatus($ws_av2->pipeline_getResponseHeaderStatus());
          $this->conneg->setStatusMsg($ws_av2->pipeline_getResponseHeaderStatusMsg());
          $this->conneg->setStatusMsgExt($ws_av2->pipeline_getResponseHeaderStatusMsgExt());
          $this->conneg->setError($ws_av2->pipeline_getError()->id, $ws_av2->pipeline_getError()->webservice,
            $ws_av2->pipeline_getError()->name, $ws_av2->pipeline_getError()->description,
            $ws_av2->pipeline_getError()->debugInfo, $ws_av2->pipeline_getError()->level);

          return;
        }      
      }
    }
  }

  /*!   @brief Returns the error structure
              
      \n
      
      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getError() { return ($this->conneg->error); }


  /*!  @brief Update a resultset in a pipelined mode based on the processed information by the Web service.
              
      \n
      
      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResultset() { return ""; }

  /*!   @brief Inject the DOCType in a XML document
              
      \n
      
      @param[in] $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function injectDoctype($xmlDoc)
  {
    $posHeader = strpos($xmlDoc, '"?>') + 3;
    $xmlDoc = substr($xmlDoc, 0, $posHeader)
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Ontology Update DTD 0.1//EN\" \""
      . $this->dtdBaseURL . $this->dtdURL . "\">" . substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);

    return ($xmlDoc);
  }

  /*!   @brief Do content negotiation as an external Web Service
              
      \n
      
      @param[in] $accept Accepted mime types (HTTP header)
      
      @param[in] $accept_charset Accepted charsets (HTTP header)
      
      @param[in] $accept_encoding Accepted encodings (HTTP header)
  
      @param[in] $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
  {
    $this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language,
      OntologyUpdate::$supportedSerializations);

    // Check for errors

    if($this->ontologyUri == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);

      return;
    }
  }

  /*!   @brief Do content negotiation as an internal, pipelined, Web Service that is part of a Compound Web Service
              
      \n
      
      @param[in] $accept Accepted mime types (HTTP header)
      
      @param[in] $accept_charset Accepted charsets (HTTP header)
      
      @param[in] $accept_encoding Accepted encodings (HTTP header)
  
      @param[in] $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
    { $this->ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language); }

  /*!   @brief Returns the response HTTP header status
              
      \n
      
      @return returns the response HTTP header status
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatus() { return $this->conneg->getStatus(); }

  /*!   @brief Returns the response HTTP header status message
              
      \n
      
      @return returns the response HTTP header status message
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatusMsg() { return $this->conneg->getStatusMsg(); }

  /*!   @brief Returns the response HTTP header status message extension
              
      \n
      
      @return returns the response HTTP header status message extension
    
      @note The extension of a HTTP status message is
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatusMsgExt() { return $this->conneg->getStatusMsgExt(); }

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize() { return ""; }

  /*!   @brief Non implemented method (only defined)
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize_reification() { return ""; }

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize() { return ""; }

  /*!   @brief Sends the HTTP response to the requester
              
      \n
      
      @param[in] $content The content (body) of the response.
      
      @return NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_respond($content)
  {
    // First send the header of the request
    $this->conneg->respond();

    // second, send the content of the request

    // Make sure there is no error.
    if($this->conneg->getStatus() == 200)
    {
      echo $content;
    }

    $this->__destruct();
  }
  
  private function returnError($statusCode, $statusMsg, $wsErrorCode, $debugInfo = "")
  {
    $this->conneg->setStatus($statusCode);
    $this->conneg->setStatusMsg($statusMsg);
    $this->conneg->setStatusMsgExt($this->errorMessenger->{$wsErrorCode}->name);
    $this->conneg->setError($this->errorMessenger->{$wsErrorCode}->id, $this->errorMessenger->ws,
      $this->errorMessenger->{$wsErrorCode}->name, $this->errorMessenger->{$wsErrorCode}->description, $debugInfo,
      $this->errorMessenger->{$wsErrorCode}->level);
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
        $this->returnError(400, "Bad Request", "_202");
        return;              
      }          
      if($newUri == "")
      {
        $this->returnError(400, "Bad Request", "_203");
        return;              
      }      
      
      $this->ontology->updateEntityUri($oldUri, $newUri);
      
      if($advancedIndexation === TRUE)
      {   
        // Find the type of entity manipulated here
        $entity = $this->ontology->_getEntity($newUri);
        
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
        include_once($this->wsf_base_path."ontology/read/OntologyRead.php");

        $ontologyRead = new OntologyRead($this->ontologyUri, $function, $params,
                                         $this->registered_ip, $this->requester_ip);

        // Since we are in pipeline mode, we have to set the owlapisession using the current one.
        // otherwise the java bridge will return an error
        $ontologyRead->setOwlApiSession($this->OwlApiSession);                                                    
                          
        $ontologyRead->ws_conneg("application/rdf+xml", $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
                               $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        if($this->reasoner)
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
          $this->conneg->setStatus($ontologyRead->pipeline_getResponseHeaderStatus());
          $this->conneg->setStatusMsg($ontologyRead->pipeline_getResponseHeaderStatusMsg());
          $this->conneg->setStatusMsgExt($ontologyRead->pipeline_getResponseHeaderStatusMsgExt());
          $this->conneg->setError($ontologyRead->pipeline_getError()->id, $ontologyRead->pipeline_getError()->webservice,
            $ontologyRead->pipeline_getError()->name, $ontologyRead->pipeline_getError()->description,
            $ontologyRead->pipeline_getError()->debugInfo, $ontologyRead->pipeline_getError()->level);

          return;
        } 
        
        $entitySerialized = $ontologyRead->pipeline_serialize();
        
        unset($ontologyRead);  

        // Delete the old entity in Solr
        include_once($this->wsf_base_path."crud/delete/CrudDelete.php");
        include_once($this->wsf_base_path."framework/Solr.php");
        include_once($this->wsf_base_path."framework/ClassHierarchy.php");
        
        // Update the classes and properties into the Solr index
        $crudDelete = new CrudDelete($oldUri, $this->ontologyUri, 
                                     $this->registered_ip, $this->requester_ip);

        $crudDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
          $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $crudDelete->process();
        
        if($crudDelete->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->conneg->setStatus($crudDelete->pipeline_getResponseHeaderStatus());
          $this->conneg->setStatusMsg($crudDelete->pipeline_getResponseHeaderStatusMsg());
          $this->conneg->setStatusMsgExt($crudDelete->pipeline_getResponseHeaderStatusMsgExt());
          $this->conneg->setError($crudDelete->pipeline_getError()->id, $crudDelete->pipeline_getError()->webservice,
            $crudDelete->pipeline_getError()->name, $crudDelete->pipeline_getError()->description,
            $crudDelete->pipeline_getError()->debugInfo, $crudDelete->pipeline_getError()->level);

          return;
        } 
        
        unset($crudDelete);                
        
        // Add the new entity in Solr
        include_once($this->wsf_base_path."crud/create/CrudCreate.php");
        include_once($this->wsf_base_path."framework/arc2/ARC2.php");    
        include_once($this->wsf_base_path."framework/Namespaces.php");
        include_once($this->wsf_base_path."framework/Solr.php");

        // Update the classes and properties into the Solr index
        $crudCreate = new CrudCreate($entitySerialized, "application/rdf+xml", "full", $this->ontologyUri, 
                                     $this->registered_ip, $this->requester_ip);

        $crudCreate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
          $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $crudCreate->process();
        
        if($crudCreate->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->conneg->setStatus($crudCreate->pipeline_getResponseHeaderStatus());
          $this->conneg->setStatusMsg($crudCreate->pipeline_getResponseHeaderStatusMsg());
          $this->conneg->setStatusMsgExt($crudCreate->pipeline_getResponseHeaderStatusMsgExt());
          $this->conneg->setError($crudCreate->pipeline_getError()->id, $crudCreate->pipeline_getError()->webservice,
            $crudCreate->pipeline_getError()->name, $crudCreate->pipeline_getError()->description,
            $crudCreate->pipeline_getError()->debugInfo, $crudCreate->pipeline_getError()->level);

          return;
        } 
        
        unset($crudCreate);                   
      }
          
      // Update the name of the file of the ontology to mark it as "changed"
      $this->ontology->addOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");
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
      // and structWSF if the advancedIndexation is enabled.
      include_once($this->wsf_base_path."framework/arc2/ARC2.php");  
      include_once($this->wsf_base_path."framework/Namespaces.php");  
       
      $parser = ARC2::getRDFParser();
      $parser->parse($this->ontologyUri, $document);
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

        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
          $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, $errorsOutput,
          $this->errorMessenger->_301->level);

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
                  if(!is_array($literalValues[$predicate]))
                  {
                    $literalValues[$predicate] = array();
                  }
                  
                  array_push($literalValues[$predicate], $value["value"]);  
                }
                else
                {
                  if(!is_array($objectValues[$predicate]))
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
        if($this->in_array_r(Namespaces::$owl."Class", $description[Namespaces::$rdf."type"]))
        {
          $this->ontology->updateClass($uri, $literalValues, $objectValues); 
        }
        elseif($this->in_array_r(Namespaces::$owl."DatatypeProperty", $description[Namespaces::$rdf."type"]) ||
               $this->in_array_r(Namespaces::$owl."ObjectProperty", $description[Namespaces::$rdf."type"]) ||
               $this->in_array_r(Namespaces::$owl."AnnotationProperty", $description[Namespaces::$rdf."type"]))
        {
          foreach($types as $type)
          {
            if(!is_array($objectValues[Namespaces::$rdf."type"]))
            {
              $objectValues[Namespaces::$rdf."type"] = array();
            }
            
            array_push($objectValues[Namespaces::$rdf."type"], $type);      
          }
        
          $this->ontology->updateProperty($uri, $literalValues, $objectValues);   
        }
        else
        {
          $this->ontology->updateNamedIndividual($uri, $types, $literalValues, $objectValues);   
        }
        
        // Call different API calls depending what we are manipulating
        if($advancedIndexation == TRUE)
        {          
          $rdfxmlParser = ARC2::getRDFParser();
          $rdfxmlSerializer = ARC2::getRDFXMLSerializer();
          
          $resourcesIndex = $rdfxmlParser->getSimpleIndex(0);
          
          // Index the entity to update
          $rdfxmlParser->parse($uri, $rdfxmlSerializer->getSerializedIndex(array($uri => $resourceIndex[$uri])));
          $rIndex = $rdfxmlParser->getSimpleIndex(0);
          $resourcesIndex = ARC2::getMergedIndex($resourcesIndex, $rIndex);                    
          
          // Check if the entity got punned
          $entities = $this->ontology->_getEntities($uri);
          
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
              include_once($this->wsf_base_path."ontology/read/OntologyRead.php");

              $ontologyRead = new OntologyRead($this->ontologyUri, $query["function"], $query["params"],
                                               $this->registered_ip, $this->requester_ip);

              // Since we are in pipeline mode, we have to set the owlapisession using the current one.
              // otherwise the java bridge will return an error
              $ontologyRead->setOwlApiSession($this->OwlApiSession);                                                    
                                
              $ontologyRead->ws_conneg("application/rdf+xml", $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
                                     $_SERVER['HTTP_ACCEPT_LANGUAGE']);

              if($this->reasoner)
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
                $this->conneg->setStatus($ontologyRead->pipeline_getResponseHeaderStatus());
                $this->conneg->setStatusMsg($ontologyRead->pipeline_getResponseHeaderStatusMsg());
                $this->conneg->setStatusMsgExt($ontologyRead->pipeline_getResponseHeaderStatusMsgExt());
                $this->conneg->setError($ontologyRead->pipeline_getError()->id, $ontologyRead->pipeline_getError()->webservice,
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
              // punned descriptison together before updating them in structWSF (Virtuoso and Solr).
              // otherwise we will loose information in these other systems.
              
              // Once we start the ontology creation process, we have to make sure that even if the server
              // loose the connection with the user the process will still finish.
              ignore_user_abort(true);

              // However, maybe there is an issue with the server handling that file tht lead to some kind of infinite or near
              // infinite loop; so we have to limit the execution time of this procedure to 45 mins.
              set_time_limit(2700);                
              
              include_once($this->wsf_base_path."crud/update/CrudUpdate.php");
              include_once($this->wsf_base_path."framework/Solr.php");
              include_once($this->wsf_base_path."framework/ClassHierarchy.php");
              
              $serializedResource = $rdfxmlSerializer->getSerializedIndex($resourcesIndex);
              
              // Update the classes and properties into the Solr index
              $crudUpdate = new CrudUpdate($serializedResource, "application/rdf+xml", $this->ontologyUri, 
                                           $this->registered_ip, $this->requester_ip);

              $crudUpdate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
                $_SERVER['HTTP_ACCEPT_LANGUAGE']);

              $crudUpdate->process();
              
              if($crudUpdate->pipeline_getResponseHeaderStatus() != 200)
              {
                $this->conneg->setStatus($crudUpdate->pipeline_getResponseHeaderStatus());
                $this->conneg->setStatusMsg($crudUpdate->pipeline_getResponseHeaderStatusMsg());
                $this->conneg->setStatusMsgExt($crudUpdate->pipeline_getResponseHeaderStatusMsgExt());
                $this->conneg->setError($crudUpdate->pipeline_getError()->id, $crudUpdate->pipeline_getError()->webservice,
                  $crudUpdate->pipeline_getError()->name, $crudUpdate->pipeline_getError()->description,
                  $crudUpdate->pipeline_getError()->debugInfo, $crudUpdate->pipeline_getError()->level);

                return;
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
              
              include_once($this->wsf_base_path."crud/update/CrudUpdate.php");
              include_once($this->wsf_base_path."framework/Solr.php");
              include_once($this->wsf_base_path."framework/ClassHierarchy.php");
              
              // Update the classes and properties into the Solr index
              $crudUpdate = new CrudUpdate($serializedResource, "application/rdf+n3", $this->ontologyUri, 
                                           $this->registered_ip, $this->requester_ip);

              $crudUpdate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
                $_SERVER['HTTP_ACCEPT_LANGUAGE']);

              $crudUpdate->process();
              
              if($crudUpdate->pipeline_getResponseHeaderStatus() != 200)
              {
                $this->conneg->setStatus($crudUpdate->pipeline_getResponseHeaderStatus());
                $this->conneg->setStatusMsg($crudUpdate->pipeline_getResponseHeaderStatusMsg());
                $this->conneg->setStatusMsgExt($crudUpdate->pipeline_getResponseHeaderStatusMsgExt());
                $this->conneg->setError($crudUpdate->pipeline_getError()->id, $crudUpdate->pipeline_getError()->webservice,
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
      $this->ontology->addOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");    
    }
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
      $this->ontology->removeOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");
    }
  }

  /**
  * 
  *   
  * @param mixed $uri
  * @param mixed $description
  * @return string
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getLabel($uri, $description)  
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

    return $resource;
  }  
  
  /**
  * 
  * 
  * @param mixed $description
  * @return mixed
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getDescription($description)
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
  
  
  /**
  * 
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function initiateOwlBridgeSession()
  {
    // Starts the OWLAPI process/bridge
    require_once($this->owlapiBridgeURI);

    // Create the OWLAPI session object that could have been persisted on the OWLAPI instance.
    // Second param "false" => we re-use the pre-created session without destroying the previous one
    // third param "0" => it nevers timeout.
    if($this->OwlApiSession == null)
    {
      $this->OwlApiSession = java_session("OWLAPI", false, 0);
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
      $this->ontology = new OWLOntology($this->ontologyUri, $this->OwlApiSession, TRUE);
    }
    catch(Exception $e)
    {
      $this->returnError(400, "Bad Request", "_300");
    }    
  }
  
  /**
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function isValid()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $this->validateQuery();

      // If the query is still valid
      if($this->conneg->getStatus() == 200)
      {
        return(TRUE);
      }
    }
    
    return(FALSE);    
  }  
  
  /*!
  * Enable the reasoner for advanced indexation 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function useReasonerForAdvancedIndexation()
  {
    $this->reasoner = TRUE;
  }
  
  /*!
  * Disable the reasoner for advanced indexation 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function stopUsingReasonerForAdvancedIndexation()
  {
    $this->reasoner = FALSE;
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
}

//@}

?>
