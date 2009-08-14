<?php

	/*! @defgroup WsCrud Crud Web Service */
	//@{ 

	/*! @file \ws\crud\read\CrudRead.php
		 @brief Define the Crud Read web service
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief CRUD Read web service. It reads instance records description within dataset indexes on different systems (Virtuoso, Solr, etc).
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class CrudRead extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;

	/*! @brief Include the reference of the resources that links to this resource */
	private $include_linksback = "";
	
	/*! @brief URI of the resource to get its description */
	private $resourceUri = "";
	
	/*! @brief IP of the requester */
	private $requester_ip = "";

	/*! @brief Requested IP */
	private $registered_ip = "";

	/*! @brief URI of the target dataset. */
	private $dataset = "";

	/*! @brief Description of one or multiple datasets */
	private $datasetsDescription = array();
	
	/*! @brief Array of triples where the current resource is a subject. */
	public $subjectTriples = array(); // 

	/*! @brief Array of triples where the current resource is an object. */
	public $objectTriples = array();	

	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");
		
	/*!	 @brief Constructor
			 @details 	Initialize the Auth Web Service
							
			@param[in] $uri URI of the instance record
			@param[in] $dataset URI of the dataset where the instance record is indexed
			@param[in] $include_linksback One of (1) True — Means that the reference to the other instance records referring 
														 to the target instance record will be added in the resultset (2) False (default) — No 
														 links-back will be added 

			@param[in] $registered_ip Target IP address registered in the WSF
			@param[in] $requester_ip IP address of the requester
							
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($uri, $dataset, $include_linksback, $registered_ip, $requester_ip)
	{
		parent::__construct();		
		
		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->dataset = $dataset;
		$this->resourceUri = $uri;
		$this->include_linksback = $include_linksback;
		$this->requester_ip = $requester_ip;
		
		if($registered_ip == "")
		{
			$this->registered_ip = $requester_ip;
		}
		else
		{
			$this->registered_ip = $registered_ip;
		}
		
		if(strtolower(substr($this->registered_ip, 0, 4)) == "self")
		{
			$pos = strpos($this->registered_ip, "::");
			
			if($pos !== FALSE)
			{
				$account = substr($this->registered_ip, $pos + 2, strlen($this->registered_ip) - ($pos + 2));
				
				$this->registered_ip = $requester_ip."::".$account;
			}
			else
			{
				$this->registered_ip = $requester_ip;
			}
		}		

		$this->uri = parent::$wsf_base_url."/wsf/ws/crud/read/";	
		$this->title = "Crud Read Web Service";	
		$this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
		$this->endpoint = parent::$wsf_base_url."/ws/crud/read/";			
		
		$this->dtdURL = "crud/crudRead.dtd";
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
		
			@note This function is not used by the authentication validator web service
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	protected function validateQuery()
	{
		// Validation of the "requester_ip" to make sure the system that is sending the query as the rights.
		$ws_av = new AuthValidator($this->requester_ip, $this->dataset, $this->uri);
		
		$ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
		
		$ws_av->process();
		
		if($ws_av->pipeline_getResponseHeaderStatus() != 200)
		{
			$this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
			$this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
			$this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
			
			return;
		}
		
		unset($ws_av);
		
		// Validation of the "registered_ip" to make sure the user of this system has the rights
		$ws_av = new AuthValidator($this->registered_ip, $this->dataset, $this->uri);
		
		$ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
		
		$ws_av->process();
		
		if($ws_av->pipeline_getResponseHeaderStatus() != 200)
		{
			$this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
			$this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
			$this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
			
			return;
		}
	}
	
	/*!	@brief Create a resultset in a pipelined mode based on the processed information by the Web service.
							
			\n
			
			@return a resultset XML document
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function pipeline_getResultset()
	{ 
		$xml = new ProcessorXML();
	
		// Creation of the RESULTSET
		$resultset = $xml->createResultset();

		// Creation of the prefixes elements.
		$void = $xml->createPrefix("owl", "http://www.w3.org/2002/07/owl#");
		$resultset->appendChild($void);
		$rdf = $xml->createPrefix("rdf", "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
		$resultset->appendChild($rdf);
		$dcterms = $xml->createPrefix("rdfs", "http://www.w3.org/2000/01/rdf-schema#");
		$resultset->appendChild($dcterms);
		$dcterms = $xml->createPrefix("wsf", "http://purl.org/ontology/wsf#");
		$resultset->appendChild($dcterms);

		$subject;

		if(isset($this->subjectTriples["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"]))
		{
			foreach($this->subjectTriples["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"] as $key => $type)
			{
				if($key > 0)
				{
					$pred = $xml->createPredicate("http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
					$object = $xml->createObject("", $type[0]);
					$pred->appendChild($object);
					$subject->appendChild($pred);				
				}
				else
				{
					$subject = $xml->createSubject($type[0], $this->resourceUri);
				}
			}
		}
		else
		{
			$subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $this->resourceUri);
		}

		foreach($this->subjectTriples as $property => $values)
		{
			if($property != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
			{
				foreach($values as $value)
				{
					if($value[1] == "http://www.w3.org/2001/XMLSchema#string")
					{
						$pred = $xml->createPredicate($property);
						$object = $xml->createObjectContent($this->xmlEncode($value[0]));
						$pred->appendChild($object);
						$subject->appendChild($pred);
					}
					else
					{
						$pred = $xml->createPredicate($property);
						$object = $xml->createObject("", $value[0]);
						$pred->appendChild($object);
						$subject->appendChild($pred);								
					}
				}
			}
		}

		$resultset->appendChild($subject);	
		
		// Now let add object references
		if(count($this->objectTriples) > 0)
		{
			foreach($this->objectTriples as $property => $propertyValue)
			{
				foreach($propertyValue as $resource)
				{
					$subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $resource);
					
					$pred = $xml->createPredicate($property);
					$object = $xml->createObject("", $this->resourceUri);
					$pred->appendChild($object);
					$subject->appendChild($pred);		
					
					$resultset->appendChild($subject);												
				}
			}
		}
		
		return($this->injectDoctype($xml->saveXML($resultset)));			
	}
	
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
		$xmlDoc = substr($xmlDoc, 0, $posHeader)."\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Crud Read DTD 0.1//EN\" \"".parent::$dtdBaseURL.$this->dtdURL."\">".substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);	
		
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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudRead::$supportedSerializations);

		// Validate query
		$this->validateQuery();
		
		// If the query is still valid
		if($this->conneg->getStatus() == 200)
		{
			// Check for errors
			if($this->resourceUri == "")
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("No URI specified for any resource");
				return;
			}
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
	public function pipeline_serialize()
	{
		$rdf_part = "";

		switch($this->conneg->getMime())
		{
			case "application/json":
				$json_part = "";
				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());
				
				$subjects = $xml->getSubjects();
			
				foreach($subjects as $subject)
				{
					$subjectURI = $xml->getURI($subject);
					$subjectType = $xml->getType($subject);
				
					$json_part .= "      { \n";
					$json_part .= "        \"uri\": \"".parent::jsonEncode($subjectURI)."\", \n";
					$json_part .= "        \"type\": \"".parent::jsonEncode($subjectType)."\", \n";

					$predicates = $xml->getPredicates($subject);
					
					$nbPredicates = 0;
					
					foreach($predicates as $predicate)
					{
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$nbPredicates++;
							
							if($nbPredicates == 1)
							{
								$json_part .= "        \"predicates\": [ \n";
							}
							
							$objectType = $xml->getType($object);						
							$predicateType = $xml->getType($predicate);
							
							if($objectType == "rdfs:Literal")
							{
								$objectValue = $xml->getContent($object);
														
								$json_part .= "          { \n";
								$json_part .= "            \"".parent::jsonEncode($predicateType)."\": \"".parent::jsonEncode($objectValue)."\" \n";
								$json_part .= "          },\n";
							}
							else
							{
								$objectURI = $xml->getURI($object);						
								$rdf_part .= "          <$predicateType> <$objectURI> ;\n";
								
								$json_part .= "          { \n";
								$json_part .= "            \"".parent::jsonEncode($predicateType)."\": { \n";
								$json_part .= "            	  \"uri\": \"".parent::jsonEncode($objectURI)."\" \n";
								$json_part .= "            	  } \n";
								$json_part .= "          },\n";
							}
						}
					}
					
					if(strlen($json_part) > 0)
					{
						$json_part = substr($json_part, 0, strlen($json_part) - 2)."\n";
					}						
					
					if($nbPredicates > 0)
					{
						$json_part .= "        ]\n";
					}
					
					$json_part .= "      },\n";					
				}
				
				if(strlen($json_part) > 0)
				{
					$json_part = substr($json_part, 0, strlen($json_part) - 2)."\n";
				}
				

				return($json_part);		
			break;	
			
			case "application/rdf+n3":
			
				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());
				
				$subjects = $xml->getSubjects();
			
				foreach($subjects as $subject)
				{
					$subjectURI = $xml->getURI($subject);
					$subjectType = $xml->getType($subject);
				
					$rdf_part .= "\n    <$subjectURI> a <$subjectType> ;\n";

					$predicates = $xml->getPredicates($subject);
					
					foreach($predicates as $predicate)
					{
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$objectType = $xml->getType($object);						
							$predicateType = $xml->getType($predicate);
							
							if($objectType == "rdfs:Literal")
							{
								$objectValue = $xml->getContent($object);						
								$rdf_part .= "        <$predicateType> \"\"\"".str_replace(array("\\"), "\\\\", $objectValue)."\"\"\" ;\n";
							}
							else
							{
								$objectURI = $xml->getURI($object);						
								$rdf_part .= "        <$predicateType> <$objectURI> ;\n";
							}
						}
					}
					
					if(strlen($rdf_part) > 0)
					{
						$rdf_part = substr($rdf_part, 0, strlen($rdf_part) - 2)."\n";
					}
					
				}

				return($rdf_part);		
			break;
			case "application/rdf+xml":
				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());
				
				$subjects = $xml->getSubjects();
				
				$namespaces = array(	"http://www.w3.org/2002/07/owl#" => "owl",
												"http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
												"http://www.w3.org/2000/01/rdf-schema#" => "rdfs",
												"http://purl.org/ontology/wsf#" => "wsf");				
				$nsId = 0;
				
				foreach($subjects as $subject)
				{
					$subjectURI = $xml->getURI($subject);
					$subjectType = $xml->getType($subject);
				
					$ns = $this->getNamespace($subjectType);
					$stNs = $ns[0];
					$stExtension = $ns[1];
				
					if(!isset($namespaces[$stNs]))
					{
						$namespaces[$stNs] = "ns".$nsId;
						$nsId++;
					}
				
					$rdf_part .= "\n    <".$namespaces[$stNs].":".$stExtension." rdf:about=\"$subjectURI\">\n";

					$predicates = $xml->getPredicates($subject);
					
					foreach($predicates as $predicate)
					{
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$objectType = $xml->getType($object);						
							$predicateType = $xml->getType($predicate);
							
							if($objectType == "rdfs:Literal")
							{
								$objectValue = $xml->getContent($object);	
								
								$ns = $this->getNamespace($predicateType);
								$ptNs = $ns[0];
								$ptExtension = $ns[1];
							
								if(!isset($namespaces[$ptNs]))
								{
									$namespaces[$ptNs] = "ns".$nsId;
									$nsId++;
								}
													
								$rdf_part .= "        <".$namespaces[$ptNs].":".$ptExtension.">".$this->xmlEncode($objectValue)."</".$namespaces[$ptNs].":".$ptExtension.">\n";
							}
							else
							{
								$objectURI = $xml->getURI($object);		
								
								$ns = $this->getNamespace($predicateType);
								$ptNs = $ns[0];
								$ptExtension = $ns[1];
							
								if(!isset($namespaces[$ptNs]))
								{
									$namespaces[$ptNs] = "ns".$nsId;
									$nsId++;
								}
												
								$rdf_part .= "        <".$namespaces[$ptNs].":".$ptExtension." rdf:resource=\"$objectURI\" />\n";
							}
						}
					}

					$rdf_part .= "    </".$namespaces[$stNs].":".$stExtension.">\n";
					
					$rdf_header = "<rdf:RDF ";

					foreach($namespaces as $ns => $prefix)
					{
						$rdf_header .= " xmlns:$prefix=\"$ns\"";
					}
					
					$rdf_header .= ">\n\n";
					
					$rdf_part = $rdf_header.$rdf_part;
				}
				
				return($rdf_part);
			break;
		}		
	}
	
	/*!	 @brief Get the namespace of a URI
							
			@param[in] $uri Uri of the resource from which we want the namespace
							
			\n
			
			@return returns the extracted namespace			
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	private function getNamespace($uri)
	{
		$pos = strpos($uri, "#");
		
		if($pos !== FALSE)
		{
			return array(substr($uri, 0, $pos)."#", substr($uri, $pos + 1, strlen($uri) - ($pos +1)));
		}
		else
		{
			$pos = strrpos($uri, "/");
			
			if($pos !== FALSE)
			{
				return array(substr($uri, 0, $pos)."/", substr($uri, $pos + 1, strlen($uri) - ($pos +1)));
			}
		}
		
		return(FALSE);
	}

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
	public function ws_serialize()
	{ 
		switch($this->conneg->getMime())
		{
			case "application/rdf+n3":
				$rdf_document = "";
				$rdf_document .= "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
				
				$rdf_document .= $this->pipeline_serialize();
				
				return $rdf_document;
			break;
	
			case "application/rdf+xml":
				$rdf_document = "";
				$rdf_document .= "<?xml version=\"1.0\"?>\n";
			
				$rdf_document .= $this->pipeline_serialize();
				
			
				$rdf_document .= "</rdf:RDF>";
			
				return $rdf_document;
			break;

			case "text/xml":
				return $this->pipeline_getResultset();
			break;
			
			case "application/json":
				$json_document = "";
				$json_document .= "{\n";
				$json_document .= "  \"resultset\": {\n";
				$json_document .= "    \"subjects\": [\n";
				$json_document .= $this->pipeline_serialize();
				$json_document .= "    ]\n";
				$json_document .= "  }\n";
				$json_document .= "}";
				
				return($json_document);
			break;
		}		
	}
	
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
	

	/*!	 @brief Get the description of an instance resource from the triple store
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function process()
	{
		// Make sure there was no conneg error prior to this process call
		if($this->conneg->getStatus() == 200)
		{
			$query ="";
		
			// Archiving suject triples
			$query = $this->db->build_sparql_query("select ?p ?o (DATATYPE(?o)) as ?otype from <".$this->dataset."> where {<".$this->resourceUri."> ?p ?o.}", array ('p', 'o', 'otype'), FALSE);

			$resultset = $this->db->query($query);
	
			while(odbc_fetch_row($resultset))
			{
				$p = odbc_result($resultset, 1);
				$o = odbc_result($resultset, 2);

				$otype = odbc_result($resultset, 3);

				if(!isset($this->subjectTriples[$p]))
				{
					$this->subjectTriples[$p] = array();
				}
				
				array_push($this->subjectTriples[$p], array($o, $otype));
			}
			
			if(count($this->subjectTriples) <= 0)
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("This resource is not existing");
				return;				
			}
			
			// Archiving object triples
			if(strtolower($this->include_linksback) == "true")
			{
				$query = $this->db->build_sparql_query("select ?s ?p from <".$this->dataset."> where {?s ?p <".$this->resourceUri.">.}", array ('s', 'p'), FALSE);
			
				$resultset = $this->db->query($query);
				
				while(odbc_fetch_row($resultset))
				{
					$s = odbc_result($resultset, 1);
					$p = odbc_result($resultset, 2);
		
					if(!isset($this->objectTriples[$p]))
					{
						$this->objectTriples[$p] = array();
					}
					
					array_push($this->objectTriples[$p], $s);
				}
			}
		}
	}
}


//@} 


?>