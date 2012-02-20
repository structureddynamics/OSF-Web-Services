<?php

/*! @ingroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \ws\auth\validator\AuthValidator.php
   @brief Define the Authentication / Registration web service
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Auth Validator Web Service. It validates queries to a web service of the web service framework linked to this authentication web service.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class AuthValidator extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Error message to report */
  private $errorMessages = "";

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Datasets requested by the requester */
  private $requested_datasets = "";

  /*! @brief Web service URI where the request has been made, and that is registered on this web service */
  private $requested_ws_uri = "";

  /*! @brief The validation answer of the query */
  private $valid = "False";

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/auth/validator/",
                        "_200": {
                          "id": "WS-AUTH-VALIDATOR-200",
                          "level": "Warning",
                          "name": "No requester IP available",
                          "description": "No requester IP address defined for this query"
                        },
                        "_201": {
                          "id": "WS-AUTH-VALIDATOR-201",
                          "level": "Warning",
                          "name": "No target dataset",
                          "description": "No target dataset defined for this query"
                        },
                        "_202": {
                          "id": "WS-AUTH-VALIDATOR-202",
                          "level": "Warning",
                          "name": "No web service URI available",
                          "description": "No target web service URI defined for this query"
                        },
                        "_203": {
                          "id": "WS-AUTH-VALIDATOR-203",
                          "level": "Warning",
                          "name": "Invalid target dataset IRI",
                          "description": "One of the IRI of the input target dataset(s) is not a valid IRI."
                        },
                        "_204": {
                          "id": "WS-AUTH-VALIDATOR-204",
                          "level": "Warning",
                          "name": "Invalid target web service IRI",
                          "description": "The IRI of the input web service is not a valid IRI."
                        },
                        "_300": {
                          "id": "WS-AUTH-VALIDATOR-300",
                          "level": "Fatal",
                          "name": "Can\'t get the CRUD permissions of the target web service",
                          "description": "An error occured when we tried to get the CRUD permissions of the target web service"
                        },
                        "_301": {
                          "id": "WS-AUTH-VALIDATOR-301",
                          "level": "Warning",
                          "name": "Target web service not registered",
                          "description": "Target web service not registered to this Web Services Framework"
                        },
                        "_302": {
                          "id": "WS-AUTH-VALIDATOR-302",
                          "level": "Fatal",
                          "name": "Can\'t get the list of datasets accessible to this user",
                          "description": "An error occured when we tried to get the list of datasets accessible to this user"
                        },
                        "_303": {
                          "id": "WS-AUTH-VALIDATOR-303",
                          "level": "Warning",
                          "name": "No access defined",
                          "description": "No access defined for this requester IP , dataset and web service"
                        },
                        "_304": {
                          "id": "WS-AUTH-VALIDATOR-304",
                          "level": "Warning",
                          "name": "No create permissions",
                          "description": "The target web service needs create access and the requested user doesn\'t have this access for that dataset."
                        },
                        "_305": {
                          "id": "WS-AUTH-VALIDATOR-305",
                          "level": "Warning",
                          "name": "No update permissions",
                          "description": "The target web service needs update access and the requested user doesn\'t have this access for that dataset."
                        },
                        "_306": {
                          "id": "WS-AUTH-VALIDATOR-306",
                          "level": "Warning",
                          "name": "No read permissions",
                          "description": "The target web service needs read access and the requested user doesn\'t have this access for that dataset."
                        },
                        "_307": {
                          "id": "WS-AUTH-VALIDATOR-307",
                          "level": "Warning",
                          "name": "No delete permissions",
                          "description": "The target web service needs delete access and the requested user doesn\'t have this access for that dataset."
                        }  
                      }';


  /*!   @brief Constructor
       @details Initialize the Auth Web Service
        
      @param[in] $requester_ip IP address of the requester
      @param[in] $requested_datasets Target dataset targeted by the query of the user
      @param[in] $requested_ws_uri Target web service endpoint accessing the target dataset
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($requester_ip, $requested_datasets, $requested_ws_uri)
  { 
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->requester_ip = $requester_ip;
    $this->requested_datasets = $requested_datasets;
    $this->requested_ws_uri = $requested_ws_uri;

    $this->uri = $this->wsf_base_url . "/wsf/ws/auth/validator/";
    $this->title = "Authentication Validator Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/auth/validator/";

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
  protected function validateQuery() { return TRUE; }

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, AuthValidator::$supportedSerializations);

    // Check for errors
    if($this->requester_ip == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);

      return;
    }

    if($this->requested_datasets == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setStatusMsgExt($this->errorMessenger->_->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
        $this->errorMessenger->_201->level);

      return;
    }

    if($this->requested_ws_uri == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_->name);
      $this->conneg->setStatusMsgExt($this->errorMessenger->_->name);
      $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
        $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
        $this->errorMessenger->_202->level);

      return;
    }
    
    $datasets = array();
    
    if(strpos($this->requested_datasets, ";") !== FALSE)
    {
      $datasets = explode(";", $this->requested_datasets);
    }
    else
    {
      array_push($datasets, $this->requested_datasets);
    }    
    
    foreach($datasets as $dataset)
    {
      if(!$this->isValidIRI($dataset))
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
        $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
          $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
          $this->errorMessenger->_203->level);

        unset($resultset);      
        
        return;    
      }
    }    
    
    if(!$this->isValidIRI($this->requested_ws_uri))
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_204->name);
      $this->conneg->setError($this->errorMessenger->_204->id, $this->errorMessenger->ws,
        $this->errorMessenger->_204->name, $this->errorMessenger->_204->description, "",
        $this->errorMessenger->_204->level);

      unset($resultset);      
      
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

  /*!   @brief Validate the request
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */

  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {  
      // Get the CRUD usage of the target web service
      $resultset =
        $this->db->query($this->db->build_sparql_query("select ?_wsf ?_create ?_read ?_update ?_delete from <"
        . $this->wsf_graph . "> where {?_wsf a <http://purl.org/ontology/wsf#WebServiceFramework>." .
        " ?_wsf <http://purl.org/ontology/wsf#hasWebService> <$this->requested_ws_uri>. " .
        "<$this->requested_ws_uri> <http://purl.org/ontology/wsf#hasCrudUsage> ?crudUsage. " .
        "?crudUsage <http://purl.org/ontology/wsf#create> ?_create; <http://purl.org/ontology/wsf#read> " .
        "?_read; <http://purl.org/ontology/wsf#update> ?_update; <http://purl.org/ontology/wsf#delete> " .
        "?_delete. }", array ('_wsf', '_create', '_read', '_update', '_delete'), FALSE));

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
      elseif(odbc_fetch_row($resultset))
      {
        $wsf = odbc_result($resultset, 1);
        $ws_create = odbc_result($resultset, 2);
        $ws_read = odbc_result($resultset, 3);
        $ws_update = odbc_result($resultset, 4);
        $ws_delete = odbc_result($resultset, 5);
      }

      unset($resultset);

      // Check if the web service is registered
      if($wsf == "")
      {
        $this->conneg->setStatus(500);
        $this->conneg->setStatusMsg("Internal Error");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
        $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
          $this->errorMessenger->_301->name, $this->errorMessenger->_301->description,
          "Target web service ($this->requested_ws_uri) not registered to this Web Services Framework",
          $this->errorMessenger->_301->level);
        return;
      }

      // Check the list of datasets
      $datasets = explode(";", $this->requested_datasets);

      foreach($datasets as $dataset)
      {
        // Decode potentially encoded ";" character.
        $dataset = str_ireplace("%3B", ";", $dataset);

        $query =
          "select ?_access ?_create ?_read ?_update ?_delete 
                from <" . $this->wsf_graph
          . "> 
                where 
                { 
                    {
                    ?_access <http://purl.org/ontology/wsf#webServiceAccess> <$this->requested_ws_uri>; 
                    <http://purl.org/ontology/wsf#datasetAccess> <$dataset>; 
                    <http://purl.org/ontology/wsf#registeredIP> ?ip; 
                    <http://purl.org/ontology/wsf#create> ?_create; 
                    <http://purl.org/ontology/wsf#read> ?_read; 
                    <http://purl.org/ontology/wsf#update> ?_update; 
                    <http://purl.org/ontology/wsf#delete> ?_delete. 
                    filter(str(?ip) = \"$this->requester_ip\").
                  }
                  UNION
                  {
                    ?_access <http://purl.org/ontology/wsf#webServiceAccess> <$this->requested_ws_uri>; 
                    <http://purl.org/ontology/wsf#datasetAccess> <$dataset>; 
                    <http://purl.org/ontology/wsf#registeredIP> ?ip; 
                    <http://purl.org/ontology/wsf#create> ?_create; 
                    <http://purl.org/ontology/wsf#read> ?_read; 
                    <http://purl.org/ontology/wsf#update> ?_update; 
                    <http://purl.org/ontology/wsf#delete> ?_delete. 
                    filter(str(?ip) = \"0.0.0.0\").
                  }
                }";

        $resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
          array ('_access', '_create', '_read', '_update', '_delete'), FALSE));

        $access = array();
        $create = array();
        $read = array();
        $update = array();
        $delete = array();

        if(odbc_error())
        {
          $this->conneg->setStatus(500);
          $this->conneg->setStatusMsg("Internal Error");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
          $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
            $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
            $this->errorMessenger->_302->level);
        }

        while(odbc_fetch_row($resultset))
        {
          array_push($access, strtolower(odbc_result($resultset, 1)));
          array_push($create, strtolower(odbc_result($resultset, 2)));
          array_push($read, strtolower(odbc_result($resultset, 3)));
          array_push($update, strtolower(odbc_result($resultset, 4)));
          array_push($delete, strtolower(odbc_result($resultset, 5)));
        }

        unset($resultset);

        // Check if an access is defined for this IP, dataset and registered web service
        if(count($access) <= 0)
        {          
          $this->conneg->setStatus(403);
          $this->conneg->setStatusMsg("Forbidden");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
          $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
            $this->errorMessenger->_303->name, $this->errorMessenger->_303->description,
            "No access defined for this requester IP ($this->requester_ip), dataset ($dataset) and web service ($this->requested_ws_uri)",
            $this->errorMessenger->_303->level);
          return;
        }

        // Check if the user has permissions to perform one of the CRUD operation needed by the web service

        if(strtolower($ws_create) == "true")
        {
          if(array_search("true", $create) === FALSE)
          {
            $this->conneg->setStatus(403);
            $this->conneg->setStatusMsg("Forbidden");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
            $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
              $this->errorMessenger->_304->name, $this->errorMessenger->_304->description,
              "The target web service ($this->requested_ws_uri) needs create access and the requested user ($this->requester_ip) doesn't have this access for that dataset ($dataset).",
              $this->errorMessenger->_304->level);
          }
        }

        if(strtolower($ws_update) == "true")
        {
          if(array_search("true", $update) === FALSE)
          {
            $this->conneg->setStatus(403);
            $this->conneg->setStatusMsg("Forbidden");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_305->name);
            $this->conneg->setError($this->errorMessenger->_305->id, $this->errorMessenger->ws,
              $this->errorMessenger->_305->name, $this->errorMessenger->_305->description,
              "The target web service ($this->requested_ws_uri) needs update access and the requested user ($this->requester_ip) doesn't have this access for that dataset ($dataset).",
              $this->errorMessenger->_305->level);
          }
        }

        if(strtolower($ws_read) == "true")
        {
          if(array_search("true", $read) === FALSE)
          {
            $this->conneg->setStatus(403);
            $this->conneg->setStatusMsg("Forbidden");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_306->name);
            $this->conneg->setError($this->errorMessenger->_306->id, $this->errorMessenger->ws,
              $this->errorMessenger->_306->name, $this->errorMessenger->_306->description,
              "The target web service ($this->requested_ws_uri) needs read access and the requested user ($this->requester_ip) doesn't have this access for that dataset ($dataset).",
              $this->errorMessenger->_306->level);

            return;
          }
        }

        if(strtolower($ws_delete) == "true")
        {
          if(array_search("true", $delete) === FALSE)
          {
            $this->conneg->setStatus(403);
            $this->conneg->setStatusMsg("Forbidden");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_307->name);
            $this->conneg->setError($this->errorMessenger->_307->id, $this->errorMessenger->ws,
              $this->errorMessenger->_307->name, $this->errorMessenger->_307->description,
              "The target web service needs delete access and the requested user doesn't have this access for that dataset.",
              $this->errorMessenger->_307->level);

            return;
          }
        }
      }
    }
  }
}

//@}

?>