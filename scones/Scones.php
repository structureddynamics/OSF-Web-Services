<?php

/*! @ingroup WsScones */
//@{

/*! @file \ws\scones\Scones.php
   @brief Define the Scones web service
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Scones Web Service. It tags a corpus of texts with related concepts and named entities.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class Scones extends WebService
{
  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief List of ";" seperated document URL(s). This is the list of documents to process */
  private $document = "";

  /*! @brief Document content's MIME type  */
  private $docmime = "";

  /*! @brief Name of the GATE application used to perform the tagging. This name is pre-defined by the 
             administrator of the node. */
  private $application = "";

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";
  
  /*! @brief Configuration file of the Scones web service endpoint. */
  private $config_ini;
  
  /*! @brief The Scones Java session that is persistend in the servlet container. */
  private $SconesSession;
  
  /*! @brief The annotated document by Scones. */
  private $annotatedDocument = "";
  
  /*! @brief Supported MIME serializations by this web service */
  public static $supportedSerializations = array ("text/xml", "text/*", "*/xml", "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/scones/",
                        "_200": {
                          "id": "WS-SCONES-200",
                          "level": "Warning",
                          "name": "No documents URI specified for this request",
                          "description": "No documents URI specified for this request"
                        },
                        "_201": {
                          "id": "WS-SCONES-201",
                          "level": "Error",
                          "name": "Scones is not configured.",
                          "description": "Ask the system administrator to configure Scones"
                        },
                        "_202": {
                          "id": "WS-SCONES-202",
                          "level": "Error",
                          "name": "Scones is not initialized.",
                          "description": "Ask the system administrator to initialize Scones"
                        },
                        "_203": {
                          "id": "WS-SCONES-203",
                          "level": "Warning",
                          "name": "Scones is being initialized.",
                          "description": "Wait a minute and send your query again"
                        },
                        "_300": {
                          "id": "WS-SCONES-300",
                          "level": "Warning",
                          "name": "Document MIME type not supported.",
                          "description": "The MIME type of the document you feeded to Scones is not currently supported"
                        },
                        "_301": {
                          "id": "WS-SCONES-301",
                          "level": "Warning",
                          "name": "Document empty",
                          "description": "The content of the document you defined is empty"
                        }
                        
                      }';


  /*!   @brief Constructor
       @details   Initialize the SCONES Web Service
        
      @param[in] $document Document content (in non-binary form)    
      @param[in] $docmime Document content's MIME type
      @param[in] $application Name of the GATE application used to perform the tagging. This name is 
                              pre-defined by the administrator of the node.
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($document, $docmime, $application, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->document = $document;
    $this->docmime = $docmime;
    $this->application = $application;
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/scones/";
    $this->title = "Scones Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/scones/";

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
  protected function validateQuery()
  {
    if($this->docmime != "text/plain")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
      $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
        $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, "",
        $this->errorMessenger->_300->level);

      return;
    }
    if($this->document == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
      $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
        $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, "",
        $this->errorMessenger->_301->level);

      return;
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
  public function pipeline_getResultset()
  {
    // Returns the annotated GATE XML document
    return($this->annotatedDocument);
  }

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, Scones::$supportedSerializations);

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      // Validate query
      $this->validateQuery();
    }
    
    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      /*
        Get the pool of stories to process
        Can be a URL or a file reference.
      */
      $this->config_ini = parse_ini_file("config.ini", TRUE);   
            
      // Make sure the service if configured
      if($this->config_ini === FALSE)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
        $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
          $this->errorMessenger->_201->level);

        return;        
      }

      // Starts the GATE process/bridge  
      require_once($this->config_ini["gate"]["gateBridgeURI"]);
      
      // Create a Scones session where we will save the Gate objects (started & loaded Gate application).
      // Second param "false" => we re-use the pre-created session without destroying the previous one
      // third param "0" => it nevers timeout.
      $this->SconesSession = java_session($this->config_ini["gate"]["sessionName"], false, 0);   
      
      if(is_null(java_values($this->SconesSession->get("initialized")))) 
      {
        /* 
          If the "initialized" session variable is null, it means that the Scone threads
          are not initialized, and that they is no current in initialization.
        */
        
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
        $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
          $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
          $this->errorMessenger->_202->level);
      }
      
      if(java_values($this->SconesSession->get("initialized")) === FALSE) 
      {
        /* 
          If the "initialized" session variable is FALSE, it means that the Scone threads
          are being initialized.
        */
        
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
        $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
          $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
          $this->errorMessenger->_203->level);
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
  public function pipeline_serialize()
  {
    return $this->pipeline_getResultset();
  }

  /*!   @brief Non implemented method (only defined)
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize_reification() { }

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    switch($this->conneg->getMime())
    {
      case "text/xml":
        return $this->pipeline_serialize();
      break;

      default:
        return $this->pipeline_getResultset();
      break;
    }
  }

  /*!   @brief Process the document by tagging it using Scones.
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {     
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      // Check which instance is available right now
      $processed = FALSE;
      
      // Get accessible sessions (threads) from the running Scones instance
      while($processed === FALSE) // Continue until we get a free running thread
      {
        for($i = 1; $i <= $this->config_ini["gate"]["nbSessions"]; $i++)
        {
          // Make sure the issued is not currently used by another user/process
          if(java_values($this->SconesSession->get("session".$i."_used")) === FALSE)
          {
            $this->SconesSession->put("session".$i."_used", TRUE);
            
            // Process the incoming article
            $corpus = $this->SconesSession->get("session".$i."_instance")->getCorpus();
            
            // Create the content of a document
            $documentContent = new java("gate.corpora.DocumentContentImpl", $this->document);
            
            // Create the document to process
            $document = new java("gate.corpora.DocumentImpl");
            
            // Add the document content to the document
            $document->setContent($documentContent);
            
            // Create the corpus
            $corpus = new java("gate.corpora.CorpusImpl");
            
            // Add the document to the corpus
            $corpus->add($document);
            
            // Add the corpus to the corpus controler (the application)
            $this->SconesSession->get("session".$i."_instance")->setCorpus($corpus);
            
            // Execute the pipeline
            try 
            {
              $this->SconesSession->get("session".$i."_instance")->execute();        
            } 
            catch (Exception $e) 
            {
              $this->SconesSession->put("session".$i."_used", FALSE);
            }            
            
            // output the XML document
            $this->annotatedDocument =  $document->toXML();
            
            // Empty the corpus
            $corpus->clear();
            
            // Stop the thread seeking process
            $processed = TRUE;
            
            // Liberate the thread for others to use
            $this->SconesSession->put("session".$i."_used", FALSE);
            
            // Fix namespaces of the type of the tagged named entities
            $this->fixNamedEntitiesNamespaces();
            
            break;
          }
        }
        
        sleep(1);
      }      
    }
  }
  
  /*!   @brief Fix namespaces of the type of the tagged named entities      
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function fixNamedEntitiesNamespaces()
  {
    $annotatedNeXML = new SimpleXMLElement($this->annotatedDocument);

    foreach($annotatedNeXML->xpath('//AnnotationSet') as $annotationSet) 
    {
      if((string) $annotationSet['Name'] == $this->config_ini["gate"]["neAnnotationSetName"])
      {
        foreach($annotationSet->Annotation as $annotation) 
        {
          foreach($annotation->Feature as $feature)
          {
            if((string) $feature->Name == "majorType")
            {
              $feature->Value = urldecode((string) $feature->Value);
            }
          }  
        }         
      }
    }
    
    $this->annotatedDocument = $annotatedNeXML->asXML();
  }
}


//@}

?>