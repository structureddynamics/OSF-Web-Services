<?php

/** @defgroup WsCrud Crud Web Service */
//@{
                                                  
/*! @file \StructuredDynamics\osf\ws\crud\create\CrudCreate.php
    @brief Define the Crud Create web service
 */

namespace StructuredDynamics\osf\ws\crud\create;

use \StructuredDynamics\osf\ws\framework\CrudUsage;
use \StructuredDynamics\osf\ws\framework\Conneg;
use \StructuredDynamics\osf\ws\dataset\read\DatasetRead;
use \StructuredDynamics\osf\ws\crud\read\CrudRead;
use \StructuredDynamics\osf\framework\Namespaces;

/** CRUD Create web service. It populates dataset indexes on different systems (Virtuoso, Solr, etc).

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class CrudCreate extends \StructuredDynamics\osf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /** Dataset where to index the resource*/
  private $dataset;

/** RDF document where resource(s) to be added are described. Maximum size (by default) is 8M (default php.ini setting). */
  private $document = array();

  /** Mime of the RDF document serialization */
  private $mime = "";

  /** Error messages of this web service */
  private $errorMessenger =
    '{
        "ws": "/ws/crud/create/",
        "_200": {
          "id": "WS-CRUD-CREATE-200",
          "level": "Notice",
          "name": "No RDF document to index",
          "description": "No RDF document has been defined for this query"
        },
        "_201": {
          "id": "WS-CRUD-CREATE-201",
          "level": "Warning",
          "name": "Unknown MIME type for this RDF document",
          "description": "An unknown MIME type has been defined for this RDF document"
        },
        "_202": {
          "id": "WS-CRUD-CREATE-202",
          "level": "Warning",
          "name": "No dataset specified",
          "description": "No dataset URI defined for this query"
        },
        "_301": {
          "id": "WS-CRUD-CREATE-301",
          "level": "Fatal",
          "name": "Can\'t parse RDF document",
          "description": "Can\'t parse the specified RDF document"
        },
        "_302": {
          "id": "WS-CRUD-CREATE-302",
          "level": "Fatal",
          "name": "Syntax error in the RDF document",
          "description": "A syntax error exists in the specified RDF document"
        },
        "_303": {
          "id": "WS-CRUD-CREATE-303",
          "level": "Fatal",
          "name": "Can\'t update the Solr index",
          "description": "An error occured when we tried to update the Solr index"
        },
        "_304": {
          "id": "WS-CRUD-CREATE-304",
          "level": "Fatal",
          "name": "Can\'t commit changes to the Solr index",
          "description": "An error occured when we tried to commit changes to the Solr index"
        },  
        "_307": {
          "id": "WS-CRUD-CREATE-307",
          "level": "Fatal",
          "name": "Requested source interface not existing",
          "description": "The source interface you requested is not existing for this web service endpoint."
        },
        "_308": {
          "id": "WS-CRUD-CREATE-308",
          "level": "Fatal",
          "name": "Requested incompatible Source Interface version",
          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
        },
        "_309": {
          "id": "WS-CRUD-CREATE-309",
          "level": "Fatal",
          "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
          "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
        },
        "_311": {
          "id": "WS-CRUD-CREATE-311",
          "level": "Fatal",
          "name": "Can\'t query the revisions graph",
          "description": "Can\'t read the revisions graph to check if revisions exists for one of the record(s)"
        },
        "_312": {
          "id": "WS-CRUD-CREATE-312",
          "level": "Fatal",
          "name": "A revision exists for one of the record(s)",
          "description": "A revision exists for one of the record(s) that are being created. This web service endpoint cannot be used to re-create a record. That record needs to be updated using CRUD: Update."
        },
        "_313": {
          "id": "WS-CRUD-CREATE-313",
          "level": "Fatal",
          "name": "Can\'t refresh search index for an un-published record",
          "description": "You are trying to refresh the search index using records that are currently unpublished. Only published records can be used to update the search index."
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
        
      @param $document RDF document where instance record(s) are described. The size of this document is limited to 8MB
      @param $mime One of: (1) application/rdf+xml? RDF document serialized in XML (2) application/rdf+n3? RDF document serialized in N3 
      @param $mode One of: (1) full ? Index in both the triple store (Virtuoso) and search index (Solr) (2) triplestore ? Index in the triple store (Virtuoso) only (3) searchindex ? Index in the search index (Solr) only
      @param $dataset Dataset URI where to index the RDF document
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($document, $mime, $mode, $dataset, $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();
    
    $this->version = "3.0";
    
    $this->dataset = $dataset;
    
    if (extension_loaded("mbstring") && mb_detect_encoding($document, "UTF-8", TRUE) != "UTF-8")
    {                   
      $this->document = utf8_encode($document);
    }
    else //we have to assume the input is UTF-8
    {
      $this->document = $document;
    }
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["crud_create"];
    }
    else
    {
      $this->interface = $interface;
    }    
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;
    
    $this->mime = $mime;
    $this->mode = $mode;
    
    $this->uri = $this->wsf_base_url . "/wsf/ws/crud/create/";
    $this->title = "Crud Create Web Service";
    $this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/crud/create/";

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct()
  {
    parent::__destruct();
  }

  /**  @brief Validate a query to this web service

      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery()
  {
    if($this->validateUserAccess($this->dataset))
    {
      // Check for errors
      if($this->document == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
        $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
          $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
          $this->errorMessenger->_200->level);
        return;
      }

      if($this->mime != "application/rdf+xml" && $this->mime != "application/rdf+n3")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
        $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, ($this->mime),
          $this->errorMessenger->_201->level);
        return;
      }

      if($this->dataset == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
        $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
          $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
          $this->errorMessenger->_202->level);
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudCreate::$supportedSerializations);

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

  /** Index the new instance records within all the systems that need it (usually Solr + Virtuoso).

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  { 
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/crud/create/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\osf\ws\crud\create\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_309->name);
          $this->conneg->setError($this->errorMessenger->_309->id, $this->errorMessenger->ws,
            $this->errorMessenger->_309->name, $this->errorMessenger->_309->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_309->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_308->name);
          $this->conneg->setError($this->errorMessenger->_308->id, $this->errorMessenger->ws,
            $this->errorMessenger->_308->name, $this->errorMessenger->_308->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_308->level);  
            
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
      $this->conneg->setStatusMsgExt($this->errorMessenger->_307->name);
      $this->conneg->setError($this->errorMessenger->_307->id, $this->errorMessenger->ws,
        $this->errorMessenger->_307->name, $this->errorMessenger->_307->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_307->level);
    }  
  }
}


//@}

?>