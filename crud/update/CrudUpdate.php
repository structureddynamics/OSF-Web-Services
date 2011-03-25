<?php

/*! @defgroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \ws\crud\update\CrudUpdate.php
   @brief Define the Crud Update web service
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief CRUD Update web service. It updates instance records descriptions from dataset indexes on different systems (Virtuoso, Solr, etc).
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class CrudUpdate extends WebService
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

  /*! @brief Dataset where to index the resource*/
  private $dataset;

/*! @brief RDF document where resource(s) to be added are described. Maximum size (by default) is 8M (default php.ini setting). */
  private $document = array();

  /*! @brief Mime of the RDF document serialization */
  private $mime = "";

  /*! @brief Requester's IP used for request validation */
  private $requester_ip = "";

  /*! @brief Error messages of this web service */
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
                          "level": "Warning",
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
                          "id": "WS-CRUD-CREATE-307",
                          "level": "Warning",
                          "name": "Can\'t parse RDF document",
                          "description": "Can\'t parse the specified RDF document"
                        },
                        "_308": {
                          "id": "WS-CRUD-CREATE-308",
                          "level": "Fatal",
                          "name": "Can\'t create a tracking record for one of the input records",
                          "description": "We can\'t create the records because we can\'t ensure that we have a track of their changes."
                        }                          
                      }';


  /*!   @brief Constructor
       @details   Initialize the Crud Update
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($document, $mime, $dataset, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->requester_ip = $requester_ip;
    $this->dataset = $dataset;
    $this->mime = $mime;

    if (extension_loaded("mbstring") && mb_detect_encoding($document, "UTF-8", TRUE) != "UTF-8")
    {                   
      $this->document = utf8_encode($document);
    }
    else //we have to assume the input is UTF-8
    {
      $this->document = $document;
    }    
    
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/crud/update/";
    $this->title = "Crud Update Web Service";
    $this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/crud/update/";

    $this->dtdURL = "auth/CrudUpdate.dtd";

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
    // Validation of the "requester_ip" to make sure the system that is sending the query as the rights.
    $ws_av = new AuthValidator($this->requester_ip, $this->dataset, $this->uri);

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

    unset($ws_av);

    // If the system send a query on the behalf of another user, we validate that other user as well
    if($this->registered_ip != $this->requester_ip)
    {    
      // Validation of the "registered_ip" to make sure the user of this system has the rights
      $ws_av = new AuthValidator($this->registered_ip, $this->dataset, $this->uri);

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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Crud Update DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudUpdate::$supportedSerializations);

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

    $ws_dr = new DatasetRead($this->dataset, "false", "self",
      $this->wsf_local_ip); // Here the one that makes the request is the WSF (internal request).
    //    $ws_dr = new DatasetRead($this->dataset, "false", $this->registered_ip, $this->requester_ip);

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


  /*!   @brief Update the information of a given instance record
              
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
        // Step #0: Parse the file using ARC2 to populate the Solr index.
        // Get triples from ARC for some offline processing.
        $parser = ARC2::getRDFParser();
        $parser->parse($this->dataset, $this->document);   

        $rdfxmlSerializer = ARC2::getRDFXMLSerializer();

        $resourceIndex = $parser->getSimpleIndex(0);

        if(count($parser->getErrors()) > 0)
        {
          $errorsOutput = "";
          $errors = $parser->getErrors();

          foreach($errors as $key => $error)
          {
            $errorsOutput .= "[Error #$key] $error\n";
          }
          
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setError($this->errorMessenger->_307->id, $this->errorMessenger->ws,
            $this->errorMessenger->_307->name, $this->errorMessenger->_307->description, $errorsOutput,
            $this->errorMessenger->_307->level);

          return;
        }

        // Get all the reification statements
        $break = FALSE;
        $statementsUri = array();

        foreach($resourceIndex as $resource => $description)
        {
          foreach($description as $predicate => $values)
          {
            if($predicate == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
            {
              foreach($values as $value)
              {
                if($value["type"] == "uri" && $value["value"] == "http://www.w3.org/1999/02/22-rdf-syntax-ns#Statement")
                {
                  array_push($statementsUri, $resource);
                  break;
                }
              }
            }

            if($break)
            {
              break;
            }
          }

          if($break)
          {
            break;
          }
        }

        // Get all references of all instance records resources (except for the statement resources)
        $irsUri = array();

        foreach($resourceIndex as $resource => $description)
        {
          if($resource != $datasetUri && array_search($resource, $statementsUri) === FALSE)
          {
            array_push($irsUri, $resource);
          }
        }
        
        // Track the record description changes
        if($this->track_update === TRUE)
        {
          foreach($irsUri as $uri)
          {
            // First check if the record is already existing for this record, within this dataset.
            include_once("../read/CrudRead.php"); 

            $ws_cr = new CrudRead($uri, $this->dataset, FALSE, TRUE, $this->registered_ip, $this->requester_ip);
            
            $ws_cr->ws_conneg("application/rdf+xml", "utf-8", "identity", "en");

            $ws_cr->process();

            $oldRecordDescription = $ws_cr->ws_serialize();
            
            $ws_cr_error = $ws_cr->pipeline_getError();
            
            if($ws_cr->pipeline_getResponseHeaderStatus() == 400 && $ws_cr_error->id == "WS-CRUD-READ-300")
            {
              // The record is not existing within this dataset, so we simply move-on
              continue;
            }          
            elseif($ws_cr->pipeline_getResponseHeaderStatus() != 200)
            {
              // An error occured. Since we can't get the past state of a record, we have to send an error
              // for the CrudUpdate call since we can't create a tracking record for this record.
              $this->conneg->setStatus(400);
              $this->conneg->setStatusMsg("Bad Request");
              $this->conneg->setError($this->errorMessenger->_308->id, $this->errorMessenger->ws,
                $this->errorMessenger->_308->name, $this->errorMessenger->_308->description, 
                "We can't create a track record for the following record: $uri",
                $this->errorMessenger->_308->level);
                
              break;
            }    
            
            $endpoint = "";
            if($this->tracking_endpoint != "")
            {
              // We send the query to a remove tracking endpoint
              $endpoint = $this->tracking_endpoint."create/";
            }
            else
            {
              // We send the query to a local tracking endpoint
              $endpoint = $this->wsf_base_url."/ws/tracker/create/";
            }
            
            include_once("../../framework/WebServiceQuerier.php");                                                  
            
            $wsq = new WebServiceQuerier($endpoint, "post",
              "text/xml", "from_dataset=" . urlencode($this->dataset) .
              "&record=" . urlencode($uri) .
              "&action=update" .
              "&previous_state=" . urlencode($oldRecordDescription) .
              "&previous_state_mime=" . urlencode("application/rdf+xml") .
              "&performer=" . urlencode($this->registered_ip) .
              "&registered_ip=self");

            if($wsq->getStatus() != 200)
            {
              $this->conneg->setStatus($wsq->getStatus());
              $this->conneg->setStatusMsg($wsq->getStatusMessage());
              /*
              $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
                $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
                $this->errorMessenger->_302->level);                
              */
            }

            unset($wsq);              
          }
        }        
        

        // Step #1: indexing the incomming rdf document in its own temporary graph
        $tempGraphUri = "temp-graph-" . md5($this->document);

        $irs = array();

        foreach($irsUri as $uri)
        {
          $irs[$uri] = $resourceIndex[$uri];
        }

        @$this->db->query("DB.DBA.RDF_LOAD_RDFXML_MT('"
          . str_replace("'", "\'", $rdfxmlSerializer->getSerializedIndex($irs))
            . "', '$tempGraphUri', '$tempGraphUri', 0)");

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

        // Step #2: use that temp graph to modify (delete/insert using SPARUL) the target graph of the update query
        $query = "delete from <" . $this->dataset . ">
                { 
                  ?s ?p_original ?o_original.
                }
                where
                {
                  graph <" . $tempGraphUri . ">
                  {
                    ?s ?p ?o.
                  }
                  
                  graph <" . $this->dataset . ">
                  {
                    ?s ?p_original ?o_original.
                  }
                }
                
                insert into <" . $this->dataset . ">
                {
                  ?s ?p ?o.
                }                  
                where
                {
                  graph <" . $tempGraphUri . ">
                  {
                    ?s ?p ?o.
                  }
                }";

        @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
          FALSE));

        if(odbc_error())
        {
          $this->conneg->setStatus(500);
          $this->conneg->setStatusMsg("Internal Error");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
          $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
            $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, odbc_errormsg(),
            $this->errorMessenger->_301->level);

          return;
        }

        if(count($statementsUri) > 0)
        {
          $tempGraphReificationUri = "temp-graph-reification-" . md5($this->document);

          $statements = array();

          foreach($statementsUri as $uri)
          {
            $statements[$uri] = $resourceIndex[$uri];
          }

          @$this->db->query("DB.DBA.RDF_LOAD_RDFXML_MT('"
            . str_replace("'", "\'", $rdfxmlSerializer->getSerializedIndex($statements))
              . "', '$tempGraphReificationUri', '$tempGraphReificationUri', 0)");

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


          // Step #2.5: use the temp graph to modify the reification graph
          $query = "delete from <" . $this->dataset . "reification/>
                  { 
                    ?s_original ?p_original ?o_original.
                  }
                  where
                  {
                    graph <" . $tempGraphReificationUri . ">
                    {
                      ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> ?rei_subject .
                      ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> ?rei_predicate .
                      ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> ?rei_object .
                      
                      ?s ?p ?o.
                    }
                    
                    graph <" . $this->dataset . "reification/>
                    {
                      ?s_original <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> ?rei_subject .
                      ?s_original <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> ?rei_predicate .
                      ?s_original <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> ?rei_object .
                      
                      ?s_original ?p_original ?o_original.
                    }
                  }
                  
                  insert into <" . $this->dataset . "reification/>
                  {
                    ?s_original ?p2 ?o2.
                  }                  
                  where
                  {
                    graph <" . $tempGraphReificationUri . ">
                    {
                      ?s_original ?p2 ?o2.
                    }
                  }";

          @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
            $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
              $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, odbc_errormsg(),
              $this->errorMessenger->_301->level);

            return;
          }

          // Step #2.6: Remove the temp graph
          $query = "clear graph <" . $tempGraphReificationUri . ">";

          @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), "", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
            $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
              $this->errorMessenger->_303->name, $this->errorMessenger->_303->description,
              odbc_errormsg() . " -- Query: [" . str_replace(array ("\n", "\r", "\t"), " ", $query) . "]",
              $this->errorMessenger->_303->level);
            return;
          }
        }

        // Step #3: Remove the temp graph
        $query = "clear graph <" . $tempGraphUri . ">";

        @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), "", $query), array(),
          FALSE));

        if(odbc_error())
        {
          $this->conneg->setStatus(500);
          $this->conneg->setStatusMsg("Internal Error");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
          $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
            $this->errorMessenger->_303->name, $this->errorMessenger->_303->description,
            odbc_errormsg() . " -- Query: [" . str_replace(array ("\n", "\r", "\t"), " ", $query) . "]",
            $this->errorMessenger->_303->level);
          return;
        }


        // Step #4: Update Solr index


        /*!
                @todo Fixing this to use the DB.
                
                // This method is currently not working. The problem is that we ahve an issue in CrudCreate and Virtuoso's
                // LONG VARCHAR column. It appears that there is a bug somewhere in the "php -> odbc -> virtuoso" path.
                // If we are not requesting to return the LONG VARCHAR column, everything works fine.
        */
        /*    
                 $resultset = $this->db->query("select * from SD.WSF.ws_ontologies where struct_type = 'class'");
                
                odbc_binmode($resultset, ODBC_BINMODE_PASSTHRU);
                odbc_longreadlen($resultset, 16384);       
                
                odbc_fetch_row($resultset);
                $classHierarchy = unserialize(odbc_result($resultset, "struct"));
                
                if (odbc_error())
                {
                  $this->conneg->setStatus(500);
                  $this->conneg->setStatusMsg("Internal Error");
                  $this->conneg->setStatusMsgExt("Error #crud-create-103");  
                  return;
                }          
        */

        $filename = rtrim($this->ontological_structure_folder, "/") . "/classHierarchySerialized.srz";
        $file = fopen($filename, "r");
        $classHierarchy = fread($file, filesize($filename));
        $classHierarchy = unserialize($classHierarchy);
        fclose($file);

        $labelProperties =
          array (Namespaces::$iron . "prefLabel", Namespaces::$iron . "altLabel", Namespaces::$skos_2008 . "prefLabel",
            Namespaces::$skos_2008 . "altLabel", Namespaces::$skos_2004 . "prefLabel",
            Namespaces::$skos_2004 . "altLabel", Namespaces::$rdfs . "label", Namespaces::$dcterms . "title",
            Namespaces::$foaf . "name", Namespaces::$foaf . "givenName", Namespaces::$foaf . "family_name");

        $descriptionProperties = array (Namespaces::$iron . "description", Namespaces::$dcterms . "description",
          Namespaces::$skos_2008 . "definition", Namespaces::$skos_2004 . "definition");


        // Index in Solr

        $solr = new Solr($this->wsf_solr_core, $this->solr_host, $this->solr_port);

        foreach($irsUri as $subject)
        {
          // Skip Bnodes indexation in Solr
          // One of the prerequise is that each records indexed in Solr (and then available in Search and Browse)
          // should have a URI. Bnodes are simply skiped.

          if(stripos($subject, "_:arc") !== FALSE)
          {
            continue;
          }

          $add = "<add><doc><field name=\"uid\">" . md5($this->dataset . $subject) . "</field>";
          $add .= "<field name=\"uri\">$subject</field>";
          $add .= "<field name=\"dataset\">" . $this->dataset . "</field>";

          // Get types for this subject.
          $types = array();

          foreach($resourceIndex[$subject]["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"] as $value)
          {
            array_push($types, $value["value"]);

            $add .= "<field name=\"type\">" . $value["value"] . "</field>";
          }

          // get the preferred and alternative labels for this resource
          $prefLabelFound = FALSE;

          foreach($labelProperties as $property)
          {
            if(isset($resourceIndex[$subject][$property]) && !$prefLabelFound)
            {
              $prefLabelFound = TRUE;
              $add .= "<field name=\"prefLabel\">" . $this->xmlEncode($resourceIndex[$subject][$property][0]["value"])
                . "</field>";
              $add .= "<field name=\"attribute\">" . $this->xmlEncode(Namespaces::$iron . "prefLabel") . "</field>";
            }
            elseif(isset($resourceIndex[$subject][$property]))
            {
              foreach($resourceIndex[$subject][$property] as $value)
              {
                $add .= "<field name=\"altLabel\">" . $this->xmlEncode($value["value"]) . "</field>";
                $add .= "<field name=\"attribute\">" . $this->xmlEncode(Namespaces::$iron . "altLabel") . "</field>";
              }
            }
          }

          // get the description of the resource
          foreach($descriptionProperties as $property)
          {
            if(isset($resourceIndex[$subject][$property]))
            {
              $add .= "<field name=\"description\">" . $this->xmlEncode($resourceIndex[$subject][$property][0]["value"])
                . "</field>";
              $add .= "<field name=\"attribute\">" . $this->xmlEncode(Namespaces::$iron . "description") . "</field>";
              break;
            }
          }

          // Add the prefURL if available
          if(isset($resourceIndex[$subject][$iron . "prefURL"]))
          {
            $add .= "<field name=\"prefURL\">"
              . $this->xmlEncode($resourceIndex[$subject][$iron . "prefURL"][0]["value"]) . "</field>";
            $add .= "<field name=\"attribute\">" . $this->xmlEncode(Namespaces::$iron . "prefURL") . "</field>";
          }

          // Get properties with the type of the object
          foreach($resourceIndex[$subject] as $predicate => $values)
          {
            if(array_search($predicate, $labelProperties) === FALSE
              && array_search($predicate, $descriptionProperties) === FALSE && $predicate != Namespaces::$iron
              . "prefURL") // skip label & description & prefURL properties
            {
              foreach($values as $value)
              {
                if($value["type"] == "literal")
                {
                  $add .= "<field name=\"" . urlencode($predicate) . "_attr\">" . $this->xmlEncode($value["value"])
                    . "</field>";
                  $add .= "<field name=\"attribute\">" . $this->xmlEncode($predicate) . "</field>";

// Check if there is a reification statement for that triple. If there is one, we index it in the index as:
// <property> <text>
// Note: Eventually we could want to update the Solr index to include a new "reifiedText" field.
                  foreach($statementsUri as $statementUri)
                  {
                    if($resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"][0]["value"]
                      == $subject
                        && $resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"][0][
                          "value"] == $predicate &&
                        $resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#object"][0]["value"]
                        == $value["value"])
                    {
                      foreach($resourceIndex[$statementUri] as $reiPredicate => $reiValues)
                      {
                        if($reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type"
                          && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"
                          && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"
                          && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#object")
                        {
                          foreach($reiValues as $reiValue)
                          {
                            if($reiValue["type"] == "literal")
                            {
                              // Attribute used to reify information to a statement.
                              $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_attr\">"
                                . $this->xmlEncode($predicate) .
                                "</field>";

                              $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_obj\">"
                                . $this->xmlEncode($value["value"]) .
                                "</field>";

                              $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_value\">"
                                . $this->xmlEncode($reiValue["value"]) .
                                "</field>";

                              $add .= "<field name=\"attribute\">" . $this->xmlEncode($reiPredicate) . "</field>";
                            }
                          }
                        }
                      }
                    }
                  }
                }
                elseif($value["type"] == "uri")
                {
                  $query = $this->db->build_sparql_query("select ?p ?o from <" . $this->dataset . "> where {<"
                    . $value["value"] . "> ?p ?o.}", array ('p', 'o'), FALSE);

                  $resultset3 = $this->db->query($query);

                  $subjectTriples = array();

                  while(odbc_fetch_row($resultset3))
                  {
                    $p = odbc_result($resultset3, 1);
                    $o = odbc_result($resultset3, 2);

                    if(!isset($subjectTriples[$p]))
                    {
                      $subjectTriples[$p] = array();
                    }

                    array_push($subjectTriples[$p], $o);
                  }

                  unset($resultset3);

                  $labels = "";

                  foreach($labelProperties as $property)
                  {
                    if(isset($subjectTriples[$property]))
                    {
                      $labels .= $subjectTriples[$property][0] . " ";
                    }
                  }

                  if($labels != "")
                  {
                    $add .= "<field name=\"" . urlencode($predicate) . "_attr_obj\">" . $this->xmlEncode($labels)
                      . "</field>";
                    $add .= "<field name=\"" . urlencode($predicate) . "_attr_obj_uri\">"
                      . $this->xmlEncode($value["value"]) . "</field>";
                    $add .= "<field name=\"attribute\">" . $this->xmlEncode($predicate) . "</field>";
                  }

// Check if there is a reification statement for that triple. If there is one, we index it in the index as:
// <property> <text>
// Note: Eventually we could want to update the Solr index to include a new "reifiedText" field.
                  $statementAdded = FALSE;

                  foreach($statementsUri as $statementUri)
                  {
                    if($resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"][0]["value"]
                      == $subject
                        && $resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"][0][
                          "value"] == $predicate &&
                        $resourceIndex[$statementUri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#object"][0]["value"]
                        == $value["value"])
                    {
                      foreach($resourceIndex[$statementUri] as $reiPredicate => $reiValues)
                      {
                        if($reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type"
                          && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"
                          && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"
                          && $reiPredicate != "http://www.w3.org/1999/02/22-rdf-syntax-ns#object")
                        {
                          foreach($reiValues as $reiValue)
                          {
                            if($reiValue["type"] == "literal")
                            {
                              // Attribute used to reify information to a statement.
                              $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_attr_obj\">"
                                . $this->xmlEncode($predicate) .
                                "</field>";

                              $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_obj\">"
                                . $this->xmlEncode($value["value"]) .
                                "</field>";

                              $add .= "<field name=\"" . urlencode($reiPredicate) . "_reify_value\">"
                                . $this->xmlEncode($reiValue["value"]) .
                                "</field>";

                              $add .= "<field name=\"attribute\">" . $this->xmlEncode($reiPredicate) . "</field>";
                              $statementAdded = TRUE;
                              break;
                            }
                          }
                        }

                        if($statementAdded)
                        {
                          break;
                        }
                      }
                    }
                  }
                }
              }
            }
          }

          // Get all types by inference
          foreach($types as $type)
          {
            $superClasses = $classHierarchy->getSuperClasses($type);

            foreach($superClasses as $sc)
            {
              $add .= "<field name=\"inferred_type\">" . $this->xmlEncode($sc->name) . "</field>";
            }
          }

          $add .= "</doc></add>";

          if(!$solr->update($add))
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
              $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, "",
              $this->errorMessenger->_304->level);
            return;
          }
        }

        if($this->solr_auto_commit === FALSE)
        {
          if(!$solr->commit())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_305->name);
            $this->conneg->setError($this->errorMessenger->_305->id, $this->errorMessenger->ws,
              $this->errorMessenger->_305->name, $this->errorMessenger->_305->description, "",
              $this->errorMessenger->_305->level);
            return;
          }
        }

      /*        
              if(!$solr->optimize())
              {
                $this->conneg->setStatus(500);
                $this->conneg->setStatusMsg("Internal Error");
                $this->conneg->setStatusMsgExt("Error #crud-create-105");
                return;          
              }
      */
      }
    }
  }
}

//@}

?>