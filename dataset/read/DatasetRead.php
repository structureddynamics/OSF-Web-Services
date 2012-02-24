<?php

/*! @defgroup WsDataset Dataset Management Web Service  */
//@{

/*! @file \ws\dataset\read\DatasetRead.php
   @brief Read a graph for this dataset & indexation of its description
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Dataset Read Web Service. It reads description of datasets of a structWSF instance
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class DatasetRead extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";

  /*! @brief URI of the target dataset(s). "all" means all datasets visible to thatuser. */
  private $datasetUri = "";

  /*! @brief Add meta information to the resultset */
  private $addMeta = "false";

  /*! @brief Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr", "http://rdfs.org/ns/void#" => "void",
      "http://rdfs.org/sioc/ns#" => "sioc", "http://purl.org/dc/terms/" => "dcterms", 
      );


  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", 
           "application/iron+json", "application/iron+csv", "application/*", 
           "text/xml", "text/*", "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/dataset/read/",
                        "_200": {
                          "id": "WS-DATASET-READ-200",
                          "level": "Warning",
                          "name": "No unique identifier specified for this dataset",
                          "description": "No URI defined for this new dataset"
                        },
                        "_201": {
                          "id": "WS-DATASET-READ-201",
                          "level": "Warning",
                          "name": "Invalid dataset URI",
                          "description": "The URI of the dataset is not valid."
                        },                          
                        "_300": {
                          "id": "WS-DATASET-READ-300",
                          "level": "Fatal",
                          "name": "Can\'t get the description of any dataset",
                          "description": "An error occured when we tried to get information about all datasets"
                        },
                        "_301": {
                          "id": "WS-DATASET-READ-301",
                          "level": "Fatal",
                          "name": "Can\'t get the description of the target dataset",
                          "description": "An error occured when we tried to get information about the target dataset"
                        },
                        "_302": {
                          "id": "WS-DATASET-READ-302",
                          "level": "Fatal",
                          "name": "Can\'t get meta-information about the dataset(s)",
                          "description": "An error occured when we tried to get meta-information about the dataset(s)"
                        },
                        "_303": {
                          "id": "WS-DATASET-READ-303",
                          "level": "Fatal",
                          "name": "Can\'t get information about the contributors",
                          "description": "An error occured when we tried to get information about the contributors of this dataset"
                        },
                        "_304": {
                          "id": "WS-DATASET-READ-304",
                          "level": "Warning",
                          "name": "This dataset doesn\'t exist in this WSF",
                          "description": "The target dataset doesn\'t exist in this web service framework"
                        },
                        "_305": {
                          "id": "WS-DATASET-READ-305",
                          "level": "Fatal",
                          "name": "Can\'t get meta-information about the dataset(s)",
                          "description": "An error occured when we tried to get meta-information about the dataset(s)"
                        }    
                      }';


  /*!   @brief Constructor
       @details   Initialize the Auth Web Service
              
      @param[in] $uri URI of the dataset to read (get its description)
      @param[in] $meta Add meta information with the resultset
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($uri, $meta, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->datasetUri = $uri;
    $this->requester_ip = $requester_ip;
    $this->addMeta = strtolower($meta);

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

    $this->uri = $this->wsf_base_url . "/wsf/ws/dataset/read/";
    $this->title = "Dataset Read Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/dataset/read/";

    $this->dtdURL = "dataset/read/datasetRead.dtd";

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

  /*! @brief Validate a query to this web service
      
      @details If a user wants to read information about a dataset on a given structWSF web service endpoint,
      he has to have access to the "http://.../wsf/datasets/" graph with Read privileges, or to have
      Read privileges on the dataset URI itself. If the users doesn't have these permissions, 
      then he won't be able to read the description of the dataset on that instance.
      
      By default, the administrators, and the creator of the dataset, have such an access on a structWSF instance. 
      However a system administrator can choose to make the "http://.../wsf/datasets/" world readable,
      which would mean that anybody could read information about the datasets on the instance.
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery()
  {
    // Check if the requester has access to the main "http://.../wsf/datasets/" graph.
    $ws_av = new AuthValidator($this->requester_ip, $this->wsf_graph . "datasets/", $this->uri);

    $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
      $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

    $ws_av->process();

    if($ws_av->pipeline_getResponseHeaderStatus() != 200)
    {
      if($this->datasetUri != "all")
      {      
        // If he doesn't, then check if he has access to the dataset itself
        $ws_av2 = new AuthValidator($this->requester_ip, $this->datasetUri, $this->uri);

        $ws_av2->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
          $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

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
      else
      {
        $this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
        $this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
        $this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
        $this->conneg->setError($ws_av->pipeline_getError()->id, $ws_av->pipeline_getError()->webservice,
          $ws_av->pipeline_getError()->name, $ws_av->pipeline_getError()->description,
          $ws_av->pipeline_getError()->debugInfo, $ws_av->pipeline_getError()->level);          
      }        
    }
           
    // If the system send a query on the behalf of another user, we validate that other user as well
    if($this->registered_ip != $this->requester_ip)
    {
      // Check if the requester has access to the main "http://.../wsf/datasets/" graph.
      $ws_av = new AuthValidator($this->registered_ip, $this->wsf_graph . "datasets/", $this->uri);

      $ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_av->process();

      if($ws_av->pipeline_getResponseHeaderStatus() != 200)
      {
        if($this->datasetUri != "all")
        {
          // If he doesn't, then check if he has access to the dataset itself
          $ws_av2 = new AuthValidator($this->registered_ip, $this->datasetUri, $this->uri);

          $ws_av2->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
            $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

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
        else
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
  
    if($this->datasetUri == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt("No URI specified for any dataset");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);

      return;
    }
    
    if($this->datasetUri != "all" && !$this->isValidIRI($this->datasetUri))
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

  /*!   @brief Normalize the remaining of a URI
              
      \n
      
      @param[in] $uri The remaining of a URI to normalize
      
      @return a Normalized remaining URI
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function uriEncode($uri)
  {
    $uri = preg_replace("|[^a-zA-z0-9]|", " ", $uri);
    $uri = preg_replace("/\s+/", " ", $uri);
    $uri = str_replace(" ", "_", $uri);

    return ($uri);
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
    return($this->injectDoctype($this->rset->getResultsetXML()));
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Dataset Read DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, DatasetRead::$supportedSerializations);
    
    // Validate query
    $this->validateQuery();
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

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    return($this->serializations());
  }

  /*!   @brief Read informationa about a target dataset
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      /*
        In the future, this single query should be used for that ALL purpose.
        There is currently a bug in virtuoso that doesnt return anything in the resultset (virtuoso v5.0.10)
        if one of the OPTIONAL pattern is not existing in the triple store (so, OPTIONAL doesn't work)
        
        sparql
        select * 
        from named </wsf/datasets/>
        from named </wsf/>
        where
        {
          graph </wsf/>
          {
            ?access <http://purl.org/ontology/wsf#registeredIP> "174.129.251.47::1" ;
            <http://purl.org/ontology/wsf#read> "True" ;
            <http://purl.org/ontology/wsf#datasetAccess> ?dataset .
          }
          
          graph </wsf/datasets/> 
          {
            ?dataset a <http://rdfs.org/ns/void#Dataset> ;
            <http://purl.org/dc/terms/created> ?created.
        
            OPTIONAL{?dataset <http://purl.org/dc/terms/title> ?title.}
            OPTIONAL{?dataset <http://purl.org/dc/terms/description> ?description.}
            OPTIONAL{?dataset <http://purl.org/dc/terms/creator> ?creator.}
            OPTIONAL{?dataset <http://purl.org/dc/terms/modified> ?modified.}
          }    
        };
      */

      $query = "";
      $nbDatasets = 0;
      
      if($this->datasetUri == "all")
      {
        $query = "  select distinct ?dataset ?title ?description ?creator ?created ?modified ?contributor ?meta
                  from named <" . $this->wsf_graph . ">
                  from named <" . $this->wsf_graph . "datasets/>
                  where
                  {
                    graph <" . $this->wsf_graph . ">
                    {
                      ?access <http://purl.org/ontology/wsf#registeredIP> ?ip ;
                            <http://purl.org/ontology/wsf#read> \"True\" ;
                      <http://purl.org/ontology/wsf#datasetAccess> ?dataset .
                      filter( str(?ip) = \"$this->registered_ip\" or str(?ip) = \"0.0.0.0\") .
                    }
                    
                    graph <"
          . $this->wsf_graph
          . "datasets/>
                    {
                      ?dataset a <http://rdfs.org/ns/void#Dataset> ;
                      <http://purl.org/dc/terms/created> ?created.
                  
                      OPTIONAL{?dataset <http://purl.org/ontology/wsf#meta> ?meta.}
                      OPTIONAL{?dataset <http://purl.org/dc/terms/title> ?title.}
                      OPTIONAL{?dataset <http://purl.org/dc/terms/description> ?description.}
                      OPTIONAL{?dataset <http://purl.org/dc/terms/modified> ?modified.}
                      OPTIONAL{?dataset <http://purl.org/dc/terms/contributor> ?contributor.}
                      OPTIONAL{?dataset <http://purl.org/dc/terms/creator> ?creator.}
                    }    
                  } ORDER BY ?title";

        $resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
          array ("dataset", "title", "description", "creator", "created", "modified", "contributor", "meta"), FALSE));

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
        else
        {
          $dataset = "";
          $title = "";
          $description = "";
          $creator = "";
          $created = "";
          $modified = "";
          $contributors = array();
          $meta = "";

          while(odbc_fetch_row($resultset))
          {            
            $dataset2 = odbc_result($resultset, 1);

            if($dataset2 != $dataset && $dataset != "")
            {
              $subject = new Subject($dataset);
              $subject->setType(Namespaces::$void."Dataset");
              
              if($title != ""){$subject->setDataAttribute(Namespaces::$dcterms."title", $title);}
              if($description != ""){$subject->setDataAttribute(Namespaces::$dcterms."description", $description);}
              if($creator != ""){$subject->setObjectAttribute(Namespaces::$dcterms."creator", $creator, null, "sioc:User");}
              if($created != ""){$subject->setDataAttribute(Namespaces::$dcterms."created", $created);}
              if($modified != ""){$subject->setDataAttribute(Namespaces::$dcterms."modified", $modified);}
              
              foreach($contributors as $contributor)
              {
                if($contributor != "")
                {
                  $subject->setObjectAttribute(Namespaces::$dcterms."contributor", $contributor, null, "sioc:User");
                }
              }  
                
              $this->rset->addSubject($subject);  
              $nbDatasets++;
              
              $contributors = array();
            }

            $dataset = $dataset2;

            $title = odbc_result($resultset, 2);
            $description = $this->db->odbc_getPossibleLongResult($resultset, 3);

            $creator = odbc_result($resultset, 4);
            $created = odbc_result($resultset, 5);
            $modified = odbc_result($resultset, 6);
            array_push($contributors, odbc_result($resultset, 7));
            $meta = odbc_result($resultset, 8);
          }

          $metaDescription = array();

          // We have to add the meta information if available
          /*
          if($meta != "" && $this->addMeta == "true")
          {
            $query = "select ?p ?o (str(DATATYPE(?o))) as ?otype (LANG(?o)) as ?olang
                    from <" . $this->wsf_graph . "datasets/>
                    where
                    {
                      <$meta> ?p ?o.
                    }";

            $resultset =
              @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
                array ('p', 'o', 'otype', 'olang'), FALSE));

            $contributors = array();

            if(odbc_error())
            {
              $this->conneg->setStatus(500);
              $this->conneg->setStatusMsg("Internal Error");
              $this->conneg->setStatusMsgExt($this->errorMessenger->_305->name);
              $this->conneg->setError($this->errorMessenger->_305->id, $this->errorMessenger->ws,
                $this->errorMessenger->_305->name, $this->errorMessenger->_305->description, odbc_errormsg(),
                $this->errorMessenger->_305->level);

              return;
            }
            else
            {
              while(odbc_fetch_row($resultset))
              {
                $predicate = odbc_result($resultset, 1);
                $object = $this->db->odbc_getPossibleLongResult($resultset, 2);
                $otype = odbc_result($resultset, 3);
                $olang = odbc_result($resultset, 4);

                if(isset($metaDescription[$predicate]))
                {
                  array_push($metaDescription[$predicate], $object);
                }
                else
                {
                  $metaDescription[$predicate] = array( $object );

                  if($olang && $olang != "")
                  {
                    // If a language is defined for an object, we force its type to be xsd:string
                    $metaDescription[$predicate]["type"] = "http://www.w3.org/2001/XMLSchema#string";
                  }
                  else
                  {
                    $metaDescription[$predicate]["type"] = $otype;
                  }
                }
              }
            }

            unset($resultset);
          }*/

          if($dataset != "")
          {
            $subject = new Subject($dataset);
            $subject->setType(Namespaces::$void."Dataset");
            
            if($title != ""){$subject->setDataAttribute(Namespaces::$dcterms."title", $title);}
            if($description != ""){$subject->setDataAttribute(Namespaces::$dcterms."description", $description);}
            if($creator != ""){$subject->setObjectAttribute(Namespaces::$dcterms."creator", $creator, null, "sioc:User");}
            if($created != ""){$subject->setDataAttribute(Namespaces::$dcterms."created", $created);}
            if($modified != ""){$subject->setDataAttribute(Namespaces::$dcterms."modified", $modified);}
            
            foreach($contributors as $contributor)
            {
              if($contributor != "")
              {
                $subject->setObjectAttribute(Namespaces::$dcterms."contributor", $contributor, null, "sioc:User");
              }
            }  
              
            $this->rset->addSubject($subject);  
            $nbDatasets++;
          }

          unset($resultset);
        }
      }
      else
      {
        $dataset = $this->datasetUri;

        $query =
          "select ?title ?description ?creator ?created ?modified ?meta
                from named <" . $this->wsf_graph . "datasets/>
                where
                {
                  graph <" . $this->wsf_graph
          . "datasets/>
                  {
                    <$dataset> a <http://rdfs.org/ns/void#Dataset> ;
                    <http://purl.org/dc/terms/created> ?created.
                    
                    OPTIONAL{<$dataset> <http://purl.org/dc/terms/title> ?title.} .
                    OPTIONAL{<$dataset> <http://purl.org/dc/terms/description> ?description.} .
                    OPTIONAL{<$dataset> <http://purl.org/dc/terms/creator> ?creator.} .
                    OPTIONAL{<$dataset> <http://purl.org/dc/terms/modified> ?modified.} .
                    OPTIONAL{<$dataset> <http://purl.org/ontology/wsf#meta> ?meta.} .
                  }
                } ORDER BY ?title";

        $resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
          array ('title', 'description', 'creator', 'created', 'modified', 'meta'), FALSE));

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
        else
        {
          if(odbc_fetch_row($resultset))
          {
            $title = odbc_result($resultset, 1);
            $description = $this->db->odbc_getPossibleLongResult($resultset, 2);
            $creator = odbc_result($resultset, 3);
            $created = odbc_result($resultset, 4);
            $modified = odbc_result($resultset, 5);
            $meta = odbc_result($resultset, 6);

            unset($resultset);

            /*
            $metaDescription = array();

            // We have to add the meta information if available
            if($meta != "" && $this->addMeta == "true")
            {
              $query = "select ?p ?o (str(DATATYPE(?o))) as ?otype (LANG(?o)) as ?olang
                      from <" . $this->wsf_graph . "datasets/>
                      where
                      {
                        <$meta> ?p ?o.
                      }";

              $resultset =
                @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
                  array ('p', 'o', 'otype', 'olang'), FALSE));

              $contributors = array();

              if(odbc_error())
              {
                $this->conneg->setStatus(500);
                $this->conneg->setStatusMsg("Internal Error");
                $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
                $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
                  $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, odbc_errormsg(),
                  $this->errorMessenger->_302->level);

                return;
              }
              else
              {
                while(odbc_fetch_row($resultset))
                {
                  $predicate = odbc_result($resultset, 1);
                  $object = $this->db->odbc_getPossibleLongResult($resultset, 2);
                  $otype = odbc_result($resultset, 3);
                  $olang = odbc_result($resultset, 4);

                  if(isset($metaDescription[$predicate]))
                  {
                    array_push($metaDescription[$predicate], $object);
                  }
                  else
                  {
                    $metaDescription[$predicate] = array( $object );

                    if($olang && $olang != "")
                    {
                      // If a language is defined for an object, we force its type to be xsd:string 
                      $metaDescription[$predicate]["type"] = "http://www.w3.org/2001/XMLSchema#string";
                    }
                    else
                    {
                      $metaDescription[$predicate]["type"] = $otype;
                    }
                  }
                }
              }

              unset($resultset);
            }*/


            // Get all contributors (users that have CUD perissions over the dataset)
            $query =
              "select ?contributor 
                    from <" . $this->wsf_graph
              . "datasets/>
                    where
                    {
                      <$dataset> a <http://rdfs.org/ns/void#Dataset> ;
                      <http://purl.org/dc/terms/contributor> ?contributor.
                    }";

            $resultset =
              @$this->db->query($this->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
                array( 'contributor' ), FALSE));

            $contributors = array();

            if(odbc_error())
            {
              $this->conneg->setStatus(500);
              $this->conneg->setStatusMsg("Internal Error");
              $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
              $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
                $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, odbc_errormsg(),
                $this->errorMessenger->_303->level);

              return;
            }
            elseif(odbc_fetch_row($resultset))
            {
              array_push($contributors, odbc_result($resultset, 1));
            }
            
            $subject = new Subject($dataset);
            $subject->setType(Namespaces::$void."Dataset");
            
            if($title != ""){$subject->setDataAttribute(Namespaces::$dcterms."title", $title);}
            if($description != ""){$subject->setDataAttribute(Namespaces::$dcterms."description", $description);}
            if($creator != ""){$subject->setObjectAttribute(Namespaces::$dcterms."creator", $creator, null, "sioc:User");}
            if($created != ""){$subject->setDataAttribute(Namespaces::$dcterms."created", $created);}
            if($modified != ""){$subject->setDataAttribute(Namespaces::$dcterms."modified", $modified);}
            
            foreach($contributors as $contributor)
            {
              if($contributor != "")
              {
                $subject->setObjectAttribute(Namespaces::$dcterms."contributor", $contributor, null, "sioc:User");
              }
            }  
              
            $this->rset->addSubject($subject);  
            $nbDatasets++;
          }
        }
      }
      
      if($nbDatasets == 0 && $this->datasetUri != "all")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt("This dataset doesn't exist in this WSF");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
        $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
          $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, "",
          $this->errorMessenger->_304->level);
      }
    }
  }
}
              
//@}

?>