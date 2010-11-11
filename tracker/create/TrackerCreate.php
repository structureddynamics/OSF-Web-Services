<?php

/*! @defgroup WsTracker Tracker Web Service */
//@{

/*! @file \ws\tracker\create\TrackerCreate.php
   @brief Define the Crud Create web service

   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Tracker Create web service. It tracks changes in the state of records.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class TrackerCreate extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations = array ("text/xml", "text/*", "*/*");

  /*! @brief IP being registered */
  private $registered_ip = "";
  
  /*! @brief  Dataset where the record is indexed */
  private $fromDataset = "";

  /*! @brief  Record that got changed */
  private $record = "";

  /*! @brief  Action that has been performed on the record */
  private $action = "";

  /*! @brief  Serialization of the state (usually RDF description) of the record prior the performance of the 
              action on the record. */
  private $previousState = "";

  /*! @brief  MIME type of the serialization of the previous state of a record. Usually, application/rdf+xml or 
              application/rdf+n3. */
  private $previousStateMime = "";

  /*! @brief  Performer of the action on the target record. */
  private $performer = "";

  /*! @brief Requester's IP used for request validation */
  private $requester_ip = "";

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
      "ws": "/ws/tracker/create/",
      "_200": {
        "id": "WS-TRACKER-CREATE-200",
        "level": "Fatal",
        "name": "No dataset provenance defined.",
        "description": "The provenance of the record as to be specified."
      },
      "_201": {
        "id": "WS-TRACKER-CREATE-201",
        "level": "Fatal",
        "name": "State serialization mime not supported.",
        "description": "Only the application/rdf+xml and application/rdf+n3 mime types are supported by the tracker."
      },
      "_202": {
        "id": "WS-TRACKER-CREATE-202",
        "level": "Fatal",
        "name": "No record defined",
        "description": "No changed record has been defined for this query."
      },
      "_203": {
        "id": "WS-TRACKER-CREATE-203",
        "level": "Fatal",
        "name": "No performer defined.",
        "description": "The performer of the action needs to be defined."
      },
      "_204": {
        "id": "WS-TRACKER-CREATE-204",
        "level": "Fatal",
        "name": "Unsupported action",
        "description": "Only the actions \'delete\', \'create\' and \'update\' are supported by the tracker"
      }
    }';


/*!   @brief Constructor
     @details   Initialize the Crud Create
      
    @param[in] $fromDataset Dataset where the record is indexed
    @param[in] $record Record that got changed
    @param[in] $action Action that has been performed on the record
    @param[in] $previousState Serialization of the state (usually RDF description) of the record prior the 
                              performance of the action on the record.
    @param[in] $previousStateMime MIME type of the serialization of the previous state of a record. Usually, 
                                  application/rdf+xml or application/rdf+n3.
    @param[in] $performer Performer of the action on the target record.
    @param[in] $registered_ip Target IP address registered in the WSF
    @param[in] $requester_ip IP address of the requester
            
    \n
    
    @return returns NULL
  
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
  function __construct($fromDataset, $record, $action, $previousState, $previousStateMime, $performer,  $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->requester_ip = $requester_ip;

    $this->fromDataset = $fromDataset;
    $this->record = $record;
    $this->action = $action;
//    $this->previousState = urlencode(gzencode($previousState));
    $this->previousState = base64_encode(gzencode($previousState));
    $this->previousStateMime = $previousStateMime;
    $this->performer = $performer;
    
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
    
    $this->uri = $this->wsf_base_url . "/wsf/ws/tracker/create/";
    $this->title = "Tracker Create Web Service";
    $this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/tracker/create/";

    $this->dtdURL = "auth/TrackerCreate.dtd";

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

  /*!  @brief Validate a query to this web service
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    // Validation of the "requester_ip" to make sure the system that is sending the query as the rights.
    $ws_av = new AuthValidator($this->requester_ip, $this->wsf_graph."track/", $this->uri);

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
      $ws_av = new AuthValidator($this->registered_ip, $this->wsf_graph."track/", $this->uri);

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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Tracker Create DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, TrackerCreate::$supportedSerializations);

    // Check for errors
    if($this->fromDataset == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);
      return;
    }

    if($this->previousStateMime != "application/rdf+xml" && $this->previousStateMime != "application/rdf+n3")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, ($this->mime),
        $this->errorMessenger->_201->level);
      return;
    }

    if($this->record == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
      $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
        $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
        $this->errorMessenger->_202->level);
      return;
    }    
    
    if($this->performer == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
      $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
        $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
        $this->errorMessenger->_203->level);
      return;
    }

    if($this->action != "delete" && $this->action != "update" && $this->action != "create")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_204->name);
      $this->conneg->setError($this->errorMessenger->_204->id, $this->errorMessenger->ws,
        $this->errorMessenger->_204->name, $this->errorMessenger->_204->description, "",
        $this->errorMessenger->_204->level);
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


  /*!   @brief Index the new instance records within all the systems that need it (usually Solr + Virtuoso).
              
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
        $dateTime = date("c");

        /*
          Ordered changes for a record using sparql and this part of the WSF ontology.
        
          sparql select * from <http://.../wsf/track/> where 
          {
            ?s <http://purl.org/ontology/wsf#record> <http://.../wsf/datasets/67/resource/Welfare> .

            ?s <http://purl.org/ontology/wsf#changeTime> ?time.
          }
          ORDER BY asc(xsd:dateTime(?time));
        */
        
        $trackRecord = "<".$this->wsf_graph."track/record/".md5($dateTime.$this->record.$this->fromDataset)."> 
                         a <http://purl.org/ontology/wsf#ChangeState> ;";
                         
        $trackRecord .= "<http://purl.org/ontology/wsf#record> <".$this->record."> ;";
        $trackRecord .= "<http://purl.org/ontology/wsf#fromDataset> <".$this->fromDataset."> ;";
        $trackRecord .= "<http://purl.org/ontology/wsf#changeTime> \"".$dateTime."\"^^xsd:dateTime ;";
        $trackRecord .= "<http://purl.org/ontology/wsf#action> \"".$this->action."\" ;";
        $trackRecord .= "<http://purl.org/ontology/wsf#previousState> \"\"\"".$this->previousState."\"\"\" ;";
        $trackRecord .= "<http://purl.org/ontology/wsf#previousStateMime> \"".$this->previousStateMime."\" ;";
        $trackRecord .= "<http://purl.org/ontology/wsf#performer> \"".$this->performer."\" .";
        
        $this->db->query("DB.DBA.TTLP_MT('"
          . addslashes($trackRecord) . "', '" . $this->wsf_graph."track/" . "', '"
          . $this->wsf_graph."track/" . "')");

        if(odbc_error())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
            $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
            $this->errorMessenger->_302->level);

          return;
        }
      }
    }
  }
}


//@}

?>