<?php

/*! @defgroup WsOntologyRead Ontology Read Web Service */
//@{

/*! @file \ws\ontology\read\OntologyRead.php
   @brief Define the Ontology Read web service
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Ontology Read web service. It reads different kind of information from the ontological structure
             of the system.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class OntologyRead extends WebService
{
  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief URI of the ontology to query */
  private $ontologyUri = "";

  /*! @brief The function to use for this query. Different API function calls are embeded in this web service endpoint */
  private $function = "";
  
  /*! @brief The parameters of the function to call. These parameters changes depending on the function. All
             parameters are split with a ";" and are encoded */
  private $parameters = array();

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";
  
  private $OwlApiSession = null;

  /**
  * Specify if we want to use the reasonner in this Ontology object.
  * 
  * @var boolean
  */
  private $useReasoner = TRUE;  

  /*! @brief Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr");

  private $getSerialized = "";

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/iron+csv", "application/iron+json", "application/json", "application/rdf+xml",
      "application/rdf+n3", "application/*", "text/csv", "text/xml", "text/*", "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/ontology/read/",
                        "_200": {
                          "id": "WS-ONTOLOGY-READ-200",
                          "level": "Warning",
                          "name": "Unknown function call",
                          "description": "The function call being requested is unknown or unsupported by this Ontology Read web service endpoint"
                        },
                        "_201": {
                          "id": "WS-ONTOLOGY-READ-201",
                          "level": "Warning",
                          "name": "Unsupported mode",
                          "description": "Unsupported mode used for this query"
                        },
                        "_202": {
                          "id": "WS-ONTOLOGY-READ-202",
                          "level": "Warning",
                          "name": "URI parameter not provided",
                          "description": "You omited to provide the URI parameter for this query."
                        },
                        "_203": {
                          "id": "WS-ONTOLOGY-READ-203",
                          "level": "Warning",
                          "name": "Unsupported type",
                          "description": "Unsupported type used for this query"
                        },                        
                        "_204": {
                          "id": "WS-ONTOLOGY-READ-204",
                          "level": "Warning",
                          "name": "Property not existing",
                          "description": "The target property URI is not existing in the target ontology."
                        },                        
                        "_205": {
                          "id": "WS-ONTOLOGY-READ-205",
                          "level": "Warning",
                          "name": "Class not existing",
                          "description": "The target class URI is not existing in the target ontology."
                        },                        
                        "_206": {
                          "id": "WS-ONTOLOGY-READ-206",
                          "level": "Warning",
                          "name": "Named individual not existing",
                          "description": "The target named individual URI is not existing in the target ontology."
                        },                        
                        "_300": {
                          "id": "WS-ONTOLOGY-READ-300",
                          "level": "Warning",
                          "name": "Target ontology not loaded",
                          "description": "The target ontology is not loaded into the ontological structure of this instance."
                        }
                      }';


  /*! @brief Constructor
      @details   Initialize the Auth Web Service
              
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($ontologyUri, $function, $parameters, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->ontologyUri = $ontologyUri;
    $this->function = $function;

    $params = explode(";", $parameters);
    
    foreach($params as $param)
    {
      $p = explode("=", $param);
      
      $this->parameters[$p[0]] = urldecode($p[1]);
    }

    $this->requester_ip = $requester_ip;

    if($registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
    }
    else
    {
      $this->registered_ip = $registered_ip;
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/ontology/read/";
    $this->title = "Ontology Read Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/ontology/read/";

    $this->dtdURL = "ontology/read/ontologyRead.dtd";

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
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    // @TODO Validate the OntologyRead queries such that: (1) if the user is requesting something related to a 
    //       specific ontology, we check if it has the rights. If it is requesting a list of available ontologies
    //       we list the ones he has access to. That second validation has to happen in these special functions.
    
    if($this->ontologyUri != "")
    {
      $ws_av = new AuthValidator($this->requester_ip, $this->ontologyUri, $this->uri);

      $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_av->process();

      if($ws_av->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($ws_av->pipeline_getError()->id, $ws_av->pipeline_getError()->webservice,
          $ws_av->pipeline_getError()->name, $ws_av->pipeline_getError()->description,
          $ws_av->pipeline_getError()->debugInfo, $ws_av->pipeline_getError()->level);

        return;
      }

      // If the system send a query on the behalf of another user, we validate that other user as well
      if($this->registered_ip != $this->requester_ip)
      {
        // Validation of the "registered_ip" to make sure the user of this system has the rights
        $ws_av = new AuthValidator($this->registered_ip, $this->ontologyUri, $this->uri);

        $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
          $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

        $ws_av->process();

        if($ws_av->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
          $this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
          $this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
          $this->conneg->setError($ws_av->pipeline_getError()->id, $ws_av->pipeline_getError()->webservice,
            $ws_av->pipeline_getError()->name, $ws_av->pipeline_getError()->description,
            $ws_av->pipeline_getError()->debugInfo, $ws_av->pipeline_getError()->level);
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


  /*!  @brief Create a resultset in a pipelined mode based on the processed information by the Web service.
              
      \n
      
      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResultset()
  {    
    return($this->injectDoctype($this->rset->getResultsetXML()));
  }

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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Ontology Read DTD 0.1//EN\" \"" . $this->dtdBaseURL
        . $this->dtdURL . "\">" . substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);

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
    $this->conneg =
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, OntologyRead::$supportedSerializations);

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      // Check for errors

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
  public function pipeline_serialize() 
  { 
    if($this->getSerialized != "")
    {
      return($this->getSerialized);
    }     
    
    return($this->serializations());    
  }

  /*!   @brief Get the namespace of a URI
              
      @param[in] $uri Uri of the resource from which we want the namespace
              
      \n
      
      @return returns the extracted namespace      
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function getNamespace($uri)
  {
    $pos = strrpos($uri, "#");

    if($pos !== FALSE)
    {
      return array (substr($uri, 0, $pos) . "#", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
    }
    else
    {
      $pos = strrpos($uri, "/");

      if($pos !== FALSE)
      {
        return array (substr($uri, 0, $pos) . "/", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
      }
      else
      {
        $pos = strpos($uri, ":");

        if($pos !== FALSE)
        {
          $nsUri = explode(":", $uri, 2);

          foreach($this->namespaces as $uri2 => $prefix2)
          {
            $uri2 = urldecode($uri2);

            if($prefix2 == $nsUri[0])
            {
              return (array ($uri2, $nsUri[1]));
            }
          }

          return explode(":", $uri, 2);
        }
      }
    }

    return (FALSE);
  }

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    if($this->getSerialized != "")
    {
      return($this->getSerialized);
    }     
    
    return($this->serializations());
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

  /*!   @brief Perform the requested action and return the results.
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
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

      $ontology;
      
      try
      {
        $ontology = new OWLOntology($this->ontologyUri, $this->OwlApiSession, TRUE);   
      }
      catch(Exception $e)
      {
        if(strtolower($this->function) != "getserializedclasshierarchy" &&
           strtolower($this->function) != "getserializedpropertyhierarchy" &&
           strtolower($this->function) != "getironxmlschema" &&
           strtolower($this->function) != "getironjsonschema" &&
           strtolower($this->function) != "getloadedontologies")
        {        
          $this->returnError(400, "Bad Request", "_300");
          return;
        }
      }
       
      if(isset($ontology))
      {                  
        if($this->useReasoner)
        {   
          $ontology->useReasoner();
        }
        else
        {
          $ontology->stopUsingReasoner();
        }
      }
      
      if(isset($this->parameters["direct"]) && $this->parameters["direct"] != "")
      {
        $this->parameters["direct"] = strtolower($this->parameters["direct"]);
        
        if($this->parameters["direct"] == "false" ||
           $this->parameters["direct"] == "0" ||
           $this->parameters["direct"] == "off")
         {
           $this->parameters["direct"] = false;
         }
         else
         {
           $this->parameters["direct"] = true;
         }
      }

      switch(strtolower($this->function))
      {
        case "getserialized":
          $this->conneg->setStatus(200);
          $this->conneg->setStatusMsg("OK");        
          $this->getSerialized = $ontology->getSerialization();        
          return;
        break;
        
        case "getclass":
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }

          $class = $ontology->_getClass($this->parameters["uri"]);
          
          if($class == null)
          {
            $this->returnError(400, "Bad Request", "_205"); 
          }
          else
          {
            $subject = new Subject($this->parameters["uri"]);
            $subject->setSubject($ontology->_getClassDescription($class));
            $this->rset->addSubject($subject);
          }          
        break;
        
        case "getclasses":

          $limit = -1;
          $offset = 0;
          
          if(isset($this->parameters["limit"]))
          {
            $limit = $this->parameters["limit"];
          }
          
          if(isset($this->parameters["offset"]))
          {
            $offset = $this->parameters["offset"];
          }          
          
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
            
              $classes = $ontology->getClassesUri($limit, $offset);
             
              foreach($classes as $class)
              {
                $subject = new Subject($class);
                $subject->setType("owl:Class");
                $this->rset->addSubject($subject);                  
              }
            break;
            
            case "descriptions":
              $this->rset->setResultset(Array($this->ontologyUri => $ontology->getClassesDescription($limit, $offset)));
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;            
          }
          
        break;
        
        case "getnamedindividual":
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
          
          $namedIndividual = $ontology->_getNamedIndividual($this->parameters["uri"]);
          
          if($namedIndividual == null)
          {
            $this->returnError(400, "Bad Request", "_206"); 
          }
          else
          {          
            $subject = new Subject($this->parameters["uri"]);
            $subject->setSubject($ontology->_getNamedIndividualDescription($namedIndividual));
            $this->rset->addSubject($subject);
          }
        break;        
        
        case "getnamedindividuals":

          $limit = -1;
          $offset = 0;
          
          $direct = true;
          
          if(isset($this->parameters["limit"]))
          {
            $limit = $this->parameters["limit"];
          }
          
          if(isset($this->parameters["offset"]))
          {
            $offset = $this->parameters["offset"];
          }

          if(isset($this->parameters["direct"]))
          {
            switch($this->parameters["direct"])
            {
              case "0":
                $direct = false;
              break;
              case "1":
                $direct = true;
              break;
            }
          }
          
          $classUri = "all";
          
          if(!isset($this->parameters["classuri"]))
          {
            $classUri = $this->parameters["classuri"];
          }          
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $namedindividuals = $ontology->getNamedIndividualsUri($classUri, $direct, $limit, $offset);
             
              foreach($namedindividuals as $ni)
              {
                $subject = new Subject($ni);
                $subject->setType("owl:NamedIndividual");
                $this->rset->addSubject($subject);                  
              }
            break;
            
            case "descriptions":
              $this->rset->setResultset(Array($this->ontologyUri => $ontology->getNamedIndividualsDescription($classUri, $direct, $limit, $offset)));            
            break;
            
            case "list":
              $this->rset->setResultset(Array($this->ontologyUri => $ontology->getNamedIndividualsDescription($classUri, $direct, $limit, $offset, TRUE)));            
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201", "Mode provided: ".$this->parameters["mode"]);
              return;
            break;            
          }
          
        break;
        
        case "getsubclasses":

          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }

          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $classes = $ontology->getSubClassesUri($this->parameters["uri"], $this->parameters["direct"]);
              
              foreach($classes as $class)
              {
                $subject = new Subject($class);
                $subject->setType("owl:Class");
                $this->rset->addSubject($subject);                  
              }
            break;
            
            case "descriptions":
              $this->rset->setResultset(Array($this->ontologyUri => $ontology->getSubClassesDescription($this->parameters["uri"], $this->parameters["direct"])));            
            break;

            case "hierarchy":
              $this->rset->setResultset(Array($this->ontologyUri => $ontology->getSubClassesDescription($this->parameters["uri"], TRUE, TRUE)));
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;
        
        case "getsuperclasses":

          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $classes = $ontology->getSuperClassesUri($this->parameters["uri"], $this->parameters["direct"]);
              
              foreach($classes as $class)
              {
                $subject = new Subject($class);
                $subject->setType("owl:Class");
                $this->rset->addSubject($subject);                  
              }
            break;
            
            case "descriptions":
              $this->rset->setResultset(Array($this->ontologyUri => $ontology->getSuperClassesDescription($this->parameters["uri"], $this->parameters["direct"])));
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;        
        
        case "getequivalentclasses":

          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $classes = $ontology->getEquivalentClassesUri($this->parameters["uri"]);
              
              foreach($classes as $class)
              {
                $subject = new Subject($class);
                $subject->setType("owl:Class");
                $this->rset->addSubject($subject);                  
              }
            break;
            
            case "descriptions":
              $this->rset->setResultset(Array($this->ontologyUri => $ontology->getEquivalentClassesDescription($this->parameters["uri"])));
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;                
                
        case "getdisjointclasses":

          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $classes = $ontology->getDisjointClassesUri($this->parameters["uri"]);
              
              foreach($classes as $class)
              {
                $subject = new Subject($class);
                $subject->setType("owl:Class");
                $this->rset->addSubject($subject);                  
              }
            break;
            
            case "descriptions":
              $this->rset->setResultset(Array($this->ontologyUri => $ontology->getDisjointClassesDescription($this->parameters["uri"])));
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;  
        
        case "getontologies":
       
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $ontologies = $ontology->getOntologiesUri();
              
              foreach($ontologies as $ontology)
              {
                $subject = new Subject($ontology);
                $subject->setType("owl:Ontology");
                $this->rset->addSubject($subject);                  
              }
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;        
        
        case "getloadedontologies":
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $ontologies = OWLOntology::getLoadedOntologiesUri($this->OwlApiSession);
              
              foreach($ontologies as $ontology)
              {
                $subject = new Subject($ontology);
                $subject->setType("owl:Ontology");
                $this->rset->addSubject($subject);                  
              }
            break;
            
            case "descriptions":
              $this->rset->setResultset(Array($this->ontologyUri => OWLOntology::getLoadedOntologiesDescription($this->OwlApiSession)));            
            break;
            
            default:
              $this->conneg->setStatus(400);
              $this->conneg->setStatusMsg("Bad Request");
              $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
              $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
                $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
                $this->errorMessenger->_201->level);
              return;
            break;
          }
          
        break;
        
        case "getserializedclasshierarchy":
          $sch = $this->generationSerializedClassHierarchy($this->OwlApiSession);

          $subject = new Subject($this->ontologyUri);
          $subject->setType("owl:Ontology");
          $subject->setDataAttribute(Namespaces::$wsf."serializedClassHierarchy", $sch);
          $this->rset->addSubject($subject);
          
        break;
        
        case "getserializedpropertyhierarchy":
          $sch = $this->generationSerializedPropertyHierarchy($this->OwlApiSession);

          $subject = new Subject($this->ontologyUri);
          $subject->setType("owl:Ontology");
          $subject->setDataAttribute(Namespaces::$wsf."serializedPropertyHierarchy", $sch);
          $this->rset->addSubject($subject);          
                      
        break;
        
        case "getironxmlschema":
          $subjectTriples = $ontology->getClassesDescription($limit, $offset);
          
          $schema = '<schema><version>0.1</version><typeList>';
          
          $prefixes = array();
          
          foreach($subjectTriples as $uri => $subject)
          {
            $this->manageIronPrefixes($uri, $prefixes);
            
            $schema .= "<".$this->ironPrefixize($uri, $prefixes).">";

            $schema .= "<description>".$this->xmlEncode($this->getDescription($subject))."</description>";
            $schema .= "<prefLabel>".$this->xmlEncode($this->getLabel($uri, $subject))."</prefLabel>";
            
            foreach($subject as $predicate => $values)
            {
              foreach($values as $value)
              {
                switch($predicate)
                {
                  case Namespaces::$rdfs."subClassOf":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<subTypeOf>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</subTypeOf>";
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    
                    if(isset($value["uri"]))
                    {
                      $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $displayControl = $value["value"];
                    }
                    
                    $schema .= "<displayControl>".$this->xmlEncode($displayControl)."</displayControl>";
                  break;

                  
                  case Namespaces::$sco."ignoredBy":
                    
                    if(isset($value["uri"]))
                    {
                      $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $ignoredBy = $value["value"];
                    }
                    
                    $schema .= "<ignoredBy>".$this->xmlEncode($ignoredBy)."</ignoredBy>";
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= "<shortLabel>".$this->xmlEncode($value["value"])."</shortLabel>";
                  break;
                  
                  case Namespaces::$sco."mapMarkerImageUrl":
                    $schema .= "<mapMarkerImageUrl>".$this->xmlEncode($value["value"])."</mapMarkerImageUrl>";
                  break;
                  
                  case Namespaces::$sco."relationBrowserNodeType":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<relationBrowserNodeType>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</relationBrowserNodeType>";
                  break;
                }              
              }
            }
            
            $schema .= "</".$this->ironPrefixize($uri, $prefixes).">";            
          }
          
          $schema .= "</typeList>";
          $schema .= "<attributeList>";

          $subjectTriples = $ontology->getPropertiesDescription(TRUE);

          foreach($subjectTriples as $uri => $subject)
          {
            $this->manageIronPrefixes($uri, $prefixes);
            
            $schema .= "<".$this->ironPrefixize($uri, $prefixes).">";

            $schema .= "<description>".$this->xmlEncode($this->getDescription($subject))."</description>";
            $schema .= "<prefLabel>".$this->xmlEncode($this->getLabel($uri, $subject))."</prefLabel>";
            
            foreach($subject as $predicate => $values)
            {
              foreach($values as $value)
              {
                switch($predicate)
                {
                  case Namespaces::$rdfs."domain":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<allowedType>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</allowedType>";
                  break;
                  
                  case Namespaces::$sco."displayControl":
                  
                    if(isset($value["uri"]))
                    {
                      $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $displayControl = $value["value"];
                    }
                    
                    $schema .= "<displayControl>".$this->xmlEncode($displayControl)."</displayControl>";
                  break;

                  case Namespaces::$sco."ignoredBy":
                  
                    if(isset($value["uri"]))
                    {
                      $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $ignoredBy = $value["value"];
                    }
                    
                    $schema .= "<ignoredBy>".$this->xmlEncode($ignoredBy)."</ignoredBy>";
                  break;

                  case Namespaces::$sco."comparableWith":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<comparableWith>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</comparableWith>";
                  break;

                  case Namespaces::$sco."unitType":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<unitType>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</unitType>";
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= "<shortLabel>".$this->xmlEncode($value["value"])."</shortLabel>";
                  break;
                  
                  case Namespaces::$sco."orderingValue":
                    $schema .= "<orderingValue>".$this->xmlEncode($value["value"])."</orderingValue>";
                  break;  
                  
                  case Namespaces::$rdfs."subPropertyOf":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<subPropertyOf>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</subPropertyOf>";
                  break;
                  
                  case Namespaces::$iron."allowedValue":
                    $schema .= "<allowedValue><primitive>".$this->xmlEncode($value["value"])."</primitive></allowedValue>";
                  break;                  
                }
              }
            }
            
            $schema .= "</".$this->ironPrefixize($uri, $prefixes).">";
          }
          
          $subjectTriples = $ontology->getPropertiesDescription(FALSE, TRUE);

          foreach($subjectTriples as $uri => $subject)
          {
            $this->manageIronPrefixes($uri, $prefixes);
            
            $schema .= "<".$this->ironPrefixize($uri, $prefixes).">";

            $schema .= "<description>".$this->xmlEncode($this->getDescription($subject))."</description>";
            $schema .= "<prefLabel>".$this->xmlEncode($this->getLabel($uri, $subject))."</prefLabel>";
            
            foreach($subject as $predicate => $values)
            {
              foreach($values as $value)
              {
                switch($predicate)
                {
                  case Namespaces::$rdfs."domain":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<allowedType>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</allowedType>";
                  break;
                  
                  case Namespaces::$rdfs."range":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<allowedValue><type>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</type></allowedValue>";
                  break;
                  
                  case Namespaces::$sco."displayControl":
                  
                    if(isset($value["uri"]))
                    {
                      $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $displayControl = $value["value"];
                    }
                    
                    $schema .= "<displayControl>".$this->xmlEncode($displayControl)."</displayControl>";
                  break;
                  
                  case Namespaces::$sco."ignoredBy":
                  
                    if(isset($value["uri"]))
                    {
                      $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $ignoredBy = $value["value"];
                    }
                    
                    $schema .= "<ignoredBy>".$this->xmlEncode($ignoredBy)."</ignoredBy>";
                  break;

                  case Namespaces::$sco."comparableWith":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<comparableWith>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</comparableWith>";
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= "<shortLabel>".$this->xmlEncode($value["value"])."</shortLabel>";
                  break;
                  
                  case Namespaces::$sco."unitType":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= "<unitType>".$this->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</unitType>";
                  break;                  
                  
                  case Namespaces::$sco."orderingValue":
                    $schema .= "<orderingValue>".$this->xmlEncode($value["value"])."</orderingValue>";
                  break;          
                  
                  case Namespaces::$iron."allowedValue":
                    $schema .= "<allowedValue><primitive>".$this->xmlEncode($value["value"])."</primitive></allowedValue>";
                  break;    
                }
              }
            }
            
            $schema .= "</".$this->ironPrefixize($uri, $prefixes).">";            
          }
          
          $schema .= "</attributeList>";
          $schema .= "<prefixList>";                    

          foreach($prefixes as $prefix => $ns)
          {
            $schema .= "    <$prefix>$ns</$prefix>";
          }
          
          $schema .= "</prefixList>";          
          $schema .= "</schema>";    
          
          $subjectTriples = "";
          
          $subject = new Subject($this->ontologyUri);
          $subject->setType("owl:Ontology");
          $subject->setDataAttribute(Namespaces::$wsf."serializedIronXMLSchema", str_replace(array ("\\", "&", "<", ">"), array ("%5C", "&amp;", "&lt;", "&gt;"), $schema));
          $this->rset->addSubject($subject);          
          
/*
          <schema>
            <version>0.1</version>
            <prefLabel>PEG schema</prefLabel>
            <prefixList>
              <sco>http://purl.org/ontology/sco#</sco>
            </prefixList>
            <typeList>
              <peg_Neighborhood>
                <subTypeOf>pegf_Organization</subTypeOf>
                <description>Neighborhood community organization</description>
                <prefLabel>neighborhood</prefLabel>
                <displayControl>sRelationBrowser</displayControl>
              </peg_Neighborhood>
            </typeList>
            <attributeList>
              <peg_neighborhoodNumber>
                <prefLabel>neighborhood number</prefLabel>
                <description>Neighborhood identification number</description>
                <allowedType>Neighborhood</allowedType>
                <allowedType>City</allowedType>
                <allowedType>Province</allowedType>
                <allowedType>Country</allowedType>
                <allowedValue>
                  <primitive>String</primitive>
                </allowedValue>
                <maxValues>1</maxValues>
              </peg_neighborhoodNumber>
            </attributeList>
          </schema>
*/          

        break;
        
        
        case "getironjsonschema":
          $subjectTriples = $ontology->getClassesDescription($limit, $offset);
          
          $schema = '{ "schema": { "version": "0.1", "typeList": {';
          
          $prefixes = array();
          
          foreach($subjectTriples as $uri => $subject)
          {
            $this->manageIronPrefixes($uri, $prefixes);
            
            $schema .= '"'.$this->ironPrefixize($uri, $prefixes).'": {';

            $schema .= '"description": "'.parent::jsonEncode($this->getDescription($subject)).'",';
            $schema .= '"prefLabel": "'.parent::jsonEncode($this->getLabel($uri, $subject)).'",';
            
            foreach($subject as $predicate => $values)
            {  
              switch($predicate)
              {
                case Namespaces::$rdfs."subClassOf":
                  $schema .= '"subTypeOf": [';
                break;
                
                case Namespaces::$sco."displayControl":
                  $schema .= '"displayControl": [';
                break;
                
                case Namespaces::$sco."ignoredBy":
                  $schema .= '"ignoredBy": [';
                break;
                
                case Namespaces::$sco."shortLabel":
                  $schema .= '"shortLabel": [';
                break;
                
                case Namespaces::$sco."mapMarkerImageUrl":
                  $schema .= '"mapMarkerImageUrl": [';
                break;
                
                case Namespaces::$sco."relationBrowserNodeType":
                  $schema .= '"relationBrowserNodeType": [';
                break;
              }              
                            
              foreach($values as $value)
              {
                switch($predicate)
                {
                  case Namespaces::$rdfs."subClassOf":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    
                    if(isset($value["uri"]))
                    {
                      $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $displayControl = $value["value"];
                    }
                                       
                    $schema .= '"'.parent::jsonEncode($displayControl).'",';
                  break;
                  
                  case Namespaces::$sco."ignoredBy":
                    
                    if(isset($value["uri"]))
                    {
                      $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $ignoredBy = $value["value"];
                    }
                                       
                    $schema .= '"'.parent::jsonEncode($ignoredBy).'",';
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= '"'.parent::jsonEncode($value["value"]).'",';
                  break;
                  
                  case Namespaces::$sco."mapMarkerImageUrl":
                    $schema .= '"'.parent::jsonEncode($value["value"]).'",';
                  break;
                  
                  case Namespaces::$sco."relationBrowserNodeType":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;
                }              
              }
              
              switch($predicate)
              {
                case Namespaces::$rdfs."subClassOf":
                case Namespaces::$sco."displayControl":
                case Namespaces::$sco."ignoredBy":
                case Namespaces::$sco."shortLabel":
                case Namespaces::$sco."mapMarkerImageUrl":
                case Namespaces::$sco."relationBrowserNodeType":
                  $schema = rtrim($schema, ",");     
                  $schema .= '],';
                break;
              }                
            }
            
            $schema = rtrim($schema, ",");
            
            $schema .= "},";            
          }
          
          $schema = rtrim($schema, ",");
          
          $schema .= "},";            
          
          $schema .= '"attributeList": {';

          $subjectTriples = $ontology->getPropertiesDescription(TRUE);

          foreach($subjectTriples as $uri => $subject)
          {
            $this->manageIronPrefixes($uri, $prefixes);
            
            $schema .= '"'.$this->ironPrefixize($uri, $prefixes).'": {';
            
            $schema .= '"description": "'.parent::jsonEncode($this->getDescription($subject)).'",';
            $schema .= '"prefLabel": "'.parent::jsonEncode($this->getLabel($uri, $subject)).'",';
            
            foreach($subject as $predicate => $values)
            {             
              switch($predicate)
              {
                case Namespaces::$iron."allowedValue":
                  $schema .= '"allowedValue": {"primitive": "'.parent::jsonEncode($value["value"]).'"},';
                break;                
                
                case Namespaces::$rdfs."subPropertyOf":
                  $schema .= '"subPropertyOf": [';
                break;                
                
                case Namespaces::$rdfs."domain":
                  $schema .= '"allowedType": [';
                break;
                
                case Namespaces::$sco."displayControl":
                  $schema .= '"displayControl": [';
                break;
                
                case Namespaces::$sco."ignoredBy":
                  $schema .= '"ignoredBy": [';
                break;

                case Namespaces::$sco."comparableWith":
                  $schema .= '"comparableWith": [';
                break;

                case Namespaces::$sco."unitType":
                  $schema .= '"unitType": [';
                break;
                
                case Namespaces::$sco."shortLabel":
                  $schema .= '"shortLabel": [';
                break;
                
                case Namespaces::$sco."orderingValue":
                  $schema .= '"orderingValue": [';
                break;  
                
                case Namespaces::$rdfs."subPropertyOf":
                  $schema .= '"subPropertyOf": [';
                break;
              }              
              
              foreach($values as $value)
              {
                switch($predicate)
                {
                  case Namespaces::$rdfs."domain":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    if(isset($value["uri"]))
                    {
                      $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $displayControl = $value["value"];
                    }
                    
                    $schema .= '"'.parent::jsonEncode($displayControl).'",';
                  break;
                  
                  case Namespaces::$sco."ignoredBy":
                    if(isset($value["uri"]))
                    {
                      $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $ignoredBy = $value["value"];
                    }
                    
                    $schema .= '"'.parent::jsonEncode($ignoredBy).'",';
                  break;

                  case Namespaces::$sco."comparableWith":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;

                  case Namespaces::$sco."unitType":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= '"'.parent::jsonEncode($value["value"]).'",';
                  break;
                  
                  case Namespaces::$sco."orderingValue":
                    $schema .= '"'.parent::jsonEncode($value["value"]).'",';
                  break;  
                  
                  case Namespaces::$rdfs."subPropertyOf":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;
                }
              }
              
              switch($predicate)
              {
                case Namespaces::$rdfs."domain":
                case Namespaces::$sco."displayControl":
                case Namespaces::$sco."ignoredBy":
                case Namespaces::$sco."comparableWith":
                case Namespaces::$sco."unitType":
                case Namespaces::$sco."shortLabel":
                case Namespaces::$sco."orderingValue":
                case Namespaces::$rdfs."subPropertyOf":
                  $schema = rtrim($schema, ",");
                  $schema .= '],';  
                break;
              }               
            }
            
            $schema = rtrim($schema, ",");
            
            $schema .= "},";                 
          }
          
          $subjectTriples = $ontology->getPropertiesDescription(FALSE, TRUE);

          foreach($subjectTriples as $uri => $subject)
          {
            $this->manageIronPrefixes($uri, $prefixes);
            
            $schema .= '"'.$this->ironPrefixize($uri, $prefixes).'": {';
            
            $schema .= '"description": "'.parent::jsonEncode($this->getDescription($subject)).'",';
            $schema .= '"prefLabel": "'.parent::jsonEncode($this->getLabel($uri, $subject)).'",';
            
            foreach($subject as $predicate => $values)
            {
              switch($predicate)
              {
                case Namespaces::$rdfs."domain":
                  $schema .= '"allowedType": [';
                break;
                
                case Namespaces::$rdfs."range":
                  $schema .= '"allowedValue": [';
                break;
                
                case Namespaces::$sco."displayControl":
                  $schema .= '"displayControl": [';
                break;
                
                case Namespaces::$sco."ignoredBy":
                  $schema .= '"ignoredBy": [';
                break;

                case Namespaces::$sco."comparableWith":
                  $schema .= '"comparableWith": [';
                break;

                case Namespaces::$sco."unitType":
                  $schema .= '"unitType": [';
                break;
                
                case Namespaces::$sco."shortLabel":
                  $schema .= '"shortLabel": [';
                break;
                
                case Namespaces::$sco."orderingValue":
                  $schema .= '"orderingValue": [';
                break;  
                
                case Namespaces::$rdfs."subPropertyOf":
                  $schema .= '"subPropertyOf": [';
                break;
              }                 
              
              foreach($values as $value)
              {
                switch($predicate)
                {
                  case Namespaces::$iron."allowedValue":
                    $schema .= '{"primitive": "'.parent::jsonEncode($value["value"]).'"},';
                  break;                
                  
                  case Namespaces::$rdfs."domain":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;
                  
                  case Namespaces::$rdfs."range":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '{ "type": "'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'"},';
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    if(isset($value["uri"]))
                    {
                      $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $displayControl = $value["value"];
                    }
                    
                    $schema .= '"'.parent::jsonEncode($displayControl).'",';
                  break;
                  
                  case Namespaces::$sco."ignoredBy":
                    if(isset($value["uri"]))
                    {
                      $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                    }
                    else
                    {
                      $ignoredBy = $value["value"];
                    }
                    
                    $schema .= '"'.parent::jsonEncode($ignoredBy).'",';
                  break;

                  case Namespaces::$sco."comparableWith":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= '"'.parent::jsonEncode($value["value"]).'",';
                  break;
                  
                  case Namespaces::$sco."unitType":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;                  
                  
                  case Namespaces::$sco."orderingValue":
                    $schema .= '"'.parent::jsonEncode($value["value"]).'",';
                  break;     
                                                                   
                  case Namespaces::$rdfs."subPropertyOf":
                    $this->manageIronPrefixes($value["uri"], $prefixes);
                    
                    $schema .= '"'.parent::jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                  break;
                }
              }
              
              switch($predicate)
              {
                case Namespaces::$rdfs."domain":
                case Namespaces::$rdfs."range":
                case Namespaces::$sco."displayControl":
                case Namespaces::$sco."ignoredBy":
                case Namespaces::$sco."comparableWith":
                case Namespaces::$sco."unitType":
                case Namespaces::$sco."shortLabel":
                case Namespaces::$sco."orderingValue":
                case Namespaces::$rdfs."subPropertyOf":
                  $schema = rtrim($schema, ",");
                  $schema .= '],';  
                break;
              }                
            }
            
            $schema = rtrim($schema, ",");
            
            $schema .= "},";              
          }
          
          $schema = rtrim($schema, ",");
          
          $schema .= "},";
          $schema .= '"prefixList": {';                    

          foreach($prefixes as $prefix => $ns)
          {
            $schema .= "    \"$prefix\": \"$ns\",";
          }
          
          $schema = rtrim($schema, ",");
          
          $schema .= "}";          
          $schema .= "}";    
          $schema .= "}";    
          
          $subjectTriples = "";
          
          $subject = new Subject($this->ontologyUri);
          $subject->setType("owl:Ontology");
          $subject->setDataAttribute(Namespaces::$wsf."serializedIronJSONSchema", $schema);
          $this->rset->addSubject($subject);                     
          
/*
    
{
    "schema": {
        "version": "0.1",
        "typeList": {
            "bibo_ThesisDegree": {
                "description": "The academic degree of a Thesis",
                "prefLabel": "Thesis degree",
                "subTypeOf": [
                    "owl_Thing"
                ]
            },
            "0_1_Agent": {
                "description": "No description available",
                "prefLabel": "Agent",
                "subTypeOf": [
                    "owl_Thing"
                ]
            },
            "bibo_Event": {
                "description": "No description available",
                "prefLabel": "Event",
                "subTypeOf": [
                    "owl_Thing"
                ]
            }
        },
    
        "attributeList": {
            "bibo_sici": {
                "description": "No description available",
                "prefLabel": "sici",
                "allowedValue": {
                    "primitive": "String"
                },
                "subPropertyOf": [
                    "bibo_identifier"
                ]
            },
    
            "terms_rights": {
                "description": "No description available",
                "prefLabel": "rights",
                "subPropertyOf": [
                    "owl_topObjectProperty"
                ]
            },
            "0_1_based_near": {
                "description": "No description available",
                "prefLabel": "based_near",
                "subPropertyOf": [
                    "owl_topObjectProperty"
                ]
            }
        },
        "prefixList": {
            "bibo": "http://purl.org/ontology/bibo/",
            "owl": "http://www.w3.org/2002/07/owl#",
            "0_1": "http://xmlns.com/foaf/0.1/",
            "event_owl": "http://purl.org/NET/c4dm/event.owl#",
            "rdf_schema": "http://www.w3.org/2000/01/rdf-schema#",
            "22_rdf_syntax_ns": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
            "terms": "http://purl.org/dc/terms/",
            "basic": "http://prismstandard.org/namespaces/1.2/basic/",
            "schema": "http://schemas.talis.com/2005/address/schema#"
        }
    }
}    

*/          

        break;        
        
        case "getproperty":
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
          
          $property = $ontology->_getProperty($this->parameters["uri"]);
          
          if($property == NULL)
          {
            $this->returnError(400, "Bad Request", "_204");
          }
          else
          {
            $subject = new Subject($this->parameters["uri"]);
            $subject->setSubject($ontology->_getPropertyDescription($property));
            $this->rset->addSubject($subject);
          }
        break;
        
        case "getproperties":
        
          $limit = -1;
          $offset = 0;
          
          if(isset($this->parameters["limit"]))
          {
            $limit = $this->parameters["limit"];
          }
          
          if(isset($this->parameters["offset"]))
          {
            $offset = $this->parameters["offset"];
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getPropertiesUri(TRUE, FALSE, FALSE, $limit, $offset);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:DatatypeProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getPropertiesUri(FALSE, TRUE, FALSE, $limit, $offset);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:ObjectProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                case "annotationproperty":
                  $properties = $ontology->getPropertiesUri(FALSE, FALSE, TRUE, $limit, $offset);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:AnnotationProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getPropertiesDescription(TRUE, FALSE, FALSE, $limit, $offset)));
                break;
                
                case "objectproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getPropertiesDescription(FALSE, TRUE, FALSE, $limit, $offset)));
                break;
                
                case "annotationproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getPropertiesDescription(FALSE, FALSE, TRUE, $limit, $offset)));
                break;

                case "all":
                  $subjectTriples = array();
                  $subjectTriples = array_merge($subjectTriples, $ontology->getPropertiesDescription(TRUE, FALSE, FALSE, $limit, $offset));
                  $subjectTriples = array_merge($subjectTriples, $ontology->getPropertiesDescription(FALSE, TRUE, FALSE, $limit, $offset));
                  $subjectTriples = array_merge($subjectTriples, $ontology->getPropertiesDescription(FALSE, FALSE, TRUE, $limit, $offset));
                  $this->rset->setResultset(Array($this->ontologyUri => $subjectTriples));
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;
        
        case "getsubproperties":
        
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getSubPropertiesUri((string)$this->parameters["uri"], $this->parameters["direct"], TRUE);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:DatatypeProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getSubPropertiesUri((string)$this->parameters["uri"], $this->parameters["direct"], FALSE);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:ObjectProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getSubPropertiesDescription((string)$this->parameters["uri"], $this->parameters["direct"], TRUE)));
                break;
                
                case "objectproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getSubPropertiesDescription((string)$this->parameters["uri"], $this->parameters["direct"], FALSE)));
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;        
        
        case "getsuperproperties":
        
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getSuperPropertiesUri((string)$this->parameters["uri"], $this->parameters["direct"], TRUE);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:DatatypeProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getSuperPropertiesUri((string)$this->parameters["uri"], $this->parameters["direct"], FALSE);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:ObjectProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":

              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getSuperPropertiesDescription((string)$this->parameters["uri"], $this->parameters["direct"], TRUE)));
                break;
                
                case "objectproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getSuperPropertiesDescription((string)$this->parameters["uri"], $this->parameters["direct"], FALSE)));
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;   
        
        case "getequivalentproperties":
        
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getEquivalentPropertiesUri((string)$this->parameters["uri"], TRUE);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:DatatypeProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getEquivalentPropertiesUri((string)$this->parameters["uri"], FALSE);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:ObjectProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getEquivalentPropertiesDescription((string)$this->parameters["uri"], TRUE)));
                break;
                
                case "objectproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getEquivalentPropertiesDescription((string)$this->parameters["uri"], FALSE)));
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;
        
        case "getdisjointproperties":
        
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getDisjointPropertiesUri((string)$this->parameters["uri"], TRUE);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:DatatypeProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getDisjointPropertiesUri((string)$this->parameters["uri"], FALSE);
                  
                  foreach($properties as $property)
                  {
                    $subject = new Subject($property);
                    $subject->setType("owl:ObjectProperty");
                    $this->rset->addSubject($subject);
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getDisjointPropertiesDescription((string)$this->parameters["uri"], TRUE)));
                break;
                
                case "objectproperty":
                  $this->rset->setResultset(Array($this->ontologyUri => $ontology->getDisjointPropertiesDescription((string)$this->parameters["uri"], FALSE)));
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;        
        
        default:
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
          $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
            $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "This function is not defined
            for this endpoint:".$this->function,
            $this->errorMessenger->_200->level);

          return;          
        break;
      }
    }
  }

  private function generationSerializedPropertyHierarchy($OwlApiSession)
  {
    $ontologiesUri = OWLOntology::getLoadedOntologiesUri($OwlApiSession);

    $propertyHierarchy = new PropertyHierarchy("http://www.w3.org/2002/07/owl#Thing");
    
    foreach($ontologiesUri as $ontologyUri)
    {
      $onto = new OWLOntology($ontologyUri, $OwlApiSession, TRUE);
      
      $this->populatePropertyHierarchy("http://www.w3.org/2002/07/owl#topObjectProperty", $onto, $ontologyUri, $propertyHierarchy, FALSE);
      $this->populatePropertyHierarchy("http://www.w3.org/2002/07/owl#topDataProperty", $onto, $ontologyUri, $propertyHierarchy, TRUE);
    }  
                
    return(serialize($propertyHierarchy));
  } 
  
  private function populatePropertyHierarchy($parentProperty, $ontology, $ontologyUri, &$propertyHierarchy, $isDataProperty)
  {
    $subProperties = $ontology->getSubPropertiesDescription($parentProperty, TRUE, $isDataProperty);

    foreach($subProperties as $subProperty => $description)
    {
      $propertyHierarchy->addPropertyRelationship($subProperty, $parentProperty);

      $propertyHierarchy->properties[$subProperty]->label = preg_replace('/\s\s+/', ' ', str_replace(array("\r", "\n"), "", $this->getLabel($subProperty, $description))); 
      $propertyHierarchy->properties[$subProperty]->description = preg_replace('/\s\s+/', ' ', str_replace(array("\r", "\n"), "", $this->getDescription($description))); 
      
      $propertyHierarchy->properties[$subProperty]->isDefinedBy = $ontologyUri;
      
      // Add in-domain-of
      $domainClasses = array();
      if(isset($description[Namespaces::$rdfs."domain"]))
      {
        foreach($description[Namespaces::$rdfs."domain"] as $domain)
        {
          array_push($domainClasses, $ontology->getSubClassesUri($domain["uri"], FALSE));
        }
      }
      
      $domainClasses = array_unique($domainClasses);
      
      $propertyHierarchy->properties[$subProperty]->domain = $domainClasses;
      
      // Add in-range-of
      $rangeClasses = array();
      if(isset($description[Namespaces::$rdfs."range"]))
      {
        foreach($description[Namespaces::$rdfs."range"] as $range)
        {
          array_push($rangeClasses, $ontology->getSubClassesUri($range["uri"], FALSE));
        }
      }
           
      $rangeClasses = array_unique($rangeClasses);
      
      $propertyHierarchy->properties[$subProperty]->range = $rangeClasses;
      
      // Dig into the structure...
      $this->populatePropertyHierarchy($subProperty, $ontology, $ontologyUri, $propertyHierarchy, $isDataProperty);
    }
  }  
  
  private function generationSerializedClassHierarchy($OwlApiSession)
  {
    $ontologiesUri = OWLOntology::getLoadedOntologiesUri($OwlApiSession);
    
    $classHierarchy = new ClassHierarchy("http://www.w3.org/2002/07/owl#Thing");
    
    foreach($ontologiesUri as $ontologyUri)
    {
      $onto = new OWLOntology($ontologyUri, $OwlApiSession, TRUE);
      
      $this->populateClassHierarchy("http://www.w3.org/2002/07/owl#Thing", $onto, $ontologyUri, $classHierarchy);
    }  
    
    return(serialize($classHierarchy));
  } 
  
  private function populateClassHierarchy($parentClass, $ontology, $ontologyUri, &$classHierarchy)
  {
    $subClasses = $ontology->getSubClassesDescription($parentClass, TRUE);

    if(isset($subClasses[Namespaces::$owl."Nothing"]))
    {
      return;
    }
    
    foreach($subClasses as $subClass => $description)
    {
      $classHierarchy->addClassRelationship($subClass, $parentClass);

      $classHierarchy->classes[$subClass]->label = preg_replace('/\s\s+/', ' ', str_replace(array("\r", "\n"), "", $this->getLabel($subClass, $description))); 
      $classHierarchy->classes[$subClass]->description = preg_replace('/\s\s+/', ' ', str_replace(array("\r", "\n"), "", $this->getDescription($description))); 
      
      $classHierarchy->classes[$subClass]->isDefinedBy = $ontologyUri;
      
      // Dig into the structure...
      $this->populateClassHierarchy($subClass, $ontology, $ontologyUri, $classHierarchy);
    }
  }
  
  public function getLabel($uri, $description)  
  {
    $prefLabelAttributes = array(
      Namespaces::$rdfs."label",
      Namespaces::$skos_2004."prefLabel",
      Namespaces::$skos_2008."prefLabel",
      Namespaces::$umbel."prefLabel",
      Namespaces::$dcterms."title",
      Namespaces::$dc."title",
      Namespaces::$iron."prefLabel",
      Namespaces::$skos_2004."altLabel",
      Namespaces::$skos_2008."altLabel",
      Namespaces::$umbel."altLabel",
      Namespaces::$iron."altLabel"
    );
    
    foreach($prefLabelAttributes as $attribute)
    {
      if(isset($description[$attribute]))
      {
        return($description[$attribute][0]["value"]);
      }
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
  
  public function setOwlApiSession($OwlApiSession)
  {
    $this->OwlApiSession = $OwlApiSession;
  }
  
  public function ironPrefixize($uri, &$prefixes)
  {
    foreach($prefixes as $prefix => $u)
    {
      if(stripos($uri, $u) !== FALSE)
      {
        return(str_replace($u, $prefix."_", $uri));
      }
    }
    
    return($uri);
  } 
  
  public function manageIronPrefixes($uri, &$prefixes)
  {
    if(strripos($uri, "#") !== FALSE)
    {
      $p = substr($uri, strripos($uri, "/") + 1, strripos($uri, "#") - (strripos($uri, "/") + 1));
      
      $p = preg_replace("/[^A-Za-z0-9]/", "-", $p);
      
      if(!isset($prefixes[$p]))
      {
        $prefixes[$p] = substr($uri, 0, strripos($uri, "#") + 1);
      }
    }
    elseif(strripos($uri, "/") !== FALSE)
    {
      $uriMod = substr($uri, 0, strripos($uri, "/", strripos($uri, "/")));
      
      $p = substr($uriMod, strripos($uriMod, "/") + 1);

      $p = preg_replace("/[^A-Za-z0-9]/", "-", $p);
      
      if(!isset($prefixes[$p]))
      {
        $prefixes[$p] = substr($uri, 0, strripos($uri, "/", strripos($uri, "/")) + 1);
      }
    }
  } 
  
  /**
  * Start using the reasoner for the subsequent OWLOntology functions calls.
  */
  public function useReasoner()
  {
    $this->useReasoner = TRUE;
  }
  
  /**
  * Stop using the reasoner for the subsequent OWLOntology functions calls.
  */
  public function stopUsingReasoner()
  {
    $this->useReasoner = FALSE;
  }
}


//@}

?>
