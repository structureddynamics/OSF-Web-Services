<?php

/*! @ingroup WsConverterIrJSON */
//@{

/*! @file \StructuredDynamics\osf\ws\converter\irjson\Dataset.php
    @brief Define a dataset item
 */

namespace StructuredDynamics\osf\ws\converter\irjson;   

/** Dataset item description

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class Dataset
{
/** ID of the dataset. The ID of a dataset is used to create the global reference of local records belonging to the dataset. */
  public $id;

  /** Linkage schema related to this dataset */
  public $linkageSchema;

  /** Structure schema related to this dataset */
  public $structureSchema;

  /** All attributes/values describing the dataset */
  public $attributes;

  function __construct() { }

  function __destruct() { }

  /**      @brief Set the value of the ID

                  @param $id ID of the dataset
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setId($id)
  {
    if($id != "")
    {
      $this->id = array( $id );

      $this->id["valueType"] = "primitive:id[1]";
    }
  }

  /**      @brief Set the linkage schema(s) related to this dataset

                  @param $linkageSchema preferred label of the maintainer
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setLinkageSchema($linkageSchema)
  {
    if($linkageSchema != "")
    {
      $this->linkageSchema = array( $linkageSchema );

      $this->linkageSchema["valueType"] = "primitive:string[1]";
    }
  }

  /**      @brief Set the structure schema(s) related to this dataset

                  @param $structureSchema preferred label of the maintainer
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setStructureSchema($structureSchema)
  {
    if($structureSchema != "")
    {
      $this->structureSchema = array( $structureSchema );

      $this->structureSchema["valueType"] = "primitive:string[1]";
    }
  }

  /**      @brief Set a value for an attribute describing the dataset

                  @param $attr attribute describing the dataset
                  @param $value value of the attribute
                  @param $valueType type of the value of the attribute (ex: String, Object, etc).
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setAttribute($attr, $value, $valueType)
  {
    if($value != "")
    {
      if(!is_array($this->attributes[$attr]))
      {
        $this->attributes[$attr] = array( $value );
      }
      else
      {
        array_push($this->attributes[$attr], $value);
      }

      $this->attributes[$attr]["valueType"] = $valueType;
    }
  }

  /**      @brief Set a "ref" attribute

                  @param $attr attribute describing the dataset
                  @param $metaData metaData that describe the <subject, attribute, value> triple.
                  @param $ref Reference to the local or global ID of the reference
                  @param $valueType type of the value of the attribute (ex: String, Object, etc).
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setAttributeRef($attr, $metaData, $ref, $valueType)
    { $this->addRef($this->attributes[$attr], $metaData, $ref, $valueType); }

  /**      @brief Create a reference to a source, creator, curator or maintenainer of a dataset

                  @param $attr attribute reference
                  @param $metaData metaData that describe the <subject, attribute, value> triple.
                  @param $ref Reference to the local or global ID of the reference
                  @param $valueType Value type of the reference value
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function addRef(&$attr, $metaData, $ref, $valueType)
  {
    if(!is_array($attr))
    {
      $attr = array( array ("metaData" => $metaData, "ref" => $ref) );
    }
    else
    {
      array_push($attr, array ("metaData" => $metaData, "ref" => $ref));
    }

    $attr["valueType"] = $valueType;
  }

  /**      @brief Get the valueType of an attribute

                  @param $property Target property you want to get the valueType
                  
                  @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getValueType($property)
  {
    if(isset($this->{$property}["valueType"]))
    {
      return ($this->{$property}["valueType"]);
    }
    else
    {
      // Check if it is part of "attributes"
      if(isset($this->{"attributes"}[$property]["valueType"]))
      {
        return ($this->{"attributes"}[$property]["valueType"]);
      }

      return (FALSE);
    }
  }
}

//@}

?>