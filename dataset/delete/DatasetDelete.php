<?php

/*! @defgroup WsDataset Dataset Management Web Service  */
//@{

/*! @file \ws\dataset\delete\DatasetDelete.php
   @brief Delete a new graph for this dataset & indexation of its description
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Dataset Delete Web Service. It deletes an existing graph of the structWSF instance
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class DatasetDelete extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Requested IP */
  private $registered_ip = "";

  /*! @brief URI of the dataset to delete */
  private $datasetUri = "";

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/dataset/delete/",
                        "_200": {
                          "id": "WS-DATASET-DELETE-200",
                          "level": "Warning",
                          "name": "No unique identifier specified for this dataset",
                          "description": "No URI defined for this new dataset"
                        },
                        "_300": {
                          "id": "WS-DATASET-DELETE-300",
                          "level": "Fatal",
                          "name": "Can\'t delete meta-data information about the dataset",
                          "description": "An error occured when we tried to remove any meta-information about this dataset in our system"
                        },
                        "_301": {
                          "id": "WS-DATASET-DELETE-301",
                          "level": "Fatal",
                          "name": "Can\'t unregister the dataset in the system",
                          "description": "An error occured when we tried to delete the description of the dataset in the system"
                        },
                        "_302": {
                          "id": "WS-DATASET-DELETE-302",
                          "level": "Fatal",
                          "name": "Can\'t delete the graph in the triple store",
                          "description": "An error occured when we tried to delete the graph in the triple store"
                        },
                        "_303": {
                          "id": "WS-DATASET-DELETE-303",
                          "level": "Fatal",
                          "name": "Can\'t delete the reification graph in the system",
                          "description": "An error occured when we tried to delete the reification graph in the triple store"
                        },
                        "_304": {
                          "id": "WS-DATASET-DELETE-304",
                          "level": "Fatal",
                          "name": "Can\'t delete the dataset in Solr",
                          "description": "An error occured when we tried to delete that dataset in Solr"
                        },
                        "_305": {
                          "id": "WS-DATASET-DELETE-305",
                          "level": "Fatal",
                          "name": "Can\'t commit changes to the Solr index",
                          "description": "An error occured when we tried to commit changes to the Solr index"
                        }  
                      }';


  /*!   @brief Constructor
       @details   Initialize the Auth Web Service
          
      @param[in] $uri URI of the dataset to delete
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($uri, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->datasetUri = $uri; 
    
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/dataset/delete/";
    $this->title = "Dataset Delete Web Service";
    $this->crud_usage = new CrudUsage(FALSE, FALSE, FALSE, TRUE);
    $this->endpoint = $this->wsf_base_url . "/ws/dataset/delete/";

    $this->dtdURL = "dataset/datasetDelete.dtd";

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

  /*! @brief Validate a query to this web service
      
      @details If a user wants to delete information about a dataset on a given structWSF web service endpoint,
      he has to have access to the "http://.../wsf/datasets/" graph with Delete privileges, or to have
      Delete privileges on the dataset URI itself. If the users doesn't have these permissions, 
      then he won't be able to update the description of the dataset on that instance.
      
      By default, the administrators, and the creator of the dataset, have such an access on a structWSF instance. 
      However a system administrator can choose to make the "http://.../wsf/datasets/" world deletable,
      which would mean that anybody could update information about the datasets on the instance.          
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    // Check if the requester has access to the main "http://.../wsf/datasets/" graph.
    $ws_av = new AuthValidator($this->registered_ip, $this->wsf_graph . "datasets/", $this->uri);

    $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
      $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

    $ws_av->process();

    if($ws_av->pipeline_getResponseHeaderStatus() != 200)
    {
      // If he doesn't, then check if he has access to the dataset itself
      $ws_av2 = new AuthValidator($this->requester_ip, $this->datasetUri, $this->uri);

      $ws_av2->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_av2->process();

      if($ws_av2->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($ws_av2->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($ws_av2->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($ws_av2->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($ws_av2->pipeline_getError()->id, $ws_av2->pipeline_getError()->webservice,
          $ws_av2->pipeline_getError()->name, $ws_av2->pipeline_getError()->description,
          $ws_av2->pipeline_getError()->debugInfo, $ws_av2->pipeline_getError()->level);

        return;
      }
    }
    
    // If the system send a query on the behalf of another user, we validate that other user as well
    if($this->registered_ip != $this->requester_ip)
    {
      // Check if the requester has access to the main "http://.../wsf/datasets/" graph.
      $ws_av = new AuthValidator($this->registered_ip, $this->wsf_graph . "datasets/", $this->uri);

      $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_av->process();

      if($ws_av->pipeline_getResponseHeaderStatus() != 200)
      {
        // If he doesn't, then check if he has access to the dataset itself
        $ws_av2 = new AuthValidator($this->registered_ip, $this->datasetUri, $this->uri);

        $ws_av2->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
          $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

        $ws_av2->process();

        if($ws_av2->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->conneg->setStatus($ws_av2->pipeline_getResponseHeaderStatus());
          $this->conneg->setStatusMsg($ws_av2->pipeline_getResponseHeaderStatusMsg());
          $this->conneg->setStatusMsgExt($ws_av2->pipeline_getResponseHeaderStatusMsgExt());
          $this->conneg->setError($ws_av2->pipeline_getError()->id, $ws_av2->pipeline_getError()->webservice,
            $ws_av2->pipeline_getError()->name, $ws_av2->pipeline_getError()->description,
            $ws_av2->pipeline_getError()->debugInfo, $ws_av2->pipeline_getError()->level);

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
  public function pipeline_getResultset() { return ""; }

  /*!   @brief Inject the DOCType in a XML document
              
      \n
      
      @param[in] $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function injectDoctype($xmlDoc) { return ""; }

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, DatasetDelete::$supportedSerializations);

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      // Check for errors
      if($this->datasetUri == "")
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


  /*!   @brief Delete a dataset from the WSF
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      // Remove  all the possible other meta descriptions
      // of the dataset introduced by the wsf:meta property.

      $query = "  delete from <" . $this->wsf_graph . "datasets/> 
              { 
                ?meta ?p_meta ?o_meta.
              }
              where
              {
                graph <"
        . $this->wsf_graph
        . "datasets/>
                {
                  <$this->datasetUri> <http://purl.org/ontology/wsf#meta> ?meta.
                  ?meta ?p_meta ?o_meta.
                }
              }";

      @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
        FALSE));

      if(odbc_error())
      {
        $this->conneg->setStatus(500);
        $this->conneg->setStatusMsg("Internal Error");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
        $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
          $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, odbc_errormsg(),
          $this->errorMessenger->_300->level);

        return;
      }


      // Remove the Graph description in the ".../datasets/"

      $query = "  delete from <" . $this->wsf_graph . "datasets/> 
              { 
                <$this->datasetUri> ?p ?o.
              }
              where
              {
                graph <" . $this->wsf_graph . "datasets/>
                {
                  <$this->datasetUri> ?p ?o.
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

      // Removing all accesses for this graph
      $ws_ara = new AuthRegistrarAccess("", "", $this->datasetUri, "delete_all", "", "", $this->registered_ip);

      $ws_ara->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_ara->process();

      if($ws_ara->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($ws_ara->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($ws_ara->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($ws_ara->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($ws_ara->pipeline_getError()->id, $ws_ara->pipeline_getError()->webservice,
          $ws_ara->pipeline_getError()->name, $ws_ara->pipeline_getError()->description,
          $ws_ara->pipeline_getError()->debugInfo, $ws_ara->pipeline_getError()->level);
        return;
      }

      // Drop the entire graph
      $query = "exst('select * from (sparql clear graph <" . $this->datasetUri . ">) sub')";

      @$this->db->query($query);

      if(odbc_error())
      {
        $this->conneg->setStatus(500);
        $this->conneg->setStatusMsg("Internal Error");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
        $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
          $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
          $this->errorMessenger->_302->level);

        return;
      }

      // Drop the reification graph related to this dataset
      $query = "exst('select * from (sparql clear graph <" . $this->datasetUri . "reification/>) sub')";

      @$this->db->query($query);

      if(odbc_error())
      {
        $this->conneg->setStatus(500);
        $this->conneg->setStatusMsg("Internal Error");
        $this->conneg->setStatusMsgExt("Error #dataset-delete-105");
        $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
          $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, odbc_errormsg(),
          $this->errorMessenger->_303->level);

        return;
      }


      // Remove all documents from the solr index for this Dataset
      $solr = new Solr($this->wsf_solr_core, $this->solr_host, $this->solr_port);

      if(!$solr->flushDataset($this->datasetUri))
      {
        $this->conneg->setStatus(500);
        $this->conneg->setStatusMsg("Internal Error");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
        $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
          $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, odbc_errormsg(),
          $this->errorMessenger->_304->level);

        return;
      }

      if($this->solr_auto_commit === FALSE)
      {
        if(!$solr->commit())
        {
          $this->conneg->setStatus(500);
          $this->conneg->setStatusMsg("Internal Error");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_305->name);
          $this->conneg->setError($this->errorMessenger->_305->id, $this->errorMessenger->ws,
            $this->errorMessenger->_305->name, $this->errorMessenger->_305->description, odbc_errormsg(),
            $this->errorMessenger->_305->level);

          return;
        }
      }


    /*      
          if(!$solr->optimize())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt("Error #dataset-delete-104");  
            return;          
          }      
    */
    }
  }
}

//@}

?>