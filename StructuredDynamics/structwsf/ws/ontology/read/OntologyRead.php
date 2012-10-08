<?php

/** @defgroup WsOntologyRead Ontology Read Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\ontology\read\OntologyRead.php
    @brief Define the Ontology Read web service
 */

namespace StructuredDynamics\structwsf\ws\ontology\read; 

use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\framework\Namespaces;

/** Ontology Read web service. It reads different kind of information from the ontological structure
             of the system.

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class OntologyRead extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** URI of the ontology to query */
  private $ontologyUri = "";

  /** The function to use for this query. Different API function calls are embeded in this web service endpoint */
  private $function = "";
  
  /** The parameters of the function to call. These parameters changes depending on the function. All
             parameters are split with a ";" and are encoded */
  private $parameters = array();

  /** IP of the requester */
  private $requester_ip = "";

  /** Requested IP */
  private $registered_ip = "";
  
  public $OwlApiSession = null;

  /**
  * Specify if we want to use the reasonner in this Ontology object.
  * 
  * **Java variable type:** boolean
  */
  private $useReasoner = TRUE;   
  
  /** Language of the annotations to return. */
  public $lang = "en";  

  /** Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr");

  public $getSerialized = "";

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/iron+csv", "application/iron+json", "application/json", "application/rdf+xml",
      "application/rdf+n3", "application/*", "text/csv", "text/xml", "text/*", "*/*");

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/ontology/read/",
                        "_200": {
                          "id": "WS-ONTOLOGY-READ-200",
                          "level": "Warning",
                          "name": "Unknown function call",
                          "description": "The function call being requested is unknown or unsupported by this Ontology Read web service endpoint"
                        },
                        "_201": {
                          "id": "WS-ONTOLOGY-READ-201",
                          "level": "Warning",
                          "name": "Unsupported mode",
                          "description": "Unsupported mode used for this query"
                        },
                        "_202": {
                          "id": "WS-ONTOLOGY-READ-202",
                          "level": "Warning",
                          "name": "URI parameter not provided",
                          "description": "You omited to provide the URI parameter for this query."
                        },
                        "_203": {
                          "id": "WS-ONTOLOGY-READ-203",
                          "level": "Warning",
                          "name": "Unsupported type",
                          "description": "Unsupported type used for this query"
                        },                        
                        "_204": {
                          "id": "WS-ONTOLOGY-READ-204",
                          "level": "Warning",
                          "name": "Property not existing",
                          "description": "The target property URI is not existing in the target ontology."
                        },                        
                        "_205": {
                          "id": "WS-ONTOLOGY-READ-205",
                          "level": "Warning",
                          "name": "Class not existing",
                          "description": "The target class URI is not existing in the target ontology."
                        },                        
                        "_206": {
                          "id": "WS-ONTOLOGY-READ-206",
                          "level": "Warning",
                          "name": "Named individual not existing",
                          "description": "The target named individual URI is not existing in the target ontology."
                        },                        
                        "_300": {
                          "id": "WS-ONTOLOGY-READ-300",
                          "level": "Warning",
                          "name": "Target ontology not loaded",
                          "description": "The target ontology is not loaded into the ontological structure of this instance."
                        },
                        "_301": {
                          "id": "WS-ONTOLOGY-READ-301",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_302": {
                          "id": "WS-ONTOLOGY-READ-302",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_303": {
                          "id": "WS-ONTOLOGY-READ-303",
                          "level": "Fatal",
                          "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
                          "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
                        },
                        "_304": {
                          "id": "WS-ONTOLOGY-READ-304",
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
              
      @param $ontologyUri URI of the ontology where to delete something
      @param $function The function to use for this web service call. Refers you to the documentation to ge the 
                           list of functions and their usage.
      @param $parameters List of parameters for the target function. The parameters are split by a ";" character.
                             The parameter and its value are defined as "param-1=value-1". This tuple has to be
                             encoded. So, the parameters should be constructed that way in the URL:
                             &parameters=urlencode("param-1=value-1");urlencode("param-2=value-2")...
      @param $registered_ip Target IP address registered in the WSF
      @param $requester_ip IP address of the requester
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
  function __construct($ontologyUri, $function, $parameters, $registered_ip, $requester_ip, 
                       $interface='default', $requestedInterfaceVersion="", $lang="en")
  {
    parent::__construct();
    
    $this->version = "1.0";

    $this->ontologyUri = $ontologyUri;
    $this->function = $function;

    $params = explode(";", $parameters);
    
    foreach($params as $param)
    {
      $p = explode("=", $param);
      
      $this->parameters[$p[0]] = urldecode($p[1]);
    }

    $this->requester_ip = $requester_ip;

    if($registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
    }
    else
    {
      $this->registered_ip = $registered_ip;
    }
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["ontology_read"];
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
    
    $this->lang = $lang;

    $this->uri = $this->wsf_base_url . "/wsf/ws/ontology/read/";
    $this->title = "Ontology Read Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/ontology/read/";

    $this->dtdURL = "ontology/read/ontologyRead.dtd";

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
    if($this->lang != "" && array_search($this->lang, $this->supportedLanguages) === FALSE)
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
      $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
        $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, "",
        $this->errorMessenger->_304->level);

      return;      
    }    
    
    // @TODO Validate the OntologyRead queries such that: (1) if the user is requesting something related to a 
    //       specific ontology, we check if it has the rights. If it is requesting a list of available ontologies
    //       we list the ones he has access to. That second validation has to happen in these special functions.
    
    if($this->ontologyUri != "")
    {
      $ws_av = new AuthValidator($this->requester_ip, $this->ontologyUri, $this->uri);

      $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

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

      // If the system send a query on the behalf of another user, we validate that other user as well
      if($this->registered_ip != $this->requester_ip)
      {
        // Validation of the "registered_ip" to make sure the user of this system has the rights
        $ws_av = new AuthValidator($this->registered_ip, $this->ontologyUri, $this->uri);

        $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
          $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Ontology Read DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, OntologyRead::$supportedSerializations);

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      // Check for errors

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
  public function pipeline_serialize() 
  { 
    if($this->getSerialized != "")
    {
      return($this->getSerialized);
    }     
    
    return($this->serializations());    
  }

  /** Serialize the web service answer.

      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function ws_serialize()
  {
    if($this->getSerialized != "")
    {
      return($this->getSerialized);
    }     
    
    return($this->serializations());
  }

  /** Perform the requested action and return the results.

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/ontology/read/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\ontology\read\interfaces\\'.$class;
      
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
          $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
          $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
            $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_303->level);
            
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
      $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
      $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
        $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_301->level);
    }
  }
   
  public function setOwlApiSession($OwlApiSession)
  {
    $this->OwlApiSession = $OwlApiSession;
  }
  
  /**
  * Start using the reasoner for the subsequent OWLOntology functions calls.
  */
  public function useReasoner()
  {
    $this->useReasoner = TRUE;
  }
  
  /**
  * Stop using the reasoner for the subsequent OWLOntology functions calls.
  */
  public function stopUsingReasoner()
  {
    $this->useReasoner = FALSE;
  }
}


//@}

?>
