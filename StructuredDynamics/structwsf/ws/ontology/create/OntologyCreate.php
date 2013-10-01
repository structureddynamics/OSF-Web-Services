<?php

/** @defgroup WsOntology Ontology Management Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\ontology\create\OntologyCreate.php
    @brief Add/Import a new ontology into the ontological structure of a structWSF network instance.
 */

namespace StructuredDynamics\structwsf\ws\ontology\create; 

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\framework\Namespaces;

/** Add/Import a new ontology into the ontological structure of a structWSF network instance.

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class OntologyCreate extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

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

  /** Requester's IP used for request validation */
  private $requester_ip = "";

  /** URI of the inference rules set to use to create the ontological structure. */
  private $rulesSetURI = "";

  /** Create permissions for the global user */
  private $globalPermissionCreate = FALSE;

  /** Read permissions for the global user */
  private $globalPermissionRead = FALSE;

  /** Update permissions for the global user */
  private $globalPermissionUpdate = FALSE;

  /** Delete permissions for the global user */
  private $globalPermissionDelete = FALSE;

  /** If this parameter is set, the Ontology Create web service endpoint will index
             the ontology in the normal structWSF data stores. That way, the ontology
             will also become queryable via the standard services such as Search and Browse.
  */
  private $advancedIndexation = FALSE;
  
  /** enable/disable the reasoner when doing advanced indexation */
  private $reasoner = TRUE;

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/ontology/create/",
                        "_200": {
                          "id": "WS-ONTOLOGY-CREATE-200",
                          "level": "Warning",
                          "name": "No Ontology URI defined for this request",
                          "description": "No Ontology URI defined for this request"
                        },
                        "_300": {
                          "id": "WS-ONTOLOGY-CREATE-300",
                          "level": "Error",
                          "name": "Can\'t load the ontology",
                          "description": "The ontology can\'t be loaded by the endpoint"
                        },
                        "_301": {
                          "id": "WS-ONTOLOGY-CREATE-301",
                          "level": "Error",
                          "name": "Can\'t tag dataset",
                          "description": "Can\'t tag the dataset as being a dataset holding an ontology description"
                        },
                        "_302": {
                          "id": "WS-ONTOLOGY-CREATE-302",
                          "level": "Error",
                          "name": "Ontology already existing",
                          "description": "Can\'t create the ontology because an ontology with that URI is already existing in the system."
                        },
                        "_303": {
                          "id": "WS-ONTOLOGY-CREATE-303",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_304": {
                          "id": "WS-ONTOLOGY-CREATE-304",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_305": {
                          "id": "WS-ONTOLOGY-CREATE-305",
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
          
      @param $ontologyUri URI where the webservice can fetch the ontology file
      @param $registered_ip Target IP address registered in the WSF
      @param $requester_ip IP address of the requester
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($ontologyUri, $registered_ip, $requester_ip, $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();
    
    $this->version = "1.0";

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);
    
    $this->registered_ip = $registered_ip;
    $this->requester_ip = $requester_ip;
    $this->ontologyUri = $ontologyUri;

    if($this->registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
    }
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["ontology_create"];
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/ontology/create/";
    $this->title = "Ontology Create Web Service";
    $this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/ontology/create/";

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
    if($this->validateUserAccess($this->wsf_graph . "ontologies/"))
    {
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
  }

  /** Returns the error structure

      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getError() { return ($this->conneg->error); }


  /**  @brief Create a resultset in a pipelined mode based on the processed information by the Web service.

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
      OntologyCreate::$supportedSerializations);

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

  public function returnError($statusCode, $statusMsg, $wsErrorCode, $debugInfo = "")
  {
    $this->conneg->setStatus($statusCode);
    $this->conneg->setStatusMsg($statusMsg);
    $this->conneg->setStatusMsgExt($this->errorMessenger->{$wsErrorCode}->name);
    $this->conneg->setError($this->errorMessenger->{$wsErrorCode}->id, $this->errorMessenger->ws,
      $this->errorMessenger->{$wsErrorCode}->name, $this->errorMessenger->{$wsErrorCode}->description, $debugInfo,
      $this->errorMessenger->{$wsErrorCode}->level);
  }


  /** Update all ontological structures used by the WSF

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function createOntology()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/ontology/create/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\ontology\create\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_305->name);
          $this->conneg->setError($this->errorMessenger->_305->id, $this->errorMessenger->ws,
            $this->errorMessenger->_305->name, $this->errorMessenger->_305->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_305->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
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
      }
      
      // Process the code defined in the source interface
      $interface->createOntology();
    }
    else
    { 
      // Interface not existing
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
      $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
        $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_303->level);
    }    
  }  

  /**
  * Set the advanced indexation mode of the ontology create class. This should be set before running process().
  * 
  * @param mixed $advancedIndexation Set to TRUE to enable the advanced indexation.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setAdvancedIndexation($advancedIndexation)
  {
    $this->advancedIndexation = $advancedIndexation;
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
  
  /**
  * @brief Set the global Create permission to the ontology being created. The global permission is what is
  *        defined for *all* users. This should be set before running process().
  * 
  * @param mixed $create Create permission: TRUE or FALSE.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setGlobalPermissionCreate($create)
  {
    $this->globalPermissionCreate = $create;
  }
  
  /**
  * @brief Set the global Read permission to the ontology being created. The global permission is what is
  *        defined for *all* users. This should be set before running process().
  * 
  * @param mixed $read Create permission: TRUE or FALSE.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setGlobalPermissionRead($read)
  {
    $this->globalPermissionRead = $read;
  }
  
  /**
  * @brief Set the global Update permission to the ontology being created. The global permission is what is
  *        defined for *all* users. This should be set before running process().
  * 
  * @param mixed $update Create permission: TRUE or FALSE.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setGlobalPermissionUpdate($update)
  {
    $this->globalPermissionUpdate = $update;
  }
  
  /**
  * @brief Set the global Delete permission to the ontology being created. The global permission is what is
  *        defined for *all* users. This should be set before running process().
  * 
  * @param mixed $delete Create permission: TRUE or FALSE.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setGlobalPermissionDelete($delete)
  {
    $this->globalPermissionDelete = $delete;
  } 
}

//@}

?>