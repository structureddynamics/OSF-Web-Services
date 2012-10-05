<?php

/*! @ingroup WsFramework Framework for the Web Services */
//@{

/*! @file \StructuredDynamics\structwsf\ws\framework\PropertyHierarchy.php
    @brief The property hierarchy of the system.
*/

namespace StructuredDynamics\structwsf\ws\framework;

/** The property hierarchy of the system. This property structure is used by multiple modules to 
    leverage the properties structure of a node. It is used to get the super-properties-of, sub-properties-of, 
    labels and descriptions of properties.

    @todo Load this structure from the database system instead of the file system (using include_once).      
  
    @author Frederick Giasson, Structured Dynamics LLC.
*/
class PropertyHierarchy
{
  /** PropertyNode(s) that define the class structure */
  public $properties = array();

  /** Constructor

      @param $rootProperty Root property of the property hierarchy.
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($rootProperty)
  {
    $this->properties[$rootProperty] = new PropertyNode($rootProperty, "");
  }

  function __destruct() { }

  /** Add a property to the property hierarchy

      @param $property URI of the property to add to the hierarchy
      @param $subPropertyOf URI of the super-property of the property being added.
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function addPropertyRelationship($property, $subPropertyOf)
  {
    // Make sure the relationship doesn't already exists

    if(isset($this->properties[$property]->subPropertyOf))
    {
      foreach($this->properties[$property]->subPropertyOf as $sp)
      {
        if($sp->name == $subPropertyOf)
        {
          return;
        }
      }
    }

    // First, check if the superProperty exists. If it doesn't, we link it to owl:Thing
    if(!isset($this->properties[$subPropertyOf]))
    {
      $this->addPropertyRelationship($subPropertyOf, "http://www.w3.org/2002/07/owl#Thing");
    }

    // Then check if the property already belong to the structure. If it does, we only have to re-link the structure
    if(isset($this->properties[$property]))
    {
      $target = $this->properties[$property];

      $superProperty = $this->properties[$subPropertyOf];

      array_push($superProperty->superPropertyOf, $target);

      array_push($target->subPropertyOf, $superProperty);


      // Lets remove the owl:Thing link if it was existing (introduced at step 1).
      ///////////////////////////////////////////////////
      $newSubpropertyArray = array();

      foreach($target->subPropertyOf as $sp)
      {
        if($sp->name != "http://www.w3.org/2002/07/owl#Thing")
        {
          array_push($newSubpropertyArray, $sp);
        }
        else
        {
          // Remove the link from the subPropertyOf owl:Thing too!
          $newSuperPropertyArray = array();

          $owlThing = $this->properties["http://www.w3.org/2002/07/owl#Thing"];

          foreach($owlThing->superPropertyOf as $sp)
          {
            if($sp->name != $target->name)
            {
              array_push($newSuperPropertyArray, $sp);
            }
          }

          $owlThing->superPropertyOf = $newSuperPropertyArray;
        }
      }

      $target->subPropertyOf = $newSubpropertyArray;
    ///////////////////////////////////////////////////
    }
    else
    {
      // Otherwise we have a new node to add to the structure.
      $newProperty = new PropertyNode($property, $superProperty);
      $this->properties[$property] = $newProperty;

      $superProperty = $this->properties[$subPropertyOf];

      array_push($superProperty->superPropertyOf, &$newProperty);

      array_push($newProperty->subPropertyOf, $superProperty);
    }
  }

  /** Returns a list of references to the superproperties

      @param $property URI of the property to get its super-properties references
      
      @return returns a list of references to the superproperties
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSuperProperties($property)
  {
    $superProperties = array();
    $stack = array();

    if(isset($this->properties[$property]))
    {
      // Initialize the stack
      foreach($this->properties[$property]->subPropertyOf as $sp)
      {
        if(array_search($sp, $stack) === FALSE)
        {
          array_push($stack, $sp);
        }
      }

      while(count($stack) > 0)
      {
        $target = array_pop($stack);

        array_push($superProperties, $target);

        if(isset($target->subPropertyOf))
        {
          foreach($target->subPropertyOf as $sp)
          {
            if(array_search($sp, $stack) === FALSE)
            {
              array_push($stack, $sp);
            }
          }
        }
      }
    }

    return $superProperties;
  }

  /** Returns a list of references to the subproperties

      @param $property URI of the property to get its sub-properties references
      
      @return returns a list of references to the subproperties
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSubproperties($property)
  {
    $subproperties = array();
    $stack = array();

    if(isset($this->properties[$property]))
    {
      // Initialize the stack
      foreach($this->properties[$property]->superPropertyOf as $sp)
      {
        if(array_search($sp, $stack) === FALSE)
        {
          array_push($stack, $sp);
        }
      }

      while(count($stack) > 0)
      {
        $target = array_pop($stack);

        array_push($subproperties, $target);

        if(isset($target->superPropertyOf))
        {
          foreach($target->superPropertyOf as $sp)
          {
            if(array_search($sp, $stack) === FALSE)
            {
              array_push($stack, $sp);
            }
          }
        }
      }
    }

    return $subproperties;
  }

  /** Check if a class if a sub-property of another property

      @param $subproperty URI of the property to check if it is a sub-property of another property
      @param $superProperty URI of the super property of the property
      
      @return returns TRUE if the property is a sub-property of the super-property; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function isSubPropertyOf($subproperty, $superProperty)
  {
    $superProperties = $this->getsuperProperties($subproperty);

    foreach($superProperties as $sp)
    {
      if($sp->name == $superProperty)
      {
        return (TRUE);
      }
    }

    return (FALSE);
  }


  /** Return an array of predicates for which the target class is in their domain.

      @param $class URI of the target class
      
      @return returns An array of properties URI for which the target class belong to their class extension.
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function inDomainOf($class)
  {
    $inDomain = array();

    foreach($this->properties as $property)
    {
      if(array_search($class, $property->domain) !== FALSE)
      {
        array_push($inDomain, $property->name);
      }
    }

    return ($inDomain);
  }

  /** Return an array of predicates for which the target class is in their range.

      @param $class URI of the target class
      
      @return returns An array of properties URI for which the target class belong to their class extension.
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function inRangeOf($class)
  {
    $inRange = array();

    foreach($this->properties as $property)
    {
      if(array_search($class, $property->range) !== FALSE)
      {
        array_push($inRange, $property->name);
      }
    }

    return ($inRange);
  }

  public function getProperty($propertyURI)
  {
    foreach($this->properties as $property)
    {
      if($property->name == $propertyURI)
      {
        return ($property);
      }
    }

    return (NULL);
  }
}


/** Property node structure that populate the property hierarchy

    @author Frederick Giasson, Structured Dynamics LLC.
*/
class propertyNode
{
  /** URI of the property */
  public $name = "";

  /** Label to reference to this property */
  public $label = "";

  /** Description of the property */
  public $description = "";

  /*    public $displayCluster = "generic";
      public $displayCardinality = "*";
      public $displayKind = "string";
      public $displayPriority = 1;
  */
  /** Array of references to the sub-properties of this property */
  public $subPropertyOf = array();

  /** Array of references to the super-properties of this property */
  public $superPropertyOf = array();

  /** Exostive list of all inferred classes part of the class extension of the domain of this property. */
  public $domain = array();

  /** Exostive list of all inferred classes part of the class extension of the range of this property. */
  public $range = array();
  
  /** Specifies where this property is defined in the ontology structure (mainly: the dataset URI of the ontology)  */
  public $isDefinedBy = "";  
  
  /** Specifies the minimum cardinality of the property */
  public $minCardinality =  -1;
  
  /** Specifies the maximum cardinality of the property */
  public $maxCardinality = -1;
  
  /** Specifies the absolute cardinality of the property */
  public $cardinality = -1;

  /** Constructor

      @param $name URI of the property
      @param $subPropertyOf An array of references to the super-properties of this property
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($name, $subPropertyOf)
  {
    $this->name = $name;

    if(isset($subPropertyOf->name) && $subPropertyOf->name != "")
    {
      $this->subPropertyOf[$subPropertyOf->name] = $subPropertyOf;
    }
  }

  function __destruct() { }
}
?>