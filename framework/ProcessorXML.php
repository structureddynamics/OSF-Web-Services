<?php

/*! @defgroup WsFramework Framework for the Web Services */
//@{

/*! @file \ws\framework\ProcessorXML.php
   @brief This class handle the creationg and reading of a WSF Web Services internal XML data.
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
*/


/*!   @brief Manipulate structWSF internal XML resultset data resultsets
            
    \n
  
    @return returns NULL
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
class ProcessorXML
{
  private $dom;

  private $prefixes = array();

  /*!   @brief Constructor
       @details Create a new DOM document for the XML document being processed
              
      \n
      
      @return returns NULL
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct()
  {
    $this->dom = new DomDocument("1.0", "utf-8");
  }

  function __destruct() { }

  /*!   @brief Create a resultset root element
              
      \n
  
      @return returns the created resulotset element
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function createResultset()
  {
    $resultset = $this->dom->createElement("resultset");

    return ($resultset);
  }

  /*!   @brief Create a subject in the resultset
              
      \n
  
      @param[in] $type Type of the subject
      @param[in] $uri Optional URI for the subject
      
      @return returns the reference to the subject's element
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function createSubject($type, $uri = "")
  {
    $subject = $this->dom->createElement("subject");

    // The TYPE attribute
    $type_attr = $this->dom->createAttribute("type");
    $type_attr_value = $this->dom->createTextNode($type);

    $subject->appendChild($type_attr);
    $type_attr->appendChild($type_attr_value);

    // The URI attribute
    $uri_attr = $this->dom->createAttribute("uri");
    $uri_attr_value = $this->dom->createTextNode($uri);

    $subject->appendChild($uri_attr);
    $uri_attr->appendChild($uri_attr_value);

    return $subject;
  }

  /*!   @brief Create a prefix element for the resultset
              
      \n
  
      @param[in] $entity Entity prefix
      @param[in] $uri Uri that goes with the entity prefix
      
      @return returns the reference to the prefix element
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function createPrefix($entity, $uri)
  {
    $prefix = $this->dom->createElement("prefix");

    // The TYPE attribute
    $prefix_attr = $this->dom->createAttribute("entity");
    $prefix_attr_value = $this->dom->createTextNode($entity);

    $prefix->appendChild($prefix_attr);
    $prefix_attr->appendChild($prefix_attr_value);

    // The URI attribute
    $uri_attr = $this->dom->createAttribute("uri");
    $uri_attr_value = $this->dom->createTextNode($uri);

    $prefix->appendChild($uri_attr);
    $uri_attr->appendChild($uri_attr_value);

    return $prefix;
  }

  /*!   @brief Create a predicate for the resultset
              
      \n
  
      @param[in] $type Type of the predicate
      
      @return returns the reference to the predicate's element
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function createPredicate($type)
  {
    $predicate = $this->dom->createElement("predicate");

    // The TYPE attribute
    $type_attr = $this->dom->createAttribute("type");
    $type_attr_value = $this->dom->createTextNode($type);

    $predicate->appendChild($type_attr);
    $type_attr->appendChild($type_attr_value);

    return $predicate;
  }

  /*!   @brief Create an object for the resultset
              
      \n
  
      @param[in] $type Type of the object
      @param[in] $uri URI of the object
      @param[in] $label Optional label to refer to the object
      
      @return returns the reference to the predicate's element
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function createObject($type, $uri, $label = "")
  {
    $object = $this->dom->createElement("object");

    // The TYPE attribute
    if($type != "")
    {
      $type_attr = $this->dom->createAttribute("type");
      $type_attr_value = $this->dom->createTextNode($type);

      $object->appendChild($type_attr);
      $type_attr->appendChild($type_attr_value);
    }

    // The URI attribute
    $uri_attr = $this->dom->createAttribute("uri");
    $uri_attr_value = $this->dom->createTextNode($uri);

    $object->appendChild($uri_attr);
    $uri_attr->appendChild($uri_attr_value);

    if($label != "")
    {
      // The LABEL attribute
      $label_attr = $this->dom->createAttribute("label");
      $label_attr_value = $this->dom->createTextNode($label);

      $object->appendChild($label_attr);
      $label_attr->appendChild($label_attr_value);
    }

    return $object;
  }

  /*!   @brief Create an object that has a content (literal)
       @details   This kind of object are "literal objects"
              
      \n
  
      @param[in] $content Content of the literal.
      
      @return returns the reference to the object's element
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function createObjectContent($content)
  {
    $objectContent = $this->dom->createElement("object", $content);

    // The TYPE attribute
    $type_attr = $this->dom->createAttribute("type");
    $type_attr_value = $this->dom->createTextNode("rdfs:Literal");

    $objectContent->appendChild($type_attr);
    $type_attr->appendChild($type_attr_value);

    return $objectContent;
  }


  /*!   @brief Create a reification statement for the resultset
       @details   This is the way to reify a statement in the resultset
              
      \n
  
      @param[in] $type Type of the reification statement
      @param[in] $value Value of the reification statement
      
      @return returns the reference to the refification statement's element
      
      @return returns NULL
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function createReificationStatement($type, $value)
  {
    $reify = $this->dom->createElement("reify");

    // The TYPE attribute
    $type_attr = $this->dom->createAttribute("type");
    $type_attr_value = $this->dom->createTextNode($type);

    $reify->appendChild($type_attr);
    $type_attr->appendChild($type_attr_value);

    // The VALUE attribute
    $value_attr = $this->dom->createAttribute("value");
    $value_attr_value = $this->dom->createTextNode($value);

    $reify->appendChild($value_attr);
    $value_attr->appendChild($value_attr_value);

    return $reify;
  }

  /*!   @brief Save the resultset as a XML file
              
      \n
  
      @param[in] $resultset Resultset to be save as a xml document.
      
      @return returns the XML document of the resultset
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function saveXML($resultset)
  {
    $this->dom->appendChild($resultset);

    $this->dom->formatOutput = true;

    return $this->dom->saveXML();
  }

  /*!   @brief Load a XML document that is a resultset
              
      \n
  
      @param[in] $xml_doc XML document being loaded
      
      @return returns a reference to the DOM structure of the loaded XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function loadXML($xml_doc)
  {
    // Get all prefixes for this XML document.
    $prefixPos = 0;

    while(($prefixPos = stripos($xml_doc, "<prefix", $prefixPos)) !== FALSE)
    {
      $entityPosStart = stripos($xml_doc, 'entity="', $prefixPos);
      $entityPosEnd = stripos($xml_doc, '"', $entityPosStart + 9);
      $entity = substr($xml_doc, $entityPosStart + 8, ($entityPosEnd - $entityPosStart - 8));

      $uriPosStart = stripos($xml_doc, 'uri="', $entityPosEnd);
      $uriPosEnd = stripos($xml_doc, '"', $uriPosStart + 6);
      $uri = substr($xml_doc, $uriPosStart + 5, ($uriPosEnd - $uriPosStart - 5));

      $prefixPos = $uriPosEnd;

      $this->prefixes[$entity] = $uri;
    }

    // Now lets resolve all prefixes in the XML file.

    $replace = array();
    $replaceFor = array();

    foreach($this->prefixes as $prefix => $uri)
    {
      array_push($replace, "type=\"$prefix:");
      array_push($replaceFor, "type=\"$uri");
    }

    $xml_doc = str_ireplace($replace, $replaceFor, $xml_doc);

    $this->dom->loadXML($xml_doc);
  }

  /*!   @brief Create a resultset from a node reference of the current resultset.
              
      \n
  
      @param[in] $element Reference to the node from which to create the resultset
      
      @return returns the new resultset being created
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function createResultsetFromElement(&$element)
  {
    if(get_class($element) != "DOMElement ")
    {
      $dom = new DomDocument("1.0", "utf-8");
      $resultset = $dom->appendChild($dom->createElement("resultset"));

      $domNode = $dom->importNode($element, true);
      $resultset->appendChild($domNode);

      $dom->formatOutput = true;

      return ($dom);
    }

    return (NULL);
  }

  /*!   @brief Append an element to the root element of the current XML document
              
      \n
  
      @param[in] $element Reference to the element to happen to the root element of this XML document.
      
      @return returns NULL
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function appendElementToRoot(&$element)
  {
    $domNode = $this->dom->importNode($element, true);
    $this->dom->documentElement->appendChild($domNode);
  }

  function importNode(&$targetElement, $importElement)
  {
    $domNode = $this->dom->importNode($importElement, true);
    $targetElement->appendChild($domNode);
  }


  /*!   @brief Get a list of nodes of a given type
              
      \n
  
      @param[in] $type Type of the nodes you want
      
      @return returns a DOMNodeList with a reference to all nodes of that type
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getSubjectsByType($type)
  {
    $type = $this->transformPrefix($type);

    $xpath = new DOMXPath($this->dom);

    $query = '//resultset/subject[attribute::type="' . $type . '"]';

    $subjects = $xpath->query($query);

    return ($subjects);
  }

  function transformPrefix($type)
  {
    if(strpos($type, ":") !== FALSE)
    {
      foreach($this->prefixes as $entity => $uri)
      {
        if(stripos($type, $entity . ":") !== FALSE)
        {
          return (str_replace($entity . ":", $uri, $type));
        }
      }
    }

    return $type;
  }


  /*!   @brief Get al subjects of a resultset
              
      \n
  
      @return returns a DOMNodeList with a reference to all subjects of the resultset
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getSubjects()
  {
    $xpath = new DOMXPath($this->dom);

    $query = '//resultset/subject';

    $subjects = $xpath->query($query);

    return ($subjects);
  }

  /*!   @brief Get all prefixes for this document
              
      \n
  
      @return returns a DOMNodeList with a reference to all prefixes
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getPrefixes()
  {
    $xpath = new DOMXPath($this->dom);

    $query = '//resultset/prefix';

    $prefixes = $xpath->query($query);

    return ($prefixes);
  }

  function getSubjectContent(&$subject) { }


  /*!   @brief Send a XPath query to the resultset
              
      \n
  
      @param[in] $xpath The xpath query to send
      
      @return returns a DOMNodeList with a reference to all ndoes that are in that path
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getXPath($xpath)
  {
    $xpath = $this->transformPrefix($xpath);

    $xpath_path = new DOMXPath($this->dom);

    $nodes = $xpath_path->query($xpath);

    return ($nodes);
  }


  /*!   @brief Get a list of predicate nodes of a given type
                        
      \n
  
      @param[in] $subject Target subject to get its predicates from
      @param[in] $type Type of the nodes you want
      
      @return returns a DOMNodeList with a reference to all properties nodes of that type
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getPredicatesByType(&$subject, $type)
  {
    $type = $this->transformPrefix($type);

    $resultset = $this->createResultsetFromElement($subject);

    $xpath = new DOMXPath($resultset);

    $query = '//subject/predicate[attribute::type="' . $type . '"]';

    $predicates = $xpath->query($query);

    return ($predicates);
  }

  /*!   @brief Get all predicates for a given subject of the resultset
              
      \n
  
      @param[in] $subject Target subject
      
      @return returns a DOMNodeList with a reference to all predicates of the resultset, for that subject
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getPredicates(&$subject)
  {
    $resultset = $this->createResultsetFromElement($subject);

    $xpath = new DOMXPath($resultset);

    $query = '//subject/predicate';

    $predicates = $xpath->query($query);

    return ($predicates);
  }

  /*!   @brief Get all objects for a given predicate of the resultset for a given type
              
      \n
  
      @param[in] $predicate Target predicate
      @param[in] $type Target type
      
      @return returns a DOMNodeList with a reference to all objects of the resultset, for that predicate, for that type
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getObjectsByType(&$predicate, $type)
  {
    $type = $this->transformPrefix($type);

    $resultset = $this->createResultsetFromElement($predicate);

    $xpath = new DOMXPath($resultset);

    $query = '//predicate/object[attribute::type="' . $type . '"]';

    $objects = $xpath->query($query);

    return ($objects);
  }

  /*!   @brief Get all objects for a given predicate of the resultset
              
      \n
  
      @param[in] $predicate Target predicate
      
      @return returns a DOMNodeList with a reference to all objects of the resultset, for that predicate
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getObjects(&$predicate)
  {
    $resultset = $this->createResultsetFromElement($predicate);

    $xpath = new DOMXPath($resultset);

    $query = '//predicate/object';

    $objects = $xpath->query($query);

    return ($objects);
  }

  /*!   @brief Get the reification statement of a triple by its reification type
              
      \n
  
      @param[in] $object the reference object node
      @param[in] $type URI of the type of the reification statement
      
      @return returns reification node
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getReificationStatementsByType(&$object, $type)
  {
    $type = $this->transformPrefix($type);

    $resultset = $this->createResultsetFromElement($object);

    $xpath = new DOMXPath($resultset);

    $query = '//object/reify[attribute::type="' . $type . '"]';

    $reifies = $xpath->query($query);

    return ($reifies);
  }

  /*!   @brief Get the reification statement of a triple
              
      \n
  
      @param[in] $object the reference object node
      
      @return returns reification node
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getReificationStatements(&$object)
  {
    $resultset = $this->createResultsetFromElement($object);

    $xpath = new DOMXPath($resultset);

    $query = '//object/reify';

    $reifies = $xpath->query($query);

    return ($reifies);
  }

  /*!   @brief Get URI of a node of the resulset
              
      \n
  
      @param[in] $node the reference node
      
      @return returns the URI
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getURI(&$node)
  {
    if(isset($node->attributes))
    {
      return ($node->attributes->getNamedItem("uri")->nodeValue);
    }
    else
    {
      return "";
    }
  }

  /*!   @brief Get the Entity of a prefix element
              
      \n
  
      @param[in] $node the reference node
      
      @return returns the Entity
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getEntity(&$node)
  {
    if(isset($node->attributes))
    {
      return ($node->attributes->getNamedItem("entity")->nodeValue);
    }
    else
    {
      return "";
    }
  }


  /*!   @brief Get Label of a node of the resulset
              
      \n
  
      @param[in] $node the reference node
      
      @return returns the Label
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getLabel(&$node)
  {
    if(isset($node->attributes))
    {
      return ($node->attributes->getNamedItem("label")->nodeValue);
    }
    else
    {
      return "";
    }
  }

  /*!   @brief Get value of a node of the resulset
              
      \n
  
      @param[in] $node the reference node
      
      @return returns the value
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getValue(&$node)
  {
    if(isset($node->attributes))
    {
      return ($node->attributes->getNamedItem("value")->nodeValue);
    }
    else
    {
      return "";
    }
  }

  /*!   @brief Get type of a node of the resulset
              
      \n
  
      @param[in] $node the reference node
      @param[in] $prefixed Convert to prefixed form if a prefix exists for this type.
      
      @return returns the type
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getType(&$node, $prefixed = TRUE)
  {
    if(isset($node->attributes))
    {
      if($prefixed === TRUE)
      {
        foreach($this->prefixes as $entity => $uri)
        {
          if(stripos($node->attributes->getNamedItem("type")->nodeValue, $uri) !== FALSE)
          {
            return (str_replace($uri, $entity . ":", $node->attributes->getNamedItem("type")->nodeValue));
          }
        }
      }

      return ($node->attributes->getNamedItem("type")->nodeValue);
    }
    else
    {
      return "";
    }
  }

  /*!   @brief Get content of a node of the resulset
              
      \n
  
      @param[in] $node the reference node
      
      @return returns the content
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function getContent(&$node)
  {
    if(isset($node->attributes))
    {
      return ($node->nodeValue);
    }

    return ("");
  }
}

//@}

?>