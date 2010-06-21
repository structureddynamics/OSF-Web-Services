<?php
include_once("Namespaces.php");

/*! @ingroup WsFramework Framework for the Web Services */
//@{

/*! @file \ws\framework\RdfProperty.php
   @brief A RDF Property description
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */

/*!   @brief Property description belonging to the property hierarchy of the system
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class RdfProperty
{
  /* Data structure of $triples looks like:
  
    Array
    (
      [/ontologies/inferred/] => Array
        (
          [http://www.w3.org/2002/07/owl#equivalentClasses] => Array
            (
              [0] => http://purl.org/dc/terms/Agent
            )
    
        )
    )
  */


  /*! @brief Triples defining a property */
  public $triples = array();

  /*! @brief URI of the property */
  private $uri = "";

  /*! @brief URI of the graph where the ontologies are indexed */
  private $ontologiesGraph = "";

  /*!   @brief Constructor 
          
      @param[in] $propertyURI URI of the property   
      @param[in] $ontologiesGraph URI of the graph where properties description are indexed
      @param[in] $inferredOntologiesGraph URI of the graph where inferred properties are indexed
      @param[in] $db DB connection where to index the properties descriptions
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($propertyURI, $ontologiesGraph, $inferredOntologiesGraph, &$db)
  {
    $this->uri = $propertyURI;
    $this->ontologiesGraph = $ontologiesGraph;

    $query =
      $db->build_sparql_query(
        "select ?g ?p ?o from named <$ontologiesGraph> from named <$inferredOntologiesGraph> where {graph ?g{<$propertyURI> ?p ?o.}}",
        array ('g', 'p', 'o'), FALSE);

    $resultset = $db->query($query);

    while(odbc_fetch_row($resultset))
    {
      $g = odbc_result($resultset, 1);
      $p = odbc_result($resultset, 2);
      $o = odbc_result($resultset, 3);

      if(!isset($this->triples[$g]))
      {
        $this->triples[$g] = array();
      }

      if(!isset($this->triples[$g][$p]))
      {
        $this->triples[$g][$p] = array();
      }

      array_push($this->triples[$g][$p], $o);
    }
  }

  function __destruct() { }

  /*!   @brief Get a human readable label of the property
          
      \n
      
      @return returns a human readable label of the property
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getLabel()
  {
    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$iron . "prefLabel"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$iron . "prefLabel"][0];
    }

    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$skos_2008 . "prefLabel"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$skos_2008 . "prefLabel"][0];
    }

    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$skos_2004 . "prefLabel"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$skos_2004 . "prefLabel"][0];
    }

    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$rdfs . "label"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$rdfs . "label"][0];
    }

    // Find the base URI of the ontology
    $pos = strripos($this->uri, "#");

    if($pos === FALSE)
    {
      $pos = strripos($this->uri, "/");
    }

    if($pos !== FALSE)
    {
      $pos++;
    }

    $resource = substr($this->uri, $pos, strlen($this->uri) - $pos);

    return $resource;
  }

  /*!   @brief Get a human readable description of the property
          
      \n
      
      @return returns a human readable description of the property
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getDescription()
  {
    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$iron . "description"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$iron . "description"][0];
    }

    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$skos_2008 . "definition"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$skos_2008 . "definition"][0];
    }

    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$skos_2004 . "definition"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$skos_2004 . "definition"][0];
    }

    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$rdfs . "comment"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$rdfs . "comment"][0];
    }

    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$dcterms . "description"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$dcterms . "description"][0];
    }

    if(isset($this->triples[$this->ontologiesGraph][Namespaces::$dc . "description"]))
    {
      return $this->triples[$this->ontologiesGraph][Namespaces::$dc . "description"][0];
    }

    return "No description available";
  }
}

//@}

?>