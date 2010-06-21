<?php

/*! @defgroup WsCrud Crud Web Service */
//@{

/*! @file \ws\crud\read\CrudRead.php
   @brief Define the Crud Read web service
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief CRUD Read web service. It reads instance records description within dataset indexes on different systems (Virtuoso, Solr, etc).
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class CrudRead extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Include the reference of the resources that links to this resource */
  private $include_linksback = "";

  /*! @brief Include potential reification statements */
  private $include_reification = "";

  /*! @brief URI of the resource to get its description */
  private $resourceUri = "";

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";

  /*! @brief URI of the target dataset. */
  private $dataset = "";

  /*! @brief Description of one or multiple datasets */
  private $datasetsDescription = array();

/*! @brief The global datasetis the set of all datasets on an instance. TRUE == we query the global dataset, FALSE we don't. */
  private $globalDataset = FALSE;

  /*! @brief Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf");

  /*! @brief Array of triples where the current resource(s) is a subject. */
  public $subjectTriples = array();

  /*! @brief Array of triples where the current resource(s) is an object. */
  public $objectTriples = array();

  /*! @brief Array of triples that reify triples of a resource description. */
  public $reificationTriples = array();

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/bib+json", "application/iron+json", "application/json", "application/rdf+xml",
      "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/crud/read/",
                        "_200": {
                          "id": "WS-CRUD-READ-200",
                          "level": "Warning",
                          "name": "No URI specified for any resource",
                          "description": "No record URI defined for this query"
                        },
                        "_201": {
                          "id": "WS-CRUD-READ-201",
                          "level": "Warning",
                          "name": "Missing Dataset URIs",
                          "description": "Not all dataset URIs have been defined for each requested record URI. Remember that each URI of the list of URIs have to have a matching dataset URI in the datasets list."
                        },
                        "_202": {
                          "id": "WS-CRUD-READ-202",
                          "level": "Warning",
                          "name": "Record URI(s) not existing or not accessible",
                          "description": "The requested record URI(s) are not existing in this structWSF instance, or are not accessible to the requester. This error is only sent when no data URI are defined."
                        },
                        "_300": {
                          "id": "WS-CRUD-READ-300",
                          "level": "Warning",
                          "name": "This resource is not existing",
                          "description": "The target resource to be read is not existing in the system"
                        },
                        "_301": {
                          "id": "WS-CRUD-READ-301",
                          "level": "Warning",
                          "name": "You can\'t read more than 64 resources at once",
                          "description": "You are limited to read maximum 64 resources for each query to the CrudRead web service endpoint"
                        },
                        "_302": {
                          "id": "WS-CRUD-READ-302",
                          "level": "Fatal",
                          "name": "Can\'t get the description of the resource(s)",
                          "description": "An error occured when we tried to get the description of the resource(s)"
                        },  
                        "_303": {
                          "id": "WS-CRUD-READ-303",
                          "level": "Fatal",
                          "name": "Can\'t get the links-to the resource(s)",
                          "description": "An error occured when we tried to get the links-to the resource(s)"
                        },  
                        "_304": {
                          "id": "WS-CRUD-READ-304",
                          "level": "Fatal",
                          "name": "Can\'t get the reification statements for that resource(s)",
                          "description": "An error occured when we tried to get the reification statements of the resource(s)"
                        }  

                      }';


  /*!   @brief Constructor
       @details   Initialize the Auth Web Service
              
      @param[in] $uri URI of the instance record
      @param[in] $dataset URI of the dataset where the instance record is indexed
      @param[in] $include_linksback One of (1) True ? Means that the reference to the other instance records referring 
                             to the target instance record will be added in the resultset (2) False (default) ? No 
                             links-back will be added 

      @param[in] $include_reification Include possible reification statements for a record
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($uri, $dataset, $include_linksback, $include_reification, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->dataset = $dataset;

    // If no dataset URI is defined for this query, we simply query all datasets accessible by the requester.
    if($this->dataset == "")
    {
      $this->globalDataset = TRUE;
    }

    $this->resourceUri = $uri;

    $this->include_linksback = $include_linksback;
    $this->include_reification = $include_reification;
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/crud/read/";
    $this->title = "Crud Read Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/crud/read/";

    $this->dtdURL = "crud/crudRead.dtd";

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
    /*
      Check if dataset(s) URI(s) have been defined for this request. If not, then we query the
      AuthLister web service endpoint to get the list of datasets accessible by this user to see
      if the URI he wants to read is defined in one of these accessible dataset. 
     */
    if($this->globalDataset === TRUE)
    {
      include_once("../../auth/lister/AuthLister.php");

      $ws_al = new AuthLister("access_user", "", $this->registered_ip, $this->wsf_local_ip);

      $ws_al->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_al->process();

      $xml = new ProcessorXML();
      $xml->loadXML($ws_al->pipeline_getResultset());

      $accesses = $xml->getSubjectsByType("wsf:Access");

      $accessibleDatasets = array();

      foreach($accesses as $access)
      {
        $predicates = $xml->getPredicatesByType($access, "wsf:datasetAccess");
        $objects = $xml->getObjects($predicates->item(0));
        $datasetUri = $xml->getURI($objects->item(0));

        $predicates = $xml->getPredicatesByType($access, "wsf:read");
        $objects = $xml->getObjects($predicates->item(0));
        $read = $xml->getContent($objects->item(0));

        if(strtolower($read) == "true")
        {
          $this->dataset .= "$datasetUri;";
          array_push($accessibleDatasets, $datasetUri);
        }
      }

      if(count($accessibleDatasets) <= 0)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
        $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
          $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
          $this->errorMessenger->_202->level);

        return;
      }

      unset($ws_al);

      $this->dataset = rtrim($this->dataset, ";");
    }
    else
    {
      $datasets = explode(";", $this->dataset);

      $datasets = array_unique($datasets);

      // Validate for each requested records of each dataset
      foreach($datasets as $dataset)
      {
        // Validation of the "requester_ip" to make sure the system that is sending the query as the rights.
        $ws_av = new AuthValidator($this->requester_ip, $dataset, $this->uri);

        $ws_av->pipeline_conneg("text/xml", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
          $this->conneg->getAcceptLanguage());

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

        // Validation of the "registered_ip" to make sure the user of this system has the rights
        $ws_av = new AuthValidator($this->registered_ip, $dataset, $this->uri);

        $ws_av->pipeline_conneg("text/xml", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(),
          $this->conneg->getAcceptLanguage());

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
    $xml = new ProcessorXML();

    // Creation of the RESULTSET
    $resultset = $xml->createResultset();

    // Creation of the prefixes elements.
    $void = $xml->createPrefix("owl", "http://www.w3.org/2002/07/owl#");
    $resultset->appendChild($void);
    $rdf = $xml->createPrefix("rdf", "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
    $resultset->appendChild($rdf);
    $dcterms = $xml->createPrefix("rdfs", "http://www.w3.org/2000/01/rdf-schema#");
    $resultset->appendChild($dcterms);
    $dcterms = $xml->createPrefix("wsf", "http://purl.org/ontology/wsf#");
    $resultset->appendChild($dcterms);

    $subject;

    foreach($this->subjectTriples as $u => $sts)
    {
      if(isset($sts["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"]))
      {
        foreach($sts["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"] as $key => $type)
        {
          if($key > 0)
          {
            $pred = $xml->createPredicate("http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
            $object = $xml->createObject("", $type[0]);
            $pred->appendChild($object);
            $subject->appendChild($pred);
          }
          else
          {
            $subject = $xml->createSubject($type[0], $u);
          }
        }
      }
      else
      {
        $subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $u);
      }

      foreach($sts as $property => $values)
      {
        if($property != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
        {
          foreach($values as $value)
          {
            if($value[1] != NULL)
            {
              /*
                @TODO The internal XML structure of structWSF should be enhanced with datatypes such as xsd:double, int, 
                      literal, etc.
              */
              $pred = $xml->createPredicate($property);
              $object = $xml->createObjectContent($this->xmlEncode($value[0]));
              $pred->appendChild($object);

              if(isset($this->reificationTriples[$u][$property][$value[0]]))
              {
                foreach($this->reificationTriples[$u][$property][$value[0]] as $rStatement => $rValue)
                {
                  foreach($rValue as $rv)
                  {
                    $reify = $xml->createReificationStatement($rStatement, $rv);
                    $object->appendChild($reify);
                  }
                }
              }

              $subject->appendChild($pred);
            }
            else
            {
              $pred = $xml->createPredicate($property);
              $object = $xml->createObject("", $value[0]);
              $pred->appendChild($object);

              if(isset($this->reificationTriples[$u][$property][$value[0]]))
              {
                foreach($this->reificationTriples[$u][$property][$value[0]] as $rStatement => $rValue)
                {
                  foreach($rValue as $rv)
                  {
                    $reify = $xml->createReificationStatement($rStatement, $rv);
                    $object->appendChild($reify);
                  }
                }
              }

              $subject->appendChild($pred);
            }
          }
        }
      }

      $resultset->appendChild($subject);

      // Now let add object references
      if(count($this->objectTriples[$u]) > 0)
      {
        foreach($this->objectTriples[$u] as $property => $propertyValue)
        {
          foreach($propertyValue as $resource)
          {
            $subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $resource);

            $pred = $xml->createPredicate($property);
            $object = $xml->createObject("", $u);
            $pred->appendChild($object);
            $subject->appendChild($pred);

            $resultset->appendChild($subject);
          }
        }
      }
    }

    return ($this->injectDoctype($xml->saveXML($resultset)));
  }

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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Crud Read DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudRead::$supportedSerializations);

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      // Check for errors
      if($this->resourceUri == "")
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

    // Check if we have the same number of URIs than Dataset URIs (only if at least one dataset URI is defined).
    if($this->globalDataset === FALSE)
    {
      $uris = explode(";", $this->resourceUri);
      $datasets = explode(";", $this->dataset);

      if(count($uris) != count($datasets))
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
    $rdf_part = "";

    switch($this->conneg->getMime())
    {
      case "application/bib+json":
      case "application/iron+json":
        include_once("../../converter/irjson/ConverterIrJSON.php");
        include_once("../../converter/irjson/Dataset.php");
        include_once("../../converter/irjson/InstanceRecord.php");
        include_once("../../converter/irjson/LinkageSchema.php");
        include_once("../../converter/irjson/StructureSchema.php");
        include_once("../../converter/irjson/irJSONParser.php");

        // Include more information about the dataset (at least the ID)
        $documentToConvert = $this->pipeline_getResultset();

        $datasets = explode(";", $this->dataset);

        $datasets = array_unique($datasets);

        // Note: this is temporary. A more consistent dataset-URI/resource-URIs has to be implemented in conStruct
        ///////////////
        $d = $datasets[0];

        $d = str_replace("/wsf/datasets/", "/conStruct/datasets/", $d) . "resource/";
        ///////////////


        /*
          @TODO In the future, we will have to include the dataset's meta-data information in the data to convert.
                This meta-data include: its name, description, creator, maintainer, owner, schema and linkage.
                
                This will be done by querying the DatasetRead web service endpoint for the "$d" dataset
        */

        $ws_irv =
          new ConverterIrJSON($documentToConvert, "text/xml", "true", $this->registered_ip, $this->requester_ip);

        $ws_irv->pipeline_conneg("application/iron+json", $this->conneg->getAcceptCharset(),
          $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

        $ws_irv->process();

        if($ws_irv->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->conneg->setStatus($ws_irv->pipeline_getResponseHeaderStatus());
          $this->conneg->setStatusMsg($ws_irv->pipeline_getResponseHeaderStatusMsg());
          $this->conneg->setStatusMsgExt($ws_irv->pipeline_getResponseHeaderStatusMsgExt());
          $this->conneg->setError($ws_irv->pipeline_getError()->id, $ws_irv->pipeline_getError()->webservice,
            $ws_irv->pipeline_getError()->name, $ws_irv->pipeline_getError()->description,
            $ws_irv->pipeline_getError()->debugInfo, $ws_irv->pipeline_getError()->level);
          return;
        }

        return ($ws_irv->pipeline_serialize());

      break;

      case "application/json":
        $json_part = "";
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        $nsId = 0;

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          $ns = $this->getNamespace($subjectType);

          if(!isset($this->namespaces[$ns[0]]))
          {
            $this->namespaces[$ns[0]] = "ns" . $nsId;
            $nsId++;
          }

          $json_part .= "      { \n";
          $json_part .= "        \"uri\": \"" . parent::jsonEncode($subjectURI) . "\", \n";
          $json_part .= "        \"type\": \"" . parent::jsonEncode($this->namespaces[$ns[0]] . ":" . $ns[1])
            . "\", \n";

          $predicates = $xml->getPredicates($subject);

          $nbPredicates = 0;

          foreach($predicates as $predicate)
          {
            $objects = $xml->getObjects($predicate);

            foreach($objects as $object)
            {
              $nbPredicates++;

              if($nbPredicates == 1)
              {
                $json_part .= "        \"predicate\": [ \n";
              }

              $objectType = $xml->getType($object);
              $predicateType = $xml->getType($predicate);

              if($objectType == "rdfs:Literal")
              {
                $objectValue = $xml->getContent($object);

                $ns = $this->getNamespace($predicateType);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $json_part .= "          { \n";
                $json_part .= "            \"" . parent::jsonEncode($this->namespaces[$ns[0]] . ":" . $ns[1]) . "\": \""
                  . parent::jsonEncode($objectValue) . "\" \n";
                $json_part .= "          },\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);

                $ns = $this->getNamespace($predicateType);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $json_part .= "          { \n";
                $json_part .= "            \"" . parent::jsonEncode($this->namespaces[$ns[0]] . ":" . $ns[1])
                  . "\": { \n";
                $json_part .= "                \"uri\": \"" . parent::jsonEncode($objectURI) . "\",\n";

                // Check if there is a reification statement for this object.
                $reifies = $xml->getReificationStatements($object, "wsf:objectLabel");

                $nbReification = 0;

                foreach($reifies as $reify)
                {
                  $nbReification++;

                  if($nbReification > 0)
                  {
                    $json_part .= "               \"reify\": [\n";
                  }

                  $json_part .= "                 { \n";
                  $json_part .= "                     \"type\": \"wsf:objectLabel\", \n";
                  $json_part .= "                     \"value\": \"" . parent::jsonEncode($xml->getValue($reify))
                    . "\" \n";
                  $json_part .= "                 },\n";
                }

                if($nbReification > 0)
                {
                  $json_part = substr($json_part, 0, strlen($json_part) - 2) . "\n";

                  $json_part .= "               ]\n";
                }
                else
                {
                  $json_part = substr($json_part, 0, strlen($json_part) - 2) . "\n";
                }

                $json_part .= "                } \n";
                $json_part .= "          },\n";
              }
            }
          }

          if(strlen($json_part) > 0)
          {
            $json_part = substr($json_part, 0, strlen($json_part) - 2) . "\n";
          }

          if($nbPredicates > 0)
          {
            $json_part .= "        ]\n";
          }

          $json_part .= "      },\n";
        }

        if(strlen($json_part) > 0)
        {
          $json_part = substr($json_part, 0, strlen($json_part) - 2) . "\n";
        }

        $json_header .= "  \"prefixes\": [ \n";
        $json_header .= "    {\n";
        $json_header .= "      \"rdf\": \"http://www.w3.org/1999/02/22-rdf-syntax-ns#\",\n";
        $json_header .= "      \"wsf\": \"http://purl.org/ontology/wsf#\",\n";

        foreach($this->namespaces as $ns => $prefix)
        {
          $json_header .= "      \"$prefix\": \"$ns\",\n";
        }

        if(strlen($json_header) > 0)
        {
          $json_header = substr($json_header, 0, strlen($json_header) - 2) . "\n";
        }

        $json_header .= "    } \n";
        $json_header .= "  ],\n";
        $json_header .= "  \"resultset\": {\n";
        $json_header .= "    \"subject\": [\n";
        $json_header .= $json_part;
        $json_header .= "    ]\n";
        $json_header .= "  }\n";

        return ($json_header);
/*
        $json_part = "";
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());
        
        $subjects = $xml->getSubjects();
      
        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);
        
          $json_part .= "      { \n";
          $json_part .= "        \"uri\": \"".parent::jsonEncode($subjectURI)."\", \n";
          $json_part .= "        \"type\": \"".parent::jsonEncode($subjectType)."\", \n";

          $predicates = $xml->getPredicates($subject);
          
          $nbPredicates = 0;
          
          foreach($predicates as $predicate)
          {
            $objects = $xml->getObjects($predicate);
            
            foreach($objects as $object)
            {
              $nbPredicates++;
              
              if($nbPredicates == 1)
              {
                $json_part .= "        \"predicates\": [ \n";
              }
              
              $objectType = $xml->getType($object);            
              $predicateType = $xml->getType($predicate);
              
              if($objectType == "rdfs:Literal")
              {
                $objectValue = $xml->getContent($object);
                            
                $json_part .= "          { \n";
                $json_part .= "            \"".parent::jsonEncode($predicateType)."\": \"".parent::jsonEncode($objectValue)."\" \n";
                $json_part .= "          },\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);            
                $rdf_part .= "          <$predicateType> <$objectURI> ;\n";
                
                $json_part .= "          { \n";
                $json_part .= "            \"".parent::jsonEncode($predicateType)."\": { \n";
                $json_part .= "                \"uri\": \"".parent::jsonEncode($objectURI)."\", \n";
                
                                // Check if there is a reification statement for this object.
                                  $reifies = $xml->getReificationStatements($object);
                                
                                  $nbReification = 0;
                                
                                  foreach($reifies as $reify)
                                  {
                                    $nbReification++;
                                    
                                    if($nbReification > 0)
                                    {
                                      $json_part .= "               \"reifies\": [\n";
                                    }
                                    
                                    $json_part .= "                 { \n";
                                    $json_part .= "                     \"type\": \"wsf:objectLabel\", \n";
                                    $json_part .= "                     \"value\": \"".parent::jsonEncode($xml->getValue($reify))."\" \n";
                                    $json_part .= "                 },\n";
                                  }
                                  
                                  if($nbReification > 0)
                                  {
                                    $json_part = substr($json_part, 0, strlen($json_part) - 2)."\n";
                                    
                                    $json_part .= "               ]\n";
                                  }
                                  else
                                  {
                                    $json_part = substr($json_part, 0, strlen($json_part) - 2)."\n";
                                  }                
                                                  
                                  
                                  $json_part .= "                } \n";
                                  $json_part .= "          },\n";
                                }
                              }
                            }
                            
                            if(strlen($json_part) > 0)
                            {
                              $json_part = substr($json_part, 0, strlen($json_part) - 2)."\n";
                            }            
                            
                            if($nbPredicates > 0)
                            {
                              $json_part .= "        ]\n";
                            }
                            
                            $json_part .= "      },\n";          
                          }
                          
                          if(strlen($json_part) > 0)
                          {
                            $json_part = substr($json_part, 0, strlen($json_part) - 2)."\n";
                          }
                          
                  
                          return($json_part);    
                          */
      break;

      case "application/rdf+n3":

        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject, FALSE);

          $rdf_part .= "\n    <$subjectURI> a <$subjectType> ;\n";

          $predicates = $xml->getPredicates($subject);

          foreach($predicates as $predicate)
          {
            $objects = $xml->getObjects($predicate);

            foreach($objects as $object)
            {
              $objectType = $xml->getType($object);
              $predicateType = $xml->getType($predicate, FALSE);

              if($objectType == "rdfs:Literal")
              {
                $objectValue = $xml->getContent($object);
                $rdf_part .= "        <$predicateType> \"\"\"" . str_replace(array( "\\" ), "\\\\", $objectValue)
                  . "\"\"\" ;\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);
                $rdf_part .= "        <$predicateType> <$objectURI> ;\n";
              }
            }
          }

          if(strlen($rdf_part) > 0)
          {
            $rdf_part = substr($rdf_part, 0, strlen($rdf_part) - 2) . ". \n";
          }
        }

        return ($rdf_part);
      break;

      case "application/rdf+xml":
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        $nsId = 0;

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          $ns1 = $this->getNamespace($subjectType);

          if(!isset($this->namespaces[$ns1[0]]))
          {
            $this->namespaces[$ns1[0]] = "ns" . $nsId;
            $nsId++;
          }

          $rdf_part .= "\n    <" . $this->namespaces[$ns1[0]] . ":" . $ns1[1] . " rdf:about=\"$subjectURI\">\n";

          $predicates = $xml->getPredicates($subject);

          foreach($predicates as $predicate)
          {
            $objects = $xml->getObjects($predicate);

            foreach($objects as $object)
            {
              $objectType = $xml->getType($object);
              $predicateType = $xml->getType($predicate);

              if($objectType == "rdfs:Literal")
              {
                $objectValue = $xml->getContent($object);

                $ns = $this->getNamespace($predicateType);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $rdf_part .= "        <" . $this->namespaces[$ns[0]] . ":" . $ns[1] . ">"
                  . $this->xmlEncode($objectValue) . "</" . $this->namespaces[$ns[0]] . ":" . $ns[1] . ">\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);

                $ns = $this->getNamespace($predicateType);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $rdf_part .= "        <" . $this->namespaces[$ns[0]] . ":" . $ns[1]
                  . " rdf:resource=\"$objectURI\" />\n";
              }
            }
          }

          $rdf_part .= "    </" . $this->namespaces[$ns1[0]] . ":" . $ns1[1] . ">\n";
        }

        $rdf_header = "<rdf:RDF ";

        foreach($this->namespaces as $ns => $prefix)
        {
          $rdf_header .= " xmlns:$prefix=\"$ns\"";
        }

        $rdf_header .= ">\n\n";

        $rdf_part = $rdf_header . $rdf_part;

        return ($rdf_part);
      break;
    }
  }

  /*!   @brief Get the namespace of a URI
              
      @param[in] $uri Uri of the resource from which we want the namespace
              
      \n
      
      @return returns the extracted namespace      
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function getNamespace($uri)
  {
    $pos = strrpos($uri, "#");

    if($pos !== FALSE)
    {
      return array (substr($uri, 0, $pos) . "#", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
    }
    else
    {
      $pos = strrpos($uri, "/");

      if($pos !== FALSE)
      {
        return array (substr($uri, 0, $pos) . "/", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
      }
      else
      {
        $pos = strpos($uri, ":");

        if($pos !== FALSE)
        {
          $nsUri = explode(":", $uri, 2);

          foreach($this->namespaces as $uri2 => $prefix2)
          {
            $uri2 = urldecode($uri2);

            if($prefix2 == $nsUri[0])
            {
              return (array ($uri2, $nsUri[1]));
            }
          }

          return explode(":", $uri, 2);
        }
      }
    }

    return (FALSE);
  }

  /*!   @brief Non implemented method (only defined)
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize_reification()
  {
    $rdf_reification = "";

    switch($this->conneg->getMime())
    {
      case "application/rdf+n3":
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        $bnodeCounter = 0;

        foreach($subjects as $subject)
        {
          $predicates = $xml->getPredicates($subject);

          foreach($predicates as $predicate)
          {
            $predicateType = $xml->getType($predicate, FALSE);

            $objects = $xml->getObjects($predicate);

            foreach($objects as $object)
            {
              $reifies = $xml->getReificationStatements($object);

              foreach($reifies as $reify)
              {
                $reifyPredicate = $xml->getType($reify, FALSE);

                $ns = $this->getNamespace($reifyPredicate);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $rdf_reification .= "_:" . md5($xml->getURI($subject) . $predicateType . $xml->getURI($object))
                  . " a rdf:Statement ;\n";
                $bnodeCounter++;
                $rdf_reification .= "    rdf:subject <" . $xml->getURI($subject) . "> ;\n";
                $rdf_reification .= "    rdf:predicate <" . $predicateType . "> ;\n";
                $rdf_reification .= "    rdf:object <" . $xml->getURI($object) . "> ;\n";
                $rdf_reification .= "    " . $this->namespaces[$ns[0]] . ":" . $ns[1] . " \"" . $xml->getValue($reify)
                  . "\" .\n\n";
                $bnodeCounter++;
              }
            }
          }
        }

        return ($rdf_reification);

      break;

      case "application/rdf+xml":
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        foreach($subjects as $subject)
        {
          $predicates = $xml->getPredicates($subject);

          foreach($predicates as $predicate)
          {
            $predicateType = $xml->getType($predicate, FALSE);

            $objects = $xml->getObjects($predicate);

            foreach($objects as $object)
            {
              $reifies = $xml->getReificationStatements($object);

              foreach($reifies as $reify)
              {
                $reifyPredicate = $xml->getType($reify, FALSE);

                $ns = $this->getNamespace($reifyPredicate);

                if(!isset($this->namespaces[$ns[0]]))
                {
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $rdf_reification .= "<rdf:Statement rdf:about=\""
                  . md5($xml->getURI($subject) . $predicateType . $xml->getURI($object)) . "\">\n";
                $rdf_reification .= "    <rdf:subject rdf:resource=\"" . $xml->getURI($subject) . "\" />\n";
                $rdf_reification .= "    <rdf:predicate rdf:resource=\"" . $predicateType . "\" />\n";
                $rdf_reification .= "    <rdf:object rdf:resource=\"" . $xml->getURI($object) . "\" />\n";
                $rdf_reification .= "    <" . $this->namespaces[$ns[0]] . ":" . $ns[1] . ">"
                  . $this->xmlEncode($xml->getValue($reify)) . "</$reifyPredicate>\n";
                $rdf_reification .= "</rdf:Statement>  \n\n";
              }
            }
          }
        }

        return ($rdf_reification);

      break;
    }
  }

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
      case "application/rdf+n3":
        $rdf_document = "";
        $rdf_document .= "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";

        $rdf_document .= $this->pipeline_serialize();

        $rdf_document .= $this->pipeline_serialize_reification();

        return $rdf_document;
      break;

      case "application/rdf+xml":
        $rdf_document = "";
        $rdf_document .= "<?xml version=\"1.0\"?>\n";

        $rdf_document .= $this->pipeline_serialize();

        $rdf_document .= $this->pipeline_serialize_reification();

        $rdf_document .= "</rdf:RDF>";

        return $rdf_document;
      break;

      case "text/xml":
        return $this->pipeline_getResultset();
      break;

      case "application/json":
        /*
                $json_document = "";
                $json_document .= "{\n";
                $json_document .= "  \"resultset\": {\n";
                $json_document .= "    \"subject\": [\n";
                $json_document .= $this->pipeline_serialize();
                $json_document .= "    ]\n";
                $json_document .= "  }\n";
                $json_document .= "}";
                
                return($json_document);
        */
        $json_document = "";
        $json_document .= "{\n";
        $json_document .= $this->pipeline_serialize();
        $json_document .= "}";

        return ($json_document);
      break;

      case "application/bib+json":
      case "application/iron+json":
        return ($this->pipeline_serialize());
      break;
    }
  }

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


  /*!   @brief Get the description of an instance resource from the triple store
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $uris = explode(";", $this->resourceUri);
      $datasets = explode(";", $this->dataset);

      if(count($uris) > 64)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
        $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
          $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, "",
          $this->errorMessenger->_301->level);

        return;
      }

      foreach($uris as $key => $u)
      {

        // Decode potentially encoded ";" character.
        $u = str_ireplace("%3B", ";", $u);
        $d = str_ireplace("%3B", ";", $datasets[$key]);

        $query = "";

        if($this->globalDataset === FALSE)
        {
          $d = str_ireplace("%3B", ";", $datasets[$key]);

          // Archiving suject triples
          $query = $this->db->build_sparql_query("select ?p ?o (DATATYPE(?o)) as ?otype (LANG(?o)) as ?olang "
            . "from <" . $d . "> where {<" . $u
            . "> ?p ?o.}", array ('p', 'o', 'otype', 'olang'), FALSE);
        }
        else
        {
          $d = "";

          foreach($datasets as $dataset)
          {
            if($dataset != "")
            {
              $d .= " from named <$dataset> ";
            }
          }

          // Archiving suject triples
          $query = $this->db->build_sparql_query("select ?p ?o (DATATYPE(?o)) as ?otype (LANG(?o)) as ?olang "
            . " $d where {graph ?g{<" . $u
            . "> ?p ?o.}}", array ('p', 'o', 'otype', 'olang'), FALSE);
        }

        $resultset = $this->db->query($query);

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
          $p = odbc_result($resultset, 1);
          $o = odbc_result($resultset, 2);

          $otype = odbc_result($resultset, 3);
          $olang = odbc_result($resultset, 4);

          if(!isset($this->subjectTriples[$u][$p]))
          {
            $this->subjectTriples[$u][$p] = array();
          }

          if($olang && $olang != "")
          {
            /* If a language is defined for an object, we force its type to be xsd:string */
            array_push($this->subjectTriples[$u][$p], array ($o, "http://www.w3.org/2001/XMLSchema#string"));  
          }
          else
          {
            array_push($this->subjectTriples[$u][$p], array ($o, $otype));  
          }
        }

        if(count($this->subjectTriples) <= 0)
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
          $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
            $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, "",
            $this->errorMessenger->_300->level);

          return;
        }

        // Archiving object triples
        if(strtolower($this->include_linksback) == "true")
        {
          $query = "";

          if($this->globalDataset === FALSE)
          {
            $query = $this->db->build_sparql_query("select ?s ?p from <" . $d . "> where {?s ?p <" . $u . ">.}",
              array ('s', 'p'), FALSE);
          }
          else
          {
            $d = "";

            foreach($datasets as $dataset)
            {
              if($dataset != "")
              {
                $d .= " from named <$dataset> ";
              }
            }

            $query =
              $this->db->build_sparql_query("select ?s ?p $d where {graph ?g{?s ?p <" . $u . ">.}}", array ('s', 'p'),
                FALSE);
          }

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_303 > name);
            $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
              $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, odbc_errormsg(),
              $this->errorMessenger->_303->level);
          }

          while(odbc_fetch_row($resultset))
          {
            $s = odbc_result($resultset, 1);
            $p = odbc_result($resultset, 2);

            if(!isset($this->objectTriples[$u][$p]))
            {
              $this->objectTriples[$u][$p] = array();
            }

            array_push($this->objectTriples[$u][$p], $s);
          }

          unset($resultset);
        }

        // Get reification triples
        if(strtolower($this->include_reification) == "true")
        {

          $query = "";

          if($this->globalDataset === FALSE)
          {
            $query = "  select ?rei_p ?rei_o ?p ?o from <" . $d . "reification/> 
                    where 
                    {
                      ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <"
              . $u
              . ">.
                      ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> ?rei_p.
                      ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> ?rei_o.
                      ?statement ?p ?o.
                    }";
          }
          else
          {
            $d = "";

            foreach($datasets as $dataset)
            {
              if($dataset != "")
              {
                $d .= " from named <" . $dataset . "reification/> ";
              }
            }

            $query = "  select ?rei_p ?rei_o ?p ?o $d 
                    where 
                    {
                      graph ?g
                      {
                        ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <"
              . $u
                . ">.
                        ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> ?rei_p.
                        ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> ?rei_o.
                        ?statement ?p ?o.
                      }
                    }";
          }

          $query = $this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
            array ('rei_p', 'rei_o', 'p', 'o'), FALSE);

          $resultset = $this->db->query($query);

          if(odbc_error())
          {
            $this->conneg->setStatus(500);
            $this->conneg->setStatusMsg("Internal Error");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
            $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
              $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, odbc_errormsg(),
              $this->errorMessenger->_304->level);
          }

          while(odbc_fetch_row($resultset))
          {
            $rei_p = odbc_result($resultset, 1);
            $rei_o = odbc_result($resultset, 2);
            $p = odbc_result($resultset, 3);
            $o = odbc_result($resultset, 4);

            if($p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"
              && $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"
              && $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#object"
              && $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
            {
              if(!isset($this->reificationTriples[$u][$rei_p][$rei_o][$p]))
              {
                $this->reificationTriples[$u][$rei_p][$rei_o][$p] = array();
              }

              array_push($this->reificationTriples[$u][$rei_p][$rei_o][$p], $o);
            }
          }

          unset($resultset);
        }
      }
    }
  }
}


//@}

?>