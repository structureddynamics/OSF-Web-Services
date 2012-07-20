<?php

/*! @ingroup StructWSFPHPAPIFramework Framework of the structWSF PHP API library */
//@{

/*! @file \StructuredDynamics\structwsf\framework\Resultset.php
    @brief An internal Resultset class

*/


namespace StructuredDynamics\structwsf\framework;

use \SimpleXMLElement;

use \StructuredDynamics\structwsf\framework\Namespaces;

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

  /**
  * Constructor
  *    
  * @param mixed $wsf_base_path Path where the structWSF instance is installed on the server
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($wsf_base_path = "/usr/share/structwsf/") 
  { 
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
    include_once($this->wsf_base_path."WebService.php");
    include_once($this->wsf_base_path."Conneg.php");
    include_once($this->wsf_base_path."ProcessorXML.php");    
    
    include_once($this->wsf_base_path."converter/irjson/ConverterIrJSON.php");
    include_once($this->wsf_base_path."converter/irjson/Dataset.php");
    include_once($this->wsf_base_path."converter/irjson/InstanceRecord.php");
    include_once($this->wsf_base_path."converter/irjson/LinkageSchema.php");
    include_once($this->wsf_base_path."converter/irjson/StructureSchema.php");
    include_once($this->wsf_base_path."converter/irjson/irJSONParser.php");
    
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
    include_once($this->wsf_base_path."WebService.php");
    include_once($this->wsf_base_path."Conneg.php");
    include_once($this->wsf_base_path."ProcessorXML.php");
    
    include_once($this->wsf_base_path."converter/common/ConverterCommON.php");
    include_once($this->wsf_base_path."converter/common/CommonParser.php");
    
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
    $string = str_replace(array ("&amp;", "&lt;", "&gt;"), array ("\\", "&", "<", ">"), $string);
                           
    return str_replace(array ("&", "<", ">"), array ("%5C", "&amp;", "&lt;", "&gt;"), $string); 
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
    
    $prefixedUri = Namespaces::getPrefixedUri($uri);
    
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
      
      if(!isset($this->prefixes[Namespaces::$$pieces[0]]))
      {
        $this->prefixes[Namespaces::$$pieces[0]] = $pieces[0];
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

/**
* Class used to create subjects to add to a Resultset
* 
* @author Frederick Giasson, Structured Dynamics LLC.
*/
class Subject
{
  /** URI of the suject */
  private $uri = "";

  /** Internal array description of the subject. */
  private $description = array();

  /**
  * Contructor
  * 
  * @param mixed $uri URI of the subject to create
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($uri) 
  { 
    $this->uri = $uri;
  }
  
  function __destruct() { }

  /**
  * Get the URI of the subject
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getUri()
  {
    return($this->uri);
  }

  /**
  * Get the array description of the Subject object 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSubject()
  {
    return($this->description);
  }
  
  /**
  * Set the description of the subject using an array that describes the subject.
  * 
  * @param mixed $description Array description of the subject
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setSubject($description)
  {
    $this->description = $description;
  }

  /**
  * Set a type to the subject. Multiple types can be added by calling this function
  * multiple times.
  * 
  * @param mixed $type URI of the type to add to this subject 
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setType($type)
  {
    if(isset($this->description["type"]) && is_array($this->description["type"]))
    {
      array_push($this->description["type"], $type);
    }
    else
    {
      $this->description["type"] = array($type);
    }
  }
  
  /**
  * Set a preferred label for this subject
  * 
  * @param mixed $prefLabel
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setPrefLabel($prefLabel)
  {
    $this->description["prefLabel"] = $prefLabel;
  }
  
  /**
  * Set an alternative label for this subject. Multiple alternative label
  * can be added to this subject by calling this function multiple times.
  * 
  * @param mixed $altLabel
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setAltLabel($altLabel)
  {
    if(isset($this->description["altLabel"]) && is_array($this->description["altLabel"]))
    {
      array_push($this->description["altLabel"], $altLabel);
    }
    else
    {
      $this->description["altLabel"] = array($altLabel);
    }
  }
  
  /**
  * Set the textual description (a bio, an abstract, etc) for this subject.
  * 
  * @param mixed $description
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setDescription($description)
  {
    $this->description["description"] = $description;
  }
  
  /**
  * Set a preferred label to this subject. The preferred label is a preferred
  * webpage where someone can get more information about this subject.
  * 
  * @param mixed $prefURL
  */
  public function setPrefURL($prefURL)
  {
    $this->description["prefURL"] = $prefURL;
  }
  
  /**
  * Set a custom data attribute to this subject.
  * 
  * @param mixed $attribute URI of the attribute to define
  * @param mixed $value Literal value to associate to this attribute
  * @param mixed $type URI (normally a XSD uri) of the type of the value (optional)
  * @param mixed $lang Language code of the value (optional)
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setDataAttribute($attribute, $value, $type = "rdfs:Literal", $lang = "")
  {
    if(!isset($this->description[$attribute]) || !is_array($this->description[$attribute]))
    {
      $this->description[$attribute] = array();
    }
    
    $val = array(
      "value" => $value,
      "lang" => $lang,
      "type" => $type
    );    
    
    array_push($this->description[$attribute], $val);
  }
  
  /**
  * Set a custom object attribute to this object. An object attribute is an attribute
  * that refers to another subject (record).
  * 
  * @param mixed $attribute URI of the object attribute
  * @param mixed $uri URI of the other subject/record to refer to
  * @param mixed $reiStatements A reification statement that reifies (add meta-data about this 
  *                             relationship) this statement. This parameter expects an array
  *                             of the type:
  * 
  *                               Array(
  *                                 "reification-attribute" => Array(values...)
  *                                 "another-reification-attribute" => Array(values...)
  *                               )
  * @param mixed $type URI of the type of the subject/record that is referenced by this attribute (optional)
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setObjectAttribute($attribute, $uri, $reiStatements = null, $type = "")
  {
    if(!isset($this->description[$attribute]) || !is_array($this->description[$attribute]))
    {
      $this->description[$attribute] = array();
    }
    
    $val = array(
      "uri" => $uri,
      "type" => $type
    ); 
    
    if($reiStatements != null)
    {
      $val["reify"] = $reiStatements;
    }   
    
    array_push($this->description[$attribute], $val);
  }  
}

class Datasetypes
{
  public static $uri = "xsd:anyURI";
  public static $literal = "rdfs:Literal";
  public static $int = "xsd:int";
  public static $bool = "xsd:boolean";
  public static $decimal = "xsd:decimal";
  public static $float = "xsd:float";
  public static $double = "xsd:double";
  public static $long = "xsd:long";
  public static $short = "xsd:short";
  public static $byte = "xsd:byte";
  public static $hexBinary = "xsd:hexBinary";
  public static $base64Binary = "xsd:base64Binary";
  public static $dateTime = "xsd:dateTime";
  public static $date = "xsd:date";
  public static $time = "xsd:time";
}

/*
  As suggested ni RDF RFC 4646, these are the ISO639-1 language codes
*/
class LanguageCodes
{
  public static $aa = "aa";
  public static $ab = "ab";
  public static $af = "af";
  public static $am = "am";
  public static $ar = "ar";
  public static $as = "as";
  public static $ay = "ay";
  public static $az = "az";
  public static $ba = "ba";
  public static $be = "be";
  public static $bg = "bg";
  public static $bh = "bh";
  public static $bi = "bi";
  public static $bn = "bn";
  public static $bo = "bo";
  public static $br = "br";
  public static $ca = "ca";
  public static $co = "co";
  public static $cs = "cs";
  public static $cy = "cy";
  public static $da = "da";
  public static $de = "de";
  public static $dz = "dz";
  public static $el = "el";
  public static $en = "en";
  public static $eo = "eo";
  public static $es = "es";
  public static $et = "et";
  public static $eu = "eu";
  public static $fa = "fa";
  public static $fi = "fi";
  public static $fj = "fj";
  public static $fo = "fo";
  public static $fr = "fr";
  public static $fy = "fy";
  public static $ga = "ga";
  public static $gd = "gd";
  public static $gl = "gl";
  public static $gn = "gn";
  public static $gu = "gu";
  public static $ha = "ha";
  public static $hi = "hi";
  public static $hr = "hr";
  public static $hu = "hu";
  public static $hy = "hy";
  public static $ia = "ia";
  public static $ie = "ie";
  public static $ik = "ik";
  public static $in = "in";
  public static $is = "is";
  public static $it = "it";
  public static $iw = "iw";
  public static $ja = "ja";
  public static $ji = "ji";
  public static $jw = "jw";
  public static $ka = "ka";
  public static $kk = "kk";
  public static $kl = "kl";
  public static $km = "km";
  public static $kn = "kn";
  public static $ko = "ko";
  public static $ks = "ks";
  public static $ku = "ku";
  public static $ky = "ky";
  public static $la = "la";
  public static $ln = "ln";
  public static $lo = "lo";
  public static $lt = "lt";
  public static $lv = "lv";
  public static $mg = "mg";
  public static $mi = "mi";
  public static $mk = "mk";
  public static $ml = "ml";
  public static $mn = "mn";
  public static $mo = "mo";
  public static $mr = "mr";
  public static $ms = "ms";
  public static $mt = "mt";
  public static $my = "my";
  public static $na = "na";
  public static $ne = "ne";
  public static $nl = "nl";
  public static $no = "no";
  public static $oc = "oc";
  public static $om = "om";
  public static $or = "or";
  public static $pa = "pa";
  public static $pl = "pl";
  public static $ps = "ps";
  public static $pt = "pt";
  public static $qu = "qu";
  public static $rm = "rm";
  public static $rn = "rn";
  public static $ro = "ro";
  public static $ru = "ru";
  public static $rw = "rw";
  public static $sa = "sa";
  public static $sd = "sd";
  public static $sg = "sg";
  public static $sh = "sh";
  public static $si = "si";
  public static $sk = "sk";
  public static $sl = "sl";
  public static $sm = "sm";
  public static $sn = "sn";
  public static $so = "so";
  public static $sq = "sq";
  public static $sr = "sr";
  public static $ss = "ss";
  public static $st = "st";
  public static $su = "su";
  public static $sv = "sv";
  public static $sw = "sw";
  public static $ta = "ta";
  public static $te = "te";
  public static $tg = "tg";
  public static $th = "th";
  public static $ti = "ti";
  public static $tk = "tk";
  public static $tl = "tl";
  public static $tn = "tn";
  public static $to = "to";
  public static $tr = "tr";
  public static $ts = "ts";
  public static $tt = "tt";
  public static $tw = "tw";
  public static $uk = "uk";
  public static $ur = "ur";
  public static $uz = "uz";
  public static $vi = "vi";
  public static $vo = "vo";
  public static $wo = "wo";
  public static $xh = "xh";
  public static $yo = "yo";
  public static $zh = "zh";
  public static $zu = "zu";  
}

//@}

?>