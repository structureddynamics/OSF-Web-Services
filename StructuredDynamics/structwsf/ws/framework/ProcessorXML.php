<?php

/** @defgroup WsFramework Framework for the Web Services */
//@{

/*! @file \StructuredDynamics\structwsf\ws\framework\ProcessorXML.php
    @brief This class handle the creationg and reading of a WSF Web Services internal XML data.
*/

namespace StructuredDynamics\structwsf\ws\framework; 

use \DOMDocument;
use \DOMXPath;

/** Manipulate structWSF internal XML resultset data resultsets

    @return returns NULL

    @author Frederick Giasson, Structured Dynamics LLC.
*/
class ProcessorXML
{
  private $dom;

  private $prefixes = array();

  /** Constructor

      @return returns NULL

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct()
  {
    $this->dom = new DomDocument("1.0", "utf-8");
  }

  function __destruct(){}

  /** Create a resultset root element

      @return returns the created resulotset element

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function createResultset()
  {
    $resultset = $this->dom->createElement("resultset");

    return ($resultset);
  }

  /** Create a subject in the resultset

      @param $type Type of the subject
      @param $uri Optional URI for the subject

      @return returns the reference to the subject's element

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function createSubject($type, $uri = "")
  {
    $subject = $this->dom->createElement("subject");

    // The TYPE attribute
    $type_attr = $this->dom->createAttribute("type");
    $type_attr_value = $this->dom->createTextNode($this->xmlEncode($type));

    $subject->appendChild($type_attr);
    $type_attr->appendChild($type_attr_value);

    // The URI attribute
    $uri_attr = $this->dom->createAttribute("uri");
    $uri_attr_value = $this->dom->createTextNode($this->xmlEncode($uri));

    $subject->appendChild($uri_attr);
    $uri_attr->appendChild($uri_attr_value);

    return $subject;
  }

  /** Create a prefix element for the resultset

      @param $entity Entity prefix
      @param $uri Uri that goes with the entity prefix

      @return returns the reference to the prefix element

      @author Frederick Giasson, Structured Dynamics LLC.
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

  /** Create a predicate for the resultset

      @param $type Type of the predicate

      @return returns the reference to the predicate's element

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function createPredicate($type)
  {
    $predicate = $this->dom->createElement("predicate");

    // The TYPE attribute
    $type_attr = $this->dom->createAttribute("type");
    $type_attr_value = $this->dom->createTextNode($this->xmlEncode($type));

    $predicate->appendChild($type_attr);
    $type_attr->appendChild($type_attr_value);

    return $predicate;
  }

  /** Create an object for the resultset

      @param $type Type of the object
      @param $uri URI of the object
      @param $label Optional label to refer to the object

      @return returns the reference to the predicate's element

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function createObject($type, $uri, $label = "")
  {
    $object = $this->dom->createElement("object");

    // The TYPE attribute
    if ($type != "")
    {
      $type_attr = $this->dom->createAttribute("type");
      $type_attr_value = $this->dom->createTextNode($this->xmlEncode($type));

      $object->appendChild($type_attr);
      $type_attr->appendChild($type_attr_value);
    }

    // The URI attribute
    $uri_attr = $this->dom->createAttribute("uri");
    $uri_attr_value = $this->dom->createTextNode($this->xmlEncode($uri));

    $object->appendChild($uri_attr);
    $uri_attr->appendChild($uri_attr_value);

    if ($label != "")
    {
      // The LABEL attribute
      $label_attr = $this->dom->createAttribute("label");
      $label_attr_value = $this->dom->createTextNode($this->xmlEncode($label));

      $object->appendChild($label_attr);
      $label_attr->appendChild($label_attr_value);
    }

    return $object;
  }

  /** Create an object that has a content (literal)

      @param $content Content of the literal.

      @return returns the reference to the object's element

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function createObjectContent($content)
  {
    $objectContent = $this->dom->createElement("object", $this->xmlEncode($content));

    // The TYPE attribute
    $type_attr = $this->dom->createAttribute("type");
    $type_attr_value = $this->dom->createTextNode("rdfs:Literal");

    $objectContent->appendChild($type_attr);
    $type_attr->appendChild($type_attr_value);

    return $objectContent;
  }


  /** Create a reification statement for the resultset

      @param $type Type of the reification statement
      @param $value Value of the reification statement

      @return returns the reference to the refification statement's element

      @return returns NULL

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function createReificationStatement($type, $value)
  {
    $reify = $this->dom->createElement("reify");

    // The TYPE attribute
    $type_attr = $this->dom->createAttribute("type");
    $type_attr_value = $this->dom->createTextNode($this->xmlEncode($type));

    $reify->appendChild($type_attr);
    $type_attr->appendChild($type_attr_value);

    // The VALUE attribute
    $value_attr = $this->dom->createAttribute("value");
    $value_attr_value = $this->dom->createTextNode($this->xmlEncode($value));

    $reify->appendChild($value_attr);
    $value_attr->appendChild($value_attr_value);

    return $reify;
  }

  /** Save the resultset as a XML file

      @param $resultset Resultset to be save as a xml document.

      @return returns the XML document of the resultset

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function saveXML($resultset)
  {
    $this->dom->appendChild($resultset);

    $this->dom->formatOutput = true;

    return $this->dom->saveXML();
  }

  /** Load a XML document that is a resultset

      @param $xml_doc XML document being loaded

      @return returns a reference to the DOM structure of the loaded XML document

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function loadXML($xml_doc)
  {
    // Get all prefixes for this XML document.
    $prefixPos = 0;

    while (($prefixPos = strpos($xml_doc, "<prefix", $prefixPos)) !== FALSE)
    {
      $entityPosStart = strpos($xml_doc, 'entity="', $prefixPos);
      $entityPosEnd = strpos($xml_doc, '"', $entityPosStart + 9);
      $entity = substr($xml_doc, $entityPosStart + 8, ($entityPosEnd - $entityPosStart - 8));

      $uriPosStart = strpos($xml_doc, 'uri="', $entityPosEnd);
      $uriPosEnd = strpos($xml_doc, '"', $uriPosStart + 6);
      $uri = substr($xml_doc, $uriPosStart + 5, ($uriPosEnd - $uriPosStart - 5));

      $prefixPos = $uriPosEnd;

      $this->prefixes[$entity] = $uri;
    }

    // Now lets resolve all prefixes in the XML file.

    $replace = array();
    $replaceFor = array();

    foreach ($this->prefixes as $prefix => $uri)
    {
      array_push($replace, "type=\"$prefix:");
      array_push($replaceFor, "type=\"$uri");
    }

    $xml_doc = str_ireplace($replace, $replaceFor, $xml_doc);

    $this->dom->loadXML($xml_doc);
  }

  /** Create a resultset from a node reference of the current resultset.

      @param $element Reference to the node from which to create the resultset

      @return returns the new resultset being created

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function createResultsetFromElement(&$element)
  {
    if(get_class($element) != 'DOMElement')
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
  /** Append an element to the root element of the current XML document

      @param $element Reference to the element to happen to the root element of this XML document.

      @return returns NULL

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function appendElementToRoot(&$element)
  {
    $domNode = $this->dom->importNode($element, true);
    $this->dom->documentElement->appendChild($domNode);
  }

  function importNode(&$targetElement, $importElement)
  {
    if(($targetElement != null && (get_class($targetElement) == 'DOMNode' ||
                                   get_class($targetElement) == 'DOMElement')) && 
       ($importElement != null && (get_class($importElement) == 'DOMNode' ||
                                   get_class($importElement) == 'DOMElement'))) 
    {
      $domNode = $this->dom->importNode($importElement, true);
      $targetElement->appendChild($domNode);
    }
  }


  /** Get a list of nodes of a given type

      @param $type Type of the nodes you want

      @return returns a DOMNodeList with a reference to all nodes of that type

      @author Frederick Giasson, Structured Dynamics LLC.
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
    if (strpos($type, ":") !== FALSE)
    {
      foreach ($this->prefixes as $entity => $uri)
      {
        if (stripos($type, $entity . ":") !== FALSE)
        {
          return (str_replace($entity . ":", $uri, $type));
        }
      }
    }

    return $type;
  }


  /** Get al subjects of a resultset

      @return returns a DOMNodeList with a reference to all subjects of the resultset

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getSubjects()
  {
    $xpath = new DOMXPath($this->dom);

    $query = '//resultset/subject';

    $subjects = $xpath->query($query);

    return($subjects);
  }

  /** Get all prefixes for this document

      @return returns a DOMNodeList with a reference to all prefixes

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getPrefixes()
  {
    $xpath = new DOMXPath($this->dom);

    $query = '//resultset/prefix';

    $prefixes = $xpath->query($query);

    return ($prefixes);
  }

  function getSubjectContent(&$subject){}


  /** Send a XPath query to the resultset

      @param $xpath The xpath query to send

      @return returns a DOMNodeList with a reference to all ndoes that are in that path

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getXPath($xpath)
  {
    $xpath = $this->transformPrefix($xpath);

    $xpath_path = new DOMXPath($this->dom);

    $nodes = $xpath_path->query($xpath);

    return ($nodes);
  }


  /** Get a list of predicate nodes of a given type

      @param $subject Target subject to get its predicates from
      @param $type Type of the nodes you want

      @return returns a DOMNodeList with a reference to all properties nodes of that type

      @author Frederick Giasson, Structured Dynamics LLC.
  */     
  function getPredicatesByType(&$subject, $type)
  {
    if($subject != null && (get_class($subject) == 'DOMNode' ||
                            get_class($subject) == 'DOMElement')) 
    {
      $type = $this->transformPrefix($type);

      $xpath = new DOMXPath($this->dom);

      $query = './/predicate[attribute::type="' . $type . '"]';

      $predicates = $xpath->query($query, $subject);

      return($predicates);
    }
    
    return(new DOMNodeList()); 
  }
  
  /** Get all predicates for a given subject of the resultset

      @param $subject Target subject

      @return returns a DOMNodeList with a reference to all predicates of the resultset, for that subject

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getPredicates(&$subject)
  {
    if($subject != null && (get_class($subject) == 'DOMNode' ||
                            get_class($subject) == 'DOMElement')) 
    {
      $xpath = new DOMXPath($this->dom);

      $query = './/predicate';

      $predicates = $xpath->query($query, $subject);

      return ($predicates);
    }
    
    return(new DOMNodeList()); 
  }

  /** Get all objects for a given predicate of the resultset for a given type

      @param $predicate Target predicate
      @param $type Target type

      @return returns a DOMNodeList with a reference to all objects of the resultset, for that predicate, for that type

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getObjectsByType(&$predicate, $type)
  {
    if($predicate != null && (get_class($predicate) == 'DOMNode' ||
                              get_class($predicate) == 'DOMElement')) 
    {
      $type = $this->transformPrefix($type);

      $xpath = new DOMXPath($this->dom);

      $query = './/object[attribute::type="' . $type . '"]';

      $objects = $xpath->query($query, $predicate);

      return ($objects);
    }
    else
    {
      return(new DOMNodeList()); 
    }
  }

  /** Get all objects for a given predicate of the resultset

      @param $predicate Target predicate

      @return returns a DOMNodeList with a reference to all objects of the resultset, for that predicate

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getObjects(&$predicate)
  {
    if($predicate != null && (get_class($predicate) == 'DOMNode' ||
                              get_class($predicate) == 'DOMElement')) 
    {
      $xpath = new DOMXPath($this->dom);

      $query = './/object';

      $objects = $xpath->query($query, $predicate);

      return ($objects);
    }
    
    return(new DOMNodeList()); 
  }

  /** Get the reification statement of a triple by its reification type

      @param $object the reference object node
      @param $type URI of the type of the reification statement

      @return returns reification node

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getReificationStatementsByType(&$object, $type)
  {
    if($object != null && (get_class($object) == 'DOMNode' ||
                           get_class($object) == 'DOMElement')) 
    {
      $type = $this->transformPrefix($type);

      $xpath = new DOMXPath($this->dom);

      $query = './/reify[attribute::type="' . $type . '"]';

      $reifies = $xpath->query($query, $object);

      return ($reifies);
    }
    
    return(new DOMNodeList()); 
  }

  /** Get the reification statement of a triple

      @param $object the reference object node

      @return returns reification node

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getReificationStatements(&$object)
  {
    if($object != null && (get_class($object) == 'DOMNode' ||
                           get_class($object) == 'DOMElement')) 
    {
      $xpath = new DOMXPath($this->dom);
    
      $query = './/reify';
      
      $reifies = $xpath->query($query, $object);
      
      return ($reifies);
    }
    
    return(new DOMNodeList()); 
  }

  /** Get URI of a node of the resulset

      @param $node the reference node

      @return returns the URI

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getURI(&$node)
  {
    if($node != null && (get_class($node) == 'DOMNode' ||
                         get_class($node) == 'DOMElement')) 
    {
      if (isset($node->attributes))
      {
        return ($node->attributes->getNamedItem("uri")->nodeValue);
      }
    }
    
    return "";
  }

  /** Get the Entity of a prefix element

      @param $node the reference node

      @return returns the Entity

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getEntity(&$node)
  {
    if($node != null && (get_class($node) == 'DOMNode' ||
                         get_class($node) == 'DOMElement')) 
    {    
      if(isset($node->attributes))
      {
        return ($node->attributes->getNamedItem("entity")->nodeValue);
      }
    }
    return "";
  }


  /** Get Label of a node of the resulset

      @param $node the reference node

      @return returns the Label

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getLabel(&$node)
  {
    if($node != null && (get_class($node) == 'DOMNode' ||
                         get_class($node) == 'DOMElement')) 
    {    
      if (isset($node->attributes))
      {
        return ($node->attributes->getNamedItem("label")->nodeValue);
      }
    }
    
    return "";
  }

  /** Get value of a node of the resulset

      @param $node the reference node

      @return returns the value

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getValue(&$node)
  {
    if($node != null && (get_class($node) == 'DOMNode' ||
                         get_class($node) == 'DOMElement')) 
    {
      if (isset($node->attributes))
      {
        return ($node->attributes->getNamedItem("value")->nodeValue);
      }
    }
    
    return "";
  }

  /** Get type of a node of the resulset

      @param $node the reference node
      @param $prefixed Convert to prefixed form if a prefix exists for this type.

      @return returns the type

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getType(&$node, $prefixed = TRUE)
  {
    if($node != null && (get_class($node) == 'DOMNode' ||
                         get_class($node) == 'DOMElement')) 
    {
      if(isset($node->attributes))
      {
        if($prefixed === TRUE)
        {
          foreach($this->prefixes as $entity => $uri)
          {
            if(isset($node->attributes->getNamedItem("type")->nodeValue))
            {
              if(strpos($node->attributes->getNamedItem("type")->nodeValue, $uri) !== FALSE)
              {
                return(str_replace($uri, $entity . ":", $node->attributes->getNamedItem("type")->nodeValue));
              }
            }
          }
        }

        if(isset($node->attributes->getNamedItem("type")->nodeValue))
        {        
          return ($node->attributes->getNamedItem("type")->nodeValue);
        }
      }
    }

    return "";
  }

  /** Get content of a node of the resulset

      @param $node the reference node

      @return returns the content

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getContent(&$node)
  {
    if($node != null && (get_class($node) == 'DOMNode' ||
                         get_class($node) == 'DOMElement')) 
    {    
      if(isset($node->attributes))
      {    
        return($node->nodeValue);
      }
    }
    
    return("");
  }
  
  /**
   * Helper function that removes all child nodes of $node recursively
   * (i.e. including child nodes of the child nodes and so forth).
   */
  function removeChildren(&$node) 
  {
    while ($node->firstChild) 
    {
      while ($node->firstChild->firstChild) 
      {
        $this->removeChildren($node->firstChild);
      }
      
      $node->removeChild($node->firstChild);
    }
  }  
  
  /** Remove all the predicates assertions for a given subject.

      @param $subject Target subject for which to remove the predicate assertions
      @param $type URI of the predicate to remove.

      @return returns Nothing

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function removePredicatesByType(&$subject, $type)
  {
    $type = $this->transformPrefix($type);

    $xpath = new DOMXPath($this->dom);

    $query = './/predicate[attribute::type="' . $type . '"]';

    $predicates = $xpath->query($query, $subject);
    
    foreach($predicates as $predicate)
    {
      $predicate->parentNode->removeChild($predicate);
    }
  }  
  
 /** Encode content to be included in XML files

      @param $string The content string to be encoded
      
      @return returns the encoded string
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function xmlEncode($string)
  { 
    // Replace all the possible entities by their character. That way, we won't "double encode" 
    // these entities. Otherwise, we can endup with things such as "&amp;amp;" which some
    // XML parsers doesn't seem to like (and throws errors).
    //$string = str_replace(array ("%5C", "&amp;", "&lt;", "&gt;"), array ("\\", "&", "<", ">"), $string);
    
    return str_replace(array ("&", "<", ">"), array ("&amp;", "&lt;", "&gt;"), $string); 
  } 
  
  function xmlDecode($string)
  { 
    return str_replace(array ("&amp;", "&lt;", "&gt;"), array ("&", "<", ">"), $string); 
  }    
  
  function saveRdfN3()
  {
    $rdf_part = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>\n\n";
    $rdf_reification = "";
    
    $subjects = $this->getSubjects();

    foreach($subjects as $subject)
    {
      $subjectURI = $this->getURI($subject);
      $subjectType = $this->getType($subject, FALSE);

      $rdf_part .= "<$subjectURI> a <$subjectType> ;\n";

      $predicates = $this->getPredicates($subject);

      foreach($predicates as $predicate)
      {
        $objects = $this->getObjects($predicate);

        foreach($objects as $object)
        {
          $objectType = $this->getType($object);
          $predicateType = $this->getType($predicate, FALSE);

          if($objectType == "rdfs:Literal")
          {
            $objectValue = $this->getContent($object);
            $rdf_part .= "        <$predicateType> \"" . $this->escapeN3($objectValue) . "\" ;\n";
          }
          else
          {
            $objectURI = $this->getURI($object);
            $rdf_part .= "        <$predicateType> <$objectURI> ;\n";
          }
          
          
          // Check for reified statements.
          $reifies = $this->getReificationStatements($object);

          foreach($reifies as $reify)
          {
            $reifyPredicate = $this->getType($reify, FALSE);

            $rdf_reification .= "_:" . md5($this->getURI($subject) . $predicateType . $this->getURI($object))
              . " a rdf:Statement ;\n";
            $rdf_reification .= "    rdf:subject <" . $this->getURI($subject) . "> ;\n";
            $rdf_reification .= "    rdf:predicate <" . $predicateType . "> ;\n";
            $rdf_reification .= "    rdf:object <" . $this->getURI($object) . "> ;\n";
            $rdf_reification .= "    <$reifyPredicate> \"" . $this->escapeN3($this->getValue($reify)) . "\" .\n\n";
          }          
        }
      }

      if(strlen($rdf_part) > 0)
      {
        $rdf_part = substr($rdf_part, 0, strlen($rdf_part) - 2) . ". \n";
      }
    }
    
    return($rdf_part.$rdf_reification);    
  } 
  
  private function escapeN3($literal)
  {
    $literal = str_replace("\\","\\\\", $literal);

    return str_replace(array('"', "'"), array('\\"', "\\'"), $literal);
  }
}

//@}

?>