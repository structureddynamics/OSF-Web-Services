<?php

/*! @ingroup WsFramework Framework for the Web Services  */
//@{

/*! @file \ws\framework\Solr.php
   @brief Query the Solr server.
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */

/*!   @brief Query the Solr server.

     @author Frederick Giasson, Structured Dynamics LLC.
*/

class Solr
{
  /*! @brief URL where to reach the Solr update endpoint */
  private $updateUrl;

  /*! @brief URL where to reach the Solr select (normal query) endpoint */
  private $selectUrl;
  
  /*! @brief URL where to reach the Solr Luke endpoint */
  private $lukeUrl;
  
  /*! @brief This is the folder there the file of the index where all the fields defined in Solr
   *         are indexed. You have to make sure that the web server has write access to this folder.
   *         This folder path has to end with a slash "/". 
   */
  private $fieldIndexFolder;
  

  /*!   @brief Constructor
              
      \n
      
      @param[in] $core An optional target Solr core in a multicore setting
      @param[in] $host The host name where the Solr server is accessible
      @param[in] $port The port number where the Solr server is accessible
      @param[in] $fieldIndexFolder The folder where the Solr fields index should be saved on the server
      
      @return returns the XML resultset
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($core = "", $host = "localhost", $port = "8983", $fieldIndexFolder = "/tmp/")
  {
    if($core != "")
    {
      $this->updateUrl = "http://$host:$port/solr/$core/update";
      $this->selectUrl = "http://$host:$port/solr/$core/select";
      $this->lukeUrl = "http://$host:$port/solr/$core/admin/luke?numTerms=0";
    }
    else
    {
      $this->updateUrl = "http://$host:$port/solr/update";
      $this->selectUrl = "http://$host:$port/solr/select";
      $this->lukeUrl = "http://$host:$port/solr/admin/luke?numTerms=0";
    }
    
    $this->fieldIndexFolder = $fieldIndexFolder;
  }

  function __destruct() { }

  /*!   @brief Send a select query to the Solr server
              
      \n
      
      @param[in] $query Solr query
      
      @return returns the XML resultset
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function select($query) { return $this->sendQuery($query); }

  /*!   @brief Send a update query to the Solr server
              
      \n
      
      @param[in] $content Solr content (add) XML item to add to the server
      
      @return returns the XML resultset with the status of this request
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function update($content) { return $this->sendContent($content); }

  /*!   @brief Send a commit query to the Solr server
              
      \n
      
      @return returns the XML resultset with the status of this request
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function commit() { return $this->sendContent("<commit />"); }

  /*!   @brief Delete a specific instance record in the solr index
              
      \n
      
      @param[in] $uri URI of the instance record to delete
      
      @param[in] $dataset Dataset URI where the instance record is described
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function deleteInstanceRecord($uri, $dataset)
  {
    if($uri != "" && $dataset != "")
    {
      return $this->sendContent("<delete><id>" . md5($dataset . $uri) . "</id></delete>");
    }
  }

  /*!   @brief Send a optimize query to the Solr server
              
      \n
      
      @return returns the XML resultset with the status of this request
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function optimize() { return $this->sendContent("<optimize />"); }

  /*!   @brief Remove all records in the Solr index
              
      \n
      
      @return returns the XML resultset with the status of this request
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function flushIndex() { return $this->sendContent("<delete><query>*:*</query></delete>"); }

  /*!   @brief Remove all records in the Solr index, belonging to a specific dataset
              
      \n
      
      @param[in] $dataset Dataset to remove
      
      @return returns the XML resultset with the status of this request
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function flushDataset($dataset)
    { return $this->sendContent("<delete><query>dataset:\"$dataset\"</query></delete>"); }

  /*!   @brief Send any kind of query to the Solr server.
              
      \n
      
      @param[in] $query Solr query to send to the server
      @return returns the XML resultset with the status of this request
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function sendQuery($query)
  {
    $ch = curl_init();

  
    $headers = array( "Content-Type: text/xml" );    

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_URL, $this->selectUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    $data = curl_exec($ch);

    if(curl_errno($ch))
    {
      return FALSE;
    }
    else
    {
      return ($data);
    }
  }

/*!   @brief Create a Solr element to add to the index from a web service XML element (the XML representation of a RDF resource of the web services)
            
    \n
    
    @param[in] $wsElement Web service element to convert
    
    @return returns an array of Solr document to index
  
    @todo "object_property" and "object_label" have to be added once everything is indexed.
  
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
  public function createSolrAddElementFromWSElement($wsElement)
  {
    $xml = new ProcessorXML();
    $xml->loadXML($wsElement);

    $subjects = $xml->getSubjects();

    $adds = array();

    include_once("ontologies/classHierarchySerialized.php");

    foreach($subjects as $subject)
    {
      $types = array();
      $subjectURI = $xml->getURI($subject);
      $subjectType = @$xml->getType($subject);

      array_push($types, get_label_uri($subjectType));

      $add = "<add><doc><field name=\"uri\">" . get_label_uri($subjectURI) . "</field>";

      if($subjectType != "")
      {
        $add .= "<field name=\"type\">" . get_label_uri($subjectType) . "</field>";
      }

      $predicates = $xml->getPredicates($subject);

      foreach($predicates as $predicate)
      {
        $objects = $xml->getObjects($predicate);

        foreach($objects as $object)
        {
          @$objectType = $xml->getType($object);
          $predicateType = $xml->getType($predicate);

          if($objectType == "rdfs:Literal")
          {
            $objectValue = $xml->getContent($object);

            $add .= "<field name=\"property\">" . get_label_uri($predicateType) . "</field>";
            $add .= "<field name=\"text\">" . $this->xmlEncode($objectValue) . "</field>";
          }
        }
      }

      // Get all types by inference
      foreach($types as $type)
      {
        $superClasses = $classHierarchy->getSuperClasses($type);

        foreach($superClasses as $sc)
        {
          $add .= "<field name=\"inferred_type\">" . $this->xmlEncode($sc->name) . "</field>";
        }
      }

      $add .= "</doc></add>";

      array_push($adds, $add);
    }

    return ($adds);
  }

  /*!  @brief Create/Update a Solr document
              
      \n
      
      @param[in] $solrDocument A SolrDocument description
      
      @return returns FALSE for an internal error
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  /*
  public function createUpdateSolrDocument($solrDocument)
  {
    if($solrDocument->uri == "")
    {
      return(FALSE);
    }
    
    // If there is no "inferred_type" defined for this solrDocument, we try to find some.
    if(count($solrDocument->inferredTypes) <= 0)
    {
      include_once("ontologies/classHierarchySerialized.php");  
      
      foreach($solrDocument->types as $type)
      {
        $superClasses = $classHierarchy->getSuperClasses($type);

        foreach($superClasses as $sc)
        {
          $solrDocument->addInferredType($sc->name);
        }
      }      
    }
    
    // If there is no object_property/object_label pairs defined for this document; we try to find some.
    if(count($solrDocument->objectPropertiesLabels) <= 0)
    {
      global $dbUsername, $dbPassword, $dbDSN, $dbHost;
      global $base_url;

      include_once("WebService.php");
  
      $data_ini = parse_ini_file(WebService::$data_ini."data.ini", TRUE);    
  
      $this->db = new DB_Virtuoso($data_ini["triplestore"]["username"], $data_ini["triplestore"]["password"], $data_ini["triplestore"]["dsn"], $data_ini["triplestore"]["host"]);


      $db = new DB_Virtuoso($dbUsername, $dbPassword, $dbDSN, $dbHost);  
      
      $query = $db->build_sparql_query("select ?p ?o (str(DATATYPE(?o))) as ?otype from <".get_domain($base_url)."/data/core/> where {<".$solrDocument->uri."> ?p ?o.}", array ('p', 'o', 'otype'), FALSE);

      $resultset = $db->query($query);
      
      while(odbc_fetch_row($resultset))
      {
        $property = odbc_result($resultset, 1);
        $object = odbc_result($resultset, 2);
        $otype = odbc_result($resultset, 3);
        
        if($otype == "" && strpos($property, "http://www.w3.org/1999/02/22-rdf-syntax-ns#") === FALSE && strpos($property, "http://www.w3.org/2000/01/rdf-schema#") === FALSE && strpos($property, "http://www.w3.org/2002/07/owl#") === FALSE)
        {
          $query = $db->build_sparql_query("select ?p ?o from <".get_domain($base_url)."/data/core/> where {<$object> ?p ?o.}", array ('p', 'o'), FALSE);
      
          $resultset2 = $db->query($query);
          
          $subjectTriples = array();
          
          while(odbc_fetch_row($resultset2))
          {
            $p = odbc_result($resultset2, 1);
            $o = odbc_result($resultset2, 2);
            
            if(!isset($subjectTriples[$p]))
            {
              $subjectTriples[$p] = array();
            }
            
            array_push($subjectTriples[$p], $o);
          }
          
          unset($resultset2);
  
          $labels = "";
          foreach($labelProperties as $property)
          {
            if(isset($subjectTriples[$property]))
            {
              $labels = $subjectTriples[$property][0]." ";
            }
          }
          
          if($labels != "")
          {
            $solrDocument->addObjectPropertyLabel(array($property, $labels));
          }
          else
          {
            $solrDocument->addObjectPropertyLabel(array($property, "-"));
          }
        }
      }
      
      unset($resultset);      
      
      $db->close();
      $this->update("<add>".$solrDocument->serializeSolrDocument()."</add>");
    }    
  }  */

  /*!   @brief Encode content to be included in XML files
              
      \n
      
      @param[in] $string The content string to be encoded
      
      @return returns the encoded string
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function xmlEncode($string)
    { return str_replace(array ("\\", "&", "<", ">"), array ("%5C", "&amp;", "&lt;", "&gt;"), $string); }

  /*!   @brief Send any kind of query to the Solr server.
              
      \n
      
      @param[in] $content Solr query to send to the server
      
      @return returns the XML resultset with the status of this request
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function sendContent($content)
  {
    $ch = curl_init();

    $headers = array( "Content-Type: text/xml" );

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $this->updateUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

    $data = curl_exec($ch);

    if(curl_errno($ch))
    {
      return false;
    }
    else
    {
      if(strstr($data, '<int name="status">0</int>'))
      {
        return true;
      }
      else
      {
        return false;
      }
    }
  }
  
  /**
  * @brief Get the array of the name of all the fields that have been indexed in Solr.
  * 
  * @return Return an array of all the names of the fields defined in the Solr index.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getFieldsIndex()
  {
    if(!file_exists($this->fieldIndexFolder."solrFields.srz"))  
    {
      // Force the creation of the index if the file is not existing
      $this->updateFieldsIndex();
    }
    
    return(unserialize(file_get_contents($this->fieldIndexFolder."solrFields.srz")));
  }
  
  /**
  * @brief Force an update of the fields index using the Luke solr endpoint
  * 
  * @return Return FALSE if the index couldn't be update. TRUE otherwise.
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function updateFieldsIndex()
  {
    $ch = curl_init();

    $headers = array( "Content-Type: text/xml" );

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $this->lukeUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

    $data = curl_exec($ch);

    if(curl_errno($ch))
    {
      return FALSE;
    }
    else
    {
      $fields = array();
      
      $domResultset = new DomDocument("1.0", "utf-8");
      $domResultset->loadXML($data);

      $xpath = new DOMXPath($domResultset);
      
      $founds = $xpath->query("//*/lst[@name='fields']//lst");

      foreach($founds as $found)
      {
        array_push($fields, $found->getAttribute("name"));
      } 
      
      $fields = array_unique($fields);
      
      file_put_contents($this->fieldIndexFolder."fieldsIndex.srz", serialize($fields));
    }    
    
    return TRUE;
  }
}


/*!   @brief Description of a Solr document record

     @author Frederick Giasson, Structured Dynamics LLC.
*/

class SolrDocument
{
  /*! @brief Unique identifier of the document */
  public $uri;

  /*! @brief Types of a document */
  public $types;

  /*! @brief Inferred types of a document */
  public $inferredTypes;

  /*! @brief object property/label pairs */
  public $objectPropertiesLabels;

  /*! @brief property/text pairs */
  public $propertiesTexts;

  function __construct($uri = "", $types = array(), $inferredTypes = array(), $objectPropertiesLabels = array(),
    $propertiesTexts = array())
  {
    $this->uri = $uri;
    $this->types = $types;
    $this->inferredTypes = $inferredTypes;
    $this->objectPropertiesLabels = $objectPropertiesLabels;
    $this->propertiesTexts = $propertiesTexts;
  }

  function __destruct() { }

  /*!   @brief Add an object property/label pair
              
      \n
      
      @param[in] $propertyLabel Property/label pair to add to the solr document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function addObjectPropertyLabel($propertyLabel) { array_push($this->objectPropertiesLabels, $propertyLabel); }

  /*!   @brief Add an object property/text pair
              
      \n
      
      @param[in] $propertyText Property/text pair to add to the solr document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function addPropertyText($propertyText) { array_push($this->propertiesTexts, $propertyText); }

  /*!   @brief Add a type to the solr document
              
      \n
      
      @param[in] $type type to add to the solr document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function addType($type) { array_push($this->types, $type); }

  /*!   @brief Add an inferred type
              
      \n
      
      @param[in] $inferredType Inferred type to add to the solr document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function addInferredType($inferredType) { array_push($this->inferredTypes, $inferredType); }

  /*!   @brief Serialize this solr document to get indexed by solr.
              
      \n
      
      @return returns a serialized solr document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function serializeSolrDocument()
  {
    $serialization = "<doc>";

    $serialization .= "<field name=\"uri\">" . $this->uri . "</field>";

    foreach($this->types as $type)
    {
      $serialization .= "<field name=\"type\">" . $type . "</field>";
    }

    foreach($this->inferredTypes as $inferredType)
    {
      $serialization .= "<field name=\"inferred_type\">" . $inferredType . "</field>";
    }

    foreach($this->propertiesTexts as $propertyText)
    {
      $serialization .= "<field name=\"property\">" . $this->xmlEncode($propertyText[0]) . "</field>";
      $serialization .= "<field name=\"text\">" . $this->xmlEncode($propertyText[1]) . "</field>";
    }

    foreach($this->objectPropertiesLabels as $propertyLabel)
    {
      $serialization .= "<field name=\"object_property\">" . $this->xmlEncode($propertyLabel[0]) . "</field>";
      $serialization .= "<field name=\"object_label\">" . $this->xmlEncode($propertyLabel[1]) . "</field>";
    }

    $serialization .= "</doc>";

    return ($serialization);
  }
  
  /*!   @brief Encode content to be included in XML files
              
      \n
      
      @param[in] $string The content string to be encoded
      
      @return returns the encoded string
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function xmlEncode($string)
    { return str_replace(array ("\\", "&", "<", ">"), array ("%5C", "&amp;", "&lt;", "&gt;"), $string); }
}

//@}

?>