<?php

/** @defgroup WsTracker Tracker Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\tracker\create\TrackerCreate.php
    @brief Define the Crud Create web service
 */

namespace StructuredDynamics\structwsf\ws\tracker\create; 

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
use \StructuredDynamics\structwsf\ws\framework\Conneg;

/** Tracker Create web service. It tracks changes in the state of records.

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class TrackerCreate extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations = array ("text/xml", "text/*", "*/*");

  /** IP being registered */
  private $registered_ip = "";
  
  /**  Dataset where the record is indexed */
  private $fromDataset = "";

  /**  Record that got changed */
  private $record = "";

  /**  Action that has been performed on the record */
  private $action = "";

  /**  Serialization of the state (usually RDF description) of the record prior the performance of the 
              action on the record. */
  private $previousState = "";

  /**  MIME type of the serialization of the previous state of a record. Usually, application/rdf+xml or 
              application/rdf+n3. */
  private $previousStateMime = "";

  /**  Performer of the action on the target record. */
  private $performer = "";

  /** Requester's IP used for request validation */
  private $requester_ip = "";

  /** Error messages of this web service */
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


/** Constructor
      
    @param $fromDataset Dataset where the record is indexed
    @param $record Record that got changed
    @param $action Action that has been performed on the record
    @param $previousState Serialization of the state (usually RDF description) of the record prior the 
                              performance of the action on the record.
    @param $previousStateMime MIME type of the serialization of the previous state of a record. Usually, 
                                  application/rdf+xml or application/rdf+n3.
    @param $performer Performer of the action on the target record.
    @param $registered_ip Target IP address registered in the WSF
    @param $requester_ip IP address of the requester

    @return returns NULL
  
    @author Frederick Giasson, Structured Dynamics LLC.
*/
  function __construct($fromDataset, $record, $action, $previousState, $previousStateMime, $performer,  $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

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

  /**  @brief Validate a query to this web service

      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
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

  /** Returns the error structure

      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getError() { return ($this->conneg->error); }


  /**  @brief Create a resultset in a pipelined mode based on the processed information by the Web service.

      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResultset() { return ""; }

  /** Inject the DOCType in a XML document

      @param $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function injectDoctype($xmlDoc) { return ""; }

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

  /** Serialize the web service answer.

      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function ws_serialize() { return ""; }

  /** Index the new instance records within all the systems that need it (usually Solr + Virtuoso).

      @author Frederick Giasson, Structured Dynamics LLC.
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