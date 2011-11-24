<?php

/*! @ingroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \ws\auth\registrar\access\AuthRegistrarAccess.php
   @brief Define the Authentication / Registration web service
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief AuthRegister Access Web Service. It registers an Access on the structWSF instance
     @details Register an Access (user access to a dataset, for a given set of web services, with some CRUD permissions) on the
                structWSF instance
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class AuthRegistrarAccess extends WebService
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

  /*! @brief CRUD access being registered */
  private $crud;

  /*! @brief WS URIs being registered */
  private $ws_uris = array();

  /*! @brief Dataset being registered */
  private $dataset = "";

  /*! @brief Requester's IP used for request validation */
  private $requester_ip = "";

  /*! @brief URI of the access to update if action=update */
  private $target_access_uri = "";

  /*! @brief Type of action to perform: (1) create (2) delete_target (3) delete_all (4) update */
  private $action = "";

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/auth/registrar/access/",
                        "_200": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-200",
                          "level": "Warning",
                          "name": "Action type undefined",
                          "description": "No type of \'action\' has been defined for this query."
                        },
                        "_201": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-201",
                          "level": "Warning",
                          "name": "No IP to register",
                          "description": "No IP address has been defined for this query."
                        },
                        "_202": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-202",
                          "level": "Warning",
                          "name": "No crud access defined",
                          "description": "No crud access have been defined for this query."
                        },
                        "_203": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-203",
                          "level": "Warning",
                          "name": "No web service URI(s) defined",
                          "description": "No web service URI(s) have been defined for this query."
                        },
                        "_204": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-204",
                          "level": "Warning",
                          "name": "No dataset defined",
                          "description": "No dataset has been defined for this query."
                        },
                        "_205": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-205",
                          "level": "Warning",
                          "name": "No target Access URI defined for update",
                          "description": "No target Access URI has been defined to be updated for this query."
                        },
                        "_300": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-300",
                          "level": "Fatal",
                          "name": "Can\'t create the access to this dataset",
                          "description": "An error occured when we tried to create the new access to this dataset"
                        },
                        "_301": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-301",
                          "level": "Fatal",
                          "name": "Can\'t update the access to this dataset",
                          "description": "An error occured when we tried to update the new access to this dataset"
                        },
                        "_302": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-302",
                          "level": "Fatal",
                          "name": "Can\'t delete the access to this dataset",
                          "description": "An error occured when we tried to delete the new access to this dataset"
                        },  
                        "_303": {
                          "id": "WS-AUTH-REGISTRAR-ACCESS-303",
                          "level": "Fatal",
                          "name": "Can\'t delete all accesses to this dataset",
                          "description": "An error occured when we tried to delete all accesses to this dataset"
                        }  
                      }';

  /*!   @brief Constructor
       @details Initialize the Auth Web Service
              
      \n
      
      @param[in] $crud   A quadruple with a value "True" or "False" defined as <Create;Read;Update;Delete>. 
                    Each value is separated by the ";" character. an example of such a quadruple is:
                    "crud=True;True;False;False", meaning: Create = True,
                    Read = True, Update = False and Delete = False
      @param[in] $ws_uris A list of ";" separated Web services URI accessible by this access  definition
      @param[in] $dataset URI of the target dataset of this access  description
      @param[in] $action One of:  (1)"create (default)": Create a new access description
                                (2) "delete_target": Delete a target access description for a specific IP address and a specific dataset
                                (3) "delete_all": Delete all access descriptions for a target dataset
                                (4) "update": Update an existing access description 
      @param[in] $target_access_uri Target URI of the access resource to update. Only used when param4 = update
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($crud, $ws_uris, $dataset, $action, $target_access_uri, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->registered_ip = $registered_ip;
    $this->target_access_uri = $target_access_uri;

    $crud = explode(";", $crud);

    $this->crud = new CrudUsage((strtolower($crud[0]) == "true" ? TRUE : FALSE), (strtolower($crud[1])
      == "true" ? TRUE : FALSE), (strtolower($crud[2]) == "true" ? TRUE : FALSE), (strtolower($crud[3])
      == "true" ? TRUE : FALSE));

    $this->ws_uris = explode(";", $ws_uris);
    $this->dataset = $dataset;
    $this->requester_ip = $requester_ip;
    $this->action = $action;

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

    $this->uri = $this->wsf_base_url . "/wsf/ws/auth/registrar/access/";
    $this->title = "Authentication Access Registration Web Service";
    $this->crud_usage = new CrudUsage(TRUE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/auth/registrar/access/";

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
    $ws_av = new AuthValidator($this->requester_ip, $this->wsf_graph, $this->uri);

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
    }
  }

  /*!   @brief Normalize the remaining of a URI
              
      \n
      
      @param[in] $uri The remaining of a URI to normalize
      
      @return a Normalized remaining URI
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function uriEncode($uri)
  {
    $uri = preg_replace("|[^a-zA-z0-9]|", " ", $uri);
    $uri = preg_replace("/\s+/", " ", $uri);
    $uri = str_replace(" ", "_", $uri);

    return ($uri);
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
    $this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language,
      AuthRegistrarAccess::$supportedSerializations);

    if(strtolower($this->action) != "create" && strtolower($this->action) != "delete_target"
      && strtolower($this->action) != "delete_all" && strtolower($this->action) != "update")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);
      return;
    }


    // Check for errors
    if($this->registered_ip == "" && strtolower($this->action) != "delete_all")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
        $this->errorMessenger->_201->level);
      return;
    }

    if(strtolower($this->action) != "delete_target" && strtolower($this->action) != "delete_all")
    {
      // Only need this information for create/update
      if($this->crud == "")
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

    if(strtolower($this->action) != "delete_target" && strtolower($this->action) != "delete_all")
    {
      // Only need this information for create/update
      if(count($this->ws_uris) <= 0 || $this->ws_uris[0] == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
        $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
          $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
          $this->errorMessenger->_203->level);
        return;
      }
    }

    if($this->dataset == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_204->name);
      $this->conneg->setError($this->errorMessenger->_204->id, $this->errorMessenger->ws,
        $this->errorMessenger->_204->name, $this->errorMessenger->_204->description, "",
        $this->errorMessenger->_204->level);
      return;
    }

    if(strtolower($this->action) == "update" && $this->target_access_uri == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_205->name);
      $this->conneg->setError($this->errorMessenger->_205->id, $this->errorMessenger->ws,
        $this->errorMessenger->_205->name, $this->errorMessenger->_205->description, "",
        $this->errorMessenger->_205->level);
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


  /*!   @brief Register the Access to the WSF
              
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
        if(strtolower($this->action) == "create")
        {
          // Create and describe the resource being registered
          // Note: we make sure we remove any previously defined triples that we are about to re-enter in the graph. 
          //       All information other than these new properties will remain in the graph

          $query = "delete from graph <" . $this->wsf_graph . ">
                  { 
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <$this->dataset> ;
                    ?p ?o.
                  }
                  where
                  {
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <$this->dataset> ;
                    ?p ?o.
                  }
                  insert into <"
            . $this->wsf_graph . ">
                  {
                    <" . $this->wsf_graph . "access/" . md5($this->registered_ip . $this->dataset)
            . "> a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <$this->dataset> ;";

          foreach($this->ws_uris as $uri)
          {
            if($uri != "")
            {
              $query .= "<http://purl.org/ontology/wsf#webServiceAccess> <$uri> ;";
            }
          }

          $query .= "  <http://purl.org/ontology/wsf#create> " . ($this->crud->create ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#read> " . ($this->crud->read ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#update> " . ($this->crud->update ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#delete> " . ($this->crud->delete ? "\"True\"" : "\"False\"") . " .
                  }";

          $this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
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
        }
        elseif(strtolower($this->action) == "update")
        {
          // Update and describe the resource being registered

          $query = "modify graph <" . $this->wsf_graph . ">
                  delete
                  { 
                    <$this->target_access_uri> a <http://purl.org/ontology/wsf#Access> ;
                    ?p ?o.
                  }
                  insert
                  {
                    <"
            . $this->wsf_graph . "access/" . md5($this->registered_ip . $this->dataset)
            . "> a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <$this->dataset> ;";

          foreach($this->ws_uris as $uri)
          {
            if($uri != "")
            {            
              $query .= "<http://purl.org/ontology/wsf#webServiceAccess> <$uri> ;";
            }
          }

          $query .= "  <http://purl.org/ontology/wsf#create> " . ($this->crud->create ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#read> " . ($this->crud->read ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#update> " . ($this->crud->update ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#delete> " . ($this->crud->delete ? "\"True\"" : "\"False\"")
            . " .
                  }                  
                  where
                  {
                    <$this->target_access_uri> a <http://purl.org/ontology/wsf#Access> ;
                    ?p ?o.
                  }";

          @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
            $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
              $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, odbc_errormsg() . $query,
              $this->errorMessenger->_301->level);
            return;
          }
        }
        elseif(strtolower($this->action) == "delete_target")
        {
          // Just delete target access
          $query =
            "delete from graph <" . $this->wsf_graph
            . ">
                  { 
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <$this->dataset> ;
                    ?p ?o.
                  }
                  where
                  {
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <$this->dataset> ;
                    ?p ?o.
                  }";

          @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

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
        }
        else
        {
          // Delete all access to a specific dataset
          $query =
            "delete from graph <" . $this->wsf_graph
            . ">
                  { 
                    ?access ?p ?o. 
                  }
                  where
                  {
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#datasetAccess> <$this->dataset> ;
                    ?p ?o.
                  }";

          @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
              $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, odbc_errormsg(),
              $this->errorMessenger->_303->level);
            return;
          }
        }
      }
    }
  }
}

//@}

?>