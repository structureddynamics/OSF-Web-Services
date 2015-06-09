<?php
/** @defgroup WsFramework Framework for the Web Services */
//@{

/**@file \StructuredDynamics\osf\ws\framework\WebService.php
   @brief An abstract atomic web service class
*/

namespace StructuredDynamics\osf\ws\framework; 

use \StructuredDynamics\osf\ws\framework\Error;
use \StructuredDynamics\osf\framework\Resultset;
use \StructuredDynamics\osf\ws\framework\SparqlQueryHttp; 
use \StructuredDynamics\osf\ws\framework\SparqlQueryOdbc; 

/** A Web Service abstract class. This abstract class is used to define a web service that can interact 
    with external webservices, or web services in a pipeline (compound), in a RESTful way.


    @todo Creating a DTD for creating structured error reports
    @todo Extension of the web service framework to enable the integration of a caching system (like memcached)

    @author Frederick Giasson, Structured Dynamics LLC.
*/
abstract class WebService
{
  /** osf.ini file folder */
  public static $osf_ini = "/usr/share/osf/StructuredDynamics/osf/ws/";

  /** keys.ini file folder */
  public static $keys_ini = "/usr/share/osf/StructuredDynamics/osf/ws/";
                                 
  /** Conneg object that manage the content negotiation capabilities of the web service */
  protected $conneg;  
  
  
  /** Database user name */
  protected $triplestore_username = "";

  /** Database password */
  protected $triplestore_password = "";

  /** Database host */
  protected $triplestore_host = "localhost";

  /** Database DSN */
  protected $triplestore_dsn = "";

  /** DTD URL of the web service */
  protected $dtdBaseURL = "";

  /** SPARQL connection */
  protected $sparql;
  
  /** SPARQL Endpoint */
  protected $sparql_endpoint = "sparql";

  /** SPARQL endpoint communication channel */
  protected $sparql_channel = "odbc";
  
  /** SPARQL 1.1 Graph Store HTTP Protocol Endpoint */
  protected $sparql_graph_endpoint = "sparql-graph-crud-auth";
  
  /** SPARQL Update command to use to insert data into the triplestore */
  protected $sparql_insert = "virtuoso";

  /** The graph where the Web Services Framework description has been indexed */
  protected $wsf_graph = "";

  /** Base URL of the WSF */
  protected $wsf_base_url = "";

  /** Local server path of the WSF files */
  protected $wsf_base_path = "";

  /** The core to use for Solr; "" for no core */
  protected $wsf_solr_core = "";

  /** Path to the ontologies description files (in RDFS and OWL) */
  protected $ontologies_files_folder = "";

  /** Hostname where to send queries to the Solr instance */
  protected $solr_host = "localhost";

  /** Path to the OSF ontological structure */
  protected $ontological_structure_folder = "";

  /** Port number where the triple store server is reachable */
  protected $triplestore_port = "8890";

  /** Port number where the Solr store server is reachable */
  protected $solr_port = "8983";

  /** The HTTP headers received for this query */
  protected $headers = array();

/**   Auto commit handled by the Solr data management systems. If this parameter is true, then this means
 *         Solr will handle the commit operation by itself. If it is false, then the web services will trigger the commit
 *         operations. Usually, Auto-commit should be handled by Solr when the size of the dataset is too big, otherwise
 *         operation such as delete could take much time.      
 */
  protected $solr_auto_commit = FALSE;
  
  /** This is the folder there the file of the index where all the fields defined in Solr
   *          are indexed. You have to make sure that the web server has write access to this folder.
   *          This folder path has to end with a slash "/".
   */
  protected $fields_index_folder = "/tmp/";
  
  /**
  * The list of default interfaces to use for each web service endpoints.
  * These are defined in the netowork.ini configuation file
  */
  protected $default_interfaces = array();

  /** The URI of the Authentication Registrar web service */
  protected $uri;

  /** The Title of the Authentication Registrar web service */
  protected $title;

  /** The CRUD usage of the Authentication Registrar web service */
  protected $crud_usage;

  /** The endpoint of the Authentication Registrar web service */
  protected $endpoint;
  
  /** Number of sessions (threads) to use in parallel */
  protected $owlapiNbSessions;
  
  /** The OWLAPI reasoner to use */
  protected $owlapiReasoner = "pellet";
  
  /** URL where the Java Bridge can be accessed from this server */
  protected $owlapiBridgeURI;
  
  /** Specifies if the OSF instance is geo-enable, which means
             that the geo-Solr index is used, and that geo-related queries
             can be used. */
  protected $geoEnabled = FALSE;
  
  /**  An array of supported languages by the OSF instance. Each of the 
  *    language that appear here have to be properly configured in the Solr schema.
  */
  protected $supportedLanguages = array("en");
  
  /**
  * Exclude a list of properties to be returned by the Search web service endpoint. 
  * All these attributes will be created, updated and returned by Solr, but they won't
  * be returned in the Search web service endpoint resultset.
  */
  protected $searchExcludedAttributes = array();
  
  /** Name of the source interface to use for this web service query */
  protected $interface = "default";

  /** Internal resultset array structure used by all web service endpoints.
      @see http://techwiki.openstructs.org/index.php/Internal_Resultset_Array */
  protected $rset;
  
  /**
  * Determines if the WebService instance is in pipeline mode or if it got directly
  * called as a web service endpoint.
  */
  protected $isInPipelineMode = FALSE;
  
  /** 
  * Version of the Web Service Endpoint API  
  * 
  * The goal of the versioning system is to notice the user of the endpoint if 
  * the behavior of the endpoint changed since the client application was developed 
  * for interacting with it.
  * 
  * The version will increase when the behavior of the endpoint API changes. The
  * behavior may changes if:
  * 
  * (1) A bug get fixed (and at least a behavior changes)
  * (2) The code get refactored (and at least a behavior changes) [impacts]
  * (3) A new parameter/feature is added to the endpoint (but at least another behavior changed) [impacts]
  * (4) An existing parameter/feature got changed [impacts]
  * (5) An existing parameter/feature got removed [impacts]
  * 
  * In this kind of versioning system, versions are not backward compatible. Because
  * the version only changes when core behaviors of existing featuers changes, the
  * versions can't be backward compatible. It is used to track if behaviors changed, 
  * and to notice users if they did since they last implemented the API.
  */
  protected $version;
  
  /** The server's host were to reach the Memcached server */
  protected $memcached_host = 'localhost';
  
  /** The port of the Memcached server */
  protected $memcached_port = '11211';
  
  /** Memcached connection */
  protected $memcached;
  
  /** Determine if Memcached is enabled on this instance */
  protected $memcached_enabled = TRUE;
  
  protected $memcached_auth_validator_expire = 0;
  protected $memcached_auth_lister_expire = 0;
  protected $memcached_crud_read_expire = 0;
  protected $memcached_dataset_read_expire = 0;
  protected $memcached_ontology_read_expire = 0;
  protected $memcached_revision_lister_expire = 0;
  protected $memcached_revision_read_expire = 0;
  protected $memcached_search_expire = 0;
  protected $memcached_sparql_expire = 0;
  
  protected $scones_endpoint = 'http://localhost:8080/scones/';
  
  protected $virtuoso_disable_transaction_log = TRUE;
  
  /** Version of the interface requested by the user */
  protected $requestedInterfaceVersion;
  
  function __construct()
  { 
    $this->headers = array_change_key_case(getallheaders(), CASE_UPPER);

    // Load INI settings
    $osf_ini = parse_ini_file(self::$osf_ini . "osf.ini", TRUE);
    
    // Check if we can read the files
    if($osf_ini === FALSE || $osf_ini === FALSE)
    {
      // Get the web service reference
      $webservice = substr($_SERVER["SCRIPT_NAME"], 0, strrpos($_SERVER["SCRIPT_NAME"], "/") + 1);
      
      // Get the query MIME
      $mimes = array();

      $header = $_SERVER['HTTP_ACCEPT'];

      if(strlen($header) > 0)
      {
        // break up string into pieces (languages and q factors)
        preg_match_all('/([^,]+)/', $header, $accepts);

        foreach($accepts[0] as $accept)
        {
          $foo = explode(";", str_replace(" ", "", $accept));

          if(isset($foo[1]))
          {
            if(stripos($foo[1], "q=") !== FALSE)
            {
              $foo[1] = str_replace("q=", "", $foo[1]);
            }
            else
            {
              $foo[1] = "1";
            }
          }
          else
          {
            array_push($foo, "1");
          }

          $mimes[$foo[0]] = $foo[1];
        }

        // In the case that there is a Accept: header, but that it is empty. We set it to: anything.
        if(count($mimes) <= 0)
        {
          $mimes["*/*"] = 1;
        }

        arsort($mimes, SORT_NUMERIC);
      }

      $errorMime = "";

      foreach($mimes as $mime => $q)
      {
        $mime = strtolower($mime);

        switch($mime)
        {
          case "application/rdf+xml":
          case "text/xml":
          case "application/sparql-results+xml":
          case "application/xhtml+rdfa":
          case "text/html":
            $errorMime = "text/xml";
          break;

          case "application/sparql-results+json":
          case "application/iron+json":
          case "application/json":
          case "application/bib+json":
            $errorMime = "application/json";
          break;
        }

        if($errorMime != "")
        {
          break;
        }
      }
      
      // Create the error object
      $error = new Error("HTTP-500", $webservice, "Error", 
                         "Can't read the osf.ini configuration files on the server.",
                         "", $errorMime, "Fatal");

      // Return the error according to the requested mime.
      header("HTTP/1.1 500 Internal Server Error");
      header("Content-Type: $errorMime");

      echo $error->getError();

      die;      
    }
    
    if(isset($osf_ini["triplestore"]["username"]))
    {
      $this->triplestore_username = $osf_ini["triplestore"]["username"];
    }
    
    if(isset($osf_ini["triplestore"]["password"]))
    {
      $this->triplestore_password = $osf_ini["triplestore"]["password"];
    }
    if(isset($osf_ini["triplestore"]["sparql"]))
    {
      $this->sparql_endpoint = $osf_ini["triplestore"]["sparql"];
    }
    if(isset($osf_ini["triplestore"]["channel"]))
    {
      $this->sparql_channel = strtolower($osf_ini["triplestore"]["channel"]);
    }
    if(isset($osf_ini["triplestore"]["sparql-graph"]))
    {
      $this->sparql_graph_endpoint = $osf_ini["triplestore"]["sparql-graph"];
    }
    if(isset($osf_ini["triplestore"]["sparql-insert"]))
    {
      $this->sparql_insert = strtolower($osf_ini["triplestore"]["sparql-insert"]);
    }
    if(isset($osf_ini["triplestore"]["host"]))
    {
      $this->triplestore_host = $osf_ini["triplestore"]["host"];
    }
    if(isset($osf_ini["triplestore"]["dsn"]))
    {
      $this->triplestore_dsn = $osf_ini["triplestore"]["dsn"];
    }
    
    if(isset($osf_ini["datasets"]["dtd_base"]))
    {
      $this->dtdBaseUrl = $osf_ini["datasets"]["dtd_base"];
    }
    if(isset($osf_ini["datasets"]["wsf_graph"]))
    {
      $this->wsf_graph = $osf_ini["datasets"]["wsf_graph"];
    }
    
    
    if(isset($osf_ini["network"]["wsf_base_url"]))
    {
      $this->wsf_base_url = $osf_ini["network"]["wsf_base_url"];
    }
    if(isset($osf_ini["network"]["wsf_base_path"]))
    {
      $this->wsf_base_path = $osf_ini["network"]["wsf_base_path"];
    }       
    
    if(isset($osf_ini["owlapi"]["nb_sessions"]))
    {
      $this->owlapiNbSessions = $osf_ini["owlapi"]["nb_sessions"];
    } 
    
    if(isset($osf_ini["owlapi"]["bridge_uri"]))
    {
      $this->owlapiBridgeURI = $osf_ini["owlapi"]["bridge_uri"];
    } 

    if(isset($osf_ini["owlapi"]["reasoner"]))
    {
      $this->owlapiReasoner = $osf_ini["owlapi"]["reasoner"];
    } 
    
    if(isset($osf_ini["geo"]["geoenabled"]))
    {
      if(strtolower($osf_ini["geo"]["geoenabled"]) == "true" || $osf_ini["geo"]["geoenabled"] == "1")
      {
        $this->geoEnabled = TRUE;
      }      
    } 
    
    if(isset($osf_ini["search"]["exclude_attributes"]))  
    {
      $this->searchExcludedAttributes = $osf_ini["search"]["exclude_attributes"];
    }

    if(isset($osf_ini["lang"]["supported_languages"]))  
    {
      $this->supportedLanguages = $osf_ini["lang"]["supported_languages"];
    }
    
    if(isset($osf_ini["solr"]["solr_core"]))
    {
      $this->wsf_solr_core = $osf_ini["solr"]["solr_core"];
    }
    
    if(isset($osf_ini["solr"]["solr_host"]))
    {
      $this->solr_host = $osf_ini["solr"]["solr_host"];
    }

    if(isset($osf_ini["ontologies"]["ontologies_files_folder"]))
    {
      $this->ontologies_files_folder = $osf_ini["ontologies"]["ontologies_files_folder"];
    }
    if(isset($osf_ini["ontologies"]["ontological_structure_folder"]))
    {
      $this->ontological_structure_folder = $osf_ini["ontologies"]["ontological_structure_folder"];
    }
    if(isset($osf_ini["triplestore"]["port"]))
    {
      $this->triplestore_port = $osf_ini["triplestore"]["port"];
    }
    if(isset($osf_ini["solr"]["solr_port"]))
    {
      $this->solr_port = $osf_ini["solr"]["solr_port"];
    }
    if(isset($osf_ini["solr"]["fields_index_folder"]))
    {
      $this->fields_index_folder = $osf_ini["solr"]["fields_index_folder"];
    }
    if(strtolower($osf_ini["solr"]["solr_auto_commit"]) == "true" || $osf_ini["solr"]["solr_auto_commit"] == "1")
    {
      $this->solr_auto_commit = TRUE;
    }
    
    if(isset($osf_ini["default-interfaces"]["auth_lister"]))  
    {
      $this->default_interfaces["auth_lister"] = $osf_ini["default-interfaces"]["auth_lister"];
    }    
    else
    {
      $this->default_interfaces["auth_lister"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["auth_registrar_access"]))  
    {
      $this->default_interfaces["auth_registrar_access"] = $osf_ini["default-interfaces"]["auth_registrar_access"];
    }    
    else
    {
      $this->default_interfaces["auth_registrar_access"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["auth_registrar_ws"]))  
    {
      $this->default_interfaces["auth_registrar_ws"] = $osf_ini["default-interfaces"]["auth_registrar_ws"];
    }    
    else
    {
      $this->default_interfaces["auth_registrar_ws"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["auth_validator"]))  
    {
      $this->default_interfaces["auth_validator"] = $osf_ini["default-interfaces"]["auth_validator"];
    }    
    else
    {
      $this->default_interfaces["auth_validator"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["crud_create"]))  
    {
      $this->default_interfaces["crud_create"] = $osf_ini["default-interfaces"]["crud_create"];
    }    
    else
    {
      $this->default_interfaces["crud_create"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["crud_read"]))  
    {
      $this->default_interfaces["crud_read"] = $osf_ini["default-interfaces"]["crud_read"];
    }    
    else
    {
      $this->default_interfaces["crud_read"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["crud_delete"]))  
    {
      $this->default_interfaces["crud_delete"] = $osf_ini["default-interfaces"]["crud_delete"];
    }    
    else
    {
      $this->default_interfaces["crud_delete"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["crud_update"]))  
    {
      $this->default_interfaces["crud_update"] = $osf_ini["default-interfaces"]["crud_update"];
    }    
    else
    {
      $this->default_interfaces["crud_update"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["dataset_create"]))  
    {
      $this->default_interfaces["dataset_create"] = $osf_ini["default-interfaces"]["dataset_create"];
    }    
    else
    {
      $this->default_interfaces["dataset_create"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["dataset_read"]))  
    {
      $this->default_interfaces["dataset_read"] = $osf_ini["default-interfaces"]["dataset_read"];
    }    
    else
    {
      $this->default_interfaces["dataset_read"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["dataset_update"]))  
    {
      $this->default_interfaces["dataset_update"] = $osf_ini["default-interfaces"]["dataset_update"];
    }    
    else
    {
      $this->default_interfaces["dataset_update"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["dataset_delete"]))  
    {
      $this->default_interfaces["dataset_delete"] = $osf_ini["default-interfaces"]["dataset_delete"];
    }    
    else
    {
      $this->default_interfaces["dataset_delete"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["ontology_create"]))  
    {
      $this->default_interfaces["ontology_create"] = $osf_ini["default-interfaces"]["ontology_create"];
    }    
    else
    {
      $this->default_interfaces["ontology_create"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["ontology_read"]))  
    {
      $this->default_interfaces["ontology_read"] = $osf_ini["default-interfaces"]["ontology_read"];
    }    
    else
    {
      $this->default_interfaces["ontology_read"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["ontology_update"]))  
    {
      $this->default_interfaces["ontology_update"] = $osf_ini["default-interfaces"]["ontology_update"];
    }    
    else
    {
      $this->default_interfaces["ontology_update"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["ontology_delete"]))  
    {
      $this->default_interfaces["ontology_delete"] = $osf_ini["default-interfaces"]["ontology_delete"];
    }    
    else
    {
      $this->default_interfaces["ontology_delete"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["scones"]))  
    {
      $this->default_interfaces["scones"] = $osf_ini["default-interfaces"]["scones"];
    }    
    else
    {
      $this->default_interfaces["scones"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["search"]))  
    {
      $this->default_interfaces["search"] = $osf_ini["default-interfaces"]["search"];
    }    
    else
    {
      $this->default_interfaces["search"] = "DefaultSourceInterface";
    }
    
    if(isset($osf_ini["default-interfaces"]["sparql"]))  
    {
      $this->default_interfaces["sparql"] = $osf_ini["default-interfaces"]["sparql"];
    }    
    else
    {
      $this->default_interfaces["sparql"] = "DefaultSourceInterface";
    }    
    
    if(isset($osf_ini["default-interfaces"]["revision_lister"]))  
    {
      $this->default_interfaces["revision_lister"] = $osf_ini["default-interfaces"]["revision_lister"];
    }    
    else
    {
      $this->default_interfaces["revision_lister"] = "DefaultSourceInterface";
    }   
         
    if(isset($osf_ini["default-interfaces"]["revision_read"]))  
    {
      $this->default_interfaces["revision_read"] = $osf_ini["default-interfaces"]["revision_read"];
    }    
    else
    {
      $this->default_interfaces["revision_read"] = "DefaultSourceInterface";
    }  
         
    if(isset($osf_ini["default-interfaces"]["revision_update"]))  
    {
      $this->default_interfaces["revision_update"] = $osf_ini["default-interfaces"]["revision_update"];
    }    
    else
    {
      $this->default_interfaces["revision_update"] = "DefaultSourceInterface";
    }  
         
    if(isset($osf_ini["default-interfaces"]["revision_delete"]))  
    {
      $this->default_interfaces["revision_delete"] = $osf_ini["default-interfaces"]["revision_delete"];
    }    
    else
    {
      $this->default_interfaces["revision_delete"] = "DefaultSourceInterface";
    }  
         
    if(isset($osf_ini["default-interfaces"]["revision_diff"]))  
    {
      $this->default_interfaces["revision_diff"] = $osf_ini["default-interfaces"]["revision_diff"];
    }    
    else
    {
      $this->default_interfaces["revision_diff"] = "DefaultSourceInterface";
    }  
         
    if(isset($osf_ini["memcached"]["memcached_enabled"]))  
    {
      $this->memcached_enabled = filter_var($osf_ini["memcached"]["memcached_enabled"], FILTER_VALIDATE_BOOLEAN);
    }    
         
    if(isset($osf_ini["memcached"]["memcached_host"]))  
    {
      $this->memcached_host = $osf_ini["memcached"]["memcached_host"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_port"]))  
    {
      $this->memcached_port = $osf_ini["memcached"]["memcached_port"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_auth_validator_expire"]))  
    {
      $this->memcached_auth_validator_expire = $osf_ini["memcached"]["memcached_auth_validator_expire"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_auth_lister_expire"]))  
    {
      $this->memcached_auth_lister_expire = $osf_ini["memcached"]["memcached_auth_lister_expire"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_crud_read_expire"]))  
    {
      $this->memcached_crud_read_expire = $osf_ini["memcached"]["memcached_crud_read_expire"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_dataset_read_expire"]))  
    {
      $this->memcached_dataset_read_expire = $osf_ini["memcached"]["memcached_dataset_read_expire"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_ontology_read_expire"]))  
    {
      $this->memcached_ontology_read_expire = $osf_ini["memcached"]["memcached_ontology_read_expire"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_revision_lister_expire"]))  
    {
      $this->memcached_revision_lister_expire = $osf_ini["memcached"]["memcached_revision_lister_expire"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_revision_read_expire"]))  
    {
      $this->memcached_revision_read_expire = $osf_ini["memcached"]["memcached_revision_read_expire"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_search_expire"]))  
    {
      $this->memcached_search_expire = $osf_ini["memcached"]["memcached_search_expire"];
    }    
         
    if(isset($osf_ini["memcached"]["memcached_sparql_expire"]))  
    {
      $this->memcached_sparql_expire = $osf_ini["memcached"]["memcached_sparql_expire"];
    }    
    
    if(isset($osf_ini["scones"]["scones_endpoint"]))  
    {
      $this->scones_endpoint = $osf_ini["scones"]["scones_endpoint"];
    }    
    
    if(isset($osf_ini["triplestore"]["virtuoso-disable-transaction-log"]))  
    {
      $this->virtuoso_disable_transaction_log = filter_var($osf_ini["triplestore"]["virtuoso-disable-transaction-log"], FILTER_VALIDATE_BOOLEAN);
    }      

    // This handler is defined for the fatal script errors. If a script can't be finished because a fatal error
    // occured, then the handleFatalPhpError function is used to return the error message to the requester.
    register_shutdown_function('StructuredDynamics\osf\ws\framework\handleFatalPhpError');
    
    $this->rset = new Resultset($this->wsf_base_path); 
    
    // Connect to memcached
    if($this->memcached_enabled)
    {
      $this->memcached = new \Memcache;
      $this->memcached->connect($this->memcached_host, $this->memcached_port);
      $this->memcached->setCompressThreshold(50000); // 50k 
    }

    switch($this->sparql_channel)
    {
      case 'http':
        $this->sparql = new SparqlQueryHttp($this);   
      break;
      
      case 'odbc':
      default:
        $this->sparql = new SparqlQueryOdbc($this);   
      break;
    }
  }

  function __destruct() { unset($this->sparql); }


/** does the content negotiation for the queries that come from the Web (when this class acts as a Web Service)

    @param $accept Accepted mime types (HTTP header)
    
    @param $accept_charset Accepted charsets (HTTP header)
    
    @param $accept_encoding Accepted encodings (HTTP header)

    @param $accept_language Accepted languages (HTTP header)

    @author Frederick Giasson, Structured Dynamics LLC.
*/
  abstract public function ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language);

  /** Output the content generated by the class in some serialization format

      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  abstract public function ws_serialize();
 
  /** Core web service serializations supported by all OSF web service
             endpoints. This function is normally called within each web service
             function: ws_serialize(). Additionally, ws_serialize() can add more
             serializations to these core ones depending on the needs of the 
             endpoint.

      @return returns serialized content of the requested mime.
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  protected function serializations()
  {      
    switch($this->conneg->getMime())
    {
      case "text/xml":
        return($this->injectDoctype($this->rset->getResultsetXML()));        
      break;
      
      case "application/json":
        return($this->rset->getResultsetJSON());
      break;
      
      case "application/rdf+xml":
        return($this->rset->getResultsetRDFXML());
      break;
      
      case "application/rdf+n3":
        return($this->rset->getResultsetRDFN3());
      break;

      case "application/iron+json":
        return($this->rset->getResultsetIronJSON());
      break;

      case "application/iron+csv":
        return($this->rset->getResultsetIronCOMMON());
      break;
    } 
    
    return("");         
  } 
  

  /** Sends the HTTP response to the requester

      @param $content The content (body) of the response.
      
      @return NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function ws_respond($content)
  {                                                                                             
    // First send the header of the request
    $this->conneg->respond();

    // second, send the content of the request

    // Make sure there is no error.
    if($this->conneg->getStatus() == 200)
    {
      // Make sure the output buffer is empty when we output the result of the 
      // web service endpoint. 
      ob_clean();
      
      if(empty($content))
      {
        switch($this->conneg->getMime())
        {
          case "application/rdf+xml":
          case "application/xhtml+rdfa":
          case "text/rdf+n3":
          case "text/xml":
          case "text/html":
          case "application/sparql-results+xml":
          case "application/rdf+n3":
            echo '<resultset />';
          break;

          case "application/sparql-results+json":
          case "application/json":
          case "application/iron+json":
          case "application/bib+json":
          case "application/rdf+json":
            echo '{"resultset": {}}';
          break;

          case "text/tsv":
          case "text/csv":
          case "application/iron+csv":
          case "application/x-bibtex":
            echo ' ';
          break;          
        }
      }
      else
      {
        echo $content;  
      }      
    }

    $this->__destruct();
  }


  /** Propagate the conneg to the nodes that belong to the current pipeline of web services.

      @param $accept Accepted mime types (HTTP header)
      
      @param $accept_charset Accepted charsets (HTTP header)
      
      @param $accept_encoding Accepted encodings (HTTP header)
  
      @param $accept_language Accepted languages (HTTP header)
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  abstract public function pipeline_conneg($accept, $accept_charset, $accept_encoding, $accept_language);

  /** Returns the response HTTP header status

      @return returns the response HTTP header status
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  abstract public function pipeline_getResponseHeaderStatus();

  /** Returns the error structure

      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  abstract public function pipeline_getError();


  /** Returns the response HTTP header status message

      @return returns the response HTTP header status message
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  abstract public function pipeline_getResponseHeaderStatusMsg();

  /** Returns the response HTTP header status message extension

      @return returns the response HTTP header status message extension
    
      @note The extension of a HTTP status message is
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  abstract public function pipeline_getResponseHeaderStatusMsgExt();


  /**  @brief Create a resultset in a pipelined mode based on the processed information by the Web service.

      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  abstract public function pipeline_getResultset();

  /** Inject the DOCType in a XML document

      @param $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  abstract public function injectDoctype($xmlDoc);

  /** Validate a query to this web service

      @return TRUE if valid; FALSE otherwise
    
      @note Usually, this function sends a query to the Authentication web service in order to be validated.
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  abstract public function validateQuery();
  
  private function securityHash($apiKey, $timeStamp)
  {
    $payload = "";
    
    if($_SERVER['REQUEST_METHOD'] == 'GET')
    {
      foreach($_GET as $key => $value)
      {
        $payload .= "$key=$value&";
      }
      
      $payload = trim($payload, '&');
    }
    elseif($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      foreach($_POST as $key => $value)
      {
        $payload .= "$key=$value&";
      }
      
      $payload = trim($payload, '&');
    }
    
    $md5_payload = base64_encode(md5($payload, true));

    $wsUri = $_SERVER['REQUEST_URI'];
    if(!empty($_SERVER['QUERY_STRING']))
    {
      $wsUri = str_replace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
    }    

    $data = $_SERVER['REQUEST_METHOD'] . $md5_payload . $wsUri . $timeStamp;

    $hash = hash_hmac("sha1", $data, $apiKey, true);
    $hash = base64_encode($hash);
    
    return($hash);
  }

  /** Validate a call to this web service. If the call is validated, then each implementation of a
      web service endpoint will validate other things depending on their specificities using validateQuery().
      
      What this function does is to validate the call using the API Keys shared between the requester
      and this OSF instance.
 
      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  protected function validateCall()
  { 
    $timeStamp = $this->headers['OSF-TS'];
    $appID = $this->headers['OSF-APP-ID'];
    $authorization = $this->headers['AUTHORIZATION'];
    
    // Use the Application ID to get the key of the requester.
    $keys_ini = parse_ini_file(self::$keys_ini . "keys.ini", TRUE);
    
    $apikey = '';

    // Check if we can read the files
    if($keys_ini !== FALSE && isset($keys_ini['keys'][$appID]))
    {
      $apikey = $keys_ini['keys'][$appID];
    }
    else
    {
      $wsUri = $_SERVER['REQUEST_URI'];
      if(!empty($_SERVER['QUERY_STRING']))
      {
        $wsUri = str_replace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
      }
      
      $this->conneg->setStatus(403);
      $this->conneg->setStatusMsg("Forbidden");
      $this->conneg->setStatusMsgExt('Unauthorized Access');
      $this->conneg->setError('WS-AUTH-VALIDATION-100', 
                              $wsUri, 
                              'Unauthorized Request',
                              'Your request cannot be authorized for this web service call',
                              '',
                              'Fatal');
      return;
    }

    $hash = $this->securityHash($apikey, $timeStamp);
    
    if($authorization != $hash)
    {
      $wsUri = $_SERVER['REQUEST_URI'];
      if(!empty($_SERVER['QUERY_STRING']))
      {
        $wsUri = str_replace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
      }
            
      $this->conneg->setStatus(403);
      $this->conneg->setStatusMsg("Forbidden");
      $this->conneg->setStatusMsgExt('Unauthorized Access');
      $this->conneg->setError('WS-AUTH-VALIDATION-101', 
                              $wsUri, 
                              'Unauthorized Request',
                              'Your request cannot be authorized for this web service call',
                              '',
                              'Fatal');
      return;
    }
  }
  
  /**
  * Validate that a user does have access to one or multiple datasets using a specific web service endpoint
  *   
  * @param mixed $datasets An array of datasets that needs to be validated for this web service call
  *                        and the requesting user.
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateUserAccess($datasets)
  {  
    if(!is_array($datasets))
    {
      $datasets = array($datasets);
    }
    
    // At this point, validateCall() validated the call, so there shouldn't be
    // any issues caching the result of this query validation

    if($this->memcached_enabled)
    {
      $key = $this->generateCacheKey('auth-validator', array(
        $this->wsf_graph,
        $this->headers['OSF-USER-URI'],
        $this->uri,
        $this->headers['OSF-APP-ID'],
        implode(' ', $datasets)
      ));
      
      if($return = $this->memcached->get($key))
      {
        return($return);
      }
    }
    
    // Check if the user is already existing
    $this->sparql->query("
      prefix wsf: <http://purl.org/ontology/wsf#> 
      select ?dataset ?create ?read ?update ?delete
      from <". $this->wsf_graph .">
      where
      {                                
        ?group a wsf:Group ;
               wsf:appID \"". $this->headers['OSF-APP-ID'] ."\" .
      
        <". $this->headers['OSF-USER-URI'] ."> wsf:hasGroup ?group .
              
        ?access wsf:datasetAccess ?dataset ;
                wsf:webServiceAccess <". $this->uri ."> ;
                wsf:groupAccess ?group .
                
        filter(?dataset in(<". implode('>, <', $datasets) .">)) .
        
        optional
        {        
          ?access wsf:create ?create ;
                  wsf:read ?read ;
                  wsf:update ?update ;
                  wsf:delete ?delete .
        }
      }");

    if($this->sparql->error())
    {
      $this->conneg->setStatus(500);
      $this->conneg->setStatusMsg("Forbidden");
      $this->conneg->setStatusMsgExt('Internal Error');
      $this->conneg->setError('WS-AUTH-VALIDATION-102', 
                              $this->uri, 
                              'Couldn\'t authorize request',
                              'An internal error occured when we tried to authorize this request',
                              $this->sparql->errormsg(),
                              'Fatal');        

      return;
    }
    else
    {
      $datasetsAccesses = array();
      
      while($this->sparql->fetch_binding())
      {
        $dataset = $this->sparql->value('dataset');
        $create = $this->sparql->value('create');
        $read = $this->sparql->value('read');
        $update = $this->sparql->value('update');
        $delete = $this->sparql->value('delete');
        
        if(!isset($datasetsAccesses[$dataset]))          
        {
          $datasetsAccesses[$dataset] = array();
        }
        
        array_push($datasetsAccesses[$dataset], array('create' => filter_var($create, FILTER_VALIDATE_BOOLEAN),
                                                      'read' => filter_var($read, FILTER_VALIDATE_BOOLEAN),
                                                      'update' => filter_var($update, FILTER_VALIDATE_BOOLEAN),
                                                      'delete' => filter_var($delete, FILTER_VALIDATE_BOOLEAN)));
      }
            
      // Now we want to validate that for all the input datasets, that the user has the right accesses to them
      // using this web service endpoint
      $unauthorized = TRUE;
      
      foreach($datasets as $dataset)
      {   
        if(isset($datasetsAccesses[$dataset]))                               
        {
          foreach($datasetsAccesses[$dataset] as $accessRecord)
          {
            $partialAuthorization = TRUE;
            
            if($this->crud_usage->create)
            {
              if(!$accessRecord['create'])
              {
                $partialAuthorization = FALSE;
              }
            }
            if($this->crud_usage->read && $partialAuthorization)
            {
              if(!$accessRecord['read'])
              {
                $partialAuthorization = FALSE;
              }
            }
            if($this->crud_usage->update && $partialAuthorization)
            {
              if(!$accessRecord['update'])
              {
                $partialAuthorization = FALSE;
              }
            }
            if($this->crud_usage->delete && $partialAuthorization)
            {
              if(!$accessRecord['delete'])
              {
                $partialAuthorization = FALSE;
              }
            }
            
            if($partialAuthorization)
            {
              $unauthorized = FALSE;
              break;
            }
          }
        }

        if(!$unauthorized)
        {
          break;
        }
      }
      
      if($unauthorized)
      {
        $this->conneg->setStatus(403);
        $this->conneg->setStatusMsg("Forbidden");
        $this->conneg->setStatusMsgExt('Unauthorized Access');
        $this->conneg->setError('WS-AUTH-VALIDATION-103', 
                                $this->uri, 
                                'Unauthorized Request',
                                'Your request cannot be authorized for this user: "'.$this->headers['OSF-USER-URI'].'", on this dataset: "'.$dataset.'", using this web service endpoint: "'.$this->uri.'"',
                                '',
                                'Fatal');      
        
        if($this->memcached_enabled)
        {
          $this->memcached->set($key, FALSE, NULL, $this->memcached_auth_validator_expire);
        }
        
        return(FALSE);
      }
    }

    if($this->memcached_enabled)
    {
      $this->memcached->set($key, TRUE, NULL, $this->memcached_auth_validator_expire);
    }
    
    return(TRUE);
  }
        
  public function generateCacheKey($prefix, $vars)
  {
    $values = '';
    
    if(is_array($vars))
    {
      foreach($vars as $var)
      {
        if(is_array($var))
        {
          $values .= implode(' ', $var);
        }
        else
        {
          $values .= ' '.$var;
        }
      }
    }

    // Here we use an iterator to create the final prefix for the key.
    // We will invalidate the prefix simply by incrementing the iterator
    // once we have to remove them (because data changed, etc)
    $ns_key_iterator = $this->memcached->get($prefix);
    
    // if not set, initialize it
    if(empty($ns_key_iterator))
    {
      $ns_key_iterator = 1;
      $this->memcached->set($prefix, $ns_key_iterator, NULL, 0); 
    }
    
    return($prefix.':'.$ns_key_iterator.(!empty($values) ? ':'.md5($values) : ''));    
  }

  /**
  * Invalidate the cache based on a key prefix. What this does is to increment an
  * iterator such that the existing keys become unavailable. Then, eventually,
  * Memcached will clear them out when they will get at the end of the queue.
  *   
  * @param mixed $prefix Prefix to invalidate
  */
  public function invalidateCache($prefix)
  {
    $ns_key_iterator = $this->memcached->get($prefix);
    
    // Only invalidate if this cache is currently used
    if(!empty($ns_key_iterator))
    {    
      $this->memcached->increment($prefix);
    }
  }
  
  /** Encode content to be included in XML files

      @param $string The content string to be encoded
      
      @return returns the encoded string
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function xmlEncode($string)
  { 
    // Replace all the possible entities by their character. That way, we won't "double encode" 
    // these entities. Otherwise, we can endup with things such as "&amp;amp;" which some
    // XML parsers doesn't seem to like (and throws errors).
    $string = str_replace(array ("&amp;", "&lt;", "&gt;"), array ("&", "<", ">"), $string);
    
    return str_replace(array ("&", "<", ">"), array ("&amp;", "&lt;", "&gt;"), $string); 
  }

  /** Encode a string to put in a JSON value
              
      @param $string The string to escape

      @return returns the escaped string
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function jsonEncode($string) { return str_replace(array ('\\', '"', "\n", "\r", "\t"), array ('\\\\', '\\"', " ", " ", "\\t"), $string); }
  

  /** Check if a given IRI is valid.
              
      @param $iri The IRI to validate

      @return returns true if the IRI is valid, false otherwise.
      
      @see http://stackoverflow.com/questions/4713216/what-is-the-rfc-complicant-and-working-regular-expression-to-check-if-a-string-i
  */  
  public function isValidIRI($iri)
  {
    return((bool) preg_match('/^[a-z](?:[-a-z0-9\+\.])*:(?:\/\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:])*@)?(?:\[(?:(?:(?:[0-9a-f]{1,4}:){6}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|::(?:[0-9a-f]{1,4}:){5}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){4}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4}:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){3}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,2}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){2}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,3}[0-9a-f]{1,4})?::[0-9a-f]{1,4}:(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,4}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,5}[0-9a-f]{1,4})?::[0-9a-f]{1,4}|(?:(?:[0-9a-f]{1,4}:){0,6}[0-9a-f]{1,4})?::)|v[0-9a-f]+[-a-z0-9\._~!\$&\'\(\)\*\+,;=:]+)\]|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}|(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=@])*)(?::[0-9]*)?(?:\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))*)*|\/(?:(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))+)(?:\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))*)*)?|(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))+)(?:\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{
40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))*)*|(?!(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@])))(?:\?(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@])|[\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}|\x{100000}-\x{10FFFD}\/\?])*)?(?:\#(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@])|[\/\?])*)?$/iu', $iri));
  }
  
  public function setResultset(\StructuredDynamics\osf\framework\Resultset $resultset)
  {
    $this->rset = $resultset;
  }
  
  public function getResultsetObject()
  {
    return($this->rset);
  }
  
  /**
  * Check if the requester source interface exists for this web service endpoint.
  * 
  * Note that class_exists doesn't work with the auto-loading feature of the framework.
  * It is the reason why we have to proceed that way to check if the source 
  * interface exists or not.  
  * 
  * @param mixed $sourcesInterfacesPath Path where the sources interface files are defined
  *                                     for this web service endpoint
  * 
  * @return Return an empty string if the source interface is not existing. Return
  *         the name of the class if it is existing.
  */
  public function sourceinterface_exists($sourcesInterfacesPath)
  {
    $sourcesInterfacesPath = rtrim($sourcesInterfacesPath, '/')."/";
    
    $fileArray = glob($sourcesInterfacesPath.'*', GLOB_NOSORT);

    $fileNameLowerCase = strtolower($sourcesInterfacesPath.$this->interface.".php");
    
    $class = "";
    
    foreach($fileArray as $file) 
    {
      if(strtolower($file) == $fileNameLowerCase) 
      {
        $class = str_replace(array(".php", $sourcesInterfacesPath), "", $file);
        break;
      }
    }
    
    return($class);
  }
}

/** CRUD usage data structure of a web service
            
    @author Frederick Giasson, Structured Dynamics LLC.
*/

class CrudUsage
{
  /** Create permissions (TRUE or FALSE) */
  public $create;

  /** Read permissions (TRUE or FALSE) */
  public $read;

  /** Update permissions (TRUE or FALSE) */
  public $update;

  /** Delete permissions (TRUE or FALSE) */
  public $delete;

  function __construct($create, $read, $update, $delete)
  {
    $this->create = $create;
    $this->read = $read;
    $this->update = $update;
    $this->delete = $delete;
  }
}

function handleFatalPhpError()
{
  $last_error = error_get_last();

  if($last_error['type'] === E_ERROR || $last_error['type'] === E_CORE_ERROR || $last_error['type'] === E_COMPILE_ERROR
    || $last_error['type'] === E_RECOVERABLE_ERROR || $last_error['type'] === E_USER_ERROR)
  {
    // Check accept parameter
    $mimes = array();

    $header = $_SERVER['HTTP_ACCEPT'];

    if(strlen($header) > 0)
    {
      // break up string into pieces (languages and q factors)
      preg_match_all('/([^,]+)/', $header, $accepts);

      foreach($accepts[0] as $accept)
      {
        $foo = explode(";", str_replace(" ", "", $accept));

        if(isset($foo[1]))
        {
          if(stripos($foo[1], "q=") !== FALSE)
          {
            $foo[1] = str_replace("q=", "", $foo[1]);
          }
          else
          {
            $foo[1] = "1";
          }
        }
        else
        {
          array_push($foo, "1");
        }

        $mimes[$foo[0]] = $foo[1];
      }

      // In the case that there is a Accept: header, but that it is empty. We set it to: anything.
      if(count($mimes) <= 0)
      {
        $mimes["*/*"] = 1;
      }

      arsort($mimes, SORT_NUMERIC);
    }

    $errorMime = "";

    foreach($mimes as $mime => $q)
    {
      $mime = strtolower($mime);

      switch($mime)
      {
        case "application/rdf+xml":
        case "text/xml":
        case "application/sparql-results+xml":
        case "application/xhtml+rdfa":
        case "text/html":
          $errorMime = "text/xml";
        break;

        case "application/sparql-results+json":
        case "application/iron+json":
        case "application/json":
        case "application/bib+json":
          $errorMime = "application/json";
        break;
      }

      if($errorMime != "")
      {
        break;
      }
    }

    // Check what web service returned the error
    $webservice = substr($_SERVER["SCRIPT_NAME"], 0, strrpos($_SERVER["SCRIPT_NAME"], "/") + 1);

    $errorName = "";

    switch($last_error['type'])
    {
      case E_ERROR;
        $errorName = "Error";
      break;

      case E_CORE_ERROR;
        $errorName = "Core Error";
      break;

      case E_COMPILE_ERROR;
        $errorName = "Compile Error";
      break;

      case E_RECOVERABLE_ERROR;
        $errorName = "Recoverable Error";
      break;

      case E_USER_ERROR;
        $errorName = "User Error";
      break;
    }

    // Create the error object
    $error = new Error("HTTP-500", $webservice, $errorName, $last_error['message'],
      "[file]: " . $last_error['file'] . "[line]:" . $last_error['line'], $errorMime, "Fatal");

    // Return the error according to the requested mime.
    header("HTTP/1.1 500 Internal Server Error");
    header("Content-Type: $errorMime");

    echo $error->getError();

    die;
  }
}

//@}

?>