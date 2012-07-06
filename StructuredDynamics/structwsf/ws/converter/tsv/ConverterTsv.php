<?php

/** @defgroup WsConverterTsv Converter TSV Web Service */
//@{

/*! @file \StructuredDynamics\structwsf\ws\converter\tsv\ConverterTsv.php
    @brief Define the TSV converter class
 */

namespace StructuredDynamics\structwsf\ws\converter\tsv;

use \StructuredDynamics\structwsf\ws\framework\DBVirtuoso; 
use \StructuredDynamics\structwsf\ws\framework\CrudUsage;
use \StructuredDynamics\structwsf\ws\framework\Conneg;
use \StructuredDynamics\structwsf\ws\framework\ProcessorXML;
use \StructuredDynamics\structwsf\ws\converter\tsv\TsvParser;

/** Convert TSV data into RDF. This class takes TSV (table separeted values) files as input, 
    convert them into RDF using the BKN Ontology, and output RDF in different formats.

    @author Frederick Giasson, Structured Dynamics LLC.
  
    @todo Implementing the input of RDF and output in TSV' format
*/

class ConverterTsv extends \StructuredDynamics\structwsf\ws\framework\WebService
{
  /** Database connection */
  private $db;

  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Text being converted */
  private $text;

  /** Mime type of the document */
  private $docmime;

  /** Type of the resource being converted */
  private $type;

  /** Parsed TSV data items */
  private $tsvResources;

  /** Base URI for the resources being created by this convertion process */
  private $baseURI;

  /** Error message to report */
  private $errorMessages = "";

  /** IP of the requester */
  private $requester_ip = "";

  /** Requested IP */
  private $registered_ip = "";

  /** Delimiter character of the TSV file */
  private $delimiter = "";

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/rdf+xml", "application/rdf+n3", "application/*", "text/tsv", "text/csv", "text/xml", "text/*",
      "*/*");

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/converter/irv/",
                        "_200": {
                          "id": "WS-CONVERTER-IRV-200",
                          "level": "Warning",
                          "name": "No data to convert",
                          "description": "No data to convert"
                        },
                        "_201": {
                          "id": "WS-CONVERTER-IRV-201",
                          "level": "Warning",
                          "name": "Document mime not supported (supported mimes: text/tsv, text/csv and text/xml)",
                          "description": "Document mime not supported (supported mimes: text/tsv, text/csv and text/xml)"
                        },
                        "_300": {
                          "id": "WS-CONVERTER-IRV-300",
                          "level": "Warning",
                          "name": "Parsing Errors",
                          "description": "Parsing Errors"
                        },
                        "_301": {
                          "id": "WS-CONVERTER-IRV-301",
                          "level": "Warning",
                          "name": "No TSV data converted",
                          "description": "No TSV data converted"
                        }  
                      }';


  /** Constructor

      @param $document Text of a Bibtex document
      @param $docmime The mime type of the incoming document to convert      
      @param $delimiter Delimiter used to split fields of a record row
      @param $base_uri The base URI to use to create the URIs of the resources created by this web service
      @param $registered_ip Target IP address registered in the WSF
      @param $requester_ip IP address of the requester
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($document = "", $docmime = "text/tsv", $delimiter = "\t",
    $base_uri = "http://www.baseuri.com/resource/", $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->text = $document;
    $this->baseURI = $base_uri;
    $this->docmime = $docmime;
    $this->delimiter = $delimiter;

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

    $this->tsvResources = array();

    $this->uri = $this->wsf_base_url . "/wsf/ws/converter/tsv/";
    $this->title = "TSV/CSV Converter Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/converter/tsv/";

    $this->dtdURL = "converter/tsv/tsv.dtd";

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

  /** Validate a query to this web service

      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery() { return; }

  /** Returns the error structure

      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getError() { return ($this->conneg->error); }

  /** Generate the converted TSV items using the internal XML representation

      @return a XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResultset()
  {
    if($this->docmime == "text/xml")
    {
      return ($this->text);
    }
    else
    {
      $xml = new ProcessorXML();

      $resultset = $xml->createResultset();

      foreach($this->tsvResources as $uri => $properties)
      {
        $subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $this->baseURI . $this->uriEncode($uri));

        foreach($properties as $propertyValue)
        {
          $propertyValue[1] = trim($propertyValue[1]);

          // check if the object of the TSV is a reference to another subject of the same document
          if(substr($propertyValue[0], 0, 1) == "{"
            && substr($propertyValue[0], strlen($propertyValue[0]) - 1, 1) == "}")
          {
            $property = $this->get_domain($this->baseURI) . "/ontology#" . $this->uriEncode($propertyValue[0]);
            $value = $this->baseURI . $this->uriEncode($propertyValue[1]);

            $pred = $xml->createPredicate($property);
            $object = $xml->createObject("http://www.w3.org/2002/07/owl#Thing", $value);

            $pred->appendChild($object);
            $subject->appendChild($pred);
          }
          else
          {
            $property = $this->get_domain($this->baseURI) . "/ontology#" . $this->uriEncode($propertyValue[0]);
            $value = $propertyValue[1];

            $pred = $xml->createPredicate($property);
            $object = $xml->createObjectContent($value);

            $pred->appendChild($object);
            $subject->appendChild($pred);
          }
        }

        $resultset->appendChild($subject);
      }

      return ($this->injectDoctype($xml->saveXML($resultset)));
    }
  }

  /** Get the domain of a URL
  
      @param $url the full URL
             
      @return the domain name of the URL *with* the prefix "http://"

      @author Frederick Giasson, Structured Dynamics LLC.
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

  /** Inject the DOCType in a XML document

      @param $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function injectDoctype($xmlDoc)
  {
    $posHeader = strpos($xmlDoc, '"?>') + 3;
    $xmlDoc = substr($xmlDoc, 0, $posHeader)
      . "\n<!DOCTYPE resultset PUBLIC \"-//Bibliographic Knowledge Network//Converter TSV DTD 0.1//EN\" \""
      . $this->dtdBaseURL . $this->dtdURL . "\">" . substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);

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
      new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, ConverterTsv::$supportedSerializations);

    // No text to process? Throw an error.
    if($this->text == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
        $this->errorMessenger->_200->level);
    }

    if($this->docmime != "text/csv" && $this->docmime != "text/tsv" && $this->docmime != "text/xml")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
        $this->errorMessenger->_201->level);
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

  /** Serialize the converted UCB Memorial Data content into different serialization formats

      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_serialize()
  {
    $rdf_part = "";

    switch($this->conneg->getMime())
    {
      case "text/tsv":
      case "text/csv":
        $tsv = "";
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

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
              $predicateType = $xml->getType($predicate);
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

        return ($tsv);
      break;

      case "application/rdf+n3":
        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          $rdf_part .= "\n    <$subjectURI> a <$subjectType> ;\n";

          $predicates = $xml->getPredicates($subject);

          foreach($predicates as $predicate)
          {
            $objects = $xml->getObjects($predicate);

            foreach($objects as $object)
            {
              $objectType = $xml->getType($object);
              $predicateType = $xml->getType($predicate);
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

        $namespaces = array();

        $nsId = 0;

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          $ns = $this->getNamespace($subjectType);
          $stNs = $ns[0];
          $stExtension = $ns[1];

          if(!isset($namespaces[$stNs]))
          {
            // Make sure the ID is not already existing. Increase the counter if it is the case.
            while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
            {
              $nsId++;
            }            
            
            $namespaces[$stNs] = "ns" . $nsId;
            $nsId++;
          }

          $rdf_part .= "\n    <" . $namespaces[$stNs] . ":" . $stExtension . " rdf:about=\"".
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
                $ptNs = $ns[0];
                $ptExtension = $ns[1];

                if(!isset($namespaces[$ptNs]))
                {
                  // Make sure the ID is not already existing. Increase the counter if it is the case.
                  while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                  {
                    $nsId++;
                  }
                                    
                  $namespaces[$ptNs] = "ns" . $nsId;
                  $nsId++;
                }

                $rdf_part .= "        <" . $namespaces[$ptNs] . ":" . $ptExtension . ">"
                  . $this->xmlEncode($objectValue) . "</" . $namespaces[$ptNs] . ":" . $ptExtension . ">\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);

                $ns = $this->getNamespace($predicateType);
                $ptNs = $ns[0];
                $ptExtension = $ns[1];

                if(!isset($namespaces[$ptNs]))
                {
                  // Make sure the ID is not already existing. Increase the counter if it is the case.
                  while(array_search("ns".$nsId, $this->namespaces) !== FALSE)
                  {
                    $nsId++;
                  }
                                    
                  $namespaces[$ptNs] = "ns" . $nsId;
                  $nsId++;
                }

                $rdf_part .= "        <" . $namespaces[$ptNs] . ":" . $ptExtension
                  . " rdf:resource=\"".$this->xmlEncode($objectURI)."\" />\n";
              }
            }
          }

          $rdf_part .= "    </" . $namespaces[$stNs] . ":" . $stExtension . ">\n";
        }

        $rdf_header =
          "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\" xmlns:wsf=\"http://purl.org/ontology/wsf#\"";

        foreach($namespaces as $ns => $prefix)
        {
          $rdf_header .= " xmlns:$prefix=\"$ns\"";
        }

        $rdf_header .= ">\n\n";

        $rdf_part = $rdf_header . $rdf_part;

        return ($rdf_part);
      break;
    }
  }

  /** Serialize the converted UCB Memorial Data content into different serialization formats

      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function ws_serialize()
  {
    // Check for parsing errors
    if($this->tsvParsingError() === TRUE)
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
      $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
        $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, $this->errorMessages,
        $this->errorMessenger->_300->level);

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
          $rdf_document .= "@prefix wsf: <http://purl.org/ontology/wsf#> .\n";

          $rdf_document .= $this->pipeline_serialize();

          return $rdf_document;
        break;

        case "application/rdf+xml":
          $rdf_document = "";
          $rdf_document .= "<?xml version=\"1.0\"?>\n";

          $rdf_document .= $this->pipeline_serialize();

          $rdf_document .= "</rdf:RDF>";

          return $rdf_document;
        break;

        case "text/xml":
          return $this->pipeline_getResultset();
        break;
      }
    }
  }

  /** Get the namespace of a URI
              
      @param $uri Uri of the resource from which we want the namespace

      @return returns the extracted namespace      
      
      @author Frederick Giasson, Structured Dynamics LLC.
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


  /** Normalize the remaining of a URI

      @param $uri The remaining of a URI to normalize
      
      @return a Normalized remaining URI
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function uriEncode($uri)
  {
    $uri = preg_replace("|[^a-zA-z0-9]|", " ", $uri);
    $uri = preg_replace("/\s+/", " ", $uri);
    $uri = str_replace(" ", "_", $uri);

    return ($uri);
  }

/** Parse the TSV file for declaraton error (properties or classes used in the file that are not defined on the node)

    @return returns TRUE if there is errors; FALSE otherwise
  
    @author Frederick Giasson, Structured Dynamics LLC.
*/
  private function tsvParsingError() { return FALSE;

/*
$validClasses = array("bkn:Affiliation", "bkn:Bibliography", "bkn:Biography", "bkn:CurriculumVitae", "bkn:Department", "bkn:Homepage", "bkn:Honor", "bkn:Interview", "bkn:Library", "bkn:Memorial", "bkn:News", "bkn:Obituary", "bkn:PhotoGallery", "bkn:PreprintBibliography", "bkn:Profile", "bkn:Publisher", "bkn:Quotes", "bkn:Relation", "bkn:Society", "bkn:University", "bkn-base:Astrophysics", "bkn-base:Biology", "bkn-base:BooleanFunctions", "bkn-base:CirclePackings", "bkn-base:CoarseGeometry", "bkn-base:ComputerScience", "bkn-base:ConformalGeometry", "bkn-base:Economics", "bkn-base:GameTheory", "bkn-base:Geometry", "bkn-base:Mathematics", "bkn-base:Probabilities", "bkn-base:RandomWalks", "bkn-base:SchrammLoewnerEvolutions", "bkn-base:Statistics", "bibo:AcademicArticle", "bibo:Article", "bibo:Book", "bibo:BookSection", "bibo:Chapter", "bibo:CollectedDocument", "bibo:Collection", "bibo:Document", "bibo:DocumentPart", "bibo:EditedBook", "bibo:Email", "bibo:Excerpt", "bibo:Issue", "bibo:Journal", "bibo:Letter", "bibo:Magazine", "bibo:Manual", "bibo:Manuscript", "bibo:Newspaper", "bibo:Note", "bibo:Patent", "bibo:Periodical", "bibo:Proceedings", "bibo:Quote", "bibo:ReferenceSource", "bibo:Report", "bibo:Series", "bibo:Slide", "bibo:Slideshow", "bibo:Standard", "bibo:Thesis", "bibo:Webpage", "bibo:Website", "foaf:Agent", "foaf:Organization", "foaf:Person", "bio:Death", "umbel:SubjectConcept", "bkn:Software", "bkn:Service", "bkn:Job", "bkn:Mactutor", "bibo:Transcript", "bkn:Encyclopedia"); 

$validProperties = array("bkn:ar", "bkn:mscmr", "bkn:arxiv","bkn:authorTex","bkn:titleTex","bkn:euclid","bkn:mrClass","bkn:mrNumber","bkn:url","bkn:position","bkn:affiliatedTo","bkn:affiliation","bkn:agent","bkn:associatedDepartment","bibo:abstract","bibo:asin","bibo:chapter","bibo:coden","bibo:doi","bibo:eanucc13","bibo:edition","bibo:eissn","bibo:gtin14","bibo:identifier","bibo:isbn10","bibo:isbn13","bibo:issn","bibo:lccn","bibo:locator","bibo:number","bibo:oclcnum","bibo:pageEnd","bibo:pages","bibo:pageStart","bibo:pmid","bibo:prefixName","bibo:section","bibo:shortDescription","bibo:shortTitle","bibo:sici","bibo:suffixName","bibo:upc","bibo:volume","bibo:annotates","bibo:degree","bibo:distributor","bibo:editor","bibo:interviewee","bibo:interviewer","bibo:issuer","bibo:owner","bibo:reproducedIn","bibo:reviewOf","bibo:status","bibo:transcriptOf","bibo:translationOf","bibo:translator","address:localityName","dcterms:created","dcterms:date","dcterms:dateAccepted","dcterms:dateCopyrighted","dcterms:dateSubmitted","dcterms:description","dcterms:issued","dcterms:modified","dcterms:publisher","dcterms:title","dcterms:contributor","dcterms:creator","dcterms:format","dcterms:hasPart","dcterms:isPartOf","dcterms:language","dcterms:license","dcterms:mediator","dcterms:publisher","dcterms:replaces","dcterms:requires","dcterms:rightsHolder","dcterms:type","foaf:accountName","foaf:birthdate","foaf:family_name","foaf:gender","foaf:givenname","foaf:name","foaf:nick","foaf:phone","foaf:topic_interest","foaf:depiction","foaf:homepage","foaf:interest","foaf:knows","foaf:logo","foaf:made","foaf:member","foaf:page","foaf:weblog","foaf:workInfoHomepage","bio:event","skos:note","rdf:type","rdfs:seeAlso","umbel:isAbout", "bio:date", "skos:prefLabel", "skos:altLabel");    


$nbErrors = 0;
*/
/*  
  Array
  (
    [http_www_math_cornell_edu_People_Faculty_kesten_html] => Array
      (
        [0] => Array
          (
            [0] => rdf:type
            [1] => {bkn:Homepage}
          )
  
        [1] => Array
          (
            [0] => bkn:url
            [1] => http://www.math.cornell.edu/People/Faculty/kesten.html
          )
  
      )
  )  
*/
/*
    $bknTemps = array();
    
    foreach($this->tsvResources as $uri => $properties)
    {
      foreach($properties as $propertyValue)
      {
        if($propertyValue[0] == "rdf:type")
        {
          $propertyValue[1] = substr($propertyValue[1], 1, strlen($propertyValue[1]) - 2 );

                // Check if it is a BKN-TEMP property or class.
                if(substr($propertyValue[1], 0, 9) == "bkn-temp:")
                {
                  array_push($bknTemps, array($propertyValue[1], "http://www.w3.org/2000/01/rdf-schema#Class"));
                }
                else
                {
                  // Check if this is a valid Class
                  if(array_search($propertyValue[1], $validClasses) === FALSE)
                  {
                    $this->errorMessages .= "<b>Error:</b> the use of the Class <b>&lt;$propertyValue[1]&gt;</b> is invalid when describing resource <b>&lt;$uri&gt;</b>.<br /><br />\n";
                    $nbErrors++;
                  }
                }
              }
              else
              {
                // Check if it is a BKN-TEMP property or class.
                if(substr($propertyValue[0], 0, 9) == "bkn-temp:")
                {
                  array_push($bknTemps, array($propertyValue[0], "http://www.w3.org/2000/01/rdf-schema#Property"));
                }
                else
                {
                  // Check if this is a valid Property
                  if(array_search($propertyValue[0], $validProperties) === FALSE)
                  {
                    $this->errorMessages .= "<b>Error:</b> the use of the Property <b>&lt;$propertyValue[0]&gt;</b> is invalid when describing resource <b>&lt;$uri&gt;</b>.<br /><br />\n";
                    $nbErrors++;
                  }        
                }
              }
            }  
          }  
          
          // Handle the BKN-TEMPS classes and properties
          if(count($bknTemps) > 0)
          {
            $this->db = new DBVirtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);
      
            $bknTempsN3 = "@prefix bkn-temp: <http://purl.org/ontology/bkn/temp#> .\n";
            
            foreach($bknTemps as $temp)
            {
              $bknTempsN3 .= "$temp[0] a <$temp[1]> .\n";
            }
            
          
            $this->db->query("DB.DBA.TTLP_MT('".$bknTempsN3."', 'http://purl.org/ontology/bkn/temp#', 'http://purl.org/ontology/bkn/temp#')");    
            
            $this->db->close();
          }
      
          if($this->errorMessages != "")
          {
            $this->errorMessages = "<b>$nbErrors errors.</b><br /><br />\n".$this->errorMessages;
            
            return(TRUE);
          }
      
          return(FALSE);  */
  }

  /** Convert the target document

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {
    if($this->conneg->getStatus() == 200)
    {
      switch($this->docmime)
      {
        case "text/tsv":
        case "text/csv":
          $parser = new TsvParser($this->text, $this->delimiter);

          $this->tsvResources = $parser->resources;

          if(count($this->tsvResources) <= 0)
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
            $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
              $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, "",
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