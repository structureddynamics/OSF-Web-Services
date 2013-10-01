<?php

/*! @ingroup WsSparql */
//@{

/*! @file \StructuredDynamics\structwsf\ws\sparql\Sparql.php
    @brief Define the Sparql web service
 */

namespace StructuredDynamics\structwsf\ws\sparql;

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\framework\Namespaces;

/** SPARQL Web Service. It sends SPARQL queries to datasets indexed in the structWSF instance.

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class Sparql extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Sparql query */
  private $query = "";

  /** Dataset where t send the query */
  private $dataset = "";

  /** Limit of the number of results to return in the resultset */
  private $limit = "";

  /** Offset of the "sub-resultset" from the total resultset of the query */
  private $offset = "";

  /** SPARQL query content resultset */
  public $sparqlContent = "";

  /** Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr");
      
  /** Supported MIME serializations by this web service */
  public static $supportedSerializations =
    array ("application/rdf+json", "text/rdf+n3", "application/json", "text/xml", "application/sparql-results+xml", 
           "application/sparql-results+json", "text/html", "application/rdf+xml", "application/rdf+n3", 
           "application/iron+json", "application/iron+csv", "application/*", "text/plain", "text/*", "*/*");

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/sparql/",
                        "_200": {
                          "id": "WS-SPARQL-200",
                          "level": "Warning",
                          "name": "No query specified for this request",
                          "description": "No query specified for this request"
                        },
                        "_201": {
                          "id": "WS-SPARQL-201",
                          "level": "Warning",
                          "name": "No dataset specified for this request",
                          "description": "No dataset specified for this request"
                        },
                        "_202": {
                          "id": "WS-SPARQL-202",
                          "level": "Warning",
                          "name": "The maximum number of records returned within the same slice is 2000. Use multiple queries with the OFFSET parameter to build-up the entire resultset.",
                          "description": "The maximum number of records returned within the same slice is 2000. Use multiple queries with the OFFSET parameter to build-up the entire resultset."
                        },
                        "_203": {
                          "id": "WS-SPARQL-203",
                          "level": "Warning",
                          "name": "SPARUL not permitted.",
                          "description": "No SPARUL queries are permitted for this sparql endpoint."
                        },
                        "_204": {
                          "id": "WS-SPARQL-204",
                          "level": "Warning",
                          "name": "CONSTRUCT not permitted.",
                          "description": "The SPARQL CONSTRUCT clause is not permitted for this sparql endpoint. Please change you mime type if you want to get the resultset in a specific format."
                        },
                        "_205": {
                          "id": "WS-SPARQL-205",
                          "level": "Warning",
                          "name": "GRAPH not permitted without FROM NAMED clauses.",
                          "description": "The SPARQL GRAPH clause is not permitted for this sparql endpoint. GRAPH clauses are only permitted when you bound your SPARQL query using one, or a series of FROM NAMED clauses."
                        },                        
                        "_206": {
                          "id": "WS-SPARQL-206",
                          "level": "Warning",
                          "name": "Dataset not accessible.",
                          "description": "You don\' have access to the dataset URI you specified in the dataset parameter of this query."
                        },                        
                        "_300": {
                          "id": "WS-SPARQL-300",
                          "level": "Warning",
                          "name": "Connection to the sparql endpoint failed",
                          "description": "Connection to the sparql endpoint failed"
                        },
                        "_301": {
                          "id": "WS-SPARQL-301",
                          "level": "Notice",
                          "name": "No instance records found",
                          "description": "No instance records found for this query"
                        },
                        "_302": {
                          "id": "WS-SPARQL-302",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_303": {
                          "id": "WS-SPARQL-303",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_304": {
                          "id": "WS-SPARQL-304",
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
        
      @param $query SPARQL query to send to the triple store of the WSF
      @param $dataset Dataset URI where to send the query
      @param $limit Limit of the number of results to return in the resultset
      @param $offset Offset of the "sub-resultset" from the total resultset of the query
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                                 
      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.

      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($query, $dataset, $limit, $offset, $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();

    $this->version = "1.0";
    
    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->query = $query;
    $this->limit = $limit;
    $this->offset = $offset;
    $this->dataset = $dataset;
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["sparql"];
    }
    else
    {
      $this->interface = $interface;
    }

    $this->requestedInterfaceVersion = $requestedInterfaceVersion;

    $this->uri = $this->wsf_base_url . "/wsf/ws/sparql/";
    $this->title = "Sparql Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/sparql/";

    $this->dtdURL = "sparql/sparql.dtd";

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
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery()
  {
    // Validating the access of the dataset specified as input parameter if defined.
    if($this->dataset != "")
    {
      if(!$this->validateUserAccess($this->dataset))
      {      
        return;
      }      
    }
    
    // Check for errors
    if($this->query == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);

      return;
    }

    if($this->limit > 2000)
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
      $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
        $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
        $this->errorMessenger->_202->level);

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
    return($this->rset->getResultsetXML());
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//SPARQL DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, Sparql::$supportedSerializations);

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
    if($this->conneg->getMime() == "application/sparql-results+xml" ||
       $this->conneg->getMime() == "application/sparql-results+json" ||
       $this->isDescribeQuery === TRUE ||
       $this->isConstructQuery === TRUE)
    {
      return $this->sparqlContent;
    }
    
    return($this->serializations());
  }    

  /** Send the SPARQL query to the triple store of this WSF

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {           
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/sparql/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\sparql\interfaces\\'.$class;
      
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