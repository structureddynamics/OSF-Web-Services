<?php

/*! @defgroup WsOntology Ontology Management Web Service */
//@{

/*! @file \ws\ontology\create\OntologyCreate.php
   @brief Add/Import a new ontology into the ontological structure of a structWSF network instance.

  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Add/Import a new ontology into the ontological structure of a structWSF network instance.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class OntologyCreate extends WebService
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

  /*! @brief URI where the web service can fetch the ontology document */
  private $ontologyUri = "";

  /*! @brief Requester's IP used for request validation */
  private $requester_ip = "";

  /*! @brief URI of the inference rules set to use to create the ontological structure. */
  private $rulesSetURI = "";

  /*! @brief Create permissions for the global user */
  private $globalPermissionCreate = FALSE;

  /*! @brief Read permissions for the global user */
  private $globalPermissionRead = FALSE;

  /*! @brief Update permissions for the global user */
  private $globalPermissionUpdate = FALSE;

  /*! @brief Delete permissions for the global user */
  private $globalPermissionDelete = FALSE;

  /*! @brief If this parameter is set, the Ontology Create web service endpoint will index
             the ontology in the normal structWSF data stores. That way, the ontology
             will also become queryable via the standard services such as Search and Browse.
  */
  private $advancedIndexation = FALSE;
  
  /*! @brief enable/disable the reasoner when doing advanced indexation */
  private $reasoner = TRUE;

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/ontology/create/",
                        "_200": {
                          "id": "WS-ONTOLOGY-CREATE-200",
                          "level": "Warning",
                          "name": "No Ontology URI defined for this request",
                          "description": "No Ontology URI defined for this request"
                        },
                        "_300": {
                          "id": "WS-ONTOLOGY-CREATE-300",
                          "level": "Error",
                          "name": "Can\'t load the ontology",
                          "description": "The ontology can\'t be loaded by the endpoint"
                        },
                        "_301": {
                          "id": "WS-ONTOLOGY-CREATE-301",
                          "level": "Error",
                          "name": "Can\'t tag dataset",
                          "description": "Can\'t tag the dataset as being a dataset holding an ontology description"
                        }
                        
                      }';


  /*!   @brief Constructor
       @details   Initialize the Ontology Create
          
      @param[in] $ontologyUri URI where the webservice can fetch the ontology file
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

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);
    
    $this->registered_ip = $registered_ip;
    $this->requester_ip = $requester_ip;
    $this->ontologyUri = $ontologyUri;

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

    $this->uri = $this->wsf_base_url . "/wsf/ws/ontology/create/";
    $this->title = "Ontology Create Web Service";
    $this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/ontology/create/";

    $this->dtdURL = "auth/OntologyCreate.dtd";

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
      $this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
      $this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
      $this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
      $this->conneg->setError($ws_av->pipeline_getError()->id, $ws_av->pipeline_getError()->webservice,
        $ws_av->pipeline_getError()->name, $ws_av->pipeline_getError()->description,
        $ws_av->pipeline_getError()->debugInfo, $ws_av->pipeline_getError()->level);

      return;
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Ontology Create DTD 0.1//EN\" \""
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
      OntologyCreate::$supportedSerializations);

    // Check for errors

    if($this->ontologyUri == "")
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

  private function returnError($statusCode, $statusMsg, $wsErrorCode, $debugInfo = "")
  {
    $this->conneg->setStatus($statusCode);
    $this->conneg->setStatusMsg($statusMsg);
    $this->conneg->setStatusMsgExt($this->errorMessenger->{$wsErrorCode}->name);
    $this->conneg->setError($this->errorMessenger->{$wsErrorCode}->id, $this->errorMessenger->ws,
      $this->errorMessenger->{$wsErrorCode}->name, $this->errorMessenger->{$wsErrorCode}->description, $debugInfo,
      $this->errorMessenger->{$wsErrorCode}->level);
  }


  /*! @brief Update all ontological structures used by the WSF
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function createOntology()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $this->validateQuery();

      // If the query is still valid
      if($this->conneg->getStatus() == 200)
      {
        // Starts the OWLAPI process/bridge
        require_once($this->owlapiBridgeURI);

        // Create the OWLAPI session object that could have been persisted on the OWLAPI instance.
        // Second param "false" => we re-use the pre-created session without destroying the previous one
        // third param "0" => it nevers timeout.
        $OwlApiSession = java_session("OWLAPI", false, 0);

        try
        {
          $ontology = new OWLOntology($this->ontologyUri, $OwlApiSession, FALSE);
        }
        catch(Exception $e)
        {
          $this->returnError(400, "Bad Request", "_300", (string)java_values($e));

          return;
        }

        // Get the description of the ontology
        $ontologyDescription = $ontology->getOntologyDescription();

        $ontologyName = $this->getLabel($this->ontologyUri, $ontologyDescription);
        $ontologyDescription = $this->getDescription($ontologyDescription);

        // Get the list of webservices that will be accessible for this ontology dataset.
        include_once($this->wsf_base_path."auth/lister/AuthLister.php");

        $authLister = new AuthLister("ws", $this->ontologyUri, $this->requester_ip, $this->wsf_local_ip);

        $authLister->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
          $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

        $authLister->process();

        if($authLister->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->conneg->setStatus($authLister->pipeline_getResponseHeaderStatus());
          $this->conneg->setStatusMsg($authLister->pipeline_getResponseHeaderStatusMsg());
          $this->conneg->setStatusMsgExt($authLister->pipeline_getResponseHeaderStatusMsgExt());
          $this->conneg->setError($authLister->pipeline_getError()->id, $authLister->pipeline_getError()->webservice,
            $authLister->pipeline_getError()->name, $authLister->pipeline_getError()->description,
            $authLister->pipeline_getError()->debugInfo, $authLister->pipeline_getError()->level);

          return;
        }

        /* Get all web services */
        $webservices = "";

        $xml = new ProcessorXML();
        $xml->loadXML($authLister->pipeline_getResultset());

        $webServiceElements = $xml->getXPath('//predicate/object[attribute::type="wsf:WebService"]');

        foreach($webServiceElements as $element)
        {
          if(stristr($xml->getURI($element), "/wsf/ws/search/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/browse/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/sparql/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/crud/create/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/crud/update/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/crud/delete/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/crud/read/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/ontology/create/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/ontology/read/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/ontology/update/") !== FALSE
            || stristr($xml->getURI($element), "/wsf/ws/ontology/delete/") !== FALSE)
          {
            $webservices .= $xml->getURI($element) . ";";
          }
        }

        $webservices = rtrim($webservices, ";");

        unset($xml);
        unset($authLister);

        // Create a new dataset for this ontology
        include_once($this->wsf_base_path."dataset/create/DatasetCreate.php");
        include_once($this->wsf_base_path."auth/registrar/access/AuthRegistrarAccess.php");

        $globalPermissions = "";
        
        if($this->globalPermissionCreate === FALSE)
        {
          $globalPermissions .= "False;";
        }
        else
        {
          $globalPermissions .= "True;";
        }
        
        if($this->globalPermissionRead === FALSE)
        {
          $globalPermissions .= "False;";
        }
        else
        {
          $globalPermissions .= "True;";
        }
        
        if($this->globalPermissionUpdate === FALSE)
        {
          $globalPermissions .= "False;";
        }
        else
        {
          $globalPermissions .= "True;";
        }
        
        if($this->globalPermissionDelete === FALSE)
        {
          $globalPermissions .= "False";
        }
        else
        {
          $globalPermissions .= "True";
        }
        
        $datasetCreate =
          new DatasetCreate($this->ontologyUri, $ontologyName, $ontologyDescription, "", $this->registered_ip,
            $this->requester_ip, $webservices, $globalPermissions);

        $datasetCreate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
          $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $datasetCreate->process();

        if($datasetCreate->pipeline_getResponseHeaderStatus() != 200)
        {
          if($datasetCreate->pipeline_getError()->id != "WS-DATASET-CREATE-202")
          {
            $this->conneg->setStatus($datasetCreate->pipeline_getResponseHeaderStatus());
            $this->conneg->setStatusMsg($datasetCreate->pipeline_getResponseHeaderStatusMsg());
            $this->conneg->setStatusMsgExt($datasetCreate->pipeline_getResponseHeaderStatusMsgExt());
            $this->conneg->setError($datasetCreate->pipeline_getError()->id,
              $datasetCreate->pipeline_getError()->webservice, $datasetCreate->pipeline_getError()->name,
              $datasetCreate->pipeline_getError()->description, $datasetCreate->pipeline_getError()->debugInfo,
              $datasetCreate->pipeline_getError()->level);
          }

          // If the dataset already exists, then we simply stop the processing of the advancedIndexation
          // mode. This means that the tomcat instance has been rebooted, and that the datasets
          // have been leaved there, and that a procedure, normally using the advancedIndexation mode
          // is currently being re-processed.

          return;
        }

        unset($datasetCreate);
        
        // Tag the new dataset as being a dataset that host an ontology description
        $query = "insert into <" . $this->wsf_graph . "datasets/>
                {
                  <" . $this->ontologyUri . "> <http://purl.org/ontology/wsf#holdOntology> \"true\" .
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

        // Check if we want to enable the advanced indexation: so, if we want to import all the ontologies 
        // description into the other structWSF data stores to enable search and filtering using the other
        // endpoints such as search, sparql, read, etc.
        if($this->advancedIndexation)
        {          
          // Once we start the ontology creation process, we have to make sure that even if the server
          // loose the connection with the user the process will still finish.
          ignore_user_abort(true);

          // However, maybe there is an issue with the server handling that file tht lead to some kind of infinite 
          // or near infinite loop; so we have to limit the execution time of this procedure to 45 mins.
          set_time_limit(86400);    

          // Get the description of the classes, properties and named individuals of this ontology.

          include_once($this->wsf_base_path."ontology/read/OntologyRead.php");
          include_once($this->wsf_base_path."crud/create/CrudCreate.php");
          include_once($this->wsf_base_path."framework/arc2/ARC2.php");
          include_once($this->wsf_base_path."framework/Namespaces.php");
          include_once($this->wsf_base_path."framework/Solr.php");
          include_once($this->wsf_base_path."framework/ClassHierarchy.php");
                    
          // Check the size of the Ontology file to import. If the size is bigger than 6MB, then we will
          // use another method that incurs some Virtuoso indexing. If it is the case, you have to make sure
          // that Virtuoso is properly configured so that it can access (DirsAllowed Virtuoso config option)
          // the folder where the ontology file has been saved.

          if(filesize($this->ontologyUri) > 6000000)
          {
            $sliceSize = 100;          

            // Import the big file into Virtuoso  
            $sqlQuery = "DB.DBA.RDF_LOAD_RDFXML_MT(file_to_string_output('".str_replace("file://localhost", "", $this->ontologyUri)."'),'".$this->ontologyUri."/import','".$this->ontologyUri."/import')";
            
            $resultset = $this->db->query($sqlQuery);
            
            if(odbc_error())
            {
              // If there is an error, try to load it using the Turtle parser
              $sqlQuery = "DB.DBA.TTLP_MT(file_to_string_output('".str_replace("file://localhost", "", $this->ontologyUri)."'),'".$this->ontologyUri."/import','".$this->ontologyUri."/import')";
              
              $resultset = $this->db->query($sqlQuery);
              
              if(odbc_error())
              {
  //            echo "Error: can't import the file: $file, into the triple store.\n";
  //            return;
              }            
            }    
            
            unset($resultset);     

            // count the number of records
            $sparqlQuery = "
            
              select count(distinct ?s) as ?nb from <".$this->ontologyUri."/import>
              where
              {
                ?s a ?o .
              }
            
            ";

            $resultset = $this->db->query($this->db->build_sparql_query($sparqlQuery, array ('nb'), FALSE));
            
            $nb = odbc_result($resultset, 1);

            unset($resultset);
            
            $nbRecordsDone = 0;
            
            while($nbRecordsDone < $nb && $nb > 0)
            {
              // Create slices of 100 records.
              $sparqlQuery = "
                
                select ?s ?p ?o (DATATYPE(?o)) as ?otype (LANG(?o)) as ?olang
                where 
                {
                  {
                    select distinct ?s from <".$this->ontologyUri."/import> 
                    where 
                    {
                      ?s a ?type.
                    } 
                    limit ".$sliceSize." 
                    offset ".$nbRecordsDone."
                  } 
                  
                  ?s ?p ?o
                }
              
              ";

              $resultset = $this->db->query($this->db->build_sparql_query($sparqlQuery, array ('s', 'p', 'o', 'otype', 'olang'), FALSE));
              
              if(odbc_error())
              {
  //              echo "Error: can't get records slices.\n";
  //              return;
              }          
              
              $crudCreates = "";
              $crudUpdates = "";
              $crudDeletes = array();
              
              $rdfDocumentN3 = "";
              
              $currentSubject = "";
              $subjectDescription = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";

              while(odbc_fetch_row($resultset))
              {
                $s = odbc_result($resultset, 1);
                $p = odbc_result($resultset, 2);
                $o = odbc_result($resultset, 3);
                $otype = odbc_result($resultset, 4);
                $olang = odbc_result($resultset, 5);
                
                if($otype != "" || $olang != "")
                {
                  $subjectDescription .= "<$s> <$p> \"\"\"".$this->n3Encode($o)."\"\"\" .\n";
                }
                else
                {
                  $subjectDescription .= "<$s> <$p> <$o> .\n";
                }
              }  
              
              unset($resultset);  

             
              include_once($this->wsf_base_path."framework/WebServiceQuerier.php");           
              
              $wsq = new WebServiceQuerier(rtrim($this->wsf_base_url, "/") . "/ws/crud/create/", "post",
                "application/rdf+xml", 
                "document=" . urlencode($subjectDescription) .
                "&dataset=" . urlencode($this->ontologyUri) .
                "&mime=" . urlencode("application/rdf+n3") .
                "&mode=full" .
                "&registered_ip=" . urlencode($this->registered_ip));

              if($wsq->getStatus() != 200)
              {
                $this->conneg->setStatus($wsq->getStatus());
                $this->conneg->setStatusMsg($wsq->getStatusMessage());
                $this->conneg->setStatusMsgExt($wsq->getStatusMessageDescription());
                /*
                $this->conneg->setError($wsq->pipeline_getError()->id,
                  $crudCreate->pipeline_getError()->webservice, $crudCreate->pipeline_getError()->name,
                  $crudCreate->pipeline_getError()->description, $crudCreate->pipeline_getError()->debugInfo,
                  $crudCreate->pipeline_getError()->level);               
                */
                
                // In case of error, we delete the dataset we previously created.
                include_once($this->wsf_base_path."ontology/delete/OntologyDelete.php");

                $ontologyDelete = new OntologyDelete($this->ontologyUri, $this->registered_ip, $this->requester_ip);

                $ontologyDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
                  $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                $ontologyDelete->deleteOntology();

                if($ontologyDelete->pipeline_getResponseHeaderStatus() != 200)
                {
                  $this->conneg->setStatus($ontologyDelete->pipeline_getResponseHeaderStatus());
                  $this->conneg->setStatusMsg($ontologyDelete->pipeline_getResponseHeaderStatusMsg());
                  $this->conneg->setStatusMsgExt($ontologyDelete->pipeline_getResponseHeaderStatusMsgExt());
                  $this->conneg->setError($ontologyDelete->pipeline_getError()->id,
                    $ontologyDelete->pipeline_getError()->webservice, $ontologyDelete->pipeline_getError()->name,
                    $ontologyDelete->pipeline_getError()->description, $ontologyDelete->pipeline_getError()->debugInfo,
                    $ontologyDelete->pipeline_getError()->level);

                  //return;
                }

                //return;              
              }              
              
              $nbRecordsDone += $sliceSize;
            }
          
            // Now delete the graph we used to import the file

            $sqlQuery = "sparql clear graph <".$this->ontologyUri."/import>";
            
            $resultset = $this->db->query($sqlQuery);

            if(odbc_error())
            {
  //            echo "Error: can't delete the graph sued for importing the file\n";
  //            return;
            }    
            
            unset($resultset);    
          }
          else
          {
            $nbClasses = $ontology->getNbClasses();
            $sliceSize = 200;
            
            // Note: in OntologyCreate, we have to merge all the classes, properties and named individuals
            //       together. This is needed to properly handle possible punning used in imported ontologies.
            //       If we don't do this, and that a resource is both a class and an individual, then only
            //       the individual will be in the Solr index because it would overwrite the Class 
            //       record document with the same URI.
            
            $rdfxmlParser = ARC2::getRDFParser();
            $rdfxmlSerializer = ARC2::getRDFXMLSerializer();
            
            $resourcesIndex = $rdfxmlParser->getSimpleIndex(0);
            
            for($i = 0; $i < $nbClasses; $i += $sliceSize)
            {
              $ontologyRead =
                new OntologyRead($this->ontologyUri, "getClasses", "mode=descriptions;limit=$sliceSize;offset=$i",
                  $this->registered_ip, $this->requester_ip);

              // Since we are in pipeline mode, we have to set the owlapisession using the current one.
              // otherwise the java bridge will return an error
              $ontologyRead->setOwlApiSession($OwlApiSession);

              $ontologyRead->ws_conneg("application/rdf+xml", $_SERVER['HTTP_ACCEPT_CHARSET'],
                $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

              if($this->reasoner)
              {
                $ontologyRead->useReasoner(); 
              }  
              else
              {
                $ontologyRead->stopUsingReasoner();
              }
                
              $ontologyRead->process();

              $classesRDF = $ontologyRead->ws_serialize();

              $rdfxmlParser->parse($this->ontologyUri, $classesRDF);
              $resourceIndex = $rdfxmlParser->getSimpleIndex(0);
              $resourcesIndex = ARC2::getMergedIndex($resourcesIndex, $resourceIndex);
              
              unset($ontologyRead);
            }

            $nbProperties = 0;
            $nbProperties += $ontology->getNbObjectProperties();
            $nbProperties += $ontology->getNbDataProperties();
            $nbProperties += $ontology->getNbAnnotationProperties();
            $sliceSize = 200;

            for($i = 0; $i < $nbProperties; $i += $sliceSize)
            {
              $ontologyRead = new OntologyRead($this->ontologyUri, "getProperties",
                "mode=descriptions;limit=$sliceSize;offset=$i;type=all", $this->registered_ip, $this->requester_ip);

              // Since we are in pipeline mode, we have to set the owlapisession using the current one.
              // otherwise the java bridge will return an error
              $ontologyRead->setOwlApiSession($OwlApiSession);

              $ontologyRead->ws_conneg("application/rdf+xml", $_SERVER['HTTP_ACCEPT_CHARSET'],
                $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

              if($this->reasoner)
              {
                $ontologyRead->useReasoner(); 
              }  
              else
              {
                $ontologyRead->stopUsingReasoner();
              }                
                
              $ontologyRead->process();

              $propertiesRDF = $ontologyRead->ws_serialize();

              $rdfxmlParser->parse($this->ontologyUri, $propertiesRDF);
              $resourceIndex = $rdfxmlParser->getSimpleIndex(0);
              $resourcesIndex = ARC2::getMergedIndex($resourcesIndex, $resourceIndex);            
              
              unset($ontologyRead);
            }

            $nbNamedIndividuals = $ontology->getNbNamedIndividuals();
            $sliceSize = 200;

            for($i = 0; $i < $nbNamedIndividuals; $i += $sliceSize)
            {
              $ontologyRead = new OntologyRead($this->ontologyUri, "getNamedIndividuals",
                "classuri=all;mode=descriptions;limit=$sliceSize;offset=$i", $this->registered_ip, $this->requester_ip);

              // Since we are in pipeline mode, we have to set the owlapisession using the current one.
              // otherwise the java bridge will return an error
              $ontologyRead->setOwlApiSession($OwlApiSession);

              $ontologyRead->ws_conneg("application/rdf+xml", $_SERVER['HTTP_ACCEPT_CHARSET'],
                $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

              if($this->reasoner)
              {
                $ontologyRead->useReasoner(); 
              }  
              else
              {
                $ontologyRead->stopUsingReasoner();
              }                
                
              $ontologyRead->process();

              $namedIndividualsRDF = $ontologyRead->ws_serialize();
              
              $rdfxmlParser->parse($this->ontologyUri, $namedIndividualsRDF);
              $resourceIndex = $rdfxmlParser->getSimpleIndex(0);
              $resourcesIndex = ARC2::getMergedIndex($resourcesIndex, $resourceIndex);              

              unset($ontologyRead);
            }
            
            // Now, let's index the resources of this ontology within structWSF (for the usage of browse, search 
            // and sparql)
            
            // Split the aggregated resources in multiple slices
            $nbResources = count($resourcesIndex);
            $sliceSize = 200;
                                           
            for($i = 0; $i < $nbResources; $i += $sliceSize)
            {
              $slicedResourcesIndex = array_slice($resourcesIndex, $i, $sliceSize);
              
              $resourcesRDF = $rdfxmlSerializer->getSerializedIndex($slicedResourcesIndex);
              
              $crudCreate =
                new CrudCreate($resourcesRDF, "application/rdf+xml", "full", $this->ontologyUri, $this->registered_ip,
                  $this->requester_ip);

              $crudCreate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
                $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

              $crudCreate->process();

              if($crudCreate->pipeline_getResponseHeaderStatus() != 200)
              {
                $this->conneg->setStatus($crudCreate->pipeline_getResponseHeaderStatus());
                $this->conneg->setStatusMsg($crudCreate->pipeline_getResponseHeaderStatusMsg());
                $this->conneg->setStatusMsgExt($crudCreate->pipeline_getResponseHeaderStatusMsgExt());
                $this->conneg->setError($crudCreate->pipeline_getError()->id,
                  $crudCreate->pipeline_getError()->webservice, $crudCreate->pipeline_getError()->name,
                  $crudCreate->pipeline_getError()->description, $crudCreate->pipeline_getError()->debugInfo,
                  $crudCreate->pipeline_getError()->level);

                // In case of error, we delete the dataset we previously created.
                include_once($this->wsf_base_path."ontology/delete/OntologyDelete.php");

                $ontologyDelete = new OntologyDelete($this->ontologyUri, $this->registered_ip, $this->requester_ip);

                $ontologyDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
                  $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                $ontologyDelete->deleteOntology();

                if($ontologyDelete->pipeline_getResponseHeaderStatus() != 200)
                {
                  $this->conneg->setStatus($ontologyDelete->pipeline_getResponseHeaderStatus());
                  $this->conneg->setStatusMsg($ontologyDelete->pipeline_getResponseHeaderStatusMsg());
                  $this->conneg->setStatusMsgExt($ontologyDelete->pipeline_getResponseHeaderStatusMsgExt());
                  $this->conneg->setError($ontologyDelete->pipeline_getError()->id,
                    $ontologyDelete->pipeline_getError()->webservice, $ontologyDelete->pipeline_getError()->name,
                    $ontologyDelete->pipeline_getError()->description, $ontologyDelete->pipeline_getError()->debugInfo,
                    $ontologyDelete->pipeline_getError()->level);

                  return;
                }

                return;
              }

              unset($crudCreate);             
            }
          }
        }
      }
    }
  } 
  
  private function n3Encode($string)
  {
    return(trim(str_replace(array( "\\" ), "\\\\", $string), '"'));
  }  
  
  private function indexRdfData($rdfContent)
  {
    $crudCreate =
      new CrudCreate($rdfContent, "application/rdf+xml", "full", $this->ontologyUri, $this->registered_ip,
        $this->requester_ip);

    $crudCreate->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
      $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

    $crudCreate->process();

    if($crudCreate->pipeline_getResponseHeaderStatus() != 200)
    {
      $this->conneg->setStatus($crudCreate->pipeline_getResponseHeaderStatus());
      $this->conneg->setStatusMsg($crudCreate->pipeline_getResponseHeaderStatusMsg());
      $this->conneg->setStatusMsgExt($crudCreate->pipeline_getResponseHeaderStatusMsgExt());
      $this->conneg->setError($crudCreate->pipeline_getError()->id,
        $crudCreate->pipeline_getError()->webservice, $crudCreate->pipeline_getError()->name,
        $crudCreate->pipeline_getError()->description, $crudCreate->pipeline_getError()->debugInfo,
        $crudCreate->pipeline_getError()->level);

      // In case of error, we delete the dataset we previously created.
      include_once($this->wsf_base_path."ontology/delete/OntologyDelete.php");

      $ontologyDelete = new OntologyDelete($this->ontologyUri, $this->registered_ip, $this->requester_ip);

      $ontologyDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
        $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

      $ontologyDelete->deleteOntology();

      if($ontologyDelete->pipeline_getResponseHeaderStatus() != 200)
      {
        $this->conneg->setStatus($ontologyDelete->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($ontologyDelete->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($ontologyDelete->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($ontologyDelete->pipeline_getError()->id,
          $ontologyDelete->pipeline_getError()->webservice, $ontologyDelete->pipeline_getError()->name,
          $ontologyDelete->pipeline_getError()->description, $ontologyDelete->pipeline_getError()->debugInfo,
          $ontologyDelete->pipeline_getError()->level);

        return(FALSE);
      }

      return(FALSE);
    }

    unset($crudCreate);              
  }  

  /*! @brief Get the preferred label for a resource (class, proeperty, instance).
  
      @param[in] $uri the URI of the resource for which we are looking for a preferred label. This URI is
                      used to try to create a label if nothing can be used in its own description (this is the fallback)
      @param[in] $description the internal representation of the resource. The structure of this array is:
      
      $classDescription = array(
                                 "predicate-uri" => array(
                                                          array(
                                                                  "value" => "the value of the predicate",
                                                                  "datatype" => "the type of the value",
                                                                  "lang" => "language reference of the value (if literal)"
                                                               ),
                                                          array(...)
                                                        ),
                                 "..." => array(...)
                               )      
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */  
  public function getLabel($uri, $description)
  {
    if(isset($description[Namespaces::$iron . "prefLabel"]))
    {
      return $description[Namespaces::$iron . "prefLabel"][0]["value"];
    }

    if(isset($description[Namespaces::$skos_2008 . "prefLabel"]))
    {
      return $description[Namespaces::$skos_2008 . "prefLabel"][0]["value"];
    }

    if(isset($description[Namespaces::$skos_2004 . "prefLabel"]))
    {
      return $description[Namespaces::$skos_2004 . "prefLabel"][0]["value"];
    }

    if(isset($description[Namespaces::$rdfs . "label"]))
    {
      return $description[Namespaces::$rdfs . "label"][0]["value"];
    }

    if(isset($description[Namespaces::$dcterms . "title"]))
    {
      return $description[Namespaces::$dcterms . "title"][0]["value"];
    }

    if(isset($description[Namespaces::$dc . "title"]))
    {
      return $description[Namespaces::$dc . "title"][0]["value"];
    }

    // Find the base URI of the ontology
    $pos = strripos($uri, "#");

    if($pos === FALSE)
    {
      $pos = strripos($uri, "/");
    }

    if($pos !== FALSE)
    {
      $pos++;
    }

    $resource = substr($uri, $pos, strlen($uri) - $pos);

    // Remove non alpha-num and replace them by spaces
    $resource = preg_replace("/[^A-Za-z0-9]/", " ", $resource);

    // Split upper-case words into seperate words
    $resourceArr = preg_split('/(?=[A-Z])/', $resource);
    $resource = implode(" ", $resourceArr);

    return $resource;
  }

  /*! @brief Get the description for a resource (class, property, instance).
  
      @param[in] $description the internal representation of the resource. The structure of this array is:
      
      $classDescription = array(
                                 "predicate-uri" => array(
                                                          array(
                                                                  "value" => "the value of the predicate",
                                                                  "datatype" => "the type of the value",
                                                                  "lang" => "language reference of the value (if literal)"
                                                               ),
                                                          array(...)
                                                        ),
                                 "..." => array(...)
                               )      
                               
      @return returns a description for that resource. "No description available" if none are described in the 
              resource's description                               
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */    
  public function getDescription($description)
  {
    if(isset($description[Namespaces::$iron . "description"]))
    {
      return $description[Namespaces::$iron . "description"][0]["value"];
    }

    if(isset($description[Namespaces::$skos_2008 . "definition"]))
    {
      return $description[Namespaces::$skos_2008 . "definition"][0]["value"];
    }

    if(isset($description[Namespaces::$skos_2004 . "definition"]))
    {
      return $description[Namespaces::$skos_2004 . "definition"][0]["value"];
    }

    if(isset($description[Namespaces::$rdfs . "comment"]))
    {
      return $description[Namespaces::$rdfs . "comment"][0]["value"];
    }

    if(isset($description[Namespaces::$dcterms . "description"]))
    {
      return $description[Namespaces::$dcterms . "description"][0]["value"];
    }

    if(isset($description[Namespaces::$dc . "description"]))
    {
      return $description[Namespaces::$dc . "description"][0]["value"];
    }

    return "No description available";
  }
  
  /*!
  * Set the advanced indexation mode of the ontology create class. This should be set before running process().
  * 
  * @param mixed $advancedIndexation Set to TRUE to enable the advanced indexation.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setAdvancedIndexation($advancedIndexation)
  {
    $this->advancedIndexation = $advancedIndexation;
  }
    
  /*!
  * Enable the reasoner for advanced indexation 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function useReasonerForAdvancedIndexation()
  {
    $this->reasoner = TRUE;
  }
  
  /*!
  * Disable the reasoner for advanced indexation 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function stopUsingReasonerForAdvancedIndexation()
  {
    $this->reasoner = FALSE;
  }
  
  /*!
  * @brief Set the global Create permission to the ontology being created. The global permission is what is
  *        defined for *all* users. This should be set before running process().
  * 
  * @param mixed $create Create permission: TRUE or FALSE.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setGlobalPermissionCreate($create)
  {
    $this->globalPermissionCreate = $create;
  }
  
  /*!
  * @brief Set the global Read permission to the ontology being created. The global permission is what is
  *        defined for *all* users. This should be set before running process().
  * 
  * @param mixed $read Create permission: TRUE or FALSE.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setGlobalPermissionRead($read)
  {
    $this->globalPermissionRead = $read;
  }
  
  /*!
  * @brief Set the global Update permission to the ontology being created. The global permission is what is
  *        defined for *all* users. This should be set before running process().
  * 
  * @param mixed $update Create permission: TRUE or FALSE.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setGlobalPermissionUpdate($update)
  {
    $this->globalPermissionUpdate = $update;
  }
  
  /*!
  * @brief Set the global Delete permission to the ontology being created. The global permission is what is
  *        defined for *all* users. This should be set before running process().
  * 
  * @param mixed $delete Create permission: TRUE or FALSE.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setGlobalPermissionDelete($delete)
  {
    $this->globalPermissionDelete = $delete;
  }
}

//@}

?>