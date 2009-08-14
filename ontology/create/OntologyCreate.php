<?php

	/*! @defgroup WsOntology Ontology Management Web Service */
	//@{ 

	/*! @file \ws\ontology\create\OntologyCreate.php
		 @brief Define the Ontology Create web service

		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief Ontology Create Web Service. It indexes new ontologies description in the structWSF instance. Re-generate the internal ontological structure of the system.
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class OntologyCreate extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;
	
	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");

	/*! @brief IP being registered */
	private $registered_ip = "";
	
	/*! @brief Ontology RDF document. Maximum size (by default) is 8M (default php.ini setting). */
	private $ontology = array();
	
	/*! @brief Mime of the Ontology RDF document serialization */
	private $mime = "";
	
	/*! @brief Additional action that can be performed when adding a new ontology: (1) recreate_inference */
	private $action = "";

	/*! @brief Requester's IP used for request validation */
	private $requester_ip = "";

		
	/*!	 @brief Constructor
			 @details 	Initialize the Ontology Create
					
			@param[in] $ontology RDF document describing the ontology. The size of this document is limited to 8MB
			@param[in] $mime One of: (1) application/rdf+xml— RDF document serialized in XML
										(2) application/rdf+n3— RDF document serialized in N3 

			@param[in] $action (optional).If action = "recreate_inference" then the inference table will be re-created as well
			@param[in] $registered_ip Target IP address registered in the WSF
			@param[in] $requester_ip IP address of the requester
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($ontology, $mime, $action, $registered_ip, $requester_ip)
	{
		parent::__construct();		
		
		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->registered_ip = $registered_ip;
		$this->requester_ip = $requester_ip;
		$this->ontology = str_replace("'", "\'", $ontology);
		$this->mime = $mime;
		$this->action = $action;
		
		if($this->registered_ip == "")
		{
			$this->registered_ip = $requester_ip;
		}
		
		if(strtolower(substr($this->registered_ip, 0, 4)) == "self")
		{
			$pos = strpos($this->registered_ip, "::");
			
			if($pos !== FALSE)
			{
				$account = substr($this->registered_ip, $pos + 2, strlen($this->registered_ip) - ($pos +2));
				
				$this->registered_ip = $requester_ip."::".$account;
			}
			else
			{
				$this->registered_ip = $requester_ip;
			}
		}					
		
		$this->uri = parent::$wsf_base_url."/wsf/ws/ontology/create/";	
		$this->title = "Ontology Create Web Service";	
		$this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
		$this->endpoint = parent::$wsf_base_url."/ws/ontology/create/";			
		
		$this->dtdURL = "auth/OntologyCreate.dtd";
	}

	function __destruct() 
	{
		parent::__destruct();
		
		if(isset($this->db))
		{
			@$this->db->close();
		}
	}
	
	/*!	 @brief Validate a query to this web service
							
			\n
			
			@return TRUE if valid; FALSE otherwise
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	protected function validateQuery()
	{ 
		return;
		$ws_av = new AuthValidator($this->requester_ip, parent::$wsf_graph."ontologies/", $this->uri);
		
		$ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
		
		$ws_av->process();
		
		if($ws_av->pipeline_getResponseHeaderStatus() != 200)
		{
			$this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
			$this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
			$this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
		}
	}
	
	/*!	@brief Create a resultset in a pipelined mode based on the processed information by the Web service.
							
			\n
			
			@return a resultset XML document
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function pipeline_getResultset(){ return ""; }
	
	/*!	 @brief Inject the DOCType in a XML document
							
			\n
			
			@param[in] $xmlDoc The XML document where to inject the doctype
			
			@return a XML document with a doctype
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function injectDoctype($xmlDoc)
	{
		$posHeader = 	strpos($xmlDoc, '"?>') + 3;
		$xmlDoc = substr($xmlDoc, 0, $posHeader)."\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Ontology Create DTD 0.1//EN\" \"".parent::$dtdBaseURL.$this->dtdURL."\">".substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);	
		
		return($xmlDoc);
	}

	/*!	 @brief Do content negotiation as an external Web Service
							
			\n
			
			@param[in] $accept Accepted mime types (HTTP header)
			
			@param[in] $accept_charset Accepted charsets (HTTP header)
			
			@param[in] $accept_encoding Accepted encodings (HTTP header)
	
			@param[in] $accept_language Accepted languages (HTTP header)
		
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
	{
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, OntologyCreate::$supportedSerializations);

		// Check for errors

		if($this->ontology == "")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("No Ontology RDF document to index");
			return;
		}

		if($this->mime != "application/rdf+xml" && $this->mime != "application/rdf+n3")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("Unknown MIME type for this RDF document");
			return;
		}
	}
	
	/*!	 @brief Do content negotiation as an internal, pipelined, Web Service that is part of a Compound Web Service
							
			\n
			
			@param[in] $accept Accepted mime types (HTTP header)
			
			@param[in] $accept_charset Accepted charsets (HTTP header)
			
			@param[in] $accept_encoding Accepted encodings (HTTP header)
	
			@param[in] $accept_language Accepted languages (HTTP header)
		
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function pipeline_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
	{
		$this->ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language);
	}
	
	/*!	 @brief Returns the response HTTP header status
							
			\n
			
			@return returns the response HTTP header status
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	public function pipeline_getResponseHeaderStatus()
	{
		return $this->conneg->getStatus();
	}

	/*!	 @brief Returns the response HTTP header status message
							
			\n
			
			@return returns the response HTTP header status message
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function pipeline_getResponseHeaderStatusMsg()
	{
		return $this->conneg->getStatusMsg();
	}

	/*!	 @brief Returns the response HTTP header status message extension
							
			\n
			
			@return returns the response HTTP header status message extension
		
			@note The extension of a HTTP status message is
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function pipeline_getResponseHeaderStatusMsgExt()
	{
		return $this->conneg->getStatusMsgExt();
	}

	/*!	 @brief Serialize the web service answer.
							
			\n
			
			@return returns the serialized content
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function pipeline_serialize(){ return ""; }

	/*!	 @brief Non implemented method (only defined)
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function pipeline_serialize_reification(){ return ""; }	
	
	/*!	 @brief Serialize the web service answer.
							
			\n
			
			@return returns the serialized content
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	public function ws_serialize(){ return ""; }
	
	/*!	 @brief Sends the HTTP response to the requester
							
			\n
			
			@param[in] $content The content (body) of the response.
			
			@return NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	public function ws_respond($content)
	{
		// First send the header of the request
		$this->conneg->respond();

		// second, send the content of the request
		
		// Make sure there is no error.
		if($this->conneg->getStatus() == 200)
		{
			echo $content;
		}
		
		$this->__destruct();
	}
	

	/*!	 @brief Update all ontological structures used by the WSF
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function process()
	{
		// Make sure there was no conneg error prior to this process call
		if($this->conneg->getStatus() == 200)
		{
			$this->validateQuery();
			
			// If the query is still valid
			if($this->conneg->getStatus() == 200)
			{
				// Step #1: load the new ontology
				if($this->mime == "application/rdf+xml")
				{
					$this->db->query("DB.DBA.RDF_LOAD_RDFXML_MT('".$this->ontology."', '".parent::$wsf_graph."ontologies/', '".parent::$wsf_graph."ontologies/')");		
				}
				
				if($this->mime == "application/rdf+n3")
				{
					$this->db->query("DB.DBA.TTLP_MT('".$this->ontology."', '".parent::$wsf_graph."ontologies/', '".parent::$wsf_graph."ontologies/')");		
				}
				
				if($this->db->getError())
				{
					$this->conneg->setStatus(400);
					$this->conneg->setStatusMsg("Bad Request");
					$this->conneg->setStatusMsgExt("Error #ontology-create-100. Syntax error in the RDF document: ".$this->db->getErrorMsg());
				}
				
				// Step #2: re-creating the inference graph
				if($this->action == "recreate_inference")
				{
					// Clean the inference table
					$this->db->query("rdfs_rule_set('wsf_inference_rule1', '".parent::$wsf_graph."ontologies/inferred/', 1)");
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-101. Can't clear the inference table");
					}
					
					// Recreatethe inference table
					$this->db->query("rdfs_rule_set('wsf_inference_rule1', '".parent::$wsf_graph."ontologies/inferred/')");
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-102. Can't create the inference table");
					}
					
					// Commit changes
					$this->db->query("exec('checkpoint')");
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-103. Can't commit changes");
					}
					
					// Clear the inference graph
					$this->db->query("exst('sparql clear graph <".parent::$wsf_graph."ontologies/inferred/>')");
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-104. Can't clear the inference graph");
					}
					
					// Step #3: Creating class hierarchy
					$classHierarchy = new ClassHierarchy("http://www.w3.org/2002/07/owl#Thing");
					
					$query = $this->db->build_sparql_query("select ?s ?o from <".parent::$wsf_graph."ontologies/> where {?s <http://www.w3.org/2000/01/rdf-schema#subClassOf> ?o.}", array ('s', 'o'), FALSE);
					
					$resultset = $this->db->query($query);
					
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-105. Can't get the list of sub-classes-of all classes");
					}					
					
					$ontologiesClasses = array();
					
					while(odbc_fetch_row($resultset))
					{
						$s = odbc_result($resultset, 1);
						$o = odbc_result($resultset, 2);
					
						// Drop blank nodes
						if(strpos($s, "nodeID://") === FALSE && strpos($o, "nodeID://") === FALSE)
						{
							$classHierarchy->addClassRelationship($s, $o);	
							
							$ontologiesClasses[$s] = 1;
						}
					}	
					
					$query = $this->db->build_sparql_query("select ?s from <".parent::$wsf_graph."ontologies/> where {?s a <http://www.w3.org/1999/02/22-rdf-syntax-ns#Class>.}", array ('s'), FALSE);
					
					$resultset = $this->db->query($query);
					
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-106. Can't get the list of RDFS classes");
					}						
					
					while(odbc_fetch_row($resultset))
					{
						$s = odbc_result($resultset, 1);
					
						if(strpos($s, "nodeID://") === FALSE &&  isset($classHierarchy->classes[$s]) === FALSE)
						{
							$classHierarchy->addClassRelationship($s, "http://www.w3.org/2002/07/owl#Thing");	
							
							$ontologiesClasses[$s] = 1;
						}
					}
								
					$query = $this->db->build_sparql_query("select ?s from <".parent::$wsf_graph."ontologies/> where {?s a <http://www.w3.org/2002/07/owl#Class>.}", array ('s'), FALSE);

					$resultset = $this->db->query($query);
					
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-107. Can't get the list of OWL classes");
					}						
					
					while(odbc_fetch_row($resultset))
					{
						$s = odbc_result($resultset, 1);
					
						if(strpos($s, "nodeID://") === FALSE &&  isset($classHierarchy->classes[$s]) === FALSE)
						{
							$classHierarchy->addClassRelationship($s, "http://www.w3.org/2002/07/owl#Thing");	
							
							$ontologiesClasses[$s] = 1;
						}
					}			
					
					
					
					// Step #4: Properties class hierarchy					
									
					$propertyHierarchy = new PropertyHierarchy("http://www.w3.org/2002/07/owl#Thing");
					
					$query = $this->db->build_sparql_query("select ?s ?o from <".parent::$wsf_graph."ontologies/> where {?s <http://www.w3.org/2000/01/rdf-schema#subPropertyOf> ?o.}", array ('s', 'o'), FALSE);
					
					$resultset = $this->db->query($query);
					
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-108. Can't get the list of sub-properties-of all properties");
					}						
					
					$ontologiesProperties = array();
					
					while(odbc_fetch_row($resultset))
					{
						$s = odbc_result($resultset, 1);
						$o = odbc_result($resultset, 2);
					
						if(strpos($s, "nodeID://") === FALSE && strpos($o, "nodeID://") === FALSE)
						{
							$propertyHierarchy->addPropertyRelationship($s, $o);	
							
							$ontologiesProperties[$s] = 1;
						}
					}		
					
					$query = $this->db->build_sparql_query("select ?s from <".parent::$wsf_graph."ontologies/> where {?s a <http://www.w3.org/1999/02/22-rdf-syntax-ns#Property>.}", array ('s'), FALSE);
					
					$resultset = $this->db->query($query);
					
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-109. Can't get the list of RDFS properties");
					}							
					
					while(odbc_fetch_row($resultset))
					{
						$s = odbc_result($resultset, 1);
					
						if(strpos($s, "nodeID://") === FALSE && isset($propertyHierarchy->properties[$s]) === FALSE)
						{
							$propertyHierarchy->addPropertyRelationship($s, "http://www.w3.org/2002/07/owl#Thing");	
							
							$ontologiesProperties[$s] = 1;
						}
					}					
						
					$query = $this->db->build_sparql_query("select ?s from <".parent::$wsf_graph."ontologies/> where {{?s a <http://www.w3.org/2002/07/owl#ObjectProperty>.}union{?s a <http://www.w3.org/2002/07/owl#DatatypeProperty>.}}", array ('s'), FALSE);
					
					$resultset = $this->db->query($query);
					
					if($this->db->getError())
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-10. Can't get the list of OWL-Object/Datatype properties");
					}							
					
					while(odbc_fetch_row($resultset))
					{
						$s = odbc_result($resultset, 1);
					
						if(strpos($s, "nodeID://") === FALSE && isset($propertyHierarchy->properties[$s]) === FALSE)
						{
							$propertyHierarchy->addPropertyRelationship($s, "http://www.w3.org/2002/07/owl#Thing");	
							
							$ontologiesProperties[$s] = 1;
						}
					}						
							
							
					// Step #5: Populating the labels and descriptions for each ClassNode.

					foreach($classHierarchy->classes as $c)
					{
						$class = new RdfClass($c->name, parent::$wsf_graph."ontologies/", parent::$wsf_graph."ontologies/inferred/", $this->db);
						$c->description = preg_replace('/[^(\x20-\x7F)]*/','', $class->getDescription());
						$c->label = preg_replace('/[^(\x20-\x7F)]*/','', $class->getLabel());
						
						unset($class);			
					}
					
					
					foreach($propertyHierarchy->properties as $p)
					{
						$property = new RdfProperty($p->name, parent::$wsf_graph."ontologies/", parent::$wsf_graph."ontologies/inferred/", $this->db);
						$p->description = preg_replace('/[^(\x20-\x7F)]*/','', $property->getDescription());
						$p->label = preg_replace('/[^(\x20-\x7F)]*/','', $property->getLabel());
						
						unset($property);
					}
				}
				
				// Step #6: for each class, we add a "subClassOf" triple for each of their subClasses (recursively until we reach owl:Thing)
				
				foreach($ontologiesClasses as $class => $value)
				{
					$superClasses = $classHierarchy->getSuperClasses($class);
					
					foreach($superClasses as $sp)
					{
						$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<".$classHierarchy->classes[$class]->name."> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <".$sp->name.">.}')");
						
						if($this->db->getError())
						{
							$this->conneg->setStatus(400);
							$this->conneg->setStatusMsg("Bad Request");
							$this->conneg->setStatusMsgExt("Error #ontology-create-11. Can't insert inferred triples");
						}									
					}
				}			
				
				// Step #7: checking for equivalent classes.
				
				$query = $this->db->build_sparql_query("select ?s ?o from <".parent::$wsf_graph."ontologies/> where {?s <http://www.w3.org/2002/07/owl#equivalentClass> ?o.}", array ('s', 'o'), FALSE);
				
				$resultset = $this->db->query($query);
				
				while(odbc_fetch_row($resultset))
				{
					$s = odbc_result($resultset, 1);
					$o = odbc_result($resultset, 2);
				
					if(strpos($s, "nodeID://") === FALSE && strpos($o, "nodeID://") === FALSE)
					{
						// Check if the equivalentClass belongs to our current class structure.
						if(isset($classHierarchy->classes[$o]))
						{
							// We perform the same superClasses assignation
							$subClasses = $classHierarchy->getSubClasses($o);
							
							foreach($subClasses as $sp)
							{
								$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<".$sp->name."> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <$s>.}')");
							}	
						}
				
						// Check if the equivalentClass belongs to our current class structure.
						if(isset($classHierarchy->classes[$s]))
						{
							// We perform the same superClasses assignation
							$subClasses = $classHierarchy->getSubClasses($s);
							
							foreach($subClasses as $sp)
							{
								$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<".$sp->name."> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <$o>.}')");
							}	
						}
							
						// Check if the equivalentClass belongs to our current class structure.
						if(isset($classHierarchy->classes[$o]))
						{
							// We perform the same superClasses assignation
							$superClasses = $classHierarchy->getSuperClasses($o);
							
							foreach($superClasses as $sp)
							{
								$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$s> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <".$sp->name.">.}')");
							}	
						}
						
						// Check if the equivalentClass belongs to our current class structure.
						if(isset($classHierarchy->classes[$s]))
						{
							// We perform the same superClasses assignation
							$superClasses = $classHierarchy->getSuperClasses($s);
							
							foreach($superClasses as $sp)
							{
								$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$o> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <".$sp->name.">.}')");
							}	
						}
				
						// We re-iterate the equivalency relationship in the inferred table.
						$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$s> <http://www.w3.org/2002/07/owl#equivalentClasses> <$o>.}')");
						$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$o> <http://www.w3.org/2002/07/owl#equivalentClasses> <$s>.}')");
				
						$classHierarchy->addClassRelationship($s, $o);	
						
						$ontologiesClasses[$s] = 1;
					}
				}
				
				if($this->db->getError())
				{
					$this->conneg->setStatus(400);
					$this->conneg->setStatusMsg("Bad Request");
					$this->conneg->setStatusMsgExt("Error #ontology-create-12.");
				}									
				
				// Step #8 inferring the domains and range of all properties (except unionOf)
				
				$properties = array();
				
				$query = $this->db->build_sparql_query("select distinct ?s ?domain ?range from <".parent::$wsf_graph."ontologies/> where {?s a <http://www.w3.org/1999/02/22-rdf-syntax-ns#Property>. optional{{?s <http://www.w3.org/2000/01/rdf-schema#domain> ?domain.} union { ?s <http://www.w3.org/2000/01/rdf-schema#range> ?range.}}}", array ('s', 'domain', 'range'), FALSE);
				
				$resultset = $this->db->query($query);
				
				$ontologiesClasses = array();
				
				while(odbc_fetch_row($resultset))
				{
					$property = odbc_result($resultset, 1);
					$domain = odbc_result($resultset, 2);
					$range = odbc_result($resultset, 3);
					
					if(strpos($domain, "nodeID://") !== FALSE)
					{
						$domain = "";	
					}
					
					if(strpos($range, "nodeID://") !== FALSE)
					{
						$range = "";	
					}
					
					if(!isset($properties[$property]))
					{
						$properties[$property] = array("http://www.w3.org/2002/07/owl#Thing", "http://www.w3.org/2002/07/owl#Thing");
					}
					
					if($domain == "next")
					{
						$properties[$property][0] = "";
					}
					elseif($domain != "")
					{
						$properties[$property][0] = $domain;
					}
				
					if($range == "next")
					{
						$properties[$property][1] = "";
					}
					elseif($domain != "")
					{
						$properties[$property][1] = $range;
					}
				}
				
				
				$query = $this->db->build_sparql_query("select distinct ?s ?domain ?range from <".parent::$wsf_graph."ontologies/> where {?s a <http://www.w3.org/2002/07/owl#DatatypeProperty>. optional{{?s <http://www.w3.org/2000/01/rdf-schema#domain> ?domain.} union {?s <http://www.w3.org/2000/01/rdf-schema#range> ?range.}}}", array ('s', 'domain', 'range'), FALSE);
				
				
				$resultset = $this->db->query($query);
				
				$ontologiesClasses = array();
				
				while(odbc_fetch_row($resultset))
				{
					$property = odbc_result($resultset, 1);
					$domain = odbc_result($resultset, 2);
					$range = odbc_result($resultset, 3);
					
					if(strpos($domain, "nodeID://") !== FALSE)
					{
						$domain = "next";	
					}
					
					if(strpos($range, "nodeID://") !== FALSE)
					{
						$range = "next";	
					}
					
					if(!isset($properties[$property]))
					{
						$properties[$property] = array("http://www.w3.org/2002/07/owl#Thing", "http://www.w3.org/2002/07/owl#Thing");
					}
					
					if($domain == "next")
					{
						$properties[$property][0] = "";
					}
					elseif($domain != "")
					{
						$properties[$property][0] = $domain;
					}
				
					if($range == "next")
					{
						$properties[$property][1] = "";
					}
					elseif($range != "")
					{
						$properties[$property][1] = $range;
					}
				}
				
				$query = $this->db->build_sparql_query("select distinct ?s ?domain ?range from <".parent::$wsf_graph."ontologies/> where {?s a <http://www.w3.org/2002/07/owl#ObjectProperty>. optional{{?s <http://www.w3.org/2000/01/rdf-schema#domain> ?domain.} union {?s <http://www.w3.org/2000/01/rdf-schema#range> ?range.}}}", array ('s', 'domain', 'range'), FALSE);
				
				$resultset = $this->db->query($query);
				
				$ontologiesClasses = array();
				
				while(odbc_fetch_row($resultset))
				{
					$property = odbc_result($resultset, 1);
					$domain = odbc_result($resultset, 2);
					$range = odbc_result($resultset, 3);
					
					if(strpos($domain, "nodeID://") !== FALSE)
					{
						$domain = "";	
					}
					
					if(strpos($range, "nodeID://") !== FALSE)
					{
						$range = "";	
					}
					
					if(!isset($properties[$property]))
					{
						$properties[$property] = array("http://www.w3.org/2002/07/owl#Thing", "http://www.w3.org/2002/07/owl#Thing");
					}
					
					if($domain == "next")
					{
						$properties[$property][0] = "";
					}
					elseif($domain != "")
					{
						$properties[$property][0] = $domain;
					}
				
					if($range == "next")
					{
						$properties[$property][1] = "";
					}
					elseif($domain != "")
					{
						$properties[$property][1] = $range;
					}
				}
				
				foreach($properties as $property => $domainsRanges)
				{
					// Domains
					if($domainsRanges[0] != "")
					{
						if($domainsRanges[0] == "http://www.w3.org/2002/07/owl#Thing")
						{
							$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <http://www.w3.org/2002/07/owl#Thing>.}')");
						}
						else
						{
							if(isset($classHierarchy->classes[$domainsRanges[0]]))
							{
								$subClasses = $classHierarchy->getSubClasses($domainsRanges[0]);
								
								foreach($subClasses as $sp)
								{
									$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <".$sp->name.">.}')");
									
									$this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#domain", $sp->name);
								}	
							}
							else
							{
								$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <$domainsRanges[0]>.}')");
								
								$this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#domain", $domainsRanges[0]);
							}	
						}
					}
					
					// Ranges
					if($domainsRanges[1] != "")
					{
						if($domainsRanges[1] == "http://www.w3.org/2002/07/owl#Thing")
						{
							$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <http://www.w3.org/2002/07/owl#Thing>.}')");
						}
						else
						{
							if(isset($classHierarchy->classes[$domainsRanges[1]]))
							{
								$subClasses = $classHierarchy->getSubClasses($domainsRanges[1]);
								
								foreach($subClasses as $sp)
								{
									$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <".$sp->name.">.}')");
									
									$this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#range", $sp->name);
								}	
							}
							else
							{
								$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <$domainsRanges[1]>.}')");
				
								$this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#range", $domainsRanges[1]);
							}	
						}
					}	
				}		
				
				if($this->db->getError())
				{
					$this->conneg->setStatus(400);
					$this->conneg->setStatusMsg("Bad Request");
					$this->conneg->setStatusMsgExt("Error #ontology-create-13.");
				}							
				
				
				// Step #9: processing the unionOf domains and ranges if needed.
				
				// Domains
				
				$query = $this->db->build_sparql_query("SELECT ?s ?unionOf FROM <".parent::$wsf_graph."ontologies/> WHERE { ?s <http://www.w3.org/2000/01/rdf-schema#domain> ?o. ?o <http://www.w3.org/2002/07/owl#unionOf> ?unionOf.}", array ('s', 'unionOf'), FALSE);
				
				$resultset = $this->db->query($query);
				
				while(odbc_fetch_row($resultset))
				{
					$property = odbc_result($resultset, 1);
					$union = odbc_result($resultset, 2);
					
					$unionClasses = array();
					
					$this->getUnionOf($union, $unionClasses);
				
					foreach($unionClasses as $uc)
					{
						if(isset($classHierarchy->classes[$uc]))
						{
							$subClasses = $classHierarchy->getSubClasses($uc);
							
							foreach($subClasses as $sp)
							{
								$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <".$sp->name.">.}')");
								
								$this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#domain", $sp->name);
							}	
						}
						
						$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#domain> <$uc>.}')");
						
						$this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#domain", $uc);
					}
				}
				
				
				// Ranges
				$query = $this->db->build_sparql_query("SELECT ?s ?unionOf FROM <".parent::$wsf_graph."ontologies/> WHERE { ?s <http://www.w3.org/2000/01/rdf-schema#range> ?o. ?o <http://www.w3.org/2002/07/owl#unionOf> ?unionOf.}", array ('s', 'unionOf'), FALSE);
				
				$resultset = $this->db->query($query);
				
				while(odbc_fetch_row($resultset))
				{
					$property = odbc_result($resultset, 1);
					$union = odbc_result($resultset, 2);
					
					$unionClasses = array();
					
					$this->getUnionOf($union, $unionClasses);
				
					foreach($unionClasses as $uc)
					{
						if(isset($classHierarchy->classes[$uc]))
						{
							$subClasses = $classHierarchy->getSubClasses($uc);
							
							foreach($subClasses as $sp)
							{
								$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <".$sp->name.">.}')");
				
								$this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#range", $sp->name);
							}	
						}
						
						$this->db->query("exst('sparql insert into graph <".parent::$wsf_graph."ontologies/inferred/> {<$property> <http://www.w3.org/2000/01/rdf-schema#range> <$uc>.}')");
						
						$this->addEquivalentClass($inferredOntologiesGraph, $property, "http://www.w3.org/2000/01/rdf-schema#range", $uc);
					}
				}					
				
				$classHierarchy = serialize($classHierarchy);
				$classHierarchy = str_replace(array("\n", "\r"), array("", "",), $classHierarchy);

				$propertyHierarchy = serialize($propertyHierarchy);
				$propertyHierarchy = str_replace(array("\n", "\r", "\t", "'"), array("", "", "", "\'"), $propertyHierarchy);
						
				
/*!
				@todo Fixing this to use the DB.
				
				// This method is currently not working. The problem is that we ahve an issue in CrudCreate and Virtuoso's
				// LONG VARCHAR column. It appears that there is a bug somewhere in the "php -> odbc -> virtuoso" path.
				// If we are not requesting to return the LONG VARCHAR column, everything works fine.
*/ 		
/*		
				// Step #10: Delete the previously created table
				$this->db->query('drop table "SD"."WSF"."ws_ontologies"');

				// Step #11: Adding the class & properties structures to the table.
				
				$this->db->query('create table "SD"."WSF"."ws_ontologies" ("struct_type" VARCHAR, "struct" LONG VARCHAR, PRIMARY KEY ("struct_type"))');
				
//				$this->db->query("insert into SD.WSF.ws_ontologies(struct_type, struct) values('class', '".$classHierarchy."')");
//				$this->db->query("insert into SD.WSF.ws_ontologies(struct_type, struct) values('property', '".$propertyHierarchy."')");

				$this->db->query("insert into SD.WSF.ws_ontologies(struct_type, struct) values('class', 'test1')");
				$this->db->query("insert into SD.WSF.ws_ontologies(struct_type, struct) values('property', 'test2')");

				$this->db->query("exec('checkpoint')");
*/				

				// Step #10: Create the PHP serialized files that will be used by other web services of this WSF
				
				$classHierarchyFile = parent::$wsf_base_path."/framework/ontologies/classHierarchySerialized.srz";
				
				$fHandle = fopen($classHierarchyFile, 'w');
				
				if($fHandle !== FALSE)
				{
					if(!fwrite($fHandle, $classHierarchy))
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-15. Can't write file: $classHierarchyFile");
						return;				
					}
					
					fclose($fHandle);
				}
				else
				{
					$this->conneg->setStatus(400);
					$this->conneg->setStatusMsg("Bad Request");
					$this->conneg->setStatusMsgExt("Error #ontology-create-16. Can't open file: $classHierarchyFile");
					return;				
				}
				
				$propertyHierarchyFile = parent::$wsf_base_path."/framework/ontologies/propertyHierarchySerialized.srz";
				
				$fHandle = fopen($propertyHierarchyFile, 'w');
				
				if($fHandle !== FALSE)
				{
					if(!fwrite($fHandle, $propertyHierarchy))
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("Error #ontology-create-17 Can't write file: $propertyHierarchyFile");
					}
					fclose($fHandle);
				}
				else
				{
					$this->conneg->setStatus(400);
					$this->conneg->setStatusMsg("Bad Request");
					$this->conneg->setStatusMsgExt("Error #ontology-create-18. Can't open file: $propertyHierarchyFile");
					return;				
				}
				
				return;
			}
		}
	}

	private function addEquivalentClass($graph, $subject, $property, $target)
	{
		$query = $this->db->build_sparql_query("select ?o from <".parent::$wsf_graph."ontologies/> where {<$target> <http://www.w3.org/2002/07/owl#equivalentClass> ?o.}", array ('o'), FALSE);
		
		$resultset = $this->db->query($query);
		
		while(odbc_fetch_row($resultset))
		{
			$o = odbc_result($resultset, 1);
			
			$this->db->query("exst('sparql insert into graph <$graph> {<$subject> <$property> <$o>.}')");
		}
	} 
	
	private function getUnionOf($unionURI, &$unionClasses)
	{
		$query = $this->db->build_sparql_query("SELECT * FROM <".parent::$wsf_graph."ontologies/> WHERE { <$unionURI> ?p ?o. }", array ('p', 'o'), FALSE);
		
		$resultset = $this->db->query($query);
		
		while(odbc_fetch_row($resultset))
		{
			$p = odbc_result($resultset, 1);
			$o = odbc_result($resultset, 2);
			
			if($p == "http://www.w3.org/1999/02/22-rdf-syntax-ns#first")
			{
				array_push($unionClasses, $o);
			}
			
			if($p == "http://www.w3.org/1999/02/22-rdf-syntax-ns#rest")
			{
				if($o == "http://www.w3.org/1999/02/22-rdf-syntax-ns#nil")
				{
					break;
				}
				else
				{
					$this->getUnionOf($o, $unionClasses);
				}
			}
		}	
	}
}

	//@} 


?>