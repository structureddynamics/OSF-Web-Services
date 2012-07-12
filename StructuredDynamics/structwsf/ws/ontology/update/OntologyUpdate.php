<?php

/** @defgroup WsOntology Ontology Management Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\ontology\update\OntologyUpdate.php
    @brief Update an ontology
 */

namespace StructuredDynamics\structwsf\ws\ontology\update; 

use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\framework\Namespaces;

/** Update an ontology. Update any resource of an ontology. It may be a class, 
    object property, datatype property, annotation property or instances. Updating 
    an ontology here means adding or modifying any resource in the ontology.       
    
    @author Frederick Giasson, Structured Dynamics LLC.
*/

class OntologyUpdate extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /** IP being registered */
  private $registered_ip = "";

  /** URI where the web service can fetch the ontology document */
  private $ontologyUri = "";
  
  /** Ontology object. */
  public $ontology;  
  
  /** enable/disable the reasoner when doing advanced indexation */
  private $reasoner = TRUE;  
  
  /** Error messages of this web service */
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
                        },
                        "_302": {
                          "id": "WS-ONTOLOGY-UPDATE-305",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_303": {
                          "id": "WS-ONTOLOGY-UPDATE-303",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_304": {
                          "id": "WS-ONTOLOGY-UPDATE-304",
                          "level": "Fatal",
                          "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
                          "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
                        }                          
                      }';

  /**
  * Implementation of the __get() magic method. We do implement it to create getter functions
  * for all the protected and private variables of this class, and to all protected variables
  * of the parent class.
  * 
  * This implementation is needed by the interfaces layer since we want the SourceInterface
  * class to access the variables of the web service class for which it is used as a 
  * source interface.
  * 
  * This means that all the privated and propected variables of these web service objects
  * are available to users; but they won't be able to set values for them.
  * 
  * Also note that This method is about 4 times slower than having the varaible as public instead 
  * of protected and private. However, these variables are only accessed about 10 to 200 times 
  * per script call. This means that for accessing these undefined variable using the __get magic 
  * method call, then it adds about 0.00022 seconds to the call or, about 0.22 milli-second 
  * (one fifth of a millisecond) For the gain of keeping the variables protected and private, 
  * we can spend this one fifth of a milli-second. This is a good compromize.  
  * 
  * @param mixed $name Name of the variable that is currently not defined for this object
  */
  public function __get($name)
  {
    // Check if the variable exists (so, if it is private or protected). If it is, then
    // we return the value. Otherwise a fatal error will be returned by PHP.
    if(isset($this->{$name}))
    {
      return($this->{$name});
    }
  }                      

  /** Constructor
          
      @param $ontologyUri URI of the ontology where to delete something
      @param $registered_ip Target IP address registered in the WSF
      @param $requester_ip IP address of the requester
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($ontologyUri, $registered_ip, $requester_ip, $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();
    
    $this->version = "1.0";

    $this->registered_ip = $registered_ip;
    $this->requester_ip = $requester_ip;
    
    $this->ontologyUri = $ontologyUri;
    
    if($this->registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
    }
    
    if(strtolower($interface) == "default")
    {
      $this->interface = "DefaultSourceInterface";
    }
    else
    {
      $this->interface = $interface;
    }    
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;

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

  /** Validate a query to this web service

      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery()
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

  /** Returns the error structure

      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getError() { return ($this->conneg->error); }


  /**  @brief Update a resultset in a pipelined mode based on the processed information by the Web service.

      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResultset() { return ""; }

  /** Inject the DOCType in a XML document

      @param $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function injectDoctype($xmlDoc) { return ""; }

  /** Do content negotiation as an external Web Service

      @param $accept Accepted mime types (HTTP header)
      
      @param $accept_charset Accepted charsets (HTTP header)
      
      @param $accept_encoding Accepted encodings (HTTP header)
  
      @param $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
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

  /** Do content negotiation as an internal, pipelined, Web Service that is part of a Compound Web Service

      @param $accept Accepted mime types (HTTP header)
      
      @param $accept_charset Accepted charsets (HTTP header)
      
      @param $accept_encoding Accepted encodings (HTTP header)
  
      @param $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
    { $this->ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language); }

  /** Returns the response HTTP header status

      @return returns the response HTTP header status
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResponseHeaderStatus() { return $this->conneg->getStatus(); }

  /** Returns the response HTTP header status message

      @return returns the response HTTP header status message
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResponseHeaderStatusMsg() { return $this->conneg->getStatusMsg(); }

  /** Returns the response HTTP header status message extension

      @return returns the response HTTP header status message extension
    
      @note The extension of a HTTP status message is
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResponseHeaderStatusMsgExt() { return $this->conneg->getStatusMsgExt(); }

  /** Serialize the web service answer.

      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function ws_serialize() { return ""; }
  
  public function returnError($statusCode, $statusMsg, $wsErrorCode, $debugInfo = "")
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
    $interface = $this->getInterface();
    
    if($interface !== NULL)
    {
      $interface->updateEntityUri($oldUri, $newUri, $advancedIndexation);      
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
    $interface = $this->getInterface();
    
    if($interface !== NULL)
    {
      $interface->createOrUpdateEntity($document, $advancedIndexation);  
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
    $interface = $this->getInterface();
    
    if($interface !== NULL)
    {
      $interface->saveOntology();        
    }
  }
    
  /**
  * Enable the reasoner for advanced indexation 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function useReasonerForAdvancedIndexation()
  {
    $this->reasoner = TRUE;
  }
  
  /**
  * Disable the reasoner for advanced indexation 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function stopUsingReasonerForAdvancedIndexation()
  {
    $this->reasoner = FALSE;
  }  
  
  private function getInterface()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/ontology/update/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\ontology\update\interfaces\\'.$class;
      
      $interface = new $class($this);
      
      // Validate versions
      if($this->requestedInterfaceVersion == "")
      {
        // The default requested version is the last version of the interface
        $this->requestedInterfaceVersion = $interface->getVersion();
      }
      else
      {
        if(!$interface->validateWebServiceCompatibility())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
          $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
            $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_304->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
          $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
            $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_303->level);  
            
            return;
        }
      }
      
      // Process the code defined in the source interface
      return($interface);
    }
    else
    { 
      // Interface not existing
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
      $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
        $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_302->level);
        
      return(NULL);
    }    
  }  
}

//@}

?>
