<?php

/*! @defgroup WsConverterBibtex Converter Bibtex Web Service */
//@{

/*! @file \ws\converter\bibtex\ConverterBibtex.php
   @brief Define the Bibtex converter class
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Convert Bibtex data into RDF.
     @details   This class takes Bibtex files as input, convert them into RDF using the BKN Ontology, 
            and output RDF in different formats.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    @todo Implementing the input of RDF and output in Bibtex's format
  
    \n\n\n
*/

class ConverterBibtex extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Text being converted */
  private $text;

  /*! @brief Type of the resource being converted */
  private $docmime;

  /*! @brief Parsed TSV data items */
  private $bibItems;

  /*! @brief Base URI for the resources being created by this convertion process */
  private $baseURI;

  /*! @brief Error message to report */
  private $errorMessages = "";

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "application/x-bibtex", "text/*",
      "*/*");

  /*! @brief Enable the enhanced bibtex parsing */
  private $enhancedBibtex = FALSE;

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";

  /*! @brief Mapping between bib item types and bibo classes */
  private $bibTypes = array ( // Standard BIBTEX
  "book" => "bibo:Book", "booklet" => "bibo:Book", "misc" => "bibo:Document", "article" => "bibo:Article",
    "inbook" => "bibo:Chapter", "manual" => "bibo:Manual", "inproceedings" => "bibo:Article",
    "conference" => "bibo:Article", "unpublished" => "bibo:Document", "masterthesis" => "bibo:Thesis",
    "phdthesis" => "bibo:Thesis", "proceedings" => "bibo:Proceedings", "techreport" => "bibo:Report",
    "incollection" => "bibo:BookSection", "heading" => "bibo:Collection", "subject" => "umbel:SubjectConcept",
    "person" => "foaf:Person");

/*! @brief  Additional properties for special bib item types. These properties are automatically added when the resource is created for a given type. Example: a "unpublished" bib item type is a bibo:Document with a bibo:status/unpublished status & kind of thesis*/
  private $bibTypesAdditionalProperties = array ( // Standard BIBTEX
  "unpublished" => array ("bibo:status", "http://purl.org/ontology/bibo/status/unpublished"),
    "masterthesis" => array ("bibo:degree", "http://purl.org/ontology/bibo/degrees/ma"),
    "phdthesis" => array ("bibo:degree", "http://purl.org/ontology/bibo/degrees/phd"));

/*  
  Dropped from core:  - howpublished
                  - key
                  - annote
*/

/*
  BIBTYPES (custom): HEADING, PERSON, SUBJECTS
  
  HEADING  ???
  ID ???
  KEY_AU ???
  AUTHOR_AR ???
  AUTHOR_ID_MR ??? (not used)
  BUT ???
  POST (what posted refers to?)
  HOWPUBLISHED (same as publisher?)
  HOWPUBLISHED_AR (what AR refers to?)
  
  
  FJOURNAL (why duplicating journal for fjournal (only a change in the name); This should appears in the description of the journal itself.)
  MRREVIEWER

*/

/*! @brief Mapping of properties */
  private $bibProperties = array ( // Standard BIBTEX
  "title" => "dcterms:title", "author" => "dcterms:creator", "booktitle" => "dcterms:title",
    "publisher" => "dcterms:publisher", "year" => "dcterms:date", "month" => "dcterms:date", "isbn" => "bibo:isbn",
    "editor" => "bibo:editor", "institution" => "dcterms:contributor", "volume" => "bibo:volume", "url" => "bkn:url",
    "type" => "dcterms:type", "series" => "dcterms:isPartOf",
  //                          "school" => "rdfs:seeAlso",
  "pages" => "bibo:pages", "organization" => "bibo:organizer", "number" => "bibo:number", "note" => "skos:note",
    "journal" => "dcterms:isPartOf", "edition" => "bibo:edition", "chapter" => "bibo:chapter",
    "address" => "address:localityName",
  //                          "eprint" => "rdfs:seeAlso",
  "eprint" => "bkn:eprint", "crossref" => "dcterms:isPartOf", "name" => "foaf:name", "homepage" => "foaf:homepage",
    "last_updated" => "dcterms:modified", "bibliography" => "foaf:page", "honor" => "foaf:page",
    "born_date" => "foaf:birthdate", "death_date" => "bio:event", "image" => "foaf:page", "memorial" => "foaf:page",
    "biography" => "foaf:page", "url" => "bkn:url", "author_tex" => "bkn:authorTex", "title_tex" => "bkn:titleTex",
    "coden" => "bibo:coden", "sici" => "bibo:sici", "mrclass" => "bkn:mrClass", "mrnumber" => "bkn:mrNumber",
    "arxiv" => "bkn:arxiv", "euclid" => "bkn:euclid", "id_ar" => "bkn:ar", "msc_mr" => "bkn:mscmr",
    "comment" => "skos:note", "comment_post" => "skos:note", "rev" => "dcterms:modified", "date" => "dcterms:date",
    "subjectTitle" => "skos:prefLabel", "subjects" => "umbel:isAbout",
  //                          "howpublished" => "dcterms:publisher",
  //                          "howpublished_ar" => "dcterms:publisher",


  // Bibsonomy.org BIBTEX extension
  "biburl" => "bkn:url", "keywords" => "dcterms:subject", "abstract" => "bibo:abstract", "asin" => "bibo:asin",
    "ean" => "bibo:eanucc13", "doi" => "bibo:doi", "issn" => "bibo:issn", "description" => "dcterms:description");

  // Sometimes a bibtex property introduce a new resource. In such a case, we have to specify what is the type
  // of the resource, and the property where the value of the bibtex property will be converted.
  //
  // For example, the property "author" suggest that a bibtex item refers to a person resource.
  // This property is clearly introducing a new resource. This new resource will be of type "foaf:Person" and the
  // value of this bibtex property will be described using the "foaf:name" property.


  /*! @brief  Mapping of additional properties*/
  private $bibPropertiesAdditionalObjects = array ( // Standard BIBTEX
  "author" => array ("foaf:Agent", "foaf:name"), "publisher" => array ("foaf:Organization", "foaf:name"),
    "editor" => array ("foaf:Person", "foaf:name"), "institution" => array ("foaf:Organization", "foaf:name"),
    "series" => array ("bibo:Series", "dcterms:title"),
  //                                        "school" => array("foaf:Organization", "foaf:name"),
  "organization" => array ("foaf:Organization", "foaf:name"), "journal" => array ("bibo:Journal", "dcterms:title"),
  //                                        "eprint" => array("bibo:Document", "dcterms:title"),
  "homepage" => array ("bkn:Homepage", "bkn:url"), "honor" => array ("bkn:Honor", "bkn:url"),
    "image" => array ("bkn:PhotoGallery", "bkn:url"), "memorial" => array ("bkn:Memorial", "bkn:url"),
    "biography" => array ("bkn:Biography", "bkn:url"), "bibliography" => array ("bkn:Bibliography", "bkn:url"),
    "death_date" => array ("bio:Death", "bio:date"),);

  /*!   @brief Constructor
       @details   Initialize the Bibtex Converter Web Service
              
      \n
      
      @param[in] $document Text of a Bibtex document
      @param[in] $docmime The mime type of the incoming document to convert
      @param[in] $base_uri The base URI to use to create the URIs of the resources created by this web service
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($document = "", $docmime = "application/x-bibtex",
    $base_uri = "http://www.baseuri.com/resource/", $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

    $this->docmime = $docmime;
    $this->text = $document;
    $this->baseURI = $base_uri;

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

    $this->bibItems = array();

    $this->uri = $this->wsf_base_url . "/wsf/ws/converter/bibtex/";
    $this->title = "Bibtex Converter Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/converter/bibtex/";

    $this->dtdURL = "converter/bibtex.dtd";
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

  // Communication structure
/*

  <!DOCTYPE resultset PUBLIC "-//BKN //Bibtex Converter DTD 0.1//EN" "http://bknetwork.org:8890/ws/dtd/converter/bibtex.dtd">
  <resultset>
    <subject type="bibo:Book" uri="http://baseuri.com/companion">
    <predicate type="dcterms:title">
      <object type="rdfs:Literal">The {{\LaTeX}} {C}ompanion</object>
    </predicate>
    <predicate type="dcterms:creator">
      <object type="rdfs:Literal">Goossens, Michel and Mittelbach, Franck and Samarin, Alexander</object>
    </predicate>
    <predicate type="dcterms:date">
      <object type="rdfs:Literal">December 1993</object>
    </predicate>
    <predicate type="bibo:isbn">
      <object type="rdfs:Literal">0-201-54199-8</object>
    </predicate>
    <predicate type="dcterms:publisher">
      <object type="foaf:Organization" uri="http://baseuri.com/md5_hash_123456" label="AW"/>
    </predicate>  
    </subject>
    <subject type="foaf:Organization" uri="http://baseuri.com/md5_hash_123456">
    <predicate type="foaf:name">
      <object type="rdfs:Literal">AW</object>
    </predicate>
    </subject>
  </resultset>
   
*/

/*!   @brief Generate the converted TSV items using the internal XML representation
            
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
    else
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


      // Check if we are handling a collection of documents compiled by someone.
      if($this->enhancedBibtex)
      {
        foreach($this->bibItems as $item)
        {
          if($item->itemType == "heading")
          {
            $this->processingCollection = $item->itemID;
            break;
          }
        }
      }

      foreach($this->bibItems as $item)
      {
        // Creation of a SUBJECT of the RESULTSET

        if(!isset($this->bibTypes[$item->itemType]))
        {
          $item->itemType = "misc";
        }

        $subject =
          $xml->createSubject($this->bibTypes[$item->itemType], $this->baseURI . $this->uriEncode($item->itemID));

        // If we are processing a collection, lets make the link between the item and that collection.
        if($this->processingCollection != "" && $this->processingCollection != $item->itemID
          && $item->itemType != "subject" && $item->itemType != "person")
        {
          $pred = $xml->createPredicate("dcterms:isPartOf");
          $object = $xml->createObject("", $this->baseURI . $this->uriEncode($this->processingCollection));

          $pred->appendChild($object);
          $subject->appendChild($pred);
        }

        // Lets add the additional properties that define this bibtex item type
        if(isset($this->bibTypesAdditionalProperties[$item->itemType]))
        {
          // Creation of the predicate
          $pred = $xml->createPredicate($this->bibTypesAdditionalProperties[$item->itemType][0]);

          // Creation of the OBJECT of the predicate
          $object = $xml->createObject("", $this->bibTypesAdditionalProperties[$item->itemType][1]);

          $pred->appendChild($object);
          $subject->appendChild($pred);
        }

        // Now lets convert all the bibtex item properties
        foreach($item->properties as $property => $value)
        {
          if(isset($this->bibProperties[$property]))
          {
            // Check if we have a enhanced bibtex file
            $processed = FALSE;

            if($this->enhancedBibtex)
            {
              if($property == "author" || $property == "subjects")
              {
                // Lets explode and create authors
                $authors = explode(",", $value);

                foreach($authors as $author)
                {
                  // Creation of the predicate
                  $pred = $xml->createPredicate($this->bibProperties[$property]);
                  $object = $xml->createObject("", $this->baseURI . $this->uriEncode($author));
                  $pred->appendChild($object);

                  $subject->appendChild($pred);
                }

                $processed = TRUE;
              }
            }

            if($processed === FALSE)
            {
              // Creation of the predicate
              $pred = $xml->createPredicate($this->bibProperties[$property]);

              if(isset($this->bibPropertiesAdditionalObjects[$property]))
              {
                $object = $xml->createObject("", $this->baseURI . $this->uriEncode($value));
                $pred->appendChild($object);

                // Lets create the new subject
                $subSubject = $xml->createSubject($this->bibPropertiesAdditionalObjects[$property][0],
                  $this->baseURI . $this->uriEncode($value));
                $subPred = $xml->createPredicate($this->bibPropertiesAdditionalObjects[$property][1]);
                $subObject = $xml->createObjectContent(str_replace("\\", "%5C", $value));

                $subPred->appendChild($subObject);
                $subSubject->appendChild($subPred);
                $resultset->appendChild($subSubject);
              }
              else
              {
                // Creation of the OBJECT of the predicate
                $object = $xml->createObjectContent(str_replace("\\", "%5C", $value));

                $pred->appendChild($object);
              }

              $subject->appendChild($pred);
            }
          }
        }

        $resultset->appendChild($subject);
      }

      return ($this->injectDoctype($xml->saveXML($resultset)));
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
      . "\n<!DOCTYPE resultset PUBLIC \"-//Bibliographic Knowledge Network//Converter BibTeX DTD 0.1//EN\" \""
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
      ConverterBibtex::$supportedSerializations);

    // No text to process? Throw an error.
    if($this->text == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt("No data to convert");
    }

    if($this->docmime != "application/x-bibtex" && $this->docmime != "text/xml")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt(
        "Document mime not supported (supported mimes: application/x-bibtex and text/xml)");
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

/*!   @brief Get a prefix for a given ontology URI
     @details   This function return the prefix literal for a given ontologu URI. This is a simple procedure that associate an Ontology URI
                    to its "generally used prefix".

    \n
    
    @attention All the data ingested by a conStruct node has to define a prefix for the ontologies used to describe any data.
           
    @param[in] $uri is the URI of a class or a property of an ontology
           
    @return A string where the class or the property is prefixed with the most commonly used prefix
  
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
  private function get_uri_label($uri)
  {
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

    // Save the URI of the ontology
    $onto = substr($uri, 0, $pos);

    // Save the URI of the class or property passed in parameter
    $resource = substr($uri, $pos, strlen($uri) - $pos);

    // This statement associate a base ontology URI to its prefix.
    switch($onto)
    {
      case "http://xmlns.com/foaf/0.1/":
        return "foaf:" . $resource;
      break;

      case "http://purl.org/umbel/sc/":
      case "http://purl.org/umbel/ac/":
      case "http://umbel.org/ns/":
      case "http://umbel.org/ns/sc/":
      case "http://umbel.org/ns/ac/":
      case "http://umbel.org/umbel/":
      case "http://umbel.org/umbel#":
      case "http://umbel.org/umbel/sc/":
      case "http://umbel.org/umbel/ac/":
        return "umbel:" . $resource;
      break;

      case "http://www.w3.org/2000/01/rdf-schema#":
        return "rdfs:" . $resource;
      break;

      case "http://www.w3.org/1999/02/22-rdf-syntax-ns#":
        return "rdf:" . $resource;
      break;

      case "http://www.w3.org/2002/07/owl#":
        return "owl:" . $resource;
      break;

      case "http://purl.org/dc/terms/":
        return "dcterms:" . $resource;
      break;

      case "http://www.w3.org/2004/02/skos/core#":
      case "http://www.w3.org/2008/05/skos#":
        return "skos:" . $resource;
      break;

      case "http://purl.org/ontology/bibo/":
        return "bibo:" . $resource;
      break;

      case "http://purl.org/ontology/bkn#":
        return "bkn:" . $resource;
      break;

      case "http://purl.org/vocab/bio/0.1/":
        return "bio:" . $resource;
      break;

      case "http://schemas.talis.com/2005/address/schema#":
        return "address:" . $resource;
      break;

      // By default, we return the "un-prefixed" URI passed in parameter.
      default:
        return $uri;
      break;
    }
  }

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
      case "application/x-bibtex":
        $bibtex = "";

        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        $nbConvertedItems = 0;

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          // Check if the type of this subject is mappable to BibTeX
          $subjectType = $this->get_uri_label($subjectType);

          if(($bibType = array_search($subjectType, $this->bibTypes)) !== FALSE)
          {
            $bibtex .= "@" . $bibType . "{" . $subjectURI . " \n";
          }
          else
          {
            // If the type is unknow as a bibtex type, then we continue and doesnt convert that subject.
            continue;
          }

          // Check if the properties of this subject are mappable to BibTeX

          $predicates = $xml->getPredicates($subject);

          foreach($predicates as $predicate)
          {
            $objects = $xml->getObjects($predicate);
            $predicateType = $this->get_uri_label($xml->getType($predicate));

            $bibPropertyType;

            if(($bibPropertyType = array_search($predicateType, $this->bibProperties)) === FALSE)
            {
              // If the predicate is unmappable, we skip it
              continue;
            }

            $nbConvertedItems++;

            foreach($objects as $object)
            {
              $objectType = $xml->getType($object);

              if($objectType == "rdfs:Literal")
              {
                $objectValue = $xml->getContent($object);

                $bibtex .= " $bibPropertyType = \"$objectValue\",\n";
              }
              else
              {
                $objectLabel = $xml->getLabel($object);

                if($objectLabel == "")
                {
                  $objectURI = $xml->getURI($object);
                  $bibtex .= " $bibPropertyType = \"$objectURI\",\n";
                }
                else
                {
                  $bibtex .= " $bibPropertyType = \"$objectLabel\",\n";
                }
              }
            }
          }

          $bibtex .= "}\n";
        }

        if($nbConvertedItems == 0)
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt("No BibTex data converted");
          return;
        }

        return ($bibtex);
      break;

      case "application/rdf+n3":

        $xml = new ProcessorXML();
        $xml->loadXML($this->pipeline_getResultset());

        $subjects = $xml->getSubjects();

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          $rdf_part .= "\n    <$subjectURI> a $subjectType ;\n";

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
                $rdf_part .= "        $predicateType \"\"\"" . str_replace(array( "\\" ), "\\\\", $objectValue)
                  . "\"\"\" ;\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);
                $rdf_part .= "        $predicateType <$objectURI> ;\n";
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

        foreach($subjects as $subject)
        {
          $subjectURI = $xml->getURI($subject);
          $subjectType = $xml->getType($subject);

          $rdf_part .= "\n    <$subjectType rdf:about=\"$subjectURI\">\n";

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
                $rdf_part .= "        <$predicateType>" . $this->xmlEncode($objectValue) . "</$predicateType>\n";
              }
              else
              {
                $objectURI = $xml->getURI($object);
                $rdf_part .= "        <$predicateType rdf:resource=\"$objectURI\" />\n";
              }
            }
          }

          $rdf_part .= "    </$subjectType>\n";
        }

        return ($rdf_part);
      break;
    }
  }

  /*!   @brief Non implemented method (only defined)
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize_reification() { return ""; }

  /*!   @brief Serialize the converted UCB Memorial Data content into different serialization formats
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    switch($this->conneg->getMime())
    {
      case "application/x-bibtex":
        return $this->pipeline_serialize();
      break;

      case "application/rdf+n3":

        $rdf_document = "";
        $rdf_document .= "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
        $rdf_document .= "@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n";
        $rdf_document .= "@prefix owl: <http://www.w3.org/2002/07/owl#> .\n";
        $rdf_document .= "@prefix bibo: <http://purl.org/ontology/bibo/> .\n";
        $rdf_document .= "@prefix skos: <http://www.w3.org/2008/05/skos#> .\n";
        $rdf_document .= "@prefix foaf: <http://xmlns.com/foaf/0.1/> .\n";
        $rdf_document .= "@prefix event: <http://purl.org/NET/c4dm/event.owl> .\n";
        $rdf_document .= "@prefix dcterms: <http://purl.org/dc/terms/> .\n";
        $rdf_document .= "@prefix address: <http://schemas.talis.com/2005/address/schema> .\n";
        $rdf_document .= "@prefix bkn: <http://purl.org/ontology/bkn#> .\n";
        $rdf_document .= "@prefix bio: <http://purl.org/vocab/bio/0.1/> .\n";
        $rdf_document .= "@prefix umbel: <http://umbel.org/umbel#> .\n";

        $rdf_document .= $this->pipeline_serialize();

        return $rdf_document;

      break;

      case "application/rdf+xml":
        $rdf_document = "";
        $rdf_document .= "<?xml version=\"1.0\"?>\n";
        $rdf_document
          .= "<rdf:RDF xmlns:bibo=\"http://purl.org/ontology/bibo/\" xmlns:bibo_degrees=\"http://purl.org/ontology/bibo/degrees/\" xmlns:bibo_status=\"http://purl.org/ontology/bibo/status/\" xmlns:owl=\"http://www.w3.org/2002/07/owl#\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema#\" xmlns:rdfs=\"http://www.w3.org/2000/01/rdf-schema#\" xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\" xmlns:event=\"http://purl.org/NET/c4dm/event.owl#\" xmlns:address=\"http://schemas.talis.com/2005/address/schema#\" xmlns:dcterms=\"http://purl.org/dc/terms/\" xmlns:foaf=\"http://xmlns.com/foaf/0.1/\" xmlns:skos=\"http://www.w3.org/2008/05/skos#\" xmlns:bkn=\"http://purl.org/ontology/bkn#\" xmlns:bio=\"http://purl.org/vocab/bio/0.1/\" xmlns:umbel=\"http://umbel.org/umbel#\">\n\n";

        $rdf_document .= $this->pipeline_serialize();

        $rdf_document .= "</rdf:RDF>\n";

        return $rdf_document;
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

  /*!   @brief Convert the target document
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    // Make sure there was no conneg error prior to this process call
    if($this->conneg->getStatus() == 200)
    {
      switch($this->docmime)
      {
        case "text/xml": break;

        case "application/x-bibtex":
        default:
          // check if it is a real bibtex file; or a bibtex file serialized in TSV.
          preg_match_all("|@(.*){(.*)}|U", str_replace(array ("\r", "\n"), "", $this->text), $matches);

          if(count($matches[0]) > 1)
          {
            $parser = new BibtexParser($this->text);

            $this->bibItems = $parser->items;
          }
          else
          {
            $parser = new BibtexParserCsv($this->text);

            $this->bibItems = $parser->items;

            $this->enhancedBibtex = TRUE;
          }

          if(count($this->bibItems) <= 0)
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt("No BibTex data converted");
          }
        break;
      }
    }
  }
}

//@}

?>