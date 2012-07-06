<?php

/** @defgroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\auth\lister\AuthLister.php
    @brief Lists registered web services and available datasets
 */

namespace StructuredDynamics\structwsf\ws\auth\lister;  

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
use \StructuredDynamics\structwsf\ws\framework\Conneg;

/** AuthLister Web Service. It lists registered web services and available dataset
            
    @author Frederick Giasson, Structured Dynamics LLC.
*/

class AuthLister extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** IP of the requester */
  private $requester_ip = "";

  /** Requested IP (ex: a node wants to see all web services or datasets accessible for one of its user) */
  private $registered_ip = "";

  /** Target dataset URI if action = "access_dataset" */
  private $dataset = "";

  /** Type of the thing to list */
  private $mode = "";
  
  /** Specifies what web service we want to focus on for that query */
  private $targetWebservice = "all";

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/auth/lister/",
                        "_200": {
                          "id": "WS-AUTH-LISTER-200",
                          "level": "Warning",
                          "name": "Unknown Listing Mode",
                          "description": "The mode you specified for the \'mode\' parameter is unknown. Please check the documentation of this web service endpoint for more information"
                        },
                        "_201": {
                          "id": "WS-AUTH-LISTER-201",
                          "level": "Warning",
                          "name": "No Target Dataset URI",
                          "description": "No target dataset URI defined for this request. A target dataset URI is needed for the mode \'ws\' and \'dataset\'"
                        },
                        "_300": {
                          "id": "WS-AUTH-LISTER-300",
                          "level": "Fatal",
                          "name": "Can\'t get the list of datasets",
                          "description": "An error occured when we tried to get the list of datasets available to the user"
                        },
                        "_301": {
                          "id": "WS-AUTH-LISTER-301",
                          "level": "Fatal",
                          "name": "Can\'t get the list of web services",
                          "description": "An error occured when we tried to get the list of web services endpoints registered to this web service network"
                        },
                        "_302": {
                          "id": "WS-AUTH-LISTER-302",
                          "level": "Fatal",
                          "name": "Can\'t get the list of accesses for that dataset",
                          "description": "An error occured when we tried to get the list of accesses defined for this dataset"
                        },
                        "_303": {
                          "id": "WS-AUTH-LISTER-303",
                          "level": "Fatal",
                          "name": "Can\'t get the list of accesses an datasets available to that user",
                          "description": "An error occured when we tried to get the list of accesses and datasets accessible to that user"
                        },  
                        "_304": {
                          "id": "WS-AUTH-LISTER-304",
                          "level": "Fatal",
                          "name": "Can\'t get access information for this web service",
                          "description": "An error occured when we tried to get the information for the access to that web service."
                        },
                        "_305": {
                          "id": "WS-AUTH-LISTER-305",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        }    
                      }';

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/iron+json", 
           "application/iron+csv", "application/*", "text/xml", "text/*", "*/*");

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

    @param $mode One of:  (1) "dataset (default)": List all datasets URI accessible by a user, 
                          (2) "ws": List all Web services registered in a WSF
                          (3) "access_dataset": List all the registered IP addresses and their CRUD permissions for a given dataset URI
                          (4) "access_user": List all datasets URI and CRUD permissions accessible by a user 
    @param $dataset URI referring to a target dataset. Needed when param1 = "dataset" or param1 = "access_datase". Otherwise this parameter as to be ommited.
    @param $registered_ip Target IP address registered in the WSF
    @param $requester_ip IP address of the requester
    @param $target_webservice Determine on what web service URI(s) we should focus on for the listing of the access records.
                              This parameter is used to improve the performance of the web service endpoint depending on the 
                              use case. If there are numerous datasets with a numerous number of access permissions defined 
                              for each of them, properly using this parameter can have a dramatic impact on the performances. 
                              This parameter should be used if the param1 = "access_dataset" or param1 = "access_user" This 
                              parameter can have any of these values:
                             
                                + "all" (default): all the web service endpoints URIs for each access records will 
                                                   be taken into account and returned to the user (may be more time 
                                                   consuming).
                                + "none": no web service URI, for any access record, will be returned. 
    @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
    
    @return returns NULL
  
    @author Frederick Giasson, Structured Dynamics LLC.
*/
  function __construct($mode, $dataset, $registered_ip, $requester_ip, $target_webservice = "all", 
                       $interface='default')
  {
    parent::__construct();

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->requester_ip = $requester_ip;
    $this->mode = $mode;
    $this->dataset = $dataset;
    $this->targetWebservice = strtolower($target_webservice);
    
    if(strtolower($interface) == "default")
    {
      $this->interface = "DefaultSourceInterface";
    }
    else
    {
      $this->interface = $interface;
    }    
    
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/auth/lister/";
    $this->title = "Authentication Lister Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/auth/lister/";

    $this->dtdURL = "auth/lister/authLister.dtd";

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
    // publicly accessible users
    if($this->mode != "dataset" && $this->mode != "access_user")
    {
      $ws_av = new AuthValidator($this->requester_ip, $this->wsf_graph, $this->uri);

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

  /** Returns the error structure
              
      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getError() { return ($this->conneg->error); }

  /** Create a resultset in a pipelined mode based on the processed information by the Web service.
      
      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResultset() 
  { 
    return($this->injectDoctype($this->rset->getResultsetXML()));
  }

  /** Inject the DOCType in a XML document
              
      @param $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function injectDoctype($xmlDoc)
  {
    $posHeader = strpos($xmlDoc, '"?>') + 3;
    $xmlDoc = substr($xmlDoc, 0, $posHeader)
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Auth Lister DTD 0.1//EN\" \"" . $this->dtdBaseURL
        . $this->dtdURL . "\">" . substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);

    return ($xmlDoc);
  }

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, AuthLister::$supportedSerializations);

    // Check for errors
    if(strtolower($this->mode) != "ws" && strtolower($this->mode) != "dataset"
      && strtolower($this->mode) != "access_dataset" && strtolower($this->mode) != "access_user")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt("Unknown listing type");
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, odbc_errormsg(),
        $this->errorMessenger->_200->level);
      return;
    }

    // Check for errors
    if(strtolower($this->mode) != "access_dataset" && $dataset = "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, odbc_errormsg(),
        $this->errorMessenger->_201->level);
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
  public function ws_serialize()
  {
    return($this->serializations());
  }

  /** Aggregates information about the Accesses available to the requester.

      @return NULL      
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/auth/lister/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\auth\lister\interfaces\\'.$class;
      
      $interface = new $class($this);
      
      // Process the code defined in the source interface
      $interface->processInterface();
    }
    else
    { 
      // Interface not existing
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_305->name);
      $this->conneg->setError($this->errorMessenger->_305->id, $this->errorMessenger->ws,
        $this->errorMessenger->_305->name, $this->errorMessenger->_305->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_305->level);
    }        
  }
}

//@}

?>