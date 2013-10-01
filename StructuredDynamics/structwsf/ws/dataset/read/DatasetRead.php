<?php

/** @defgroup WsDataset Dataset Management Web Service  */
//@{

/*! @file \StructuredDynamics\structwsf\ws\dataset\read\DatasetRead.php
    @brief Read a graph for this dataset & indexation of its description
 */

namespace StructuredDynamics\structwsf\ws\dataset\read; 

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\framework\Namespaces;

/** Dataset Read Web Service. It reads description of datasets of a structWSF instance

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class DatasetRead extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** URI of the target dataset(s). "all" means all datasets visible to thatuser. */
  private $datasetUri = "";

  /** Add meta information to the resultset */
  private $addMeta = "false";

  /** Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr", "http://rdfs.org/ns/void#" => "void",
      "http://rdfs.org/sioc/ns#" => "sioc", "http://purl.org/dc/terms/" => "dcterms", 
      );


  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", 
           "application/iron+json", "application/iron+csv", "application/*", 
           "text/xml", "text/*", "*/*");

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/dataset/read/",
                        "_200": {
                          "id": "WS-DATASET-READ-200",
                          "level": "Warning",
                          "name": "No unique identifier specified for this dataset",
                          "description": "No URI defined for this new dataset"
                        },
                        "_201": {
                          "id": "WS-DATASET-READ-201",
                          "level": "Warning",
                          "name": "Invalid dataset URI",
                          "description": "The URI of the dataset is not valid."
                        },                          
                        "_300": {
                          "id": "WS-DATASET-READ-300",
                          "level": "Fatal",
                          "name": "Can\'t get the description of any dataset",
                          "description": "An error occured when we tried to get information about all datasets"
                        },
                        "_301": {
                          "id": "WS-DATASET-READ-301",
                          "level": "Fatal",
                          "name": "Can\'t get the description of the target dataset",
                          "description": "An error occured when we tried to get information about the target dataset"
                        },
                        "_302": {
                          "id": "WS-DATASET-READ-302",
                          "level": "Fatal",
                          "name": "Can\'t get meta-information about the dataset(s)",
                          "description": "An error occured when we tried to get meta-information about the dataset(s)"
                        },
                        "_303": {
                          "id": "WS-DATASET-READ-303",
                          "level": "Fatal",
                          "name": "Can\'t get information about the contributors",
                          "description": "An error occured when we tried to get information about the contributors of this dataset"
                        },
                        "_304": {
                          "id": "WS-DATASET-READ-304",
                          "level": "Warning",
                          "name": "This dataset doesn\'t exist in this WSF",
                          "description": "The target dataset doesn\'t exist in this web service framework"
                        },
                        "_305": {
                          "id": "WS-DATASET-READ-305",
                          "level": "Fatal",
                          "name": "Can\'t get meta-information about the dataset(s)",
                          "description": "An error occured when we tried to get meta-information about the dataset(s)"
                        },
                        "_306": {
                          "id": "WS-DATASET-READ-306",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_307": {
                          "id": "WS-DATASET-READ-307",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_308": {
                          "id": "WS-DATASET-READ-308",
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
              
      @param $uri URI of the dataset to read (get its description)
      @param $meta Add meta information with the resultset
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($uri, $meta, $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();
    
    $this->version = "1.0";

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->datasetUri = $uri;
    $this->addMeta = strtolower($meta);

    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["dataset_read"];
    }
    else
    {
      $this->interface = $interface;
    }
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;

    $this->uri = $this->wsf_base_url . "/wsf/ws/dataset/read/";
    $this->title = "Dataset Read Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/dataset/read/";

    $this->dtdURL = "dataset/read/datasetRead.dtd";

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
      
      If a user wants to read information about a dataset on a given structWSF web service endpoint,
      he has to have access to the "http://.../wsf/datasets/" graph with Read privileges, or to have
      Read privileges on the dataset URI itself. If the users doesn't have these permissions, 
      then he won't be able to read the description of the dataset on that instance.
      
      By default, the administrators, and the creator of the dataset, have such an access on a structWSF instance. 
      However a system administrator can choose to make the "http://.../wsf/datasets/" world readable,
      which would mean that anybody could read information about the datasets on the instance.

      @return TRUE if valid; FALSE otherwise
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery()
  {
    if($this->validateUserAccess($this->wsf_graph . "datasets/"))
    {
      if($this->datasetUri == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt("No URI specified for any dataset");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
        $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
          $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
          $this->errorMessenger->_200->level);

        return;
      }
      
      if($this->datasetUri != "all" && !$this->isValidIRI($this->datasetUri))
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Dataset Read DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, DatasetRead::$supportedSerializations);
    
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

  /** Read informationa about a target dataset

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {
     // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/dataset/read/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\dataset\read\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_308->name);
          $this->conneg->setError($this->errorMessenger->_308->id, $this->errorMessenger->ws,
            $this->errorMessenger->_308->name, $this->errorMessenger->_308->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_308->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
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
      }      
      
      // Process the code defined in the source interface
      $interface->processInterface();
    }
    else
    { 
      // Interface not existing
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_306->name);
      $this->conneg->setError($this->errorMessenger->_306->id, $this->errorMessenger->ws,
        $this->errorMessenger->_306->name, $this->errorMessenger->_306->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_306->level);
    }
  }
}
              
//@}

?>