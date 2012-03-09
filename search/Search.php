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
  
  private $includeAttributesList = array();

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

  /*! @brief Include aggregates to the resultset */
  public $includeAggregates = array();
  
  /*! @brief Attributes URI for which we want the aggregations of their values */
  public $aggregateAttributes = array();
  
  /*! @brief Specifies the type of the aggregated values for the list of aggregate attributes requested
             for this query. This value can be: (1) "literal" or, (2) "uri" */
  public $aggregateAttributesObjectType = "literal";
  
  /*! @brief Number of aggregated values to return for each attribute of the list of aggregated attributes requested
             for this query. If this value is "-1", then it means all the possible values. */
  public $aggregateAttributesNb = 10;

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", 
           "application/iron+json", "application/iron+csv", "text/xml", "text/*", "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/search/",
                        "_200": {
                          "id": "WS-SEARCH-200",
                          "level": "Warning",                          
                          "name": "Invalid number of items requested",
                          "description": "The number of items returned per request has to be greater than 0 and lesser than 300"
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
                       $includeAttributesList = "", $aggregate_attributes_object_type = "literal",
                       $aggregate_attributes_nb = 10)
  {
    parent::__construct();
 
    $this->query = $query;
    
    $this->attributes = $attributes;
    $this->items = $items;
    $this->page = $page;
    $this->inference = $inference;
    $this->includeAggregates = $include_aggregates;
    $this->attributesBooleanOperator = strtoupper($attributesBooleanOperator);
    $this->aggregateAttributesObjectType = $aggregate_attributes_object_type;
    $this->aggregateAttributesNb = $aggregate_attributes_nb;
    
    if($includeAttributesList != "")
    {
      $this->includeAttributesList = explode(";", $includeAttributesList);
    }
    
    if($aggregate_attributes != "")
    {
      $aas = explode(";", $aggregate_attributes);
      
      for($i = 0; $i < count($aas); $i++)
      {
        if($this->aggregateAttributesObjectType == "uri")
        {
          $aas[$i] = $aas[$i]."_attr_obj_uri";
        }
        else // "literal" and all other unknown type
        {
          $aas[$i] = $aas[$i]."_attr_facets";
        }
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
      if($this->items < 0 || $this->items > 300)
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
      $solr = new Solr($this->wsf_solr_core, $this->solr_host, $this->solr_port, $this->fields_index_folder);

      $solrQuery = "";

      // Get all datasets accessible to that user
      $accessibleDatasets = array();

      $ws_al = new AuthLister("access_user", "", $this->registered_ip, $this->wsf_local_ip, "none");

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

        $ws_al = new AuthLister("access_user", "", $this->requester_ip, $this->wsf_local_ip, "none");

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
              
              case Namespaces::$geo."lat":
                if(!is_numeric(urldecode($attributeValue[1])))
                {
                  // If the value is not numeric, we skip that attribute/value. 
                  // Otherwise an exception will be raised by Solr.
                  continue;
                }
                              
                $attribute = "lat";
                $coreAttr = TRUE;
              break;
              
              case Namespaces::$geo."long":
                if(!is_numeric(urldecode($attributeValue[1])))
                {
                  // If the value is not numeric, we skip that attribute/value. 
                  // Otherwise an exception will be raised by Solr.
                  continue;
                }
                
                $attribute = "long";
                $coreAttr = TRUE;
              break;
              
              case Namespaces::$sco."polygonCoordinates":              
                $attribute = "polygonCoordinates";
                $coreAttr = TRUE;
              break;
              
              case Namespaces::$sco."polylineCoordinates":
                $attribute = "polylineCoordinates";
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
                    $solrQuery .= "&fq=(".$attribute.":".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
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
                  $solrQuery .= "(".urlencode(urlencode($attribute)).":".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";  
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(array_search(urlencode($attribute."_attr"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr:".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(array_search(urlencode($attribute."_attr_obj"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj:".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
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
                    $solrQuery .= " ".$this->attributesBooleanOperator." (".$attribute.":".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
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
                  $solrQuery .= "(".urlencode(urlencode($attribute)).":".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(array_search(urlencode($attribute."_attr"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr:".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
                  $addOR = TRUE;
                  $empty = FALSE;
                }

                if(array_search(urlencode($attribute."_attr_obj"), $indexedFields) !== FALSE)
                {
                  if($addOR)
                  {
                    $solrQuery .= " OR ";
                  }
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj:".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
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
      if(count($this->aggregateAttributes) > 0 && strtolower($this->includeAggregates) == "true") 
      {
        foreach($this->aggregateAttributes as $attribute)
        {
          $solrQuery .= "&facet.field=".urlencode($attribute);
          $solrQuery .= "&f.".urlencode($attribute).".facet.limit=".$this->aggregateAttributesNb;
        }
      }
      
      // Only return these fields in the resultset
      if(count($this->includeAttributesList) > 0)
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
        $solrQuery .= "polygonCoordinates ";
        $solrQuery .= "polylineCoordinates ";
        $solrQuery .= "type ";
        $solrQuery .= "uri ";
        $solrQuery .= "locatedIn ";
        $solrQuery .= "dataset ";
        $solrQuery .= "prefURL ";
        $solrQuery .= "geohash ";
        $solrQuery .= "inferred_type ";
        $solrQuery .= "prefLabelAutocompletion";
        
      }
      
      // Remove possible left-over introduced by the procedure above for some rare usecases.
      $solrQuery = str_replace("fq= OR ", "fq=", $solrQuery);      
      $solrQuery = str_replace("fq= AND ", "fq=", $solrQuery);      

      $resultset = $solr->select($solrQuery);

      
      
      // Create the internal representation of the resultset.      
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
      
      // Set types aggregates
      foreach($founds as $found)
      {
        $subject = new Subject($this->uri . "aggregate/" . md5(microtime()));
        $subject->setType(Namespaces::$aggr."Aggregate");
        $subject->setObjectAttribute(Namespaces::$aggr."property", Namespaces::$rdf."type");
        $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
        $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
        $this->rset->addSubject($subject);
      }

      // Set types aggregates for inferred types
      if(strtolower($this->inference) == "on")
      {
        // Get all the "inferred_type" facets with their counts
        $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='inferred_type']/int");

        // Get types counts
        foreach($founds as $found)
        {
          $subject = new Subject($this->uri . "aggregate/" . md5(microtime()));
          $subject->setType(Namespaces::$aggr."Aggregate");
          $subject->setObjectAttribute(Namespaces::$aggr."property", Namespaces::$rdf."type");
          $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
          $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
          $this->rset->addSubject($subject);
        }
      }                  
      
      // Set the dataset aggregates
      $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='dataset']/int");

      foreach($founds as $found)
      {
        $subject = new Subject($this->uri . "aggregate/" . md5(microtime()));
        $subject->setType(Namespaces::$aggr."Aggregate");
        $subject->setObjectAttribute(Namespaces::$aggr."property", Namespaces::$void."Dataset");
        $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
        $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
        $this->rset->addSubject($subject);        
      }

      // Set the attributes aggregates
      $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='attribute']/int");

      foreach($founds as $found)
      {
        $subject = new Subject($this->uri . "aggregate/" . md5(microtime()));
        $subject->setType(Namespaces::$aggr."Aggregate");
        $subject->setObjectAttribute(Namespaces::$aggr."property", Namespaces::$rdf."Property");
        $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
        $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
        $this->rset->addSubject($subject);        
      }
      
      // Set all the attributes/values aggregates
      foreach($this->aggregateAttributes as $attribute)
      {
        $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='$attribute']/int");

        foreach($founds as $found)
        {
          $subject = new Subject($this->uri . "aggregate/" . md5(microtime()));
          $subject->setType(Namespaces::$aggr."Aggregate");
          $subject->setObjectAttribute(Namespaces::$aggr."property", str_replace(array("_attr_facets", "_attr_obj_uri"), "", urldecode($attribute)));
          
          if($this->aggregateAttributesObjectType == "uri")
          {          
            $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
          }
          else
          {
            $subject->setDataAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
          }
          
          $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
          $this->rset->addSubject($subject);
        }          
      }


      // Set all the results
      $resultsDom = $xpath->query("//doc");

      foreach($resultsDom as $result)
      {
        // get URI
        $resultURI = $xpath->query("arr[@name='uri']/str", $result);

        $uri = "";

        if($resultURI->length > 0)
        {
          $uri = $resultURI->item(0)->nodeValue;
        }
        else
        {
          continue;
        }
        
        $subject = new Subject($uri);
        
        // get Dataset URI
        $resultDatasetURI = $xpath->query("arr[@name='dataset']/str", $result);

        $datasetUri = "";

        if($resultDatasetURI->length > 0)
        {
          $subject->setObjectAttribute(Namespaces::$dcterms."isPartOf", $resultDatasetURI->item(0)->nodeValue);
        }                

        // get records preferred label
        $resultPrefLabelURI = $xpath->query("arr[@name='prefLabel']/str", $result);

        if($resultPrefLabelURI->length > 0)
        {
          $subject->setPrefLabel($resultPrefLabelURI->item(0)->nodeValue);
        }

        // get records aternative labels
        $resultAltLabelURI = $xpath->query("arr[@name='altLabel']/str", $result);

        for($i = 0; $i < $resultAltLabelURI->length; ++$i) 
        {
          $subject->setAltLabel($resultAltLabelURI->item($i)->nodeValue);
        }
                
        // Get possible Lat/Long and shapes descriptions
        
        // First check if there is a polygonCoordinates pr a polylineCoordinates attribute for that record
        // If there is one, then we simply ignore the lat/long coordinates since they come from these
        // attributes and that we don't want to duplicate that information.
        $skipLatLong == FALSE;
        
        $resultPolygonCoordinates = $xpath->query("arr[@name='polygonCoordinates']/str", $result);
        
        if($resultPolygonCoordinates->length > 0)
        {
          foreach($resultPolygonCoordinates as $value)
          {
            $subject->setDataAttribute(Namespaces::$sco."polygonCoordinates", $value->nodeValue);
          }            
          
          $skipLatLong = TRUE;
        }
        
        $resultPolylineCoordinates = $xpath->query("arr[@name='polylineCoordinates']/str", $result);
        
        if($resultPolylineCoordinates->length > 0)
        {
          foreach($resultPolylineCoordinates as $value)
          {
            $subject->setDataAttribute(Namespaces::$sco."polylineCoordinates", $value->nodeValue);
          }            
          
          $skipLatLong = TRUE;
        }          
        
        if(!$skipLatLong)
        {
          $resultDescriptionLat = $xpath->query("arr[@name='lat']/double", $result);

          if($resultDescriptionLat->length > 0)
          {
            $subject->setDataAttribute(Namespaces::$geo."lat", $resultDescriptionLat->item(0)->nodeValue);
          }

          $resultDescriptionLong = $xpath->query("arr[@name='long']/double", $result);

          if($resultDescriptionLong->length > 0)
          {
            $subject->setDataAttribute(Namespaces::$geo."long", $resultDescriptionLong->item(0)->nodeValue);
          }
        }

        // get records description
        $resultDescriptionURI = $xpath->query("arr[@name='description']/str", $result);

        if($resultDescriptionURI->length > 0)
        {
          $subject->setDescription($resultDescriptionURI->item(0)->nodeValue);
        }

        // Get all dynamic fields attributes.
        $resultProperties = $xpath->query("arr", $result);

        $objectPropertyLabels = array();
        $objectPropertyUris = array();
        
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
                $subject->setDataAttribute($attributeURI, $value->nodeValue);
              }
            break;

            case "_attr_obj":
              $values = $property->getElementsByTagName("str");

              foreach($values as $value)
              {
                if(!is_array($objectPropertyLabels[$attributeURI]))
                {
                  $objectPropertyLabels[$attributeURI] = array();
                }
                
                array_push($objectPropertyLabels, $value->nodeValue);
              }
            break;

            case "_attr_obj_uri":
              $values = $property->getElementsByTagName("str");

              foreach($values as $value)
              {
                if(!is_array($objectPropertyUris[$attributeURI]))
                {
                  $objectPropertyUris[$attributeURI] = array();
                }
                
                array_push($objectPropertyUris, $value->nodeValue);
              }
            break;

            case "_reify_attr":
            case "_reify_attr_obj":
            case "_reify_obj":
            case "_reify_value": break;
          }
        }
        
        foreach($objectPropertyUris as $attributeUri => $objectUris)
        {
          foreach($objectUris as $key => $objectUris)
          {
            if(isset($objectPropertyLabels[$attributeUri][$key]))
            {
              $subject->setObjectAttribute($attributeUri, $objectUri, Array(Namespaces::$wsf."objectLabel" => Array($label = $objectPropertyLabels[$attributeUri][$key])));
            }
            else
            {
              $subject->setObjectAttribute($attributeUri, $objectUri);
            }
          }
        }
        
        unset($objectPropertyUris);
        unset($objectPropertyLabels);

        // Get the types of the resource.
        $resultTypes = $xpath->query("arr[@name='type']/str", $result);

        for($i = 0; $i < $resultTypes->length; ++$i) 
        {
          if($resultTypes->item($i)->nodeValue != "-")
          {
            $subject->setType($resultTypes->item($i)->nodeValue);
          }
        }
        
        $this->rset->addSubject($subject);
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
