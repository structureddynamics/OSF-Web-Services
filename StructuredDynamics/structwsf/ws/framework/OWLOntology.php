<?php


/*! @ingroup WsFramework Framework for the Web Services */
//@{

/*! @file \StructuredDynamics\structwsf\ws\framework\OWLOntology.php
    @brief This class is a PHP wrapper over the Java OWLAPI. This manages all the ontologies existing in a structWSF 
           instance.
 */

namespace StructuredDynamics\structwsf\ws\framework;  
 
use \java;
use \Exception; 
use \StructuredDynamics\structwsf\framework\Namespaces;
 
/**
* An OWL ontology object that get persisted in an OWAPI tomcat instance.
* 
* Note: the functions that starts with an underscore "_" are public functions. However, these are
*       taking OWLAPI classes/types as parameters, or that returns classes/types as returned value.* 
*/
class OWLOntology
{
  /**
  * URI of the current ontology
  * 
  * **Java variable type:** string
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private $uri = "";
  
  /**
  * Configuration file of the OWLAPI instance.
  * 
  * **Java variable type:** array
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private $config;
  
  /**
  * Ontology manager object.
  * 
  * **Java variable type:** org.semanticweb.owlapi.apibinding.OWLOntologyManager
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLOntologyManager.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private $manager;
  
  /**
  * Pellet reasoner object
  * 
  * **Java variable type:** com.clarkparsia.pellet.owlapiv3.PelletReasonerFactory
  * @see http://hermit-reasoner.com/download/0.9.3/owlapi/javadoc/org/semanticweb/reasonerfactory/pellet/PelletReasonerFactory.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private $reasoner;
  
  /**
  * An interface for creating entities, class expressions and axioms. 
  * 
  * **Java variable type:** uk.ac.manchester.cs.owl.owlapi.OWLDataFactory
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLDataFactory.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private $owlDataFactory;
  
  /** the OWLAPI sessions used to access the persisted objects within the javabridge */
  private $owlApiSession;

  /**
  * Ontology object
  *   
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private $ontology = null;
  
  /**
  * Specify if we want to use the reasonner in this Ontology object.
  * 
  * **Java variable type:** boolean
  */
  private $useReasoner = TRUE;  
  
  /**
  * Language to use to return the annotations
  * 
  * @var mixed
  */
  private $lang = "en";
  
  /**
  * Constructor
  * 
  * @param $uri URI of the ontology to create/load
  * @param $OwlApiSession OWLAPI session where the OWLAPI object may be defined/hosted
  * @param $readingMode The reading mode is used to simply read an ontology, and not
  *                     to load it if it is not currently loaded.
  * @return Nothing
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($uri, $OwlApiSession, $readingMode=TRUE)
  {
    $this->uri = $uri;

    // Register it into the ontologies register
    if(!is_null($OwlApiSession))
    {
      $this->owlApiSession = $OwlApiSession;
      
      $register = java_values($OwlApiSession->get("ontologiesRegister"));
    }

    // Import the ontology if necessary           
    if(is_null($OwlApiSession) ||
       is_null(java_values($OwlApiSession->get($this->getOntologySessionID($uri)))) ||
       array_search($this->getOntologySessionID($uri), $register) === FALSE) 
    {
      if($readingMode === TRUE)
      {
        throw new Exception("Ontology not loaded");  
      }
      
      // Load the ontology in OWLAPI
      
      // OWLOntologyManager manager = OWLManager.createOWLOntologyManager(); 
      $managerClass = java("org.semanticweb.owlapi.apibinding.OWLManager");
      $this->manager = $managerClass->createOWLOntologyManager();
      
      // IRI docIRI = IRI.create(DOCUMENT_IRI); 
      $iriClass = java("org.semanticweb.owlapi.model.IRI");
      $iri = $iriClass->create($uri);
       
      // OWLOntology ont = manager.loadOntologyFromOntologyDocument(docIRI);
      $this->ontology = $this->manager->loadOntologyFromOntologyDocument($iri);    
      
      if(!is_array($register))
      {
        $register = array();
      }
      
      if(!is_null($OwlApiSession) && array_search($this->getOntologySessionID($uri), $register) === FALSE)
      {
        $register[$uri."-ontology"] = $this->getOntologySessionID($uri);
        $OwlApiSession->put("ontologiesRegister", $register);
      }
    }
    else
    {
      $this->manager = $OwlApiSession->get($this->getOntologySessionID($uri));
    }
    
    if($this->ontology == null)
    {
      $ontologies = $this->getOntologies();
      
      foreach($ontologies as $ontology)
      {
        $this->ontology = $ontology;
        break;      
      }
    }     
         
    // Register it into the reasoner register
    if(!is_null($OwlApiSession))
    {
      $register = java_values($OwlApiSession->get("reasonersRegister"));
    }
         
    // Create the reasoner if necessary      
    if(is_null($OwlApiSession) ||
       is_null(java_values($OwlApiSession->get($this->getReasonerSessionID($uri)))) ||
       array_search($this->getReasonerSessionID($uri), $register) === FALSE) 
    {
      // Create the reasoner for this ontology (Pellet)

      // Pellet    
      $PelletReasonnerFactory = java("com.clarkparsia.pellet.owlapiv3.PelletReasonerFactory");
      $PelletReasonnerFactory = $PelletReasonnerFactory->getInstance();
      $this->reasoner = $PelletReasonnerFactory->createNonBufferingReasoner($this->ontology);
      $this->manager->addOntologyChangeListener($this->reasoner);
      
      // HermiT
      //$this->reasoner = new java("org.semanticweb.HermiT.Reasoner", $this->ontology);
                                         
      // Fact++ (Currently NOT WORKING)
      //$FactppReasonnerFactory= new java("uk.ac.manchester.cs.factplusplus.owlapiv3.FaCTPlusPlusReasonerFactory", $this->ontology);
      //$FactppReasonnerFactory = $FactppReasonnerFactory->getInstance();
      //$this->reasoner = $FactppReasonnerFactory->createNonBufferingReasoner($this->ontology);
      //$this->manager->addOntologyChangeListener($this->reasoner);
      
      
      // Persist this ontology
      if(!is_null($OwlApiSession))
      {
        $OwlApiSession->put($this->getOntologySessionID($uri), $this->manager);
        
        // Persist the reasoner
        $OwlApiSession->put($this->getReasonerSessionID($uri), $this->reasoner);
        
        if(!is_array($register))
        {
          $register = array();
        }      
        
        if(array_search($this->getReasonerSessionID($uri), $register) === FALSE)
        {
          $register[$uri."-reasoner"] = $this->getReasonerSessionID($uri);
          $OwlApiSession->put("reasonersRegister", $register);
        }
      }
    }
    else
    {
      $this->reasoner = $OwlApiSession->get($this->getReasonerSessionID($uri));
    }
    
    $this->owlDataFactory = $this->manager->getOWLDataFactory();

  }
  
  /**
  * Gets an OWL individual that has the specified IRI 
  * 
  * @param string $uri The IRI of the individual to be obtained 
  * 
  * @return Returns a OWLNamedIndividual; null if not existing.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLNamedIndividual.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function _getNamedIndividual($uri)
  {
    $entities = $this->ontology->getEntitiesInSignature(java("org.semanticweb.owlapi.model.IRI")->create($uri));

    foreach($entities as $entity)
    {
      if(java_instanceof($entity, java("org.semanticweb.owlapi.model.OWLNamedIndividual")))
      {
        return($entity);
      }
    }     
    
    return($ni);
  }
  
  /**
  * Convert the OWLAPI named individual description into an array describing the class. This array is a simplification
  * of the OWLAPI that is used by other parts of this API, along with other scripts that uses this
  * API such as the various ontology related structWSF endpoints.
  * 
  * Note: annotations on are converted into non-annotation attribute/value with this API call.
  * 
  * The array is defined as:
  *   
  *   $classDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $namedIndividual The OWLAPI named individual instance.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/uk/ac/manchester/cs/owl/owlapi/OWLNamedIndividual.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function _getNamedIndividualDescription($namedIndividual)
  {
    $niDescription = array();
    
    // Get the types of the entity
    $niDescription[Namespaces::$rdf."type"] = array();     
    
    // Get all the annotations
    $annotations = $namedIndividual->getAnnotations($this->ontology);
    
    foreach($annotations as $annotation)
    {
      $info = $this->getAnnotationInfo($annotation);
      
      if(!isset($niDescription[$info["property"]]) || 
         is_array($niDescription[$info["property"]]) === FALSE)
      {
        $niDescription[$info["property"]] = array(); 
      }

      if(isset($info["uri"]))
      {
        array_push($niDescription[$info["property"]], array("uri" => $info["uri"],
                                                            "reify" => $info["reify"]));       
      }
      else
      {
        if($info["lang"] == "" || $this->lang == "" || $info["lang"] == $this->lang)
        {
          array_push($niDescription[$info["property"]], array("value" => $info["value"],
                                                              "type" => $info["type"],
                                                              "lang" => $info["lang"]));       
        }
      }
    }
    
    // Get all types of this named individual
    $types = $namedIndividual->getTypes($this->ontology);
    
    foreach($types as $type)
    {
      $typeUri = (string)java_values($type->toStringID());
      
      array_push($niDescription[Namespaces::$rdf."type"], array("uri" => $typeUri,
                                                                "reify" => array(
                                                                  "wsf:objectLabel" => array($this->getPrefLabel($type))
                                                                 ))); 
    }
    
    // Get all dataproperty/values defining this named individual
    $datapropertiesValuesMap = $namedIndividual->getDataPropertyValues($this->ontology);
    
    $keys = $datapropertiesValuesMap->keySet();
    $size = java_values($datapropertiesValuesMap->size());
    
    foreach($keys as $property)
    {
      $propertyUri = (string)java_values($property->toStringID());
      
      $valuesOWLLiteral = $datapropertiesValuesMap->get($property);
      
      if(!isset($niDescription[$propertyUri]) ||
         !is_array($niDescription[$propertyUri]))
      {
        $niDescription[$propertyUri] = array();
      }
      
      foreach($valuesOWLLiteral as $valueOWLLiteral)
      {
        if((string)$valueOWLLiteral->getLang() == "" || $this->lang == "" || 
           (string)$valueOWLLiteral->getLang() == $this->lang)
        {
          array_push($niDescription[$propertyUri], array("value" => (string)$valueOWLLiteral->getLiteral(),
                                                         "type" => "rdfs:Literal",
                                                         "lang" => (string)$valueOWLLiteral->getLang()));       
        }
      }
    }
    
    // Get all objectproperty/values defining this named individual
    $objectpropertiesValuesMap = $namedIndividual->getObjectPropertyValues($this->ontology);
    
    $keys = $objectpropertiesValuesMap->keySet();
    $size = java_values($objectpropertiesValuesMap->size());
    
    foreach($keys as $property)
    {
      $propertyUri = (string)java_values($property->toStringID());
      
      $valuesOWLIndividual = $objectpropertiesValuesMap->get($property);
      
      if(!isset($niDescription[$propertyUri]) || 
         !is_array($niDescription[$propertyUri]))
      {
        $niDescription[$propertyUri] = array();
      }
      
      foreach($valuesOWLIndividual as $valueOWLIndividual)
      {
        array_push($niDescription[$propertyUri], array("uri" => (string)$valueOWLIndividual->toStringID(),
                                                       "reify" => array(
                                                        "wsf:objectLabel" => array($this->getPrefLabel($valueOWLIndividual))
                                                       )));       
      }
    }
    
    return($niDescription);
  }  
  
  /**
  * Convert the OWLAPI named individual description into an array describing the class. This array is a simplification
  * of the OWLAPI that is used by other parts of this API, along with other scripts that uses this
  * API such as the various ontology related structWSF endpoints.
  * 
  * This array is optimized for displaying the named individual references in some list controls. This function
  * only returns the prefLabel, and types of the named individual.
  * 
  * Note: annotations on are converted into non-annotation attribute/value with this API call.
  * 
  * The array is defined as:
  *   
  *   $classDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $namedIndividual The OWLAPI named individual instance.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/uk/ac/manchester/cs/owl/owlapi/OWLNamedIndividual.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function _getNamedIndividualListDescription($namedIndividual)
  {
    $niDescription = array();

    // Get the types of the entity
    $niDescription[Namespaces::$rdf."type"] = array();     
    
    // Get all types of this named individual
    $types = $namedIndividual->getTypes($this->ontology);
    
    foreach($types as $type)
    {
      $typeUri = (string)java_values($type->toStringID());
      
      array_push($niDescription[Namespaces::$rdf."type"], array("uri" => $typeUri)); 
    }
    
    // Get all the annotations
    $annotations = $namedIndividual->getAnnotations($this->ontology);
    
    foreach($annotations as $annotation)
    {
      $info = $this->getAnnotationInfo($annotation);
      
      if(in_array($info["property"], Namespaces::getLabelProperties()))
      {      
        if(!isset($niDescription[$info["property"]]) || 
           is_array($niDescription[$info["property"]]) === FALSE)
        {
          $niDescription[$info["property"]] = array(); 
        }

        if(isset($info["uri"]))
        {
          array_push($niDescription[$info["property"]], array("uri" => $info["uri"],
                                                              "reify" => $info["reify"]));
        }
        else
        {
          if($info["lang"] == "" || $this->lang == "" || $info["lang"] == $this->lang)
          {
            array_push($niDescription[$info["property"]], array("value" => $info["value"],
                                                                "type" => $info["type"],
                                                                "lang" => $info["lang"]));
          }
        }
      }       
    }
    
    // Get all dataproperty/values defining this named individual
    $datapropertiesValuesMap = $namedIndividual->getDataPropertyValues($this->ontology);
    
    $keys = $datapropertiesValuesMap->keySet();
    $size = java_values($datapropertiesValuesMap->size());
    
    foreach($keys as $property)
    {
      $propertyUri = (string)java_values($property->toStringID());
      
      if(in_array($propertyUri, Namespaces::getLabelProperties()))
      {
        $valuesOWLLiteral = $datapropertiesValuesMap->get($property);
        
        if(!isset($niDescription[$propertyUri]) ||
           !is_array($niDescription[$propertyUri]))
        {
          $niDescription[$propertyUri] = array();
        }
        
        foreach($valuesOWLLiteral as $valueOWLLiteral)
        {
          if((string)$valueOWLLiteral->getLang() == "" || $this->lang == "" || 
             (string)$valueOWLLiteral->getLang() == $this->lang)
          {
            array_push($niDescription[$propertyUri], array("value" => (string)$valueOWLLiteral->getLiteral(),
                                                           "type" => "rdfs:Literal",
                                                           "lang" => (string)$valueOWLLiteral->getLang()));       
          }
        }
      }
    }
    
    return($niDescription);
  }  
  
  /**
  * Gets the URI of all the classes of all the loaded ontologies
  * 
  * @param mixed $limit The maximum number of results to return with this function
  * @param mixed $offset The place, in the array, where to start returning results.
  * 
  * @return Returns an array of URIs of the classes contained in this OWLAPI instance
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getClassesUri($limit = -1, $offset = 0)
  {
    $classes = $this->ontology->getClassesInSignature();

    $sc = array();
    
    $nb = 0; 
          
    foreach($classes as $class)
    {
      if($limit > -1)
      {
        if($nb >= $offset + $limit)  
        {
          break;
        }
        
        if($nb < $offset)
        {
          $nb++;
          continue;
        }
      }
      
      array_push($sc, (string)java_values($class->toStringID()));
      $nb++;     
    }
    
    return($sc);
  }   
  
  /**
  * Get the number of classes in the ontology
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getNbClasses()
  {
    $classes = $this->ontology->getClassesInSignature();
       
    return(count(java_values($classes)));
  } 

  /**
  * Get the number of object properties in the ontology
  *     
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getNbObjectProperties()
  {
    $properties = $this->ontology->getObjectPropertiesInSignature();
       
    return(count(java_values($properties)));
  }     
  
  /**
  * Get the nubmer of named individuals in the ontology
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getNbNamedIndividuals()
  {
    $namedIndividuals = $this->ontology->getIndividualsInSignature();
       
    return(count(java_values($namedIndividuals)));
  }   

  /**
  * Get the number of data properties in the ontology
  *     
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getNbDataProperties()
  {
    $properties = $this->ontology->getDataPropertiesInSignature();
       
    return(count(java_values($properties)));
  }   
  
  /**
  * Get the number of annotation proeprties in the ontology
  *   
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getNbAnnotationProperties()
  {
    $properties = $this->ontology->getAnnotationPropertiesInSignature();
       
    return(count(java_values($properties)));
  }   
  
  /**
  * Get the list of sub classes URIs of a target class
  * 
  * @param mixed $uri of the class for which to get the list of subclasses
  * @param mixed $direct TRUE, returns the direct sub-classes only; FALSE returns all of them.
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSubClassesUri($uri, $direct = false)
  {    
    // Create a class object.
    $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($uri));
    
    $subClasses;
    
    if($this->useReasoner)
    {
      try
      {
        $subClasses = $this->reasoner->getSubClasses($class, $direct); 
        $subClasses = $subClasses->getFlattened();
      }
      catch(Exception $e)
      {
      }
    } 
    else
    {
      $subClasses = $class->getSubClasses($this->ontology);
    } 

    $sc = array();
          
    foreach($subClasses as $class)
    {
      if(java_instanceof($class, java("org.semanticweb.owlapi.model.OWLClass")))
      {
        array_push($sc, (string)java_values($class->toStringID()));
      }
    }
    
    return($sc);
  }
  
  /**
  * Get the list of super classes URIs of a target class
  * 
  * @param mixed $uri of the class for which to get the list of subclasses
  * @param mixed $direct TRUE, returns the direct super-classes only; FALSE returns all of them.
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSuperClassesUri($uri, $direct = false)
  {
    // Create a class object.
    $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($uri));

    $superClasses;
    
    if($this->useReasoner)
    {
      $superClasses = $this->reasoner->getSuperClasses($class, $direct); 
      $superClasses = $superClasses->getFlattened();
    } 
    else
    {
      $superClasses = $class->getSubClasses($this->ontology);
    } 

    $sc = array();
          
    foreach($classes as $class)
    {
      if(java_instanceof($class, java("org.semanticweb.owlapi.model.OWLClass")))
      {
        array_push($sc, (string)java_values($class->toStringID()));
      }
    }
    
    return($sc);
  }  
  
  /**
  * Get the list of equivalent classes URIs of a target class
  * 
  * @param mixed $uri of the class for which to get the list of equivalent classes
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getEquivalentClassesUri($uri)
  {
    // Create a class object.
    $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($uri));

    $equivalentClasses = $class->getEquivalentClasses($this->ontology); 

    $ec = array();
    
    foreach($equivalentClasses as $class)
    {
      array_push($ec, (string)java_values($class->toStringID()));
    }
    
    return($ec);
  }   
  
  /**
  * Get the list of disjoint classes URIs of a target class
  * 
  * @param mixed $uri of the class for which to get the list of disjoint classes
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getDisjointClassesUri($uri)
  {
    // Create a class object.
    $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($uri));

    $disjointClasses = $class->getDisjointClasses($this->ontology); 

    $dc = array();
    
    foreach($disjointClasses as $class)
    {
      array_push($dc, (string)java_values($class->toStringID()));
    }
    
    return($dc);
  }   
  
  /**
  * Gets the description all the classes of all the loaded ontologies
  * 
  * The array is defined as:
  *   
  *   $classDescription = 
  *                          array( "resource-uri" =>
  *                            array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            ),
  *                            array( "..." => ...)
  *                          );
  * 
  * @param mixed $limit The maximum number of results to return with this function
  * @param mixed $offset The place, in the array, where to start returning results.
  * 
  * @return Returns an array of classes descriptions (OWLClassNodeSet) of the classes contained in this OWLAPI instance
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getClassesDescription($limit = -1, $offset = 0)
  {
    $classes = $this->ontology->getClassesInSignature();
    
    $classDescription = array();

    $nb = 0; 
    
    foreach($classes as $class)
    {
      if($limit > -1)
      {
        if($nb >= $offset + $limit)  
        {
          break;
        }
        
        if($nb < $offset)
        {
          $nb++;
          continue;
        }
      }
      
      $classUri = (string)java_values($class->toStringID());
      $classDescription[$classUri] = array();
      
      $classDescription[$classUri] = $this->_getClassDescription($class);
      $nb++;     
    }
    
    return($classDescription);    
  }    
  
  /**
  * Get the list of sub classes resource description of a target class
  * 
  * The array is defined as:
  *   
  *   $classDescription = 
  *                          array( "resource-uri" =>
  *                            array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            ),
  *                            array( "..." => ...)
  *                          );
  * 
  * @param mixed $uri of the class for which to get the list of subclasses
  * @param boolean $direct Specifies if you want the direct sub-classes or the inherented ones also.
  * @param boolean $hierarchy Specifies that we want a class description specialized for display in a hierarchy control.
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSubClassesDescription($uri, $direct = FALSE, $hierarchy = FALSE)
  {
    // Create a class object.
    $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($uri));
        
    $subClasses;

    if($this->useReasoner)
    {
      try
      {
        $subClasses = $this->reasoner->getSubClasses($class, $direct); 
        $subClasses = $subClasses->getFlattened();
      }
      catch(Exception $e)
      {
      }
    } 
    else
    {
      $subClasses = $class->getSubClasses($this->ontology);
    }   
    
    $classDescription = array();

    foreach($subClasses as $subClass)
    {
      if(java_instanceof($subClass, java("org.semanticweb.owlapi.model.OWLClass")))
      {
        $subClassUri = (string)java_values($subClass->toStringID());
        $classDescription[$subClassUri] = array();
        
        // Skip owl:Nothing and return an empty record for it.
        if($subClassUri == Namespaces::$owl."Nothing")
        {
          continue;
        }
        
        if($hierarchy)
        {
          $classDescription[$subClassUri] = $this->_getClassHierarchyDescription($subClass);
        }
        else
        {
          $classDescription[$subClassUri] = $this->_getClassDescription($subClass);
        }
      }
    }

    return($classDescription);
  }
  
  /**
  * Get the list of disjoint classes resource description of a target class
  * 
  * The array is defined as:
  *   
  *   $classDescription = 
  *                          array( "resource-uri" =>
  *                            array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            ),
  *                            array( "..." => ...)
  *                          );
  * 
  * @param mixed $uri of the class for which to get the list of disjoint classes
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getDisjointClassesDescription($uri)
  {
    // Create a class object.
    $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($uri));
   
    $disjointClasses = $class->getDisjointClasses($this->ontology);   
        
    $classDescription = array();

    foreach($disjointClasses as $disjointClass)
    {                                                         
      if(java_instanceof($range, java("uk.ac.manchester.cs.owl.owlapi.OWLClassImpl")))       
      {      
        $disjointClassUri = (string)java_values($disjointClass->toStringID());
        $classDescription[$disjointClassUri] = array();
        
        $classDescription[$disjointClassUri] = $this->_getClassDescription($disjointClass);
      }
    }
    
    return($classDescription);
  }  
  
  /**
  * Get the list of equivalent classes resource description of a target class
  * 
  * The array is defined as:
  *   
  *   $classDescription = 
  *                          array( "resource-uri" =>
  *                            array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            ),
  *                            array( "..." => ...)
  *                          );
  * 
  * @param mixed $uri of the class for which to get the list of equivalent classes
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getEquivalentClassesDescription($uri)
  {
    // Create a class object.
    $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($uri));
   
    $disjointClasses = $class->getEquivalentClasses($this->ontology);   
        
    $classDescription = array();

    foreach($disjointClasses as $disjointClass)
    {
      if(java_instanceof($disjointClass, java("uk.ac.manchester.cs.owl.owlapi.OWLClassImpl")))       
      {      
        $disjointClassUri = (string)java_values($disjointClass->toStringID());
        $classDescription[$disjointClassUri] = array();
        
        $classDescription[$disjointClassUri] = $this->_getClassDescription($disjointClass);
      }
    }
    
    return($classDescription);
  }  
  
  /**
  * Get the list of super classes resource description of a target class
  * 
  * The array is defined as:
  *   
  *   $classDescription = 
  *                          array( "resource-uri" =>
  *                            array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            ),
  *                            array( "..." => ...)
  *                          );
  * 
  * @param mixed $uri of the class for which to get the list of super classes
  * @param boolean $direct Specifies if you want the direct super-classes or the inherented ones also.
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSuperClassesDescription($uri, $direct = false)
  {
    // Create a class object.
    $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($uri));
        
    $superClasses;
    
    if($this->useReasoner)
    {
      $superClasses = $this->reasoner->getSuperClasses($class, $direct); 
      $superClasses = $superClasses->getFlattened();
    } 
    else
    {
      $superClasses = $class->getSubClasses($this->ontology);
    } 
    
    $classDescription = array();

    foreach($superClasses as $superClass)
    {
      if(java_instanceof($superClass, java("org.semanticweb.owlapi.model.OWLClass")))
      {
        $superClassUri = (string)java_values($superClass->toStringID());
        $classDescription[$superClassUri] = array();
        
        // Skip owl:Nothing and return an empty record for it.
        if($superClassUri == Namespaces::$owl."Nothing")
        {
          continue;
        }      
        
        $classDescription[$superClassUri] = $this->_getClassDescription($superClass);
      }
    }
    
    return($classDescription);
  }
  
  /**
  * Get the URI of all the properties defined in the current ontology
  * 
  * @param mixed $includesDataProperties determine if we add data properties to the returned value
  * @param mixed $includesObjectProperties determine if we add object properties to the returned value
  * @param mixed $includesAnnotationProperties determine if we add annotation properties to the returned value
  * @param mixed $limit The maximum number of results to return with this function
  * @param mixed $offset The place, in the array, where to start returning results.
  * 
  * @returns An array of data properties URIs
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getPropertiesUri($includesDataProperties = TRUE, $includesObjectProperties = FALSE, $includesAnnotationProperties = FALSE, $limit = -1, $offset = 0)
  {
    $propertyUris = array();
    $nb = 0;      
    
    for($i = 0; $i < 3; $i++)
    {
      $properties;      
      
      if($i == 0 && $includesDataProperties)
      {
        $properties = $this->ontology->getDataPropertiesInSignature();
      }
      
      if($i == 1 && $includesObjectProperties)
      {
        $properties = $this->ontology->getObjectPropertiesInSignature();
      }
      
      if($i == 2 && $includesAnnotationProperties)
      {
        $properties = $this->ontology->getAnnotationPropertiesInSignature();
      }
      
      foreach($properties as $property)
      {
        if($limit > -1 )
        {
          if($nb >= $offset + $limit)  
          {
            break;
          }
          
          if($nb < $offset)
          {
            $nb++;
            continue;
          }
        }
        
        $propertyUri = (string)java_values($property->toStringID());
        
        array_push($propertyUris, $propertyUri);      
        $nb++;   
      }
    }
    
    return($propertyUris);    
  }
  
  /**
  * Get the description of all the data properties of the current ontology. The description(s) are returned as 
  * an array of this type:
  * 
  *   $datatypePropertyDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $includesDataProperties determine if we add data properties to the returned value
  * @param mixed $includesObjectProperties determine if we add object properties to the returned value
  * @param mixed $includesAnnotationProperties determine if we add annotation properties to the returned value
  * @param mixed $limit The maximum number of results to return with this function
  * @param mixed $offset The place, in the array, where to start returning results.
  * 
  * @returns An array of data properties description
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getPropertiesDescription($includesDataProperties = TRUE, $includesObjectProperties = FALSE, $includesAnnotationProperties = FALSE, $limit = -1, $offset = 0)
  {
    $propertyDescription = array();

    $nb = 0;        
                   
    for($i = 0; $i < 3; $i++)
    {
      $properties;      
      
      if($i == 0 && $includesDataProperties)
      {
        $properties = $this->ontology->getDataPropertiesInSignature();
      }
      
      if($i == 1 && $includesObjectProperties)
      {
        $properties = $this->ontology->getObjectPropertiesInSignature();
      }
      
      if($i == 2 && $includesAnnotationProperties)
      {
        $properties = $this->ontology->getAnnotationPropertiesInSignature();
      }    
      
      if(isset($properties))
      {
        $properties = java_values($properties);
        
        if(is_array($properties))
        {
          foreach($properties as $property)
          {
            if($limit > -1)
            {
              if($nb >= $offset + $limit)  
              {
                break;
              }
              
              if($nb < $offset)
              {
                $nb++;
                continue;
              }
            }      
            
            $propertyUri = (string)java_values($property->toStringID());
            
            $propertyDescription[$propertyUri] = array();
            
            $propertyDescription[$propertyUri] = $this->_getPropertyDescription($property);      
            $nb++;   
          }    
        }
      }
    }
      
    return($propertyDescription);    
  }    
  
  /**
  * Get the URI of all the sub data properties of a given property. 
  * 
  * @param mixed $uri The URI of the data property for which we want its sub-properties
  * @param mixed $direct TRUE, returns the direct sub properties only; FALSE returns all of them.
  * @param mixed $isDataProperty determine if the property is a data property. If it is not, it is considered an object property.
  * 
  * @returns An array of data properties URIs
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSubPropertiesUri($uri, $direct = FALSE, $isDataProperty = TRUE)
  {  
    $subProperties;

    if($isDataProperty)
    {
      $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $subProperties = $this->reasoner->getSubDataProperties($property, $direct); 
      } 
      else
      {
        $subProperties = $property->getSubProperties($this->ontology);
      }      
    }
    else
    {
      $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $subProperties = $this->reasoner->getSubObjectProperties($property, $direct); 
      } 
      else
      {
        $subProperties = $property->getSubProperties($this->ontology);
      }            
    }

    if($this->useReasoner)
    {
      $subProperties = $subProperties->getFlattened();     
    }
    
    $propertyDescription = array();

    foreach($subProperties as $subProperty)
    {
      if(java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
         java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
         java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
      {
        $subPropertyUri = (string)java_values($subProperty->toStringID());
      
        if($subPropertyUri == "_BOTTOM_DATA_PROPERTY_" ||
           $subPropertyUri == Namespaces::$owl."bottomDataProperty" ||
           $subPropertyUri == "_BOTTOM_OBJECT_PROPERTY_" ||
           $subPropertyUri == Namespaces::$owl."bottomObjectProperty")
        {
          continue;
        }         
      
        array_push($propertyDescription, $subPropertyUri);
      }
    }    
    
    return($propertyDescription);
  }
  
  /**
  * Get the description of all the sub data properties of a given property. The description(s) are returned as 
  * an array of this type:
  * 
  *   $datatypePropertyDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $uri The URI of the data property for which we want its sub-properties
  * @param mixed $direct TRUE, returns the direct sub properties only; FALSE returns all of them.
  * @param mixed $isDataProperty determine if the property is a data property. If it is not, it is considered an object property.
  * 
  * @returns An array of data properties description
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSubPropertiesDescription($uri, $direct = FALSE, $isDataProperty = TRUE)
  {  
    $subProperties;
    
    if($isDataProperty)
    {
      $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $subProperties = $this->reasoner->getSubDataProperties($property, $direct); 
      } 
      else
      {
        $subProperties = $property->getSubProperties($this->ontology);
      }      
    }
    else
    {
      $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $subProperties = $this->reasoner->getSubObjectProperties($property, $direct); 
      } 
      else
      {
        $subProperties = $property->getSubProperties($this->ontology);
      }            
    }

    if($this->useReasoner)
    {
      $subProperties = $subProperties->getFlattened();     
    }    
    
    $propertyDescription = array();

    foreach($subProperties as $subProperty)
    {
      if(java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
         java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
         java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
      {
        $subPropertyUri = (string)java_values($subProperty->toStringID());
        
        if($subPropertyUri == "_BOTTOM_DATA_PROPERTY_" ||
           $subPropertyUri == Namespaces::$owl."bottomDataProperty" ||
           $subPropertyUri == "_BOTTOM_OBJECT_PROPERTY_" ||
           $subPropertyUri == Namespaces::$owl."bottomObjectProperty")
        {
          continue;
        }
        
        $propertyDescription[$subPropertyUri] = array();
        
        $propertyDescription[$subPropertyUri] = $this->_getPropertyDescription($subProperty);
      }
    }

    return($propertyDescription);
  }  
  
  /**
  * Get the URI of all the super data properties of a given property. 
  * 
  * @param mixed $uri The URI of the data property for which we want its super-properties
  * @param mixed $direct TRUE, returns the direct super properties only; FALSE returns all of them.
  * @param mixed $isDataProperty determine if the property is a data property. If it is not, it is considered an object property.
  * 
  * @returns An array of data properties URIs
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSuperPropertiesUri($uri, $direct = FALSE, $isDataProperty = TRUE)
  {  
    $superProperties;
  
    if($isDataProperty)
    {
      $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $superProperties = $this->reasoner->getSuperDataProperties($property, $direct); 
      } 
      else
      {
        $superProperties = $property->getSuperProperties($this->ontology);
      }      
    }
    else
    {
      $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $superProperties = $this->reasoner->getSuperObjectProperties($property, $direct); 
      } 
      else
      {
        $superProperties = $property->getSuperProperties($this->ontology);
      }            
    }

    if($this->useReasoner)
    {
      $superProperties = $superProperties->getFlattened();     
    }    
    
    $propertyDescription = array();

    foreach($superProperties as $superProperty)
    {
      if(java_instanceof($superProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
         java_instanceof($superProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
         java_instanceof($superProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
      {
        $superPropertyUri = (string)java_values($superProperty->toStringID());
        
        switch($superPropertyUri)
        {
          case "_TOP_DATA_PROPERTY_":
          case "http://www.w3.org/2002/07/owl#topDataProperty":
            
            // If a topDataProperty is returned but that an object property was requested, we simply return an empty
            // description.
            if(!$isDataProperty)
            {
              return($propertyDescription);;
            }
          
            $superPropertyUri = Namespaces::$owl."topDataProperty";
          break;
          case "_TOP_OBJECT_PROPERTY_":
          case "http://www.w3.org/2002/07/owl#topObjectProperty":
          
            // If a topObjectProperty is returned but that a data property was requested, we simply return an empty
            // description.
            if($isDataProperty)
            {
              return($propertyDescription);;
            }
            
            $superPropertyUri = Namespaces::$owl."topObjectProperty";
          break;
        }      
        
        array_push($propertyDescription, $superPropertyUri);
      }
    }    
    
    return($propertyDescription);
  }
  
  /**
  * Get the description of all the super data properties of a given property. The description(s) are returned as 
  * an array of this type:
  * 
  *   $datatypePropertyDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $uri The URI of the data property for which we want its super-properties
  * @param mixed $direct TRUE, returns the direct super properties only; FALSE returns all of them.
  * @param mixed $isDataProperty determine if the property is a data property. If it is not, it is considered an object property.
  * 
  * @returns An array of data properties description
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSuperPropertiesDescription($uri, $direct = FALSE, $isDataProperty = TRUE)
  {
    $superProperties;
  
    if($isDataProperty)
    {
      $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $superProperties = $this->reasoner->getSuperDataProperties($property, $direct); 
      } 
      else
      {
        $superProperties = $property->getSuperProperties($this->ontology);
      }      
    }
    else
    {
      $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $superProperties = $this->reasoner->getSuperObjectProperties($property, $direct); 
      } 
      else
      {
        $superProperties = $property->getSuperProperties($this->ontology);
      }            
    }

    if($this->useReasoner)
    {
      $superProperties = $superProperties->getFlattened();     
    }    
    
    $propertyDescription = array();

    foreach($superProperties as $superProperty)
    {
      if(java_instanceof($superProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
         java_instanceof($superProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
         java_instanceof($superProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
      {
        $superPropertyUri = (string)java_values($superProperty->toStringID());

        switch($superPropertyUri)
        {
          case "_TOP_DATA_PROPERTY_":
          case "http://www.w3.org/2002/07/owl#topDataProperty":
          
            // If a topDataProperty is returned but that an object property was requested, we simply return an empty
            // description.
            if(!$isDataProperty)
            {
              return($propertyDescription);;
            }
            
            $superPropertyUri = Namespaces::$owl."topDataProperty";
          break;
          case "_TOP_OBJECT_PROPERTY_":
          case "http://www.w3.org/2002/07/owl#topObjectProperty":
          
            // If a topObjectProperty is returned but that a data property was requested, we simply return an empty
            // description.
            if($isDataProperty)
            {
              return($propertyDescription);;
            }
          
            $superPropertyUri = Namespaces::$owl."topObjectProperty";
          break;
        }
        
        $propertyDescription[$superPropertyUri] = array();
        
        $propertyDescription[$superPropertyUri] = $this->_getPropertyDescription($superProperty);
      }
    }
    
    return($propertyDescription);
  }    
  
  /**
  * Get the URI of all the equivalent data properties of a given property. 
  * 
  * @param mixed $uri The URI of the data property for which we want its equivalent-properties
  * @param mixed $isDataProperty determine if the property is a data property. If it is not, it is considered an object property.
  * 
  * @returns An array of data properties URIs
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getEquivalentPropertiesUri($uri, $isDataProperty = TRUE)
  {  
    $equivalentProperties;
    $property;
    
    if($this->useReasoner)
    {
      if($isDataProperty)
      {
        $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
        $equivalentProperties = $this->reasoner->getEquivalentDataProperties($property); 
      }
      else
      {
        $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
        $equivalentProperties = $this->reasoner->getEquivalentObjectProperties($property); 
      }
      
      $equivalentProperties = $equivalentProperties->getEntitiesMinus($property);
    }
    else
    {
      if($isDataProperty)
      {
        $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      }
      else
      {
        $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      }
      
      $equivalentProperties = $equivalentProperties->getEntitiesMinus($property);
    }

    $propertyDescription = array();

    foreach($equivalentProperties as $equivalentProperty)
    {
      if(java_instanceof($equivalentProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
         java_instanceof($equivalentProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
         java_instanceof($equivalentProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
      {
        $equivalentPropertyUri = (string)java_values($equivalentProperty->toStringID());
        
        array_push($propertyDescription, $equivalentPropertyUri);
      }
    }    
    
    return($propertyDescription);
  }
  
  /**
  * Get the description of all the equivalent data properties of a given property. The description(s) are returned as 
  * an array of this type:
  * 
  *   $datatypePropertyDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $uri The URI of the data property for which we want its equivalent-properties
  * @param mixed $isDataProperty determine if the property is a data property. If it is not, it is considered an object property.
  * 
  * @returns An array of data properties description
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getEquivalentPropertiesDescription($uri, $isDataProperty = TRUE)
  {
    $equivalentProperties;
    $property;
    
    if($this->useReasoner)
    {
      if($isDataProperty)
      {
        $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
        $equivalentProperties = $this->reasoner->getEquivalentDataProperties($property); 
      }
      else
      {
        $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
        $equivalentProperties = $this->reasoner->getEquivalentObjectProperties($property); 
      }
      
      $equivalentProperties = $equivalentProperties->getEntitiesMinus($property);
    }
    else
    {
      if($isDataProperty)
      {
        $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      }
      else
      {
        $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      }
      
      $equivalentProperties = $equivalentProperties->getEntitiesMinus($property);
    }
    
    $propertyDescription = array();

    foreach($equivalentProperties as $equivalentProperty)
    {
      if(java_instanceof($equivalentProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
         java_instanceof($equivalentProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
         java_instanceof($equivalentProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
      {
        $equivalentPropertyUri = (string)java_values($equivalentProperty->toStringID());
        $propertyDescription[$equivalentPropertyUri] = array();
        
        $propertyDescription[$equivalentPropertyUri] = $this->_getPropertyDescription($equivalentProperty);
      }
    }
    
    return($propertyDescription);
  }      
  
  /**
  * Get the URI of all the disjoint data properties of a given property. 
  * 
  * @param mixed $uri The URI of the data property for which we want its disjoint-properties
  * @param mixed $isDataProperty determine if the property is a data property. If it is not, it is considered an object property.
  * 
  * @returns An array of data properties URIs
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getDisjointPropertiesUri($uri, $isDataProperty = TRUE)
  {  
    $disjointProperties;
    
    if($isDataProperty)
    {
      $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $disjointProperties = $this->reasoner->getDisjointDataProperties($property); 
        $disjointProperties = $disjointProperties->getFlattened();
      } 
      else
      {
        $disjointProperties = $class->getDisjointProperties($this->ontology);
      }             
    }
    else
    {
      $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $disjointProperties = $this->reasoner->getDisjointObjectProperties($property); 
        $disjointProperties = $disjointProperties->getFlattened();
      } 
      else
      {
        $disjointProperties = $class->getDisjointProperties($this->ontology);
      }         
    }
    
    if($this->useReasoner)
    {
      $disjointProperties = $disjointProperties->getFlattened();  
    }

    $propertyDescription = array();

    foreach($disjointProperties as $disjointProperty)
    {
      if(java_instanceof($disjointProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
         java_instanceof($disjointProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
         java_instanceof($disjointProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
      {
        $disjointPropertyUri = (string)java_values($disjointProperty->toStringID());
        
        array_push($propertyDescription, $disjointPropertyUri);
      }
    }    
    
    return($propertyDescription);
  }
  
  /**
  * Get the description of all the disjoint data properties of a given property. The description(s) are returned as 
  * an array of this type:
  * 
  *   $datatypePropertyDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $uri The URI of the data property for which we want its disjoint-properties
  * @param mixed $isDataProperty determine if the property is a data property. If it is not, it is considered an object property.
  * 
  * @returns An array of data properties description
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getDisjointPropertiesDescription($uri, $isDataProperty = TRUE)
  {
    $disjointProperties;
    
    if($isDataProperty)
    {
      $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $disjointProperties = $this->reasoner->getDisjointDataProperties($property); 
        $disjointProperties = $disjointProperties->getFlattened();
      } 
      else
      {
        $disjointProperties = $class->getDisjointProperties($this->ontology);
      }             
    }
    else
    {
      $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      
      if($this->useReasoner)
      {
        $disjointProperties = $this->reasoner->getDisjointObjectProperties($property); 
        $disjointProperties = $disjointProperties->getFlattened();
      } 
      else
      {
        $disjointProperties = $class->getDisjointProperties($this->ontology);
      }         
    }
    
    if($this->useReasoner)
    {
      $disjointProperties = $disjointProperties->getFlattened();  
    }
    
    $propertyDescription = array();

    foreach($disjointProperties as $disjointProperty)
    {
      if(java_instanceof($disjointProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
         java_instanceof($disjointProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
         java_instanceof($disjointProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
      {
        $disjointPropertyUri = (string)java_values($disjointProperty->toStringID());
        $propertyDescription[$disjointPropertyUri] = array();
        
        $propertyDescription[$disjointPropertyUri] = $this->_getPropertyDescription($disjointProperty);
      }
    }
    
    return($propertyDescription);
  }  
  
  /**
  * Gets an OWL property that has the specified IRI 
  * 
  * @param string $iri The IRI of the individual to be obtained 
  * 
  * @return Returns a OWLProperty or OWLAnnotationProperty; null if not existing.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLProperty.html
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLAnnotationProperty.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function _getProperty($iri)
  {
    // Create a class object.
    
    $entities = $this->ontology->getEntitiesInSignature(java("org.semanticweb.owlapi.model.IRI")->create($iri));
    
    foreach($entities as $entity)
    {
      if(java_instanceof($entity, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
         java_instanceof($entity, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
         java_instanceof($entity, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
      {
        return($entity);
      }
    }
    
    return(null);
  }    
  
  /**
  * Convert the OWLAPI datatype property description into an array describing the class. This array is a simplification
  * of the OWLAPI that is used by other parts of this API, along with other scripts that uses this
  * API such as the various ontology related structWSF endpoints.
  * 
  * The array is defined as:
  *   
  *   $datatypePropertyDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $property The OWLAPI datatype property instance.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/uk/ac/manchester/cs/owl/owlapi/OWLDataPropertyImpl.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function _getPropertyDescription($property)
  {
    $propertyDescription = array();
    
    $pType = "owl:DatatypeProperty";
    
    if(!isset($property))
    {
      return($propertyDescription);
    }
    
    switch($property->getEntityType()->getName())
    {
      case "DataProperty":
        $pType = "owl:DatatypeProperty";
      break;
      
      case "ObjectProperty":
        $pType = "owl:ObjectProperty";
      break;
      
      case "AnnotationProperty":
        $pType = "owl:AnnotationProperty";
      break;
    }
    
    $propertyDescription["type"] = array($pType);       
                       
    // Get all the annotations
    $annotations = $property->getAnnotations($this->ontology);
    
    foreach($annotations as $annotation)
    {
      $info = $this->getAnnotationInfo($annotation);
      
      if(!isset($propertyDescription[$info["property"]]) || 
         is_array($propertyDescription[$info["property"]]) === FALSE)
      {
        $propertyDescription[$info["property"]] = array(); 
      }

      
      if(isset($info["uri"]))
      {      
        array_push($propertyDescription[$info["property"]], array("uri" => $info["uri"],
                                                                  "reify" => $info["reify"])); 
      }
      else
      {
        if($info["lang"] == "" || $this->lang == "" || $info["lang"] == $this->lang)
        {
          array_push($propertyDescription[$info["property"]], array("value" => $info["value"],
                                                                    "type" => $info["type"],
                                                                    "lang" => $info["lang"])); 
        }
      }
    }
     
     
    if(java_values($property->isOWLDataProperty()) || java_values($property->isOWLObjectProperty()))
    {       
      // Get super properties 
      $superProperties = $property->getSuperProperties($this->ontology);
      
      foreach($superProperties as $superProperty)
      {
        $spUri = (string)java_values($superProperty->toStringID());
              
        if(!isset($propertyDescription[Namespaces::$rdfs."subPropertyOf"]) || 
           is_array($propertyDescription[Namespaces::$rdfs."subPropertyOf"]) === FALSE)
        {
          $propertyDescription[Namespaces::$rdfs."subPropertyOf"] = array(); 
        }

        array_push($propertyDescription[Namespaces::$rdfs."subPropertyOf"], array("uri" => $spUri,
                                                                                  "reify" => array(
                                                                                    "wsf:objectLabel" => array($this->getPrefLabel($superProperty))
                                                                                  ))); 
      }
      
      // Ensure that if it has no subPropertyOf relationship, that it at least has
      // topData/ObjectProperty as super properties.
      if(!isset($propertyDescription[Namespaces::$rdfs."subPropertyOf"]) || (is_array($propertyDescription[Namespaces::$rdfs."subPropertyOf"]) === FALSE &&
         ((string)java_values($property->toStringID()) != "_TOP_DATA_PROPERTY_" && (string)java_values($property->toStringID()) != "_TOP_OBJECT_PROPERTY_" &&
          (string)java_values($property->toStringID()) != Namespaces::$owl."topDataProperty" && (string)java_values($property->toStringID()) != Namespaces::$owl."topObjectProperty")))
      {
        $propertyDescription[Namespaces::$rdfs."subPropertyOf"] = array(); 
        array_push($propertyDescription[Namespaces::$rdfs."subPropertyOf"], array("uri" => (java_values($property->isOWLObjectProperty()) ? Namespaces::$owl."topObjectProperty" : Namespaces::$owl."topDataProperty"),
                                                                                  "reify" => array(
                                                                                    "wsf:objectLabel" => array((java_values($property->isOWLObjectProperty()) ? "top Object Property" : "top Data Property"))
                                                                                  )));         
      }    
      
      // Specify Super Properties Of properties
      $subProperties;

      if(java_values($property->isOWLDataProperty()))
      {                                               
        if($this->useReasoner)
        {
          $subProperties = $this->reasoner->getSubDataProperties($property, TRUE); 
          $subProperties = $subProperties->getFlattened();
        } 
        else
        {
          $subProperties = $property->getSubProperties($this->ontology);
        }               
        
        foreach($subProperties as $subProperty)
        {
          if(java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
             java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
             java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
          {
            $spUri = (string)java_values($subProperty->toStringID());
            
            if($spUri == Namespaces::$owl."bottomDataProperty" ||
               $spUri == Namespaces::$owl."topDataProperty")
            {
              continue;
            }
                  
            if(!isset($propertyDescription[Namespaces::$umbel."superPropertyOf"]) || 
               is_array($propertyDescription[Namespaces::$umbel."superPropertyOf"]) === FALSE)
            {
              $propertyDescription[Namespaces::$umbel."superPropertyOf"] = array(); 
            }

            array_push($propertyDescription[Namespaces::$umbel."superPropertyOf"], array("uri" => $spUri,
                                                                                   "reify" => array(
                                                                                    "wsf:objectLabel" => array($this->getPrefLabel($subProperty))
                                                                                   ))); 
          }
        }
      }
      else if(java_values($property->isOWLObjectProperty()))
      {   
        if($this->useReasoner)
        {
          $subProperties = $this->reasoner->getSubObjectProperties($property, TRUE); 
          $subProperties = $subProperties->getFlattened();
        } 
        else
        {
          $subProperties = $property->getSubProperties($this->ontology);
        }        
        
        foreach($subProperties as $subProperty)
        {
          if(java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLObjectProperty")) ||
             java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLDataProperty")) ||
             java_instanceof($subProperty, java("org.semanticweb.owlapi.model.OWLAnnotationProperty")))      
          {
            $spUri = (string)java_values($subProperty->toStringID());
            
            if($spUri == Namespaces::$owl."bottomObjectProperty" ||
               $spUri == Namespaces::$owl."topObjectProperty")
            {
              continue;
            }
                  
            if(!isset($propertyDescription[Namespaces::$umbel."superPropertyOf"]) || 
               is_array($propertyDescription[Namespaces::$umbel."superPropertyOf"]) === FALSE)
            {
              $propertyDescription[Namespaces::$umbel."superPropertyOf"] = array(); 
            }

            array_push($propertyDescription[Namespaces::$umbel."superPropertyOf"], array("uri" => $spUri,
                                                                                   "reify" => array(
                                                                                    "wsf:objectLabel" => array($this->getPrefLabel($subProperty))
                                                                                   ))); 
          }
        }  
      }
      
      // Get Equivalent properties
      $equivalentProperties = $property->getEquivalentProperties($this->ontology);
      
      foreach($equivalentProperties as $equivalentProperty)
      {
        $epUri = (string)java_values($equivalentProperty->toStringID());
              
        if(!isset($propertyDescription[Namespaces::$owl."equivalentProperty"]) || 
           is_array($propertyDescription[Namespaces::$owl."equivalentProperty"]) === FALSE)
        {
          $propertyDescription[Namespaces::$owl."equivalentProperty"] = array(); 
        }
        
        array_push($propertyDescription[Namespaces::$owl."equivalentProperty"], array("uri" => $epUri,
                                                                                      "reify" => array(
                                                                                        "wsf:objectLabel" => array($this->getPrefLabel($equivalentProperty))
                                                                                      ))); 
      }                                                                   
      
      // Get disjoint properties    
      $disjointProperties = $property->getDisjointProperties($this->ontology);
      
      foreach($disjointProperties as $disjointProperty)
      {
        $dpUri = (string)java_values($disjointProperty->toStringID());
              
        if(!isset($propertyDescription[Namespaces::$owl."propertyDisjointWith"]) || 
           is_array($propertyDescription[Namespaces::$owl."propertyDisjointWith"]) === FALSE)
        {
          $propertyDescription[Namespaces::$owl."propertyDisjointWith"] = array(); 
        }
        
        array_push($propertyDescription[Namespaces::$owl."propertyDisjointWith"], array("uri" => $dpUri,
                                                                                        "wsf:objectLabel" => array(
                                                                                          "wsf:objectLabel" => $this->getPrefLabel($disjointProperty)
                                                                                        ))); 
      }  
      
      // Get inverse property
      if(java_values($property->isOWLObjectProperty()))
      {
        $inverseProperties = $property->getInverses($this->ontology);
        
        foreach($inverseProperties as $inverseProperty)
        {
          $ipUri = (string)java_values($inverseProperty->toStringID());
                
          if(!isset($propertyDescription[Namespaces::$owl."inverseOf"]) || 
             is_array($propertyDescription[Namespaces::$owl."inverseOf"]) === FALSE)
          {
            $propertyDescription[Namespaces::$owl."inverseOf"] = array(); 
          }
          
          array_push($propertyDescription[Namespaces::$owl."inverseOf"], array("uri" > $ipUri,
                                                                               "reify" => array(
                                                                               "wsf:objectLabel" => array($this->getPrefLabel($inverseProperty))
                                                                               )));  
        }       
      }
      
      // Get domain
      $domains = $property->getDomains($this->ontology);
      
      foreach($domains as $domain)
      {
        if(java_instanceof($domain, java("uk.ac.manchester.cs.owl.owlapi.OWLClassImpl")))       
        {
          if(!java_values($domain->isOWLNothing()))
          {
            $domainClassUri = (string)java_values($domain->toStringID());
                  
            if(!isset($propertyDescription[Namespaces::$rdfs."domain"]) || 
               is_array($propertyDescription[Namespaces::$rdfs."domain"]) === FALSE)
            {
              $propertyDescription[Namespaces::$rdfs."domain"] = array(); 
            }
            
            array_push($propertyDescription[Namespaces::$rdfs."domain"], array("uri" => $domainClassUri,
                                                                               "reify" => array(
                                                                                "wsf:objectLabel" => array($this->getPrefLabel($domain))
                                                                               ))); 
          }
        }
      }

      // Get range
      $ranges = $property->getRanges($this->ontology);
      
      foreach($ranges as $range)
      {
        if((java_instanceof($range, java("uk.ac.manchester.cs.owl.owlapi.OWLClassImpl")) && $range->isOWLNothing()) ||
           java_instanceof($range, java("uk.ac.manchester.cs.owl.owlapi.OWLDatatypeImpl")))       
        {
          $rangeClassUri = (string)java_values($range->toStringID());
                
          if(!isset($propertyDescription[Namespaces::$rdfs."range"]) || 
             is_array($propertyDescription[Namespaces::$rdfs."range"]) === FALSE)
          {
            $propertyDescription[Namespaces::$rdfs."range"] = array(); 
          }
          
          array_push($propertyDescription[Namespaces::$rdfs."range"], array("uri" => $rangeClassUri,
                                                                            "reify" => array(
                                                                              "wsf:objectLabel" => array($this->getPrefLabel($range))
                                                                            ))); 
        }
      }
    }
    
    return($propertyDescription);      
  }
  
  /**
  * Get the preferred label of a resource of this ontology
  * 
  * @param mixed $entity Reference to an OWLEntity
  * @return string
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function getPrefLabel($entity)
  {
    $prefLabelAttributes = array(
      Namespaces::$rdfs."label",
      Namespaces::$skos_2004."prefLabel",
      Namespaces::$skos_2008."prefLabel",
      Namespaces::$umbel."prefLabel",
      Namespaces::$dcterms."title",
      Namespaces::$dc."title",
      Namespaces::$iron."prefLabel"
    );
    
    $altLabelAttributes = array(
      Namespaces::$skos_2004."altLabel",
      Namespaces::$skos_2008."altLabel",
      Namespaces::$umbel."altLabel",
      Namespaces::$iron."altLabel"
    );
    
    $altLabelFound = "";

    // Get all the annotations
    if(strtolower((string)$entity->getEntityType()->getName()) == "namedindividual")
    {  
      // If we are manipulating a named invidual, we first check if a data property is used
      // to describes its preflabel. If not, we will continue and check for annotations.
      $datapropertiesValuesMap = $entity->getDataPropertyValues($this->ontology);
      
      $keys = $datapropertiesValuesMap->keySet();
      $size = java_values($datapropertiesValuesMap->size());
      
      foreach($keys as $property)
      {
        $propertyUri = (string)java_values($property->toStringID());
        
        $valuesOWLLiteral = $datapropertiesValuesMap->get($property);
        
        if(array_search($propertyUri, $prefLabelAttributes) !== FALSE)
        {
          foreach($valuesOWLLiteral as $valueOWLLiteral)
          {        
            return((string)$valueOWLLiteral->getLiteral());
          }        
        }
        
        if(array_search($propertyUri, $altLabelAttributes) !== FALSE)
        {
          foreach($valuesOWLLiteral as $valueOWLLiteral)
          {        
            $altLabelFound = (string)$valueOWLLiteral->getLiteral();
          }        
        }        
      }
    }
    
    $annotations = $entity->getAnnotations($this->ontology);
    
    foreach($annotations as $annotation)
    {
      $info = $this->getAnnotationInfo($annotation, FALSE);
      
      if(array_search($info["property"], $prefLabelAttributes) !== FALSE)
      {
        return($info["value"]);
      }
      
      if(array_search($info["property"], $altLabelAttributes) !== FALSE)
      {
        $altLabelFound = $info["value"];
      }      
    }
    
    // If no pref labels have been found, but that an alternative label exists, we return it immediately.
    if($altLabelFound != "")
    {
      return($altLabelFound);
    }
    
    $uri = (string)java_values($entity->toStringID());    
    
    if(strrpos($uri, "#"))
    {
      $uri = substr($uri, strrpos($uri, "#") + 1);
      
      // Remove non alpha-num and replace them by spaces
      $uri = preg_replace("/[^A-Za-z0-9]/", " ", $uri);      
      
      // Split upper-case words into seperate words
      $uriArr = preg_split('/(?=[A-Z])/', $uri);
      $uri = implode(" ", $uriArr);
      
      return($uri);
    }

    if(strrpos($uri, "/"))
    {
      $uri = substr($uri, strrpos($uri, "/") + 1);
      
      // Remove non alpha-num and replace them by spaces
      $uri = preg_replace("/[^A-Za-z0-9]/", " ", $uri);      
      
      // Split upper-case words into seperate words
      $uriArr = preg_split('/(?=[A-Z])/', $uri);
      $uri = implode(" ", $uriArr);
      
      return($uri);
    }

    return($uri);    
  }
  
  /**
  * Get the list of classes description for the sub-classes described in this ontology. 
  * The class description being returned is a lightweight version of the full "description" 
  * mode. The goal is to manipulate and transmit a simpler structure that would be used by 
  * user interfaces that wants to display some parts of the hierarchy of an ontology. What 
  * is returned is all the annotation properties (used to get some label to display for one
  * of the sub-class) and a possible attribute: "sco:hasSubClass" which has "true" as value. 
  * If this triple exists, it means that the sub-class has itself other subclasses (this is 
  * mainly used to be able to display an "extend" button in a tree control).
  * 
  * The array is defined as:
  *   
  *   $classDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $class The OWLAPI class instance.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/uk/ac/manchester/cs/owl/owlapi/OWLClassImpl.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function _getClassHierarchyDescription($class)
  {
    $classDescription = array();
    
    // Get the types of the entity
    $classDescription["type"] = array("owl:".$class->getEntityType()->getName());       
    
    // Get all the annotations
    $annotations = $class->getAnnotations($this->ontology);

    foreach($annotations as $annotation)
    {
      $info = $this->getAnnotationInfo($annotation);
      
      if(!isset($classDescription[$info["property"]]) ||
         is_array($classDescription[$info["property"]]) === FALSE)
      {
        $classDescription[$info["property"]] = array(); 
      }

      if(isset($info["uri"]))
      {      
        array_push($classDescription[$info["property"]], array("uri" => $info["uri"],
                                                           "reify" => $info["reify"]));     
      }
      else
      {
        if($info["lang"] == "" || $this->lang == "" || $info["lang"] == $this->lang)
        {
          array_push($classDescription[$info["property"]], array("value" => $info["value"],
                                                             "type" => $info["type"],
                                                             "lang" => $info["lang"]));     
        }
      }
    } 
    
    // Specify Super Classes Of properties   
    if($this->useReasoner)
    {
      $subClasses = $this->reasoner->getSubClasses($class, TRUE); 
      $subClasses = $subClasses->getFlattened();
    } 
    else
    {
      $subClasses = $class->getSubClasses($this->ontology);
    }             

    foreach($subClasses as $subClass)
    {
      if(java_instanceof($subClass, java("org.semanticweb.owlapi.model.OWLClass")))
      {
        $scUri = (string)java_values($subClass->toStringID());
        
        if($scUri == Namespaces::$owl."Nothing")
        {
          break;
        }

        $classDescription[Namespaces::$sco."hasSubClasses"] = array(array("value" => "true",
                                                                          "type" => "rdfs:Literal",
                                                                          "lang" => ""));   
        break;
      }  
    }    
    
    return($classDescription);  
  }
  
  /**
  * Convert the OWLAPI class description into an array describing the class. This array is a simplification
  * of the OWLAPI that is used by other parts of this API, along with other scripts that uses this
  * API such as the various ontology related structWSF endpoints.
  * 
  * The array is defined as:
  *   
  *   $classDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @param mixed $class The OWLAPI class instance.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/uk/ac/manchester/cs/owl/owlapi/OWLClassImpl.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function _getClassDescription($class)
  {
    $classDescription = array();
    
    // Get the types of the entity    
    $classDescription["type"] = array("owl:".$class->getEntityType()->getName());                                                             
    
    // Get all the annotations
    $annotations = $class->getAnnotations($this->ontology);

    foreach($annotations as $annotation)
    {
      $info = $this->getAnnotationInfo($annotation);
      
      if(!isset($classDescription[$info["property"]]) ||
         is_array($classDescription[$info["property"]]) === FALSE)
      {
        $classDescription[$info["property"]] = array(); 
      }

      if(isset($info["uri"]))
      {      
        array_push($classDescription[$info["property"]], array("uri" => $info["uri"],
                                                               "reify" => $info["reify"]));       
      }
      else
      {
        if($info["lang"] == "" || $this->lang == "" || $info["lang"] == $this->lang)
        {
          array_push($classDescription[$info["property"]], array("value" => $info["value"],
                                                                 "type" => $info["type"],
                                                                 "lang" => $info["lang"]));       
        }
      }
    }
    
    // Get Sub Classes Of properties 
    if($this->useReasoner)
    {
      $superClasses = $this->reasoner->getSuperClasses($class, TRUE); 
      $superClasses = $superClasses->getFlattened();
    } 
    else
    {
      $superClasses = $class->getSuperClasses($this->ontology);
    }           
    
    foreach($superClasses as $superClass)
    {
      if(java_instanceof($superClass, java("org.semanticweb.owlapi.model.OWLClass")))
      {
        // Since getSuperClasses returns a set of OWLClassExpression, then we have to make sure that
        // we only keep the OWLClassImpl for this process.
        /*if(!java_instanceof($superClass, java("uk.ac.manchester.cs.owl.owlapi.OWLClassImpl")))
        {
          continue;
        }*/
        
        $scUri = (string)java_values($superClass->toStringID());
              
        if(!isset($classDescription[Namespaces::$rdfs."subClassOf"]) ||
           is_array($classDescription[Namespaces::$rdfs."subClassOf"]) === FALSE)
        {
          $classDescription[Namespaces::$rdfs."subClassOf"] = array(); 
        }

        array_push($classDescription[Namespaces::$rdfs."subClassOf"], array("uri" => $scUri,
                                                                           "reify" => array(
                                                                            "wsf:objectLabel" => array($this->getPrefLabel($superClass))
                                                                           ))); 
      }
    } 
    
    // Specify Super Classes Of properties   
    if($this->useReasoner)
    {
      $subClasses = $this->reasoner->getSubClasses($class, TRUE); 
      $subClasses = $subClasses->getFlattened();
    } 
    else
    {
      $subClasses = $class->getSubClasses($this->ontology);
    }             
    
    foreach($subClasses as $subClass)
    {      
      if(java_instanceof($subClass, java("org.semanticweb.owlapi.model.OWLClass")))
      {
        $scUri = (string)java_values($subClass->toStringID());
        
        if($scUri == Namespaces::$owl."Nothing")
        {
          continue;
        }
              
        if(!isset($classDescription[Namespaces::$umbel."superClassOf"]) || 
           is_array($classDescription[Namespaces::$umbel."superClassOf"]) === FALSE)
        {
          $classDescription[Namespaces::$umbel."superClassOf"] = array(); 
        }

        array_push($classDescription[Namespaces::$umbel."superClassOf"], array("uri" => $scUri,
                                                                               "reify" => array(
                                                                                "wsf:objectLabel" => array($this->getPrefLabel($subClass))
                                                                               ))); 
      }
    }    
    
    // Get Equivalent Classes
    $equivalentClasses = $class->getEquivalentClasses($this->ontology);
    
    foreach($equivalentClasses as $equivalentClass)
    {
      if(java_instanceof($equivalentClass, java("uk.ac.manchester.cs.owl.owlapi.OWLClassImpl")))       
      {      
        $ecUri = (string)java_values($equivalentClass->toStringID());
              
        if(!isset($classDescription[Namespaces::$owl."equivalentClass"]) ||
           is_array($classDescription[Namespaces::$owl."equivalentClass"]) === FALSE)
        {
          $classDescription[Namespaces::$owl."equivalentClass"] = array(); 
        }
        
        array_push($classDescription[Namespaces::$owl."equivalentClass"], array("uri" => $ecUri,
                                                                                "reify" => array(
                                                                                 "wsf:objectLabel" => array($this->getPrefLabel($equivalentClass))
                                                                                ))); 
      }
    }  
    
    // Get disjoint classes    
    $disjointClasses = $class->getDisjointClasses($this->ontology);
    
    foreach($disjointClasses as $disjointClass)
    {         
      if(java_instanceof($disjointClass, java("uk.ac.manchester.cs.owl.owlapi.OWLClassImpl")))       
      {                                         
        $dcUri = (string)java_values($disjointClass->toStringID());
              
        if(!isset($classDescription[Namespaces::$owl."disjointWith"]) ||
           is_array($classDescription[Namespaces::$owl."disjointWith"]) === FALSE)
        {
          $classDescription[Namespaces::$owl."disjointWith"] = array(); 
        }
        
        array_push($classDescription[Namespaces::$owl."disjointWith"], array("uri" => $dcUri,
                                                                             "reify" => array(
                                                                              "wsf:objectLabel" => array($this->getPrefLabel($disjointClass))
                                                                             ))); 
      }
    }  
    
    return($classDescription);  
  }  
  
  /**
  * Convert the OWLAPI ontology description into an array describing the ontology's annotations. This array 
  * is a simplification of the OWLAPI that is used by other parts of this API.
  * 
  * The array is defined as:
  *   
  *   $classDescription = array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            )
  * 
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLOntology.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getOntologyDescription()
  {
    $ontologyDescription = array();
    
    $ontologyDescription["type"] = array("owl:Ontology");       
    
    // Get all the annotations
    $annotations = $this->ontology->getAnnotations();
    
    foreach($annotations as $annotation)
    {
      $info = $this->getAnnotationInfo($annotation);
      
      if(!isset($ontologyDescription[$info["property"]]) || 
         is_array($ontologyDescription[$info["property"]]) === FALSE)
      {
        $ontologyDescription[$info["property"]] = array(); 
      }

      if(isset($info["uri"]))
      {      
        array_push($ontologyDescription[$info["property"]], array("uri" => $info["uri"],
                                                                  "reify" => $info["reify"]));       
      }
      else
      {
        if($info["lang"] == "" || $this->lang == "" || $info["lang"] == $this->lang)
        {
          array_push($ontologyDescription[$info["property"]], array("value" => $info["value"],
                                                                    "type" => $info["type"],
                                                                    "lang" => $info["lang"]));       
        }
      }
    }
    
    // Add a rdfs:isDefinedBy triple to make sure the requester has a reference to the Ontology URI 
    // This over-write anything that could be defined within the ontology.
    $ontologyDescription[Namespaces::$rdfs."isDefinedBy"] = array(); 
    
    array_push($ontologyDescription[Namespaces::$rdfs."isDefinedBy"], array("uri" => $this->getBaseUri()));     
    
    return($ontologyDescription);  
  }  

  /**
  * Gets an OWL class that has the specified IRI 
  * 
  * @param string $iri The IRI of the individual to be obtained 
  * 
  * @return Returns a OWLClass; null if not existing.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLClass.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function _getClass($iri)
  {
    $entities = $this->ontology->getEntitiesInSignature(java("org.semanticweb.owlapi.model.IRI")->create($iri));

    foreach($entities as $entity)
    {
      if(java_instanceof($entity, java("org.semanticweb.owlapi.model.OWLClass")))
      {
        return($entity);
      }
    }    

    return($class);
  }  

  /**
  * Gets an OWL Entity that has the specified IRI 
  * 
  * @param string $iri The IRI of the entity to be obtained 
  * 
  * @return Returns a OWLEntity; null if not existing.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLEntity.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function _getEntity($iri)
  {
    // Create a class object.
    $entities = $this->ontology->getEntitiesInSignature(java("org.semanticweb.owlapi.model.IRI")->create($iri));
    
    foreach($entities as $entity)
    {
      return($entity);
    }    
    
    return(null);
  }  
  
    
  /**
  * Gets all OWL Entities in the signature of the ontology for that IRI
  * 
  * @param string $iri The IRI of the entities to be obtained 
  * 
  * @return Returns a array of OWLEntity; empty array if not existing.
  * 
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLEntity.html
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function _getEntities($iri)
  {
    // Create a class object.
    $entities = $this->ontology->getEntitiesInSignature(java("org.semanticweb.owlapi.model.IRI")->create($iri));
    
    $entitiesArr = array();
    
    foreach($entities as $entity)
    {
       array_push($entitiesArr, $entity);
    }    
    
    return($entitiesArr);
  } 
  
  /**
  * Gets the individuals URI which are instances of the specified class expression.
  * 
  * @param string $classUri The class expression whose instances are to be retrieved. If the value is "all", it
  *                         means that it returns all the named individuals defined in the entire ontology.
  *                         otherwise just the named individuals that belongs to the class' uri
  * @param boolean $direct Specifies if the direct instances should be retrieved (true), or if all instances should 
  *                        be retrieved (false). 
  * @param mixed $limit The maximum number of results to return with this function
  * @param mixed $offset The place, in the array, where to start returning results.
  * 
  * @returns An array of URI which are instance of the specified class expression
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getNamedIndividualsUri($classUri = "all", $direct = true, $limit = -1, $offset = 0)
  {
    // Create a class object.
    $individuals;
    
    if($classUri != "all")
    {
      $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($classUri));

      if($this->useReasoner)
      {
        $individuals = $this->reasoner->getInstances($class, $direct); 
        $individuals = $individuals->getFlattened();
      } 
      else
      {
        $individuals = $class->getIndividuals($this->ontology);
      }       
    }
    else
    {
      $individuals = $this->ontology->getIndividualsInSignature();
    }

    $is = array();
    
    $nb = 0; 
          
    foreach($individuals as $individual)
    {
      if(java_instanceof($individual, java("org.semanticweb.owlapi.model.OWLNamedIndividual")))
      {
        if($limit > -1)
        {
          if($nb >= $offset + $limit)  
          {
            break;
          }
          
          if($nb < $offset)
          {
            $nb++;
            continue;
          }
        }
        
        array_push($is, (string)java_values($individual->toStringID()));
        $nb++;     
      }
    }    
    
    return($is);    
  }
  
  /**
  * Gets the description all the named individuals of all the loaded ontologies
  * 
  * The array is defined as:
  *   
  *   $classDescription = 
  *                          array( "resource-uri" =>
  *                            array(
  *                              "predicate-uri" => array(
  *                                                       array(
  *                                                               "value" => "the value of the predicate",
  *                                                               "type" => "the type of the value",
  *                                                               "lang" => "language reference of the value (if literal)"
  *                                                            ),
  *                                                       array(...)
  *                                                     ),
  *                              "..." => array(...)
  *                            ),
  *                            array( "..." => ...)
  *                          );
  * 
  * @param string $classUri The class expression whose instances are to be retrieved. If the value is "all", it
  *                         means that it returns all the named individuals defined in the entire ontology.
  *                         otherwise just the named individuals that belongs to the class' uri
  * @param boolean $direct Specifies if the direct instances should be retrieved (true), or if all instances should 
  *                        be retrieved (false). 
  * @param mixed $limit The maximum number of results to return with this function
  * @param mixed $offset The place, in the array, where to start returning results.
  * @param boolean $list Specifies if we want the description of the named individuals being optimized for displaying in a list.
  * 
  * @return Returns a class description array as described above.
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */  
  public function getNamedIndividualsDescription($classUri = "all", $direct = true, $limit = -1, $offset = 0, $list = FALSE)
  {
    $namedIndividuals;
    if($classUri != "all")
    {
      $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($classUri));

      if($this->useReasoner)
      {
        $namedIndividuals = $this->reasoner->getInstances($class, $direct); 
        $namedIndividuals = $namedIndividuals->getFlattened();
      } 
      else
      {
        $namedIndividuals = $class->getIndividuals($this->ontology);
      }                                                        
    }
    else
    {
      $namedIndividuals = $this->ontology->getIndividualsInSignature();
    }    
    
    $niDescription = array();

    $nb = 0; 
    
    foreach($namedIndividuals as $ni)
    {
      if(java_instanceof($ni, java("org.semanticweb.owlapi.model.OWLNamedIndividual")))
      {
        if($limit > -1)
        {
          if($nb >= $offset + $limit)  
          {
            break;
          }
          
          if($nb < $offset)
          {
            $nb++;
            continue;
          }
        }
        
        $niUri = (string)java_values($ni->toStringID());
        $niDescription[$niUri] = array();
        
        if(!$list)
        {
          $niDescription[$niUri] = $this->_getNamedIndividualDescription($ni);
        }
        else
        {
          $niDescription[$niUri] = $this->_getNamedIndividualListDescription($ni);
        }
        $nb++;  
      }   
    }
    
    return($niDescription);    
  }    
  
  /**
  * Check if the ontology is consistent.
  * 
  * @returns TRUE if the ontology is consistent, false otherwise.
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function isConsistent()
  {
    return($this->reasoner->isConsistent());
  }
  
  /**
  * Get the set of loaded ontologies
  * 
  * @return A set of OWLOntology
  * @see http://owlapi.sourceforge.net/javadoc/org/semanticweb/owlapi/model/OWLOntology.html    
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getOntologies()
  {
    return($this->manager->getOntologies());
  }
  
  /**
  * Get the list of all the ontologies of the  import closure of the current ontology. 
  * If you want to get the list of all individually loaded ontologies file of this instance,
  * please use the getLoadedOntologiesUri() API call instead. 
  * 
  * @return An array of ontology URI loaded in the instance.
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getOntologiesUri()
  {                                      
    $ontologies = $this->manager->getOntologies();

    $os = array();
          
    foreach($ontologies as $ontology)
    {
      array_push($os, str_replace(array("<", ">"), "", $ontology->getOntologyID()));
    }
    
    return($os);    
  }  
  
  /**
  * Get a list of all the loaded ontologies in the instance. This list includes all the ontologies files
  * that have been loaded seperately. If you want the import closure of a specific ontology, use
  * the getOntologiesUri() API call instead.
  * 
  * @return An array of ontology URI loaded in the instance.
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public static function getLoadedOntologiesUri($owlApiSession=NULL)
  {
    if(@$this === NULL && $owlApiSession == NULL)
    {
      return(array());
    }
    
    if(@$this !== NULL && $owlApiSession == NULL)
    {
      $owlApiSession = $this->owlApiSession;  
    }
    
    $ontologies = array();
    
    if(!is_null(java_values($owlApiSession->get("ontologiesRegister"))))
    {
      $register = java_values($owlApiSession->get("ontologiesRegister"));
      
      foreach($register as $onto => $id)
      {
        $onto = str_replace("-ontology", "", $onto);
        array_push($ontologies, $onto);
      }
    }
    
    return($ontologies);
  }    
  
  /**
  * Get a list of all the loaded ontologies in the instance. This list includes all the ontologies files
  * that have been loaded seperately. This list includes the description of the loaded ontologies
  * 
  * The array is defined as:
  *   
  *   $classDescription = array(
  *                              "http://...." => array(
  *                              "predicate-uri" => array(
  *                                                         array(
  *                                                                 "value" => "the value of the predicate",
  *                                                                 "type" => "the type of the value",
  *                                                                 "lang" => "language reference of the value (if literal)"
  *                                                              ),
  *                                                         array(...)
  *                                                       ),
  *                                "..." => array(...)
  *                              )
  *                            )
  * 
  * @return An array of ontology URI loaded in the instance.
  * 
  * @see getLoadedOntologiesUri()
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public static function getLoadedOntologiesDescription($owlApiSession=NULL)
  {
    if(@$this === NULL && $owlApiSession == NULL)
    {
      return(array());
    }
    
    if(@$this !== NULL && $owlApiSession == NULL)
    {
      $owlApiSession = $this->owlApiSession;  
    }    
    
    $ontologies = array();  
    
    $ontologiesURI = OWLOntology::getLoadedOntologiesUri($owlApiSession);
    
    foreach($ontologiesURI as $uri)
    {
      $ont = new OWLOntology($uri, $owlApiSession);
      
      $description = $ont->getOntologyDescription();
      
      $ontologies[$uri] = $description;
    }

    return($ontologies);
  }   
  
  /**
  * Add an annotation to this ontology
  * 
  * @param mixed $property Annotation property to use for this axiom description
  * @param mixed $literalValue Literal value to use as the value of this annotation axiom
  * @param mixed $objectValue Object value to use as the value of this annotation axiom 
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function addOntologyAnnotation($property, $literalValue = null, $objectValue = null)
  {
    $annotationProperty = $this->owlDataFactory->getOWLAnnotationProperty(java("org.semanticweb.owlapi.model.IRI")->create($property));

    $value = null;
                                                                      
    if($literalValue != null && $literalValue != "")
    {
      $value = $this->owlDataFactory->getOWLLiteral($literalValue);
    }
    elseif($objectValue != null && $objectValue != "")
    {
      $value = java("org.semanticweb.owlapi.model.IRI")->create($objectValue);
    } 
    else
    {
      return;
    }   
    
    $annotationAxiom = $this->owlDataFactory->getOWLAnnotation($annotationProperty, $value); 
    
    $addAxiom = new java("org.semanticweb.owlapi.model.AddOntologyAnnotation", $this->ontology, $annotationAxiom);  
    
    $this->manager->applyChange($addAxiom);         
  }  

  /**
  * Remove an annotation of this ontology
  * 
  * @param mixed $property Annotation property to use for this axiom description
  * @param mixed $literalValue Literal value to use as the value of this annotation axiom
  * @param mixed $objectValue Object value to use as the value of this annotation axiom 
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function removeOntologyAnnotation($property, $literalValue = null, $objectValue = null)
  {
    $annotationProperty = $this->owlDataFactory->getOWLAnnotationProperty(java("org.semanticweb.owlapi.model.IRI")->create($property));

    $value = null;
                                                                      
    if($literalValue != null && $literalValue != "")
    {
      $value = $this->owlDataFactory->getOWLLiteral($literalValue);
    }
    elseif($objectValue != null && $objectValue != "")
    {
      $value = java("org.semanticweb.owlapi.model.IRI")->create($objectValue);
    } 
    else
    {
      return;
    }   
    
    $annotationAxiom = $this->owlDataFactory->getOWLAnnotation($annotationProperty, $value); 
    
    $removeAxiom = new java("org.semanticweb.owlapi.model.RemoveOntologyAnnotation", $this->ontology, $annotationAxiom);  
    
    $this->manager->applyChange($removeAxiom);         
  }  
  
  /**
  * Add a class to the ontology
  * 
  * @param mixed $uri URI that identify the class
  * @param mixed $literalValues Array of DataProperty/Values. Array of type: array("uri" => array(values), ...)
  * @param mixed $objectValues Array of ObjectProperty/Values. Array of type: array("uri" => array(values), ...)
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function addClass($uri, $literalValues = array(), $objectValues = array())
  {
    // Create the new class
    $class = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($uri));
    
    // Create Object Properties
    foreach($objectValues as $predicate => $values)
    {
      foreach($values as $value)
      {
        switch($predicate)
        {
          case Namespaces::$rdfs."subClassOf":
          
            $superClass = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($value));     
            
            $subClassOfAxiom = $this->owlDataFactory->getOWLSubClassOfAxiom($class, $superClass);    
            
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $subClassOfAxiom);  
            
            $this->manager->applyChange($addAxiom);    
            
          break;
          
          case Namespaces::$owl."disjointWith":
          
            $disjointClass = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($value));     
            
            $disjointSet = new Java("java.util.HashSet");
            
            $disjointSet->add($disjointClass);
            $disjointSet->add($class);
            
            $disjointWithAxiom = $this->owlDataFactory->getOWLDisjointClassesAxiom($disjointSet);    
            
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $disjointWithAxiom);  
            
            $this->manager->applyChange($addAxiom);    
            
          break;
          
          
          case Namespaces::$owl."equivalentClass":
          
            $equivalentClass = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($value));     
            
            $equivalentClassAxiom = $this->owlDataFactory->getOWLEquivalentClassesAxiom($class, $equivalentClass);    
            
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $equivalentClassAxiom);  
            
            $this->manager->applyChange($addAxiom);    
            
          break;
          
          default:
          
            $annotationProperty = $this->owlDataFactory->getOWLAnnotationProperty(java("org.semanticweb.owlapi.model.IRI")->create($predicate));
            
            $iriValue = java("org.semanticweb.owlapi.model.IRI")->create($value);
            
            $annotationAxiom = $this->owlDataFactory->getOWLAnnotation($annotationProperty, $iriValue); 
            
            $addAnnotationAxiom = $this->owlDataFactory->getOWLAnnotationAssertionAxiom($class->getIRI(), $annotationAxiom);  
            
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $addAnnotationAxiom);  
            
            $this->manager->applyChange($addAxiom);              
          
          break;
        }
      }
    }
    
    // Create Literal Properties
    foreach($literalValues as $predicate => $values)
    {
      foreach($values as $value)
      {
        $annotationProperty = $this->owlDataFactory->getOWLAnnotationProperty(java("org.semanticweb.owlapi.model.IRI")->create($predicate));
        
        $literalValue = $this->owlDataFactory->getOWLLiteral($value);
        
        $annotationAxiom = $this->owlDataFactory->getOWLAnnotation($annotationProperty, $literalValue); 
        
        $addAnnotationAxiom = $this->owlDataFactory->getOWLAnnotationAssertionAxiom($class->getIRI(), $annotationAxiom);  
        
        $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $addAnnotationAxiom);  
        
        $this->manager->applyChange($addAxiom);    
      }
    }    
    
    // @TODO finish to implement this API call   
  }  
  
  /**
  * Add a property to the ontology
  * 
  * @param mixed $uri URI that identify the property
  * @param mixed $literalValues Array of DataProperty/Values. Array of type: array("uri" => array(values), ...)
  * @param mixed $objectValues Array of ObjectProperty/Values. Array of type: array("uri" => array(values), ...)
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function addProperty($uri, $literalValues = array(), $objectValues = array())
  {
    $property;

    // Create the new property
    switch($objectValues[Namespaces::$rdf."type"][0])
    {
      case Namespaces::$owl."DatatypeProperty":
        $property = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      break;
      
      case Namespaces::$owl."ObjectProperty":
        $property = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      break;
      
      case Namespaces::$owl."AnnotationProperty":
        $property = $this->owlDataFactory->getOWLAnnotationProperty(java("org.semanticweb.owlapi.model.IRI")->create($uri));
      break;      
    }
    
    // Create Object Properties
    foreach($objectValues as $predicate => $values)
    {
      foreach($values as $value)
      {
        switch($predicate)
        {
          case Namespaces::$rdfs."subPropertyOf":
          
            $superProperty;
          
            switch($objectValues[Namespaces::$rdf."type"][0])
            {
              case Namespaces::$owl."DatatypeProperty":
                $superProperty = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($value));
              break;
              
              case Namespaces::$owl."ObjectProperty":
                $superProperty = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($value));
              break;
            }          
            
            $subPropertyOfAxiom;
            
            switch($objectValues[Namespaces::$rdf."type"][0])
            {
              case Namespaces::$owl."DatatypeProperty":
                $subPropertyOfAxiom = $this->owlDataFactory->getOWLSubDataPropertyOfAxiom($property, $superProperty);
              break;
              
              case Namespaces::$owl."ObjectProperty":
                $subPropertyOfAxiom = $this->owlDataFactory->getOWLSubObjectPropertyOfAxiom($property, $superProperty);
              break;
            }               
            
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $subPropertyOfAxiom);  
            
            $this->manager->applyChange($addAxiom);    
            
          break;
          
          case Namespaces::$owl."disjointWith":
          
            $disjointProperty;
          
            switch($objectValues[Namespaces::$rdf."type"][0])
            {
              case Namespaces::$owl."DatatypeProperty":
                $disjointProperty = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($value));
              break;
              
              case Namespaces::$owl."ObjectProperty":
                $disjointProperty = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($value));
              break;
              
              case Namespaces::$owl."AnnotationProperty":
                $disjointProperty = $this->owlDataFactory->getOWLAnnotationProperty(java("org.semanticweb.owlapi.model.IRI")->create($value));
              break;      
            }        
            
            $disjointSet = new Java("java.util.HashSet");
            
            $disjointSet->add($disjointProperty);
            $disjointSet->add($property);

            $disjointWithAxiom;
            
            switch($objectValues[Namespaces::$rdf."type"][0])
            {
              case Namespaces::$owl."DatatypeProperty":
                $disjointWithAxiom = $this->owlDataFactory->getOWLDisjointDataPropertiesAxiom(java("org.semanticweb.owlapi.model.IRI")->create($disjointSet));
              break;
              
              case Namespaces::$owl."ObjectProperty":
                $disjointWithAxiom = $this->owlDataFactory->getOWLDisjointObjectPropertiesAxiom(java("org.semanticweb.owlapi.model.IRI")->create($disjointSet));
              break;
            }  
            
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $disjointWithAxiom);  
            
            $this->manager->applyChange($addAxiom);    
            
          break;
          
          case Namespaces::$owl."equivalentProperty":
          
            $equivalentProperty;
          
            switch($objectValues[Namespaces::$rdf."type"][0])
            {
              case Namespaces::$owl."DatatypeProperty":
                $equivalentProperty = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($value));
              break;
              
              case Namespaces::$owl."ObjectProperty":
                $equivalentProperty = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($value));
              break;
              
              case Namespaces::$owl."AnnotationProperty":
                $equivalentProperty = $this->owlDataFactory->getOWLAnnotationProperty(java("org.semanticweb.owlapi.model.IRI")->create($value));
              break;      
            }       
            
            $equivalentSet = new Java("java.util.HashSet");
            
            $equivalentSet->add($equivalentProperty);
            $equivalentSet->add($property);            
            
            $equivalentPropertyAxiom;
            
            switch($objectValues[Namespaces::$rdf."type"][0])
            {
              case Namespaces::$owl."DatatypeProperty":
                $equivalentPropertyAxiom = $this->owlDataFactory->getOWLEquivalentDataPropertiesAxiom($equivalentSet);
              break;
              
              case Namespaces::$owl."ObjectProperty":
                $equivalentPropertyAxiom = $this->owlDataFactory->getOWLEquivalentObjectPropertiesAxiom($equivalentSet);
              break;
            }  
                      
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $equivalentPropertyAxiom);  
            
            $this->manager->applyChange($addAxiom);    
            
          break;
        
          case Namespaces::$owl."inverseOf":
            $inverseProperty;
          
            switch($objectValues[Namespaces::$rdf."type"][0])
            {
              case Namespaces::$owl."ObjectProperty":
                $inverseProperty = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($value));
              break;
              
              case Namespaces::$owl."DatatypeProperty":
              case Namespaces::$owl."AnnotationProperty":
              default:
              break;
            }           

            $inversePropertyAxiom = $this->owlDataFactory->getOWLInverseObjectPropertiesAxiom($property, $inverseProperty);    
                      
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $inversePropertyAxiom);  
            
            $this->manager->applyChange($addAxiom);    
            
          break;         
          
          case Namespaces::$rdfs."domain":
          
            $domainAxiom;
            
            switch($objectValues[Namespaces::$rdf."type"][0])
            {
              case Namespaces::$owl."DatatypeProperty":
                $domain = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($value));
                $domainAxiom = $this->owlDataFactory->getOWLDataPropertyDomainAxiom($property, $domain);    
              break;
              
              case Namespaces::$owl."ObjectProperty":
                $domain = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($value));
                $domainAxiom = $this->owlDataFactory->getOWLObjectPropertyDomainAxiom($property, $domain);    
              break;
              
              case Namespaces::$owl."AnnotationProperty":
                $domainAxiom = $this->owlDataFactory->getOWLAnnotationPropertyDomainAxiom($property, java("org.semanticweb.owlapi.model.IRI")->create($value));    
              break;     
            }                       
            
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $domainAxiom);  
            
            $this->manager->applyChange($addAxiom);    
            
          break;          
              
          case Namespaces::$rdfs."range":
          
            $rangeAxiom;
            
            switch($objectValues[Namespaces::$rdf."type"][0])
            {
              case Namespaces::$owl."DatatypeProperty":
                $owlDataType = $this->owlDataFactory->getOWLDatatype(java("org.semanticweb.owlapi.model.IRI")->create($value));
                $rangeAxiom = $this->owlDataFactory->getOWLDataPropertyRangeAxiom($property, $owlDataType);    
              break;
              
              case Namespaces::$owl."ObjectProperty":
                $range = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($value));
                $rangeAxiom = $this->owlDataFactory->getOWLObjectPropertyRangeAxiom($property, $range);    
              break;
              
              case Namespaces::$owl."AnnotationProperty":
                $range = java("org.semanticweb.owlapi.model.IRI")->create($value);
                $rangeAxiom = $this->owlDataFactory->getOWLAnnotationPropertyRangeAxiom($property, $range);    
              break;     
            }                       
            
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $rangeAxiom);  
            
            $this->manager->applyChange($addAxiom);    
            
          break;    
                    
          default:
          
            if($predicate == Namespaces::$rdf."type")
            {
              break;  
            }
          
            $annotationProperty = $this->owlDataFactory->getOWLAnnotationProperty(java("org.semanticweb.owlapi.model.IRI")->create($predicate));
            
            $iriValue = java("org.semanticweb.owlapi.model.IRI")->create($value);
            
            $annotationAxiom = $this->owlDataFactory->getOWLAnnotation($annotationProperty, $iriValue); 
            
            $addAnnotationAxiom = $this->owlDataFactory->getOWLAnnotationAssertionAxiom($property->getIRI(), $annotationAxiom);  
            
            $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $addAnnotationAxiom);  
            
            $this->manager->applyChange($addAxiom);              
          
          break;
        }
      }
    }
    
    // Create Literal Properties
    foreach($literalValues as $predicate => $values)
    {
      foreach($values as $value)
      {
        $annotationProperty = $this->owlDataFactory->getOWLAnnotationProperty(java("org.semanticweb.owlapi.model.IRI")->create($predicate));
        
        $literalValue = $this->owlDataFactory->getOWLLiteral($value);
        
        $annotationAxiom = $this->owlDataFactory->getOWLAnnotation($annotationProperty, $literalValue); 
        
        $addAnnotationAxiom = $this->owlDataFactory->getOWLAnnotationAssertionAxiom($property->getIRI(), $annotationAxiom);  
        
        $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $addAnnotationAxiom);  
        
        $this->manager->applyChange($addAxiom);    
      }
    }    
    
    // @TODO finish to implement this API call   
  }    
  
  /**
  * Update an existing class in the ontology. This class will perform the folowing steps in order to update the class's
  * definition:
  * 
  *   (1) Get all the referencing axioms that refers to this class entity.
  *   (2) Remove the class to update
  *   (3) Add the updated version of the class
  *   (4) Re-add the referencing axioms to this updated class entity
  * 
  * @param mixed $uri URI that identify the class
  * @param mixed $literalValues Array of DataProperty/Values. Array of type: array("uri" => array(values), ...)
  * @param mixed $objectValues Array of ObjectProperty/Values. Array of type: array("uri" => array(values), ...)
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function updateClass($uri, $literalValues = array(), $objectValues = array())
  {
    $class = $this->_getClass($uri);    

    if($class != null)
    {
      $referencingAxioms = $class->getReferencingAxioms($this->ontology);
       
      $changesSet = new Java("java.util.HashSet");
      
      foreach($referencingAxioms as $ra)
      {
        $syntaxedAxiom = (string)$ra->toString();
        
        // We skip the referencing axioms if they are being updated
        if(stripos($syntaxedAxiom, "DisjointClasses(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "SubClassOf(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "EquivalentClasses(<$uri>") !== FALSE)
        {
          continue;       
        }
        
        $addAxiom = new Java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $ra);
        $changesSet->add($addAxiom);
      }
       
      $changesList = new Java("java.util.ArrayList", $changesSet);
      
      $this->removeClass($uri);
    }
    
    $this->addClass($uri, $literalValues, $objectValues);
        
    if($class != null)
    {
      $this->manager->applyChanges($changesList); 
    }
  }  
  
  /**
  * Update the description (annotations) of the current ontology. 
  * This class will perform the folowing steps in order to update the ontology's description:
  * 
  *   (1) Remove all the annotations from the ontology's description
  *   (2) Add the new annotation properties and their values
  * 
  * @param mixed $literalValues Array of DataProperty/Values. Array of type: array("uri" => array(values), ...)
  * @param mixed $objectValues Array of ObjectProperty/Values. Array of type: array("uri" => array(values), ...)
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function updateOntology($literalValues = array(), $objectValues = array())
  {
    // Remove all the annotation axioms describing the ontology
    $ontologyAnnotations = $this->ontology->getAnnotations();
    
    foreach($ontologyAnnotations as $annotation)
    {    
      $removeAxiom = new java("org.semanticweb.owlapi.model.RemoveOntologyAnnotation", $this->ontology, $annotation);  
      
      $this->manager->applyChange($removeAxiom);       
    }
    
    // Add all the new ontology annotation axioms 
    foreach($literalValues as $predicate => $values)
    {
      foreach($values as $value)
      {
        $this->addOntologyAnnotation($predicate, $value, null);
      }
    }    
        
    foreach($objectValues as $predicate => $values)
    {
      foreach($values as $value)
      {
        $this->addOntologyAnnotation($predicate, null, $value);
      }
    }  
  }  
  
  /**
  * Update an existing property in the ontology. This class will perform the folowing steps in order to update the property's
  * definition:
  * 
  *   (1) Get all the referencing axioms that refers to this property entity.
  *   (2) Remove the property to update
  *   (3) Add the updated version of the property
  *   (4) Re-add the referencing axioms to this updated property entity
  * 
  * @param mixed $uri URI that identify the property
  * @param mixed $literalValues Array of DataProperty/Values. Array of type: array("uri" => array(values), ...)
  * @param mixed $objectValues Array of ObjectProperty/Values. Array of type: array("uri" => array(values), ...)
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function updateProperty($uri, $literalValues = array(), $objectValues = array())
  {
    $property = $this->_getProperty($uri);  
    
    if($property != null)  
    {
      $referencingAxioms = $property->getReferencingAxioms($this->ontology);
       
      $changesSet = new Java("java.util.HashSet");
      
      foreach($referencingAxioms as $ra)
      {
        $syntaxedAxiom = (string)$ra->toString();
        
        // We skip the referencing axioms if they are being updated
        if(stripos($syntaxedAxiom, "DisjointObjectProperties(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "DisjointDataProperties(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "SubObjectPropertyOf(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "SubDataPropertyOf(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "EquivalentObjectProperties(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "EquivalentDataProperties(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "InverseObjectProperty(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "ObjectPropertyDomain(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "ObjectPropertyRange(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "DataPropertyDomain(<$uri>") !== FALSE ||
           stripos($syntaxedAxiom, "DataPropertyRange(<$uri>") !== FALSE)
        {
          continue;       
        }
        
        $addAxiom = new Java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $ra);
        $changesSet->add($addAxiom);
      }

      $changesList = new Java("java.util.ArrayList", $changesSet);
      
      $this->removeProperty($uri);
    }
     
        
    $this->addProperty($uri, $literalValues, $objectValues);
        
    if($property != null)  
    {        
      $this->manager->applyChanges($changesList); 
    }
  }    
  
  /**
  * Update an existing named individual in the ontology. This function will perform the folowing steps in 
  * order to update the named individual's definition:
  * 
  *   (1) Get all the referencing axioms that refers to this named individual entity.
  *   (2) Remove the named individual to update
  *   (3) Add the updated version of the named individual
  *   (4) Re-add the referencing axioms to this updated named individual entity
  * 
  * @param mixed $uri URI that identify the named individual
  * @param mixed $types Types defined for this named individual
  * @param mixed $literalValues Array of DataProperty/Values. Array of type: array("uri" => array(values), ...)
  * @param mixed $objectValues Array of ObjectProperty/Values. Array of type: array("uri" => array(values), ...)
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function updateNamedIndividual($uri, $types, $literalValues = array(), $objectValues = array())
  {
    $namedIndividual = $this->_getNamedIndividual($uri);    

    if($namedIndividual != null)
    {
      $referencingAxioms = $namedIndividual->getReferencingAxioms($this->ontology);
       
      $changesSet = new Java("java.util.HashSet");
      
      foreach($referencingAxioms as $ra)
      {
        $syntaxedAxiom = (string)$ra->toString();
        
        // Match (assertions we want to drop):        
        // ClassAssertion(<http://purl.org/ontology/peg#CrossCuttingIssue> <http://purl.org/ontology/peg/framework#Poverty>)
        // ClassAssertion(<http://purl.org/ontology/peg#CrossCuttingIssue> <http://purl.org/ontology/peg/framework#Poverty> <http://purl.org/ontology/peg/framework#Test>)
        // ClassAssertion(rdf:test <http://purl.org/ontology/peg/framework#Poverty> <http://purl.org/ontology/peg/framework#Test>)
        // ClassAssertion(rdf:test <http://purl.org/ontology/peg/framework#Poverty>)
        // DataPropertyAssertion(rdfs:label <http://purl.org/ontology/peg/framework#Energy> "energy"^^xsd:string)
        // DataPropertyAssertion(skos:note <http://purl.org/ontology/peg/framework#Energy> "Energy
        // more text here...
        // more text here...
        // "^^xsd:string)
        
        // Make sure it doesn't match (assertions we want to keep and re-introduce):
        // ClassAssertion(<http://purl.org/ontology/peg#CrossCuttingIssue> <http://purl.org/ontology/peg/framework#Test>)       
        // ClassAssertion(rdf:test <http://purl.org/ontology/peg/framework#Test>)       
        // ClassAssertion(<http://purl.org/ontology/peg#CrossCuttingIssue> <http://purl.org/ontology/peg/framework#test> <http://purl.org/ontology/peg/framework#Poverty>)
        // ClassAssertion(rdf:test <http://purl.org/ontology/peg/framework#test> <http://purl.org/ontology/peg/framework#Poverty>)
        // DataPropertyAssertion(<http://purl.org/ontology/iron#prefLabel> <http://purl.org/ontology/peg/framework#Energy> "Energy"^^xsd:string)
        if(preg_match("/^[A-Za-z0-9_\\-]+\\(<([^\\s]*)>\\s<".str_replace("/", "\\/", $uri).">\\)\$/", $syntaxedAxiom) > 0 ||
           preg_match("/^[A-Za-z0-9_\\-]+\\(<([^\\s]*)>\\s<".str_replace("/", "\\/", $uri).">\\s<([^\\s]*)>\\)\$/", $syntaxedAxiom) > 0 ||
           preg_match("/^[A-Za-z0-9_\\-]+\\([A-Za-z0-9_\\-]+:[A-Za-z0-9_\\-]+\\s<".str_replace("/", "\\/", $uri).">\\s<([^\\s]*)>\\)$/", $syntaxedAxiom) > 0 ||
           preg_match("/^[A-Za-z0-9_\\-]+\\([A-Za-z0-9_\\-]+:[A-Za-z0-9_\\-]+\\s<".str_replace("/", "\\/", $uri).">\\)$/", $syntaxedAxiom) > 0 ||
           preg_match("/^[A-Za-z0-9_\\-]+\\([A-Za-z0-9_\\-]+:[A-Za-z0-9_\\-]+\\s<".str_replace("/", "\\/", $uri).">\\s\"(.+)\".*\\)$/", $syntaxedAxiom) ||
           preg_match("/^[A-Za-z0-9_\\-]+\\([A-Za-z0-9_\\-]+:[A-Za-z0-9_\\-]+\\s<".str_replace("/", "\\/", $uri).">\\s\".*/", $syntaxedAxiom) ||
           preg_match("/^[A-Za-z0-9_\\-]+\\(<([^\\s]*)>\\s<".str_replace("/", "\\/", $uri).">\\s\"(.+)\".*\\)$/", $syntaxedAxiom) > 0)
        {
          continue;         
        }
        
        $addAxiom = new Java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $ra);
        $changesSet->add($addAxiom);
      }
       
      $changesList = new Java("java.util.ArrayList", $changesSet);
      
      $this->removeNamedIndividual($uri);
    }
    
    $this->addNamedIndividual($uri, $types, $literalValues, $objectValues);
        
    if($namedIndividual != null)
    {        
      $this->manager->applyChanges($changesList); 
    }
  }  
  
  /**
  * Update the URI of a given entity
  * 
  * @param mixed $oldUri The current URI of the entity to update
  * @param mixed $newUri The new URI of the entity
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function updateEntityUri($oldUri, $newUri)
  {
    $ontologiesSet = new Java("java.util.HashSet");
            
    $ontologiesSet->add($this->ontology);    
    
    $entityRenamer = new Java("org.semanticweb.owlapi.util.OWLEntityRenamer", $this->manager, $ontologiesSet);
    
    $changes = $entityRenamer->changeIRI(java("org.semanticweb.owlapi.model.IRI")->create($oldUri), java("org.semanticweb.owlapi.model.IRI")->create($newUri));
    
    $this->manager->applyChanges($changes); 
  }    
  
  /**
  * Add a named individual to the ontology
  * 
  * @param mixed $uri URI that identify the individual
  * @param mixed $types An array of types URI to give to the individual
  * @param mixed $literalValues 
  * @param mixed $objectValues
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function addNamedIndividual($uri, $types, $literalValues = array(), $objectValues = array())
  {
    // Create the new named individual
    $namedIndividual = $this->owlDataFactory->getOWLNamedIndividual(java("org.semanticweb.owlapi.model.IRI")->create($uri));
    
    // Add all the "types" axioms
    foreach($types as $type)
    {
      // Get the type's class reference
      $typeClass = $this->owlDataFactory->getOWLClass(java("org.semanticweb.owlapi.model.IRI")->create($type)); 
      
      // Create the type axiom
      $typeAxiom = $this->owlDataFactory->getOWLClassAssertionAxiom($typeClass, $namedIndividual); 
      
      // Add the new type axiom to the ontology
      $this->manager->addAxiom($this->ontology, $typeAxiom);
    }  
    
    // Create Literal Properties
    foreach($literalValues as $predicate => $values)
    {
      foreach($values as $value)
      {
        $dataProperty = $this->owlDataFactory->getOWLDataProperty(java("org.semanticweb.owlapi.model.IRI")->create($predicate));
        
        $literalValue = $this->owlDataFactory->getOWLLiteral($value);
        
        $addDataPropertyAxiom = $this->owlDataFactory->getOWLDataPropertyAssertionAxiom($dataProperty, $namedIndividual, $literalValue);  
        
        $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $addDataPropertyAxiom);  
        
        $this->manager->applyChange($addAxiom);    
      }
    }  
    
    // Create Object Properties
    foreach($objectValues as $predicate => $values)
    {
      foreach($values as $value)
      {
        $objectProperty = $this->owlDataFactory->getOWLObjectProperty(java("org.semanticweb.owlapi.model.IRI")->create($predicate));
        
        $objectIndividual = $this->owlDataFactory->getOWLNamedIndividual(java("org.semanticweb.owlapi.model.IRI")->create($value));
        
        $addObjectPropertyAxiom = $this->owlDataFactory->getOWLObjectPropertyAssertionAxiom($objectProperty, $namedIndividual, $objectIndividual);  
        
        $addAxiom = new java("org.semanticweb.owlapi.model.AddAxiom", $this->ontology, $addObjectPropertyAxiom);  
        
        $this->manager->applyChange($addAxiom);    
      }
    }     
  }
  
  /**
  * Remove a class description from the ontology
  * 
  * @param mixed $uri URI of the class to remove from the ontology
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function removeClass($uri)
  {
    $remover = new java("org.semanticweb.owlapi.util.OWLEntityRemover", $this->manager, 
                        java("java.util.Collections")->singleton($this->ontology));
                        
    $class = $this->_getClass($uri);    
    
    $class->accept($remover);

    $this->manager->applyChanges($remover->getChanges());
    
    unset($remover);
  }  
  
  
  /**
  * Remove a property description from the ontology
  * 
  * @param mixed $uri URI of the property to remove from the ontology
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function removeProperty($uri)
  {
    $remover = new java("org.semanticweb.owlapi.util.OWLEntityRemover", $this->manager, 
                        java("java.util.Collections")->singleton($this->ontology));
                        
    $property = $this->_getProperty($uri);    
    
    $property->accept($remover);

    $this->manager->applyChanges($remover->getChanges());
    
    unset($remover);
  }  
  
  /**
  * Remove an individual from the ontology
  * 
  * @param mixed $uri URI of the individual to remove from the ontology
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function removeNamedIndividual($uri)
  {
    $remover = new java("org.semanticweb.owlapi.util.OWLEntityRemover", $this->manager, 
                        java("java.util.Collections")->singleton($this->ontology));
                        
    $individual = $this->_getNamedIndividual($uri);    
    
    $individual->accept($remover);
    
    $this->manager->applyChanges($remover->getChanges());
    
    unset($remover);
  }
  
  /**
  * Get the information about an annotation object. The information retreived is: the predicate URI which is the
  * annotation property, the value, the datatype of the value and possibly the language if it is a literal and that
  * the language is defined for it.
  * 
  * @param mixed $annotation OWLAnnotation object to get information about
  * @param boolean $getReifications This tells the getAnnotationInfo method if it should check for possible
  *                                 reification statements. This should always be TRUE. This is mainly
  *                                 used to stop possible infinite loops between getAnnotationInfo and
  *                                 getPrefLabel.
  * @return mixed Array("property" => "", "value" => "", "type" => "", "lang" => "")
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function getAnnotationInfo($annotation, $getReifications = TRUE)
  {
    $property = "";
    $value = "";
    $type = "";
    $lang = "";
    $rei = array();
    
    if(!java_instanceof($annotation, java("org.semanticweb.owlapi.model.OWLAnnotation")))
    {
      return(array("property" => "", "uri" => "", "value" => "", "type" => "", "lang" => "", "reify" => ""));
    }
   
    // Check if the value is an OWLLiteral
    if(java_instanceof($annotation->getValue(), java("org.semanticweb.owlapi.model.OWLLiteral")))
    {
      $property = (string)java_values($annotation->getProperty()->toStringID());
      
      $value = (string)java_values($annotation->getValue()->getLiteral());
      $lang = (string)java_values($annotation->getValue()->getLang());
      $type = (string)java_values($annotation->getValue()->getDatatype()->toStringID());
    }
    
    // Check if the value is a IRI
    if(java_instanceof($annotation->getValue(), java("org.semanticweb.owlapi.model.IRI")))      
    {
      $property = (string)java_values($annotation->getProperty()->toStringID());
      $value = (string)java_values($annotation->getValue()->toURI());

      $entities = $this->ontology->getEntitiesInSignature($annotation->getValue());
      
      if($getReifications)
      {
        foreach($entities as $entity)
        {
          $rei = array("wsf:objectLabel" => array($this->getPrefLabel($entity)));
          break;
        }
      }
      
      $type = "xsd:anyURI";
    }
    
    // Check if the value is a OWLAnonymousIndividual
    if(java_instanceof($annotation->getValue(), java("org.semanticweb.owlapi.model.OWLAnonymousIndividual")))      
    {
      $property = (string)java_values($annotation->getProperty()->toStringID());
      $value = (string)java_values($annotation->getValue()->toStringID());

      $type = "xsd:anyURI";
    }    
    
    if($type == "xsd:anyURI")
    {
      return(array("property" => $property, "uri" => $value, "reify" => $rei));
    }
    else
    {
      return(array("property" => $property, "value" => $value, "type" => $type, "lang" => $lang));
    }
  }  
  
  /**
  * Return the ID used as a session ID, within tomcat, for the ontology
  * 
  * @param string $uri URI of the ontology to load
  * 
  * @return Session ID of the ontology
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function getOntologySessionID($uri)
  {
    return("ontology__".preg_replace("/[^a-zA-Z0-9]/", "_", $uri));
  }

  /**
  * Return the ID used as a session ID, within tomcat, for the reasoner related to this ontology
  * 
  * @param string $uri of the ontology to load
  * 
  * @return Session ID of the reasoner
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function getReasonerSessionID($uri)
  {
    return("reasoner__".preg_replace("/[^a-zA-Z0-9]/", "_", $uri));
  }

  /**
  * Get the serialization format of this ontology
  * 
  * @return the MIME type of the format used to serialize this ontology
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getFormat()
  {
    $format = $this->manager->getOntologyFormat($this->ontology);
    $format = java_values($format->toString());
    
    switch(strtolower($format))
    {
      case "turtle":
      case "n3":
        $format = "application/rdf+n3";
      break;
      
      case "xml":
        $format = "application/rdf+xml";
      break;
    }
    
    return($format);
  }
  
  /**
  * Delete the reference of this ontology in the different java sessions where they are persisted.
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function delete()
  {
    // Delete the ontology object
    $this->owlApiSession->remove($this->getOntologySessionID($this->uri));    
    
    // Delete the reasoner object
    $this->owlApiSession->remove($this->getReasonerSessionID($this->uri));    
    
    // Clean the ontologies registry
    $newOntologiesRegister = array();
    
    if(!is_null(java_values($this->owlApiSession->get("ontologiesRegister"))))
    {
      $register = java_values($this->owlApiSession->get("ontologiesRegister"));
      
      foreach($register as $onto => $id)
      {           
        $onto = str_replace("-ontology", "", $onto);    
        if($onto != $this->uri)
        {
          $newOntologiesRegister[$onto."-ontology"] = $this->getOntologySessionID($onto);  
        }
      }
    }    
    
    $this->owlApiSession->put("ontologiesRegister", $newOntologiesRegister);     

    
    // Clean the reasoners registry
    $newReasonnersRegister = array();
    
    if(!is_null(java_values($this->owlApiSession->get("reasonersRegister"))))
    {
      $register = java_values($this->owlApiSession->get("reasonersRegister"));
      
      foreach($register as $onto => $id)
      {           
        $onto = str_replace("-reasoner", "", $onto);    
        if($onto != $this->uri)
        {
          $newReasonnersRegister[$onto."-reasoner"] = $this->getReasonerSessionID($onto);  
        }
      }
    }    
    
    $this->owlApiSession->put("reasonersRegister", $newReasonnersRegister);      
  }
  
  /**
  * Get the serialization of the ontology. The type of serialization will be the same as the one used when the ontology
  * got create.
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getSerialization()
  {
    $outputStream = new java("java.io.ByteArrayOutputStream");
    
    $this->manager->saveOntology($this->ontology, $outputStream);
    
    return((string)java_values($outputStream->toString()));
  }
  
  /**
  * Start using the reasoner for the subsequent OWLOntology functions calls.
  */
  public function useReasoner()
  {
    $this->useReasoner = TRUE;
  }
  
  /**
  * Stop using the reasoner for the subsequent OWLOntology functions calls.
  */
  public function stopUsingReasoner()
  {
    $this->useReasoner = FALSE;
  }
  
  public function getBaseUri()
  {
    $uri = (string)java_values($this->ontology->getOntologyID()->getDefaultDocumentIRI()->toString()); 
    
    if(substr($uri, (strlen($uri) - 1), 1) == "/")
    {
      return($uri);      
    }
    else
    {
      return($uri."#");
    }    
  }
  
  /**
  * Set the language of the annotations to return
  * 
  * @param mixed $lang Language reference such as "en", "fr", etc.
  */
  public function setLanguage($lang)
  {
    $this->lang = $lang;
  }
}  

//@}

?>
