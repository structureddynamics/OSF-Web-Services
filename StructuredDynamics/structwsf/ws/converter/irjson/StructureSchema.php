<?php

/*! @ingroup WsConverterIrJSON */
//@{

/*! @file \StructuredDynamics\structwsf\ws\converter\irjson\StructureSchema.php
    @brief Define an linkage schema item
 */

namespace StructuredDynamics\structwsf\ws\converter\irjson;  

/** Structure Schema item description

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class StructureSchema
{
  /** Version of the linkage schema */
  public $version;

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

  /**      @brief Define an attribute in the structure schema

                  @param $property Attribute name to be described
                  @param $type Expected type of the value
                  @param $format Expected format of the balue
                  @param $equivalentPropertyTo Equivalent property relationships
                  @param $subPropertyOf Sub-property relationships
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setPropertyX($property, $type, $format, $equivalentPropertyTo, $subPropertyOf)
    { $this->addProperty($this->propertyX[$property], $type, $format, $equivalentPropertyTo, $subPropertyOf); }


  /**      @brief Define a type in the structure schema

                  @param $type Attribute name to be described
                  @param $equivalentTypeTo Equivalent type relationships
                  @param $subTypeOf Sub-type relationships
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setTypeX($type, $equivalentTypeTo, $subTypeOf)
    { $this->addType($this->typeX[$type], $equivalentTypeTo, $subTypeOf); }

  private function addProperty(&$property, $type, $format, $equivalentPropertyTo, $subPropertyOf)
  {
    if(!is_array($property))
    {
      $property = array( array ("type" => $type, "format" => $format, "equivalentPropertyTo" => $equivalentPropertyTo,
        "subPropertyOf" => $subPropertyOf) );
    }
    else
    {
      array_push($property,
        array ("type" => $type, "format" => $format, "equivalentPropertyTo" => $equivalentPropertyTo,
          "subPropertyOf" => $subPropertyOf));
    }
  }

  private function addType(&$type, $equivalentTypeTo, $subTypeOf)
  {
    if(!is_array($type))
    {
      $type = array( array ("equivalentPropertyTo" => $equivalentPropertyTo, "subPropertyOf" => $subPropertyOf) );
    }
    else
    {
      array_push($type, array ("equivalentPropertyTo" => $equivalentPropertyTo, "subPropertyOf" => $subPropertyOf));
    }
  }

  public function getPropertyTypes($property)
  {
    // Only one type which is not an array in JSON.
    if(!is_array($this->propertyX[$property][0]["type"]) && $this->propertyX[$property][0]["type"] != "")
    {
      return array( $this->propertyX[$property][0]["type"] );
    }

    if(count($this->propertyX[$property][0]["type"]) > 0)
    {
      return ($this->propertyX[$property][0]["type"]);
    }

    return (FALSE);
  }
}

//@}

?>