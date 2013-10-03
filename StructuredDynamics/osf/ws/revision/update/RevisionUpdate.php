<?php

/** @defgroup WsRevision Revisioning Web Service */
//@{

/*! @file \StructuredDynamics\osf\ws\revision\update\RevisionUpdate.php
    @brief Change the lifestage status of a revision record.
 */

namespace StructuredDynamics\osf\ws\revision\update;  

use \StructuredDynamics\osf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\osf\ws\framework\Conneg;
use \StructuredDynamics\osf\ws\framework\CrudUsage;

/** RevisionUpdate Web Service. Change the lifestage status of a revision record.
            
    @author Frederick Giasson, Structured Dynamics LLC.
*/

class RevisionUpdate extends \StructuredDynamics\osf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Dataset URI where to index the RDF document. Note: this is the Dataset URI, and not the Dataset Revisions URI  */
  private $dataset = "";

  /** URI of the revision to update */
  private $revuri = "";
  
  /** Publication status to use to update the revision record */
  private $lifestage = "";
  
  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/revision/update/",
                        "_200": {
                          "id": "WS-REVISION-UPDATE-200",
                          "level": "Warning",
                          "name": "No Target Dataset URI",
                          "description": "No target dataset URI defined for this request. A target dataset URI is needed for the mode \'ws\' and \'dataset\'"
                        },
                        "_201": {
                          "id": "WS-REVISION-UPDATE-201",
                          "level": "Warning",
                          "name": "No Target Revision URI",
                          "description": "No target revision URI defined for this request."
                        },
                        "_300": {
                          "id": "WS-REVISION-UPDATE-300",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_301": {
                          "id": "WS-REVISION-UPDATE-301",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_302": {
                          "id": "WS-REVISION-UPDATE-302",
                          "level": "Fatal",
                          "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
                          "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
                        },
                        "_303": {
                          "id": "WS-REVISION-UPDATE-303",
                          "level": "Fatal",
                          "name": "Unknown publication lifecycle stage",
                          "description": "The publication lifecycle stage that has been specified for this query is unknown. Known publication lifecycle stages are: published, archive, experimental, pre_release, staging, harvesting, unspecified"
                        },
                        "_304": {
                          "id": "WS-REVISION-UPDATE-304",
                          "level": "Fatal",
                          "name": "Couldn\'t change the lifecycle stage of the revision",
                          "description": "The lifecycle stage status of the currently published revision couldn\'t be changed"
                        },
                        "_305": {
                          "id": "WS-REVISION-UPDATE-305",
                          "level": "Fatal",
                          "name": "Couldn\'t change the lifecycle stage of the revision to published",
                          "description": "The lifecycle stage status of the currently published revision couldn\'t be changed to published"
                        },
                        "_306": {
                          "id": "WS-REVISION-UPDATE-306",
                          "level": "Fatal",
                          "name": "Can\'t query the revisions graph",
                          "description": "Can\'t read the revisions graph to get the lifecycle stage of the last revision"
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

    @param $revuri URI of the revision to update
    @param $dataset URI referring to a target dataset. Needed when param1 = "dataset" or param1 = "access_datase". Otherwise this parameter as to be ommited.
    @param $lifecycle Publication status to use to update the revision record
                      (1) "published"
                      (2) "archive"
                      (3) "experimental"
                      (4) "pre_release"
                      (5) "staging"
                      (6) "harvesting"
                      (7) "unspecified"
    @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
    @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                      version of the interface.

    
    @return returns NULL
  
    @author Frederick Giasson, Structured Dynamics LLC.
*/
  function __construct($revuri, $dataset, $lifecycle, $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();

    $this->version = "3.0";

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->dataset = $dataset;
    $this->revuri = $revuri;
    $this->lifecycle = strtolower($lifecycle);
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["revision_update"];
    }
    else
    {
      $this->interface = $interface;
    }       
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;    
    
    $this->uri = $this->wsf_base_url . "/wsf/ws/revision/update/";
    $this->title = "Revision Update Web Service";
    $this->crud_usage = new CrudUsage(FALSE, FALSE, TRUE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/revision/update/";

    $this->dtdURL = "revision/update/revisionUpdate.dtd";

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
    if($this->validateUserAccess($this->dataset))
    {      
      // Check for errors
      if($this->dataset == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
        $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
          $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, odbc_errormsg(),
          $this->errorMessenger->_200->level);
        return;
      }
      
      if($this->revuri == "")
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, RevisionUpdate::$supportedSerializations);

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

  /** Aggregates information about the Accesses available to the requester.

      @return NULL      
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  { 
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/revision/update/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\osf\ws\revision\update\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
          $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
            $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_301->level);
            
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
      $interface->processInterface();
    }
    else
    { 
      // Interface not existing
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
      $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
        $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_300->level);
    }        
  }
}

//@}

?>