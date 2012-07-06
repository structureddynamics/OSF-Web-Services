<?php

/*! @ingroup WsScones */
//@{

/*! @file \StructuredDynamics\structwsf\ws\scones\Scones.php
    @brief Define the Scones web service
 */

namespace StructuredDynamics\structwsf\ws\scones; 

use \SimpleXMLElement;
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\framework\Conneg;

/** Scones Web Service. It tags a corpus of texts with related concepts and named entities.

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class Scones extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Document content to process; or URL of a document accessible on the web to extract/process */
  private $document = "";

  /** Document content's MIME type  */
  private $docmime = "";

  /** Name of the GATE application used to perform the tagging. This name is pre-defined by the 
             administrator of the node. */
  private $application = "";

  /** IP of the requester */
  private $requester_ip = "";

  /** Requested IP */
  private $registered_ip = "";
  
  /** Configuration file of the Scones web service endpoint. */
  private $config_ini;
  
  /** The Scones Java session that is persistend in the servlet container. */
  private $SconesSession;
  
  /** The annotated document by Scones. */
  private $annotatedDocument = "";
  
  /** Supported MIME serializations by this web service */
  public static $supportedSerializations = array ("text/xml", "text/*", "*/xml", "*/*");

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
                        "_201": {
                          "id": "WS-SCONES-201",
                          "level": "Error",
                          "name": "Scones is not configured.",
                          "description": "Ask the system administrator to configure Scones"
                        },
                        "_202": {
                          "id": "WS-SCONES-202",
                          "level": "Error",
                          "name": "Scones is not initialized.",
                          "description": "Ask the system administrator to initialize Scones"
                        },
                        "_203": {
                          "id": "WS-SCONES-203",
                          "level": "Warning",
                          "name": "Scones is being initialized.",
                          "description": "Wait a minute and send your query again"
                        },
                        "_300": {
                          "id": "WS-SCONES-300",
                          "level": "Warning",
                          "name": "Document MIME type not supported.",
                          "description": "The MIME type of the document you feeded to Scones is not currently supported"
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
      @param $registered_ip Target IP address registered in the WSF
      @param $requester_ip IP address of the requester
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($document, $docmime, $application, $registered_ip, $requester_ip, $interface='default')
  {
    parent::__construct();

    $this->document = $document;
    $this->docmime = $docmime;
    $this->application = $application;
    $this->requester_ip = $requester_ip;

    if($registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
    }
    else
    {
      $this->registered_ip = $registered_ip;
    }
    
    if(strtolower($interface) == "default")
    {
      $this->interface = "DefaultSourceInterface";
    }
    else
    {
      $this->interface = $interface;
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/scones/";
    $this->title = "Scones Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/scones/";

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
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery()
  {
    if($this->docmime != "text/plain")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
      $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
        $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, "",
        $this->errorMessenger->_300->level);

      return;
    }
    
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

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      // Validate query
      $this->validateQuery();
    }
    
    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      /*
        Get the pool of stories to process
        Can be a URL or a file reference.
      */
      $this->config_ini = parse_ini_file("config.ini", TRUE);   
            
      // Make sure the service if configured
      if($this->config_ini === FALSE)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
        $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
          $this->errorMessenger->_201->level);

        return;        
      }

      // Starts the GATE process/bridge  
      require_once($this->config_ini["gate"]["gateBridgeURI"]);
      
      // Create a Scones session where we will save the Gate objects (started & loaded Gate application).
      // Second param "false" => we re-use the pre-created session without destroying the previous one
      // third param "0" => it nevers timeout.
      $this->SconesSession = java_session($this->config_ini["gate"]["sessionName"], false, 0);   
      
      if(is_null(java_values($this->SconesSession->get("initialized")))) 
      {
        /* 
          If the "initialized" session variable is null, it means that the Scone threads
          are not initialized, and that they is no current in initialization.
        */
        
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
        $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
          $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
          $this->errorMessenger->_202->level);
      }
      
      if(java_values($this->SconesSession->get("initialized")) === FALSE) 
      {
        /* 
          If the "initialized" session variable is FALSE, it means that the Scone threads
          are being initialized.
        */
        
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
        $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
          $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
          $this->errorMessenger->_203->level);
      }      
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
      $class = 'StructuredDynamics\structwsf\ws\scones\interfaces\\'.$class;
      
      $interface = new $class($this);
      
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