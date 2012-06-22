<?php
/** @defgroup WsFramework Framework for the Web Services */
//@{

/**@file \StructuredDynamics\structwsf\ws\framework\WebService.php
   @brief An abstract atomic web service class
*/

namespace StructuredDynamics\structwsf\ws\framework; 

use \StructuredDynamics\structwsf\ws\framework\Error;
use \StructuredDynamics\structwsf\framework\Resultset;

/** A Web Service abstract class. This abstract class is used to define a web service that can interact 
    with external webservices, or web services in a pipeline (compound), in a RESTful way.


    @todo Creating a DTD for creating structured error reports
    @todo Extension of the web service framework to enable the integration of a caching system (like memcached)

    @author Frederick Giasson, Structured Dynamics LLC.
*/
abstract class WebService
{
  /** data.ini file folder */
  public static $data_ini = "/data/";

  /** network.ini file folder */
  public static $network_ini = "/usr/share/structwsf/";

  /** Main version of the Virtuoso server used by this structWSF instance (4, 5 or 6) */
  protected $virtuoso_main_version = "6";

  /** Enable the Long Read Len feature of Virtuoso. */  
  protected $enable_lrl = FALSE;
    
  /** Conneg object that manage the content negotiation capabilities of the web service */
  protected $conneg;	
	
  /** Database user name */
  protected $db_username = "";

  /** Database password */
  protected $db_password = "";

  /** Database DSN connection */
  protected $db_dsn = "";

  /** Database host */
  protected $db_host = "localhost";

  /** DTD URL of the web service */
  protected $dtdBaseURL = "";

  /** The graph where the Web Services Framework description has been indexed */
  protected $wsf_graph = "";

  /** Base URL of the WSF */
  protected $wsf_base_url = "";

  /** Local server path of the WSF files */
  protected $wsf_base_path = "";

  /** Local server path of the WSF files */
  protected $wsf_local_ip = "";

  /** The core to use for Solr; "" for no core */
  protected $wsf_solr_core = "";

  /** Path to the ontologies description files (in RDFS and OWL) */
  protected $ontologies_files_folder = "";

  /** Hostname where to send queries to the Solr instance */
  protected $solr_host = "localhost";

  /** Path to the structWSF ontological structure */
  protected $ontological_structure_folder = "";
  
  /** Enable the tracking of records changes from the Crud Create web service endpoint */
  protected $track_create = FALSE;

  /** Enable the tracking of records changes from the Crud Update web service endpoint */
  protected $track_update = FALSE;

  /** Enable the tracking of records changes from the Crud Delete web service endpoint */
  protected $track_delete = FALSE;

  /** Specifies a specific WSF tracking web service endpoint URL to access the tracking endpoint. 
             This is useful to put all the record changes tracking on a different, dedicated purposes, 
             WSF server. If this parameter is commented, we will use the wsf_base_url to access the 
             tracking endpoints. If it is uncommented, then we will use the endpoint specified by this
             parameter. */
  protected $tracking_endpoint = "";

  /** Port number where the triple store server is reachable */
  protected $triplestore_port = "8890";

  /** Port number where the Solr store server is reachable */
  protected $solr_port = "8983";

  
  /** Name of the logging table on the Virtuoso instance */
  protected $log_table = "SD.WSF.ws_queries_log";

  /** Determine if the logging capabilities of structWSF are enabled. */
  protected $log_enable = TRUE;

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
  
  /** URL where the Java Bridge can be accessed from this server */
  protected $owlapiBridgeURI;
  
  /** Specifies if the structWSF instance is geo-enable, which means
             that the geo-Solr index is used, and that geo-related queries
             can be used. */
  protected $geoEnabled = FALSE;

  /** Internal resultset array structure used by all web service endpoints.
      @see http://techwiki.openstructs.org/index.php/Internal_Resultset_Array */
  protected $rset;
  
  function __construct()
  {    
    // Load INI settings
    $data_ini = parse_ini_file(self::$data_ini . "data.ini", TRUE);
    $network_ini = parse_ini_file(self::$network_ini . "network.ini", TRUE);
    
    // Check if we can read the files
    if($data_ini === FALSE || $network_ini === FALSE)
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
                         "Can't read the data.ini and/or the network.ini configuration files on the server.",
                         "", $errorMime, "Fatal");

      // Return the error according to the requested mime.
      header("HTTP/1.1 500 Internal Server Error");
      header("Content-Type: $errorMime");

      echo $error->getError();

      die;      
    }
    
    if(isset($data_ini["triplestore"]["username"]))
    {
      $this->db_username = $data_ini["triplestore"]["username"];
    }
    
    if(isset($data_ini["triplestore"]["password"]))
    {
      $this->db_password = $data_ini["triplestore"]["password"];
    }
    if(isset($data_ini["triplestore"]["dsn"]))
    {
      $this->db_dsn = $data_ini["triplestore"]["dsn"];
    }
    if(isset($data_ini["triplestore"]["host"]))
    {
      $this->db_host = $data_ini["triplestore"]["host"];
    }
    if(isset($data_ini["triplestore"]["log_table"]))
    {
      $this->log_table = $data_ini["triplestore"]["log_table"];
    }
    
    if(isset($data_ini["datasets"]["dtd_base"]))
    {
      $this->dtdBaseUrl = $data_ini["datasets"]["dtd_base"];
    }
    if(isset($data_ini["datasets"]["wsf_graph"]))
    {
      $this->wsf_graph = $data_ini["datasets"]["wsf_graph"];
    }
    
    
    if(isset($network_ini["network"]["wsf_base_url"]))
    {
      $this->wsf_base_url = $network_ini["network"]["wsf_base_url"];
    }
    if(isset($network_ini["network"]["wsf_base_path"]))
    {
      $this->wsf_base_path = $network_ini["network"]["wsf_base_path"];
    }
    if(isset($network_ini["network"]["wsf_local_ip"]))
    {
      $this->wsf_local_ip = $network_ini["network"]["wsf_local_ip"];
    }

    if(isset($network_ini["network"]["log_enable"]))
    {
      if(strtolower($network_ini["network"]["log_enable"]) == "true" || $network_ini["network"]["log_enable"] == "1")
      {
        $this->log_enable = TRUE;
      }
      else
      {
        $this->log_enable = FALSE;
      }
    }    
    
    if(isset($network_ini["tracking"]["track_create"]))
    {
      if(strtolower($network_ini["tracking"]["track_create"]) == "true" || $network_ini["tracking"]["track_create"] == "1")
      {
        $this->track_create = TRUE;
      }
    }
    if(isset($network_ini["tracking"]["track_update"]))
    {
      if(strtolower($network_ini["tracking"]["track_update"]) == "true" || $network_ini["tracking"]["track_update"] == "1")
      {
        $this->track_update = TRUE;
      }
    }
    if(isset($network_ini["tracking"]["track_delete"]))
    {
      if(strtolower($network_ini["tracking"]["track_delete"]) == "true" || $network_ini["tracking"]["track_delete"] == "1")
      {
        $this->track_delete = TRUE;
      }
    }
    if(isset($network_ini["tracking"]["tracking_endpoint"]))
    {
      $this->tracking_endpoint = $network_ini["tracking"]["tracking_endpoint"];
    } 
    
    
    if(isset($network_ini["owlapi"]["nb_sessions"]))
    {
      $this->owlapiNbSessions = $network_ini["owlapi"]["nb_sessions"];
    } 
    if(isset($network_ini["owlapi"]["bridge_uri"]))
    {
      $this->owlapiBridgeURI = $network_ini["owlapi"]["bridge_uri"];
    } 

    if(isset($network_ini["geo"]["geoenabled"]))
    {
      if(strtolower($network_ini["geo"]["geoenabled"]) == "true" || $network_ini["geo"]["geoenabled"] == "1")
      {
        $this->geoEnabled = TRUE;
      }      
    }   
    
    
    if(isset($data_ini["solr"]["wsf_solr_core"]))
    {
      $this->wsf_solr_core = $data_ini["solr"]["wsf_solr_core"];
    }
    
    if(isset($data_ini["solr"]["host"]))
    {
      $this->solr_host = $data_ini["solr"]["host"];
    }

    if(isset($data_ini["ontologies"]["ontologies_files_folder"]))
    {
      $this->ontologies_files_folder = $data_ini["ontologies"]["ontologies_files_folder"];
    }
    if(isset($data_ini["ontologies"]["ontological_structure_folder"]))
    {
      $this->ontological_structure_folder = $data_ini["ontologies"]["ontological_structure_folder"];
    }
    if(isset($data_ini["triplestore"]["port"]))
    {
      $this->triplestore_port = $data_ini["triplestore"]["port"];
    }
    if(isset($data_ini["triplestore"]["virtuoso_main_version"]))
    {
      $this->virtuoso_main_version = $data_ini["triplestore"]["virtuoso_main_version"];
    }    
    if(strtolower($data_ini["triplestore"]["enable_lrl"]) == "true" || $data_ini["triplestore"]["enable_lrl"] == "1")
    {
      $this->enable_lrl = TRUE;
    }    
    if(isset($data_ini["solr"]["port"]))
    {
      $this->solr_port = $data_ini["solr"]["port"];
    }
    if(isset($data_ini["solr"]["fields_index_folder"]))
    {
      $this->fields_index_folder = $data_ini["solr"]["fields_index_folder"];
    }
    if(strtolower($data_ini["solr"]["solr_auto_commit"]) == "true" || $data_ini["solr"]["solr_auto_commit"] == "1")
    {
      $this->solr_auto_commit = TRUE;
    }

    // This handler is defined for the fatal script errors. If a script can't be finished because a fatal error
    // occured, then the handleFatalPhpError function is used to return the error message to the requester.
    register_shutdown_function('StructuredDynamics\structwsf\ws\framework\handleFatalPhpError');
    
    $this->rset = new Resultset($this->wsf_base_path);    
  }

  function __destruct() { }


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
 
  /** Core web service serializations supported by all structWSF web service
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
      echo $content;
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
  abstract protected function validateQuery();

  /** Determine if the logging capabilities of this endpoint are enabled.

      @return returns TRUE if enabled, FALSE otherwise.
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function isLoggingEnabled()
  { 
    return($this->log_enable);
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
    $string = str_replace(array ("%5C", "&amp;", "&lt;", "&gt;"), array ("\\", "&", "<", ">"), $string);
    
    return str_replace(array ("\\", "&", "<", ">"), array ("%5C", "&amp;", "&lt;", "&gt;"), $string); 
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
    return((bool) preg_match('/^[a-z](?:[-a-z0-9\+\.])*:(?:\/\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:])*@)?(?:\[(?:(?:(?:[0-9a-f]{1,4}:){6}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|::(?:[0-9a-f]{1,4}:){5}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){4}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4}:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){3}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,2}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){2}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,3}[0-9a-f]{1,4})?::[0-9a-f]{1,4}:(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,4}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,5}[0-9a-f]{1,4})?::[0-9a-f]{1,4}|(?:(?:[0-9a-f]{1,4}:){0,6}[0-9a-f]{1,4})?::)|v[0-9a-f]+[-a-z0-9\._~!\$&\'\(\)\*\+,;=:]+)\]|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}|(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=@])*)(?::[0-9]*)?(?:\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))*)*|\/(?:(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))+)(?:\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))*)*)?|(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))+)(?:\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))*)*|(?!(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@])))(?:\?(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@])|[\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}|\x{100000}-\x{10FFFD}\/\?])*)?(?:\#(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@])|[\/\?])*)?$/iu', $iri));
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