<?php

/*! @ingroup StructWSFPHPAPIFramework Framework of the structWSF PHP API library */
//@{

/*! @file \StructuredDynamics\structwsf\framework\Resultset.php
    @brief An internal Resultset class

*/


namespace StructuredDynamics\structwsf\framework;

use \SimpleXMLElement;

use \StructuredDynamics\structwsf\framework\Namespaces;
use \StructuredDynamics\structwsf\ws\converter\irjson\ConverterIrJSON;
use \StructuredDynamics\structwsf\ws\converter\irjson\Dataset;
use \StructuredDynamics\structwsf\ws\converter\irjson\InstanceRecord;
use \StructuredDynamics\structwsf\ws\converter\irjson\irJSONParser;
use \StructuredDynamics\structwsf\ws\converter\irjson\LinkageSchema;
use \StructuredDynamics\structwsf\ws\converter\irjson\StructureSchema;
use \StructuredDynamics\structwsf\ws\converter\common\CommonParser;
use \StructuredDynamics\structwsf\ws\converter\common\ConverterCommON;


/** 
* Internal Resultset representation of structWSF results
* 
* @author Frederick Giasson, Structured Dynamics LLC.
*/

class Resultset
{
  /** 
  *    @page internalResultsetStructures Internal Resultset Structures used in structWSF web services.
  *
  *    @section theInternalStructure The Structure
  *
  *    Every structWSF web service endpoint that returns a resultset uses this internal array structure
  *    to represent the resultset to serialize and return to the user. This structure is used to
  *    generate the resultset using the structXML format. It is this structXML serialization that is used
  *    to exchange data between structWSF web services in a pipeline of web services, or used to convert
  *    the data into different other serializations.
  *
  *    Each web service interface should comply with this internal resultset array structure.
  *
  *    @verbatim
  *
  *    $resultset =   Array("dataset-uri" => 
  *                     Array("record-uri" =>
  *                      Array(
  *                        "type" => Array(URIs...),
  *                        "prefLabel" => "preferred label",
  *                        "altLabel" => Array(alternative label literals...),
  *                        "prefURL" => "http://preferred-url.com",
  *                        "description" => "some description of the record",
  *                        
  *                        "other-data-attribute-uri" => Array(
  *                          Array(
  *                            "value" => "some value",
  *                            "lang" => "language string of the value",
  *                            "type" => "type of the value"
  *                          ),
  *                          Array(
  *                            ...
  *                          )
  *                        ),
  *                        "more-data-attribute-uri": ...,
  *                        
  *                        "other-object-attribute-uri" => Array(
  *                          Array(
  *                            "uri" => "some uri",
  *                            "type" => "optional type of the referenced URI",
  *                            "reify" => Array(
  *                              "reification-attribute-uri" => Array("value of the reification statement"),
  *                              "more-reification-attribute-uri" => ...
  *                            )
  *                          ),
  *                          Array(
  *                            ...
  *                          )
  *                        )
  *                        "more-object-attribute-uri": ...
  *                      ),
  *                      
  *                      "more-record-uri": ...
  *                    )
  *                  )
  *        
  *    @endverbatim
  *  
  */    
  private $resultset = array();
  
  /** Prefixes used by the resultset */
  private $prefixes = array(
                        "http://www.w3.org/2002/07/owl#" => "owl",
                        "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
                        "http://www.w3.org/2000/01/rdf-schema#" => "rdfs",
                        "http://purl.org/ontology/iron#" => "iron",
                        "http://www.w3.org/2001/XMLSchema#" => "xsd",
                        "http://purl.org/ontology/wsf#" => "wsf"
                      ); 
                      
  /** Number of new prefixes that have been added to the list of prefixes */
  private $newPrefixesCounter = 0;                       
  
  /** Folder where the structWSF instance is installed */
  private $wsf_base_path = "/usr/share/structwsf";
  
  /** Namespaces object instance */
  private $namespaces;

  /**
  * Constructor
  *    
  * @param mixed $wsf_base_path Path where the structWSF instance is installed on the server
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($wsf_base_path = "/usr/share/structwsf/") 
  { 
    $this->namespaces = new Namespaces();
    
    $this->wsf_base_path = rtrim($wsf_base_path, "/")."/";
  }

  function __destruct() { }
  
  /**
  * Add a Subject object to the resultset
  * 
  * @param mixed $subject Subject object to add to the resultset
  * @param mixed $dataset Dataset URI where to add the subject
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function addSubject($subject, $dataset = "")
  {
    if(get_class($subject) == 'Subject')
    {
      if($dataset == "")
      {
        $dataset = "unspecified";
      }
      
      $uri = $subject->getUri();
      
      if(!isset($this->resultset[$dataset][$uri]))
      {
        $this->resultset[$dataset][$uri] = $subject->getSubject();
        
        return(TRUE);
      }
      else
      {
        return(FALSE);
      }
    }
    else
    {
      return(FALSE);
    }
  }
  
  /**
  * Get all the subjects defined in this resultset.
  * 
  * @return An array of Subject objects
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSubjects()
  {
    $subjects = array();
    
    foreach($this->resultset as $dataset => $subjects)
    {
      foreach($subjects as $uri => $s)
      {
        $subject = new Subject($uri);
        
        $subject->setSubject($s);
        
        array_push($subjects, $subject);
      }      
    }
    
    return($subjects);
  }  
  
  /**
  * Get a subject by its URI
  * 
  * @param mixed $uri URI of the subject to get from the resultset.
  * @return Subject instance that match the input URI. Returns FALSE if no subject
  *         match the input URI.
  */
  public function getSubjectByUri($uri)
  {
    foreach($this->resultset as $dataset => $subjects)
    {
      foreach($subjects as $suri => $s)
      {
        if($suri == $uri)
        {
          $subject = new Subject($uri);
          $subject->setSubject($s);
          
          return($subject);
        }
      }
    }
    
    return(FALSE);
  }
  
  /**
  * Get the array internal description of the resultset 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getResultset()
  {
    return($this->resultset);
  }
  
  /**
  * Import an array that describes the resultset to use with this Resultset object
  * 
  * The input resultset array has to be of format:
  * 
  * Array("record-uri" =>
  *                    Array(
  *                      "type" => Array(URIs...),
  *                      "prefLabel" => "preferred label",
  *                      "altLabel" => Array(alternative label literals...),
  *                      "prefURL" => "http://preferred-url.com",
  *                      "description" => "some description of the record",
  *                      
  *                      "other-data-attribute-uri" => Array(
  *                        Array(
  *                          "value" => "some value",
  *                          "lang" => "language string of the value",
  *                          "type" => "type of the value"
  *                        ),
  *                        Array(
  *                          ...
  *                        )
  *                      ),
  *                      "more-data-attribute-uri": ...,
  *                      
  *                      "other-object-attribute-uri" => Array(
  *                        Array(
  *                          "uri" => "some uri",
  *                          "type" => "optional type of the referenced URI",
  *                          "reify" => Array(
  *                            "reification-attribute-uri" => Array("value of the reification statement"),
  *                            "more-reification-attribute-uri" => ...
  *                          )
  *                        ),
  *                        Array(
  *                          ...
  *                        )
  *                      )
  *                      "more-object-attribute-uri": ...
  *                    ),
  *                    
  *                    "more-record-uri": ...
  *                  )
  * 
  * @param mixed $resultset Resultset array to use in this Resultset object
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setResultset($resultset)
  {
    $this->resultset = $resultset;
  }  

  /**
  * Import a structXML resultset
  * 
  * @param mixed $structXMLResultset structXML resultset to import
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function importStructXMLResultset($structXMLResultset)
  {
    $xml = new SimpleXMLElement($structXMLResultset);
    
    /*
     $resultset =   Array("dataset-uri" => 
                       Array("record-uri" =>
                        Array(
                          "type" => Array(URIs...),
                          "prefLabel" => "preferred label",
                          "altLabel" => Array(alternative label literals...),
                          "prefURL" => "http://preferred-url.com",
                          "description" => "some description of the record",
                          
                          "other-data-attribute-uri" => Array(
                            Array(
                              "value" => "some value",
                              "lang" => "language string of the value",
                              "type" => "type of the value"
                            ),
                            Array(
                              ...
                            )
                          ),
                          "more-data-attribute-uri": ...,
                          
                          "other-object-attribute-uri" => Array(
                            Array(
                              "uri" => "some uri",
                              "type" => "optional type of the referenced URI",
                              "reify" => Array(
                                "reification-attribute-uri" => Array("value of the reification statement"),
                                "more-reification-attribute-uri" => ...
                              )
                            ),
                            Array(
                              ...
                            )
                          )
                          "more-object-attribute-uri": ...
                        ),
                        
                        "more-record-uri": ...
                      )
                    )    
    */
    
    $resultset = array();
    
    foreach($xml->prefix as $prefix)
    {
      if(!isset($this->prefixes[(string) $prefix["uri"]]))
      {
        $this->prefixes[(string) $prefix["uri"]] = (string) $prefix["entity"];
      }
    }
    
    foreach($xml->subject as $s)
    {
      $type = $this->unprefixize((string) $s["type"]);
      $uri = $this->unprefixize((string) $s["uri"]);

      $subject = array(
        "type" => array($type)      
      );
      
      foreach($s->predicate as $predicate)
      {
        $predicateType = $this->unprefixize((string) $predicate["type"]);
        
        foreach($predicate->object as $object)
        {
          //$objectType = $this->unprefixize((string) $object["type"]);
          
          // check if object property
          if(isset($object["uri"]))
          {
            if($predicateType == Namespaces::$rdf."type")   
            {
              array_push($subject["type"], (string) $object["uri"]);
              continue;
            }
            
            if(!isset($subject[$predicateType]))
            {
              $subject[$predicateType] = array();
            }
            
            $value = array();
            
            if(isset($object["type"]))
            {
              $value["type"] = $this->unprefixize((string) $object["type"]);
            }
            
            if(isset($object["uri"]))
            {
              $value["uri"] = $this->unprefixize((string) $object["uri"]);
            }
            
            // Add possible reification statement.
            
            if(isset($object->reify))
            {
              $reifyType = $this->unprefixize((string) $object->reify["type"]);
              $reifyValue = $this->unprefixize((string) $object->reify["value"]);
              
              $value["reify"][$reifyType] =  array($reifyValue);
            }
            
            array_push($subject[$predicateType], $value);
          }
          else
          {           
            if(!isset($subject[$predicateType]))
            {
              $subject[$predicateType] = array();
            }
            
            $value = array();
            
            if(isset($object["type"]))
            {
              $value["type"] = $this->unprefixize((string) $object["type"]);
            }
            
            if(isset($object["lang"]))
            {
              $value["lang"] = (string) $object["lang"];
            }

            $value["value"] = (string) $object;
            
            // Add possible reification statement.
            
            if(isset($object->reify))
            {
              $reifyType = $this->unprefixize((string) $object->reify["type"]);
              $reifyValue = $this->unprefixize((string) $object->reify["value"]);
              
              $value["reify"][$reifyType] =  array($reifyValue);
            }
            
            array_push($subject[$predicateType], $value);            
          }          
        }
      }
      
      // Try to find a prefLabel
      $prefLabelProperty = "";
      
      foreach(Namespaces::getLabelProperties() as $labelProperty)
      {
        if(isset($subject[$labelProperty]))        
        {
          $subject["prefLabel"] = $subject[$labelProperty][0]["value"];
          
          $prefLabelProperty = $labelProperty;
          
          if($labelProperty == Namespaces::$iron."prefLabel")
          {
            unset($subject[$labelProperty]);
          }
          
          break;
        }
      }
      
      // Try to find alternative labels
      foreach(Namespaces::getLabelProperties() as $labelProperty)
      {
        if($labelProperty != $prefLabelProperty && isset($subject[$labelProperty]))        
        {
          if(!isset($subject["altLabel"]))
          {
            $subject["altLabel"] = array();
          }
          
          array_push($subject["altLabel"], $subject[$labelProperty][0]["value"]);
          
          if($labelProperty == Namespaces::$iron."altLabel")
          {
            unset($subject[$labelProperty]);
          }
        }
      }
      
      // Try to find description
      foreach(Namespaces::getDescriptionProperties() as $descriptionProperty)
      {
        if(isset($subject[$descriptionProperty]))        
        {
          $subject["description"] = $subject[$descriptionProperty][0]["value"];
          
          if($descriptionProperty == Namespaces::$iron."description")
          {
            unset($subject[$descriptionProperty]);
          }
          
          break;
        }
      }
            
      // Try to find preferred URLs
      if(isset($subject[Namespaces::$iron."prefURL"]))        
      {
        $subject["prefURL"] = $subject[Namespaces::$iron."prefURL"][0]["value"];
        
        unset($subject[Namespaces::$iron."prefURL"]);
      }  
      
      // Try to get a reference to the dataset where the record could come from
      $dataset = "unspecified";
      
      if(isset($subject[Namespaces::$dcterms."isPartOf"]))    
      {
        $dataset = $subject[Namespaces::$dcterms."isPartOf"][0]["uri"];
      }
      
      if(!isset($resultset[$dataset]))
      {
        $resultset[$dataset] = array();
      }
      
      $resultset[$dataset][$uri] = $subject;
    }
    
    $this->resultset = $resultset;
  } 
  
  /**
  * Convert an internal structWSF resultset array structure in structXML 
  *    
  * @return a structWSF document
  *   
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getResultsetXML()            
  {   
    $xml = '';
    
    foreach($this->resultset as $datasetURI => $records)
    {
      foreach($records as $recordURI => $record)
      {
        // Determine the first (main) type of the record
        $firstType = "http://www.w3.org/2002/07/owl#Thing";
        
        if(isset($record["type"][0]))
        {
          $firstType = $record["type"][0];
        }
        
        $xml .= '  <subject type="'.$this->xmlEncode($this->prefixize($firstType)).'" uri="'.$this->xmlEncode($recordURI).'">'."\n";
        
        foreach($record as $attributeURI => $attributeValues)
        {
          switch($attributeURI)
          {
            case "type":
              foreach($attributeValues as $key => $type)
              {
                if($key > 0 && $type != "")
                {      
                  $xml .= '    <predicate type="rdf:type">'."\n";
                  $xml .= '      <object uri="'.$this->xmlEncode($type).'" />'."\n";
                  $xml .= '    </predicate>'."\n";
                }              
              }
            break;
            case "prefLabel":
              if($attributeValues != "")
              {
                $xml .= '    <predicate type="iron:prefLabel">'."\n";
                $xml .= '      <object type="rdfs:Literal">'.$this->xmlEncode($attributeValues).'</object>'."\n";
                $xml .= '    </predicate>'."\n";
              }
            break;
            case "altLabel":     
              foreach($attributeValues as $altLabel)
              {
                if($altLabel != "")
                {
                  $xml .= '    <predicate type="iron:altLabel">'."\n";
                  $xml .= '      <object type="rdfs:Literal">'.$this->xmlEncode($altLabel).'</object>'."\n";
                  $xml .= '    </predicate>'."\n";
                }
              }          
            break;
            case "description":   
              if($attributeValues != "")
              {
                $xml .= '    <predicate type="iron:description">'."\n";
                $xml .= '      <object type="rdfs:Literal">'.$this->xmlEncode($attributeValues).'</object>'."\n";
                $xml .= '    </predicate>'."\n";
              }
            break;
            case "prefURL":
              if($attributeValues != "")
              {
                $xml .= '    <predicate type="iron:prefURL">'."\n";
                $xml .= '      <object type="rdfs:Literal">'.$this->xmlEncode($attributeValues).'</object>'."\n";
                $xml .= '    </predicate>'."\n";
              }            
            break;
            
            // Any other attribute describing this record
            default:
              foreach($attributeValues as $value)
              {             
                $xml .= '    <predicate type="'.$this->xmlEncode($this->prefixize($attributeURI)).'">'."\n";
                
                if(isset($value["value"]))
                {
                  // It is a literal value, or a literal value of some type (int, bool, etc)
                  if($value["value"] != "")
                  {
                    $xml .= '      <object type="'.$this->xmlEncode($this->prefixize($value["type"])).'"'.(isset($value["lang"]) && $value["lang"] != "" ? ' lang="'.$this->xmlEncode($value["lang"]).'"' : "").'>'.$this->xmlEncode($value["value"]).'</object>'."\n";
                  }
                }
                elseif(isset($value["uri"]))
                {
                  // It is a resource URI
                  if(!isset($value["reify"]))
                  {
                    if($value["uri"] != "")
                    {
                      $xml .= '      <object uri="'.$this->xmlEncode($value["uri"]).'" '.(isset($value["type"]) && $value["type"] != ""  ? 'type="'.$value["type"].'"' : '').' />'."\n";
                    }
                  }
                  else
                  {
                    if($value["uri"] != "")
                    {
                      $xml .= '      <object uri="'.$this->xmlEncode($value["uri"]).'" '.(isset($value["type"]) && $value["type"] != "" ? 'type="'.$value["type"].'"' : '').'>'."\n";
                      
                      foreach($value["reify"] as $reifyAttributeUri => $reifiedValues)
                      {
                        foreach($reifiedValues as $reifiedValue)
                        {
                          if($reifiedValue != "")
                          {
                            $xml .= '        <reify type="'.$this->xmlEncode($this->prefixize($reifyAttributeUri)).'" value="'.$this->xmlEncode($reifiedValue).'" />'."\n";
                          }
                        }
                      }                  
                      
                      $xml .= '      </object>'."\n";                                    
                    }
                  }
                }
                
                
                $xml .= '    </predicate>'."\n";
              }            
            break;          
          }
        }
        
        $xml .= "  </subject>\n";
      }
    }
    
    $xmlHeader = '<?xml version="1.0" encoding="utf-8"?>'."\n";
    $xmlHeader .= "  <resultset>\n";
    
    foreach($this->prefixes as $uri => $entity)
    {
      $xmlHeader .= '  <prefix entity="'.$entity.'" uri="'.$uri.'" />'."\n";
    }
    
    $xml = $xmlHeader.$xml;
    
    $xml .= '</resultset>';
    
    return($xml);
  } 
  
  /** 
  * Convert an internal structWSF resultset array structure in structJSON 
  *    
  * @return a structWSF document in JSON
  *    
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getResultsetJSON()            
  {
    $json = "";
      
    foreach($this->resultset as $datasetURI => $records)
    {
      foreach($records as $recordURI => $record)
      {
        // Determine the first (main) type of the record
        $firstType = "http://www.w3.org/2002/07/owl#Thing";
        
        if(isset($record["type"][0]))
        {
          $firstType = $record["type"][0];
        }
        
        $json .= '      {'."\n";
        $json .= '        "uri": "'.$this->jsonEncode($recordURI).'", '."\n";
        $json .= '        "type": "'.$this->jsonEncode($this->prefixize($firstType)).'", '."\n";
        
        $json .= '        "predicate": [  '."\n";
           
        foreach($record as $attributeURI => $attributeValues)
        {
          switch($attributeURI)
          {
            case "type":
              foreach($attributeValues as $key => $type)
              {
                if($key > 0 && $type != "")
                {               
                  $json .= '          { '."\n";            
                  $json .= '            "rdfs:type": "'.$this->jsonEncode($type).'" '."\n";            
                  $json .= '          }, '."\n";            
                }
              }
            break;
            case "prefLabel":
              if($attributeValues != "")
              {
                $json .= '          { '."\n";            
                $json .= '            "iron:prefLabel": "'.$this->jsonEncode($attributeValues).'" '."\n";            
                $json .= '          }, '."\n";            
              }
            break;
            case "altLabel":
              foreach($attributeValues as $altLabel)
              {
                if($altLabel != "")
                {
                  $json .= '          { '."\n";            
                  $json .= '            "iron:altLabel": "'.$this->jsonEncode($altLabel).'" '."\n";            
                  $json .= '          }, '."\n";            
                }
              }          
            break;
            case "description":
              if($attributeValues != "")
              {
                $json .= '          { '."\n";            
                $json .= '            "iron:description": "'.$this->jsonEncode($attributeValues).'" '."\n";            
                $json .= '          }, '."\n";            
              }
            break;
            case "prefURL":
              if($attributeValues != "")
              {
                $json .= '          { '."\n";            
                $json .= '            "iron:prefURL": "'.$this->jsonEncode($attributeValues).'" '."\n";            
                $json .= '          }, '."\n";            
              }
            break;
            
            // Any other attribute describing this record
            default:
              foreach($attributeValues as $value)
              {             
                if(isset($value["value"]))
                {
                  // It is a literal value, or a literal value of some type (int, bool, etc)
                  if($value["value"] != "")
                  {  
                    // We simply return the literal value                
                    if((isset($value["lang"]) && $value["lang"] != "") ||
                       (isset($value["type"]) && ($value["type"] != "" && $value["type"] != "rdfs:Literal" && $value["type"] != Namespaces::$rdfs."Literal")))
                    {
                      /*
                        If we have a type, or a lang defined for this literal value, we return and object of the kind:
                        {
                          "value": "the literal value",
                          "type": "xsd:string",
                          "lang": "en"
                        }
                      */
                      
                      $json .= '          { '."\n";            
                      $json .= '            "'.$this->jsonEncode($this->prefixize($attributeURI)).'": {'."\n";
                      $json .= '              "value": "'.$this->jsonEncode($value["value"]).'", '."\n";            
                      
                      if(isset($value["type"]) && ($value["type"] != "" && $value["type"] != "rdfs:Literal" && $value["type"] != Namespaces::$rdfs."Literal"))
                      {
                        $json .= '              "type": "'.$this->jsonEncode($this->prefixize($value["type"])).'", '."\n";            
                      }
                      
                      if(isset($value["lang"]) && $value["lang"] != "")
                      {
                        $json .= '              "lang": "'.$this->jsonEncode($value["lang"]).'", '."\n";            
                      }
                      
                      $json = substr($json, 0, strlen($json) - 3)." \n";
                      
                      $json .= "            }\n";
                      $json .= '          }, '."\n";                      
                    }
                    else
                    {
                      $json .= '          { '."\n";            
                      $json .= '            "'.$this->jsonEncode($this->prefixize($attributeURI)).'": "'.$this->jsonEncode($value["value"]).'" '."\n";            
                      $json .= '          }, '."\n";            
                    }
                  }
                }
                elseif(isset($value["uri"]))
                {
                  // It is a resource URI
                  if($value["uri"] != "")
                  {
                    $json .= '          { '."\n";            
                    $json .= '            "'.$this->jsonEncode($this->prefixize($attributeURI)).'": { '."\n";            
                    $json .= '              "uri": "'.$this->jsonEncode($value["uri"]).'"';
                    
                    if(isset($value["type"]) && $value["type"] != "")
                    {
                      $json .= ", \n";
                      $json .= '              "type": "'.$this->jsonEncode($this->prefixize($value["type"])).'"';
                    }
                    
                    if(isset($value["reify"]))
                    {
                      $jsonReifyData = '';
                      
                      foreach($value["reify"] as $reifyAttributeUri => $reifiedValues)
                      {
                        foreach($reifiedValues as $reifiedValue)
                        {
                          if($reifiedValue != "")
                          {
                            $jsonReifyData .= '                { '."\n";
                            $jsonReifyData .= '                  "type": "'.$this->jsonEncode($this->prefixize($reifyAttributeUri)).'", '."\n";
                            $jsonReifyData .= '                  "value": "'.$this->jsonEncode($this->prefixize($reifiedValue)).'" '."\n";
                            $jsonReifyData .= '                }, '."\n";
                          }
                        }
                      }                    

                      if($jsonReifyData != "")
                      {
                        $json .= ", \n";                      
                        
                        $json .= '              "reify": [ '."\n";

                        
                        $jsonReifyData = substr($jsonReifyData, 0, strlen($jsonReifyData) - 3);
                        
                        $json .= $jsonReifyData;
                        
                        $json .= "\n";
                        
                        $json .= '              ] '."\n";
                      }
                    }
                    else
                    {
                      $json .= " \n";
                    }
                    
                    $json .= '            } '."\n";            
                    $json .= '          }, '."\n";                              
                  }
                }
              }          
            break;          
          }
        }

        $json = substr($json, 0, strlen($json) - 3);
        
        $json .= "\n";
        
        $json .= '        ] '."\n";
        
        $json .= "      }, \n";    
      }
    }
    
    $json = substr($json, 0, strlen($json) - 3);
    
    $json .= "\n";  
    
    $jsonHeader = "{\n";
    
    if(count($this->prefixes) > 0)
    {
      $jsonHeader .= '  "prefixes": {'."\n";
      
      
      foreach($this->prefixes as $uri => $entity)
      {
        $jsonHeader .= '    "'.$entity.'": "'.$uri.'", '."\n";
      }
      
      $jsonHeader = substr($jsonHeader, 0, strlen($jsonHeader) - 3);
      
      $jsonHeader .= "\n";
      
      $jsonHeader .= '  }, '."\n";
    }
    
    $jsonHeader .= '  "resultset": {'."\n";
    $jsonHeader .= '    "subject": ['."\n";

    $xmlHeader = '<?xml version="1.0" encoding="utf-8"?>'."\n";
    $xmlHeader .= "  <resultset>\n";
    

    
    $json = $jsonHeader.$json;  
    
    $json .= '    ]'."\n";
    $json .= '  }'."\n";
    $json .= '}';
    
    return($json);
  } 
  
  /**
  * Convert an internal structWSF resultset array structure in irON JSON 
  *    
  * @return a structWSF document in irON JSON 
  *    
  * @author Frederick Giasson, Structured Dynamics LLC.
  */   
  public function getResultsetIronJSON()
  {
    $ws_irv = new ConverterIrJSON($this->getResultsetXML(), "text/xml", "true", "self", "self");

    $ws_irv->pipeline_conneg("application/iron+json", "", "text/xml", "");

    $ws_irv->process();

    if($ws_irv->pipeline_getResponseHeaderStatus() != 200)
    {
      /*
      $this->conneg->setStatus($ws_irv->pipeline_getResponseHeaderStatus());
      $this->conneg->setStatusMsg($ws_irv->pipeline_getResponseHeaderStatusMsg());
      $this->conneg->setStatusMsgExt($ws_irv->pipeline_getResponseHeaderStatusMsgExt());
      $this->conneg->setError($ws_irv->pipeline_getError()->id, $ws_irv->pipeline_getError()->webservice,
        $ws_irv->pipeline_getError()->name, $ws_irv->pipeline_getError()->description,
        $ws_irv->pipeline_getError()->debugInfo, $ws_irv->pipeline_getError()->level);
      */
      
      return;    
    }

    return ($ws_irv->pipeline_serialize());  
  }

  /** 
  * Convert an internal structWSF resultset array structure in irON commON 
  *    
  * @return a structWSF document in irON commON 
  *   
  * @author Frederick Giasson, Structured Dynamics LLC.
  */     
  public function getResultsetIronCOMMON()
  {
    $ws_irc = new ConverterCommON($this->getResultsetXML(), "text/xml", "true", "self", "self");

    $ws_irc->pipeline_conneg("application/iron+csv", "", "text/xml", "");

    $ws_irc->process();

    if($ws_irc->pipeline_getResponseHeaderStatus() != 200)
    {
      /*
      $this->conneg->setStatus($ws_irv->pipeline_getResponseHeaderStatus());
      $this->conneg->setStatusMsg($ws_irv->pipeline_getResponseHeaderStatusMsg());
      $this->conneg->setStatusMsgExt($ws_irv->pipeline_getResponseHeaderStatusMsgExt());
      $this->conneg->setError($ws_irv->pipeline_getError()->id, $ws_irv->pipeline_getError()->webservice,
        $ws_irv->pipeline_getError()->name, $ws_irv->pipeline_getError()->description,
        $ws_irv->pipeline_getError()->debugInfo, $ws_irv->pipeline_getError()->level);
      */
      
      return;    
    }

    return ($ws_irc->pipeline_serialize());  
  }  
  
  /** 
  * Convert an internal structWSF resultset array structure in RDF+XML
  *    
  * @return a RDF+XML document
  *    
  * @see http://techwiki.openstructs.org/index.php/Internal_Resultset_Array
  *    
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getResultsetRDFXML()            
  {   
    $xml = '';
    
    foreach($this->resultset as $datasetURI => $records)
    {
      foreach($records as $recordURI => $record)
      {
        $xmlRei = '';
        
        // Determine the first (main) type of the record
        $firstType = "http://www.w3.org/2002/07/owl#Thing";
        
        if(isset($record["type"][0]))
        {
          $firstType = $record["type"][0];
        }
        
        $xml .= '  <'.$this->xmlEncode($this->prefixize($firstType)).' rdf:about="'.$this->xmlEncode($recordURI).'">'."\n";
        
        foreach($record as $attributeURI => $attributeValues)
        {        
          switch($attributeURI)
          {
            case "type":
              foreach($attributeValues as $key => $type)
              {
                if($key > 0 && $type != "")
                {      
                  $xml .= '    <rdf:type rdf:resource="'.$this->xmlEncode($type).'" />'."\n";
                }              
              }
            break;
            case "prefLabel":
              if($attributeValues != "")
              {
                $xml .= '    <iron:prefLabel>'.$this->xmlEncode($attributeValues).'</iron:prefLabel>'."\n";              
              }
            break;
            case "altLabel":     
              foreach($attributeValues as $altLabel)
              {
                if($altLabel != "")
                {
                  $xml .= '    <iron:altLabel>'.$this->xmlEncode($altLabel).'</iron:altLabel>'."\n";              
                }
              }          
            break;
            case "description":   
              if($attributeValues != "")
              {
                $xml .= '    <iron:description>'.$this->xmlEncode($attributeValues).'</iron:description>'."\n";              
              }
            break;
            case "prefURL":
              if($attributeValues != "")
              {
                $xml .= '    <iron:prefURL>'.$this->xmlEncode($attributeValues).'</iron:prefURL>'."\n";              
              }            
            break;
            
            // Any other attribute describing this record
            default:
              foreach($attributeValues as $value)
              {             
                if(isset($value["value"]))
                {
                  // It is a literal value, or a literal value of some type (int, bool, etc)
                  if($value["value"] != "")
                  {
                    $xml .= '    <'.$this->xmlEncode($this->prefixize($attributeURI)).''.(isset($value["lang"]) && $value["lang"] != "" ? ' xml:lang="'.$this->xmlEncode($value["lang"]).'"' : "").''.(isset($value["type"]) && $value["type"] != "" && $value["type"] != "rdfs:Literal" && $value["type"] != Namespaces::$rdfs."Literal" ? ' xml:datatype="'.$this->xmlEncode($this->prefixize($value["type"])).'"' : "").'>'.$this->xmlEncode($value["value"]).'</'.$this->xmlEncode($this->prefixize($attributeURI)).'>'."\n";              
                  }
                }
                elseif(isset($value["uri"]))
                {
                  // It is a resource URI
                  if($value["uri"] != "")
                  {
                    $xml .= '    <'.$this->xmlEncode($this->prefixize($attributeURI)).' rdf:resource="'.$this->xmlEncode($value["uri"]).'" />'."\n";              
                    
                    if(isset($value["reify"]))
                    {
                      foreach($value["reify"] as $reifyAttributeUri => $reifiedValues)
                      {
                        foreach($reifiedValues as $reifiedValue)
                        {
                          if($reifiedValue != "")
                          {
                            $xmlRei .= "  <rdf:Statement rdf:about=\"". $this->xmlEncode("bnode:".md5($recordURI . $attributeURI . $value["uri"])) . "\">\n";
                            $xmlRei .= "    <rdf:subject rdf:resource=\"" . $recordURI . "\" />\n";
                            $xmlRei .= "    <rdf:predicate rdf:resource=\"" . $this->xmlEncode($attributeURI) . "\" />\n";
                            $xmlRei .= "    <rdf:object rdf:resource=\"" . $this->xmlEncode($value["uri"]) . "\" />\n";
                            $xmlRei .= "    <".$this->prefixize($reifyAttributeUri).">". $this->xmlEncode($reifiedValue) . "</".$this->prefixize($reifyAttributeUri).">\n";
                            $xmlRei .= "  </rdf:Statement>  \n\n";
                          }
                        }
                      }                  
                    }
                  }
                }
              }            
            break;          
          }
        }
              
        $xml .= '  </'.$this->xmlEncode($this->prefixize($firstType)).'>'."\n";
        $xml .= $xmlRei;
      }
    }
    

    $xmlHeader = "<?xml version=\"1.0\"?>\n";
    $xmlHeader .= "<rdf:RDF";

    $nb = 0;
    foreach($this->prefixes as $uri => $entity)
    {
      if($nb == 0)
      {
        $xmlHeader .= " xmlns:$entity=\"$uri\"\n";
      }
      else
      {
        $xmlHeader .= "         xmlns:$entity=\"$uri\"\n";
      }
      
      $nb++;
    }
    
    $xmlHeader = rtrim($xmlHeader, "\n");

    $xmlHeader .= ">\n\n";    
       
    
    $xml = $xmlHeader.$xml;
    
    $xml .= "</rdf:RDF>";
    
    return($xml);
  }
  
  /**
  * Convert an internal structWSF resultset array structure in RDF+N3
  *    
  * @return a RDF+N3 document
  *    
  * @see http://techwiki.openstructs.org/index.php/Internal_Resultset_Array
  *    
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getResultsetRDFN3()            
  {   
    $json = '';
    
    foreach($this->resultset as $datasetURI => $records)
    {
      foreach($records as $recordURI => $record)
      {
        $jsonRei = '';
        
        // Determine the first (main) type of the record
        $firstType = "http://www.w3.org/2002/07/owl#Thing";
        
        if(isset($record["type"][0]))
        {
          $firstType = $record["type"][0];
        }
        
        $json .= '<'.$recordURI.'> a '.$this->prefixize($firstType).' ;'."\n";
        
        $jsonPaddingSize = "";
        
        for($i = 0; $i < strlen('<'.$recordURI.'> '); $i++)
        {
          $jsonPaddingSize .= " ";
        }
        
        foreach($record as $attributeURI => $attributeValues)
        {        
          switch($attributeURI)
          {
            case "type":
              foreach($attributeValues as $key => $type)
              {
                if($key > 0 && $type != "")
                {      
                  $json .= $jsonPaddingSize.'a'.' '.$this->prefixize($type)." ;\n";
                }              
              }
            break;
            case "prefLabel":
              if($attributeValues != "")
              {
                $json .= $jsonPaddingSize.'iron:prefLabel """'.$this->jsonEncode($attributeValues).'"""'." ;\n";              
              }
            break;
            case "altLabel":     
              foreach($attributeValues as $altLabel)
              {
                if($altLabel != "")
                {
                  $json .= $jsonPaddingSize.'iron:prefLabel """'.$this->jsonEncode($altLabel).'"""'." ;\n";              
                }
              }          
            break;
            case "description":   
              if($attributeValues != "")
              {
                $json .= $jsonPaddingSize.'iron:description """'.$this->jsonEncode($attributeValues).'"""'." ;\n";              
              }
            break;
            case "prefURL":
              if($attributeValues != "")
              {
                $json .= $jsonPaddingSize.'iron:prefURL """'.$this->jsonEncode($attributeValues).'"""'." ;\n";              
              }            
            break;
            
            // Any other attribute describing this record
            default:
              foreach($attributeValues as $value)
              {             
                if(isset($value["value"]))
                {
                  // It is a literal value, or a literal value of some type (int, bool, etc)
                  if($value["value"] != "")
                  {
                    if(isset($value["type"]) && $value["type"] != "" && $value["type"] != "rdfs:Literal" && $value["type"] != Namespaces::$rdfs."Literal")
                    {
                      $json .= $jsonPaddingSize.$this->prefixize($attributeURI).' """'.$this->jsonEncode($value["value"]).'"""^^'.$this->prefixize($value["type"])." ;\n";                
                    }
                    elseif(isset($value["lang"]) && $value["lang"] != "")
                    {
                      $json .= $jsonPaddingSize.$this->prefixize($attributeURI).' """'.$this->jsonEncode($value["value"]).'"""@'.$value["lang"]." ;\n";                
                    }
                    else
                    {
                      $json .= $jsonPaddingSize.$this->prefixize($attributeURI).' """'.$this->jsonEncode($value["value"]).'"""'." ;\n";                
                    }
                  }
                }
                elseif(isset($value["uri"]))
                {
                  // It is a resource URI
                  if($value["uri"] != "")
                  {
                    $json .= $jsonPaddingSize.$this->prefixize($attributeURI).' <'.$this->jsonEncode($value["uri"]).'>'." ;\n";                
                    
                    if(isset($value["reify"]))
                    {
                      foreach($value["reify"] as $reifyAttributeUri => $reifiedValues)
                      {
                        foreach($reifiedValues as $reifiedValue)
                        {
                          if($reifiedValue != "")
                          {
                            $jsonRei .= "_:" . md5($recordURI . $attributeURI . $value["uri"]). " a rdf:Statement ;\n";
                            $jsonRei .= "    rdf:subject <" . $recordURI . "> ;\n";
                            $jsonRei .= "    rdf:predicate <" . $attributeURI . "> ;\n";
                            $jsonRei .= "    rdf:object <" . $value["uri"] . "> ;\n";
                            $jsonRei .= "    " . $this->prefixize($reifyAttributeUri) . " \"\"\"" . $this->jsonEncode($reifiedValue). "\"\"\" .\n\n";
                          }
                        }
                      }                  
                    }
                  }
                }
              }            
            break;          
          }
        }
        
        if(substr($json, strlen($json) - 2) == ";\n")
        {
          // Set the proper terminason for the N3 record.
          $json = substr($json, 0, strlen($json) - 2).".\n\n";
        }
              
        $json .= $jsonRei;
      }
    }
    

    $jsonHeader = "";

    foreach($this->prefixes as $uri => $entity)
    {
      $jsonHeader .= "@prefix $entity: <$uri> .\n";
    }
    
    $jsonHeader .= "\n";    
       
    
    $json = $jsonHeader.$json;
    
    return($json);
  }  
  
  /**
  * Encode content to be included in XML files
  *           
  * @param $string The content string to be encoded
  *    
  * @return returns the encoded string
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function xmlEncode($string)
  { 
    // Replace all the possible entities by their character. That way, we won't "double encode" 
    // these entities. Otherwise, we can endup with things such as "&amp;amp;" which some
    // XML parsers doesn't seem to like (and throws errors).
    $string = str_replace(array ("&amp;", "&lt;", "&gt;"), array ("&", "<", ">"), $string);
                           
    return str_replace(array ("&", "<", ">"), array ("&amp;", "&lt;", "&gt;"), $string); 
  }

  /**
  * Encode a string to put in a JSON value
  *            
  * @param $string The string to escape
  *    
  * @return returns the escaped string
  *    
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function jsonEncode($string) { return str_replace(array ('\\', '"', "\n", "\r", "\t"), array ('\\\\', '\\"', " ", " ", "\\t"), $string); }  
  
  /**
  * Prefixizes a URI (so, create a reference such as: "foo:Bar"). The prefix
  * and the base URI is saved in an array passed in reference.
  * 
  * @param mixed $uri URI to prefixize
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function prefixize($uri)
  {
    // Check if it is already prefixed
    if(substr($uri, 0, 7) != "http://")
    {
      return($uri);
    }  
    
    $prefixedUri = $this->namespaces->getPrefixedUri($uri);
    
    if($prefixedUri == $uri)
    {
      $pos = strripos($uri, "#");
      
      if($pos)
      {
        $baseUri = substr($uri, 0, $pos + 1);
        $uriExt = substr($uri, $pos + 1);
        
        if(!isset($this->prefixes[$baseUri]))
        {
          // A new prefix needs to be created
          $newPrefix = "ns".$this->newPrefixesCounter;
          
          $this->prefixes[$baseUri] = $newPrefix;
          
          $this->newPrefixesCounter++;
        }      
        
        return($this->prefixes[$baseUri].':'.$uriExt);
      }
      else
      {
        $pos = strripos($uri, "/");
        
        $baseUri = substr($uri, 0, $pos + 1);
        $uriExt = substr($uri, $pos + 1);
        
        if(!isset($this->prefixes[$baseUri]))
        {
          // A new prefix needs to be created
          $newPrefix = "ns".$this->newPrefixesCounter;
          
          $this->prefixes[$baseUri] = $newPrefix;
          
          $this->newPrefixesCounter++;
        }          
        
        if($pos)
        {
          return($this->prefixes[$baseUri].':'.$uriExt);
        }
        else
        {
          return($uri);
        }
      }
    }
    else
    {
      // The URI got prefixed
      $pieces = explode(":", $prefixedUri);
      
      if(!isset($this->prefixes[$this->namespaces->getUri($pieces[0])]))
      {
        $this->prefixes[$this->namespaces->getUri($pieces[0])] = $pieces[0];
      }
      
      return($prefixedUri);
    }
  }  
  
  /**
  * Unprefixize a URI using the prefixes defined in this resultset.
  * 
  * @param mixed $uri string to unprefixize.
  * 
  * @return the unprefixized URI. Return the input string if it can't be unprefixized
  */
  private function unprefixize($uri)
  {
    $pos = strpos($uri, ":");
    
    if($pos !== FALSE)
    {
      $prefix = substr($uri, 0, $pos);
      
      foreach($this->prefixes as $namespace => $px)
      {
        if($prefix == $px)
        {
          return($namespace.substr($uri, ($pos + 1)));
        }
      }
      
      return($uri);
    }
    else
    {
      return($uri);
    }
  }
}            

//@}

?>