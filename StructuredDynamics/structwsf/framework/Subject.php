<?php

/*! @ingroup StructWSFPHPAPIFramework Framework of the structWSF PHP API library */
//@{

/*! @file \StructuredDynamics\structwsf\framework\Subject.php
    @brief An internal Subject class

*/


namespace StructuredDynamics\structwsf\framework;

/**
* Class used to create Subjects to add to a Resultset
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
    if(is_array($description))
    {
      $this->description = $description;
    }
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
      if(array_search($type, $this->description["type"]) === FALSE)
      {
        array_push($this->description["type"], $type);
      }
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

  /**
  * Get all the types of this subject
  *
  * @return Returns an array of types URIs.
  *         returns an empty array if there is none.
  *
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getTypes()
  {
    if(isset($this->description["type"]))
    {
      return($this->description["type"]);
    }

    return(array());
  }

  /**
  * Get the preferred label of this subject
  *
  * @param $force Force a preferred to be returned. If this parameter is TRUE (default) then
  *               in the worsecase, getPrefLabel() will return the subject's URI fragment
  *               as the pref label if nothing else is defined for it.
  * 
  * @return Returns the preferred label. Returns an empty string if there is none and if $force is FALSE.
  *
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getPrefLabel($force = TRUE)
  {
    if(isset($this->description["prefLabel"]))
    {
      return($this->description["prefLabel"]);
    }

    if($force)
    {
      // Else, return URI's ending
      $pos = strripos($this->uri, "#");

      if($pos === FALSE)
      {
        $pos = strripos($this->uri, "/");
      }

      if($pos !== FALSE)
      {
        $pos++;
      }

      $label = substr($this->uri, $pos, strlen($this->uri) - $pos);    
      
      return($label);
    }
    else
    {
      return("");
    }
  }

  /**
  * Get all the alternative labels of this subject
  *
  * @return Returns an array of alternative labels. Returns an empty
  *         array is there is none.
  *
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getAltLabels()
  {
    if(isset($this->description["altLabel"]))
    {
      return($this->description["altLabel"]);
    }

    return(array());
  }


  /**
  * Get the preferred URL of this subject
  *
  * @return Returns the preferred URL. Returns an empty string if there is none.
  *
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getPrefURL()
  {
    if(isset($this->description["prefURL"]))
    {
      return($this->description["prefURL"]);
    }

    return("");
  }

  /**
  * Get the description of this subject
  *
  * @return Returns the description. Returns an empty string if there is none.
  *
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  function getDescription()
  {
    if(isset($this->description["description"]))
    {
      return($this->description["description"]);
    }

    return("");
  }

  /**
  * Get the URI of all the object properties that describes this subject
  *
  * @return Array of object property URIs
  *
  */
  function getObjectPropertiesUri()
  {
    $uris = array();

    foreach($this->description as $uri => $property)
    {
      if(isset($property[0]["uri"]))
      {
        array_push($uris, $uri);
      }
    }

    return($uris);
  }

  /**
  * Get the URI of all the data properties that describes this subject
  *
  * @return Array of data property URIs
  *
  */
  function getDataPropertiesUri()
  {
    $uris = array();

    foreach($this->description as $uri => $property)
    {
      if(isset($property[0]["value"]))
      {
        array_push($uris, $uri);
      }
    }

    return($uris);
  }

  /**
  *  Get the values of an object property.
  *
  *  The values are turned as an array of values which has this structure:
  *
  *  Array(
  *    Array(
  *      "uri" => "some uri",
  *      "type" => "optional type of the referenced URI",
  *      "reify" => Array(
  *      "reification-attribute-uri" => Array("value of the reification statement"),
  *      "more-reification-attribute-uri" => ...
  *    ),
  *  )
  *
  * @param mixed $propertyUri
  * @return mixed
  */
  function getObjectPropertyValues($propertyUri)
  {
    if(isset($this->description[$propertyUri]))
    {
      return($this->description[$propertyUri]);
    }
    else
    {
      return(FALSE);
    }
  }

  /**
  *  Get the values of a data property.
  *
  *  The values are turned as an array of values which has this structure:
  *
  * Array(
  *   Array(
  *     "value" => "some value",
  *     "lang" => "language string of the value",
  *     "type" => "type of the value"
  *   ),
  *   Array(
  *     ...
  *   )
  * )
  *
  * @param mixed $propertyUri
  * @return mixed
  */
  function getDataPropertyValues($propertyUri)
  {
    if(isset($this->description[$propertyUri]))
    {
      return($this->description[$propertyUri]);
    }
    else
    {
      return(FALSE);
    }
  }
}
