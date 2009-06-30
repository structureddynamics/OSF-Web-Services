<?php

/*! @ingroup WsFramework Framework for the Web Services */
//@{ 

/*! @file \ws\framework\RdfClass.php
	 @brief A RDF Class description
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
 */

/*!	 @brief Class description belonging to the class hierarchy of the system
						
		\n
		
		@author Frederick Giasson, Structured Dynamics LLC.
	
		\n\n\n
*/

class RdfClass
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
	
	/*! @brief Triples defining a class */	
	public $triples = array();

	/*! @brief URI of the class */	
	private $uri = "";

	/*! @brief URI of the graph where the ontologies are indexed */	
	private $ontologiesGraph = "";
	
	/*!	 @brief Constructor 
					
			@param[in] $classURI URI of the class   
			@param[in] $ontologiesGraph URI of the graph where classes description are indexed
			@param[in] $inferredOntologiesGraph URI of the graph where inferred classes are indexed
			@param[in] $db DB connection where to index the classes descriptions
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($classURI, $ontologiesGraph, $inferredOntologiesGraph, &$db)
	{
		$this->uri = $classURI;
		$this->ontologiesGraph = $ontologiesGraph;
	
		$query = $db->build_sparql_query("select ?g ?p ?o from named <$this->ontologiesGraph> from named <$inferredOntologiesGraph> where {graph ?g{<$classURI> ?p ?o.}}", array ('g', 'p', 'o'), FALSE);
	
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
	
	function __destruct(){}
	
	/*!	 @brief Get a human readable label of the class
					
			\n
			
			@return returns a human readable label of the class
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getLabel()
	{
		if(isset($this->triples[$this->ontologiesGraph]["http://www.w3.org/2008/05/skos#prefLabel"]))
		{
			return $this->triples[$this->ontologiesGraph]["http://www.w3.org/2008/05/skos#prefLabel"][0];
		}

		if(isset($this->triples[$this->ontologiesGraph]["http://www.w3.org/2000/01/rdf-schema#label"]))
		{
			return $this->triples[$this->ontologiesGraph]["http://www.w3.org/2000/01/rdf-schema#label"][0];
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
	
	/*!	 @brief Get a human readable description of the class
					
			\n
			
			@return returns returns a human readable description of the class
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getDescription()
	{
		global $ontologiesGraph;	
	
		if(isset($this->triples[$this->ontologiesGraph]["http://www.w3.org/2008/05/skos#definition"]))
		{
			return $this->triples[$this->ontologiesGraph]["http://www.w3.org/2008/05/skos#definition"][0];
		}
		
		if(isset($this->triples[$this->ontologiesGraph]["http://www.w3.org/2000/01/rdf-schema#comment"]))
		{
			return $this->triples[$this->ontologiesGraph]["http://www.w3.org/2000/01/rdf-schema#comment"][0];
		}

		if(isset($this->triples[$this->ontologiesGraph]["http://purl.org/dc/terms/description"]))
		{
			return $this->triples[$this->ontologiesGraph]["http://purl.org/dc/terms/description"][0];
		}
		
		return "No description available";
	}
}

//@} 

?>