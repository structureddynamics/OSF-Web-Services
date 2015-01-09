<?php

/** @defgroup WsCrud Crud Web Service */
//@{

/*! @file \StructuredDynamics\osf\ws\crud\delete\CrudDelete.php
    @brief Define the Crud Delete web service
 */

namespace StructuredDynamics\osf\ws\crud\delete;

use \StructuredDynamics\osf\ws\framework\CrudUsage;
use \StructuredDynamics\osf\ws\framework\Conneg;
use \StructuredDynamics\osf\ws\dataset\read\DatasetRead;
use \StructuredDynamics\osf\ws\crud\read\CrudRead;

/** CRUD Delete web service. It removes record instances within dataset indexes on different systems (Virtuoso, Solr, etc).

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class CrudDelete extends \StructuredDynamics\osf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /** Dataset where to index the resource*/
  private $dataset;

  /** URI of the resource to delete */
  private $resourceUri;

  /** Mode of the endpoint. Specify if we want to delete, or keep, the revisions related to this record */
  private $mode = 'soft';

  /** Error messages of this web service */
  private $errorMessenger =
    '{
      "ws": "/ws/crud/delete/",
      "_200": {
        "id": "WS-CRUD-DELETE-200",
        "level": "Warning",
        "name": "No resource URI to delete specified",
        "description": "No resource URI has been defined for this query"
      },
      "_201": {
        "id": "WS-CRUD-DELETE-201",
        "level": "Warning",
        "name": "No dataset specified",
        "description": "No dataset URI defined for this query"
      },
      "_300": {
        "id": "WS-CRUD-DELETE-300",
        "level": "Fatal",
        "name": "Can\'t delete the record in the triple store",
        "description": "An error occured when we tried to delete that record in the triple store"
      },
      "_301": {
        "id": "WS-CRUD-DELETE-301",
        "level": "Fatal",
        "name": "Can\'t delete the record in Solr",
        "description": "An error occured when we tried to delete that record in Solr"
      },
      "_302": {
        "id": "WS-CRUD-DELETE-302",
        "level": "Fatal",
        "name": "Can\'t commit changes to the Solr index",
        "description": "An error occured when we tried to commit changes to the Solr index"
      },
      "_304": {
        "id": "WS-CRUD-DELETE-304",
        "level": "Fatal",
        "name": "Requested source interface not existing",
        "description": "The source interface you requested is not existing for this web service endpoint."
      },
      "_305": {
        "id": "WS-CRUD-DELETE-305",
        "level": "Fatal",
        "name": "Requested incompatible Source Interface version",
        "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
      },
      "_306": {
        "id": "WS-CRUD-DELETE-306",
        "level": "Fatal",
        "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
        "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
      },
      "_307": {
        "id": "WS-CRUD-DELETE-307",
        "level": "Fatal",
        "name": "Unknown mode",
        "description": "The mode you specified for this query is unknown. Support modes are: \'soft\' and \'hard\'"
      },
      "_308": {
        "id": "WS-CRUD-DELETE-308",
        "level": "Fatal",
        "name": "Cannot change the record\'s revision status from published to archive",
        "description": "An error occured when we tried to change the record\'s revision status from published to archive."
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
      
      @param $uri URI of the instance record to delete
      @param $dataset URI of the dataset where the instance record is indexed
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($uri, $dataset, $interface='default', $requestedInterfaceVersion='', $mode='soft')
  {
    parent::__construct();
    
    $this->version = "3.0";

    $this->dataset = $dataset;
    $this->resourceUri = $uri;
    $this->mode = strtolower($mode);

    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["crud_delete"];
    }
    else
    {
      $this->interface = $interface;
    }
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;

    $this->uri = $this->wsf_base_url . "/wsf/ws/crud/delete/";
    $this->title = "Crud Delete Web Service";
    $this->crud_usage = new CrudUsage(FALSE, FALSE, FALSE, TRUE);
    $this->endpoint = $this->wsf_base_url . "/ws/crud/delete/";

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct()
  {
    parent::__destruct();
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
      if($this->resourceUri == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
        $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
          $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
          $this->errorMessenger->_200->level);

        return;
      }

      if($this->dataset == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
        $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
          $this->errorMessenger->_201->level);

        return;
      }
      
      if($this->mode != 'soft' &&
         $this->mode != 'hard')
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_307->name);
        $this->conneg->setError($this->errorMessenger->_307->id, $this->errorMessenger->ws,
          $this->errorMessenger->_307->name, $this->errorMessenger->_307->description, "",
          $this->errorMessenger->_307->level);

        return;
      }

      // Check if the dataset is created

      $ws_dr = new DatasetRead($this->dataset); // Here the one that makes the request is the WSF (internal request).

      $ws_dr->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_dr->process();

      if($ws_dr->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($ws_dr->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($ws_dr->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($ws_dr->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($ws_dr->pipeline_getError()->id, $ws_dr->pipeline_getError()->webservice,
          $ws_dr->pipeline_getError()->name, $ws_dr->pipeline_getError()->description,
          $ws_dr->pipeline_getError()->debugInfo, $ws_dr->pipeline_getError()->level);

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
    $this->conneg =
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudDelete::$supportedSerializations);

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

  /** Delete an instance record from all systems that are indexing it 9usually Virtuoso and Solr)

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/crud/delete/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\osf\ws\crud\delete\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_306->name);
          $this->conneg->setError($this->errorMessenger->_306->id, $this->errorMessenger->ws,
            $this->errorMessenger->_306->name, $this->errorMessenger->_306->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_306->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
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
      }      
      
      // Process the code defined in the source interface
      $interface->processInterface();
    }
    else
    { 
      // Interface not existing
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
      $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
        $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_304->level);
    }  
  }
}

//@}

?>