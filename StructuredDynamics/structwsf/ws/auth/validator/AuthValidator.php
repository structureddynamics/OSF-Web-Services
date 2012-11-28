<?php

/*! @ingroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator.php
    @brief Define the Authentication / Registration web service
 */

namespace StructuredDynamics\structwsf\ws\auth\validator;   

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\framework\Conneg;

/** Auth Validator Web Service. It validates queries to a web service of the web service framework linked to this authentication web service.
    
    @author Frederick Giasson, Structured Dynamics LLC.
*/

class AuthValidator extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Error message to report */
  private $errorMessages = "";

  /** IP of the requester */
  private $requester_ip = "";

  /** Datasets requested by the requester */
  private $requested_datasets = "";

  /** Web service URI where the request has been made, and that is registered on this web service */
  private $requested_ws_uri = "";

  /** The validation answer of the query */
  private $valid = "False";

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/iron+json", 
           "application/iron+csv", "application/*", "text/xml", "text/*", "*/*");

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/auth/validator/",
                        "_200": {
                          "id": "WS-AUTH-VALIDATOR-200",
                          "level": "Warning",
                          "name": "No requester IP available",
                          "description": "No requester IP address defined for this query"
                        },
                        "_201": {
                          "id": "WS-AUTH-VALIDATOR-201",
                          "level": "Warning",
                          "name": "No target dataset",
                          "description": "No target dataset defined for this query"
                        },
                        "_202": {
                          "id": "WS-AUTH-VALIDATOR-202",
                          "level": "Warning",
                          "name": "No web service URI available",
                          "description": "No target web service URI defined for this query"
                        },
                        "_203": {
                          "id": "WS-AUTH-VALIDATOR-203",
                          "level": "Warning",
                          "name": "Invalid target dataset IRI",
                          "description": "One of the IRI of the input target dataset(s) is not a valid IRI."
                        },
                        "_204": {
                          "id": "WS-AUTH-VALIDATOR-204",
                          "level": "Warning",
                          "name": "Invalid target web service IRI",
                          "description": "The IRI of the input web service is not a valid IRI."
                        },
                        "_300": {
                          "id": "WS-AUTH-VALIDATOR-300",
                          "level": "Fatal",
                          "name": "Can\'t get the CRUD permissions of the target web service",
                          "description": "An error occured when we tried to get the CRUD permissions of the target web service"
                        },
                        "_301": {
                          "id": "WS-AUTH-VALIDATOR-301",
                          "level": "Warning",
                          "name": "Target web service not registered",
                          "description": "Target web service not registered to this Web Services Framework"
                        },
                        "_302": {
                          "id": "WS-AUTH-VALIDATOR-302",
                          "level": "Fatal",
                          "name": "Can\'t get the list of datasets accessible to this user",
                          "description": "An error occured when we tried to get the list of datasets accessible to this user"
                        },
                        "_303": {
                          "id": "WS-AUTH-VALIDATOR-303",
                          "level": "Warning",
                          "name": "No access defined",
                          "description": "No access defined for this requester IP , dataset and web service"
                        },
                        "_304": {
                          "id": "WS-AUTH-VALIDATOR-304",
                          "level": "Warning",
                          "name": "No create permissions",
                          "description": "The target web service needs create access and the requested user doesn\'t have this access for that dataset."
                        },
                        "_305": {
                          "id": "WS-AUTH-VALIDATOR-305",
                          "level": "Warning",
                          "name": "No update permissions",
                          "description": "The target web service needs update access and the requested user doesn\'t have this access for that dataset."
                        },
                        "_306": {
                          "id": "WS-AUTH-VALIDATOR-306",
                          "level": "Warning",
                          "name": "No read permissions",
                          "description": "The target web service needs read access and the requested user doesn\'t have this access for that dataset."
                        },
                        "_307": {
                          "id": "WS-AUTH-VALIDATOR-307",
                          "level": "Warning",
                          "name": "No delete permissions",
                          "description": "The target web service needs delete access and the requested user doesn\'t have this access for that dataset."
                        },
                        "_308": {
                          "id": "WS-AUTH-VALIDATOR-308",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_309": {
                          "id": "WS-AUTH-VALIDATOR-309",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_310": {
                          "id": "WS-AUTH-VALIDATOR-310",
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
        
      @param $requester_ip IP address of the requester
      @param $requested_datasets Target dataset targeted by the query of the user
      @param $requested_ws_uri Target web service endpoint accessing the target dataset
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($requester_ip, $requested_datasets, $requested_ws_uri, 
                       $interface='default', $requestedInterfaceVersion="")
  { 
    parent::__construct();
    
    $this->version = "1.0";

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->requester_ip = $requester_ip;
    $this->requested_datasets = $requested_datasets;
    $this->requested_ws_uri = $requested_ws_uri;
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["auth_validator"];
    }
    else
    {
      $this->interface = $interface;
    }
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;

    $this->uri = $this->wsf_base_url . "/wsf/ws/auth/validator/";
    $this->title = "Authentication Validator Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/auth/validator/";

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
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery() { return TRUE; }

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
    $this->conneg =
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, AuthValidator::$supportedSerializations);

    // Check for errors
    if($this->requester_ip == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);

      return;
    }

    if($this->requested_datasets == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setStatusMsgExt($this->errorMessenger->_->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
        $this->errorMessenger->_201->level);

      return;
    }

    if($this->requested_ws_uri == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_->name);
      $this->conneg->setStatusMsgExt($this->errorMessenger->_->name);
      $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
        $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
        $this->errorMessenger->_202->level);

      return;
    }
    
    $datasets = array();
    
    if(strpos($this->requested_datasets, ";") !== FALSE)
    {
      $datasets = explode(";", $this->requested_datasets);
    }
    else
    {
      array_push($datasets, $this->requested_datasets);
    }    
    
    foreach($datasets as $dataset)
    {
      if(!$this->isValidIRI($dataset))
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
        $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
          $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
          $this->errorMessenger->_203->level);

        unset($resultset);      
        
        return;    
      }
    }    
    
    if(!$this->isValidIRI($this->requested_ws_uri))
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_204->name);
      $this->conneg->setError($this->errorMessenger->_204->id, $this->errorMessenger->ws,
        $this->errorMessenger->_204->name, $this->errorMessenger->_204->description, "",
        $this->errorMessenger->_204->level);

      unset($resultset);      
      
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

  /** Validate the request

      @author Frederick Giasson, Structured Dynamics LLC.
  */

  public function process()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/auth/validator/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\auth\validator\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_310->name);
          $this->conneg->setError($this->errorMessenger->_310->id, $this->errorMessenger->ws,
            $this->errorMessenger->_310->name, $this->errorMessenger->_310->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_310->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_309->name);
          $this->conneg->setError($this->errorMessenger->_309->id, $this->errorMessenger->ws,
            $this->errorMessenger->_309->name, $this->errorMessenger->_309->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_309->level);  
            
            return;
        }
      }      
      
      // Process the code defined in the source interface
      $interface->processInterface();
    }
    else
    { 
      // Interface not existing
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_308->name);
      $this->conneg->setError($this->errorMessenger->_308->id, $this->errorMessenger->ws,
        $this->errorMessenger->_308->name, $this->errorMessenger->_308->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_308->level);
    } 
  }
}

//@}

?>