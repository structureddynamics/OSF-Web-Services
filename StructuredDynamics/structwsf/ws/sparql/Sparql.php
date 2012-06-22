<?php

/*! @ingroup WsSparql */
//@{

/*! @file \StructuredDynamics\structwsf\ws\sparql\Sparql.php
    @brief Define the Sparql web service
 */

namespace StructuredDynamics\structwsf\ws\sparql;

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\framework\Subject;
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

  /** IP of the requester */
  private $requester_ip = "";

  /** Limit of the number of results to return in the resultset */
  private $limit = "";

  /** Offset of the "sub-resultset" from the total resultset of the query */
  private $offset = "";

  /** Requested IP */
  private $registered_ip = "";

  /** SPARQL query content resultset */
  private $sparqlContent = "";

  /** Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr");
      
  /** Determine if this is a CONSTRUCT SPARQL query */
  private $isConstructQuery = FALSE;
  
  /** Determine if this is a CONSTRUCT SPARQL query */
  private $isDescribeQuery = FALSE;

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
                        }  
                      }';


  /** Constructor
        
      @param $query SPARQL query to send to the triple store of the WSF
      @param $dataset Dataset URI where to send the query
      @param $limit Limit of the number of results to return in the resultset
      @param $offset Offset of the "sub-resultset" from the total resultset of the query
      @param $registered_ip Target IP address registered in the WSF
      @param $requester_ip IP address of the requester

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($query, $dataset, $limit, $offset, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->query = $query;
    $this->limit = $limit;
    $this->offset = $offset;
    $this->dataset = $dataset;
    $this->requester_ip = $requester_ip;

    if($registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
    }
    else
    {
      $this->registered_ip = $registered_ip;
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
  protected function validateQuery()
  {
    // Validating the access of the dataset specified as input parameter if defined.
    if($this->dataset != "")
    {
      $ws_av = new AuthValidator($this->registered_ip, $this->dataset, $this->uri);

      $ws_av->pipeline_conneg("*/*", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
        $this->conneg->getAcceptLanguage());

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

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
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

  /** Get the namespace of a URI
              
      @param $uri Uri of the resource from which we want the namespace

      @return returns the extracted namespace      
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function getNamespace($uri)
  {
    $pos = strrpos($uri, "#");

    if($pos !== FALSE)
    {
      return array (substr($uri, 0, $pos) . "#", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
    }
    else
    {
      $pos = strrpos($uri, "/");

      if($pos !== FALSE)
      {
        return array (substr($uri, 0, $pos) . "/", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
      }
      else
      {
        $pos = strpos($uri, ":");

        if($pos !== FALSE)
        {
          $nsUri = explode(":", $uri, 2);

          foreach($this->namespaces as $uri2 => $prefix2)
          {
            $uri2 = urldecode($uri2);

            if($prefix2 == $nsUri[0])
            {
              return (array ($uri2, $nsUri[1]));
            }
          }

          return explode(":", $uri, 2);
        }
      }
    }

    return (FALSE);
  }

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
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $ch = curl_init();
      
      // Normalize the query to remove the return carriers and line feeds
      // This is performed to help matching the regular expressions patterns.
      $this->query = str_replace(array("\r", "\n"), " ", $this->query);
      
      // remove the possible starting "sparql"
      $this->query = preg_replace("/^[\s\t]*sparql[\s\t]*/Uim", "", $this->query);
      
      // Check if there is a prolog to this SPARQL query.
      
      // First check if there is a "base" declaration
      
      preg_match("/^[\s\t]*base[\s\t]*<.*>/Uim", $this->query, $matches, PREG_OFFSET_CAPTURE);
      
      $baseOffset = -1;
      if(count($matches) > 0)
      {
        $baseOffset = $matches[0][1] + strlen($matches[0][0]);
      }
      
      // Second check for all possible "prefix" clauses
      preg_match_all("/[\s\t]*prefix[\s\t]*.*:.*<.*>/Uim", $this->query, $matches, PREG_OFFSET_CAPTURE);       

      $lastPrefixOffset = -1;
      
      if(count($matches) > 0)
      {
        $lastPrefixOffset = $matches[0][count($matches[0]) - 1][1] + strlen($matches[0][count($matches[0]) - 1][0]);
      }
      
      $prologEndOffset = -1;
      
      if($lastPrefixOffset > -1)
      {
        $prologEndOffset = $lastPrefixOffset;
      }
      elseif($baseOffset > -1)
      {
        $prologEndOffset = $baseOffset;
      }

      $noPrologQuery = $this->query;
      if($prologEndOffset != -1)
      {
        $noPrologQuery = substr($this->query, $prologEndOffset);
      }
      
      // Now extract prefixes references
      $prefixes = array();
      preg_match_all("/[\s\t]*prefix[\s\t]*(.*):(.*)<(.*)>/Uim", $this->query, $matches, PREG_OFFSET_CAPTURE);       
      
      if(count($matches[0]) > 0)
      {
        for($i = 0; $i < count($matches[1]); $i++)
        {
          $p = str_replace(array(" ", " "), "", $matches[1][$i][0]).":".str_replace(array(" ", " "), "", $matches[2][$i][0]);
          $iri = $matches[3][$i][0];
          
          $prefixes[$p] = $iri;
        }
      }
      
      // Drop any SPARUL queries
      // Reference: http://www.w3.org/Submission/SPARQL-Update/
      if(preg_match_all("/^[\s\t]*modify[\s\t]*/Uim",$noPrologQuery , $matches) > 0 ||
         preg_match_all("/^[\s\t]*delete[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*insert[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*load[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*clear[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*create[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
         preg_match_all("/^[\s\t]*drop[\s\t]*/Uim", $noPrologQuery, $matches) > 0)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
        $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
          $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
          $this->errorMessenger->_203->level);

        return;               
      }

      // Detect any CONSTRUCT clause
      $this->isConstructQuery = FALSE;
      if(preg_match_all("/^[\s\t]*construct[\s\t]*/Uim", $noPrologQuery, $matches) > 0)
      {
        $this->isConstructQuery = TRUE;
        /*
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_204->name);
        $this->conneg->setError($this->errorMessenger->_204->id, $this->errorMessenger->ws,
          $this->errorMessenger->_204->name, $this->errorMessenger->_204->description, "",
          $this->errorMessenger->_204->level);

        return;               
        */
      }
      
      // Drop any SPARQL query with a GRAPH clause which are not bound by one, or a series, of FROM NAMED clauses

      if((preg_match_all("/[\s\t]*graph[\s\t]*</Uim", $noPrologQuery, $matches) > 0 ||
          preg_match_all("/[\s\t]*graph[\s\t]*\?/Uim", $noPrologQuery, $matches) > 0 ||
          preg_match_all("/[\s\t]*graph[\s\t]*\$/Uim", $noPrologQuery, $matches) > 0 ||
          preg_match_all("/[\s\t]*graph[\s\t]*[a-zA-Z0-9\-_]*:/Uim", $noPrologQuery, $matches) > 0) &&
         (preg_match_all("/([\s\t]*from[\s\t]*named[\s\t]*<(.*)>[\s\t]*)/Uim", $noPrologQuery, $matches) <= 0 &&
          preg_match_all("/[\s\t]*(from[\s\t]*named)[\s\t]*([^\s\t<]*):(.*)[\s\t]*/Uim", $noPrologQuery, $matches) <= 0))
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_205->name);
        $this->conneg->setError($this->errorMessenger->_205->id, $this->errorMessenger->ws,
          $this->errorMessenger->_205->name, $this->errorMessenger->_205->description, "",
          $this->errorMessenger->_205->level);

        return;               
      }
      
      $graphs = array();   
      
      // Validate DESCRIBE query.
      // The only thing we have to check here, is to get the graph IRI if the DESCRIBE is immediately using
      // IRIRef clause. Possibilities are:
      // "DESCRIBE <test>" -- IRI_REF
      // "DESCRIBE a:" -- PrefixedName
      
      $this->isDescribeQuery = FALSE;
      if(preg_match("/^[\s\t]*describe[\s\t]*/Uim", $noPrologQuery, $matches) > 0)
      {
        $this->isDescribeQuery = TRUE;
      }    
      
      preg_match_all("/^[\s\t]*describe[\s\t]*<(.*)>/Uim", $noPrologQuery, $matches);  
      
      if(count($matches[0]) > 0)
      {
        array_push($graphs, $matches[1][0]);    
      }
      
      preg_match_all("/^[\s\t]*describe[\s\t]*([^<\s\t]*):(.*)[\s\t]*/Uim", $noPrologQuery, $matches);
      
      if(count($matches[0]) > 0)
      {
        for($i = 0; $i < count($matches[0]); $i++)
        {
          $p = $matches[1][$i].":";
          
          if(isset($prefixes[$p]))
          {
            $d = $prefixes[$p].$matches[2][$i];
            array_push($graphs, $d);
          }
        }
      }       
      
      
      // Get all the "from" and "from named" clauses so that we validate if the user has access to them.

      // Check for the clauses that uses direct IRI_REF
      preg_match_all("/([\s\t]*from[\s\t]*<(.*)>[\s\t]*)/Uim", $noPrologQuery, $matches);

      foreach($matches[2] as $match)
      {
        array_push($graphs, $match);
      }

      preg_match_all("/([\s\t]*from[\s\t]*named[\s\t]*<(.*)>[\s\t]*)/Uim", $noPrologQuery, $matches);

      foreach($matches[2] as $match)
      {
        array_push($graphs, $match);
      }
      
      // Check for the clauses that uses PrefixedName
      
      preg_match_all("/[\s\t]*(from|from[\s\t]*named)[\s\t]*([^\s\t<]*):(.*)[\s\t]*/Uim", $noPrologQuery, $matches);

      if(count($matches[0]) > 0)
      {
        for($i = 0; $i < count($matches[0]); $i++)
        {
          $p = $matches[2][$i].":";
          
          if(isset($prefixes[$p]))
          {
            $d = $prefixes[$p].$matches[3][$i];
            array_push($graphs, $d);
          }
        }
      }   
      
      
      if($this->dataset == "" && count($graphs) <= 0)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
        $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
          $this->errorMessenger->_201->level);

        return;
      }      

      
      // Validate all graphs of the query for the IP of the requester of this query. 
      // If one of the graph is not accessible to the user, we just return
      // an error for this SPARQL query.
      foreach($graphs as $graph)
      {
        if(substr($graph, strlen($graph) - 12, 12) == "reification/")
        {
          $graph = substr($graph, 0, strlen($graph) - 12);
        }

        $ws_av = new AuthValidator($this->requester_ip, $graph, $this->uri);

        $ws_av->pipeline_conneg("*/*", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
          $this->conneg->getAcceptLanguage());

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
      
      /*
        if registered_ip != requester_ip, this means that the query is sent by a registered system
        on the behalf of someone else. In this case, we want to make sure that that system 
        (the one that send the actual query) has access to the same datasets. Otherwise, it means that
        it tries to personificate that registered_ip user.
        
        Validate all graphs of the query. If one of the graph is not accessible to the system, we just return
        and error for this SPARQL query.  
      */
      if(registered_ip != requester_ip)
      {
        foreach($graphs as $graph)
        {
          if(substr($graph, strlen($graph) - 12, 12) == "reification/")
          {
            $graph = substr($graph, 0, strlen($graph) - 12);
          }

          $ws_av = new AuthValidator($this->registered_ip, $graph, $this->uri);

          $ws_av->pipeline_conneg("*/*", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
            $this->conneg->getAcceptLanguage());

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

      // Determine the query format
      $queryFormat = "";

      if($this->conneg->getMime() == "application/sparql-results+json" || 
         $this->conneg->getMime() == "application/sparql-results+xml" || 
         $this->conneg->getMime() == "text/html" ||
         $this->isDescribeQuery === TRUE ||
         $this->isConstructQuery === TRUE)
      {
        $queryFormat = $this->conneg->getMime();
      }
      elseif($this->conneg->getMime() == "text/xml" || 
             $this->conneg->getMime() == "application/json" || 
             $this->conneg->getMime() == "application/rdf+xml" || 
             $this->conneg->getMime() == "application/rdf+n3" ||
             $this->conneg->getMime() == "application/iron+json" ||
             $this->conneg->getMime() == "application/iron+csv")
      {
        $queryFormat = "application/sparql-results+xml";
      }      
      
      // Add a limit to the query

      // Disable limits and offset for now until we figure out what to do (not limit on triples, but resources)
      //      $this->query .= " limit ".$this->limit." offset ".$this->offset;

      curl_setopt($ch, CURLOPT_URL,
        $this->db_host . ":" . $this->triplestore_port . "/sparql?default-graph-uri=" . urlencode($this->dataset) . "&query="
        . urlencode($this->query) . "&format=" . urlencode($queryFormat));

      //curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Accept: " . $queryFormat ));
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, TRUE);

      $xml_data = curl_exec($ch);

      $header = substr($xml_data, 0, strpos($xml_data, "\r\n\r\n"));

      $data =
        substr($xml_data, strpos($xml_data, "\r\n\r\n") + 4, strlen($xml_data) - (strpos($xml_data, "\r\n\r\n") - 4));

      curl_close($ch);

      // check returned message

      $httpMsgNum = substr($header, 9, 3);
      $httpMsg = substr($header, 13, strpos($header, "\r\n") - 13);

      if($httpMsgNum == "200")
      {
        $this->sparqlContent = $data;
      }
      else
      {
        $this->conneg->setStatus($httpMsgNum);
        $this->conneg->setStatusMsg($httpMsg);
        $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
        $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
          $this->errorMessenger->_300 > name, $this->errorMessenger->_300->description, $data,
          $this->errorMessenger->_300->level);

        $this->sparqlContent = "";
        return;
      }

      // If a DESCRIBE query as been requested by the user, then we simply returns what is returned by
      // the triple store. We don't have any convertion to do here.
      if($this->isDescribeQuery === TRUE)
      {
         return;
      }

      // If a CONSTRUCT query as been requested by the user, then we simply returns what is returned by
      // the triple store. We don't have any convertion to do here.
      if($this->isConstructQuery === TRUE)
      {
         return;
      }
      
      if($this->conneg->getMime() == "text/xml" || 
         $this->conneg->getMime() == "application/rdf+n3" || 
         $this->conneg->getMime() == "application/rdf+xml" || 
         $this->conneg->getMime() == "application/json" ||
         $this->conneg->getMime() == "application/iron+json" ||
         $this->conneg->getMime() == "application/iron+csv")
      {
        // Read the XML file and populate the recordInstances variables

        $xml = $this->xml2ary($this->sparqlContent);
     
        if(isset($xml["sparql"]["_c"]["results"]["_c"]["result"]))
        {
          $currentSubjectUri = "";
          $subject = null;
          $sourceDataset = "";
          $isPartOfFound = FALSE;
          $g;

          foreach($xml["sparql"]["_c"]["results"]["_c"]["result"] as $result)
          {
            $s = "";
            $p = "";
            $o = "";
            $g = "";
            
            $valueBoundType = "";

            foreach($result["_c"]["binding"] as $binding)
            {
              $boundVariable = $binding["_a"]["name"];

              $keys = array_keys($binding["_c"]);

              $boundType = $keys[0];
              $boundValue = $binding["_c"][$boundType]["_v"];
              
              switch($boundVariable)
              {
                case "s":
                  $s = $boundValue;
                break;

                case "p":
                  $p = $boundValue;
                  
                  if($p == Namespaces::$dcterms."isPartOf")
                  {
                    $isPartOfFound = TRUE;
                  }
                break;

                case "o":
                  $o = $boundValue;
                  $valueBoundType = $boundType;
                break;

                case "g":
                  $g = $boundValue;
                break;
              }
            }
            
            if($currentSubject != $s)
            {
              if($subject != null)
              {
                if($g != "" && $isPartOfFound === FALSE)
                {
                  $subject->setObjectAttribute(Namespaces::$dcterms."isPartOf", $g);
                  $isPartOfFound = FALSE;
                }
                
                $this->rset->addSubject($subject);
              }
              
              $subject = new Subject($s);
              
              $currentSubject = $s;
            }

            
            // process URI
            if($valueBoundType == "uri" ||
               $valueBoundType == "bnode")
            {
              if($p == Namespaces::$rdf."type")
              {
                $subject->setType($o);
              }
              else
              {
                $subject->setObjectAttribute($p, $o);
              }
            }

            // Process Literal
            if($valueBoundType == "literal")
            {
              $subject->setDataAttribute($p, $o);
            }            
          }
            
          // Add the last subject to the resultset.
          if($subject != null)
          {
            if($g != "" && $isPartOfFound === FALSE)
            {
              $subject->setObjectAttribute(Namespaces::$dcterms."isPartOf", $g);
              $isPartOfFound = FALSE;
            }          
            
            $this->rset->addSubject($subject);          
          }
        }
        
        if(count($this->rset->getResultset()) <= 0)
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
          $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
            $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, "",
            $this->errorMessenger->_301->level);
        }
      }
    }
  }

  /*
      Working with XML. Usage: 
      $xml=xml2ary(file_get_contents('1.xml'));
      $link=&$xml['ddd']['_c'];
      $link['twomore']=$link['onemore'];
      // ins2ary(); // dot not insert a link, and arrays with links inside!
      echo ary2xml($xml);
      
      from: http://mysrc.blogspot.com/2007/02/php-xml-to-array-and-backwards.html
  */

  // XML to Array
  private function xml2ary(&$string)
  {
    $parser = xml_parser_create();
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parse_into_struct($parser, $string, $vals, $index);
    xml_parser_free($parser);

    $mnary = array();
    $ary = &$mnary;

    foreach($vals as $r)
    {
      $t = $r['tag'];

      if($r['type'] == 'open')
      {
        if(isset($ary[$t]))
        {
          if(isset($ary[$t][0]))$ary[$t][] = array();
          else $ary[$t] = array ($ary[$t], array());
          $cv = &$ary[$t][count($ary[$t]) - 1];
        }
        else $cv = &$ary[$t];

        if(isset($r['attributes']))
        {
          foreach($r['attributes'] as $k => $v)$cv['_a'][$k] = $v;
        }
        $cv['_c'] = array();
        $cv['_c']['_p'] = &$ary;
        $ary = &$cv['_c'];
      }
      elseif($r['type'] == 'complete')
      {
        if(isset($ary[$t]))
        { // same as open
          if(isset($ary[$t][0]))$ary[$t][] = array();
          else $ary[$t] = array ($ary[$t], array());
          $cv = &$ary[$t][count($ary[$t]) - 1];
        }
        else $cv = &$ary[$t];

        if(isset($r['attributes']))
        {
          foreach($r['attributes'] as $k => $v)$cv['_a'][$k] = $v;
        }
        $cv['_v'] = (isset($r['value']) ? $r['value'] : '');
      }
      elseif($r['type'] == 'close')
      {
        $ary = &$ary['_p'];
      }
    }

    $this->_del_p($mnary);
    return $mnary;
  }

  // _Internal: Remove recursion in result array
  private function _del_p(&$ary)
  {
    foreach($ary as $k => $v)
    {
      if($k === '_p')unset($ary[$k]);
      elseif(is_array($ary[$k]))$this->_del_p($ary[$k]);
    }
  }

  // Array to XML
  private function ary2xml($cary, $d = 0, $forcetag = '')
  {
    $res = array();

    foreach($cary as $tag => $r)
    {
      if(isset($r[0]))
      {
        $res[] = ary2xml($r, $d, $tag);
      }
      else
      {
        if($forcetag)$tag = $forcetag;
        $sp = str_repeat("\t", $d);
        $res[] = "$sp<$tag";

        if(isset($r['_a']))
        {
          foreach($r['_a'] as $at => $av)$res[] = " $at=\"$av\"";
        }
        $res[] = ">" . ((isset($r['_c'])) ? "\n" : '');

        if(isset($r['_c']))$res[] = ary2xml($r['_c'], $d + 1);
        elseif(isset($r['_v']))$res[] = $r['_v'];
        $res[] = (isset($r['_c']) ? $sp : '') . "</$tag>\n";
      }
    }
    return implode('', $res);
  }

  // Insert element into array
  private function ins2ary(&$ary, $element, $pos)
  {
    $ar1 = array_slice($ary, 0, $pos);
    $ar1[] = $element;
    $ary = array_merge($ar1, array_slice($ary, $pos));
  }
}


//@}

?>