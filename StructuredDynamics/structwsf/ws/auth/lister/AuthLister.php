<?php

/** @defgroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\auth\lister\AuthLister.php
    @brief Lists registered web services and available datasets
 */

namespace StructuredDynamics\structwsf\ws\auth\lister;  

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\auth\validator\AuthValidator;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\framework\Subject;

/** AuthLister Web Service. It lists registered web services and available dataset
            
    @author Frederick Giasson, Structured Dynamics LLC.
*/

class AuthLister extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** IP of the requester */
  private $requester_ip = "";

  /** Requested IP (ex: a node wants to see all web services or datasets accessible for one of its user) */
  private $registered_ip = "";

  /** Target dataset URI if action = "access_dataset" */
  private $dataset = "";

  /** Type of the thing to list */
  private $mode = "";
  
  /** Specifies what web service we want to focus on for that query */
  private $targetWebservice = "all";

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/auth/lister/",
                        "_200": {
                          "id": "WS-AUTH-LISTER-200",
                          "level": "Warning",
                          "name": "Unknown Listing Mode",
                          "description": "The mode you specified for the \'mode\' parameter is unknown. Please check the documentation of this web service endpoint for more information"
                        },
                        "_201": {
                          "id": "WS-AUTH-LISTER-201",
                          "level": "Warning",
                          "name": "No Target Dataset URI",
                          "description": "No target dataset URI defined for this request. A target dataset URI is needed for the mode \'ws\' and \'dataset\'"
                        },
                        "_300": {
                          "id": "WS-AUTH-LISTER-300",
                          "level": "Fatal",
                          "name": "Can\'t get the list of datasets",
                          "description": "An error occured when we tried to get the list of datasets available to the user"
                        },
                        "_301": {
                          "id": "WS-AUTH-LISTER-301",
                          "level": "Fatal",
                          "name": "Can\'t get the list of web services",
                          "description": "An error occured when we tried to get the list of web services endpoints registered to this web service network"
                        },
                        "_302": {
                          "id": "WS-AUTH-LISTER-302",
                          "level": "Fatal",
                          "name": "Can\'t get the list of accesses for that dataset",
                          "description": "An error occured when we tried to get the list of accesses defined for this dataset"
                        },
                        "_303": {
                          "id": "WS-AUTH-LISTER-303",
                          "level": "Fatal",
                          "name": "Can\'t get the list of accesses an datasets available to that user",
                          "description": "An error occured when we tried to get the list of accesses and datasets accessible to that user"
                        },  
                        "_304": {
                          "id": "WS-AUTH-LISTER-304",
                          "level": "Fatal",
                          "name": "Can\'t get access information for this web service",
                          "description": "An error occured when we tried to get the information for the access to that web service."
                        }  
                      }';

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/iron+json", 
           "application/iron+csv", "application/*", "text/xml", "text/*", "*/*");

/** Constructor

    @param $mode One of:  (1) "dataset (default)": List all datasets URI accessible by a user, 
                          (2) "ws": List all Web services registered in a WSF
                          (3) "access_dataset": List all the registered IP addresses and their CRUD permissions for a given dataset URI
                          (4) "access_user": List all datasets URI and CRUD permissions accessible by a user 
    @param $dataset URI referring to a target dataset. Needed when param1 = "dataset" or param1 = "access_datase". Otherwise this parameter as to be ommited.
    @param $registered_ip Target IP address registered in the WSF
    @param $requester_ip IP address of the requester
    @param $target_webservice Determine on what web service URI(s) we should focus on for the listing of the access records.
                              This parameter is used to improve the performance of the web service endpoint depending on the 
                              use case. If there are numerous datasets with a numerous number of access permissions defined 
                              for each of them, properly using this parameter can have a dramatic impact on the performances. 
                              This parameter should be used if the param1 = "access_dataset" or param1 = "access_user" This 
                              parameter can have any of these values:
                             
                                + "all" (default): all the web service endpoints URIs for each access records will 
                                                   be taken into account and returned to the user (may be more time 
                                                   consuming).
                                + "none": no web service URI, for any access record, will be returned. 
    
    @return returns NULL
  
    @author Frederick Giasson, Structured Dynamics LLC.
*/
  function __construct($mode, $dataset, $registered_ip, $requester_ip, $target_webservice = "all")
  {
    parent::__construct();

    $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->requester_ip = $requester_ip;
    $this->mode = $mode;
    $this->dataset = $dataset;
    $this->targetWebservice = strtolower($target_webservice);
    
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/auth/lister/";
    $this->title = "Authentication Lister Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/auth/lister/";

    $this->dtdURL = "auth/lister/authLister.dtd";

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
    // publicly accessible users
    if($this->mode != "dataset" && $this->mode != "access_user")
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
        return;
      }
    }
  }

  /** Returns the error structure
              
      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getError() { return ($this->conneg->error); }

  /** Create a resultset in a pipelined mode based on the processed information by the Web service.
      
      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResultset() 
  { 
    return($this->injectDoctype($this->rset->getResultsetXML()));
  }

  /** Inject the DOCType in a XML document
              
      @param $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function injectDoctype($xmlDoc)
  {
    $posHeader = strpos($xmlDoc, '"?>') + 3;
    $xmlDoc = substr($xmlDoc, 0, $posHeader)
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Auth Lister DTD 0.1//EN\" \"" . $this->dtdBaseURL
        . $this->dtdURL . "\">" . substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);

    return ($xmlDoc);
  }

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, AuthLister::$supportedSerializations);

    // Check for errors
    if(strtolower($this->mode) != "ws" && strtolower($this->mode) != "dataset"
      && strtolower($this->mode) != "access_dataset" && strtolower($this->mode) != "access_user")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt("Unknown listing type");
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, odbc_errormsg(),
        $this->errorMessenger->_200->level);
      return;
    }

    // Check for errors
    if(strtolower($this->mode) != "access_dataset" && $dataset = "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, odbc_errormsg(),
        $this->errorMessenger->_201->level);
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
  public function ws_serialize()
  {
    return($this->serializations());
  }

  /** Aggregates information about the Accesses available to the requester.

      @return NULL      
      
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
        if(strtolower($this->mode) == "dataset")
        {
          $query =
            "  select distinct ?dataset 
                  from <" . $this->wsf_graph
            . "> 
                  where 
                  { 
                    { 
                      ?access <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ; 
                            <http://purl.org/ontology/wsf#datasetAccess> ?dataset . 
                    } 
                    UNION 
                    { 
                      ?access <http://purl.org/ontology/wsf#registeredIP> \"0.0.0.0\" ; 
                            <http://purl.org/ontology/wsf#create> ?create ; 
                            <http://purl.org/ontology/wsf#read> ?read ; 
                            <http://purl.org/ontology/wsf#update> ?update ; 
                            <http://purl.org/ontology/wsf#delete> ?delete ; 
                            <http://purl.org/ontology/wsf#datasetAccess> ?dataset .
                      filter( str(?create) = \"True\" or str(?read) = \"True\" or str(?update) = \"True\" or str(?delete) = \"True\").
                    }
                  }";

          $resultset =
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

          $subject = new Subject("bnode:".md5(microtime()));
          $subject->setType("rdf:Bag");
          
          while(odbc_fetch_row($resultset))
          {
            $dataset = odbc_result($resultset, 1);
            
            $subject->setObjectAttribute("rdf:li", $dataset, null, "void:Dataset");
          }
          
          $this->rset->addSubject($subject);            
        }
        elseif(strtolower($this->mode) == "ws")
        {
          $query =
            "  select distinct ?ws from <" . $this->wsf_graph
            . ">
                  where
                  {
                    ?wsf a <http://purl.org/ontology/wsf#WebServiceFramework> ;
                          <http://purl.org/ontology/wsf#hasWebService> ?ws .
                  }";

          $resultset =
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
          
          $subject = new Subject("bnode:".md5(microtime()));
          $subject->setType("rdf:Bag");          

          while(odbc_fetch_row($resultset))
          {
            $ws = odbc_result($resultset, 1);
      
            $subject->setObjectAttribute("rdf:li", $ws, null, "wsf:WebService");
          }
          
          $this->rset->addSubject($subject);    
        }
        else
        { 
          if(strtolower($this->mode) == "access_user")
          { 
            $query = "  select ?access ?datasetAccess ?create ?read ?update ?delete ?registeredIP ".($this->targetWebservice == "all" ? "?webServiceAccess" : "")."
                    from <" . $this->wsf_graph
              . ">
                    where
                    {
                      {
                        ?access a <http://purl.org/ontology/wsf#Access> ;
                              <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ;
                              <http://purl.org/ontology/wsf#create> ?create ;
                              <http://purl.org/ontology/wsf#read> ?read ;
                              <http://purl.org/ontology/wsf#update> ?update ;
                              <http://purl.org/ontology/wsf#delete> ?delete ;
                              <http://purl.org/ontology/wsf#datasetAccess> ?datasetAccess ;
                              ".($this->targetWebservice == "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> ?webServiceAccess ;" : "")."
                              ".($this->targetWebservice != "none" && $this->targetWebservice != "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> <".$this->targetWebservice."> ;" : "")."
                              <http://purl.org/ontology/wsf#registeredIP> ?registeredIP .
                      }
                      union
                      {
                        ?access a <http://purl.org/ontology/wsf#Access> ;
                              <http://purl.org/ontology/wsf#registeredIP> \"0.0.0.0\" ;
                              <http://purl.org/ontology/wsf#create> ?create ;
                              <http://purl.org/ontology/wsf#read> ?read ;
                              <http://purl.org/ontology/wsf#update> ?update ;
                              <http://purl.org/ontology/wsf#delete> ?delete ;
                              <http://purl.org/ontology/wsf#datasetAccess> ?datasetAccess ;                      
                              ".($this->targetWebservice == "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> ?webServiceAccess ;" : "")."
                              ".($this->targetWebservice != "none" && $this->targetWebservice != "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> <".$this->targetWebservice."> ;" : "")."
                              <http://purl.org/ontology/wsf#registeredIP> ?registeredIP .
                              
                        filter( str(?create) = \"True\" or str(?read) = \"True\" or str(?update) = \"True\" or str(?delete) = \"True\").
                      }
                    }";
          }
          else // access_dataset
          {
            $query = "  select ?access ?registeredIP ?create ?read ?update ?delete ".($this->targetWebservice == "all" ? "?webServiceAccess" : "")." 
                    from <" . $this->wsf_graph
              . ">
                    where
                    {
                      ?access a <http://purl.org/ontology/wsf#Access> ;
                            <http://purl.org/ontology/wsf#registeredIP> ?registeredIP ;
                            <http://purl.org/ontology/wsf#create> ?create ;
                            <http://purl.org/ontology/wsf#read> ?read ;
                            <http://purl.org/ontology/wsf#update> ?update ;
                            <http://purl.org/ontology/wsf#delete> ?delete ;
                            ".($this->targetWebservice == "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> ?webServiceAccess ;" : "")."
                            ".($this->targetWebservice != "none" && $this->targetWebservice != "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> <".$this->targetWebservice."> ;" : "")."
                            <http://purl.org/ontology/wsf#datasetAccess> <$this->dataset> .
                    }";
          }

          $resultset =
            @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
              FALSE));

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");

            if(strtolower($this->mode) == "access_user" || strtolower($this->mode) == "access_dataset")
            {
              $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
              $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
                $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
                $this->errorMessenger->_302->level);
            }
            else
            {
              $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
              $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
                $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, odbc_errormsg(),
                $this->errorMessenger->_303->level);
            }

            return;
          }
          
          $accessPreviousId = "";
          
          $subject = null;

          while(odbc_fetch_row($resultset))
          {
            $accessId = odbc_result($resultset, 1);
            
            if($accessPreviousId != $accessId)
            {
              if($subject != null)
              {
                $this->rset->addSubject($subject);
              }
              
              $subject = new Subject($accessId);
              $subject->setType("wsf:Access"); 
              
              $accessPreviousId = $accessId;
            
              $lastElement = "";

              if(strtolower($this->mode) == "access_user")
              {                
                $subject->setObjectAttribute("wsf:datasetAccess", odbc_result($resultset, 2), null, "void:Dataset");  
                $subject->setDataAttribute("wsf:create", odbc_result($resultset, 3));
                $subject->setDataAttribute("wsf:read", odbc_result($resultset, 4));
                $subject->setDataAttribute("wsf:update", odbc_result($resultset, 5));
                $subject->setDataAttribute("wsf:delete", odbc_result($resultset, 6));
                  $subject->setDataAttribute("wsf:registeredIP", odbc_result($resultset, 7));
                                                    
                if($this->targetWebservice == "all")
                {                                                    
                  $subject->setObjectAttribute("wsf:webServiceAccess", odbc_result($resultset, 8), null, "wsf:WebService");  
                }
              }
              else // access_dataset
              {
                $subject->setDataAttribute("wsf:registeredIP", odbc_result($resultset, 2));
                $subject->setDataAttribute("wsf:create", odbc_result($resultset, 3));
                $subject->setDataAttribute("wsf:read", odbc_result($resultset, 4));
                $subject->setDataAttribute("wsf:update", odbc_result($resultset, 5));
                $subject->setDataAttribute("wsf:delete", odbc_result($resultset, 6));
                                                                                                        
                                                    
                if($this->targetWebservice == "all")
                {                                                    
                  $subject->setObjectAttribute("wsf:webServiceAccess", odbc_result($resultset, 7), null, "wsf:WebService");                    
                }
              }            
            }
            else
            {
              if(strtolower($this->mode) == "access_user")
              {              
                $subject->setObjectAttribute("wsf:webServiceAccess", odbc_result($resultset, 8), null, "wsf:WebService");  
              }
              else // access_dataset
              {
                $subject->setObjectAttribute("wsf:webServiceAccess", odbc_result($resultset, 7), null, "wsf:WebService");  
              }
            }
          }
          
          // Add the last subject
          $this->rset->addSubject($subject);
        }
      }
    }
  }
}

//@}

?>