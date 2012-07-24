<?php

/*! @ingroup WsSearch */
//@{

/*! @file \StructuredDynamics\structwsf\ws\search\Search.php
    @brief Define the Search web service
 */

namespace StructuredDynamics\structwsf\ws\search; 


use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\framework\Namespaces;

/** Search Web Service. It searches datasets by using three filtering properties: 
    (1) datasets, (2) types and (3) attributes, (4) attribute/value

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class Search extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** List of attributes to filter */
  private $attributes = "";

  /** List of types to filter */
  private $types = "";

  /** List of datasets to search */
  private $datasets = "";

  /** Number of items to return per page */
  private $items = "";

  /** Page number to return */
  private $page = "";

  /** Enabling the inference engine */
  private $inference = "";

  /** IP of the requester */
  private $requester_ip = "";

  /** Requested IP */
  private $registered_ip = "";
  
  /** Global query filtering parameter */
  private $query = "";
  
  private $attributesBooleanOperator = "and";
  
  private $includeAttributesList = array();

  /** Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr");

  
  /** The distance filter is a series of parameter that are used to
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
  
  /** The range filter is a series of parameter that are used to
     filter records of the dataset according to a rectangle bounds
     they are located in given their lat;long position. The values are
     seperated by a semi-column ";". The format is as follow:
     "top-left-lat;top-left-long;bottom-right-lat;bottom-right-long;".
  */
  private $rangeFilter;
  
  /**
  * Specify a lat/long location where all the results should be aggregated around.
  * For example, if we have a set of results compromised within a region.
  * If we don't want the results spread everywhere in that region, we have to specify
  * a location for this parameter such that all results get aggregated around
  * that specific location within the region.
  * 
  * The value should be: "latitude,longitude"
  * By example: "49.92545999127249,-97.14934608459475"
  * 
  * @var mixed
  */
  private $resultsLocationAggregator;

  /** Include aggregates to the resultset */
  public $includeAggregates = array();
  
  /** Attributes URI for which we want the aggregations of their values */
  public $aggregateAttributes = array();
  
  /** Specifies the type of the aggregated values for the list of aggregate attributes requested
             for this query. This value can be: (1) "literal" or, (2) "uri" */
  public $aggregateAttributesObjectType = "literal";
  
  /** Number of aggregated values to return for each attribute of the list of aggregated attributes requested
             for this query. If this value is "-1", then it means all the possible values. */
  public $aggregateAttributesNb = 10;

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", 
           "application/iron+json", "application/iron+csv", "text/xml", "text/*", "*/*");

  /** Error messages of this web service */
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
                        },
                        "_302": {
                          "id": "WS-SEARCH-302",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_303": {
                          "id": "WS-SEARCH-303",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_304": {
                          "id": "WS-SEARCH-304",
                          "level": "Fatal",
                          "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
                          "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
                        },
                        "_305": {
                          "id": "WS-SEARCH-305",
                          "level": "Fatal",
                          "name": "Invalid query date(s)",
                          "description": "The dates range of one of your date range attribute/value filter is invalid. Please make sure you entered to valid date-ranges."
                        },
                        "_306": {
                          "id": "WS-SEARCH-306",
                          "level": "Fatal",
                          "name": "Invalid number in the numbers range filter",
                          "description": "Numbers are expected in the numbers range filter you defined for this query"
                        }
                      }';

  /**
  * Implementation of the __get() magic method. We do implement it to create getter functions
  * for all the protected and private variables of this class, and to all protected variables
  * of the parent class.
  * 
  * This implementation is needed by the interfaces layer since we want the SourceInterface
  * class to access the variables of the web service class for which it is used as a 
  * source interface.
  * 
  * This means that all the privated and propected variables of these web service objects
  * are available to users; but they won't be able to set values for them.
  * 
  * Also note that This method is about 4 times slower than having the varaible as public instead 
  * of protected and private. However, these variables are only accessed about 10 to 200 times 
  * per script call. This means that for accessing these undefined variable using the __get magic 
  * method call, then it adds about 0.00022 seconds to the call or, about 0.22 milli-second 
  * (one fifth of a millisecond) For the gain of keeping the variables protected and private, 
  * we can spend this one fifth of a milli-second. This is a good compromize.  
  * 
  * @param mixed $name Name of the variable that is currently not defined for this object
  */
  public function __get($name)
  {
    // Check if the variable exists (so, if it is private or protected). If it is, then
    // we return the value. Otherwise a fatal error will be returned by PHP.
    if(isset($this->{$name}))
    {
      return($this->{$name});
    }
  }                      
                      

  /** Constructor
      
      @param $query Global query filtering parameter  
      @param $types List of filtering types URIs separated by ";"
      @param $attributes List of filtering attributes (property) of (encoded) URIs separated 
                         by ";". Additionally, the URI can end with a (un-encoded) double-colon "::". 
                         What follows this double colons is a possible value restriction to be applied 
                         as a filter to this attribute to perform attribute/value filtered searches. 
                         The query syntax can be used for that filtering value. The value also has 
                         to be encoded. An example of this "attribute" parameter is: 
                         "http%3A%2F%2Fsome-attribute-uri::some%2Bfiltering%2Bvalue". There is a 
                         special markup used with the prefLabel attribute when the attribute/value 
                         filtering is used in this parameter. It is the double stars "**" that 
                         introduces an auto-completion behavior on the prefLabel core attribute. 
                         It should be used like: "attributes=prefLabel::te**"; this will tells the 
                         search endpoint that the requester is performing an auto-completion task. 
                         That way, the endpoint will ensure that the autocompletion task can be 
                         performed for more than one word, including spaces. If the target attribute 
                         is defined in the ontology with the xsd:dateTime datatype in its range, 
                         then date queries can be used in this filter. If a single date is specified, 
                         such as 2001-05-24, then all the records from that date until now will be 
                         returned by the query. If a range of date is specified such as [1999 to 2010], 
                         then all the records between these two dates will be returned. A range of 
                         dates has to be between double brackets. Also, the seperator of the two 
                         dates has to be " to " (space, the word "to" and another space). The format 
                         of a date description is about any English textual datetime description. If 
                         the target attribute is defined in the ontology with the xsd:int or the 
                         xsd:float datatype in its range, then numeric queries can be used in 
                         this filter. If a single number is specified, such as 235, then all the 
                         records with that attribute/value will be returned. If a range of numbers 
                         is specified such as [235 to 900], then all the records between these two 
                         numbers will be returned. A range of numbers has to be between double brackets. 
                         Also, the seperator of the two dates has to be " to " (space, the word "to" 
                         and another space). When a range is defined for an attribute/value filter, 
                         the star character (*) can be used to denote "any" (so, any number, any date, 
                         etc) like [235 to *].  
      @param $datasets List of filtering datasets URIs separated by ";"
      @param $items Number of items returned by resultset
      @param $page Starting item number of the returned resultset
      @param $inference Enabling inference on types
      @param $include_aggregates Including aggregates with returned resultsets
      @param $registered_ip Target IP address registered in the WSF
      @param $requester_ip IP address of the requester
      @param $distanceFilter The distance filter is a series of parameter that are used to
                                 filter records of the dataset according to the distance they
                                 are located from a given lat;long point. The values are
                                 seperated by a semi-column ";". The format is as follow:
                                 "lat;long;distance;distanceType". The distanceType can
                                 have two values "0" or "1": "0" means that the distance
                                 specified is in kilometers and "1" means that the distance
                                 specified is in miles. An example is:
                                 "-98.45;10.4324;5;0", which means getting all the results that
                                 are at maximum 5 kilometers from the lat/long position.
      @param $rangeFilter The range filter is a series of parameter that are used to
                              filter records of the dataset according to a rectangle bounds
                              they are located in given their lat;long position. The values are
                              seperated by a semi-colon ";". The format is as follow:
                              "top-left-lat;top-left-long;bottom-right-lat;bottom-right-long". 
      @param $aggregate_attributes Specify a set of attributes URI for which we want their aggregated
                                       values. The URIs should be url-encoded. Each attribute for which we
                                       want the aggregated values should be seperated by a semi-colon ";".
      @param $includeAttributesList A list of attribute URIs to include into the resultset. Sometime, you may 
                                        be dealing with datasets where the description of the entities are composed 
                                        of thousands of attributes/values. Since the Crud: Read web service endpoint 
                                        returns the complete entities descriptions in its resultsets, this parameter 
                                        enables you to restrict the attribute/values you want included in the 
                                        resultset which considerably reduce the size of the resultset to transmit 
                                        and manipulate. Multiple attribute URIs can be added to this parameter by 
                                        splitting them with ";".
      @param $attributesBooleanOperator Tells the endpoint what boolean operator to use ("or" or "and") when doing 
                                        attribute/value filtering. One of:

                                          + "or": Use the OR boolean operator between all attribute/value filters. 
                                                  This means that if the user filter with 3 attributes, then the 
                                                  returned records will be described using one of these three.
                                          + "and": Use the AND boolean operator between all attribute/value filters. 
                                                   this means that if the user filter with 3 attributes, then the 
                                                   returned records will be described using all the three. This 
                                                   parameter affects all the attribute/value filters. 
      @param $aggregate_attributes_object_type Determines what kind of object value you are want the search endpoint 
                                               to return as aggregate values for the list of attributes for which 
                                               you want their possible values. This list of attributes is determined 
                                               by the aggregate_attributes parameter.

                                               + "literal": The aggregated value returned by the endpoint is a literal. 
                                                            If the value is a URI (a reference to some record), then 
                                                            the literal value will be the preferred label of that 
                                                            referred record.
                                               + "uri": If the value of the attribute(s) is a URI (a reference to some 
                                                        record) then that URI will be returned as the aggregated value.  
      @param $aggregate_attributes_nb Determines the number of value to aggregate for each aggregated_attributes for 
                                      this query. If the value is -1, then it means that all possible values for the 
                                      target aggregated_attributes have to be returned.  
                                      
      @param $resultsLocationAggregator Specify a lat/long location where all the results should be aggregated around.
                                        For example, if we have a set of results compromised within a region.
                                        If we don't want the results spread everywhere in that region, we have to specify
                                        a location for this parameter such that all results get aggregated around
                                        that specific location within the region. The value should be: 
                                        "latitude,longitude". By example: "49.92545999127249,-97.14934608459475"
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.

      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($query, $types, $attributes, $datasets, $items, $page, $inference, $include_aggregates,
                       $registered_ip, $requester_ip, $distanceFilter = "", $rangeFilter = "", 
                       $aggregate_attributes = "", $attributesBooleanOperator = "and",
                       $includeAttributesList = "", $aggregate_attributes_object_type = "literal",
                       $aggregate_attributes_nb = 10, $resultsLocationAggregator = "",
                       $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();
 
    $this->version = "1.0";
 
    $this->query = $query;
    
    $this->attributes = $attributes;
    $this->items = $items;
    $this->page = $page;
    $this->inference = $inference;
    $this->includeAggregates = $include_aggregates;
    $this->attributesBooleanOperator = strtoupper($attributesBooleanOperator);
    $this->aggregateAttributesObjectType = $aggregate_attributes_object_type;
    $this->aggregateAttributesNb = $aggregate_attributes_nb;
    $this->resultsLocationAggregator = explode(",", $resultsLocationAggregator);
    
    if(strtolower($interface) == "default")
    {
      $this->interface = "DefaultSourceInterface";
    }
    else
    {
      $this->interface = $interface;
    }
    
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;
    
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

  /** Validate a query to this web service

      @return TRUE if valid; FALSE otherwise
    
      @note This function is not used by the authentication validator web service
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery() 
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

  /** Returns the error structure

      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getError() { return ($this->conneg->error); }


  /**  @brief Create a resultset in a pipelined mode based on the processed information by the Web service.

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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Search DTD 0.1//EN\" \"" . $this->dtdBaseURL
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

  /**   Send the search query to the system supporting this web service (usually Solr) 
             and aggregate searched information

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/search/interfaces/");
    
    if($class != "")
    {    
      $class = 'StructuredDynamics\structwsf\ws\search\interfaces\\'.$class;
      
      $interface = new $class($this);
      
      // Validate versions
      if($this->requestedInterfaceVersion == "")
      {
        // The default requested version is the last version of the interface
        $this->requestedInterfaceVersion = $interface->getVersion();
      }
      else
      {
        if(!$interface->validateWebServiceCompatibility())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_304->name);
          $this->conneg->setError($this->errorMessenger->_304->id, $this->errorMessenger->ws,
            $this->errorMessenger->_304->name, $this->errorMessenger->_304->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_304->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
          $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
            $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_303->level);  
            
            return;
        }
      }
      
      // Process the code defined in the source interface
      $interface->processInterface();
    }
    else
    { 
      // Interface not existing
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
      $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
        $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_302->level);
    }
  }
}


//@}

?>
