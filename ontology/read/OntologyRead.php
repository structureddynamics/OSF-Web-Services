<?php

/*! @defgroup WsOntologyRead Ontology Read Web Service */
//@{

/*! @file \ws\ontology\read\OntologyRead.php
   @brief Define the Ontology Read web service
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Ontology Read web service. It reads different kind of information from the ontological structure
             of the system.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class OntologyRead extends WebService
{
  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief URI of the ontology to query */
  private $ontologyUri = "";

  /*! @brief The function to use for this query. Different API function calls are embeded in this web service endpoint */
  private $function = "";
  
  /*! @brief The parameters of the function to call. These parameters changes depending on the function. All
             parameters are split with a ";" and are encoded */
  private $parameters = array();

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";
  
  private $OwlApiSession = null;


  /*! @brief Namespaces/Prefixes binding */
  private $namespaces =
    array ("http://www.w3.org/2002/07/owl#" => "owl", "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
      "http://www.w3.org/2000/01/rdf-schema#" => "rdfs", "http://purl.org/ontology/wsf#" => "wsf", 
      "http://purl.org/ontology/aggregate#" => "aggr");

  /*! @brief Array of triples where the current resource(s) is a subject. */
  public $subjectTriples = array();

  private $getSerialized = "";

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/bib+json", "application/iron+json", "application/json", "application/rdf+xml",
      "application/rdf+n3", "application/*", "text/csv", "text/xml", "text/*", "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/ontology/read/",
                        "_200": {
                          "id": "WS-ONTOLOGY-READ-200",
                          "level": "Warning",
                          "name": "Unknown function call",
                          "description": "The function call being requested is unknown or unsupported by this Ontology Read web service endpoint"
                        },
                        "_201": {
                          "id": "WS-ONTOLOGY-READ-201",
                          "level": "Warning",
                          "name": "Unsupported mode",
                          "description": "Unsupported mode used for this query"
                        },
                        "_202": {
                          "id": "WS-ONTOLOGY-READ-202",
                          "level": "Warning",
                          "name": "URI parameter not provided",
                          "description": "You omited to provide the URI parameter for this query."
                        },
                        "_203": {
                          "id": "WS-ONTOLOGY-READ-203",
                          "level": "Warning",
                          "name": "Unsupported type",
                          "description": "Unsupported type used for this query"
                        },                        
                        "_300": {
                          "id": "WS-CRUD-READ-300",
                          "level": "Warning",
                          "name": "Target ontology not loaded",
                          "description": "The target ontology is not loaded into the ontological structure of this instance."
                        }
                      }';


  /*! @brief Constructor
      @details   Initialize the Auth Web Service
              
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
              
              
      \n
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($ontologyUri, $function, $parameters, $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->ontologyUri = $ontologyUri;
    $this->function = $function;
    
    $params = explode(";", $parameters);
    
    foreach($params as $param)
    {
      $p = explode("=", $param);
      
      $this->parameters[$p[0]] = urldecode($p[1]);
    }

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

    $this->uri = $this->wsf_base_url . "/wsf/ws/ontology/read/";
    $this->title = "Ontology Read Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/ontology/read/";

    $this->dtdURL = "ontology/ontologyRead.dtd";

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
    // @TODO Validate the OntologyRead queries such that: (1) if the user is requesting something related to a 
    //       specific ontology, we check if it has the rights. If it is requesting a list of available ontologies
    //       we list the ones he has access to. That second validation has to happen in these special functions.
    
    if($this->ontologyUri != "")
    {
      $ws_av = new AuthValidator($this->requester_ip, $this->ontologyUri, $this->uri);

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
        $ws_av = new AuthValidator($this->registered_ip, $this->ontologyUri, $this->uri);

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

    foreach($this->subjectTriples as $u => $sts)
    {
      if(isset($sts[Namespaces::$rdf."type"]))
      {
        foreach($sts[Namespaces::$rdf."type"] as $key => $type)
        {     
          if($key > 0)
          {
            $pred = $xml->createPredicate(Namespaces::$rdf."type");
            $object = $xml->createObject("", $type["value"]);
            $pred->appendChild($object);
            $subject->appendChild($pred);
          }
          else
          {
            $subject = $xml->createSubject($type["value"], $u);
          }
        }
      }
      else
      {
        $subject = $xml->createSubject("owl:Thing", $u);
      }

      foreach($sts as $property => $values)
      {
        if($property != Namespaces::$rdf."type")
        {
          foreach($values as $value)
          {
            if($value["datatype"] != "rdf:Resource")
            {
              /*
                @TODO The internal XML structure of structWSF should be enhanced with datatypes such as xsd:double, int, 
                      literal, etc.
              */
              
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
              
              $pred = $xml->createPredicate($this->namespaces[$ns[0]] . ":" . $ns[1]);
              $object = $xml->createObjectContent($value["value"]);
              $pred->appendChild($object);

              if(isset($value["rei"]))
              {
                foreach($value["rei"] as $rStatement)
                {
                  $reify = $xml->createReificationStatement($rStatement["type"], $rStatement["value"]);
                  $object->appendChild($reify);
                }
              }              
              
              $subject->appendChild($pred);
            }
            else
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
              
              $pred = $xml->createPredicate($this->namespaces[$ns[0]] . ":" . $ns[1]);
              $object = $xml->createObject("", $value["value"]);
              $pred->appendChild($object);

              if(isset($value["rei"]))
              {
                foreach($value["rei"] as $rStatement)
                {
                  $reify = $xml->createReificationStatement($rStatement["type"], $rStatement["value"]);
                  $object->appendChild($reify);
                }
              }                
              
              $subject->appendChild($pred);
            }
          }
        }
      }
      
      // Creation of the prefixes elements.
      foreach($this->namespaces as $uri => $prefix)
      {
        $ns = $xml->createPrefix($prefix, $uri);
        $resultset->appendChild($ns);
      }      

      $resultset->appendChild($subject);
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Ontology Read DTD 0.1//EN\" \"" . $this->dtdBaseURL
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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, OntologyRead::$supportedSerializations);

    // Validate query
    $this->validateQuery();

    // If the query is still valid
    if($this->conneg->getStatus() == 200)
    {
      // Check for errors

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
    if($this->getSerialized != "")
    {
      return($this->getSerialized);
    }    
    
    $rdf_part = "";

    switch($this->conneg->getMime())
    {
      case "text/csv":

        $csv = "";
      
        foreach($this->subjectTriples as $u => $sts)
        {
          if(isset($sts[Namespaces::$rdf."type"]))
          {
            foreach($sts[Namespaces::$rdf."type"] as $key => $type)
            {     
              $csv .= Namespaces::getPrefixedUri($u).",rdf:type,".Namespaces::getPrefixedUri($type["value"])."\n";     
            }
          }
          else
          {
            $csv .= Namespaces::getPrefixedUri($u).",rdf:type,"."owl:Thing\n";     
          }

          foreach($sts as $property => $values)
          {
            if($property != Namespaces::$rdf."type")
            {
              foreach($values as $value)
              {
                if($value["datatype"] == "rdf:Resource")
                {
                  $csv .= Namespaces::getPrefixedUri($u).",".
                          Namespaces::getPrefixedUri($property).",".
                          Namespaces::getPrefixedUri($value["value"])."\n";     
                }
              }
            }
          }
        }
        
        return($csv);        
      break;
      
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
                    $json_part .= "                 \"reify\": [\n";
                  }

                  $json_part .= "                   { \n";
                  $json_part .= "                       \"type\": \"wsf:objectLabel\", \n";
                  $json_part .= "                       \"value\": \"" . parent::jsonEncode($xml->getValue($reify))
                    . "\" \n";
                  $json_part .= "                   },\n";
                }

                if($nbReification > 0)
                {
                  $json_part = substr($json_part, 0, strlen($json_part) - 2) . "\n";

                  $json_part .= "                 ]\n";
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

        $json_header = "  \"prefixes\": \n";
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
  {}

  /*!   @brief Serialize the web service answer.
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    if($this->getSerialized != "")
    {
      return($this->getSerialized);
    }
    
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

      case "text/csv":
        $csv_document .= $this->pipeline_serialize();   
        
        return $csv_document;
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
  
  private function returnError($statusCode, $statusMsg, $wsErrorCode, $debugInfo = "")
  {
    $this->conneg->setStatus($statusCode);
    $this->conneg->setStatusMsg($statusMsg);
    $this->conneg->setStatusMsgExt($this->errorMessenger->{$wsErrorCode}->name);
    $this->conneg->setError($this->errorMessenger->{$wsErrorCode}->id, $this->errorMessenger->ws,
      $this->errorMessenger->{$wsErrorCode}->name, $this->errorMessenger->{$wsErrorCode}->description, $debugInfo,
      $this->errorMessenger->{$wsErrorCode}->level);
  }

  /*!   @brief Perform the requested action and return the results.
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
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

      try
      {
        $ontology = new OWLOntology($this->ontologyUri, $this->OwlApiSession, TRUE);   
      }
      catch(Exception $e)
      {
        if(strtolower($this->function) != "getserializedclasshierarchy" &&
           strtolower($this->function) != "getserializedpropertyhierarchy" &&
           strtolower($this->function) != "getstructxmlschema" &&
           strtolower($this->function) != "getloadedontologies")
        {        
          $this->returnError(400, "Bad Request", "_300");
          return;
        }
      }

      switch(strtolower($this->function))
      {
        case "getserialized":
          $this->conneg->setStatus(200);
          $this->conneg->setStatusMsg("OK");        
          $this->getSerialized = $ontology->getSerialization();        
          return;
        break;
        
        case "getclass":
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }

          $this->subjectTriples[$this->parameters["uri"]] = $ontology->_getClassDescription($ontology->_getClass($this->parameters["uri"]));
        break;
        
        case "getclasses":

          $limit = -1;
          $offset = -1;
          
          if(isset($this->parameters["limit"]) && isset($this->parameters["offset"]))
          {
            $limit = $this->parameters["limit"];
            $offset = $this->parameters["offset"];
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
            
              $classes = $ontology->getClassesUri($limit, $offset);
             
              foreach($classes as $class)
              {
                if(!isset($this->subjectTriples[$class]))
                {
                  $this->subjectTriples[$class] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Class",
                                                                            "datatype" => "rdf:Resource",
                                                                            "lang" => "")));
                }  
              }
            break;
            
            case "descriptions":
              $this->subjectTriples = $ontology->getClassesDescription($limit, $offset);
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;            
          }
          
        break;
        
        case "getnamedindividual":
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }

          $this->subjectTriples[$this->parameters["uri"]] = $ontology->_getNamedIndividualDescription($ontology->_getNamedIndividual($this->parameters["uri"]));
        break;        
        
        case "getnamedindividuals":

          $limit = -1;
          $offset = -1;
          
          $direct = true;
          
          if(isset($this->parameters["limit"]) && isset($this->parameters["offset"]))
          {
            $limit = $this->parameters["limit"];
            $offset = $this->parameters["offset"];
          }

          if(isset($this->parameters["direct"]))
          {
            switch($this->parameters["direct"])
            {
              case "0":
                $direct = false;
              break;
              case "1":
                $direct = true;
              break;
            }
          }
          
          $classUri = "all";
          
          if(!isset($this->parameters["classuri"]))
          {
            $classUri = $this->parameters["classuri"];
          }          
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $namedindividuals = $ontology->getNamedIndividualsUri($classUri, $direct, $limit, $offset);
             
              foreach($namedindividuals as $ni)
              {
                if(!isset($this->subjectTriples[$ni]))
                {
                  $this->subjectTriples[$ni] = array(Namespaces::$rdf."type" => array(array("value" => "owl:NamedIndividual",
                                                                                            "datatype" => "rdf:Resource",
                                                                                            "lang" => "")));
                }  
              }
            break;
            
            case "descriptions":
              $this->subjectTriples = $ontology->getNamedIndividualsDescription($classUri, $direct, $limit, $offset);
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;            
          }
          
        break;
        
        case "getsubclasses":
          
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $classes = $ontology->getSubClassesUri($this->parameters["uri"], (boolean)$this->parameters["direct"]);
              
              foreach($classes as $class)
              {
                if(!isset($this->subjectTriples[$class]))
                {
                  $this->subjectTriples[$class] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Class",
                                                                                               "datatype" => "rdf:Resource",
                                                                                               "lang" => "")));
                }  
              }
            break;
            
            case "descriptions":
              $this->subjectTriples = $ontology->getSubClassesDescription($this->parameters["uri"], (boolean)$this->parameters["direct"]);
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;
        
        case "getsuperclasses":

          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $classes = $ontology->getSuperClassesUri($this->parameters["uri"], (boolean)$this->parameters["direct"]);
              
              foreach($classes as $class)
              {
                if(!isset($this->subjectTriples[$class]))
                {
                  $this->subjectTriples[$class] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Class",
                                                                                               "datatype" => "rdf:Resource",
                                                                                               "lang" => "")));
                }  
              }
            break;
            
            case "descriptions":
              $this->subjectTriples = $ontology->getSuperClassesDescription($this->parameters["uri"], (boolean)$this->parameters["direct"]);
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;        
        
        case "getequivalentclasses":

          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $classes = $ontology->getEquivalentClassesUri($this->parameters["uri"]);
              
              foreach($classes as $class)
              {
                if(!isset($this->subjectTriples[$class]))
                {
                  $this->subjectTriples[$class] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Class",
                                                                                               "datatype" => "rdf:Resource",
                                                                                               "lang" => "")));
                }  
              }
            break;
            
            case "descriptions":
              $this->subjectTriples = $ontology->getEquivalentClassesDescription($this->parameters["uri"]);
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;                
                
        case "getdisjointclasses":

          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $classes = $ontology->getDisjointClassesUri($this->parameters["uri"]);
              
              foreach($classes as $class)
              {
                if(!isset($this->subjectTriples[$class]))
                {
                  $this->subjectTriples[$class] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Class",
                                                                                               "datatype" => "rdf:Resource",
                                                                                               "lang" => "")));
                }  
              }
            break;
            
            case "descriptions":
              $this->subjectTriples = $ontology->getDisjointClassesDescription($this->parameters["uri"]);
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;  
        
        case "getontologies":
       
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $ontologies = $ontology->getOntologiesUri();
              
              foreach($ontologies as $ontology)
              {
                if(!isset($this->subjectTriples[$ontology]))
                {
                  $this->subjectTriples[$ontology] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Ontology",
                                                                                               "datatype" => "rdf:Resource",
                                                                                               "lang" => "")));
                }  
              }
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;
          }
          
        break;        
        
        case "getloadedontologies":
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              $ontologies = OWLOntology::getLoadedOntologiesUri($this->OwlApiSession);
              
              foreach($ontologies as $ontology)
              {
                if(!isset($this->subjectTriples[$ontology]))
                {
                  $this->subjectTriples[$ontology] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Ontology",
                                                                                                  "datatype" => "rdf:Resource",
                                                                                                  "lang" => "")));
                }  
              }
            break;
            
            case "descriptions":
              $this->subjectTriples = OWLOntology::getLoadedOntologiesDescription($this->OwlApiSession); 
            break;
            
            default:
              $this->conneg->setStatus(400);
              $this->conneg->setStatusMsg("Bad Request");
              $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
              $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
                $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
                $this->errorMessenger->_201->level);
              return;
            break;
          }
          
        break;
        
        case "getserializedclasshierarchy":
          $sch = $this->generationSerializedClassHierarchy($this->OwlApiSession);

          $this->subjectTriples[$this->ontologyUri] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Ontology",
                                                                                          "datatype" => "rdf:Resource",
                                                                                          "lang" => "")),
                                                   "http://purl.org/ontology/wsf#serializedClassHierarchy" => array(array("value" => $sch,
                                                                                          "datatype" => "rdf:Literal",
                                                                                          "lang" => "")));
          
                      
        break;
        
        case "getserializedpropertyhierarchy":
          $sch = $this->generationSerializedPropertyHierarchy($this->OwlApiSession);

          $this->subjectTriples[$this->ontologyUri] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Ontology",
                                                                                          "datatype" => "rdf:Resource",
                                                                                          "lang" => "")),
                                                   "http://purl.org/ontology/wsf#serializedPropertyHierarchy" => array(array("value" => $sch,
                                                                                          "datatype" => "rdf:Literal",
                                                                                          "lang" => "")));
          
                      
        break;
        
        case "getstructxmlschema":
          $this->subjectTriples = $ontology->getClassesDescription($limit, $offset);
          
          $schema = '<schema><version>0.1</version><typeList>';
          
          $prefixes = array();
          
          foreach($this->subjectTriples as $uri => $subject)
          {
            $this->manageIronXMLPrefix($uri, $prefixes);
            
            $schema .= "<".$this->ironXMLPrefixize($uri, $prefixes).">";

            $schema .= "<description>".$this->xmlEncode($this->getDescription($subject))."</description>";
            $schema .= "<prefLabel>".$this->xmlEncode($this->getLabel($uri, $subject))."</prefLabel>";
            
            foreach($subject as $predicate => $values)
            {
              foreach($values as $value)
              {
                switch($predicate)
                {
                  case Namespaces::$rdfs."subClassOf":
                    $this->manageIronXMLPrefix($value["value"], $prefixes);
                    
                    $schema .= "<subTypeOf>".$this->xmlEncode($this->ironXMLPrefixize($value["value"], $prefixes))."</subTypeOf>";
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    
                    $displayControl = substr($value["value"], strripos($value["value"], "#"));
                    
                    $schema .= "<displayControl>".$this->xmlEncode($displayControl)."</displayControl>";
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= "<shortLabel>".$this->xmlEncode($value["value"])."</shortLabel>";
                  break;
                  
                  case Namespaces::$sco."mapMarkerImageUrl":
                    $schema .= "<mapMarkerImageUrl>".$this->xmlEncode($value["value"])."</mapMarkerImageUrl>";
                  break;
                  
                  case Namespaces::$sco."relationBrowserNodeType":
                    $this->manageIronXMLPrefix($value["value"], $prefixes);
                    
                    $schema .= "<relationBrowserNodeType>".$this->xmlEncode($this->ironXMLPrefixize($value["value"], $prefixes))."</relationBrowserNodeType>";
                  break;
                }              
              }
            }
            
            $schema .= "</".$this->ironXMLPrefixize($uri, $prefixes).">";            
          }
          
          $schema .= "</typeList>";
          $schema .= "<attributeList>";

          $this->subjectTriples = $ontology->getPropertiesDescription(TRUE);

          foreach($this->subjectTriples as $uri => $subject)
          {
            $this->manageIronXMLPrefix($uri, $prefixes);
            
            $schema .= "<".$this->ironXMLPrefixize($uri, $prefixes).">";

            $schema .= "<description>".$this->xmlEncode($this->getDescription($subject))."</description>";
            $schema .= "<prefLabel>".$this->xmlEncode($this->getLabel($uri, $subject))."</prefLabel>";
            
            $schema .= "<allowedValue><primitive>String</primitive></allowedValue>";
            
            foreach($subject as $predicate => $values)
            {
              foreach($values as $value)
              {
                switch($predicate)
                {
                  case Namespaces::$rdfs."domain":
                    $this->manageIronXMLPrefix($value["value"], $prefixes);
                    
                    $schema .= "<allowedType>".$this->xmlEncode($this->ironXMLPrefixize($value["value"], $prefixes))."</allowedType>";
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    $displayControl = substr($value["value"], strripos($value["value"], "#"));
                    
                    $schema .= "<displayControl>".$this->xmlEncode($displayControl)."</displayControl>";
                  break;

                  case Namespaces::$sco."comparableWith":
                    $this->manageIronXMLPrefix($value["value"], $prefixes);
                    
                    $schema .= "<comparableWith>".$this->xmlEncode($this->ironXMLPrefixize($value["value"], $prefixes))."</comparableWith>";
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= "<shortLabel>".$this->xmlEncode($value["value"])."</shortLabel>";
                  break;
                  
                  case Namespaces::$sco."orderingValue":
                    $schema .= "<orderingValue>".$this->xmlEncode($value["value"])."</orderingValue>";
                  break;                  
                }
              }
            }
            
            $schema .= "</".$this->ironXMLPrefixize($uri, $prefixes).">";
          }
          
          $this->subjectTriples = $ontology->getPropertiesDescription(FALSE, TRUE);

          foreach($this->subjectTriples as $uri => $subject)
          {
            $this->manageIronXMLPrefix($uri, $prefixes);
            
            $schema .= "<".$this->ironXMLPrefixize($uri, $prefixes).">";

            $schema .= "<description>".$this->xmlEncode($this->getDescription($subject))."</description>";
            $schema .= "<prefLabel>".$this->xmlEncode($this->getLabel($uri, $subject))."</prefLabel>";
            
            foreach($subject as $predicate => $values)
            {
              foreach($values as $value)
              {
                switch($predicate)
                {
                  case Namespaces::$rdfs."domain":
                    $this->manageIronXMLPrefix($value["value"], $prefixes);
                    
                    $schema .= "<allowedType>".$this->xmlEncode($this->ironXMLPrefixize($value["value"], $prefixes))."</allowedType>";
                  break;
                  
                  case Namespaces::$rdfs."range":
                    $this->manageIronXMLPrefix($value["value"], $prefixes);
                    
                    $schema .= "<allowedValue><type>".$this->xmlEncode($this->ironXMLPrefixize($value["value"], $prefixes))."</type></allowedValue>";
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    $displayControl = substr($value["value"], strripos($value["value"], "#"));
                    
                    $schema .= "<displayControl>".$this->xmlEncode($displayControl)."</displayControl>";
                  break;

                  case Namespaces::$sco."comparableWith":
                    $this->manageIronXMLPrefix($value["value"], $prefixes);
                    
                    $schema .= "<comparableWith>".$this->xmlEncode($this->ironXMLPrefixize($value["value"], $prefixes))."</comparableWith>";
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= "<shortLabel>".$this->xmlEncode($value["value"])."</shortLabel>";
                  break;
                  
                  case Namespaces::$sco."orderingValue":
                    $schema .= "<orderingValue>".$this->xmlEncode($value["value"])."</orderingValue>";
                  break;                  
                }
              }
            }
            
            $schema .= "</".$this->ironXMLPrefixize($uri, $prefixes).">";            
          }
          
          $schema .= "</attributeList>";
          $schema .= "<prefixList>";                    

          foreach($prefixes as $prefix => $ns)
          {
            $schema .= "    <$prefix>$ns</$prefix>";
          }
          
          $schema .= "</prefixList>";          
          $schema .= "</schema>";    
          
          $this->subjectTriples = null;
          
          $this->subjectTriples[$this->ontologyUri] = array(Namespaces::$rdf."type" => array(array("value" => "owl:Ontology",
                                                                                          "datatype" => "rdf:Resource",
                                                                                          "lang" => "")),
                                                   "http://purl.org/ontology/wsf#serializedIronXMLSchema" => array(array("value" => $schema,
                                                                                          "datatype" => "rdf:Literal",
                                                                                          "lang" => "")));                
          
/*
          <schema>
            <version>0.1</version>
            <prefLabel>PEG schema</prefLabel>
            <prefixList>
              <sco>http://purl.org/ontology/sco#</sco>
            </prefixList>
            <typeList>
              <peg_Neighborhood>
                <subTypeOf>pegf_Organization</subTypeOf>
                <description>Neighborhood community organization</description>
                <prefLabel>neighborhood</prefLabel>
                <displayControl>sRelationBrowser</displayControl>
              </peg_Neighborhood>
            </typeList>
            <attributeList>
              <peg_neighborhoodNumber>
                <prefLabel>neighborhood number</prefLabel>
                <description>Neighborhood identification number</description>
                <allowedType>Neighborhood</allowedType>
                <allowedType>City</allowedType>
                <allowedType>Province</allowedType>
                <allowedType>Country</allowedType>
                <allowedValue>
                  <primitive>String</primitive>
                </allowedValue>
                <maxValues>1</maxValues>
              </peg_neighborhoodNumber>
            </attributeList>
          </schema>
*/          

        break;
        
        case "getproperty":
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }

          $this->subjectTriples[$this->parameters["uri"]] = $ontology->_getPropertyDescription($ontology->_getProperty($this->parameters["uri"]));
        break;
        
        case "getproperties":
        
          $limit = -1;
          $offset = -1;
          
          if(isset($this->parameters["limit"]) && isset($this->parameters["offset"]))
          {
            $limit = $this->parameters["limit"];
            $offset = $this->parameters["offset"];
          }        
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getPropertiesUri(TRUE, FALSE, FALSE, $limit, $offset);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:DatatypeProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getPropertiesUri(FALSE, TRUE, FALSE, $limit, $offset);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:DatatypeProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                case "annotationproperty":
                  $properties = $ontology->getPropertiesUri(FALSE, FALSE, TRUE, $limit, $offset);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:AnnotationProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->subjectTriples = $ontology->getPropertiesDescription(TRUE, FALSE, FALSE, $limit, $offset);
                break;
                
                case "objectproperty":
                  $this->subjectTriples = $ontology->getPropertiesDescription(FALSE, TRUE, FALSE, $limit, $offset);
                break;
                
                case "annotationproperty":
                  $this->subjectTriples = $ontology->getPropertiesDescription(FALSE, FALSE, TRUE, $limit, $offset);
                break;

                case "all":
                  $this->subjectTriples = array_merge($this->subjectTriples, $ontology->getPropertiesDescription(TRUE, FALSE, FALSE, $limit, $offset));
                  $this->subjectTriples = array_merge($this->subjectTriples, $ontology->getPropertiesDescription(FALSE, TRUE, FALSE, $limit, $offset));
                  $this->subjectTriples = array_merge($this->subjectTriples, $ontology->getPropertiesDescription(FALSE, FALSE, TRUE, $limit, $offset));
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;
        
        case "getsubproperties":
        
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getSubPropertiesUri((string)$this->parameters["uri"], (boolean)$this->parameters["direct"], TRUE);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:DatatypeProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getSubPropertiesUri((string)$this->parameters["uri"], (boolean)$this->parameters["direct"], FALSE);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:ObjectProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->subjectTriples = $ontology->getSubPropertiesDescription((string)$this->parameters["uri"], (boolean)$this->parameters["direct"], TRUE);
                break;
                
                case "objectproperty":
                  $this->subjectTriples = $ontology->getSubPropertiesDescription((string)$this->parameters["uri"], (boolean)$this->parameters["direct"], FALSE);
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;        
        
        case "getsuperproperties":
        
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getSuperPropertiesUri((string)$this->parameters["uri"], (boolean)$this->parameters["direct"], TRUE);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:DatatypeProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getSuperPropertiesUri((string)$this->parameters["uri"], (boolean)$this->parameters["direct"], FALSE);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:DatatypeProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":

              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->subjectTriples = $ontology->getSuperPropertiesDescription((string)$this->parameters["uri"], (boolean)$this->parameters["direct"], TRUE);
                break;
                
                case "objectproperty":
                  $this->subjectTriples = $ontology->getSuperPropertiesDescription((string)$this->parameters["uri"], (boolean)$this->parameters["direct"], FALSE);
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;   
        
        case "getequivalentproperties":
        
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getEquivalentPropertiesUri((string)$this->parameters["uri"], TRUE);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:DatatypeProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getEquivalentPropertiesUri((string)$this->parameters["uri"], FALSE);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:DatatypeProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->subjectTriples = $ontology->getEquivalentPropertiesDescription((string)$this->parameters["uri"], TRUE);
                break;
                
                case "objectproperty":
                  $this->subjectTriples = $ontology->getEquivalentPropertiesDescription((string)$this->parameters["uri"], FALSE);
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;
        
        case "getdisjointproperties":
        
          if(!isset($this->parameters["uri"]) || $this->parameters["uri"] == "")
          {
            $this->returnError(400, "Bad Request", "_202");
            return;              
          }
        
          switch(strtolower($this->parameters["mode"]))
          {
            case "uris":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $properties = $ontology->getDisjointPropertiesUri((string)$this->parameters["uri"], TRUE);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:DatatypeProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                case "objectproperty":
                  $properties = $ontology->getDisjointPropertiesUri((string)$this->parameters["uri"], FALSE);
                  
                  foreach($properties as $property)
                  {
                    if(!isset($this->subjectTriples[$property]))
                    {
                      $this->subjectTriples[$property] = array(Namespaces::$rdf."type" => array(array("value" => "owl:DatatypeProperty",
                                                                                "datatype" => "rdf:Resource",
                                                                                "lang" => "")));
                    }  
                  }
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }
            break;
            
            case "descriptions":
              switch(strtolower($this->parameters["type"]))
              {
                case "dataproperty":
                  $this->subjectTriples = $ontology->getDisjointPropertiesDescription((string)$this->parameters["uri"], TRUE);
                break;
                
                case "objectproperty":
                  $this->subjectTriples = $ontology->getDisjointPropertiesDescription((string)$this->parameters["uri"], FALSE);
                break;
                
                default:
                  $this->returnError(400, "Bad Request", "_203");
                  return;
                break;         
              }              
            break;
            
            default:
              $this->returnError(400, "Bad Request", "_201");
              return;
            break;         
          }
        break;        
        
        default:
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
          $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
            $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
            $this->errorMessenger->_200->level);

          return;          
        break;
      }
    }
  }

  private function generationSerializedPropertyHierarchy($OwlApiSession)
  {
    $ontologiesUri = OWLOntology::getLoadedOntologiesUri($OwlApiSession);

    $propertyHierarchy = new PropertyHierarchy("http://www.w3.org/2002/07/owl#Thing");
    
    foreach($ontologiesUri as $ontologyUri)
    {
      $onto = new OWLOntology($ontologyUri, $OwlApiSession, TRUE);
      
      $this->populatePropertyHierarchy("http://www.w3.org/2002/07/owl#topObjectProperty", $onto, $ontologyUri, $propertyHierarchy, FALSE);
      $this->populatePropertyHierarchy("http://www.w3.org/2002/07/owl#topDataProperty", $onto, $ontologyUri, $propertyHierarchy, TRUE);
    }  
                
    return(serialize($propertyHierarchy));
  } 
  
  private function populatePropertyHierarchy($parentProperty, $ontology, $ontologyUri, &$propertyHierarchy, $isDataProperty)
  {
    $subProperties = $ontology->getSubPropertiesDescription($parentProperty, TRUE, $isDataProperty);

    foreach($subProperties as $subProperty => $description)
    {
      $propertyHierarchy->addPropertyRelationship($subProperty, $parentProperty);

      $propertyHierarchy->properties[$subProperty]->label = $this->getLabel($subProperty, $description); 
      $propertyHierarchy->properties[$subProperty]->description = $this->getDescription($description); 

      $propertyHierarchy->properties[$subProperty]->isDefinedBy = $ontologyUri;
      
      // Add in-domain-of
      $domainClasses = array();
      if(isset($description[Namespaces::$rdfs."domain"]))
      {
        foreach($description[Namespaces::$rdfs."domain"] as $domain)
        {
          array_push($domainClasses, $ontology->getSubClassesUri($domain["value"], FALSE));
        }
      }
      
      $domainClasses = array_unique($domainClasses);
      
      $propertyHierarchy->properties[$subProperty]->domain = $domainClasses;
      
      // Add in-range-of
      $rangeClasses = array();
      if(isset($description[Namespaces::$rdfs."range"]))
      {
        foreach($description[Namespaces::$rdfs."range"] as $range)
        {
          array_push($rangeClasses, $ontology->getSubClassesUri($range["value"], FALSE));
        }
      }
           
      $rangeClasses = array_unique($rangeClasses);
      
      $propertyHierarchy->properties[$subProperty]->range = $rangeClasses;
      
      // Dig into the structure...
      $this->populatePropertyHierarchy($subProperty, $ontology, $ontologyUri, $propertyHierarchy, $isDataProperty);
    }
  }  
  
  private function generationSerializedClassHierarchy($OwlApiSession)
  {
    $ontologiesUri = OWLOntology::getLoadedOntologiesUri($OwlApiSession);
    
    $classHierarchy = new ClassHierarchy("http://www.w3.org/2002/07/owl#Thing");
    
    foreach($ontologiesUri as $ontologyUri)
    {
      $onto = new OWLOntology($ontologyUri, $OwlApiSession, TRUE);
      
      $this->populateClassHierarchy("http://www.w3.org/2002/07/owl#Thing", $onto, $ontologyUri, $classHierarchy);
    }  
    
    return(serialize($classHierarchy));
  } 
  
  private function populateClassHierarchy($parentClass, $ontology, $ontologyUri, &$classHierarchy)
  {
    $subClasses = $ontology->getSubClassesDescription($parentClass, TRUE);

    if(isset($subClasses[Namespaces::$owl."Nothing"]))
    {
      return;
    }
    
    foreach($subClasses as $subClass => $description)
    {
      $classHierarchy->addClassRelationship($subClass, $parentClass);

      $classHierarchy->classes[$subClass]->label = $this->getLabel($subClass, $description); 
      $classHierarchy->classes[$subClass]->description = $this->getDescription($description); 
      
      $classHierarchy->classes[$subClass]->isDefinedBy = $ontologyUri;
      
      // Dig into the structure...
      $this->populateClassHierarchy($subClass, $ontology, $ontologyUri, $classHierarchy);
    }
  }
  
  public function getLabel($uri, $description)  
  {
    $prefLabelAttributes = array(
      Namespaces::$rdfs."label",
      Namespaces::$skos_2004."prefLabel",
      Namespaces::$skos_2008."prefLabel",
      Namespaces::$umbel."prefLabel",
      Namespaces::$dcterms."title",
      Namespaces::$dc."title",
      Namespaces::$iron."prefLabel",
      Namespaces::$skos_2004."altLabel",
      Namespaces::$skos_2008."altLabel",
      Namespaces::$umbel."altLabel",
      Namespaces::$iron."altLabel"
    );
    
    foreach($prefLabelAttributes as $attribute)
    {
      if(isset($description[$attribute]))
      {
        return($description[$attribute][0]["value"]);
      }
    }
/*    
    if(array_search($propertyUri, $prefLabelAttributes) !== FALSE)
    {
      
    }    
    
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
*/    

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

    return $resource;
  }  
  
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
  
  public function setOwlApiSession($OwlApiSession)
  {
    $this->OwlApiSession = $OwlApiSession;
  }
  
  public function ironXMLPrefixize($uri, &$prefixes)
  {
    if(strripos($uri, "#") !== FALSE)
    {
      $p = substr($uri, strripos($uri, "/") + 1, strripos($uri, "#") - (strripos($uri, "/") + 1));
      
      return($p."_".substr($uri, strripos($uri, "#") + 1));
    }
    elseif(strripos($uri, "/") !== FALSE)
    {
      $p = substr($uri, strripos($uri, "/") + 1, strripos($uri, "/") - (strripos($uri, "/") + 1));
      
      return($p."_".substr($uri, strripos($uri, "/") + 1));
    }    
  } 
  
  public function manageIronXMLPrefix($uri, &$prefixes)
  {
    if(strripos($uri, "#") !== FALSE)
    {
      $p = substr($uri, strripos($uri, "/") + 1, strripos($uri, "#") - (strripos($uri, "/") + 1));
      
      if(!isset($prefixes[$p]))
      {
        $prefixes[$p] = substr($uri, 0, strripos($uri, "#") + 1);
      }
    }
    elseif(strripos($uri, "/") !== FALSE)
    {
      $uriMod = substr($uri, 0, strripos($uri, "/", strripos($uri, "/")));
      
      $p = substr($uriMod, strripos($uriMod, "/") + 1);
      
      if(!isset($prefixes[$p]))
      {
        $prefixes[$p] = substr($uri, 0, strripos($uri, "/", strripos($uri, "/")) + 1);
      }
    }
  }  
}


//@}

?>
