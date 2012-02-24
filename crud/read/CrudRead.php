<?php

/*! @defgroup WsCrud Crud Web Service */
//@{

/*! @file \ws\crud\read\CrudRead.php
   @brief Define the Crud Read web service
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief CRUD Read web service. It reads instance records description within dataset indexes on different systems (Virtuoso, Solr, etc).
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class CrudRead extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Include the reference of the resources that links to this resource */
  private $include_linksback = "";

  /*! @brief Include potential reification statements */
  private $include_reification = "";

  /*! @brief Include attribute/values of the attributes defined in this list */
  private $include_attributes_list = "";

  /*! @brief URI of the resource to get its description */
  private $resourceUri = "";

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";

  /*! @brief URI of the target dataset. */
  private $dataset = "";

  /*! @brief Description of one or multiple datasets */
  private $datasetsDescription = array();

/*! @brief The global datasetis the set of all datasets on an instance. TRUE == we query the global dataset, FALSE we don't. */
  private $globalDataset = FALSE;

  /*! @brief Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr");

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", 
           "application/iron+json", "application/iron+csv", "text/*", "*/*");

  /*! @brief Error messages of this web service */
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
                          "description": "The requested record URI(s) are not existing in this structWSF instance, or are not accessible to the requester. This error is only sent when no data URI are defined."
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
                        }  

                      }';


  /*!   @brief Constructor
       @details   Initialize the Auth Web Service
              
      @param[in] $uri URI of the instance record
      @param[in] $dataset URI of the dataset where the instance record is indexed
      @param[in] $include_linksback One of (1) True ? Means that the reference to the other instance records referring 
                             to the target instance record will be added in the resultset (2) False (default) ? No 
                             links-back will be added 

      @param[in] $include_reification Include possible reification statements for a record
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
      @param[in] $include_attributes_list A list of attribute URIs to include into the resultset. Sometime, you may 
                                          be dealing with datasets where the description of the entities are composed 
                                          of thousands of attributes/values. Since the Crud: Read web service endpoint 
                                          returns the complete entities descriptions in its resultsets, this parameter 
                                          enables you to restrict the attribute/values you want included in the 
                                          resultset which considerably reduce the size of the resultset to transmit 
                                          and manipulate. Multiple attribute URIs can be added to this parameter by 
                                          splitting them with ";".
              
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($uri, $dataset, $include_linksback, $include_reification, $registered_ip, $requester_ip, $include_attributes_list="")
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->dataset = $dataset;
    
    $this->include_attributes_list = explode(";", $include_attributes_list);

    // If no dataset URI is defined for this query, we simply query all datasets accessible by the requester.
    if($this->dataset == "")
    {
      $this->globalDataset = TRUE;
    }

    $this->resourceUri = $uri;

    $this->include_linksback = $include_linksback;
    $this->include_reification = $include_reification;
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

    if(isset($this->db))
    {
      @$this->db->close();
    }
  }

  /*!   @brief Validate a query to this web service
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    /*
      Check if dataset(s) URI(s) have been defined for this request. If not, then we query the
      AuthLister web service endpoint to get the list of datasets accessible by this user to see
      if the URI he wants to read is defined in one of these accessible dataset. 
     */
    if($this->globalDataset === TRUE)
    {
      include_once($this->wsf_base_path."auth/lister/AuthLister.php");

      $ws_al = new AuthLister("access_user", "", $this->registered_ip, $this->wsf_local_ip, "none");

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
    else
    {
      $datasets = explode(";", $this->dataset);

      $datasets = array_unique($datasets);

      // Validate for each requested records of each dataset
      foreach($datasets as $dataset)
      {
        // Validation of the "requester_ip" to make sure the system that is sending the query as the rights.
        $ws_av = new AuthValidator($this->requester_ip, $dataset, $this->uri);

        $ws_av->pipeline_conneg("text/xml", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
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

        unset($ws_av);

        // Validation of the "registered_ip" to make sure the user of this system has the rights
        $ws_av = new AuthValidator($this->registered_ip, $dataset, $this->uri);

        $ws_av->pipeline_conneg("text/xml", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
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
  }

  /*!   @brief Returns the error structure
              
      \n
      
      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getError() { return ($this->conneg->error); }


  /*!  @brief Create a resultset in a pipelined mode based on the processed information by the Web service.
              
      \n
      
      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResultset()
  {
    return($this->injectDoctype($this->rset->getResultsetXML()));        
  }

  /*!   @brief Inject the DOCType in a XML document
              
      \n
      
      @param[in] $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function injectDoctype($xmlDoc)
  {
    $posHeader = strpos($xmlDoc, '"?>') + 3;
    $xmlDoc = substr($xmlDoc, 0, $posHeader)
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Crud Read DTD 0.1//EN\" \"" . $this->dtdBaseURL
        . $this->dtdURL . "\">" . substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);

    return ($xmlDoc);
  }

  /*!   @brief Do content negotiation as an external Web Service
              
      \n
      
      @param[in] $accept Accepted mime types (HTTP header)
      
      @param[in] $accept_charset Accepted charsets (HTTP header)
      
      @param[in] $accept_encoding Accepted encodings (HTTP header)
  
      @param[in] $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
  {
    $this->conneg =
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudRead::$supportedSerializations);

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
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

  /*!   @brief Do content negotiation as an internal, pipelined, Web Service that is part of a Compound Web Service
              
      \n
      
      @param[in] $accept Accepted mime types (HTTP header)
      
      @param[in] $accept_charset Accepted charsets (HTTP header)
      
      @param[in] $accept_encoding Accepted encodings (HTTP header)
  
      @param[in] $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
    { $this->ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language); }

  /*!   @brief Returns the response HTTP header status
              
      \n
      
      @return returns the response HTTP header status
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatus() { return $this->conneg->getStatus(); }

  /*!   @brief Returns the response HTTP header status message
              
      \n
      
      @return returns the response HTTP header status message
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatusMsg() { return $this->conneg->getStatusMsg(); }

  /*!   @brief Returns the response HTTP header status message extension
              
      \n
      
      @return returns the response HTTP header status message extension
    
      @note The extension of a HTTP status message is
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatusMsgExt() { return $this->conneg->getStatusMsgExt(); }

  /*!   @brief Get the namespace of a URI
              
      @param[in] $uri Uri of the resource from which we want the namespace
              
      \n
      
      @return returns the extracted namespace      
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
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

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    return($this->serializations());
  }

  /*!   @brief Get the description of an instance resource from the triple store
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $uris = explode(";", $this->resourceUri);
      $datasets = explode(";", $this->dataset);

      if(count($uris) > 64)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
        $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
          $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, "",
          $this->errorMessenger->_301->level);

        return;
      }
      
      $subjects = array();

      foreach($uris as $key => $u)
      {
        // Decode potentially encoded ";" character.
        $u = str_ireplace("%3B", ";", $u);
        $d = str_ireplace("%3B", ";", $datasets[$key]);

        $query = "";

        $attributesFilter = "";
        
        // If the structWSF instance uses Virtuoso 6, then we use the new FILTER...IN... statement
        // instead of the FILTER...regex. This makes the queries much faster and fix an issue
        // when the Virtuoso instance has been fixed with the LRL (long read length) path
        if($this->virtuoso_main_version != 6)
        {
          foreach($this->include_attributes_list as $attr)
          {
            $attributesFilter .= $attr."|";
          }
          
          $attributesFilter = trim($attributesFilter, "|");
        }
        else
        {
          foreach($this->include_attributes_list as $attr)
          {
            if($attr != "")
            {
              $attributesFilter .= "<$attr>,";
            }
          }
          
          $attributesFilter = trim($attributesFilter, ",");
        }
        
        if($this->globalDataset === FALSE)
        {
          $d = str_ireplace("%3B", ";", $datasets[$key]);

          // Archiving suject triples
          $query = $this->db->build_sparql_query("
            select ?p ?o (DATATYPE(?o)) as ?otype (LANG(?o)) as ?olang 
            from <" . $d . "> 
            where 
            {
              <$u> ?p ?o.
              ".(
                  $this->virtuoso_main_version != 6 ?
                  ($attributesFilter == "" ? "" : "FILTER regex(str(?p), \"($attributesFilter)\")") : 
                  ($attributesFilter == "" ? "" : "FILTER (?p IN($attributesFilter))")
                )."
            }", 
            array ('p', 'o', 'otype', 'olang'), FALSE);
        }
        else
        {
          $d = "";

          foreach($datasets as $dataset)
          {
            if($dataset != "")
            {
              $d .= " from named <$dataset> ";
            }
          }

          // Archiving suject triples
          $query = $this->db->build_sparql_query("
            select ?p ?o (DATATYPE(?o)) as ?otype (LANG(?o)) as ?olang ?g 
            $d 
            where 
            {
              graph ?g
              {
                <$u> ?p ?o.
              }
              ".(
                  $this->virtuoso_main_version != 6 ?
                  ($attributesFilter == "" ? "" : "FILTER regex(str(?p), \"($attributesFilter)\")") : 
                  ($attributesFilter == "" ? "" : "FILTER (?p IN($attributesFilter))")
                )."
            }", 
            array ('p', 'o', 'otype', 'olang', 'g'), FALSE);
               
        }

        $resultset = $this->db->query($query);

        if(odbc_error())
        {
          $this->conneg->setStatus(500);
          $this->conneg->setStatusMsg("Internal Error");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
          $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
            $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
            $this->errorMessenger->_302->level);
        }
        
        $g = "";
        
        $subjects[$u] = Array("type" => Array(),
                              "prefLabel" => "",
                              "altLabel" => Array(),
                              "prefURL" => "",
                              "description" => "");

        $nbTriples = 0;                              
                              
        while(odbc_fetch_row($resultset))
        {
          $p = odbc_result($resultset, 1);
          
          $o = $this->db->odbc_getPossibleLongResult($resultset, 2);

          $otype = odbc_result($resultset, 3);
          $olang = odbc_result($resultset, 4);
                    
          if($g == "")
          {
            if($this->globalDataset === FALSE)
            {
              $g = str_ireplace("%3B", ";", $datasets[$key]);
            }
            else
            {
              $g = odbc_result($resultset, 5);
            }
          }

          $objectType = "";
          
          if($olang && $olang != "")
          {
            /* If a language is defined for an object, we force its type to be xsd:string */
            $objectType = "http://www.w3.org/2001/XMLSchema#string";
          }
          else
          {
            $objectType = $otype;
          }
  
          if($this->globalDataset === TRUE) 
          {
            if($p == Namespaces::$rdf."type")
            {
              array_push($subjects[$u]["type"], $o);
            }
            else
            {
              /** 
              * If we are using the globalDataset, there is a possibility that triples get duplicated
              * if the same triples, exists in two different datasets. It is why we have to filter them there
              * so that we don't duplicate them in the serialized dataset.
              */
              $found = FALSE;
              if(isset($subjects[$u][$p]) && is_array($subjects[$u][$p]))
              {
                foreach($subjects[$u][$p] as $value)
                {
                  if(isset($value["value"]) && $value["value"] == $o)
                  {
                    $found = TRUE;
                    break;
                  }
                  
                  if(isset($value["uri"]) && $value["uri"] == $o)
                  {
                    $found = TRUE;
                    break;
                  }
                }
              }     
              
              if($found === FALSE)
              {     
                if(!isset($subjects[$u][$p]) || !is_array($subjects[$u][$p]))
                {
                  $subjects[$u][$p] = array();
                }
                
                if($objectType !== NULL)
                {
                  array_push($subjects[$u][$p], Array("value" => $o, 
                                                      "lang" => (isset($olang) ? $olang : ""),
                                                      "type" => "rdfs:Literal"));
                                                      
                  $nbTriples++;
                }
                else
                {
                  array_push($subjects[$u][$p], Array("uri" => $o, 
                                                      "type" => ""));
                                                      
                  $nbTriples++;
                }
              }
            }
          }
          else
          {
            if($p == Namespaces::$rdf."type")
            {
              array_push($subjects[$u]["type"], $o);
            }
            else
            {
              if(!isset($subjects[$u][$p]) || !is_array($subjects[$u][$p]))
              {
                $subjects[$u][$p] = array();
              }
              
              if($objectType !== NULL)
              {
                array_push($subjects[$u][$p], Array("value" => $o, 
                                                    "lang" => (isset($olang) ? $olang : ""),
                                                    "type" => "rdfs:Literal"));
                                                      
                $nbTriples++;
              }
              else
              {
                array_push($subjects[$u][$p], Array("uri" => $o, 
                                                    "type" => ""));
                                                      
                $nbTriples++;
              }
            }
          }
        }

        if($nbTriples <= 0)
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
          $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
            $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, "",
            $this->errorMessenger->_300->level);

          return;
        }
        
        // Assigning the Dataset relationship
        if($g != "")
        {         
          if(!isset($subjects[$u]["http://purl.org/dc/terms/isPartOf"]) || !is_array($subjects[$u]["http://purl.org/dc/terms/isPartOf"]))
          {
            $subjects[$u]["http://purl.org/dc/terms/isPartOf"] = array();
          }
          
          array_push($subjects[$u]["http://purl.org/dc/terms/isPartOf"], Array("uri" => $g, 
                                                                               "type" => ""));            
        }        

        // Archiving object triples
        if(strtolower($this->include_linksback) == "true")
        {
          $query = "";

          if($this->globalDataset === FALSE)
          {
            $query = $this->db->build_sparql_query("select ?s ?p from <" . $d . "> where {?s ?p <" . $u . ">.}",
              array ('s', 'p'), FALSE);
          }
          else
          {
            $d = "";

            foreach($datasets as $dataset)
            {
              if($dataset != "")
              {
                $d .= " from named <$dataset> ";
              }
            }

            $query =
              $this->db->build_sparql_query("select ?s ?p $d where {graph ?g{?s ?p <" . $u . ">.}}", array ('s', 'p'),
                FALSE);
          }

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_303 > name);
            $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
              $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, odbc_errormsg(),
              $this->errorMessenger->_303->level);
          }

          while(odbc_fetch_row($resultset))
          {
            $s = odbc_result($resultset, 1);
            $p = odbc_result($resultset, 2);

            if(!isset($subjects[$s]))
            {
              $subjects[$s] = array( "type" => array(),
                                     "prefLabel" => "",
                                     "altLabel" => array(),
                                     "prefURL" => "",
                                     "description" => "");
            }
            
            if(!isset($subjects[$s][$p]))
            {
              $subjects[$s][$p] = array();
            }
            
            array_push($subjects[$s][$p], array("uri" => $u, "type" => ""));            
          }

          unset($resultset); 
        }

        // Get reification triples
        if(strtolower($this->include_reification) == "true")
        {
          $query = "";

          if($this->globalDataset === FALSE)
          {
            $query = "  select ?rei_p ?rei_o ?p ?o from <" . $d . "reification/> 
                    where 
                    {
                      ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <"
              . $u
              . ">.
                      ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> ?rei_p.
                      ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> ?rei_o.
                      ?statement ?p ?o.
                    }";
          }
          else
          {
            $d = "";

            foreach($datasets as $dataset)
            {
              if($dataset != "")
              {
                $d .= " from named <" . $dataset . "reification/> ";
              }
            }

            $query = "  select ?rei_p ?rei_o ?p ?o $d 
                    where 
                    {
                      graph ?g
                      {
                        ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <"
              . $u
                . ">.
                        ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> ?rei_p.
                        ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> ?rei_o.
                        ?statement ?p ?o.
                      }
                    }";
          }

          $query = $this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
            array ('rei_p', 'rei_o', 'p', 'o'), FALSE);

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
            $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
              $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, odbc_errormsg(),
              $this->errorMessenger->_304->level);
          }

          while(odbc_fetch_row($resultset))
          {
            $rei_p = odbc_result($resultset, 1);
            $rei_o = $this->db->odbc_getPossibleLongResult($resultset, 2);
            $p = odbc_result($resultset, 3);
            $o = $this->db->odbc_getPossibleLongResult($resultset, 4);

            if($p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"
              && $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"
              && $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#object"
              && $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
            {
              foreach($subjects[$u][$rei_p] as $key => $value)
              {
                if(isset($value["uri"]) && $value["uri"] == $rei_o)
                {
                  if(!isset($subjects[$u][$rei_p][$key]["reify"]))
                  {
                    $subjects[$u][$rei_p][$key]["reify"] = array();
                  }
                  
                  if(!isset($subjects[$u][$rei_p][$key]["reify"][$p]))
                  {
                    $subjects[$u][$rei_p][$key]["reify"][$p] = array();
                  }
                  
                  array_push($subjects[$u][$rei_p][$key]["reify"][$p], $o);
                }
              }
            }
          }

          unset($resultset);
        }
      }
      
      $this->rset->setResultset($subjects);
    }
  }
}


//@}

?>
