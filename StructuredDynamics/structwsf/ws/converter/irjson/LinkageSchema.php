<?php

/*! @ingroup WsConverterIrJSON */
//@{

/*! @file \StructuredDynamics\structwsf\ws\converter\irjson\LinkageSchema.php
    @brief Define an linkage schema item
 */

namespace StructuredDynamics\structwsf\ws\converter\irjson;   

/** Linkage Schema item description

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class LinkageSchema
{
  /** Version of the linkage schema */
  public $version;

  /** Mime type of the linkage schema (what type it links to) */
  public $linkedType;

  /** List of prefixes used within the schema */
  public $prefixes;

  /** List of atributes linked by the schema */
  public $propertyX = array();

  /** List of types linked by the schema */
  public $typeX = array();

  function __construct() { }

  function __destruct() { }

  /**      @brief Set the value of the version

                  @param $version Version of the linkage schema
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setVersion($version)
  {
    $this->version = $version;
  }

  /**      @brief Set the value of the linked type

                  @param $linkedType Mime type of the language this schema links to (example: application/rdf+xml)
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setLinkedType($linkedType)
  {
    $this->linkedType = $linkedType;
  }

  /**      @brief Set a prefix used in this schema

                  @param $prefix Prefix to be used (example: "foaf:") 
                  @param $uri Full URI we have to use to extend the prefix (ex: "http://xmlns.com/foaf/0.1/")
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setPrefix($prefix, $uri)
  {
    if(!is_array($this->prefixes))
    {
      $this->prefixes = array( $prefix => $uri );
    }
    else
    {
      $this->prefixes[$prefix] = $uri;
    }
  }

  /** Map an attribute to an attribute of an external format/vocabulary/ontology
                                    
      @param $property Attribute we want to map to
      @param $mapTo External attribute we want to map to
      @param[out] $error Possible mapping errors
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setPropertyX($property, $mapTo, &$error)
    { $this->addProperty($this->propertyX[$property], $mapTo, $error); }

  /**      @brief Map type to a type of an external format/vocabulary/ontology

                  @param $type Type we want to map to..
                  @param $mapTo External type we want to map to
                  @param $add Additional information that has to be part of the transformation process
                  @param $error Possible mapping errors
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setTypeX($type, $mapTo, $add, &$error) { $this->addType($this->typeX[$type], $mapTo, $add, $error); }

  private function addProperty(&$property, $mapTo, &$error)
  {
    // Check for a prefix to create the full URI of the type
    $maptToUri = $mapTo;

    if(substr($mapTo, 0, 7) != "http://" && ($pos = strpos($mapTo, ":")) !== FALSE)
    {
      $prefix = substr($mapTo, 0, $pos);

      if(!isset($this->prefixes[$prefix]))
      {
        $error = "The prefix used '$prefix:' is undefined in the linkage file.";
        return (FALSE);
      }
      else
      {
        $maptToUri = $this->prefixes[$prefix] . substr($mapTo, $pos + 1, strlen($mapTo) - ($pos + 1));
      }
    }

    if(!is_array($property))
    {
      $property = array( array( "mapTo" => $maptToUri ) );
    }
    else
    {
      // Make sure the property doesn't already exist
      $reject = FALSE;

      foreach($property as $map)
      {
        if($map["mapTo"] == $maptToUri)
        {
          $reject = TRUE;
          break;
        }
      }

      if($reject === FALSE)
      {
        array_push($property, array( "mapTo" => $maptToUri ));
      }
    }
  }

  private function addType(&$type, $mapTo, $add, &$error)
  {
    // case unsensitive;
    $type = strtolower($type);

    // Check for a prefix to create the full URI of the type
    $mapToUri = $mapTo;

    if(substr($mapTo, 0, 7) != "http://" && ($pos = strpos($mapTo, ":")) !== FALSE)
    {
      $prefix = substr($mapTo, 0, $pos);

      if(!isset($this->prefixes[$prefix]))
      {
        $error = "The prefix used '$prefix:' is undefined in the linkage file.";
        return (FALSE);
      }
      else
      {
        $mapToUri = $this->prefixes[$prefix] . substr($mapTo, $pos + 1, strlen($mapTo) - ($pos + 1));
      }
    }

    $adds = array();

    foreach($add as $key => $value)
    {
      $k;
      $v;

      if(($pos = strpos($key, ":")) !== FALSE)
      {
        $prefix = substr($key, 0, $pos);

        if(!isset($this->prefixes[$prefix]))
        {
          $error = "The prefix used '$prefix:' is undefined in the linkage file.";
          return (FALSE);
        }
        else
        {
          $k = $this->prefixes[$prefix] . substr($key, $pos + 1, strlen($key) - ($pos + 1));
        }
      }

      if(($pos = strpos($value, ":")) !== FALSE)
      {
        $prefix = substr($value, 0, $pos);

        if(!isset($this->prefixes[$prefix]))
        {
          $error = "The prefix used '$prefix:' is undefined in the linkage file.";
          return (FALSE);
        }
        else
        {
          $v = $this->prefixes[$prefix] . substr($value, $pos + 1, strlen($value) - ($pos + 1));
        }
      }

      $adds[$k] = $v;
    }

    if(!is_array($type))
    {
      $type = array( array ("mapTo" => $mapToUri, "add" => $adds) );
    }
    else
    {
      // Make sure the property doesn't already exist
      $reject = FALSE;

      foreach($type as $map)
      {
        if($map["mapTo"] == $maptToUri)
        {
          $reject = TRUE;
          break;
        }
      }

      if($reject === FALSE)
      {
        array_push($type, array ("mapTo" => $maptToUri, "add" => $add));
      }
    }
  }

  /**      @brief Generates a JSON serialized file of this linkage schema.

                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function generateJsonSerialization()
  {
    $schema = "{\n";

    if($this->version != "")
    {
      $schema .= "        \"version\": \"" . $this->version . "\",\n";
    }

    if($this->linkedType != "")
    {
      $schema .= "        \"linkedType\": \"" . $this->linkedType . "\",\n";
    }

    if(count($this->prefixes) > 0)
    {
      $schema .= "        \"prefixList\": {\n";

      foreach($this->prefixes as $prefix => $uri)
      {
        $schema .= "            \"" . $prefix . "\": \"" . $uri . "\",\n";
      }

      $schema = substr($schema, 0, strlen($schema) - 2) . "\n";

      $schema .= "        },\n";
    }

    if(count($this->propertyX) > 0)
    {
      $schema .= "        \"attributeList\": {\n";

      foreach($this->propertyX as $property => $maps)
      {
        $schema .= "            \"" . $property . "\": {\n";

        // Could be extended to create arrays of "mapTo" (if more than one property mapTo this attribute)
        $schema .= "                \"mapTo\": \"" . $maps[0]["mapTo"] . "\"\n";

        $schema .= "            },\n";
      }

      $schema = substr($schema, 0, strlen($schema) - 2) . "\n";

      $schema .= "        },\n";
    }

    if(count($this->typeX) < 1)
    {
      $schema = substr($schema, 0, strlen($schema) - 2) . "\n";
    }

    if(count($this->typeX) > 0)
    {
      $schema .= "        \"typeList\": {\n";

      foreach($this->typeX as $type => $maps)
      {
        $schema .= "            \"" . $type . "\": {\n";

        // Could be extended to create arrays of "mapTo" (if more than one property mapTo this attribute)
        $schema .= "                \"mapTo\": \"" . $maps[0]["mapTo"] . "\"\n";

        $schema .= "            },\n";
      }

      $schema = substr($schema, 0, strlen($schema) - 2) . "\n";

      $schema .= "        }\n";
    }

    $schema .= "}\n";

    return ($schema);
  }
}

//@}

?>