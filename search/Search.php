<?php

/*! @ingroup WsSearch */
//@{

/*! @file \ws\search\Search.php
   @brief Define the Search web service
  
   \n\n
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief   Search Web Service. It searches datasets by using three filtering properties: 
           (1) datasets, (2) types and (3) attributes, (4) attribute/value
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class Search extends WebService
{
  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief List of attributes to filter */
  private $attributes = "";

  /*! @brief List of types to filter */
  private $types = "";

  /*! @brief List of datasets to search */
  private $datasets = "";

  /*! @brief Number of items to return per page */
  private $items = "";

  /*! @brief Page number to return */
  private $page = "";

  /*! @brief Enabling the inference engine */
  private $inference = "";

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";
  
  /*! @brief Global query filtering parameter */
  private $query = "";
  
  private $attributesBooleanOperator = "and";
  
  private $includeAttributesList = "";

  /*! @brief Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr");

  
  /* @brief The distance filter is a series of parameter that are used to
            filter records of the dataset according to the distance they
            are located from a given lat;long point. The values are
            seperated by a semi-column ";". The format is as follow:
            "lat;long;distance;distanceType". The distanceType can
            have two values "0" or "1": "0" means that the distance
            specified is in kilometers and "1" means that the distance
            specified is in miles. An example is:
            "-98.45;10.4324;5;0", which means getting all the results that
            are at maximum 5 kilometers from the lat/long position.
  */
  private $distanceFilter;
  
  /* @brief The range filter is a series of parameter that are used to
            filter records of the dataset according to a rectangle bounds
            they are located in given their lat;long position. The values are
            seperated by a semi-column ";". The format is as follow:
            "top-left-lat;top-left-long;bottom-right-lat;bottom-right-long;".
  */
  private $rangeFilter;

  /*! @brief Array of triples where the current resource is a subject. */
  public $subjectTriples = array(); //

  /*! @brief Array of triples where the current resource is an object. */
  public $objectTriples = array();

  /*! @brief Resultset returned by Solr */
  public $resultset = array();

  /*! @brief Resultset of object properties returned by Solr */
  public $resultsetObjectProperties = array();

  /*! @brief Resultset of object properties URIs returned by Solr */
  public $resultsetObjectPropertiesUris = array();

  /*! @brief Aggregates of the search */
  public $aggregates = array();

  /*! @brief Include aggregates to the resultset */
  public $includeAggregates = array();
  
  /*! @brief Attributes URI for which we want the aggregations of their values */
  public $aggregateAttributes = array();

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/search/",
                        "_200": {
                          "id": "WS-SEARCH-200",
                          "level": "Warning",                          
                          "name": "Invalid number of items requested",
                          "description": "The number of items returned per request has to be greater than 0 and lesser than 128"
                        },
                        "_300": {
                          "id": "WS-SEARCH-300",
                          "level": "Warning",
                          "name": "No datasets accessible by that user",
                          "description": "No datasets are accessible to that user"
                        },
                        "_301": {
                          "id": "WS-SEARCH-301",
                          "level": "Warning",
                          "name": "Not geo-enabled",
                          "description": "The Search web service endpoint is not geo-enabled. Please modify your query such that it does not use any geo feature such as the distance_filter and the range_filter parameters."
                        }  
                        
                      }';


  /*!   @brief Constructor
       @details   Initialize the Search webservice endpoint
      
      @param[in] $query Global query filtering parameter  
      @param[in] $types List of filtering types URIs separated by ";"
      @param[in] $attributes List of filtering attributes (property) of (encoded) URIs separated by ";". 
                             Additionally, the URI can end with a (un-encoded) double-colon "::". What follows
                             this colon is a possible value restriction to be applied, as a filter
                             to this attribute. The lucene query syntax can be used for that filtering
                             value. The value also has to be encoded. An example of this "attribute" parameter is: 
                             "http%3A%2F%2Fsome-attribute-uri::some%2Bfiltering%2Bvalue"
      @param[in] $datasets List of filtering datasets URIs separated by ";"
      @param[in] $items Number of items returned by resultset
      @param[in] $page Starting item number of the returned resultset
      @param[in] $inference Enabling inference on types
      @param[in] $include_aggregates Including aggregates with returned resultsets
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
      @param[in] $distanceFilter The distance filter is a series of parameter that are used to
                                 filter records of the dataset according to the distance they
                                 are located from a given lat;long point. The values are
                                 seperated by a semi-column ";". The format is as follow:
                                 "lat;long;distance;distanceType". The distanceType can
                                 have two values "0" or "1": "0" means that the distance
                                 specified is in kilometers and "1" means that the distance
                                 specified is in miles. An example is:
                                 "-98.45;10.4324;5;0", which means getting all the results that
                                 are at maximum 5 kilometers from the lat/long position.
      @param[in] $rangeFilter The range filter is a series of parameter that are used to
                              filter records of the dataset according to a rectangle bounds
                              they are located in given their lat;long position. The values are
                              seperated by a semi-colon ";". The format is as follow:
                              "top-left-lat;top-left-long;bottom-right-lat;bottom-right-long". 
      @param[in] $aggregate_attributes Specify a set of attributes URI for which we want their aggregated
                                       values. The URIs should be url-encoded. Each attribute for which we
                                       want the aggregated values should be seperated by a semi-colon ";".
      @param[in] $includeAttributesList A list of attribute URIs to include into the resultset. Sometime, you may 
                                        be dealing with datasets where the description of the entities are composed 
                                        of thousands of attributes/values. Since the Crud: Read web service endpoint 
                                        returns the complete entities descriptions in its resultsets, this parameter 
                                        enables you to restrict the attribute/values you want included in the 
                                        resultset which considerably reduce the size of the resultset to transmit 
                                        and manipulate. Multiple attribute URIs can be added to this parameter by 
                                        splitting them with ";".
      
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($query, $types, $attributes, $datasets, $items, $page, $inference, $include_aggregates,
                       $registered_ip, $requester_ip, $distanceFilter = "", $rangeFilter = "", 
                       $aggregate_attributes = "", $attributesBooleanOperator = "and",
                       $includeAttributesList = "")
  {
    parent::__construct();
 
    $this->query = $query;
    
    $this->attributes = $attributes;
    $this->items = $items;
    $this->page = $page;
    $this->inference = $inference;
    $this->includeAggregates = $include_aggregates;
    $this->attributesBooleanOperator = strtoupper($attributesBooleanOperator);
    
    $this->includeAttributesList = explode(";", $includeAttributesList);
    
    if($aggregate_attributes != "")
    {
      $aas = explode(";", $aggregate_attributes);
      
      for($i = 0; $i < count($aas); $i++)
      {
        $aas[$i] = $aas[$i]."_attr_facets";
      }
      
      $this->aggregateAttributes = $aas;
    }

    $this->types = $types;
    $this->datasets = $datasets;

    $this->distanceFilter = $distanceFilter;
    $this->rangeFilter = $rangeFilter;
    $this->distance = $distance;
    $this->distanceType = $distanceType;
    
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

    $this->uri = $this->wsf_base_url . "/wsf/ws/search/";
    $this->title = "Search Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/search/";

    $this->dtdURL = "search/search.dtd";

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct() { parent::__destruct(); }

  /*!   @brief Validate a query to this web service
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery() 
  {  
    if(($this->distanceFilter != "" || $this->rangeFilter != "") && $this->geoEnabled === FALSE)
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
      $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
        $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, "",
        $this->errorMessenger->_301->level);

      return;      
    }
    
  // Here we can have a performance problem when "dataset = all" if we perform the authentication using AuthValidator.
  // Since AuthValidator doesn't support multiple datasets at the same time, we will use the AuthLister web service
  // in the process() function and check if the user has the permissions to "read" these datasets.
  //
  // This means that the validation of these queries doesn't happen at this level.
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

    $subject;

    foreach($this->resultset as $datasetUri => $results)
    {
      foreach($results as $uri => $result)
      {
        // Assigning types
        if(isset($result["type"]))
        {
          foreach($result["type"] as $key => $type)
          {
            if($key > 0)
            {
              if(array_search($type, $this->resultsetObjectPropertiesUris[$uri]["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"]) === FALSE)
              {
                $pred = $xml->createPredicate("http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
                $object = $xml->createObject("", $type);
                $pred->appendChild($object);
                $subject->appendChild($pred);
              }
            }
            else
            {
              $subject = $xml->createSubject($type, $uri);
            }
          }
        }
        else
        {
          $subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $this->resourceUri);
        }

        // Assigning the Dataset relationship
        if(isset($result["dataset"]))
        {
          $pred = $xml->createPredicate("http://purl.org/dc/terms/isPartOf");
          $object = $xml->createObject("http://rdfs.org/ns/void#Dataset", $result["dataset"]);
          $pred->appendChild($object);
          $subject->appendChild($pred);
        }

        // Assigning the preferred label relationship
        if(isset($result["prefLabel"]))
        {
          $pred = $xml->createPredicate(Namespaces::$iron . "prefLabel");
          $object = $xml->createObjectContent($result["prefLabel"][0]);
          $pred->appendChild($object);
          $subject->appendChild($pred);
        }

        // Assigning the alternative label relationship
        if(isset($result["altLabel"]))
        {
          foreach($result["altLabel"] as $altLabel)
          {
            $pred = $xml->createPredicate(Namespaces::$iron . "altLabel");
            $object = $xml->createObjectContent($altLabel);
            $pred->appendChild($object);
            $subject->appendChild($pred);
          }
        }

        // Assigning the description relationship
        if(isset($result["description"]))
        {
          $pred = $xml->createPredicate(Namespaces::$iron . "description");
          $object = $xml->createObjectContent($result["description"]);
          $pred->appendChild($object);
          $subject->appendChild($pred);
        }


        // Assigning the Properties -> Literal relationships
        foreach($result as $property => $values)
        {
          if($property != "type" && $property != "dataset" &&
             $property != "prefLabel" && $property != "altLabel" &&
             $property != "description")
          {
            foreach($values as $value)
            {
              $ns = $this->getNamespace($property);

              if($ns !== FALSE && !isset($this->namespaces[$ns[0]]))
              {                                         
                // Make sure the ID is not already existing. Increase the counter if it is the case.
                while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                {
                  $nsId++;
                } 
                               
                $this->namespaces[$ns[0]] = "ns" . $nsId;
                $nsId++;
              } 
              
              $pred = $xml->createPredicate($this->namespaces[$ns[0]] . ":" . $ns[1]);              
              
              $object = $xml->createObjectContent($value);
              $pred->appendChild($object);
              $subject->appendChild($pred);
            }
          }
        }

        // Assigning object properties
        if(isset($this->resultsetObjectProperties[$uri]))
        {
          foreach($this->resultsetObjectProperties[$uri] as $property => $values)
          {
            if($property != "type" && $property != "dataset" &&
               $property != "prefLabel" && $property != "altLabel" &&
               $property != "description")
            {
              foreach($values as $key => $value)
              {
                $ns = $this->getNamespace($property);

                if($ns !== FALSE && !isset($this->namespaces[$ns[0]]))
                {
                  // Make sure the ID is not already existing. Increase the counter if it is the case.
                  while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                  {
                    $nsId++;
                  }
                                    
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                } 
                
                $pred = $xml->createPredicate($this->namespaces[$ns[0]] . ":" . $ns[1]);

                $object = $xml->createObject("", $this->resultsetObjectPropertiesUris[$uri][$property][$key], "");
                $pred->appendChild($object);

                $reify = $xml->createReificationStatement("wsf:objectLabel", $value);
                $object->appendChild($reify);

                $subject->appendChild($pred);
              }
            }
          }
        }

        $resultset->appendChild($subject);
      }
    }


    // Include facet information

    // Type

    if(strtolower($this->includeAggregates) == "true")
    {
      $aggregatesUri = $this->uri . "aggregate/" . md5(microtime());

      $typeLabelsCounts = array();

      foreach($this->aggregates["type"] as $ftype => $fcount)
      {
        // If we have an inferred type, we use that count instead of the normal count.
        if(isset($this->aggregates["inferred_type"][$ftype]))
        {
          $fcount = $this->aggregates["inferred_type"][$ftype];
        }

        $subject =
          $xml->createSubject("http://purl.org/ontology/aggregate#Aggregate", $aggregatesUri . "/" . md5($ftype) . "/");

        $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#property");
        $object = $xml->createObject("", "http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#object");
        $object = $xml->createObject("", $ftype);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#count");
        $object = $xml->createObjectContent($fcount);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $resultset->appendChild($subject);

        $typeLabelsCounts = array();
      }

      // For each inferred type that have been left so far, we re-introduce them in the aggregates
      foreach($this->aggregates["inferred_type"] as $ftype => $fcount)
      {
        // If we have an inferred type, we use that count instead of the normal count.
        if(!isset($this->aggregates["type"][$ftype]))
        {
          $subject = $xml->createSubject("http://purl.org/ontology/aggregate#Aggregate",
            $aggregatesUri . "/" . md5($ftype) . "/");

          $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#property");
          $object = $xml->createObject("", "http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
          $pred->appendChild($object);
          $subject->appendChild($pred);

          $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#object");
          $object = $xml->createObject("", $ftype);
          $pred->appendChild($object);
          $subject->appendChild($pred);

          $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#count");
          $object = $xml->createObjectContent($fcount);
          $pred->appendChild($object);
          $subject->appendChild($pred);

          $resultset->appendChild($subject);
        }
      }

      // Dataset

      $aggregatesUri = $this->uri . "aggregate/" . md5(microtime());

      foreach($this->aggregates["dataset"] as $ftype => $fcount)
      {
        $subject =
          $xml->createSubject("http://purl.org/ontology/aggregate#Aggregate", $aggregatesUri . "/" . md5($ftype) . "/");

        $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#property");
        $object = $xml->createObject("", "http://rdfs.org/ns/void#Dataset");
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#object");
        $object = $xml->createObject("", $ftype);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#count");
        $object = $xml->createObjectContent($fcount);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $resultset->appendChild($subject);
      }


      // Attributes

      $aggregatesUri = $this->uri . "aggregate/" . md5(microtime());

      foreach($this->aggregates["attributes"] as $ftype => $fcount)
      {
        $subject =
          $xml->createSubject("http://purl.org/ontology/aggregate#Aggregate", $aggregatesUri . "/" . md5($ftype) . "/");

        $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#property");
        $object = $xml->createObject("", "http://www.w3.org/1999/02/22-rdf-syntax-ns#Property");
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#object");
        $object = $xml->createObject("", $ftype);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#count");
        $object = $xml->createObjectContent($fcount);
        $pred->appendChild($object);
        $subject->appendChild($pred);

        $resultset->appendChild($subject);
      }
      
      // Attribute/Values aggregates
      foreach($this->aggregateAttributes as $attribute)
      {
        $aggregatesUri = $this->uri . "aggregate/" . md5(microtime());

        foreach($this->aggregates[$attribute] as $ftype => $fcount)
        {
          $subject =
            $xml->createSubject("http://purl.org/ontology/aggregate#Aggregate", $aggregatesUri . "/" . md5($ftype) . "/");

          $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#property");
          $object = $xml->createObject("", str_replace("_attr_facets", "", urldecode($attribute)));
          $pred->appendChild($object);
          $subject->appendChild($pred);

          $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#object");
          $object = $xml->createObjectContent($ftype);
          $pred->appendChild($object);
          $subject->appendChild($pred);

          $pred = $xml->createPredicate("http://purl.org/ontology/aggregate#count");
          $object = $xml->createObjectContent($fcount);
          $pred->appendChild($object);
          $subject->appendChild($pred);

          $resultset->appendChild($subject);
        }                 
      }      
    }
    
    // Creation of the prefixes elements.
    foreach($this->namespaces as $uri => $prefix)
    {
      $ns = $xml->createPrefix($prefix, $uri);
      $resultset->appendChild($ns);
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Search DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, Search::$supportedSerializations);

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      if($this->items < 0 || $this->items > 128)
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

          if($ns !== FALSE && !isset($this->namespaces[$ns[0]]))
          {
            // Make sure the ID is not already existing. Increase the counter if it is the case.
            while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
            {
              $nsId++;
            }
            
            $this->namespaces[$ns[0]] = "ns" . $nsId;
            $nsId++;
          }

          $json_part .= "      { \n";
          $json_part .= "        \"uri\": \"" . parent::jsonEncode($subjectURI) . "\", \n";
          $json_part .= "        \"type\": \"" . parent::jsonEncode($this->namespaces[$ns[0]] . ":" . $ns[1])
            . "\",\n";

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

                if($ns !== FALSE && !isset($this->namespaces[$ns[0]]))
                {
                  // Make sure the ID is not already existing. Increase the counter if it is the case.
                  while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                  {
                    $nsId++;
                  }
                  
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

                if($ns !== FALSE && !isset($this->namespaces[$ns[0]]))
                {
                  // Make sure the ID is not already existing. Increase the counter if it is the case.
                  while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                  {
                    $nsId++;
                  }
                  
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

        $json_header .= "  \"prefixes\": \n";
        $json_header .= "    {\n";

        foreach($this->namespaces as $ns => $prefix)
        {
          $json_header .= "      \"$prefix\": \"$ns\",\n";
        }

        if(strlen($json_header) > 0)
        {
          $json_header = substr($json_header, 0, strlen($json_header) - 2) . "\n";
        }

        $json_header .= "    }, \n";
        $json_header .= "  \"resultset\": {\n";
        $json_header .= "    \"subject\": [\n";
        $json_header .= $json_part;
        $json_header .= "    ]\n";
        $json_header .= "  }\n";

        return ($json_header);
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
              $objectContent = $xml->getContent($object);

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
            $rdf_part = substr($rdf_part, 0, strlen($rdf_part) - 2) . ".\n";
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

          if($ns !== FALSE && !isset($this->namespaces[$ns1[0]]))
          {
            // Make sure the ID is not already existing. Increase the counter if it is the case.
            while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
            {
              $nsId++;
            }
                  
            $this->namespaces[$ns1[0]] = "ns" . $nsId;
            $nsId++;
          }

          $rdf_part .= "\n    <" . $this->namespaces[$ns1[0]] . ":" . $ns1[1] . " rdf:about=\"".
                                                                          $this->xmlEncode($subjectURI)."\">\n";

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

                if($ns !== FALSE && !isset($this->namespaces[$ns[0]]))
                {
                  // Make sure the ID is not already existing. Increase the counter if it is the case.
                  while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                  {
                    $nsId++;
                  }
                                    
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

                if($ns !== FALSE && !isset($this->namespaces[$ns[0]]))
                {
                  // Make sure the ID is not already existing. Increase the counter if it is the case.
                  while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                  {
                    $nsId++;
                  }
                                    
                  $this->namespaces[$ns[0]] = "ns" . $nsId;
                  $nsId++;
                }

                $rdf_part .= "        <" . $this->namespaces[$ns[0]] . ":" . $ns[1]
                  . " rdf:resource=\"".$this->xmlEncode($objectURI)."\" />\n";
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
              $reifies = $xml->getReificationStatementsByType($object, "wsf:objectLabel");

              foreach($reifies as $reify)
              {
                $rdf_reification .= "_:" . md5($xml->getURI($subject) . $predicateType . $xml->getURI($object))
                  . " a rdf:Statement ;\n";
                $bnodeCounter++;
                $rdf_reification .= "    rdf:subject <" . $xml->getURI($subject) . "> ;\n";
                $rdf_reification .= "    rdf:predicate <" . $predicateType . "> ;\n";
                $rdf_reification .= "    rdf:object <" . $xml->getURI($object) . "> ;\n";
                $rdf_reification .= "    wsf:objectLabel \"" . $xml->getValue($reify) . "\" .\n\n";
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
              $reifies = $xml->getReificationStatementsByType($object, "wsf:objectLabel");

              foreach($reifies as $reify)
              {
                $rdf_reification .= "<rdf:Statement rdf:about=\""
                  . md5($xml->getURI($subject) . $predicateType . $this->xmlEncode($xml->getURI($object))) . "\">\n";
                $rdf_reification .= "    <rdf:subject rdf:resource=\"" . $this->xmlEncode($xml->getURI($subject)) . 
                                                                                                              "\" />\n";
                $rdf_reification .= "    <rdf:predicate rdf:resource=\"" . $this->xmlEncode($predicateType) . "\" />\n";
                $rdf_reification .= "    <rdf:object rdf:resource=\"" . $this->xmlEncode($xml->getURI($object)) . 
                                                                                                              "\" />\n";
                $rdf_reification .= "    <wsf:objectLabel>" . $this->xmlEncode($xml->getValue($reify))
                  . "</wsf:objectLabel>\n";
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
        $rdf_document .= "@prefix wsf: <http://purl.org/ontology/wsf#> .\n";

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

      case "application/json":
        $json_document = "";
        $json_document .= "{\n";
        $json_document .= $this->pipeline_serialize();
        $json_document .= "}";

        return ($json_document);
      break;

      case "text/xml":
        return $this->pipeline_getResultset();
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


  /*!   @brief   Send the search query to the system supporting this web service (usually Solr) 
             and aggregate searched information
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      $solr = new Solr($this->wsf_solr_core, $this->solr_host, $this->solr_port);

      $solrQuery = "";

      // Get all datasets accessible to that user
      $accessibleDatasets = array();

      $ws_al = new AuthLister("access_user", "", $this->registered_ip, $this->wsf_local_ip);

      $ws_al->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
        $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

      $ws_al->process();

      $xml = new ProcessorXML();
      $xml->loadXML($ws_al->pipeline_getResultset());

      $accesses = $xml->getSubjectsByType("wsf:Access");

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
          array_push($accessibleDatasets, $datasetUri);
        }
      }
      
      unset($ws_al);
      
      /*
        if registered_ip != requester_ip, this means that the query is sent by a registered system
        on the behalf of someone else. In this case, we want to make sure that that system 
        (the one that send the actual query) has access to the same datasets. Otherwise, it means that
        it tries to personificate that registered_ip user.
      */
      if($this->registered_ip != $this->requester_ip)
      {
        // Get all datasets accessible to that system
        $accessibleDatasetsSystem = array();

        $ws_al = new AuthLister("access_user", "", $this->requester_ip, $this->wsf_local_ip);

        $ws_al->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(),
          $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());

        $ws_al->process();

        $xml = new ProcessorXML();
        $xml->loadXML($ws_al->pipeline_getResultset());

        $accesses = $xml->getSubjectsByType("wsf:Access");

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
            array_push($accessibleDatasetsSystem, $datasetUri);
          }
        }      
        
        unset($ws_al);         
      
        /*
          Finally we use the intersection of the two set of dataset URIs as the list of accessible
          datasets to include for the query.
        */ 
        $accessibleDatasets = array_intersect($accessibleDatasets, $accessibleDatasetsSystem);
      }

      if(count($accessibleDatasets) <= 0)
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
        $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
          $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, "",
          $this->errorMessenger->_300->level);
        return;
      }
      
      $queryParam = "*:*";
      
      $specialChars = array('"', 'AND', 'OR', '?', '*', '~', '+', '-', 'NOT', '&&', '||', '!', '^');
      
      if($this->query != "")
      {      
        /* 
          Check if characters of the Solr query language is used for this query. If some does, we *only* take
          them to create the query.
        */
        $useLuceneSyntax = FALSE;
        
        foreach($specialChars as $char)
        {
          if(strpos($this->query, $char) !== FALSE)
          {
            $useLuceneSyntax = TRUE;
            break;          
          }
        }
        
        if(!$useLuceneSyntax)
        {
          $queryParam = "%22" . urlencode(implode(" +", explode(" ", $this->escapeSolrValue($this->query)))) . "%22~5";
        }
        else
        {
          $queryParam = urlencode($this->query);
        }        
      }
      
      if(strtolower($this->datasets) == "all")
      {
        $datasetList = "";

        $solrQuery = "q=$queryParam&start=" . $this->page . "&rows=" . $this->items
          . (strtolower($this->includeAggregates) == "true" ? "&facet=true&facet.sort=count&facet.limit=-1&facet.field=type" .
          "&facet.field=attribute" . (strtolower($this->inference) == "on" ? "&facet.field=inferred_type" : "") . "&" .
          "facet.field=dataset&facet.mincount=1" : "");

        foreach($accessibleDatasets as $key => $dataset)
        {
          if($key == 0)
          {
            $solrQuery .= "&fq=dataset:%22" . urlencode($dataset) . "%22";
          }
          else
          {
            $solrQuery .= " OR dataset:%22" . urlencode($dataset) . "%22";
          }
        }
      }
      else
      {
        $datasets = explode(";", $this->datasets);

        $solrQuery = "q=$queryParam&start=" . $this->page . "&rows=" . $this->items
          . (strtolower($this->includeAggregates) == "true" ? "&facet=true&facet.sort=count&facet.limit=-1&facet.field=type" .
          "&facet.field=attribute" . (strtolower($this->inference) == "on" ? "&facet.field=inferred_type" : "") . "&" .
          "facet.field=dataset&facet.mincount=1" : "");

        $solrQuery .= "&fq=dataset:%22%22";

        foreach($datasets as $dataset)
        {
          // Check if the dataset is accessible to the user
          if(array_search($dataset, $accessibleDatasets) !== FALSE)
          {
            // Decoding potentially encoded ";" characters
            $dataset = str_replace(array ("%3B", "%3b"), ";", $dataset);

            $solrQuery .= " OR dataset:%22" . urlencode($dataset) . "%22";
          }
        }
      }

      if($this->types != "all")
      {
        // Lets include the information to facet per type.

        $types = explode(";", $this->types);

        $nbProcessed = 0;

        foreach($types as $type)
        {
          // Decoding potentially encoded ";" characters
          $type = str_replace(array ("%3B", "%3b"), ";", $type);

          if($nbProcessed == 0)
          {
            $solrQuery .= "&fq=type:%22" . urlencode($type) . "%22";
          }
          else
          {
            $solrQuery .= " OR type:%22" . urlencode($type) . "%22";
          }

          $nbProcessed++;

          if(strtolower($this->inference) == "on")
          {
            $solrQuery .= " OR inferred_type:%22" . urlencode($type) . "%22";
          }
        }
      }
      
      if($this->attributes != "all")
      {
        // Lets include the information to facet per type.

        $attributes = explode(";", $this->attributes);

        $nbProcessed = 0;
        
        if(file_exists($this->fields_index_folder."fieldsIndex.srz"))
        {
          $indexedFields = unserialize(file_get_contents($this->fields_index_folder."fieldsIndex.srz"));
        }
        else
        {
          $indexedFields = array();
        }

        foreach($attributes as $attribute)
        {
          $attributeValue = explode("::", $attribute);
          $attribute = urldecode($attributeValue[0]);

          if(isset($attributeValue[1]) && $attributeValue[1] != "")
          {
            // Fix the reference to some of the core attributes
            $coreAttr = FALSE;
            switch($attribute)
            {
              case Namespaces::$iron."prefLabel":
                $attribute = "prefLabel";
                $coreAttr = TRUE;
                
                // Check if we are performing an autocompletion task on the pref label
                $label = urldecode($attributeValue[1]);
                if(substr($label, strlen($label) - 2) == "**")
                {
                  $attribute = "prefLabelAutocompletion";
                  $attributeValue[1] = urlencode(str_replace(" ", "\\ ", substr($label, 0, strlen($label) -1)));
                }
              break;
              
              case Namespaces::$iron."altLabel":
                $attribute = "altLabel";
                $coreAttr = TRUE;
              break;
              
              case Namespaces::$iron."description":
                $attribute = "description";
                $coreAttr = TRUE;
              break;
              
              case Namespaces::$geonames."lat":
                $attribute = "lat";
                $coreAttr = TRUE;
              break;
              
              case Namespaces::$geonames."long":
                $attribute = "long";
                $coreAttr = TRUE;
              break;
              
              case Namespaces::$rdf."type":
                $attribute = "type";
                $coreAttr = TRUE;
              break;
              
              case Namespaces::$geonames."locatedIn":
                $attribute = "located_in";
                $coreAttr = TRUE;
              break;
            }            

            // A filtering value as been defined for this attribute.
            $val = urldecode($attributeValue[1]);
            
            if($nbProcessed == 0)
            {
              if($coreAttr)
              {
                switch($attribute)
                {                
                  case "type":
                    if(strtolower($this->inference) == "on")
                    {
                      $solrQuery .= "&fq=((type:".urlencode($this->escapeSolrValue($val)).") OR (inferred_type:".urlencode($this->escapeSolrValue($val))."))";
                    }
                    else
                    {
                      $solrQuery .= "&fq=(type:".urlencode($this->escapeSolrValue($val)).")";
                    }
                  break;
                  
                  case "located_in":
                    $solrQuery .= "&fq=(located_in:".urlencode($this->escapeSolrValue($val)).")";
                  break;
                  
                  default:
                    $solrQuery .= "&fq=(".$attribute.":".urlencode(preg_replace("/[^A-Za-z0-9\s\*\\\]/", " ", $val)).")";
                  break;
                }
              }
              else
              {
                $solrQuery .= "&fq=(";
                
                $addOR = FALSE;
                $empty = TRUE;                
                
                // We have to detect if the fields are existing in Solr, otherwise Solr will throw
                // "undefined fields" errors, and there is no way to ignore them and process
                // the query anyway.
                if(array_search(urlencode($attribute), $indexedFields) !== FALSE)
                {
                  $solrQuery .= "(".urlencode(urlencode($attribute)).":".urlencode(preg_replace("/[^A-Za-z0-9\s\*\\\]/", " ", $val)).")";  
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(array_search(urlencode($attribute."_attr"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr:".urlencode(preg_replace("/[^A-Za-z0-9\s\*\\\]/", " ", $val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(array_search(urlencode($attribute."_attr_obj"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj:".urlencode(preg_replace("/[^A-Za-z0-9\s\*\\\]/", " ", $val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }
                
                if(array_search(urlencode($attribute."_attr_obj_uri"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj_uri:".urlencode($this->escapeSolrValue($val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }                
                
                if($empty)
                {
                  $solrQuery = substr($solrQuery, 0, strlen($solrQuery) - 1);
                }
                else
                {
                  $solrQuery .= ")";                
                }                
              }
            }
            else
            {
              if($coreAttr)
              {
                switch($attribute)
                {                
                  case "type":
                    if(strtolower($this->inference) == "on")
                    {
                      $solrQuery .= " ".$this->attributesBooleanOperator." ((type:".urlencode($this->escapeSolrValue($val)).") OR (inferred_type:".urlencode($this->escapeSolrValue($val))."))";
                    }
                    else
                    {
                      $solrQuery .= " ".$this->attributesBooleanOperator." (type:".urlencode($this->escapeSolrValue($val)).")";
                    }
                  break;                  
                  case "located_in":
                    $solrQuery .= " ".$this->attributesBooleanOperator." (located_in:".urlencode($this->escapeSolrValue($val)).")";
                  break;
                  
                  default:
                    $solrQuery .= " ".$this->attributesBooleanOperator." (".$attribute.":".urlencode(preg_replace("/[^A-Za-z0-9\s\*\\\]/", " ", $val)).")";
                  break;
                }                
                
              }
              else
              {
                if(substr($solrQuery, strlen($solrQuery) - 3) != "fq=")
                {
                  $solrQuery .= " ".$this->attributesBooleanOperator." (";
                }
                else
                {
                  $solrQuery .= "(";
                }
                
                $addOR = FALSE;
                $empty = TRUE;
                
                // We have to detect if the fields are existing in Solr, otherwise Solr will throw
                // "undefined fields" errors, and there is no way to ignore them and process
                // the query anyway.
                if(array_search(urlencode($attribute), $indexedFields) !== FALSE)
                {
                  $solrQuery .= "(".urlencode(urlencode($attribute)).":".urlencode(preg_replace("/[^A-Za-z0-9\s\*\\\]/", " ", $val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(array_search(urlencode($attribute."_attr"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr:".urlencode(preg_replace("/[^A-Za-z0-9\s\*\\\]/", " ", $val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(array_search(urlencode($attribute."_attr_obj"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj:".urlencode(preg_replace("/[^A-Za-z0-9\s\*\\\]/", " ", $val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(array_search(urlencode($attribute."_attr_obj_uri"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj_uri:".urlencode($this->escapeSolrValue($val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(substr($solrQuery, strlen($solrQuery) - 4) != "fq=(")
                {
                  if($empty)
                  {
                    $solrQuery = substr($solrQuery, 0, strlen($solrQuery) - 5);
                  }
                  else
                  {
                    $solrQuery .= ")";                
                  }
                }
                else
                {
                  $solrQuery = substr($solrQuery, 0, strlen($solrQuery) - 1);
                }
              }
            }            
          }
          else
          {
            if($nbProcessed == 0)
            {
              $solrQuery .= "&fq=(attribute:%22" . urlencode($attribute) . "%22)";
            }
            else
            {
              $solrQuery .= " ".$this->attributesBooleanOperator." (attribute:%22" . urlencode($attribute) . "%22)";
            }
          }

          $nbProcessed++;
        }
      }
      
      // Check if this query is geo-enabled and if a distance-filter is requested
      if($this->geoEnabled && $this->distanceFilter != "")
      {
        /*
          $params[0] == latitude
          $params[1] == longitude
          $params[2] == distance
          $params[3] == (0) distance in kilometers, (1) distance in miles
        */
        $params = explode(";", $this->distanceFilter);
        
        $earthRadius = 6371;
        
        if($params[3] == 1)
        {
          $earthRadius = 3963.205;
        }
        
        $solrQuery .= "&fq={!frange l=0 u=".$params[2]."}hsin(".$params[0].",".$params[1].
                      " , lat_rad, long_rad, ".$earthRadius.")";
      }
      
      // Check if this query is geo-enabled and if a range-filter is requested
      if($this->geoEnabled && $this->rangeFilter != "")
      {
        /*
          $params[0] == latitude top-left
          $params[1] == longitude top-left
          $params[2] == latitude bottom-right
          $params[3] == longitude bottom-right
        */
        $params = explode(";", $this->rangeFilter);
        
        // Make sure the ranges are respected according to the way the cartesian coordinate
        // system works.
        $p1 = $params[0];
        $p2 = $params[2];
        $p3 = $params[1];
        $p4 = $params[3];
        
        if($params[0] > $params[2])
        {
          $p1 = $params[2];
          $p2 = $params[0];
        }
        
        if($params[1] > $params[3])
        {
          $p3 = $params[3];
          $p4 = $params[1];
        }
        
        $solrQuery .= "&fq=lat:[".$p1." TO ".$p2."]&fq=long:[".$p3." TO ".$p4."]";
      }
      
      // Add the attribute/value aggregates if needed
      if(count($this->aggregateAttributes) > 0 && $this->includeAggregates) 
      {
        foreach($this->aggregateAttributes as $attribute)
        {
          $solrQuery .= "&facet.field=".urlencode($attribute);
          $solrQuery .= "&f.".urlencode($attribute).".facet.limit=10";
        }

      }
      
      // Only return these fields in the resultset
      if($this->includeAttributesList != "")
      {
        $solrQuery .= "&fl=";
        
        foreach($this->includeAttributesList as $atl)
        {
          $solrQuery .= urlencode(urlencode($atl))."_attr ";
          $solrQuery .= urlencode(urlencode($atl))."_attr_obj ";
          $solrQuery .= urlencode(urlencode($atl))."_attr_obj_uri ";
        }
        
        // Also add the core attributes to the mixte
        $solrQuery .= "prefLabel ";
        $solrQuery .= "altLabel ";
        $solrQuery .= "description ";
        $solrQuery .= "lat ";
        $solrQuery .= "long ";
        $solrQuery .= "type ";
        $solrQuery .= "uri ";
        $solrQuery .= "locatedIn ";
        $solrQuery .= "dataset ";
        $solrQuery .= "prefURL ";
        $solrQuery .= "geohash ";
        $solrQuery .= "inferred_type ";
        $solrQuery .= "prefLabelAutocompletion";
        
      }
 
      $resultset = $solr->select($solrQuery);

      $domResultset = new DomDocument("1.0", "utf-8");

      $domResultset->loadXML($resultset);

      $xpath = new DOMXPath($domResultset);

      // Get the number of results
      $founds = $xpath->query("*[@numFound]");

      foreach($founds as $found)
      {
        $nbResources = $found->attributes->getNamedItem("numFound")->nodeValue;
        break;
      }

      // Get all the "type" facets with their counts
      $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='type']/int");

      // Get types counts

      $this->aggregates["type"] = array();

      foreach($founds as $found)
      {
        $this->aggregates["type"][$found->attributes->getNamedItem("name")->nodeValue] = $found->nodeValue;
      }

      // Get inferred types counts

      $this->aggregates["inferred_type"] = array();
      if(strtolower($this->inference) == "on")
      {
        // Get all the "inferred_type" facets with their counts
        $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='inferred_type']/int");

        // Get types counts
        foreach($founds as $found)
        {
          $this->aggregates["inferred_type"][$found->attributes->getNamedItem("name")->nodeValue] = $found->nodeValue;
        }
      }

      // Get all the "dataset" facets with their counts
      $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='dataset']/int");

      $this->aggregates["dataset"] = array();

      foreach($founds as $found)
      {
        $this->aggregates["dataset"][$found->attributes->getNamedItem("name")->nodeValue] = $found->nodeValue;
      }


      // Get all the "property" and "object_property" facets with their counts
      $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='attribute']/int");

      $this->aggregates["attributes"] = array();

      foreach($founds as $found)
      {
        $this->aggregates["attributes"][$found->attributes->getNamedItem("name")->nodeValue] = $found->nodeValue;
      }
      
      // Get all the facet attributes/values with their counts
      foreach($this->aggregateAttributes as $attribute)
      {
        $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='$attribute']/int");

        $this->aggregates[$attribute] = array();

        foreach($founds as $found)
        {
          $this->aggregates[$attribute][$found->attributes->getNamedItem("name")->nodeValue] = $found->nodeValue;
        }          
      }


      // Get all the results

      $resultsDom = $xpath->query("//doc");

      foreach($resultsDom as $result)
      {
        // get Dataset URI
        $resultDatasetURI = $xpath->query("arr[@name='dataset']/str", $result);

        $datasetUri = "";

        if($resultDatasetURI->length > 0)
        {
          $datasetUri = $resultDatasetURI->item(0)->nodeValue;
        }        
        
        // get URI
        $resultURI = $xpath->query("arr[@name='uri']/str", $result);

        $uri = "";

        if($resultURI->length > 0)
        {
          $uri = $resultURI->item(0)->nodeValue;
          $this->resultset[$datasetUri][$uri] = array();
          $this->resultset[$datasetUri][$uri]["dataset"] = $datasetUri;
        }

        // get records preferred label
        $resultPrefLabelURI = $xpath->query("arr[@name='prefLabel']/str", $result);

        if($resultPrefLabelURI->length > 0)
        {
          $this->resultset[$datasetUri][$uri]["prefLabel"] = array($resultPrefLabelURI->item(0)->nodeValue);
        }

        // get records aternative labels
        
        $resultAltLabelURI = $xpath->query("arr[@name='altLabel']/str", $result);

        for($i = 0; $i < $resultAltLabelURI->length; ++$i) 
        {
          if(!isset($this->resultset[$datasetUri][$uri]["altLabel"]))
          {
            $this->resultset[$datasetUri][$uri]["altLabel"] = array($resultAltLabelURI->item($i)->nodeValue );
          }
          else
          {
            array_push($this->resultset[$datasetUri][$uri]["altLabel"], $resultAltLabelURI->item($i)->nodeValue);
          }          
        }
                
        // Get possible Lat/Long
        // First check if there is a polygonCoordinates pr a polylineCoordinates attribute for that record
        // If there is one, then we simply ignore the lat/long coordinates since they come from these
        // attributes and that we don't want to duplicate that information.
        $skipLatLong == FALSE;
        
        $resultPolygonCoordinates = $xpath->query("arr[@name='polygonCoordinates']/str", $result);
        
        if($resultPolygonCoordinates->length > 0)
        {
          foreach($resultPolygonCoordinates as $value)
          {
            if(!isset($this->resultset[$datasetUri][$uri][Namespaces::$sco."polygonCoordinates"]))
            {
              $this->resultset[$datasetUri][$uri][Namespaces::$sco."polygonCoordinates"] = array($value->nodeValue);
            }
            else
            {
              array_push($this->resultset[$datasetUri][$uri][Namespaces::$sco."polygonCoordinates"], $value->nodeValue);
            }
          }            
          
          $skipLatLong = TRUE;
        }
        
        $resultPolylineCoordinates = $xpath->query("arr[@name='polylineCoordinates']/str", $result);
        
        if($resultPolylineCoordinates->length > 0)
        {
          foreach($resultPolylineCoordinates as $value)
          {
            if(!isset($this->resultset[$datasetUri][$uri][Namespaces::$sco."polylineCoordinates"]))
            {
              $this->resultset[$datasetUri][$uri][Namespaces::$sco."polylineCoordinates"] = array($value->nodeValue);
            }
            else
            {
              array_push($this->resultset[$datasetUri][$uri][Namespaces::$sco."polylineCoordinates"], $value->nodeValue);
            }
          }            
          
          $skipLatLong = TRUE;
        }          
        
        if(!$skipLatLong)
        {
          $resultDescriptionLat = $xpath->query("arr[@name='lat']/double", $result);

          if($resultDescriptionLat->length > 0)
          {
            $this->resultset[$datasetUri][$uri][Namespaces::$geo."lat"] = array($resultDescriptionLat->item(0)->nodeValue);
          }

          $resultDescriptionLong = $xpath->query("arr[@name='long']/double", $result);

          if($resultDescriptionLong->length > 0)
          {
            $this->resultset[$datasetUri][$uri][Namespaces::$geo."long"] = array($resultDescriptionLong->item(0)->nodeValue);
          }
        }

        // get records description
        $resultDescriptionURI = $xpath->query("arr[@name='description']/str", $result);

        if($resultDescriptionURI->length > 0)
        {
          $this->resultset[$datasetUri][$uri]["description"] = $resultDescriptionURI->item(0)->nodeValue;
        }

        // Get all dynamic fields attributes.
        $resultProperties = $xpath->query("arr", $result);

        $tempProperties = array();

        foreach($resultProperties as $property)
        {
          $attribute = $property->getAttribute("name");

          // Check what kind of attribute it is
          $attributeType = "";

          if(($pos = stripos($attribute, "_reify")) !== FALSE)
          {
            $attributeType = substr($attribute, $pos, strlen($attribute) - $pos);
          }
          elseif(($pos = stripos($attribute, "_attr")) !== FALSE)
          {
            $attributeType = substr($attribute, $pos, strlen($attribute) - $pos);
          }

          // Get the URI of the attribute
          $attributeURI = urldecode(str_replace($attributeType, "", $attribute));

          switch($attributeType)
          {
            case "_attr":
              $values = $property->getElementsByTagName("str");

              foreach($values as $value)
              {
                if(!isset($this->resultset[$datasetUri][$uri][$attributeURI]))
                {
                  $this->resultset[$datasetUri][$uri][$attributeURI] = array( $value->nodeValue );
                }
                else
                {
                  array_push($this->resultset[$datasetUri][$uri][$attributeURI], $value->nodeValue);
                }
              }
            break;

            case "_attr_obj":
              $values = $property->getElementsByTagName("str");

              foreach($values as $value)
              {
                if(!isset($this->resultsetObjectProperties[$uri][$attributeURI]))
                {
                  $this->resultsetObjectProperties[$uri][$attributeURI] = array( $value->nodeValue );
                }
                else
                {
                  array_push($this->resultsetObjectProperties[$uri][$attributeURI], $value->nodeValue);
                }
              }
            break;

            case "_attr_obj_uri":
              $values = $property->getElementsByTagName("str");

              foreach($values as $value)
              {
                if(!isset($this->resultsetObjectPropertiesUris[$uri][$attributeURI]))
                {
                  $this->resultsetObjectPropertiesUris[$uri][$attributeURI] = array( $value->nodeValue );
                }
                else
                {
                  array_push($this->resultsetObjectPropertiesUris[$uri][$attributeURI], $value->nodeValue);
                }
              }
            break;

            case "_reify_attr":
            case "_reify_attr_obj":
            case "_reify_obj":
            case "_reify_value": break;
          }
        }

        // Get the first type of the resource.
        $resultTypes = $xpath->query("arr[@name='type']/str", $result);

        for($i = 0; $i < $resultTypes->length; ++$i) 
        {
          if($resultTypes->item($i)->nodeValue != "-")
          {
            if(!isset($this->resultset[$datasetUri][$uri]["type"]))
            {
              $this->resultset[$datasetUri][$uri]["type"] = array( $resultTypes->item($i)->nodeValue );
            }
            else
            {
              array_push($this->resultset[$datasetUri][$uri]["type"], $resultTypes->item($i)->nodeValue);
            }
          }
        }
      }
    }
  }
  
  private function escapeSolrValue($string)
  {
    $match = array('\\', '+', '-', '&', '|', '!', '(', ')', '{', '}', '[', ']', '^', '~', '*', '?', ':', '"', ';', ' ');
    $replace = array('\\\\', '\\+', '\\-', '\\&', '\\|', '\\!', '\\(', '\\)', '\\{', '\\}', '\\[', '\\]', '\\^', '\\~', '\\*', '\\?', '\\:', '\\"', '\\;', '\\ ');
    $string = str_replace($match, $replace, $string);

    return $string;
  }
  
}


//@}

?>
