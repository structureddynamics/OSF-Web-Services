<?php

	/*! @ingroup WsSearch */
	//@{ 

	/*! @file \ws\search\Search.php
		 @brief Define the Search web service
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief Search Web Service. It searches datasets indexed in the structWSF instance.
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class Search extends WebService
{
	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;

	/*! @brief Full text query supporting Lucene query syntax */
	private $query = "";
	
	/*! @brief List of types to filter */
	private $types = "";

	/*! @brief List of datasets to search */
	private $datasets = "";
	
	/*! @brief Number of items to return per page */
	private $items = "";
	
	/*! @brief Page number to return */
	private $page = "";
	
	/*! @brief Enabling the inference engine */
	private $inference = "";
	
	/*! @brief IP of the requester */
	private $requester_ip = "";

	/*! @brief Requested IP */
	private $registered_ip = "";

	/*! @brief Array of triples where the current resource is a subject. */
	public $subjectTriples = array(); // 

	/*! @brief Array of triples where the current resource is an object. */
	public $objectTriples = array();	

	/*! @brief Resultset returned by Solr */
	public $resultset = array();

	/*! @brief Resultset of object properties returned by Solr */
	public $resultsetObjectProperties = array();

	/*! @brief Aggregates of the search */
	public $aggregates = array();

	/*! @brief Include aggregates to the resultset */
	public $include_aggregates = array();
	
	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");
		
	/*!	 @brief Constructor
			 @details 	Initialize the Search Web Service
							
			@param[in] $query Lucune syntaxed query to send to the search system 
			@param[in] $types List of filtering types URIs separated by ";"
			@param[in] $datasets List of filtering datasets URIs separated by ";"
			@param[in] $items Number of items returned by resultset
			@param[in] $page Starting item number of the returned resultset
			@param[in] $inference Enabling inference on types
			@param[in] $include_aggregates Including aggregates with returned resultsets
			@param[in] $registered_ip Target IP address registered in the WSF
			@param[in] $requester_ip IP address of the requester
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($query, $types, $datasets, $items, $page, $inference, $include_aggregates, $registered_ip, $requester_ip)
	{
		parent::__construct();		
		
		$this->query = $query;
		$this->items = $items;
		$this->page = $page;
		$this->inference = $inference;
		$this->includeAggregates = $include_aggregates;
		
		$this->types = $types;
		$this->datasets = $datasets;

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

		$this->uri = parent::$wsf_base_url."/wsf/ws/search/";	
		$this->title = "Search Web Service";	
		$this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
		$this->endpoint = parent::$wsf_base_url."/ws/search/";			
		
		$this->dtdURL = "search/search.dtd";
	}

	function __destruct() 
	{
		parent::__destruct();
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
		// Here we can have a performance problem when "dataset = all" if we perform the authentication using AuthValidator.
		// Since AuthValidator doesn't support multiple datasets at the same time, we will use the AuthLister web service
		// in the process() function and check if the user has the permissions to "read" these datasets.
		//
		// This means that the validation of these queries doesn't happen at this level.
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
		$dcterms = $xml->createPrefix("aggr", "http://purl.org/ontology/aggregate#");
		$resultset->appendChild($dcterms);

		$subject;

		foreach($this->resultset as $uri => $result)
		{
			// Assigning types
			if(isset($result["type"]))
			{
				foreach($result["type"] as $key => $type)
				{
					if($key > 0)
					{
						$pred = $xml->createPredicate("http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
						$object = $xml->createObject("", $type);
						$pred->appendChild($object);
						$subject->appendChild($pred);				
					}
					else
					{
						$subject = $xml->createSubject($type, $uri);
					}
				}
			}
			else
			{
				$subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $this->resourceUri);
			}
			
			// Assigning the Dataset relationship
			if(isset($result["dataset"]))
			{
				$pred = $xml->createPredicate("http://purl.org/dc/terms/isPartOf");
				$object = $xml->createObject("http://rdfs.org/ns/void#Dataset", $result["dataset"]);
				$pred->appendChild($object);
				$subject->appendChild($pred);				
			}
	
			// Assigning the Properties -> Literal relationships
			foreach($result as $property => $values)
			{
				if($property != "type" && $property != "dataset")
				{
					foreach($values as $value)
					{
						$pred = $xml->createPredicate($property);
						$object = $xml->createObjectContent($this->xmlEncode($value));
						$pred->appendChild($object);
						$subject->appendChild($pred);
					}
				}
			}
			
			// Assigning object_property
			if(isset($this->resultsetObjectProperties[$uri]))
			{
				foreach($this->resultsetObjectProperties[$uri] as $property => $values)
				{
					if($propeerty != "type" && $property != "dataset")
					{
						foreach($values as $value)
						{
							$pred = $xml->createPredicate($property);
							$object = $xml->createObject("", "", "");
							$pred->appendChild($object);
							
							$reify = $xml->createReificationStatement("wsf:objectLabel", $value);
							$object->appendChild($reify);
							
							$subject->appendChild($pred);
						}
					}
				}
			}
			
			$resultset->appendChild($subject);	
		}
		
		// Include facet information
		
		// Type
		
		if(strtolower($this->includeAggregates) == "true")
		{
			$aggregatesUri = $this->uri."aggregate/".md5(microtime());
			
			$typeLabelsCounts = array();
			
			foreach($this->aggregates["type"] as $ftype => $fcount)
			{
				// If we have an inferred type, we use that count instead of the normal count.
				if(isset($this->aggregates["inferred_type"][$ftype]))
				{
					$fcount = $this->aggregates["inferred_type"][$ftype];
				}
				
				$subject = $xml->createSubject("aggr:Aggregate", $aggregatesUri."/".md5($ftype)."/");

				$pred = $xml->createPredicate("aggr:property");
				$object = $xml->createObject("", "http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
				$pred->appendChild($object);
				$subject->appendChild($pred);				

				$pred = $xml->createPredicate("aggr:object");
				$object = $xml->createObject("", $ftype);
				$pred->appendChild($object);
				$subject->appendChild($pred);				

				$pred = $xml->createPredicate("aggr:count");
				$object = $xml->createObjectContent($this->xmlEncode($fcount));
				$pred->appendChild($object);
				$subject->appendChild($pred);				
				
				$resultset->appendChild($subject);
	
				$typeLabelsCounts = array();
			}
			
			// For each inferred type that have been left so far, we re-introduce them in the aggregates
			foreach($this->aggregates["inferred_type"] as $ftype => $fcount)
			{
				// If we have an inferred type, we use that count instead of the normal count.
				if(!isset($this->aggregates["type"][$ftype]))
				{
					$subject = $xml->createSubject("aggr:Aggregate", $aggregatesUri."/".md5($ftype)."/");
	
					$pred = $xml->createPredicate("aggr:property");
					$object = $xml->createObject("", "http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
					$pred->appendChild($object);
					$subject->appendChild($pred);				
	
					$pred = $xml->createPredicate("aggr:object");
					$object = $xml->createObject("", $ftype);
					$pred->appendChild($object);
					$subject->appendChild($pred);				
	
					$pred = $xml->createPredicate("aggr:count");
					$object = $xml->createObjectContent($this->xmlEncode($fcount));
					$pred->appendChild($object);
					$subject->appendChild($pred);				
					
					$resultset->appendChild($subject);
				}
			}
						
			// Dataset
			
			$aggregatesUri = $this->uri."aggregate/".md5(microtime());
			
			foreach($this->aggregates["dataset"] as $ftype => $fcount)
			{
					$subject = $xml->createSubject("aggr:Aggregate", $aggregatesUri."/".md5($ftype)."/");
	
					$pred = $xml->createPredicate("aggr:property");
					$object = $xml->createObject("", "http://rdfs.org/ns/void#Dataset");
					$pred->appendChild($object);
					$subject->appendChild($pred);				
	
					$pred = $xml->createPredicate("aggr:object");
					$object = $xml->createObject("", $ftype);
					$pred->appendChild($object);
					$subject->appendChild($pred);				
	
					$pred = $xml->createPredicate("aggr:count");
					$object = $xml->createObjectContent($this->xmlEncode($fcount));
					$pred->appendChild($object);
					$subject->appendChild($pred);				
					
					$resultset->appendChild($subject);
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
		$xmlDoc = substr($xmlDoc, 0, $posHeader)."\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Search DTD 0.1//EN\" \"".parent::$dtdBaseURL.$this->dtdURL."\">".substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);	
		
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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, Search::$supportedSerializations);

		// Validate query
		$this->validateQuery();
		
		// If the query is still valid
		if($this->conneg->getStatus() == 200)
		{
			// Check for errors
			if($this->query == "")
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("No query specified for this request");
				return;
			}
			
			if($this->items < 0 || $this->items > 128)
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("The number of items returned per request has to be greater than 0 and lesser than 128");
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
		
				$namespaces = array(	"http://www.w3.org/2002/07/owl#" => "owl",
												"http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
												"http://www.w3.org/2000/01/rdf-schema#" => "rdfs",
												"http://purl.org/ontology/wsf#" => "wsf",
												"http://purl.org/ontology/aggregate#" => "aggr");			
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
				
					$json_part .= "      { \n";
					$json_part .= "        \"uri\": \"".parent::jsonEncode($subjectURI)."\", \n";
					$json_part .= "        \"type\": \"".parent::jsonEncode($namespaces[$stNs].":".$stExtension)."\", \n";

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
														
								$ns = $this->getNamespace($predicateType);
								$ptNs = $ns[0];
								$ptExtension = $ns[1];
							
								if(!isset($namespaces[$ptNs]))
								{
									$namespaces[$ptNs] = "ns".$nsId;
									$nsId++;
								}						
														
								$json_part .= "          { \n";
								$json_part .= "            \"".parent::jsonEncode($namespaces[$ptNs].":".$ptExtension)."\": \"".parent::jsonEncode($objectValue)."\" \n";
								$json_part .= "          },\n";
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
								
								$json_part .= "          { \n";
								$json_part .= "            \"".parent::jsonEncode($namespaces[$ptNs].":".$ptExtension)."\": { \n";
								$json_part .= "            	  \"uri\": \"".parent::jsonEncode($objectURI)."\",\n";
								
								// Check if there is a reification statement for this object.
								$reifies = $xml->getReificationStatementsByType($object, "wsf:objectLabel");
							
								$nbReification = 0;
							
								foreach($reifies as $reify)
								{
									$nbReification++;
									
									if($nbReification > 0)
									{
										$json_part .= "           	  \"reifies\": [\n";
									}
									
									$json_part .= "           	    { \n";
									$json_part .= "                     \"type\": \"wsf:objectLabel\", \n";
									$json_part .= "                     \"value\": \"".parent::jsonEncode($xml->getValue($reify))."\" \n";
									$json_part .= "           	    },\n";
								}
								
								if($nbReification > 0)
								{
									$json_part = substr($json_part, 0, strlen($json_part) - 2)."\n";
									
									$json_part .= "           	  ]\n";
								}
								else
								{
									$json_part = substr($json_part, 0, strlen($json_part) - 2)."\n";
								}								
								
								$json_part .= "            	} \n";
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
				
				
    			$json_header .="  \"prefixes\": [ \n";
				$json_header .="    {\n";
				foreach($namespaces as $ns => $prefix)
				{
					$json_header .= "      \"$prefix\": \"$ns\",\n";
				}	
				
				if(strlen($json_header) > 0)
				{
					$json_header = substr($json_header, 0, strlen($json_header) - 2)."\n";
				}				
							
				$json_header .="    } \n";
				$json_header .="  ],\n";	
				$json_header .= "  \"resultset\": {\n";
				$json_header .= "    \"subjects\": [\n";
				$json_header .= $json_part;
				$json_header .= "    ]\n";
				$json_header .= "  }\n";				
				
				return($json_header);		
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
							$objectContent = $xml->getContent($object);
							
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
						$rdf_part = substr($rdf_part, 0, strlen($rdf_part) - 2).".\n";
					}
					
				}

				return($rdf_part);		
			break;
			case "application/rdf+xml":
				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());
				
				$subjects = $xml->getSubjects();
				
				$namespaces = array();
				
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
				}

				$rdf_header = "<rdf:RDF ";

				foreach($namespaces as $ns => $prefix)
				{
					$rdf_header .= " xmlns:$prefix=\"$ns\"";
				}
				
				$rdf_header .= ">\n\n";
				
				$rdf_part = $rdf_header.$rdf_part;
				
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
			else
			{
				$pos = strpos($uri, ":");
				
				if($pos !== FALSE)
				{
					return explode(":", $uri, 2);
				}
			}
		}
		
		return(FALSE);
	}
	
	/*!	 @brief Non implemented method (only defined)
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function pipeline_serialize_reification()
	{
		$rdf_reification = "";
		switch($this->conneg->getMime())
		{
			case "application/rdf+n3":
				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());
				
				$subjects = $xml->getSubjects();
				
				$bnodeCounter = 0;
				
				foreach($subjects as $subject)
				{
					$predicates = $xml->getPredicates($subject);
					
					foreach($predicates as $predicate)
					{
						$predicateType = $xml->getType($predicate);
						
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$reifies = $xml->getReificationStatementsByType($object, "wsf:objectLabel");
							
							foreach($reifies as $reify)
							{
								$rdf_reification .= "_:bnode".$bnodeCounter." a rdf:Statement ;\n";
								$bnodeCounter++;
								$rdf_reification .= "    rdf:subject <".$xml->getURI($subject)."> ;\n";
								$rdf_reification .= "    rdf:predicate <".$predicateType."> ;\n";
								$rdf_reification .= "    rdf:object _:bnode".$bnodeCounter." ;\n";
								$rdf_reification .= "    wsf:objectLabel \"".$xml->getValue($reify)."\" .\n\n";
								$bnodeCounter++;
							}
						}
					}
				}
				
				return($rdf_reification);	
							
			break;
			
			case "application/rdf+xml":
			
				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());
				
				$subjects = $xml->getSubjects();
				
				foreach($subjects as $subject)
				{
					$predicates = $xml->getPredicates($subject);
					
					foreach($predicates as $predicate)
					{
						$predicateType = $xml->getType($predicate);
					
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$reifies = $xml->getReificationStatementsByType($object, "wsf:objectLabel");
							
							foreach($reifies as $reify)
							{
								$rdf_reification .= "<rdf:Statement>\n";
								$rdf_reification .= "    <rdf:subject rdf:resource=\"".$xml->getURI($subject)."\" />\n";
								$rdf_reification .= "    <rdf:predicate rdf:resource=\"".$predicateType."\" />\n";
								$rdf_reification .= "    <rdf:object rdf:resource=\"".$xml->getURI($object)."\" />\n";
								$rdf_reification .= "    <wsf:objectLabel>".$xml->getValue($reify)."</wsf:objectLabel>\n";
								$rdf_reification .= "</rdf:Statement>	\n\n";
								
							}
						}
					}
				}
				
				return($rdf_reification);			
				
			break;
		}	
	}	
	
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
				$rdf_document .= "@prefix wsf: <http://purl.org/ontology/wsf#> .\n";
				
				$rdf_document .= $this->pipeline_serialize();
				
				$rdf_document .= $this->pipeline_serialize_reification();
				
				return $rdf_document;
			break;
	
			case "application/rdf+xml":
				$rdf_document = "";
				$rdf_document .= "<?xml version=\"1.0\"?>\n";
			
				$rdf_document .= $this->pipeline_serialize();
				
				$rdf_document .= $this->pipeline_serialize_reification();
			
				$rdf_document .= "</rdf:RDF>";
			
				return $rdf_document;
			break;

			case "application/json":
				$json_document = "";
				$json_document .= "{\n";
				$json_document .= $this->pipeline_serialize();
				$json_document .= "}";
				
				return($json_document);
			break;		

			case "text/xml":
				return $this->pipeline_getResultset();
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
	

	/*!	 @brief Send a search query to the search system
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function process()
	{
		// Make sure there was no conneg error prior to this process call
		if($this->conneg->getStatus() == 200)
		{
			$solr = new Solr(parent::$wsf_solr_core);		
		
			$solrQuery = "";
		
			// Get all datasets accessible to that user
			
			$accessibleDatasets = array();

			$ws_al = new AuthLister("access_user", "", $this->registered_ip, parent::$wsf_local_ip);
			
			$ws_al->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
			
			$ws_al->process();

			$xml = new ProcessorXML();
			$xml->loadXML($ws_al->pipeline_getResultset());
			
			$accesses = $xml->getSubjectsByType("wsf:Access");

			foreach($accesses as $access)
			{
				$predicates = $xml->getPredicatesByType($access, "wsf:datasetAccess");
				$objects = $xml->getObjects($predicates->item(0));
				$datasetUri = $xml->getURI($objects->item(0));
				
				$predicates = $xml->getPredicatesByType($access, "wsf:read");
				$objects = $xml->getObjects($predicates->item(0));
				$read = $xml->getContent($objects->item(0));
				
				if(strtolower($read) == "true")
				{
					array_push($accessibleDatasets, $datasetUri);
				}
			}
			
			if(count($accessibleDatasets) <= 0)
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("No dataset accessible by that user");
				return;
			}
			
			unset($ws_al);

			if(strtolower($this->datasets) == "all")
			{
				$datasetList = "";
				
				$solrQuery = "q=object_label:(".urlencode($this->query).") OR (".urlencode($this->query)."\)&start=".$this->page."&rows=".$this->items.(strtolower($this->includeAggregates) == "true" ? "&facet=true&facet.limit=-1&facet.field=type".(strtolower($this->inference) == "on" ? "&facet.field=inferred_type" : "")."&facet.field=dataset&facet.mincount=1" : "");
				
				foreach($accessibleDatasets as $key => $dataset)
				{
					if($key == 0)
					{
						$solrQuery .= "&fq=dataset:%22".urlencode($dataset)."%22";
					}
					else
					{
						$solrQuery .= " OR dataset:%22".urlencode($dataset)."%22";
					}
				}
			}
			else
			{
				$datasets = explode(";", $this->datasets);
				
				$solrQuery = "q=object_label:(".urlencode($this->query).") OR (".urlencode($this->query).")&start=".$this->page."&rows=".$this->items.(strtolower($this->includeAggregates) == "true" ? "&facet=true&facet.limit=-1&facet.field=type".(strtolower($this->inference) == "on" ? "&facet.field=inferred_type" : "")."&facet.field=dataset&facet.mincount=1" : "");
				
				$solrQuery .= "&fq=dataset:%22%22";
				
				foreach($datasets as $dataset)
				{
					// Check if the dataset is accessible to the user
					if(array_search($dataset, $accessibleDatasets) !== FALSE)
					{
						// Decoding potentially encoded ";" characters
						$dataset = str_replace(array("%3B", "%3b"), ";", $dataset);
						
						$solrQuery .= " OR dataset:%22".urlencode($dataset)."%22";
					}
				}
			}
				
			if($this->types != "all")
			{	
				// Lets include the information to facet per type.
				
				$types = explode(";", $this->types);
				
				$nbProcessed = 0;
				
				foreach($types as $type)
				{
					// Decoding potentially encoded ";" characters					
					$type = str_replace(array("%3B", "%3b"), ";", $type);
					
					if($nbProcessed == 0)
					{
						$solrQuery .= "&fq=type:%22".urlencode($type)."%22";
					}
					else
					{
						$solrQuery .= " OR type:%22".urlencode($type)."%22";
					}
					
					$nbProcessed++;
					
					if(strtolower($this->inference) == "on")
					{
						$solrQuery .= " OR inferred_type:%22".urlencode($type)."%22";
					}
				}
			}			
			
			$resultset = $solr->select($solrQuery);

			$domResultset = new DomDocument("1.0", "utf-8"); 
			$domResultset->loadXML($resultset);

			$xpath = new DOMXPath($domResultset);
	
			// Get the number of results
			$founds = $xpath->query("*[@numFound]");
			
			foreach($founds as $found)
			{
				$nbResources = $found->attributes->getNamedItem("numFound")->nodeValue;
				break;
			}

			// Get all the "type" facets with their counts
			$founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='type']/int");
			
			// Get types counts
			
			$this->aggregates["type"] = array();
			
			foreach($founds as $found)
			{
				$this->aggregates["type"][$found->attributes->getNamedItem("name")->nodeValue] = $found->nodeValue;
			}					
			
			// Get inferred types counts
			
			if(strtolower($this->inference) == "on")
			{
				// Get all the "inferred_type" facets with their counts
				$founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='inferred_type']/int");
				
				// Get types counts
				$this->aggregates["inferred_type"] = array();
				
				foreach($founds as $found)
				{
					$this->aggregates["inferred_type"][$found->attributes->getNamedItem("name")->nodeValue] = $found->nodeValue;
				}					
			}
			
			// Get all the "dataset" facets with their counts
			$founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='dataset']/int");
			
			$this->aggregates["dataset"] = array();
			
			foreach($founds as $found)
			{
				$this->aggregates["dataset"][$found->attributes->getNamedItem("name")->nodeValue] = $found->nodeValue;
			}									

			// Get all the results
			
			$resultsDom = $xpath->query("//doc");
			
			foreach($resultsDom as $result)
			{
				// get URI
				$resultURI = $xpath->query("arr[@name='uri']/str", $result);
				
				$uri = "";
				
				foreach($resultURI as $u)
				{
					$uri = $u->nodeValue;
					$this->resultset[$uri] = array();
					break;
				}

				// get Dataset URI
				$resultDatasetURI = $xpath->query("arr[@name='dataset']/str", $result);
				
				$datasetUri = "";
				
				foreach($resultDatasetURI as $u)
				{
					$this->resultset[$uri]["dataset"] = $u->nodeValue;
					break;
				}

									
				// get result property					
				$resultProperties = $xpath->query("arr[@name='property']/str", $result);
				
				$tempProperties = array();
				
				foreach($resultProperties as $property)
				{
					array_push($tempProperties, $property->nodeValue);
				}

				// get result property text				
				$resultTextes = $xpath->query("arr[@name='text']/str", $result);
				
				foreach($resultTextes as $key => $text)
				{
					if(!isset($this->resultset[$uri][$tempProperties[$key]]))
					{
						$this->resultset[$uri][$tempProperties[$key]] = array($text->nodeValue);
					}
					else
					{
						array_push($this->resultset[$uri][$tempProperties[$key]], $text->nodeValue);
					}
				}
				
				// get result object_property					
				$resultProperties = $xpath->query("arr[@name='object_property']/str", $result);
				
				$tempProperties = array();
				
				foreach($resultProperties as $property)
				{
					array_push($tempProperties, $property->nodeValue);
				}

				// get result object_property label				
				$resultTextes = $xpath->query("arr[@name='object_label']/str", $result);
				
				foreach($resultTextes as $key => $text)
				{
					if(!isset($this->resultsetObjectProperties[$uri][$tempProperties[$key]]))
					{
						$this->resultsetObjectProperties[$uri][$tempProperties[$key]] = array($text->nodeValue);
					}
					else
					{
						array_push($this->resultsetObjectProperties[$uri][$tempProperties[$key]], $text->nodeValue);
					}
				}						
				
	
				// Get the first type of the resource.
				$resultTypes = $xpath->query("arr[@name='type']/str", $result);
				
				foreach($resultTypes as $t)
				{
					if(!isset($this->resultset[$uri]["type"]))
					{
						$this->resultset[$uri]["type"] = array($t->nodeValue);
					}
					else
					{
						array_push($this->resultset[$uri]["type"], $t->nodeValue);
					}
				}
			}					
		}
	}
}


//@} 


?>