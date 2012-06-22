<?php

/*! @ingroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\auth\registrar\ws\AuthRegistrarWs.php
    @brief Define the Authentication / Registration web service
 */

namespace StructuredDynamics\structwsf\ws\auth\registrar\ws;   

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
use \StructuredDynamics\structwsf\ws\framework\Conneg;

/** AuthRegister WS Web Service. It registers a Web Service endpoint on the structWSF instance

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class AuthRegistrarWs extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /** Title of the service being registered */
  private $registered_title = "";

  /** Endpoint of the service being registered */
  private $registered_endpoint = "";

  /** CRUD usage of the service being registered */
  private $registered_crud_usage = "";

  /** Web service URI being registered */
  private $registered_uri = "";

  /** Requester's IP used for request validation */
  private $requester_ip = "";

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/auth/registrar/ws/",
                        "_200": {
                          "id": "WS-AUTH-REGISTRAR-WS-200",
                          "level": "Warning",
                          "name": "No endpoint URL",
                          "description": "No endpoint URL defined for this query."
                        },
                        "_201": {
                          "id": "WS-AUTH-REGISTRAR-WS-201",
                          "level": "Warning",
                          "name": "No crud usage defined",
                          "description": "No crud usage defined for this query."
                        },
                        "_202": {
                          "id": "WS-AUTH-REGISTRAR-WS-202",
                          "level": "Warning",
                          "name": "No web service URI defined",
                          "description": "No web service URI defined for this query."
                        },
                        "_203": {
                          "id": "WS-AUTH-REGISTRAR-WS-203",
                          "level": "Fatal",
                          "name": "Can\'t check of the web service was already registered to this WSF",
                          "description": "An error occured when we tried to check if the web service was already registered to this web service network."
                        },
                        "_204": {
                          "id": "WS-AUTH-REGISTRAR-WS-204",
                          "level": "Warning",
                          "name": "Web service already registered",
                          "description": "This web service is already registered to this Web Service Framework."
                        },
                        "_300": {
                          "id": "WS-AUTH-REGISTRAR-WS-300",
                          "level": "Fatal",
                          "name": "Can\'t register this web service to the network",
                          "description": "An error occured when we tried to register this new web service to the network."
                        }  
                      }';


  /** Constructor

      @param $registered_title Title of the web service to register
      @param $registered_endpoint URL of the endpoint where to send the HTTP queries
      @param $registered_crud_usage   A quadruple with a value "True" or "False" defined as 
                                <Create;Read;Update;Delete>. Each value is separated by the ";" 
                                character. an example of such a quadruple is: "crud_usage=True;True;False;False", 
                                meaning: Create = True, Read = True, Update = False and Delete = False
      @param $registered_uri URI of the web service endpoint to register
      @param $registered_ip Target IP address registered in the WSF         
      @param $requester_ip IP address of the requester
      
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($registered_title, $registered_endpoint, $registered_crud_usage, $registered_uri, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->registered_title = $registered_title;
    $this->registered_endpoint = $registered_endpoint;
    $this->registered_crud_usage = $registered_crud_usage;
    $this->registered_uri = $registered_uri;
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
    
    $this->uri = $this->wsf_base_url . "/wsf/ws/auth/registrar/ws/";
    $this->title = "Authentication Web Service Registration Web Service";
    $this->crud_usage = new CrudUsage(TRUE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/auth/registrar/ws/";

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
    
    // If the system send a query on the behalf of another user, we validate that other user as well
    if($this->registered_ip != $this->requester_ip)
    {
      $ws_av = new AuthValidator($this->registered_ip, $this->wsf_graph, $this->uri);

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
  public function injectDoctype($xmlDoc){ }
  
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
    $this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language,
      AuthRegistrarWs::$supportedSerializations);

    // Check for errors
    if($this->registered_endpoint == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);
      return;
    }

    if($this->registered_crud_usage == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
        $this->errorMessenger->_201->level);

      return;
    }

    if($this->registered_uri == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
      $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
        $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
        $this->errorMessenger->_202->level);

      return;
    }

    // Check if the web service is already registered
    $resultset =
      $this->db->query($this->db->build_sparql_query("select ?wsf ?crudUsage from <" . $this->wsf_graph
        . "> where {?wsf a <http://purl.org/ontology/wsf#WebServiceFramework>. ?wsf <http://purl.org/ontology/wsf#hasWebService> <$this->registered_uri>. <$this->registered_uri> <http://purl.org/ontology/wsf#hasCrudUsage> ?crudUsage.}",
        array ('wsf', 'crudUsage'), FALSE));

    if(odbc_error())
    {
      $this->conneg->setStatus(500);
      $this->conneg->setStatusMsg("Internal Error");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
      $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
        $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
        $this->errorMessenger->_203->level);

      return;
    }
    elseif(odbc_fetch_row($resultset))
    {
      $wsf = odbc_result($resultset, 1);
      $crud_usage = odbc_result($resultset, 2);

      if($wsf != "" && $crud_usage != "")
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

    unset($resultset);
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
   
  /** Register a new Web Service endpoint to the structWSF instance

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
// Create and describe the resource being registered
// Note: we make sure we remove any previously defined triples that we are about to re-enter in the graph. All information other than these new properties
//       will remain in the graph

        $query = "delete from <" . $this->wsf_graph . ">
                { 
                  <$this->registered_uri> a <http://purl.org/ontology/wsf#WebService> .
                  <$this->registered_uri> <http://purl.org/dc/terms/title> ?title . 
                  <$this->registered_uri> <http://purl.org/ontology/wsf#endpoint> ?endpoint .
                  <$this->registered_uri> <http://purl.org/ontology/wsf#hasCrudUsage> ?crud_usage .
                  ?crud_usage ?crud_property ?crud_value .
                }
                where
                {
                  graph <" . $this->wsf_graph . ">
                  {
                    <$this->registered_uri> a <http://purl.org/ontology/wsf#WebService> .
                    <$this->registered_uri> <http://purl.org/dc/terms/title> ?title . 
                    <$this->registered_uri> <http://purl.org/ontology/wsf#endpoint> ?endpoint .
                    <$this->registered_uri> <http://purl.org/ontology/wsf#hasCrudUsage> ?crud_usage .
                    ?crud_usage ?crud_property ?crud_value .
                  }
                }
                insert into <" . $this->wsf_graph . ">
                {
                  <$this->registered_uri> a <http://purl.org/ontology/wsf#WebService> .
                  <$this->registered_uri> <http://purl.org/dc/terms/title> \"$this->registered_title\" .
                  <$this->registered_uri> <http://purl.org/ontology/wsf#endpoint> \"$this->registered_endpoint\" .
                  <$this->registered_uri> <http://purl.org/ontology/wsf#hasCrudUsage> <" . $this->registered_uri . "usage/> .
                  
                  <" . $this->registered_uri . "usage/> a <http://purl.org/ontology/wsf#CrudUsage> ;
                  <http://purl.org/ontology/wsf#create> " . ($this->crud_usage->create ? "\"True\"" : "\"False\"") . " ;
                  <http://purl.org/ontology/wsf#read> " . ($this->crud_usage->read ? "\"True\"" : "\"False\"") . " ;
                  <http://purl.org/ontology/wsf#update> " . ($this->crud_usage->update ? "\"True\"" : "\"False\"") . " ;
                  <http://purl.org/ontology/wsf#delete> " . ($this->crud_usage->delete ? "\"True\"" : "\"False\"") . " .
                  
                  <" . $this->wsf_graph . "> <http://purl.org/ontology/wsf#hasWebService> <$this->registered_uri>.
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
      }
    }
  }
}

//@}

?>