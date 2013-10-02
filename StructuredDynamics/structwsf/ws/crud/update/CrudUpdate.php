<?php

/** @defgroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\crud\update\CrudUpdate.php
    @brief Define the Crud Update web service
 */

namespace StructuredDynamics\structwsf\ws\crud\update; 

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\ws\dataset\read\DatasetRead;
use \StructuredDynamics\structwsf\ws\crud\read\CrudRead;
use \StructuredDynamics\structwsf\framework\WebServiceQuerier;
use \StructuredDynamics\structwsf\framework\Namespaces;

/** CRUD Update web service. It updates instance records descriptions from dataset indexes on different systems (Virtuoso, Solr, etc).

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class CrudUpdate extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

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
  
  /** Publication lifecycle stage of the record */
  private $lifecycle = "published";
  
  /** Specify if we want to create a new revision or not for the updated record */
  private $createRevision = "true";

  /** Mime of the RDF document serialization */
  private $mime = "";

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/crud/update/",
                        "_200": {
                          "id": "WS-CRUD-UPDATE-200",
                          "level": "Warning",
                          "name": "No RDF document to index",
                          "description": "No RDF document defined for this query"
                        },
                        "_201": {
                          "id": "WS-CRUD-UPDATE-201",
                          "level": "Warning",
                          "name": "Unknown MIME type for this RDF document",
                          "description": "Unknown MIME type defined for the target RDF document for this query"
                        },
                        "_202": {
                          "id": "WS-CRUD-UPDATE-202",
                          "level": "Warning",
                          "name": "No dataset specified",
                          "description": "No dataset URI has been defined for this query"
                        },
                        "_300": {
                          "id": "WS-CRUD-UPDATE-300",
                          "level": "Fatal",
                          "name": "Syntax error in the RDF document",
                          "description": "A syntax error has been detected in the RDF document"
                        },
                        "_301": {
                          "id": "WS-CRUD-UPDATE-301",
                          "level": "Fatal",
                          "name": "Can\'t update the record(s) in the triple store",
                          "description": "An error occured when we tried to update the record(s) in the triple store"
                        },
                        "_302": {
                          "id": "WS-CRUD-UPDATE-302",
                          "level": "Fatal",
                          "name": "Can\'t list the record(s) that have to be updated",
                          "description": "An error occured when we tried to list all the record(s) that have to be updated"
                        },
                        "_303": {
                          "id": "WS-CRUD-UPDATE-303",
                          "level": "Fatal",
                          "name": "Can\'t delete the temporary update graph",
                          "description": "An error occured when we tried to delete the temporary update graph"
                        },
                        "_304": {
                          "id": "WS-CRUD-UPDATE-304",
                          "level": "Fatal",
                          "name": "Can\'t update the Solr index",
                          "description": "An error occured when we tried to update the Solr index"
                        },
                        "_305": {
                          "id": "WS-CRUD-UPDATE-305",
                          "level": "Fatal",
                          "name": "Can\'t commit changes to the Solr index",
                          "description": "An error occured when we tried to commit changes to the Solr index"  
                        },  
                        "_307": {
                          "id": "WS-CRUD-UPDATE-307",
                          "level": "Fatal",
                          "name": "Can\'t parse RDF document",
                          "description": "Can\'t parse the specified RDF document"
                        },
                        "_309": {
                          "id": "WS-CRUD-UPDATE-309",
                          "level": "Fatal",
                          "name": "Can\'t parse the classHierarchySerialized.srz file",
                          "description": "We can\'t parse the classHierarchySerialized.srz file. Please do make sure that this file is properly serialized. You can try to fix that issue by re-creating a serialization file from the latest version of the OntologyRead web service endpoint and to replace the result with the current file being used."
                        },
                        "_310": {
                          "id": "WS-CRUD-UPDATE-310",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_311": {
                          "id": "WS-CRUD-UPDATE-311",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_312": {
                          "id": "WS-CRUD-UPDATE-312",
                          "level": "Fatal",
                          "name": "Unknown publication lifecycle stage",
                          "description": "The publication lifecycle stage that has been specified for this query is unknown. Known publication lifecycle stages are: published, archive, experimental, pre_release, staging, harvesting, unspecified"
                        },
                        "_313": {
                          "id": "WS-CRUD-UPDATE-313",
                          "level": "Fatal",
                          "name": "Latest revision of this record is unpublished",
                          "description": "You cannot create a new published revision of a record if a more recent unpublished revision exists for that record. The first thing you have to do is to use the Revision: Update web service endpoint to publish the latest revision and then you will be able to use this web service endpoint to create this new published revision."
                        },
                        "_314": {
                          "id": "WS-CRUD-UPDATE-314",
                          "level": "Fatal",
                          "name": "Can\'t query the revisions graph",
                          "description": "Can\'t read the revisions graph to get the lifecycle stage of the last revision"
                        },
                        "_315": {
                          "id": "WS-CRUD-UPDATE-315",
                          "level": "Fatal",
                          "name": "Can\'t get the description of the initial version of this record",
                          "description": "An error occured when we tried to get the description of the initial version of this record"
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

      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.
  
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($document, $mime, $dataset, $interface='default', 
                       $requestedInterfaceVersion="", $lifecycle = 'published', $revision = 'true')
  {
    parent::__construct();

    $this->version = "3.0";

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->dataset = $dataset;
    $this->mime = $mime;
    $this->lifecycle = strtolower($lifecycle);
    
    $this->createRevision = filter_var($revision, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE));
    if($this->createRevision === NULL)
    {
      $this->createRevision = FALSE;
    }        

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
      $this->interface = $this->default_interfaces["crud_update"];
    }
    else
    {
      $this->interface = $interface;
    }    
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;

    $this->uri = $this->wsf_base_url . "/wsf/ws/crud/update/";
    $this->title = "Crud Update Web Service";
    $this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/crud/update/";

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
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
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
      $ws_dr = new DatasetRead($this->dataset, "false");

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudUpdate::$supportedSerializations);

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
  
  /** Update the information of a given instance record

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/crud/update/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\crud\update\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_311->name);
          $this->conneg->setError($this->errorMessenger->_311->id, $this->errorMessenger->ws,
            $this->errorMessenger->_311->name, $this->errorMessenger->_311->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_311->level);  
            
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
      $this->conneg->setStatusMsgExt($this->errorMessenger->_310->name);
      $this->conneg->setError($this->errorMessenger->_310->id, $this->errorMessenger->ws,
        $this->errorMessenger->_310->name, $this->errorMessenger->_310->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_310->level);
    }
  }
}

//@}

?>