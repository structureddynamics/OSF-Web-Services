<?php

/*! @defgroup WsOntology Ontology Management Web Service */
//@{

/*! @file \ws\ontology\create\OntologyCreate.php
   @brief Define the Ontology Create web service

  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Ontology Create Web Service. It indexes new ontologies description in the structWSF instance. Re-generate the internal ontological structure of the system.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class OntologyCreate extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /*! @brief IP being registered */
  private $registered_ip = "";

  /*! @brief Ontology RDF document. Maximum size (by default) is 8M (default php.ini setting). */
  private $ontology = array();

  /*! @brief Mime of the Ontology RDF document serialization */
  private $mime = "";

  /*! @brief Additional action that can be performed when adding a new ontology: (1) recreate_inference */
  private $action = "";

  /*! @brief Requester's IP used for request validation */
  private $requester_ip = "";
  
  /*! @brief URI of the inference rules set to use to create the ontological structure. */
  private $rulesSetURI = "";

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/ontology/create/",
                        "_200": {
                          "id": "WS-ONTOLOGY-CREATE-200",
                          "level": "Warning",
                          "name": "No Ontology RDF document to index",
                          "description": "No Ontology RDF document to index"
                        },
                        "_201": {
                          "id": "WS-ONTOLOGY-CREATE-201",
                          "level": "Warning",
                          "name": "Unknown MIME type for this RDF document",
                          "description": "Unknown MIME type for this RDF document"
                        },
                        "_300": {
                          "id": "WS-ONTOLOGY-CREATE-300",
                          "level": "Warning",
                          "name": "Syntax error in the RDF document",
                          "description": "Syntax error in the RDF document"
                        },
                        "_301": {
                          "id": "WS-ONTOLOGY-CREATE-301",
                          "level": "Fatal",
                          "name": "Can\'t clear the inference table",
                          "description": "Can\'t clear the inference table"
                        },
                        "_302": {
                          "id": "WS-ONTOLOGY-CREATE-302",
                          "level": "Fatal",
                          "name": "",
                          "description": ""
                        },
                        "_303": {
                          "id": "WS-ONTOLOGY-CREATE-303",
                          "level": "Fatal",
                          "name": "Can\'t commit changes",
                          "description": "Can\'t commit changes"
                        },
                        "_304": {
                          "id": "WS-ONTOLOGY-CREATE-304",
                          "level": "Fatal",
                          "name": "Can\'t clear the inference graph",
                          "description": "Can\'t clear the inference graph"
                        },
                        "_305": {
                          "id": "WS-ONTOLOGY-CREATE-305",
                          "level": "Fatal",
                          "name": "Can\'t get the list of sub-classes-of all classes",
                          "description": "Can\'t get the list of sub-classes-of all classes"
                        },
                        "_306": {
                          "id": "WS-ONTOLOGY-CREATE-306",
                          "level": "Fatal",
                          "name": "Can\'t get the list of RDFS classes",
                          "description": "Can\'t get the list of RDFS classes"
                        },
                        "_307": {
                          "id": "WS-ONTOLOGY-CREATE-307",
                          "level": "Fatal",
                          "name": "Can\'t get the list of OWL classes",
                          "description": "Can\'t get the list of OWL classes"
                        },
                        "_308": {
                          "id": "WS-ONTOLOGY-CREATE-308",
                          "level": "Fatal",
                          "name": "Can\'t get the list of sub-properties-of all properties",
                          "description": "Can\'t get the list of sub-properties-of all properties"
                        },
                        "_309": {
                          "id": "WS-ONTOLOGY-CREATE-309",
                          "level": "Fatal",
                          "name": "Can\'t get the list of RDFS properties",
                          "description": "Can\'t get the list of RDFS properties"
                        },
                        "_310": {
                          "id": "WS-ONTOLOGY-CREATE-310",
                          "level": "Fatal",
                          "name": "Can\'t get the list of OWL-Object/Datatype properties",
                          "description": "Can\'t get the list of OWL-Object/Datatype properties"
                        },
                        "_311": {
                          "id": "WS-ONTOLOGY-CREATE-311",
                          "level": "Fatal",
                          "name": "Can\'t insert inferred triples",
                          "description": "Can\'t insert inferred triples"
                        },
                        "_312": {
                          "id": "WS-ONTOLOGY-CREATE-312",
                          "level": "Fatal",
                          "name": "Can\'t create graph",
                          "description": "Can\'t create graph"
                        },
                        "_313": {
                          "id": "WS-ONTOLOGY-CREATE-313",
                          "level": "Fatal",
                          "name": "Can\'t create graph",
                          "description": "Can\'t create graph"
                        },
                        "_314": {
                          "id": "WS-ONTOLOGY-CREATE-314",
                          "level": "Fatal",
                          "name": "Can\'t write file",
                          "description": "Can\'t write file"
                        },
                        "_315": {
                          "id": "WS-ONTOLOGY-CREATE-315",
                          "level": "Fatal",
                          "name": "Can\'t open file",
                          "description": "Can\'t open file"
                        },
                        "_316": {
                          "id": "WS-ONTOLOGY-CREATE-316",
                          "level": "Fatal",
                          "name": "Can\'t write file",
                          "description": "Can\'t write file"
                        },
                        "_317": {
                          "id": "WS-ONTOLOGY-CREATE-317",
                          "level": "Fatal",
                          "name": "Can\'t open file",
                          "description": "Can\'t open file"
                        }  
                      }';


  /*!   @brief Constructor
       @details   Initialize the Ontology Create
          
      @param[in] $ontology RDF document describing the ontology. The size of this document is limited to 8MB
      @param[in] $mime One of: (1) application/rdf+xml? RDF document serialized in XML
                    (2) application/rdf+n3? RDF document serialized in N3 

      @param[in] $action (optional).If action = "recreate_inference" then the inference table will be re-created as well
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($ontology, $mime, $action, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->registered_ip = $registered_ip;
    $this->requester_ip = $requester_ip;
    $this->ontology = str_replace("'", "\'", $ontology);
    $this->mime = $mime;
    $this->action = $action;
    
    $this->rulesSetURI = "wsf_inference_rule".ereg_replace("[^A-Za-z0-9]", "", $this->wsf_base_url);

    if($this->registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/ontology/create/";
    $this->title = "Ontology Create Web Service";
    $this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/ontology/create/";

    $this->dtdURL = "auth/OntologyCreate.dtd";

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
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    $ws_av = new AuthValidator($this->requester_ip, $this->wsf_graph . "ontologies/", $this->uri);

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
      $ws_av = new AuthValidator($this->registered_ip, $this->wsf_graph . "ontologies/", $this->uri);

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
  public function pipeline_getResultset() { return ""; }

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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Ontology Create DTD 0.1//EN\" \""
      . $this->dtdBaseURL . $this->dtdURL . "\">" . substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);

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
    $this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language,
      OntologyCreate::$supportedSerializations);

    // Check for errors

    if($this->ontology == "")
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

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize() { return ""; }

  /*!   @brief Non implemented method (only defined)
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize_reification() { return ""; }

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize() { return ""; }

  /*!   @brief Sends the HTTP response to the requester
              
      \n
      
      @param[in] $content The content (body) of the response.
      
      @return NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
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


  /*!   @brief Update all ontological structures used by the WSF
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $this->validateQuery();

      // If the query is still valid
      if($this->conneg->getStatus() == 200)
      {
        // Step #1: load the new ontology
        if($this->mime == "application/rdf+xml")
        {
          $this->db->query("DB.DBA.RDF_LOAD_RDFXML_MT('" . $this->ontology . "', '" . $this->wsf_graph
            . "ontologies/', '" . $this->wsf_graph . "ontologies/')");
        }

        if($this->mime == "application/rdf+n3")
        {
          $this->db->query("DB.DBA.TTLP_MT('" . $this->ontology . "', '" . $this->wsf_graph . "ontologies/', '"
            . $this->wsf_graph . "ontologies/')");
        }

        if(odbc_error())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
          $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
            $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, odbc_errormsg(),
            $this->errorMessenger->_300->level);

          return;
        }

        // Step #2: re-creating the inference graph
        if($this->action == "recreate_inference")
        {
          // Clean the inference table
          $this->db->query("rdfs_rule_set('".$this->rulesSetURI."', '" . $this->wsf_graph . "ontologies/inferred/', 1)");

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
            $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
              $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, odbc_errormsg(),
              $this->errorMessenger->_301->level);

            return;
          }

          // Recreatethe inference table
          $this->db->query("rdfs_rule_set('".$this->rulesSetURI."', '" . $this->wsf_graph . "ontologies/inferred/')");

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
            $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
              $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
              $this->errorMessenger->_302->level);

            return;
          }

          // Commit changes
          $this->db->query("exec('checkpoint')");

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
            $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
              $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, odbc_errormsg(),
              $this->errorMessenger->_303->level);

            return;
          }

          // Clear the inference graph
          $this->db->query("exst('sparql clear graph <" . $this->wsf_graph . "ontologies/inferred/>')");

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
            $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
              $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, odbc_errormsg(),
              $this->errorMessenger->_304->level);

            return;
          }

          // Step #3: Creating class hierarchy
          $classHierarchy = new ClassHierarchy("http://www.w3.org/2002/07/owl#Thing");

          $query = $this->db->build_sparql_query("select ?s ?o from <" . $this->wsf_graph
            . "ontologies/> where {?s <http://www.w3.org/2000/01/rdf-schema#subClassOf> ?o.}",
            array ('s', 'o'), FALSE);

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_305->name);
            $this->conneg->setError($this->errorMessenger->_305->id, $this->errorMessenger->ws,
              $this->errorMessenger->_305->name, $this->errorMessenger->_305->description, odbc_errormsg(),
              $this->errorMessenger->_305->level);

            return;
          }

          $ontologiesClasses = array();

          while(odbc_fetch_row($resultset))
          {
            $s = odbc_result($resultset, 1);
            $o = odbc_result($resultset, 2);

            // Drop blank nodes
            if(strpos($s, "nodeID://") === FALSE && strpos($o, "nodeID://") === FALSE)
            {
              $classHierarchy->addClassRelationship($s, $o);

              $ontologiesClasses[$s] = 1;
            }
          }

          $query = $this->db->build_sparql_query("select ?s from <" . $this->wsf_graph
            . "ontologies/> where {?s a <http://www.w3.org/2000/01/rdf-schema#Class>.}", array( 's' ), FALSE);

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_306->name);
            $this->conneg->setError($this->errorMessenger->_306->id, $this->errorMessenger->ws,
              $this->errorMessenger->_306->name, $this->errorMessenger->_306->description, odbc_errormsg(),
              $this->errorMessenger->_306->level);

            return;
          }

          while(odbc_fetch_row($resultset))
          {
            $s = odbc_result($resultset, 1);

            if(strpos($s, "nodeID://") === FALSE && isset($classHierarchy->classes[$s]) === FALSE)
            {
              $classHierarchy->addClassRelationship($s, "http://www.w3.org/2002/07/owl#Thing");

              $ontologiesClasses[$s] = 1;
            }
          }

          $query = $this->db->build_sparql_query("select ?s from <" . $this->wsf_graph
            . "ontologies/> where {?s a <http://www.w3.org/2002/07/owl#Class>.}", array( 's' ), FALSE);

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_307->name);
            $this->conneg->setError($this->errorMessenger->_307->id, $this->errorMessenger->ws,
              $this->errorMessenger->_307->name, $this->errorMessenger->_307->description, odbc_errormsg(),
              $this->errorMessenger->_307->level);

            return;
          }

          while(odbc_fetch_row($resultset))
          {
            $s = odbc_result($resultset, 1);

            if(strpos($s, "nodeID://") === FALSE && isset($classHierarchy->classes[$s]) === FALSE)
            {
              $classHierarchy->addClassRelationship($s, "http://www.w3.org/2002/07/owl#Thing");

              $ontologiesClasses[$s] = 1;
            }
          }


          // Step #4: Properties class hierarchy

          $propertyHierarchy = new PropertyHierarchy("http://www.w3.org/2002/07/owl#Thing");

          $query = $this->db->build_sparql_query("select ?s ?o from <" . $this->wsf_graph
            . "ontologies/> where {?s <http://www.w3.org/2000/01/rdf-schema#subPropertyOf> ?o.}",
            array ('s', 'o'), FALSE);

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_308->name);
            $this->conneg->setError($this->errorMessenger->_308->id, $this->errorMessenger->ws,
              $this->errorMessenger->_308->name, $this->errorMessenger->_308->description, odbc_errormsg(),
              $this->errorMessenger->_308->level);

            return;
          }

          $ontologiesProperties = array();

          while(odbc_fetch_row($resultset))
          {
            $s = odbc_result($resultset, 1);
            $o = odbc_result($resultset, 2);

            if(strpos($s, "nodeID://") === FALSE && strpos($o, "nodeID://") === FALSE)
            {
              $propertyHierarchy->addPropertyRelationship($s, $o);

              $ontologiesProperties[$s] = 1;
            }
          }

          $query = $this->db->build_sparql_query("select ?s from <" . $this->wsf_graph
            . "ontologies/> where {?s a <http://www.w3.org/1999/02/22-rdf-syntax-ns#Property>.}",
            array( 's' ), FALSE);

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_309->name);
            $this->conneg->setError($this->errorMessenger->_309->id, $this->errorMessenger->ws,
              $this->errorMessenger->_309->name, $this->errorMessenger->_309->description, odbc_errormsg(),
              $this->errorMessenger->_309->level);

            return;
          }

          while(odbc_fetch_row($resultset))
          {
            $s = odbc_result($resultset, 1);

            if(strpos($s, "nodeID://") === FALSE && isset($propertyHierarchy->properties[$s]) === FALSE)
            {
              $propertyHierarchy->addPropertyRelationship($s, "http://www.w3.org/2002/07/owl#Thing");

              $ontologiesProperties[$s] = 1;
            }
          }

          $query =
            $this->db->build_sparql_query("select ?s from <" . $this->wsf_graph
              . "ontologies/> where {{?s a <http://www.w3.org/2002/07/owl#ObjectProperty>.}union{?s a <http://www.w3.org/2002/07/owl#DatatypeProperty>.}}",
              array( 's' ), FALSE);

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_310->name);
            $this->conneg->setError($this->errorMessenger->_310->id, $this->errorMessenger->ws,
              $this->errorMessenger->_310->name, $this->errorMessenger->_310->description, odbc_errormsg(),
              $this->errorMessenger->_310->level);

            return;
          }

          while(odbc_fetch_row($resultset))
          {
            $s = odbc_result($resultset, 1);

            if(strpos($s, "nodeID://") === FALSE && isset($propertyHierarchy->properties[$s]) === FALSE)
            {
              $propertyHierarchy->addPropertyRelationship($s, "http://www.w3.org/2002/07/owl#Thing");

              $ontologiesProperties[$s] = 1;
            }
          }


          // Step #5: Populating the labels and descriptions for each ClassNode.

          foreach($classHierarchy->classes as $c)
          {
            $class = new RdfClass($c->name, $this->wsf_graph . "ontologies/", $this->wsf_graph . "ontologies/inferred/",
              $this->db);

// Escaping the description and label to make sure that PHP serialize works as expected.
//            $c->description = str_replace(array("\n", "\r", "'", '"'), array("&#010;", "&#013;", "&#039;", "&quot;"), preg_replace('/[^(\x20-\x7F)]*/','', $class->getDescription()));
//            $c->label = str_replace(array("\n", "\r", "'", '"'), array("&#010;", "&#013;", "&#039;", "&quot;"), preg_replace('/[^(\x20-\x7F)]*/','', $class->getLabel()));
            $c->description = str_replace(array ("\n", "\r", "'", '"'), array ("&#010;", "&#013;", "&#039;", "&quot;"),
              $class->getDescription());

            $c->label = str_replace(array ("\n", "\r", "'", '"'), array ("&#010;", "&#013;", "&#039;", "&quot;"),
              $class->getLabel());

            unset($class);
          }

          foreach($propertyHierarchy->properties as $p)
          {
            $property =
              new RdfProperty($p->name, $this->wsf_graph . "ontologies/", $this->wsf_graph . "ontologies/inferred/",
                $this->db);
// Escaping the description and label to make sure that PHP serialize works as expected.
//            $p->description = str_replace(array("\n", "\r", "'", '"'), array("&#010;", "&#013;", "&#039;", "&quot;"), preg_replace('/[^(\x20-\x7F)]*/','', $property->getDescription()));
//            $p->label = str_replace(array("\n", "\r", "'", '"'), array("&#010;", "&#013;", "&#039;", "&quot;"), preg_replace('/[^(\x20-\x7F)]*/','', $property->getLabel()));
            $p->description = str_replace(array ("\n", "\r", "'", '"'), array ("&#010;", "&#013;", "&#039;", "&quot;"),
              $property->getDescription());

            $p->label = str_replace(array ("\n", "\r", "'", '"'), array ("&#010;", "&#013;", "&#039;", "&quot;"),
              $property->getLabel());

            unset($property);
          }
        }

// Step #6: for each class, we add a "subClassOf" triple for each of their subClasses (recursively until we reach owl:Thing)

        foreach($ontologiesClasses as $class => $value)
        {
          $superClasses = $classHierarchy->getSuperClasses($class);

          foreach($superClasses as $sp)
          {
            $this->db->query("exst('sparql insert into graph <" . $this->wsf_graph . "ontologies/inferred/> {<"
              . $classHierarchy->classes[$class]->name . "> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <"
              . $sp->name . ">.}')");

            if(odbc_error())
            {
              $this->conneg->setStatus(400);
              $this->conneg->setStatusMsg("Bad Request");
              $this->conneg->setStatusMsgExt($this->errorMessenger->_311->name);
              $this->conneg->setError($this->errorMessenger->_311->id, $this->errorMessenger->ws,
                $this->errorMessenger->_311->name, $this->errorMessenger->_311->description, odbc_errormsg(),
                $this->errorMessenger->_311->level);

              return;
            }
          }
        }


        // Step #7: checking for equivalent classes.

        $query = $this->db->build_sparql_query("select ?s ?o from <" . $this->wsf_graph
          . "ontologies/> where {?s <http://www.w3.org/2002/07/owl#equivalentClass> ?o.}", array ('s', 'o'), FALSE);

        $resultset = $this->db->query($query);

        while(odbc_fetch_row($resultset))
        {
          $s = odbc_result($resultset, 1);
          $o = odbc_result($resultset, 2);

          if(strpos($s, "nodeID://") === FALSE && strpos($o, "nodeID://") === FALSE)
          {
            // Check if the equivalentClass belongs to our current class structure.
            if(isset($classHierarchy->classes[$o]))
            {
              // We perform the same superClasses assignation
              $subClasses = $classHierarchy->getSubClasses($o);

              foreach($subClasses as $sp)
              {
                $this->db->query("exst('sparql insert into graph <" . $this->wsf_graph . "ontologies/inferred/> {<"
                  . $sp->name . "> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <$s>.}')");
              }
            }

            // Check if the equivalentClass belongs to our current class structure.
            if(isset($classHierarchy->classes[$s]))
            {
              // We perform the same superClasses assignation
              $subClasses = $classHierarchy->getSubClasses($s);

              foreach($subClasses as $sp)
              {
                $this->db->query("exst('sparql insert into graph <" . $this->wsf_graph . "ontologies/inferred/> {<"
                  . $sp->name . "> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <$o>.}')");
              }
            }

            // Check if the equivalentClass belongs to our current class structure.
            if(isset($classHierarchy->classes[$o]))
            {
              // We perform the same superClasses assignation
              $superClasses = $classHierarchy->getSuperClasses($o);

              foreach($superClasses as $sp)
              {
                $this->db->query("exst('sparql insert into graph <" . $this->wsf_graph
                  . "ontologies/inferred/> {<$s> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <" . $sp->name
                  . ">.}')");
              }
            }

            // Check if the equivalentClass belongs to our current class structure.
            if(isset($classHierarchy->classes[$s]))
            {
              // We perform the same superClasses assignation
              $superClasses = $classHierarchy->getSuperClasses($s);

              foreach($superClasses as $sp)
              {
                $this->db->query("exst('sparql insert into graph <" . $this->wsf_graph
                  . "ontologies/inferred/> {<$o> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <" . $sp->name
                  . ">.}')");
              }
            }

            // We re-iterate the equivalency relationship in the inferred table.
            $this->db->query("exst('sparql insert into graph <" . $this->wsf_graph
              . "ontologies/inferred/> {<$s> <http://www.w3.org/2002/07/owl#equivalentClasses> <$o>.}')");
            $this->db->query("exst('sparql insert into graph <" . $this->wsf_graph
              . "ontologies/inferred/> {<$o> <http://www.w3.org/2002/07/owl#equivalentClasses> <$s>.}')");

            $classHierarchy->addClassRelationship($s, $o);

            $ontologiesClasses[$s] = 1;
          }
        }

        if(odbc_error())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
          $this->conneg->setError($this->errorMessenger->_312->id, $this->errorMessenger->ws,
            $this->errorMessenger->_312->name, $this->errorMessenger->_312->description, odbc_errormsg(),
            $this->errorMessenger->_312->level);
          return;
        }

        // Step #8 inferring the domains and range of all properties (except unionOf)

        $properties = array();

        $query =
          $this->db->build_sparql_query("select distinct ?s ?domain ?range from <" . $this->wsf_graph
            . "ontologies/> where {?s a <http://www.w3.org/1999/02/22-rdf-syntax-ns#Property>. optional{{?s <http://www.w3.org/2000/01/rdf-schema#domain> ?domain.} union { ?s <http://www.w3.org/2000/01/rdf-schema#range> ?range.}}}",
            array ('s', 'domain', 'range'), FALSE);

        $resultset = $this->db->query($query);

        $ontologiesClasses = array();

        while(odbc_fetch_row($resultset))
        {
          $property = odbc_result($resultset, 1);
          $domain = odbc_result($resultset, 2);
          $range = odbc_result($resultset, 3);

          if(strpos($domain, "nodeID://") !== FALSE)
          {
            $domain = "";
          }

          if(strpos($range, "nodeID://") !== FALSE)
          {
            $range = "";
          }

          if(!isset($properties[$property]))
          {
            $properties[$property] = array ("http://www.w3.org/2002/07/owl#Thing",
              "http://www.w3.org/2002/07/owl#Thing");
          }

          if($domain == "next")
          {
            $properties[$property][0] = "";
          }
          elseif($domain != "")
          {
            $properties[$property][0] = $domain;
          }

          if($range == "next")
          {
            $properties[$property][1] = "";
          }
          elseif($domain != "")
          {
            $properties[$property][1] = $range;
          }
        }

        if(odbc_error())
        {
        //          echo "-- TEST-1 --";
        }

        $query =
          $this->db->build_sparql_query("select distinct ?s ?domain ?range from <" . $this->wsf_graph
            . "ontologies/> where {?s a <http://www.w3.org/2002/07/owl#DatatypeProperty>. optional{{?s <http://www.w3.org/2000/01/rdf-schema#domain> ?domain.} union {?s <http://www.w3.org/2000/01/rdf-schema#range> ?range.}}}",
            array ('s', 'domain', 'range'), FALSE);

        $resultset = $this->db->query($query);

        $ontologiesClasses = array();

        while(odbc_fetch_row($resultset))
        {
          $property = odbc_result($resultset, 1);
          $domain = odbc_result($resultset, 2);
          $range = odbc_result($resultset, 3);

          if(strpos($domain, "nodeID://") !== FALSE)
          {
            $domain = "next";
          }

          if(strpos($range, "nodeID://") !== FALSE)
          {
            $range = "next";
          }

          if(!isset($properties[$property]))
          {
            $properties[$property] = array ("http://www.w3.org/2002/07/owl#Thing",
              "http://www.w3.org/2002/07/owl#Thing");
          }

          if($domain == "next")
          {
            $properties[$property][0] = "";
          }
          elseif($domain != "")
          {
            $properties[$property][0] = $domain;
          }

          if($range == "next")
          {
            $properties[$property][1] = "";
          }
          elseif($range != "")
          {
            $properties[$property][1] = $range;
          }
        }

        if(odbc_error())
        {
        //          echo "-- TEST-2 --";
        }

        $query =
          $this->db->build_sparql_query("select distinct ?s ?domain ?range from <" . $this->wsf_graph
            . "ontologies/> where {?s a <http://www.w3.org/2002/07/owl#ObjectProperty>. optional{{?s <http://www.w3.org/2000/01/rdf-schema#domain> ?domain.} union {?s <http://www.w3.org/2000/01/rdf-schema#range> ?range.}}}",
            array ('s', 'domain', 'range'), FALSE);

        $resultset = $this->db->query($query);

        $ontologiesClasses = array();

        while(odbc_fetch_row($resultset))
        {
          $property = odbc_result($resultset, 1);
          $domain = odbc_result($resultset, 2);
          $range = odbc_result($resultset, 3);

          if(strpos($domain, "nodeID://") !== FALSE)
          {
            $domain = "";
          }

          if(strpos($range, "nodeID://") !== FALSE)
          {
            $range = "";
          }

          if(!isset($properties[$property]))
          {
            $properties[$property] = array ("http://www.w3.org/2002/07/owl#Thing",
              "http://www.w3.org/2002/07/owl#Thing");
          }

          if($domain == "next")
          {
            $properties[$property][0] = "";
          }
          elseif($domain != "")
          {
            $properties[$property][0] = $domain;
          }

          if($range == "next")
          {
            $properties[$property][1] = "";
          }
          elseif($domain != "")
          {
            $properties[$property][1] = $range;
          }
        }

        if(odbc_error())
        {
        //          echo "-- TEST-3 --";
        }


        /*
        foreach($properties as $property => $domainsRanges)
        {
          // Domains
          if($domainsRanges[0] != "")
          {
            if($domainsRanges[0] == "http://www.w3.org/2002/07/owl#Thing")
            {
              $this->db->query("exst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <http://www.w3.org/2002/07/owl#Thing>.}')");
            }
            else
            {
              if(isset($classHierarchy->classes[$domainsRanges[0]]))
              {
                $subClasses = $classHierarchy->getSubClasses($domainsRanges[0]);
                
                foreach($subClasses as $sp)
                {
                  $this->db->query("exst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <".$sp->name.">.}')");
                  
                  $this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#domain", $sp->name);
                }  
              }
              else
              {
                $this->db->query("exst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <$domainsRanges[0]>.}')");
                
                $this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#domain", $domainsRanges[0]);
              }  
            }
          }
          
          // Ranges
          if($domainsRanges[1] != "")
          {
            if($domainsRanges[1] == "http://www.w3.org/2002/07/owl#Thing")
            {
              $this->db->query("exst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <http://www.w3.org/2002/07/owl#Thing>.}')");
            }
            else
            {
              if(isset($classHierarchy->classes[$domainsRanges[1]]))
              {
                $subClasses = $classHierarchy->getSubClasses($domainsRanges[1]);
                
                foreach($subClasses as $sp)
                {
                  $this->db->query("exst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <".$sp->name.">.}')");
                  
                  $this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#range", $sp->name);
                }  
              }
              else
              {
                $this->db->query("exst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <$domainsRanges[1]>.}')");
        
                $this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#range", $domainsRanges[1]);
              }  
            }
          }  
        }    
        
        if(odbc_error())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_313->name);
          $this->conneg->setError($this->errorMessenger->_313->id, 
                            $this->errorMessenger->ws, 
                            $this->errorMessenger->_313->name, 
                            $this->errorMessenger->_313->description, 
                            odbc_errormsg(),
                            $this->errorMessenger->_313->level);
                            
          return;          
          
        }        */


        // Step #9: processing the unionOf domains and ranges if needed.

        // Domains

        $query =
          $this->db->build_sparql_query("SELECT ?s ?unionOf FROM <" . $this->wsf_graph
            . "ontologies/> WHERE { ?s <http://www.w3.org/2000/01/rdf-schema#domain> ?o. ?o <http://www.w3.org/2002/07/owl#unionOf> ?unionOf.}",
            array ('s', 'unionOf'), FALSE);

        $resultset = $this->db->query($query);

        $unionClasses = array();

        while(odbc_fetch_row($resultset))
        {
          $property = odbc_result($resultset, 1);
          $union = odbc_result($resultset, 2);

          $this->getUnionOf($union, $unionClasses);
        }

        if(odbc_error())
        {
        //          echo "-- TEST-4 --";
        }

        foreach($unionClasses as $uc)
        {
          if(isset($classHierarchy->classes[$uc]))
          {
            $subClasses = $classHierarchy->getSubClasses($uc);

            foreach($subClasses as $sp)
            {
              $this->db->query("exst('sparql insert into graph <" . $this->wsf_graph
                . "ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <" . $sp->name
                . ">.}')");

              $this->addEquivalentClass($inferredOntologiesGraph, $property,
                "http://www.w3.org/2000/01/rdf-schema#domain", $sp->name);

              if(odbc_error())
              {
//                echo "-- TEST-4.1 --";
//          echo "\n\nexst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <".$sp->name.">.}')\n\n";
              }
            }
          }

          $this->db->query("exst('sparql insert into graph <" . $this->wsf_graph
            . "ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <$uc>.}')");

          if(odbc_error())
          {
//            echo "-- TEST-4.2 --";
//            echo "\n\nexst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <$uc>.}')\n\n";
          }

          $this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#domain",
            $uc);
        }


        /*
        
        
        // Ranges
        $query = $this->db->build_sparql_query("SELECT ?s ?unionOf FROM <".$this->wsf_graph."ontologies/> WHERE { ?s <http://www.w3.org/2000/01/rdf-schema#range> ?o. ?o <http://www.w3.org/2002/07/owl#unionOf> ?unionOf.}", array ('s', 'unionOf'), FALSE);
        
        $resultset = $this->db->query($query);
        
        while(odbc_fetch_row($resultset))
        {
          $property = odbc_result($resultset, 1);
          $union = odbc_result($resultset, 2);
          
          $unionClasses = array();
          
          $this->getUnionOf($union, $unionClasses);
        
          foreach($unionClasses as $uc)
          {
            if(isset($classHierarchy->classes[$uc]))
            {
              $subClasses = $classHierarchy->getSubClasses($uc);
              
              foreach($subClasses as $sp)
              {
                $this->db->query("exst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <".$sp->name.">.}')");
        
                $this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#range", $sp->name);
              }  
            }
            
            $this->db->query("exst('sparql insert into graph <".$this->wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <$uc>.}')");
            
            $this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#range", $uc);
          }
        }          
        
        if(odbc_error())
        {
          echo "-- TEST-5 --";
        }
        */

        $classHierarchy = serialize($classHierarchy);
        $classHierarchy = str_replace(array ("\n", "\r"), array ("", ""), $classHierarchy);

        $propertyHierarchy = serialize($propertyHierarchy);
        $propertyHierarchy = str_replace(array ("\n", "\r"), array ("", ""), $propertyHierarchy);


        /*!
                @todo Fixing this to use the DB.
                
                // This method is currently not working. The problem is that we ahve an issue in CrudCreate and Virtuoso's
                // LONG VARCHAR column. It appears that there is a bug somewhere in the "php -> odbc -> virtuoso" path.
                // If we are not requesting to return the LONG VARCHAR column, everything works fine.
        */
        /*    
                // Step #10: Delete the previously created table
                $this->db->query('drop table "SD"."WSF"."ws_ontologies"');
        
                // Step #11: Adding the class & properties structures to the table.
                
                $this->db->query('create table "SD"."WSF"."ws_ontologies" ("struct_type" VARCHAR, "struct" LONG VARCHAR, PRIMARY KEY ("struct_type"))');
                
        //        $this->db->query("insert into SD.WSF.ws_ontologies(struct_type, struct) values('class', '".$classHierarchy."')");
        //        $this->db->query("insert into SD.WSF.ws_ontologies(struct_type, struct) values('property', '".$propertyHierarchy."')");
        
                $this->db->query("insert into SD.WSF.ws_ontologies(struct_type, struct) values('class', 'test1')");
                $this->db->query("insert into SD.WSF.ws_ontologies(struct_type, struct) values('property', 'test2')");
        
                $this->db->query("exec('checkpoint')");
        */

        // Step #10: Create the PHP serialized files that will be used by other web services of this WSF

        $classHierarchyFile = rtrim($this->ontological_structure_folder, "/") . "/classHierarchySerialized.srz";

        // Delete file first
        @unlink($classHierarchyFile);

        $fHandle = fopen($classHierarchyFile, 'w');

        if($fHandle !== FALSE)
        {
          if(!fwrite($fHandle, $classHierarchy))
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_314->name);
            $this->conneg->setError($this->errorMessenger->_314->id, $this->errorMessenger->ws,
              $this->errorMessenger->_314->name, $this->errorMessenger->_314->description, $classHierarchyFile,
              $this->errorMessenger->_314->level);

            return;
          }

          fclose($fHandle);
        }
        else
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt("Error #ontology-create-16. : ");
          $this->conneg->setError($this->errorMessenger->_315->id, $this->errorMessenger->ws,
            $this->errorMessenger->_315->name, $this->errorMessenger->_315->description, $classHierarchyFile,
            $this->errorMessenger->_315->level);
          return;
        }

        $propertyHierarchyFile = rtrim($this->ontological_structure_folder, "/") . "/propertyHierarchySerialized.srz";

        // Delete file first
        @unlink($propertyHierarchyFile);

        $fHandle = fopen($propertyHierarchyFile, 'w');

        if($fHandle !== FALSE)
        {
          if(!fwrite($fHandle, $propertyHierarchy))
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_316->name);
            $this->conneg->setError($this->errorMessenger->_316->id, $this->errorMessenger->ws,
              $this->errorMessenger->_316->name, $this->errorMessenger->_316->description, $propertyHierarchyFile,
              $this->errorMessenger->_316->level);
          }
          fclose($fHandle);
        }
        else
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_317->name);
          $this->conneg->setError($this->errorMessenger->_317->id, $this->errorMessenger->ws,
            $this->errorMessenger->_317->name, $this->errorMessenger->_317->description, $propertyHierarchyFile,
            $this->errorMessenger->_317->level);
          return;
        }

        return;
      }
    }
  }

  private function addEquivalentClass($graph, $subject, $property, $target)
  {
    $query = $this->db->build_sparql_query("select ?o from <" . $this->wsf_graph
      . "ontologies/> where {<$target> <http://www.w3.org/2002/07/owl#equivalentClass> ?o.}", array( 'o' ), FALSE);

    $resultset = $this->db->query($query);

    while(odbc_fetch_row($resultset))
    {
      $o = odbc_result($resultset, 1);

      $this->db->query("exst('sparql insert into graph <$graph> {<$subject> <$property> <$o>.}')");
    }
  }

  private function getUnionOf($unionURI, &$unionClasses)
  {
    $query =
      $this->db->build_sparql_query("SELECT * FROM <" . $this->wsf_graph . "ontologies/> WHERE { <$unionURI> ?p ?o. }",
        array ('p', 'o'), FALSE);

    $resultset = $this->db->query($query);

    while(odbc_fetch_row($resultset))
    {
      $p = odbc_result($resultset, 1);
      $o = odbc_result($resultset, 2);

      if($p == "http://www.w3.org/1999/02/22-rdf-syntax-ns#first")
      {
        array_push($unionClasses, $o);
      }

      if($p == "http://www.w3.org/1999/02/22-rdf-syntax-ns#rest")
      {
        if($o == "http://www.w3.org/1999/02/22-rdf-syntax-ns#nil")
        {
          break;
        }
        else
        {
          $this->getUnionOf($o, $unionClasses);
        }
      }
    }
  }
}

//@}

?>