<?php

/** @defgroup WsCrud Crud Web Service */
//@{

/*! @file \StructuredDynamics\osf\ws\crud\read\CrudRead.php
    @brief Define the Crud Read web service
 */

namespace StructuredDynamics\osf\ws\crud\read; 

use \StructuredDynamics\osf\ws\framework\CrudUsage;
use \StructuredDynamics\osf\ws\auth\lister\AuthLister;
use \StructuredDynamics\osf\ws\framework\ProcessorXML;
use \StructuredDynamics\osf\ws\framework\Conneg;
use \StructuredDynamics\osf\framework\Namespaces;

/** CRUD Read web service. It reads instance records description within dataset indexes on different systems (Virtuoso, Solr, etc).

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class CrudRead extends \StructuredDynamics\osf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Include the reference of the resources that links to this resource */
  private $include_linksback = "";

  /** Include potential reification statements */
  private $include_reification = "";

  /** Include attribute/values of the attributes defined in this list */
  private $include_attributes_list = "";

  /** URI of the resource to get its description */
  private $resourceUri = "";

  /** URI of the target dataset. */
  private $dataset = "";

  /** Description of one or multiple datasets */
  private $datasetsDescription = array();

  /** The global datasetis the set of all datasets on an instance. TRUE == we query the global dataset, FALSE we don't. */
  private $globalDataset = FALSE;  
  
  /** Language of the records to return. */
  public $lang = "en";  

  /** Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr");

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", 
           "application/iron+json", "application/iron+csv", "text/*", "*/*");

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/crud/read/",
                        "_200": {
                          "id": "WS-CRUD-READ-200",
                          "level": "Warning",
                          "name": "No URI specified for any resource",
                          "description": "No record URI defined for this query"
                        },
                        "_201": {
                          "id": "WS-CRUD-READ-201",
                          "level": "Warning",
                          "name": "Missing Dataset URIs",
                          "description": "Not all dataset URIs have been defined for each requested record URI. Remember that each URI of the list of URIs have to have a matching dataset URI in the datasets list."
                        },
                        "_202": {
                          "id": "WS-CRUD-READ-202",
                          "level": "Warning",
                          "name": "Record URI(s) not existing or not accessible",
                          "description": "The requested record URI(s) are not existing in this OSF instance, or are not accessible to the requester. This error is only sent when no data URI are defined."
                        },
                        "_300": {
                          "id": "WS-CRUD-READ-300",
                          "level": "Warning",
                          "name": "This resource is not existing",
                          "description": "The target resource to be read is not existing in the system"
                        },
                        "_301": {
                          "id": "WS-CRUD-READ-301",
                          "level": "Warning",
                          "name": "You can\'t read more than 64 resources at once",
                          "description": "You are limited to read maximum 64 resources for each query to the CrudRead web service endpoint"
                        },
                        "_302": {
                          "id": "WS-CRUD-READ-302",
                          "level": "Fatal",
                          "name": "Can\'t get the description of the resource(s)",
                          "description": "An error occured when we tried to get the description of the resource(s)"
                        },  
                        "_303": {
                          "id": "WS-CRUD-READ-303",
                          "level": "Fatal",
                          "name": "Can\'t get the links-to the resource(s)",
                          "description": "An error occured when we tried to get the links-to the resource(s)"
                        },  
                        "_304": {
                          "id": "WS-CRUD-READ-304",
                          "level": "Fatal",
                          "name": "Can\'t get the reification statements for that resource(s)",
                          "description": "An error occured when we tried to get the reification statements of the resource(s)"
                        },
                        "_305": {
                          "id": "WS-CRUD-READ-305",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_306": {
                          "id": "WS-CRUD-READ-306",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_307": {
                          "id": "WS-CRUD-READ-307",
                          "level": "Fatal",
                          "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
                          "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
                        },
                        "_308": {
                          "id": "WS-CRUD-READ-308",
                          "level": "Fatal",
                          "name": "Language not supported by the endpoint",
                          "description": "The language you requested for you query is currently not supported by the endpoint. Please use another one and re-send your query."
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
              
      @param $uri URI of the instance record
      @param $dataset URI of the dataset where the instance record is indexed
      @param $include_linksback One of (1) True ? Means that the reference to the other instance records referring 
                             to the target instance record will be added in the resultset (2) False (default) ? No 
                             links-back will be added 

      @param $include_reification Include possible reification statements for a record
      @param $include_attributes_list A list of attribute URIs to include into the resultset. Sometime, you may 
                                          be dealing with datasets where the description of the entities are composed 
                                          of thousands of attributes/values. Since the Crud: Read web service endpoint 
                                          returns the complete entities descriptions in its resultsets, this parameter 
                                          enables you to restrict the attribute/values you want included in the 
                                          resultset which considerably reduce the size of the resultset to transmit 
                                          and manipulate. Multiple attribute URIs can be added to this parameter by 
                                          splitting them with ";".
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.
      @param $lang Language of the records to be returned by the search endpoint. Only the textual information
                   of the requested language will be returned to the user. If no textual information is available
                   for a record, for a requested language, then only non-textual information will be returned
                   about the record. The default is "en"; however, if the parameter is an empty string, then
                   all the language strings for the record(s) will be returned.                

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($uri, $dataset, $include_linksback, $include_reification, $include_attributes_list="",
                       $interface='default', $requestedInterfaceVersion="", $lang="en")
  {
    parent::__construct();
    
    $this->version = "3.0";

    $this->dataset = $dataset;
    
    if($include_attributes_list != '')
    {
      $this->include_attributes_list = explode(";", $include_attributes_list);
    }

    // If no dataset URI is defined for this query, we simply query all datasets accessible by the requester.
    if($this->dataset == "")
    {
      $this->globalDataset = TRUE;
    }

    $this->resourceUri = $uri;
    
    $this->lang = $lang; 

    $this->include_linksback = $include_linksback;
    $this->include_reification = $include_reification;
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["crud_read"];
    }
    else
    {
      $this->interface = $interface;
    }
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;

    $this->uri = $this->wsf_base_url . "/wsf/ws/crud/read/";
    $this->title = "Crud Read Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/crud/read/";

    $this->dtdURL = "crud/read/crudRead.dtd";

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
    if(array_search($this->lang, $this->supportedLanguages) === FALSE &&
       $this->lang != "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_308->name);
      $this->conneg->setError($this->errorMessenger->_308->id, $this->errorMessenger->ws,
        $this->errorMessenger->_308->name, $this->errorMessenger->_308->description, "",
        $this->errorMessenger->_308->level);

      return;      
    }    

    /*
      Check if dataset(s) URI(s) have been defined for this request. If not, then we query the
      AuthLister web service endpoint to get the list of datasets accessible by this user to see
      if the URI he wants to read is defined in one of these accessible dataset. 
     */
    if($this->globalDataset === TRUE)
    {
      $ws_al = new AuthLister("access_user", "", "");
      
      $ws_al->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_al->process();

      $xml = new ProcessorXML();
      $xml->loadXML($ws_al->pipeline_getResultset());

      $accesses = $xml->getSubjectsByType("wsf:Access");

      $accessibleDatasets = array();

      foreach($accesses as $access)
      {
        $predicates = $xml->getPredicatesByType($access, "wsf:datasetAccess");
        $objects = $xml->getObjects($predicates->item(0));
        $datasetUri = $xml->getURI($objects->item(0));

        $predicates = $xml->getPredicatesByType($access, "wsf:read");
        $objects = $xml->getObjects($predicates->item(0));
        $read = $xml->getContent($objects->item(0));

        if(strtolower($read) == "true")
        {
          $this->dataset .= "$datasetUri;";
          array_push($accessibleDatasets, $datasetUri);
        }
      }

      if(count($accessibleDatasets) <= 0)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
        $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
          $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
          $this->errorMessenger->_202->level);

        return;
      }

      unset($ws_al);

      $this->dataset = rtrim($this->dataset, ";");
    }
    
    $this->validateUserAccess(explode(";", $this->dataset));
    
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
    
    // Check if we have the same number of URIs than Dataset URIs (only if at least one dataset URI is defined).    
    if($this->globalDataset === FALSE)
    {
      $uris = explode(";", $this->resourceUri);
      $datasets = explode(";", $this->dataset);

      if(count($uris) != count($datasets))
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
        $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
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


  /**  @brief Create a resultset in a pipelined mode based on the processed information by the Web service.

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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Crud Read DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudRead::$supportedSerializations);

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
    return($this->serializations());
  }

  /** Get the description of an instance resource from the triple store

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/crud/read/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\osf\ws\crud\read\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_307->name);
          $this->conneg->setError($this->errorMessenger->_307->id, $this->errorMessenger->ws,
            $this->errorMessenger->_307->name, $this->errorMessenger->_307->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_307->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
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
      }
      
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

