<?php

/** @defgroup WsRevision Revisioning Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\revision\delete\RevisionRead.php
    @brief Delete a specific revision of a record.
 */

namespace StructuredDynamics\structwsf\ws\revision\delete;  

use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;

/** RevisionDelete Web Service. Delete a specific revision of a record. It cannot delete a 
    published revision. If a published revision needs to be deleted, then it needs to be 
    updated such that the published stage is remove before being able to delete it.
            
    @author Frederick Giasson, Structured Dynamics LLC.
*/

class RevisionDelete extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** IP of the requester */
  private $requester_ip = "";

  /** Requested IP (ex: a node wants to see all web services or datasets accessible for one of its user) */
  private $registered_ip = "";

  /** Dataset URI where to index the RDF document. Note: this is the Dataset URI, and not the Dataset Revisions URI  */
  private $dataset = "";

  /** URI of the revision to read */
  private $revuri = "";
  
  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/revision/delete/",
                        "_200": {
                          "id": "WS-REVISION-DELETE-200",
                          "level": "Warning",
                          "name": "No Target Dataset URI",
                          "description": "No target dataset URI defined for this request. A target dataset URI is needed for the mode \'ws\' and \'dataset\'"
                        },
                        "_201": {
                          "id": "WS-REVISION-DELETE-201",
                          "level": "Warning",
                          "name": "No Target Revision URI",
                          "description": "No target revision URI defined for this request."
                        },
                        "_300": {
                          "id": "WS-REVISION-DELETE-300",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_301": {
                          "id": "WS-REVISION-DELETE-301",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_302": {
                          "id": "WS-REVISION-DELETE-302",
                          "level": "Fatal",
                          "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
                          "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
                        },
                        "_303": {
                          "id": "WS-REVISION-DELETE-303",
                          "level": "Fatal",
                          "name": "Couldn\'t get the revision",
                          "description": "An error accured when we tried to get the description of the revision."
                        },
                        "_304": {
                          "id": "WS-REVISION-DELETE-304",
                          "level": "Fatal",
                          "name": "Cannot delete a published revision",
                          "description": "It is not possible to delete a revision that is published using this endpoint. You have to unpublish this revision using the Revision: Update web service endpoint, or you can also unpublish it using the CRUD: Delete web service endpoint in \'soft\' mode."
                        },
                        "_305": {
                          "id": "WS-REVISION-DELETE-305",
                          "level": "Fatal",
                          "name": "Cannot delete the revision",
                          "description": "An error occured when we tried to delete the revision from the revisions graph."
                        },
                        "_306": {
                          "id": "WS-REVISION-DELETE-306",
                          "level": "Fatal",
                          "name": "Cannot delete the reification statements of the revision",
                          "description": "An error occured when we tried to delete the reification statements of the revision from the revisions graph."
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

    @param $revuri URI of the revision to delete
    @param $dataset Dataset URI where to index the RDF document. Note: this is the Dataset URI, and not the Dataset Revisions URI.
    @param $registered_ip Target IP address registered in the WSF
    @param $requester_ip IP address of the requester
    @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
    @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                      version of the interface.

    
    @return returns NULL
  
    @author Frederick Giasson, Structured Dynamics LLC.
*/
  function __construct($revuri, $dataset, $registered_ip, $requester_ip, 
                       $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();

    $this->version = "1.0";

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->requester_ip = $requester_ip;
    $this->dataset = $dataset;
    $this->revuri = $revuri;
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["revision_read"];
    }
    else
    {
      $this->interface = $interface;
    }       
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;    
    
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/revision/delete/";
    $this->title = "Revision Delete Web Service";
    $this->crud_usage = new CrudUsage(FALSE, FALSE, FALSE, TRUE);
    $this->endpoint = $this->wsf_base_url . "/ws/revision/delete/";

    $this->dtdURL = "revision/read/revisionDelete.dtd";

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
    // Validation of the "requester_ip" to make sure the system that is sending the query as the rights.
    $ws_av = new AuthValidator($this->requester_ip, $this->dataset, $this->uri);

    $ws_av->pipeline_conneg("text/xml", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
      $this->conneg->getAcceptLanguage());

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

    unset($ws_av);

    // Validation of the "registered_ip" to make sure the user of this system has the rights
    $ws_av = new AuthValidator($this->registered_ip, $this->dataset, $this->uri);

    $ws_av->pipeline_conneg("text/xml", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
      $this->conneg->getAcceptLanguage());

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, RevisionDelete::$supportedSerializations);

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
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/revision/delete/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\revision\delete\interfaces\\'.$class;
      
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