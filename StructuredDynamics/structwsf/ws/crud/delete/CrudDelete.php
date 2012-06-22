<?php

/** @defgroup WsCrud Crud Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\crud\delete\CrudDelete.php
    @brief Define the Crud Delete web service
 */

namespace StructuredDynamics\structwsf\ws\crud\delete;

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\ws\dataset\read\DatasetRead;
use \StructuredDynamics\structwsf\ws\crud\read\CrudRead;
use \StructuredDynamics\structwsf\framework\WebServiceQuerier;
use \StructuredDynamics\structwsf\ws\framework\Solr;

/** CRUD Delete web service. It removes record instances within dataset indexes on different systems (Virtuoso, Solr, etc).

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class CrudDelete extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;
  
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /** IP being registered */
  private $registered_ip = "";

  /** Dataset where to index the resource*/
  private $dataset;

  /** URI of the resource to delete */
  private $resourceUri;

  /** Requester's IP used for request validation */
  private $requester_ip = "";

  /** Error messages of this web service */
  private $errorMessenger =
    '{
      "ws": "/ws/crud/delete/",
      "_200": {
        "id": "WS-CRUD-DELETE-200",
        "level": "Warning",
        "name": "No resource URI to delete specified",
        "description": "No resource URI has been defined for this query"
      },
      "_201": {
        "id": "WS-CRUD-DELETE-201",
        "level": "Warning",
        "name": "No dataset specified",
        "description": "No dataset URI defined for this query"
      },
      "_300": {
        "id": "WS-CRUD-DELETE-300",
        "level": "Fatal",
        "name": "Can\'t delete the record in the triple store",
        "description": "An error occured when we tried to delete that record in the triple store"
      },
      "_301": {
        "id": "WS-CRUD-DELETE-301",
        "level": "Fatal",
        "name": "Can\'t delete the record in Solr",
        "description": "An error occured when we tried to delete that record in Solr"
      },
      "_302": {
        "id": "WS-CRUD-DELETE-302",
        "level": "Fatal",
        "name": "Can\'t commit changes to the Solr index",
        "description": "An error occured when we tried to commit changes to the Solr index"
      },
      "_303": {
        "id": "WS-CRUD-CREATE-303",
        "level": "Fatal",
        "name": "Can\'t create a tracking record for one of the input records",
        "description": "We can\'t create the records because we can\'t ensure that we have a track of their changes."
      }  
    }';


  /** Constructor
      
      @param $uri URI of the instance record to delete
      @param $dataset URI of the dataset where the instance record is indexed
      @param $registered_ip Target IP address registered in the WSF
      @param $requester_ip IP address of the requester

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($uri, $dataset, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->requester_ip = $requester_ip;
    $this->dataset = $dataset;
    $this->resourceUri = $uri;

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

    $this->uri = $this->wsf_base_url . "/wsf/ws/crud/delete/";
    $this->title = "Crud Delete Web Service";
    $this->crud_usage = new CrudUsage(FALSE, FALSE, FALSE, TRUE);
    $this->endpoint = $this->wsf_base_url . "/ws/crud/delete/";

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
    
      @author Frederick Giasson, Structured Dynamics LLC.
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudDelete::$supportedSerializations);

    // Check for errors

    if($this->uri == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);

      return;
    }

    if($this->dataset == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
        $this->errorMessenger->_201->level);

      return;
    }

    // Check if the dataset is created

    $ws_dr = new DatasetRead($this->dataset, "false", "self",
      $this->wsf_local_ip); // Here the one that makes the request is the WSF (internal request).

    $ws_dr->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
      $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

    $ws_dr->process();

    if($ws_dr->pipeline_getResponseHeaderStatus() != 200)
    {
      $this->conneg->setStatus($ws_dr->pipeline_getResponseHeaderStatus());
      $this->conneg->setStatusMsg($ws_dr->pipeline_getResponseHeaderStatusMsg());
      $this->conneg->setStatusMsgExt($ws_dr->pipeline_getResponseHeaderStatusMsgExt());
      $this->conneg->setError($ws_av->pipeline_getError()->id, $ws_av->pipeline_getError()->webservice,
        $ws_av->pipeline_getError()->name, $ws_av->pipeline_getError()->description,
        $ws_av->pipeline_getError()->debugInfo, $ws_av->pipeline_getError()->level);

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

  /** Delete an instance record from all systems that are indexing it 9usually Virtuoso and Solr)

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
        
        // Track the record description changes
        if($this->track_delete === TRUE)
        {
          // First check if the record is already existing for this record, within this dataset.
          $ws_cr = new CrudRead($this->resourceUri, $this->dataset, FALSE, TRUE, $this->registered_ip, $this->requester_ip);
          
          $ws_cr->ws_conneg("application/rdf+xml", "utf-8", "identity", "en");

          $ws_cr->process();

          $oldRecordDescription = $ws_cr->ws_serialize();
          
          $ws_cr_error = $ws_cr->pipeline_getError();
          
          if($ws_cr->pipeline_getResponseHeaderStatus() != 200)
          {
            // An error occured. Since we can't get the past state of a record, we have to send an error
            // for the CrudUpdate call since we can't create a tracking record for this record.
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
              $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, 
              "We can't create a track record for the following record: ".$this->resourceUri,
              $this->errorMessenger->_303->level);
              
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
          
          $wsq = new WebServiceQuerier($endpoint, "post",
            "text/xml", "from_dataset=" . urlencode($this->dataset) .
            "&record=" . urlencode($this->resourceUri) .
            "&action=delete" .
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
        
        // Delete all triples for this URI in that dataset
        $query = "delete from <" . $this->dataset . ">
                { 
                  <" . $this->resourceUri . "> ?p ?o. 
                }
                where
                {
                  <" . $this->resourceUri . "> ?p ?o. 
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

        // Delete the Solr document in the Solr index
        $solr = new Solr($this->wsf_solr_core, $this->solr_host, $this->solr_port, $this->fields_index_folder);

        if(!$solr->deleteInstanceRecord($this->resourceUri, $this->dataset))
        {
          $this->conneg->setStatus(500);
          $this->conneg->setStatusMsg("Internal Error");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
          $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
            $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, odbc_errormsg(),
            $this->errorMessenger->_301->level);

          return;
        }

        if($this->solr_auto_commit === FALSE)
        {
          if(!$solr->commit())
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
      }
    }
  }
}

//@}

?>