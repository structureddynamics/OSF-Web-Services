<?php

/*! @defgroup WsConverterIrJSON Converter irJSON Web Service */
//@{

/*! @file \ws\converter\irjson\ConverterIrJSON.php
   @brief Define the irJSON converter class
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.
   
   \n\n\n
 */


/*!   @brief Convert irJSON data into RDF.
     @details   This class takes irJSON files as input, convert them into RDF using linkage schemas, 
            and output RDF in different formats.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
class ConverterIrJSON extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Text being converted */
  private $text;

  /*! @brief Mime type of the document */
  private $docmime;

  /*! @brief Type of the resource being converted */
  private $type;

  /*! @brief Error message to report */
  private $errorMessages = "";

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";

  /*! @brief Parser */
  private $parser;

  /*! @brief Include Dataset Description in the output */
  private $include_dataset_description;

  /*! @brief Defined dummany namespaces/prefixes used for data conversion for some serializations */
  private $namespaces = array();

  /*! @brief   Custom linkage schema used to include within the dataset's description when no linkage schemas exists
   *         for some types and attributes.
   */
  private $customLinkageSchema;

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/iron+json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/tsv",
      "text/csv", "text/xml", "text/*", "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/converter/irjson/",
                        "_200": {
                          "id": "WS-CONVERTER-IRJSON-200",
                          "level": "Warning",
                          "name": "No linkage file specified",
                          "description": "No linkage file of type \'RDF\' has been defined for this Instance Record Vocabulary file."
                        },
                        "_201": {
                          "id": "WS-CONVERTER-IRJSON-201",
                          "level": "Warning",
                          "name": "No data to convert",
                          "description": "No data is available for conversion"
                        },
                        "_300": {
                          "id": "WS-CONVERTER-IRJSON-300",
                          "level": "Warning",
                          "name": "JSON parsing error(s)",
                          "description": "JSON parsing error(s)"
                        },
                        "_301": {
                          "id": "WS-CONVERTER-IRJSON-301",
                          "level": "Warning",
                          "name": "irJSON validation error(s)",
                          "description": "irJSON validation error(s)"
                        },  
                        "_302": {
                          "id": "WS-CONVERTER-IRJSON-302",
                          "level": "Warning",
                          "name": "Unsupported Document Mime",
                          "description": "The MIME type of the document you sent to this irJSON conversion web service is not supported."
                        }
                      }';


  /*! @brief Constructor
      @details   Initialize the irJSON Converter Web Service
              
      \n
      
      @param[in] $document Text of a irJSON document
      @param[in] $docmime Mime type of the document
      @param[in] $include_dataset_description Specifies if you want to include the description of the dataset in the
                                              resultset output
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($document = "", $docmime = "application/iron+json", $include_dataset_description="false", $registered_ip,
    $requester_ip)
  {
    parent::__construct();

    $this->text = $document;
    $this->docmime = $docmime;
    $this->include_dataset_description = $include_dataset_description;

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

    $this->irJSONResources = array();

    $this->uri = $this->wsf_base_url . "/wsf/ws/converter/irjson/";
    $this->title = "irJSON Converter Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/converter/irjson/";

    $this->dtdURL = "converter/irjson/irjson.dtd";

    $this->customLinkageSchema = new LinkageSchema();
    $this->customLinkageSchema->setLinkedType("application/rdf+xml");

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct()
  {
    parent::__destruct();

    if(isset($this->db))
    {
      $this->db->close();
    }
  }

  /*!   @brief Validate a query to this web service
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery() { return; }

  protected function splitUri($str, &$base, &$ext)
  {
    $pos = FALSE;

    $base = "";
    $ext = "";

    if(($pos = strrpos($str, "#")) === FALSE)
    {
      $pos = strrpos($str, "/");
    }

    if($pos !== FALSE)
    {
      $base = substr($str, 0, $pos);
      $ext = substr($str, $pos + 1, strlen($str) - $pos - 1);
    }
    else
    {
      $base = "";
      $ext = $str;
    }
  }

  /*!   @brief Returns the error structure
              
      \n
      
      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getError() { return ($this->conneg->error); }

  private function getLinkedProperty($property, &$linkageSchema)
  {
    $pro = "";

    if(isset($linkageSchema->propertyX[$property][0]["mapTo"]))
    {
      $prop = $linkageSchema->propertyX[$property][0]["mapTo"];
    }
    else
    {
      // If the property is not linked, we create one in the temporary ontology of the node.
      /*! @todo Add this new property in the internal ontology of the node. */

      if(strpos($property, "http://") === FALSE)
      {
        $prop = $this->wsf_graph . "ontology/properties/" . $property;
      }
      else
      {
        $prop = $property;
      }
    }

    return ($prop);
  }


  /*!   @brief Generate the converted irJSON items using the internal XML representation
              
      \n
      
      @return a XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResultset()
  {
    if($this->docmime == "text/xml")
    {
      return ($this->text);
    }

    if($this->docmime == "application/iron+json")
    {
      // Check if a linkage file of kind RDF has been defined for this irJSON file.
      foreach($this->parser->linkageSchemas as $linkageSchema)
      {
        if(strtolower($linkageSchema->linkedType) == "application/rdf+xml")
        {
          $xml = new ProcessorXML();

          $resultset = $xml->createResultset();

          // Creation of the prefixes elements.
          $void = $xml->createPrefix("owl", "http://www.w3.org/2002/07/owl#");
          $resultset->appendChild($void);
          $rdf = $xml->createPrefix("rdf", "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
          $resultset->appendChild($rdf);
          $dcterms = $xml->createPrefix("rdfs", "http://www.w3.org/2000/01/rdf-schema#");
          $resultset->appendChild($dcterms);
          $dcterms = $xml->createPrefix("iron", "http://purl.org/ontology/iron#");
          $resultset->appendChild($dcterms);

          //
          // Map dataset
          //

          $datasetSubject = $xml->createSubject("http://rdfs.org/ns/void#Dataset", $this->parser->dataset->id[0]);

          // Map other attributes
          if(isset($this->parser->dataset->attributes))
          {
            foreach($this->parser->dataset->attributes as $property => $obj)
            {
              if(stripos($this->parser->dataset->attributes[$property]["valueType"], "primitive:string") !== FALSE)
              {
                foreach($this->parser->dataset->attributes[$property] as $key => $value)
                {
                  if(gettype($key) == "integer")
                  {
                    // Get the linked property.
                    $prop = $this->getLinkedProperty($property, $linkageSchema);

                    $pred = $xml->createPredicate($prop);
                    $object = $xml->createObjectContent($value);

                    $pred->appendChild($object);
                    $datasetSubject->appendChild($pred);
                  }
                }
              }

              if(stripos($this->parser->dataset->attributes[$property]["valueType"], "type:object") !== FALSE)
              {
                foreach($this->parser->dataset->attributes[$property] as $key => $value)
                {
                  if(gettype($key) == "integer")
                  {
                    // Check for the reference
                    if(isset($this->parser->dataset->attributes[$property][$key]["ref"]))
                    {
                      // Reference to an external record
                      if(substr($this->parser->dataset->attributes[$property][$key]["ref"], 0, 2) == "@@")
                      {
                        $this->parser->dataset->attributes[$property][$key]["ref"] = substr(
                          $this->parser->dataset->attributes[$property][$key]["ref"],
                            2, strlen($this->parser->dataset->attributes[$property][$key]["ref"]) - 2);
                      }
                      elseif(substr($this->parser->dataset->attributes[$property][$key]["ref"], 0, 1) == "@")
                      {
                        $this->parser->dataset->attributes[$property][$key]["ref"] = $this->parser->dataset->id[0]
                          . substr($this->parser->dataset->attributes[$property][$key]["ref"], 1,
                            strlen($this->parser->dataset->attributes[$property][$key]["ref"]) - 1);
                      }
                    }
                    else
                    {
                      /*
                        If no reference has been specified, we have to create a BNode for it even if the
                        object (instance record) is not defined anywhere.
                        This object will then be used
                      */

                      $this->parser->dataset->attributes[$property][$key]["ref"] = $this->wsf_graph . "irs/"
                        . md5(microtime());
                    }

                    // Check if metaData has been added to this relationship
                    if(isset($this->parser->dataset->attributes[$property][$key]["metaData"]))
                    {
                      // Get the linked property.
                      $prop = $this->getLinkedProperty($property, $linkageSchema);

                      $pred = $xml->createPredicate($prop);
                      $object = $xml->createObject("", $this->parser->dataset->attributes[$property][$key]["ref"]);

                      foreach($this->parser->dataset->
                        attributes[$property][$key]["metaData"] as $metaKey => $metaObject)
                      {
                        foreach($metaObject as $metaAttribute => $metaValue)
                        {
                          // Reify all metaData attributes/values
                          $metaProp = $this->getLinkedProperty($metaAttribute, $linkageSchema);

                          $reify = $xml->createReificationStatement($metaProp, $metaValue);
                          $object->appendChild($reify);
                        }
                      }

                      $pred->appendChild($object);

                      $datasetSubject->appendChild($pred);
                    }
                    else
                    {
                      // No metaData exists for this relationship. We simply create the s-p-o triple
                      $prop = $this->getLinkedProperty($property, $linkageSchema);

                      $pred = $xml->createPredicate($prop);
                      $object = $xml->createObject("", $this->parser->dataset->attributes[$property][$key]["ref"]);
                      $pred->appendChild($object);

                      $datasetSubject->appendChild($pred);
                    }
                  }
                }
              }
            }
          }

          $resultset->appendChild($datasetSubject);

          //
          // Map instance records
          //

          foreach($this->parser->instanceRecords as $instanceRecord)
          {
            $uri = $this->parser->dataset->id[0] . $instanceRecord->id[0];

            $subject;

            // Map types
            if(isset($instanceRecord->attributes["type"]))
            {
              foreach($instanceRecord->attributes["type"] as $key => $type)
              {
                if(gettype($key) != "string")
                {
                  if(isset($linkageSchema->typeX[$type][0]["mapTo"]))
                  {
                    $type = $linkageSchema->typeX[$type][0]["mapTo"];
                  }
                  else
                  {
                    // If the type is not linked, we create one in the temporary ontology of the node.
                    /*! @todo Add this new type in the internal ontology of the node. */

                    if(strpos($type, "http://") === FALSE)
                    {
                      $type = $this->wsf_graph . "ontology/types/" . $type;
                    }
                  }
                }

                if($key == "0")
                {
                  $subject = $xml->createSubject($type, $uri);
                }
                elseif(gettype($key) != "string")
                {
                  $pred = $xml->createPredicate("http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
                  $object = $xml->createObject("", $type);

                  $pred->appendChild($object);
                  $subject->appendChild($pred);
                }
              }
            }
            else
            {
              $subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $uri);
            }

            // Map other attributes
            if(isset($instanceRecord->attributes))
            {
              foreach($instanceRecord->attributes as $property => $obj)
              {
                if(stripos($instanceRecord->attributes[$property]["valueType"], "primitive:string") !== FALSE)
                {
                  foreach($instanceRecord->attributes[$property] as $key => $value)
                  {
                    if(gettype($key) == "integer")
                    {
                      // Get the linked property.
                      $prop = $this->getLinkedProperty($property, $linkageSchema);

                      $pred = $xml->createPredicate($prop);
                      $object = $xml->createObjectContent($value);

                      $pred->appendChild($object);
                      $subject->appendChild($pred);
                    }
                  }
                }

                if(stripos($instanceRecord->attributes[$property]["valueType"], "type:object") !== FALSE)
                {
                  foreach($instanceRecord->attributes[$property] as $key => $value)
                  {
                    if(gettype($key) == "integer" && $property != "type")
                    {
                      // Check for the reference
                      if(isset($instanceRecord->attributes[$property][$key]["ref"]))
                      {
                        // Reference to an external record
                        if(substr($instanceRecord->attributes[$property][$key]["ref"], 0, 2) == "@@")
                        {
                          $instanceRecord->attributes[$property][$key]["ref"] = substr(
                            $instanceRecord->attributes[$property][$key]["ref"],
                              2, strlen($instanceRecord->attributes[$property][$key]["ref"]) - 2);
                        }
                        elseif(substr($instanceRecord->attributes[$property][$key]["ref"], 0, 1) == "@")
                        {
                          $instanceRecord->attributes[$property][$key]["ref"] = $this->parser->dataset->id[0]
                            . substr($instanceRecord->attributes[$property][$key]["ref"], 1,
                              strlen($instanceRecord->attributes[$property][$key]["ref"]) - 1);
                        }
                      }
                      else
                      {
                        /*
                          If no reference has been specified, we have to create a BNode for it even if the
                          object (instance record) is not defined anywhere.
                          This object will then be used
                        */

                        $instanceRecord->attributes[$property][$key]["ref"] = $this->wsf_graph . "irs/"
                          . md5(microtime());
                      }

                      // Check if metaData has been added to this relationship
                      if(isset($instanceRecord->attributes[$property][$key]["metaData"]))
                      {
                        // Get the linked property.
                        $prop = $this->getLinkedProperty($property, $linkageSchema);

                        $pred = $xml->createPredicate($prop);
                        $object = $xml->createObject("", $instanceRecord->attributes[$property][$key]["ref"]);

                        foreach($instanceRecord->attributes[$property][$key]["metaData"] as $metaKey => $metaObject)
                        {
                          foreach($metaObject as $metaAttribute => $metaValue)
                          {
                            // Reify all metaData attributes/values
                            $metaProp = $this->getLinkedProperty($metaAttribute, $linkageSchema);

                            $reify = $xml->createReificationStatement($metaProp, $metaValue);
                            $object->appendChild($reify);
                          }
                        }

                        $pred->appendChild($object);

                        $subject->appendChild($pred);
                      }
                      else
                      {
                        // No metaData exists for this relationship. We simply create the s-p-o triple
                        $prop = $this->getLinkedProperty($property, $linkageSchema);

                        $pred = $xml->createPredicate($prop);
                        $object = $xml->createObject("", $instanceRecord->attributes[$property][$key]["ref"]);
                        $pred->appendChild($object);

                        $subject->appendChild($pred);
                      }
                    }
                  }
                }
              }
            }

            $resultset->appendChild($subject);
          }

          return ($this->injectDoctype($xml->saveXML($resultset)));
        }
      }

      // No RDF linkage file exists for this irJSON file, then we throw an error
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description,
        "No linkage file of type 'RDF' has been defined for this Instance Record Vocabulary file. Cant convert this file in '"
        . $this->conneg->getMime() . "'", $this->errorMessenger->_200->level);
      return;
    }

    // Unsupported docmime type

    $this->conneg->setStatus(400);
    $this->conneg->setStatusMsg("Bad Request");
    $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
    $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
      $this->errorMessenger->_302->name, $this->errorMessenger->_302->description,
      "Mime type you requested: " . $this->docmime, $this->errorMessenger->_302->level);
  }

  /*!   @brief Get the domain of a URL
  
      \n
      
      @param[in] $url the full URL
             
      @return the domain name of the URL *with* the prefix "http://"

      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function get_domain($url)
  {
    if(strlen($url) > 8)
    {
      $pos = strpos($url, "/", 8);

      if($pos === FALSE)
      {
        return $url;
      }
      else
      {
        return substr($url, 0, $pos);
      }
    }
    else
    {
      return $url;
    }
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Converter irJSON DTD 0.1//EN\" \""
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
      ConverterIrJSON::$supportedSerializations);

    // No text to process? Throw an error.
    if($this->text == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
        $this->errorMessenger->_201->level);
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

  /*!   @brief Serialize the converted UCB Memorial Data content into different serialization formats
              
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
      case "application/iron+json":
        $irJSON = "{\n";

        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        $datasetJson = "    \"dataset\": {\n";
        $instanceRecordsJson = "    \"recordList\": [ \n";

        // The first thing we have to check is if a linkage schema is available for this dataset.
        // If it is not, then we simply use the RDF properties and values to populate the IRON+JSON file.

        $accesses = $xml->getSubjectsByType("http://rdfs.org/ns/void#Dataset");

        $ls = array();
        $linkageSchemas = array();

        foreach($accesses as $access)
        {
          // We check if there is a link to a linkage schema
          $predicates = $xml->getPredicatesByType($access, "http://purl.org/ontology/iron#linkage");

          if($predicates->length > 0)
          {
            foreach($predicates as $predicate)
            {
              $objects = $xml->getObjects($predicate);

              $linkageSchemaUrl = $xml->getContent($objects->item(0));

              if(substr($linkageSchemaUrl, 0, 7) == "http://")
              {
                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, $linkageSchemaUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

                $data = curl_exec($ch);
                $data = trim($data);

                if(!curl_errno($ch) && $data != "")
                {
                  $parsedContent = json_decode($data);

                  array_push($ls, $parsedContent);
                }

                curl_close($ch);
              }
            }
          }
          else
          {
          // If no link, we create a inline schema.
          }

        // We check if there is a link to a structure schema

        // If no link, we create an inline schema.
        }

        // Now populate the linkage schema object.
        foreach($ls as $linkageSchema)
        {
          $linkageSchema = $linkageSchema->linkage;

          $tempSchema = new LinkageSchema();

          // Set version
          $tempSchema->setVersion($linkageSchema->version);

          // Set linkedType
          $tempSchema->setLinkedType($linkageSchema->linkedType);

          // Set prefixes
          if(isset($linkageSchema->prefixList))
          {
            foreach($linkageSchema->prefixList as $prefix => $uri)
            {
              $tempSchema->setPrefix($prefix, $uri);
            }
          }

          // Set propertieslinkageSchemas
          if(isset($linkageSchema->attributeList))
          {
            foreach($linkageSchema->attributeList as $property => $values)
            {
              $tempSchema->setPropertyX($property, $values->mapTo, $error);
            }
          }

          // Set types
          if(isset($linkageSchema->typeList))
          {
            foreach($linkageSchema->typeList as $type => $values)
            {
              $adds = array();

              if(isset($values->add))
              {
                foreach($values->add as $key => $value)
                {
                  $adds[$key] = $value;
                }
              }

              $error = "";

              $tempSchema->setTypeX($type, $values->mapTo, $adds, $error);
            }
          }

          array_push($linkageSchemas, $tempSchema);
        }


        // Check if a linkage schema of the same linkageType exists. If it exists, we merge them together.

        // Merging rules:
        // (1) if a type already exists, the type of the first schema will be used
        // (2) if an attribute already exists, the attribute of the first schema will be used.

        $parsedContent = $parsedContent->linkage;

        $merged = FALSE;

        $mergedSchemas = array();

        foreach($linkageSchemas as $linkageSchema)
        {
          $merged = FALSE;

          // Check if this linkageType has already been merged
          foreach($mergedSchemas as $ms)
          {
            if($ms->linkageType == $linkageSchema->linkageType)
            {
              // Merge schemas

              // merge prefixes
              if(isset($linkageSchema->prefixes))
              {
                foreach($linkageSchema->prefixes as $prefix => $uri)
                {
                  if(!isset($ms->prefixes[$prefix]))
                  {
                    $ms->prefixes[$prefix] = $uri;
                  }
                }
              }

              // merge types
              if(isset($linkageSchema->typeX))
              {
                foreach($linkageSchema->typeX as $type => $typeObject)
                {
                  if(!isset($ms->typeX[$type]))
                  {
                    $ms->typeX[$type] = $typeObject;
                  }
                }
              }

              // merge attributes
              if(isset($linkageSchema->propertyX))
              {
                foreach($linkageSchema->propertyX as $attribute => $attributeObject)
                {
                  if(!isset($ms->propertyX[$attribute]))
                  {
                    $ms->propertyX[$attribute] = $attributeObject;
                  }
                }
              }

              $merged = TRUE;
              break;
            }
          }

          if(!$merged)
          {
            array_push($mergedSchemas, $linkageSchema);
          }
        }

        $linkageSchemas = $mergedSchemas;

        // Now lets create the IRON+JSON serialization for dataset description and instance records descriptions.

        // Get the base (dataset) ID
        $datasetID = "";

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject, FALSE);

          if($subjectType == "http://rdfs.org/ns/void#Dataset")
          {
            $datasetID = $subjectURI;
          }
        }

        $linkageSchemaLinks = array();
       
        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject, FALSE);

          if($subjectType == "http://rdfs.org/ns/void#Dataset")
          {
            $datasetJson .= "        \"id\": \"$datasetID\",\n";

            $predicates = $xml->getPredicates($subject);

            $processingPredicateNum = 1;
            $predicateType = "";

            foreach($predicates as $predicate)
            {
              $objects = $xml->getObjects($predicate);

              foreach($objects as $object)
              {
                $objectType = $xml->getType($object);

                if($predicateType != $xml->getType($predicate, FALSE))
                {
                  $predicateType = $xml->getType($predicate, FALSE);
                  $processingPredicateNum = 1;
                }
                else
                {
                  $processingPredicateNum++;
                }
                $objectContent = $xml->getContent($object);

                $predicateName = "";

                // Check for a linked property.
                foreach($linkageSchemas as $linkageSchema)
                {
                  if(strtolower($linkageSchema->linkedType) == "application/rdf+xml")
                  {
                    foreach($linkageSchema->propertyX as $property => $value)
                    {
                      if($value[0]["mapTo"] == $predicateType)
                      {
                        $predicateName = $property;
                      }
                    }
                  }
                }
               
                if($predicateName == "")
                {
                  /*! @todo: custom linkage file */

                  // If we still dont have a reference to a property name, we use the end of the RDF generated one.
                  $predicateName = str_replace($this->wsf_graph . "ontology/properties/", "", $predicateType);

                  $this->splitUri($predicateType, $base, $ext);
                  $this->customLinkageSchema->setPropertyX($ext, $predicateType, $error);

                  $predicateName = $ext;
                }

                if($predicateName != "")
                {
                  if(($this->nbPredicates($subject, $xml, $predicateType) > 1 && $processingPredicateNum == 1))
                  {
                    if($predicateName != "linkage")
                    {
                      $datasetJson .= "        \"$predicateName\": [";
                    }
                  }

                  if($objectType == "rdfs:Literal")
                  {
                    $objectValue = $xml->getContent($object);

                    if($objects->length == 1)
                    {
                      if($predicateName == "linkage")
                      {
//                          $datasetJson .= "        \"$predicateName\": [\"".$this->jsonEscape($objectValue)."\"],\n";
                        array_push($linkageSchemaLinks, $objectValue);
                      }
                      else
                      {
                        $datasetJson .= "        \"$predicateName\": \"" . $this->jsonEscape($objectValue) . "\",\n";
                      }
                    }
                    else
                    {
                      $datasetJson .= "        \"" . $this->jsonEscape($objectValue) . "\",";
                    }
                  }
                  else
                  {
                    $objectURI = $xml->getURI($object);

                    $datasetJson .= "        \"$predicateName\": {\n";

                    if(stripos($objectURI,
                      "/irs/") === FALSE) // Don't display dummy object references in the irJSON output.
                    {
                      $nbReplaced = 0;
                      $ref = str_replace($datasetID, "@", $objectURI, $nbReplaced);

                      if($nbReplaced > 0)
                      {
                        $datasetJson .= "            \"ref\": \"" . $this->jsonEscape($ref) . "\",\n";
                      }
                      else
                      {
                        $datasetJson .= "            \"ref\": \"" . $this->jsonEscape("@@" . $objectURI) . "\",\n";
                      }
                    }

                    $reifies = $xml->getReificationStatements($object);

                    foreach($reifies as $reify)
                    {
                      $v = $xml->getValue($reify);
                      $t = $xml->getType($reify, FALSE);

                      $predicateName = "";

                      // Check for a linked property
                      foreach($linkageSchemas as $linkageSchema)
                      {
                        if(strtolower($linkageSchema->linkedType) == "application/rdf+xml")
                        {
                          foreach($linkageSchema->propertyX as $property => $value)
                          {
                            if($value[0]["mapTo"] == $t)
                            {
                              $predicateName = $property;
                            }
                          }
                        }
                      }

                      if($predicateName == "")
                      {
                        /*! @todo: custom linkage file */

// If we still dont have a reference to a property name, we use the end of the RDF generated one.
                        $predicateName = str_replace($this->wsf_graph . "ontology/properties/", "", $t);

                        $this->splitUri($t, $base, $ext);
                        $this->customLinkageSchema->setPropertyX($ext, $t, $error);

                        $predicateName = $ext;
                      }

                      $datasetJson .= "            \"" . $predicateName . "\": \"" . $this->jsonEscape($v) . "\",\n";
                    }

                    $datasetJson = substr($datasetJson, 0, strlen($datasetJson) - 2) . "\n";

                    $datasetJson .= "        },\n";
                  }
                }
              }

              if($this->nbPredicates($subject, $xml, $predicateType) > 1
                && $processingPredicateNum == $this->nbPredicates($subject, $xml, $predicateType))
              {
                if($predicateName != "linkage")
                {
                  $datasetJson = substr($datasetJson, 0, strlen($datasetJson) - 2);
                  $datasetJson .= " ],\n";
                }
              }
            }

            $datasetJson = substr($datasetJson, 0, strlen($datasetJson) - 2) . "\n";
          }
          else
          {
            $instanceRecordsJson .= "        {\n";

            $instanceRecordsJson .= "            \"id\": \"" . str_replace($datasetID, "", $subjectURI) . "\",\n";

            // Get the type
            $typeName = $subjectType;
            $break = FALSE;

            foreach($linkageSchemas as $linkageSchema)
            {
              if(strtolower($linkageSchema->linkedType) == "application/rdf+xml")
              {
                foreach($linkageSchema->typeX as $type => $value)
                {
                  if($value[0]["mapTo"] == $subjectType)
                  {
                    $typeName = $type;
                    $break = TRUE;
                    break;
                  }
                }

                if($break)
                {
                  break;
                }
              }
            }

            // If there is no linked type for this type, we use the end of the automatically RDF type generated.
            if($break === FALSE)
            {
              /*! @todo: custom linkage file */
              //$typeName = str_replace($this->wsf_graph."ontology/types/", "", $typeName);

              $this->splitUri($typeName, $base, $ext);
              $this->customLinkageSchema->setTypeX($ext, $typeName, array(), $error);

              $typeName = $ext;
            }

            if($this->nbPredicates($subject, $xml, "http://www.w3.org/1999/02/22-rdf-syntax-ns#type") >= 1)
            {
              $instanceRecordsJson .= "            \"type\": [ \n";

              $predicates = $xml->getPredicates($subject);

              foreach($predicates as $predicate)
              {
                $pt = $xml->getType($predicate, FALSE);

                if($pt == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
                {
                  $objects = $xml->getObjects($predicate);

                  $objectValue = $xml->getURI($objects->item(0));

                  // Get the type
                  $break = FALSE;

                  foreach($linkageSchemas as $linkageSchema)
                  {
                    if(strtolower($linkageSchema->linkedType) == "application/rdf+xml")
                    {
                      foreach($linkageSchema->typeX as $type => $value)
                      {
                        if($value[0]["mapTo"] == $objectValue)
                        {
                          $objectValue = $type;
                          $break = TRUE;
                          break;
                        }
                      }

                      if($break)
                      {
                        break;
                      }
                    }
                  }

                  // If there is no linked type for this type, we use the end of the automatically RDF type generated.
                  if($break === FALSE)
                  {
                    /*! @todo: custom linkage file */
                    $objectValue = str_replace($this->wsf_graph . "ontology/types/", "", $objectValue);

                    $this->splitUri($objectValue, $base, $ext);
                    $this->customLinkageSchema->setTypeX($ext, $objectValue, array(), $error);

                    $objectValue = $ext;
                  }

                  $instanceRecordsJson .= "                \"" . $objectValue . "\", \n";
                }
              }

              $instanceRecordsJson .= "                \"" . $typeName . "\" ],\n";
            }
            else
            {
              $instanceRecordsJson .= "            \"type\": \"" . $typeName . "\",\n";
            }

            $predicates = $xml->getPredicates($subject);

            $processingPredicateNum = 1;
            $predicateType = "";

            foreach($predicates as $predicate)
            {
              $objects = $xml->getObjects($predicate);

              foreach($objects as $object)
              {
                $objectType = $xml->getType($object);

                if($predicateType != $xml->getType($predicate, FALSE))
                {
                  $predicateType = $xml->getType($predicate, FALSE);
                  $processingPredicateNum = 1;
                }
                else
                {
                  $processingPredicateNum++;
                }

                if($predicateType == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
                {
                  continue;
                }
                
                $objectContent = $xml->getContent($object);

                $predicateName = "";

                // Check for a linked property.
                foreach($linkageSchemas as $linkageSchema)
                {
                  if(strtolower($linkageSchema->linkedType) == "application/rdf+xml")
                  {
                    foreach($linkageSchema->propertyX as $property => $value)
                    {
                      if($value[0]["mapTo"] == $predicateType)
                      {
                        $predicateName = $property;
                      }
                    }
                  }
                }
                
                if($predicateName == "")
                {
                  /*! @todo: custom linkage file */

                  // If we still dont have a reference to a property name, we use the end of the RDF generated one.
                  $predicateName = str_replace($this->wsf_graph . "ontology/properties/", "", $predicateType);

                  $this->splitUri($predicateType, $base, $ext);
                  $this->customLinkageSchema->setPropertyX($ext, $predicateType, $error);

                  $predicateName = $ext;
                }

                if($this->nbPredicates($subject, $xml, $predicateType) > 1 && $processingPredicateNum == 1)
                {
                  $instanceRecordsJson .= "            \"$predicateName\": [\n";
                }

                if($objectType == "rdfs:Literal")
                {
                  $objectValue = $xml->getContent($object);

                  if($this->nbPredicates($subject, $xml, $predicateType) == 1)
                  {
                    $instanceRecordsJson .= "            \"$predicateName\": \"" . $this->jsonEscape($objectValue)
                      . "\",\n";
                  }
                  else
                  {
                    $instanceRecordsJson .= "            \"" . $this->jsonEscape($objectValue) . "\",\n";
                  }
                }
                else
                {
                  $objectURI = $xml->getURI($object);

                  if($this->nbPredicates($subject, $xml, $predicateType) > 1)
                  {
                    $instanceRecordsJson .= "            {\n";
                  }
                  else
                  {
                    $instanceRecordsJson .= "            \"$predicateName\": {\n";
                  }

                  if(stripos($objectURI,
                    "/irs/") === FALSE) // Don't display dummy object references in the irJSON output.
                  {
                    $nbReplaced = 0;
                    $ref = str_replace($datasetID, "@", $objectURI, $nbReplaced);

                    if($nbReplaced > 0)
                    {
                      $instanceRecordsJson .= "            \"ref\": \"" . $this->jsonEscape($ref) . "\",\n";
                    }
                    else
                    {
                      $instanceRecordsJson .= "            \"ref\": \"" . $this->jsonEscape("@@" . $objectURI)
                        . "\",\n";
                    }
                  }
                   
                  $reifies = $xml->getReificationStatements($object);

                  foreach($reifies as $reify)
                  {
                    $v = $xml->getValue($reify);
                    $t = $xml->getType($reify, FALSE);

                    $predicateName = "";

                    // Check for a linked property
                    foreach($linkageSchemas as $linkageSchema)
                    {
                      if(strtolower($linkageSchema->linkedType) == "application/rdf+xml")
                      {
                        foreach($linkageSchema->propertyX as $property => $value)
                        {
                          if($value[0]["mapTo"] == $t)
                          {
                            $predicateName = $property;
                          }
                        }
                      }
                    }

                    if($predicateName == "")
                    {
                      /*! @todo: custom linkage file */

                      // If we still dont have a reference to a property name, we use the end of the RDF generated one.
                      $predicateName = str_replace($this->wsf_graph . "ontology/properties/", "", $t);

                      $this->splitUri($t, $base, $ext);
                      $this->customLinkageSchema->setPropertyX($ext, $t, $error);

                      $predicateName = $ext;
                    }

                    $instanceRecordsJson .= "                \"" . $predicateName . "\": \"" . $this->jsonEscape($v)
                      . "\",\n";
                  }

                  $instanceRecordsJson = rtrim(rtrim($instanceRecordsJson, "\n"), ",") . "\n";

                  $instanceRecordsJson .= "            },\n";
                }
              }

              if($this->nbPredicates($subject, $xml, $predicateType) > 1
                && $processingPredicateNum == $this->nbPredicates($subject, $xml, $predicateType))
              {
                $instanceRecordsJson = substr($instanceRecordsJson, 0, strlen($instanceRecordsJson) - 2);
                $instanceRecordsJson .= "     ],\n";
              }
            }

            $instanceRecordsJson = substr($instanceRecordsJson, 0, strlen($instanceRecordsJson) - 2) . "\n";

            $instanceRecordsJson .= "        },\n";
          }
        }

        $instanceRecordsJson = substr($instanceRecordsJson, 0, strlen($instanceRecordsJson) - 2) . "\n";

        $instanceRecordsJson .= "        ]\n";

        $datasetJson .= "    },\n";

        $irJSON .= $datasetJson . $instanceRecordsJson;

        $irJSON .= "}";

        // Inject the custom linakge schema in the dataset description.

        if(($pos = stripos($irJSON, '"dataset"')) !== FALSE)
        {
          $irJSONLinkageSchema = "";
          
          if(count($this->customLinkageSchema->prefixes) > 0 || count($this->customLinkageSchema->predicateX) > 0
            || count($this->customLinkageSchema->typeX) > 0)
          {
            $irJSONLinkageSchema .= $this->customLinkageSchema->generateJsonSerialization() . ",";
          }

          foreach($linkageSchemaLinks as $lsl)
          {
            $irJSONLinkageSchema .= "\"$lsl\",";
          }
                    
          if($irJSONLinkageSchema != "")
          {
            $posStart = strpos($irJSON, "{", $pos);
            $endIrJsonFile = substr($irJSON, $posStart + 1, strlen($irJSON) - $posStart);

            $irJSON = substr($irJSON, 0, $posStart + 1) . "\n        \"linkage\": [\n";
            
            $irJSON .= $irJSONLinkageSchema;
            
            $irJSON = rtrim($irJSON, ",");
            
            $irJSON .= "],\n";

            $irJSON .= $endIrJsonFile;
          }
        }

/*
if(($pos = stripos($irJSON, '"linkage"')) !== FALSE)
{
  $posStart = strpos($irJSON, "[", $pos);
  $irJSON = substr($irJSON, 0, $posStart + 1).$this->customLinkageSchema->generateJsonSerialization().",\n".substr($irJSON, $posStart + 1, strlen($irJSON) - $posStart);
}
else
{
                  // no linakgeSchema property found. Lets create one.
                  if(($pos = stripos($irJSON, "dataset")) !== FALSE)
                  {
                    $posStart = strpos($irJSON, "{", $pos);
                    $irJSON = substr($irJSON, 0, $posStart + 1)."\n        \"linkage\": [\n".$this->customLinkageSchema->generateJsonSerialization()."        ],\n".substr($irJSON, $posStart + 1, strlen($irJSON) - $posStart);
                  }
                }
                */
        return ($this->jsonRemoveTrailingCommas($irJSON));
      break;

      case "text/tsv":
      case "text/csv":

        $tsv = "";
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject, FALSE);

          if($subjectType != "http://rdfs.org/ns/void#Dataset" || $this->include_dataset_description == "true")
          {
            $tsv .= str_replace($this->delimiter, urlencode($this->delimiter), $subjectURI) . $this->delimiter
              . "http://www.w3.org/1999/02/22-rdf-syntax-ns#type" . $this->delimiter
              . str_replace($this->delimiter, urlencode($this->delimiter), $subjectType) . "\n";

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
                  $tsv .= str_replace($this->delimiter, urlencode($this->delimiter), $subjectURI) . $this->delimiter
                    . str_replace($this->delimiter, urlencode($this->delimiter), $predicateType) . $this->delimiter
                    . str_replace($this->delimiter, urlencode($this->delimiter), $objectValue) . "\n";
                }
                else
                {
                  $objectURI = $xml->getURI($object);
                  $tsv .= str_replace($this->delimiter, urlencode($this->delimiter), $subjectURI) . $this->delimiter
                    . str_replace($this->delimiter, urlencode($this->delimiter), $predicateType) . $this->delimiter
                    . str_replace($this->delimiter, urlencode($this->delimiter), $objectURI) . "\n";
                }
              }
            }
          }
        }

        return ($tsv);
      break;

      case "application/rdf+n3":
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject, FALSE);

          if($subjectType != "http://rdfs.org/ns/void#Dataset" || $this->include_dataset_description == "true")
          {
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
        }

        return ($rdf_part);
      break;

      case "application/rdf+xml":
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        $this->namespaces =
          array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
            "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/iron#" => "iron");

        $nsId = 0;

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject, FALSE);

          if($subjectType != "http://rdfs.org/ns/void#Dataset" || $this->include_dataset_description == "true")
          {
            $ns = $this->getNamespace($subjectType);
            $stNs = $ns[0];
            $stExtension = $ns[1];

            if(!isset($this->namespaces[$stNs]))
            {
              // Make sure the ID is not already existing. Increase the counter if it is the case.
              while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
              {
                $nsId++;
              }              
              
              $this->namespaces[$stNs] = "ns" . $nsId;
              $nsId++;
            }

            $rdf_part .= "\n    <" . $this->namespaces[$stNs] . ":" . $stExtension . " rdf:about=\"".
                                                                                  $this->xmlEncode($subjectURI)."\">\n";

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

                  $ns = $this->getNamespace($predicateType);
                  $ptNs = $ns[0];
                  $ptExtension = $ns[1];

                  if(!isset($this->namespaces[$ptNs]))
                  {
                    // Make sure the ID is not already existing. Increase the counter if it is the case.
                    while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                    {
                      $nsId++;
                    }                    
                    
                    $this->namespaces[$ptNs] = "ns" . $nsId;
                    $nsId++;
                  }

                  $rdf_part .= "        <" . $this->namespaces[$ptNs] . ":" . $ptExtension . ">"
                    . $this->xmlEncode($objectValue) . "</" . $this->namespaces[$ptNs] . ":" . $ptExtension . ">\n";
                }
                else
                {
                  $objectURI = $xml->getURI($object);

                  $ns = $this->getNamespace($predicateType);
                  $ptNs = $ns[0];
                  $ptExtension = $ns[1];

                  if(!isset($this->namespaces[$ptNs]))
                  {
                    // Make sure the ID is not already existing. Increase the counter if it is the case.
                    while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                    {
                      $nsId++;
                    }
                                      
                    $this->namespaces[$ptNs] = "ns" . $nsId;
                    $nsId++;
                  }

                  $rdf_part .= "        <" . $this->namespaces[$ptNs] . ":" . $ptExtension
                    . " rdf:resource=\"".$this->xmlEncode($objectURI)."\" />\n";
                }
              }
            }

            $rdf_part .= "    </" . $this->namespaces[$stNs] . ":" . $stExtension . ">\n";
          }
        }

        return ($rdf_part);
      break;
    }
  }

  public function jsonRemoveTrailingCommas($json)
  {
    $json = preg_replace('/,\s*([\]}])/m', '$1', $json);
    return $json;
  }

  public function jsonEscape($str) { return str_replace(array ('\\', '"', "\n", "\r"), array ('\\\\', '\\"', "\\n", "\\r"), $str); }

  public function nbPredicates(&$subject, &$xml, $predicate)
  {
    $predicates = $xml->getPredicatesByType($subject, $predicate);

    return ($predicates->length);
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

              $first = 0;

              foreach($reifies as $reify)
              {
                if($first == 0)
                {
                  //                  $rdf_reification .= "_:bnode".$bnodeCounter." a rdf:Statement ;\n";
                  $rdf_reification .= "_:" . md5($xml->getURI($subject) . $predicateType . $xml->getURI($object))
                    . " a rdf:Statement ;\n";
                  $bnodeCounter++;
                  $rdf_reification .= "    rdf:subject <" . $xml->getURI($subject) . "> ;\n";
                  $rdf_reification .= "    rdf:predicate <" . $predicateType . "> ;\n";
                  $rdf_reification .= "    rdf:object <" . $xml->getURI($object) . "> ;\n";
                }

                $first++;

                $reifyingProperty = $xml->getType($reify, FALSE);

                $rdf_reification .= "    <$reifyingProperty> \"" . $xml->getValue($reify) . "\" ;\n";
              }

              if($first > 0)
              {
                $bnodeCounter++;
                $rdf_reification = substr($rdf_reification, 0, strlen($rdf_reification) - 2) . ".\n\n";
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

              $first = 0;

              foreach($reifies as $reify)
              {
                if($first == 0)
                {
                  //                  $rdf_reification .= "    <rdf:Statement>\n";
                  $rdf_reification .= "    <rdf:Statement rdf:about=\""
                    . $this->xmlEncode(md5($xml->getURI($subject) . $predicateType . $xml->getURI($object))) . "\">\n";
                  $rdf_reification .= "        <rdf:subject rdf:resource=\"" . $this->xmlEncode($xml->getURI($subject)) 
                                                                                                            . "\" />\n";
                  $rdf_reification .= "        <rdf:predicate rdf:resource=\"" . $this->xmlEncode($predicateType) . 
                                                                                                              "\" />\n";
                  $rdf_reification .= "        <rdf:object rdf:resource=\"" . $this->xmlEncode($xml->getURI($object)) . 
                                                                                                              "\" />\n";
                }

                $first++;

                $nsId = count($this->namespaces);

                $reifyingProperty = $xml->getType($reify, FALSE);

                $ns = $this->getNamespace($reifyingProperty);

                $ptNs = $ns[0];
                $ptExtension = $ns[1];

                if(!isset($this->namespaces[$ptNs]))
                {
                  // Make sure the ID is not already existing. Increase the counter if it is the case.
                  while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                  {
                    $nsId++;
                  }
                                    
                  $this->namespaces[$ptNs] = "ns" . $nsId;
                }

                $rdf_reification .= "        <" . $this->namespaces[$ptNs] . ":" . $ptExtension . ">"
                  . $xml->getValue($reify) . "</" . $this->namespaces[$ptNs] . ":" . $ptExtension . ">\n";
              }

              if($first > 0)
              {
                $rdf_reification .= "    </rdf:Statement>  \n\n";
              }
            }
          }
        }

        return ($rdf_reification);

      break;
    }
  }

  /*!   @brief Serialize the converted UCB Memorial Data content into different serialization formats
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    // Check for parsing errors
    if($this->conneg->getStatus() != 200)
    {
      return;
    }
    else
    {
      switch($this->conneg->getMime())
      {
        case "text/tsv":
        case "text/csv":
          return $this->pipeline_serialize();
        break;

        case "application/rdf+n3":
          $rdf_document = "";
          $rdf_document .= "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
          $rdf_document .= "@prefix iron: <http://purl.org/ontology/iron#> .\n";

          $rdf_document .= $this->pipeline_serialize();

          $rdf_document .= $this->pipeline_serialize_reification();

          return $rdf_document;
        break;

        case "application/rdf+xml":
          $rdf_document = "";
          $rdf_header = "<?xml version=\"1.0\"?>\n";

          $rdf_document .= $this->pipeline_serialize();

          $rdf_document .= $this->pipeline_serialize_reification();

          $rdf_header .= "<rdf:RDF ";

          foreach($this->namespaces as $ns => $prefix)
          {
            $rdf_header .= " xmlns:$prefix=\"$ns\"";
          }

          $rdf_header .= ">\n\n";

          $rdf_document .= "</rdf:RDF>";

          return $rdf_header . $rdf_document;
        break;

        case "text/xml":
          return $this->pipeline_getResultset();
        break;

        case "application/iron+json":
          return ($this->pipeline_serialize());
        break;
      }
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
    $pos = strpos($uri, "#");

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
    }

    return (FALSE);
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

/*!   @brief Parse the TSV file for declaraton error (properties or classes used in the file that are not defined on the node)
            
    \n
    
    @return returns TRUE if there is errors; FALSE otherwise
  
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
  private function irJSONParsingError() { return FALSE; }

  /*!   @brief Convert the target document
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    if($this->conneg->getStatus() == 200)
    {
      switch($this->docmime)
      {
        case "application/iron+json":
          $this->parser = new irJSONParser($this->text);

          //var_dump($this->parser);die;

          if(count($this->parser->jsonErrors) > 0)
          {
            $errorMsg = "";

            foreach($this->parser->jsonErrors as $key => $error)
            {
              $errorMsg .= "\n(" . ($key + 1) . ") $error \n";
            }

            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
            $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
              $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, $errorMsg,
              $this->errorMessenger->_300->level);
          }
          elseif(count($this->parser->irjsonErrors) > 0)
          {
            $errorMsg = "";

            foreach($this->parser->irjsonErrors as $key => $error)
            {
              $errorMsg .= "\n(" . ($key + 1) . ") $error \n";
            }

            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
            $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
              $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, $errorMsg,
              $this->errorMessenger->_301->level);
          }
        break;

        case "text/xml": break;
      }
    }
  }
}

//@}

?>