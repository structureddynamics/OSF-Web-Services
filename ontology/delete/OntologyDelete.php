<?php

/*! @defgroup WsOntology Ontology Management Web Service */
//@{

/*! @file \ws\ontology\delete\OntologyCreate.php
   @brief Delete entire ontologies from the system, or parts of it (resources of type class or property)

  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Ontology Delete Web Service. Delete entire ontologies from the system, or parts of it 
            (resources of type class or property)
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class OntologyDelete extends WebService
{
  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /*! @brief URI of the ontology to query */
  private $ontologyUri = "";

  /*! @brief Ontology object. */
  private $ontology;

  /*! @brief IP being registered */
  private $registered_ip = "";

  /*! @brief Requester's IP used for request validation */
  private $requester_ip = "";

  /*! @brief URI of the inference rules set to use to delete the ontological structure. */
  private $rulesSetURI = "";

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/ontology/delete/",
                        "_200": {
                          "id": "WS-ONTOLOGY-DELETE-200",
                          "level": "Warning",
                          "name": "Unknown function call",
                          "description": "The function call being requested is unknown or unsupported by this Ontology Delete web service endpoint"
                        },                        
                        "_201": {
                          "id": "WS-ONTOLOGY-DELETE-201",
                          "level": "Warning",
                          "name": "No Ontology URI defined for this request",
                          "description": "No Ontology URI defined for this request"
                        },
                        "_300": {
                          "id": "WS-ONTOLOGY-DELETE-300",
                          "level": "Error",
                          "name": "Can\'t load the ontology",
                          "description": "The ontology can\'t be loaded by the endpoint"
                        }
                      }';


  /*!   @brief Constructor
       @details   Initialize the Ontology Delete
          
      @param[in] $ontology URI of the ontology where to delete something
      @param[in] $function The function to use for this web service call. Refers you to the documentation to ge the 
                           list of functions and their usage.
      @param[in] $parameters List of parameters for the target function. The parameters are split by a ";" character.
                             The parameter and its value are defined as "param-1=value-1". This tuple has to be
                             encoded. So, the parameters should be constructed that way in the URL:
                             &parameters=urlencode("param-1=value-1");urlencode("param-2=value-2")...
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($ontologyUri, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->ontologyUri = $ontologyUri;
      
    $this->registered_ip = $registered_ip;
    $this->requester_ip = $requester_ip;

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

    $this->uri = $this->wsf_base_url . "/wsf/ws/ontology/delete/";
    $this->title = "Ontology Delete Web Service";
    $this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/ontology/delete/";

    $this->dtdURL = "auth/OntologyDelete.dtd";

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
      // If he doesn't, then check if he has access to the dataset itself
      $ws_av2 = new AuthValidator($this->requester_ip, $this->ontologyUri, $this->uri);

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
      // Validation of the "registered_ip" to make sure the user of this system has the rights
      $ws_av = new AuthValidator($this->registered_ip, $this->wsf_graph . "ontologies/", $this->uri);

      $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_av->process();

      if($ws_av->pipeline_getResponseHeaderStatus() != 200)
      {
        // If he doesn't, then check if he has access to the dataset itself
        $ws_av2 = new AuthValidator($this->registered_ip, $this->ontologyUri, $this->uri);

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


  /*!  @brief Delete a resultset in a pipelined mode based on the processed information by the Web service.
              
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Ontology Delete DTD 0.1//EN\" \""
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
      OntologyDelete::$supportedSerializations);

    // Check for errors

    if($this->ontologyUri == "")
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

  /**
  * 
  * 
  * @param mixed $statusCode
  * @param mixed $statusMsg
  * @param mixed $wsErrorCode
  * @param mixed $debugInfo
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function returnError($statusCode, $statusMsg, $wsErrorCode, $debugInfo = "")
  {
    $this->conneg->setStatus($statusCode);
    $this->conneg->setStatusMsg($statusMsg);
    $this->conneg->setStatusMsgExt($this->errorMessenger->{$wsErrorCode}->name);
    $this->conneg->setError($this->errorMessenger->{$wsErrorCode}->id, $this->errorMessenger->ws,
      $this->errorMessenger->{$wsErrorCode}->name, $this->errorMessenger->{$wsErrorCode}->description, $debugInfo,
      $this->errorMessenger->{$wsErrorCode}->level);
  }

  /**
  * 
  *  
  * @param mixed $uri
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function deleteProperty($uri)
  {
    $this->initiateOwlBridgeSession();

    $this->getOntologyReference();
        
    if($this->isValid())
    {
      if($uri == "")
      {
        $this->returnError(400, "Bad Request", "_201");
        return;
      }

      // Delete the OWLAPI class entity
      $this->ontology->removeProperty($uri);

      // Check to delete potential datasets that have been created within structWSF
      include_once("../../crud/delete/CrudDelete.php");
      include_once("../../dataset/read/DatasetRead.php");
      include_once("../../framework/Solr.php");

      $crudDelete =
        new CrudDelete($uri, $this->ontologyUri, $this->registered_ip, $this->requester_ip);

      $crudDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
        $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

      $crudDelete->process();

      if($crudDelete->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($crudDelete->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($crudDelete->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($crudDelete->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($crudDelete->pipeline_getError()->id,
          $crudDelete->pipeline_getError()->webservice, $crudDelete->pipeline_getError()->name,
          $crudDelete->pipeline_getError()->description, $crudDelete->pipeline_getError()->debugInfo,
          $crudDelete->pipeline_getError()->level);

        return;
      }

      // Update the name of the file of the ontology to mark it as "changed"
      $this->ontology->addOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");    
    }
  }

  /**
  * 
  *   
  * @param mixed $uri
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function deleteClass($uri)
  {
    $this->initiateOwlBridgeSession();

    $this->getOntologyReference();
        
    if($this->isValid())
    {
      if($uri == "")
      {
        $this->returnError(400, "Bad Request", "_201");
        return;
      }

      // Delete the OWLAPI class entity
      $this->ontology->removeClass($uri);

      // Check to delete potential datasets that have been created within structWSF
      include_once("../../crud/delete/CrudDelete.php");
      include_once("../../dataset/read/DatasetRead.php");
      include_once("../../framework/Solr.php");

      $crudDelete = new CrudDelete($uri, $this->ontologyUri, $this->registered_ip, $this->requester_ip);

      $crudDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
        $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

      $crudDelete->process();

      if($crudDelete->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($crudDelete->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($crudDelete->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($crudDelete->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($crudDelete->pipeline_getError()->id,
          $crudDelete->pipeline_getError()->webservice, $crudDelete->pipeline_getError()->name,
          $crudDelete->pipeline_getError()->description, $crudDelete->pipeline_getError()->debugInfo,
          $crudDelete->pipeline_getError()->level);

        return;
      }

      // Update the name of the file of the ontology to mark it as "changed"
      $this->ontology->addOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");    
    }
  }
  
  /**
  * 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function deleteOntology()
  {
    $this->initiateOwlBridgeSession();

    $this->getOntologyReference();
        
    if($this->isValid())
    {
      // Delete the OWLAPI instance
      $this->ontology->delete();

      // Check to delete potential datasets that have been created within structWSF
      include_once("../../dataset/delete/DatasetDelete.php");
      include_once("../../auth/registrar/access/AuthRegistrarAccess.php");
      include_once("../../framework/Solr.php");

      $datasetDelete = new DatasetDelete($this->ontologyUri, $this->registered_ip, $this->requester_ip);

      $datasetDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
        $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

      $datasetDelete->process();

      if($datasetDelete->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($datasetDelete->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($datasetDelete->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($datasetDelete->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($datasetDelete->pipeline_getError()->id,
          $datasetDelete->pipeline_getError()->webservice, $datasetDelete->pipeline_getError()->name,
          $datasetDelete->pipeline_getError()->description, $datasetDelete->pipeline_getError()->debugInfo,
          $datasetDelete->pipeline_getError()->level);

        return;
      }

      if(isset($this->parameters["deleteFile"]) && (boolean)$this->parameters["deleteFile"] === TRUE)
      {
        unlink(str_replace("file://localhost", "", $this->ontologyUri));
      }    
      
      unset($datasetDelete);
    }
  }
  
  /**
  * 
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function initiateOwlBridgeSession()
  {
    // Starts the OWLAPI process/bridge
    require_once($this->owlapiBridgeURI);

    // Create the OWLAPI session object that could have been persisted on the OWLAPI instance.
    // Second param "false" => we re-use the pre-created session without destroying the previous one
    // third param "0" => it nevers timeout.
    if($this->OwlApiSession == null)
    {
      $this->OwlApiSession = java_session("OWLAPI", false, 0);
    }    
  }
  
  /**
  * 
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function getOntologyReference()
  {
    try
    {
      $this->ontology = new OWLOntology($this->ontologyUri, $this->OwlApiSession, TRUE);
    }
    catch(Exception $e)
    {
      $this->returnError(400, "Bad Request", "_300");
    }    
  }
  
  /**
  * 
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function isValid()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $this->validateQuery();

      // If the query is still valid
      if($this->conneg->getStatus() == 200)
      {
        return(TRUE);
      }
    }
    
    return(FALSE);    
  }
}

//@}

?>