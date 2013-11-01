<?php

/** @defgroup WsOntology Ontology Management Web Service */
//@{

/*! @file \StructuredDynamics\osf\ws\ontology\delete\OntologyDelete.php
    @brief Delete entire ontologies from the system, or parts of it (resources of type class or property)
 */

namespace StructuredDynamics\osf\ws\ontology\delete; 

use \StructuredDynamics\osf\ws\framework\CrudUsage;
use \StructuredDynamics\osf\ws\framework\Conneg;

/** Ontology Delete Web Service. Delete entire ontologies from the system, or parts of it 
            (resources of type class or property)

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class OntologyDelete extends \StructuredDynamics\osf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /** URI of the ontology to query */
  private $ontologyUri = "";

  /** Ontology object. */
  public $ontology;

  /** URI of the inference rules set to use to delete the ontological structure. */
  private $rulesSetURI = "";      

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/ontology/delete/",
                        "_200": {
                          "id": "WS-ONTOLOGY-DELETE-200",
                          "level": "Warning",
                          "name": "Unknown function call",
                          "description": "The function call being requested is unknown or unsupported by this Ontology Delete web service endpoint"
                        },                        
                        "_201": {
                          "id": "WS-ONTOLOGY-DELETE-201",
                          "level": "Warning",
                          "name": "No Ontology URI defined for this request",
                          "description": "No Ontology URI defined for this request"
                        },
                        "_202": {
                          "id": "WS-ONTOLOGY-DELETE-202",
                          "level": "Warning",
                          "name": "No Property URI defined for this request",
                          "description": "No Property URI defined for this request"
                        },
                        "_203": {
                          "id": "WS-ONTOLOGY-DELETE-203",
                          "level": "Warning",
                          "name": "No Named Individual URI defined for this request",
                          "description": "No Named Individual URI defined for this request"
                        },                        
                        "_204": {
                          "id": "WS-ONTOLOGY-DELETE-204",
                          "level": "Warning",
                          "name": "No Class URI defined for this request",
                          "description": "No Class URI defined for this request"
                        },                        
                        "_300": {
                          "id": "WS-ONTOLOGY-DELETE-300",
                          "level": "Error",
                          "name": "Can\'t load the ontology",
                          "description": "The ontology can\'t be loaded by the endpoint"
                        },
                        "_301": {
                          "id": "WS-ONTOLOGY-DELETE-301",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_302": {
                          "id": "WS-ONTOLOGY-DELETE-302",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_303": {
                          "id": "WS-ONTOLOGY-DELETE-303",
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
      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($ontologyUri, $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();
    
    $this->version = "3.0";

    $this->ontologyUri = $ontologyUri;
      
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["ontology_delete"];
    }
    else
    {
      $this->interface = $interface;
    }
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;

    $this->uri = $this->wsf_base_url . "/wsf/ws/ontology/delete/";
    $this->title = "Ontology Delete Web Service";
    $this->crud_usage = new CrudUsage(FALSE, FALSE, FALSE, TRUE);
    $this->endpoint = $this->wsf_base_url . "/ws/ontology/delete/";

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct()
  {
    parent::__destruct();

    // If we are in pipeline mode, then we *don't* close the ODBC connection.
    // If we are *not* then we have to close the connection.
    if(isset($this->db) && !$this->isInPipelineMode)
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
    if($this->validateUserAccess(array($this->wsf_graph . "ontologies/", $this->ontologyUri)))   
    {
      // Check if the URI is defined.
      if($this->ontologyUri == "")
      {
        $this->returnError(400, "Bad Request", "_201");
        return;
      }
    }
  }

  /** Returns the error structure

      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getError() { return ($this->conneg->error); }


  /**  @brief Delete a resultset in a pipelined mode based on the processed information by the Web service.

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
      OntologyDelete::$supportedSerializations);
      
    // Validate call
    $this->validateCall();  
      
    // Validate query
    if($this->conneg->getStatus() == 200)
    {
      $this->validateQuery();
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
  {     
    $this->ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language); 
    
    $this->isInPipelineMode = TRUE;
  }
  
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

  /**
  * 
  * 
  * @param mixed $statusCode
  * @param mixed $statusMsg
  * @param mixed $wsErrorCode
  * @param mixed $debugInfo
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function returnError($statusCode, $statusMsg, $wsErrorCode, $debugInfo = "")
  {
    $this->conneg->setStatus($statusCode);
    $this->conneg->setStatusMsg($statusMsg);
    $this->conneg->setStatusMsgExt($this->errorMessenger->{$wsErrorCode}->name);
    $this->conneg->setError($this->errorMessenger->{$wsErrorCode}->id, $this->errorMessenger->ws,
      $this->errorMessenger->{$wsErrorCode}->name, $this->errorMessenger->{$wsErrorCode}->description, $debugInfo,
      $this->errorMessenger->{$wsErrorCode}->level);
  }
  
  private function getInterface()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/ontology/delete/interfaces/");

    if($class != "")
    {    
      $class = 'StructuredDynamics\osf\ws\ontology\delete\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
          $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
            $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_303->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
          $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
            $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_302->level);  
            
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
      $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
      $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
        $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_301->level);
        
      return(NULL);
    }    
  }
  

  /**
  * 
  *  
  * @param mixed $uri
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function deleteProperty($uri)
  {
    $interface = $this->getInterface();
    
    if($interface !== NULL)
    {
      $interface->deleteProperty($uri);     
    }
  }
  
  /**
  * 
  *  
  * @param mixed $uri
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function deleteNamedIndividual($uri)
  {
    $interface = $this->getInterface();
    
    if($interface !== NULL)
    {
      $interface->deleteNamedIndividual($uri);    
    }
  }  

  /**
  * 
  *   
  * @param mixed $uri
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function deleteClass($uri)
  {
    $interface = $this->getInterface();
    
    if($interface !== NULL)
    {
      $interface->deleteClass($uri);
    }
  }
  
  /**
  * 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function deleteOntology()
  {
    $interface = $this->getInterface();
    
    if($interface !== NULL)
    {
      $interface->deleteOntology();  
    }
  }
}

//@}

?>