<?php

/*! @ingroup WsScones */
//@{

/*! @file \StructuredDynamics\osf\ws\scones\Scones.php
    @brief Define the Scones web service
 */

namespace StructuredDynamics\osf\ws\scones; 

use \SimpleXMLElement;
use \StructuredDynamics\osf\ws\framework\CrudUsage;
use \StructuredDynamics\osf\ws\framework\Conneg;

/** Scones Web Service. It tags a corpus of texts with related concepts and named entities.

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class Scones extends \StructuredDynamics\osf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Document content to process; or URL of a document accessible on the web to extract/process */
  private $document = "";

  /** Type of the Scones tagger to use for this query */
  private $type = "";
  
  /** Enable/disable stemming during the tagging process */
  private $stemming = "";

  /** The annotated document by Scones. */
  public $annotatedDocument = "";  
  
  /** Supported MIME serializations by this web service */
  public static $supportedSerializations = array ("application/edn", "application/clojure", "application/*", "*/*", "application/json");

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/scones/",
                        "_200": {
                          "id": "WS-SCONES-200",
                          "level": "Warning",
                          "name": "No documents URI specified for this request",
                          "description": "No documents URI specified for this request"
                        },
                        "_301": {
                          "id": "WS-SCONES-301",
                          "level": "Warning",
                          "name": "Document empty",
                          "description": "The content of the document you defined is empty"
                        },
                        "_302": {
                          "id": "WS-SCONES-302",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_303": {
                          "id": "WS-SCONES-303",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_304": {
                          "id": "WS-SCONES-304",
                          "level": "Fatal",
                          "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
                          "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
                        },
                        "_305": {
                          "id": "WS-SCONES-305",
                          "level": "Fatal",
                          "name": "Unsupported type",
                          "description": "The type of the tagger to use is not supported by the endpoint. Valid types are: \'plain\' and \'noun\'"
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
        
      @param $document Document content (in non-binary form). It can be a valid URL where the document is located on the web.    
      @param $docmime Document content's MIME type
      @param $application Name of the GATE application used to perform the tagging. This name is 
                              pre-defined by the administrator of the node.
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($document, $type, $stemming, $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();
    
    $this->version = "3.0";

    $this->document = $document;
    $this->type = $type;

    $this->stemming = filter_var($stemming, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE));
    if($this->stemming === NULL)
    {
      $this->stemming = FALSE;
    }        
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["scones"];
    }
    else
    {
      $this->interface = $interface;
    }    

    $this->uri = $this->wsf_base_url . "/wsf/ws/scones/";
    $this->title = "Scones Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/scones/";

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct()
  {
    parent::__destruct();
  }

  /** Validate a query to this web service

      @return TRUE if valid; FALSE otherwise
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery()
  {   
    if($this->document == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
      $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
        $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, "",
        $this->errorMessenger->_301->level);

      return;
    }
    
    
    if($this->type != 'plain' && $this->type != 'noun')
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_305->name);
      $this->conneg->setError($this->errorMessenger->_305->id, $this->errorMessenger->ws,
        $this->errorMessenger->_305->name, $this->errorMessenger->_305->description, "",
        $this->errorMessenger->_305->level);

      return;
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
  public function pipeline_getResultset()
  {
    // Returns the annotated GATE XML document
    return($this->annotatedDocument);
  }

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, Scones::$supportedSerializations);

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
  public function ws_serialize()
  {
    return($this->pipeline_getResultset());
  }

  /** Process the document by tagging it using Scones.

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {     
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/scones/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\osf\ws\scones\interfaces\\'.$class;
      
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
      $interface->processInterface();
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
    }
  }
}


//@}

?>