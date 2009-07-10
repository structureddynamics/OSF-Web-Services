<?php

	/*! @ingroup WsSparql */
	//@{ 

	/*! @file \ws\sparql\Sparql.php
		 @brief Define the Sparql web service
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief SPARQL Web Service. It sends SPARQL queries to datasets indexed in the structWSF instance.
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class Sparql extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;

	/*! @brief Sparql query */
	private $query = "";
	
	/*! @brief Dataset where t send the query */
	private $dataset = "";
	
	/*! @brief IP of the requester */
	private $requester_ip = "";
	
	/*! @brief Limit of the number of results to return in the resultset */
	private $limit = "";
	
	/*! @brief Offset of the "sub-resultset" from the total resultset of the query */
	private $offset = "";

	/*! @brief Requested IP */
	private $registered_ip = "";

	/*! @brief SPARQL query content resultset */
	private $sparqlContent = "";
	
	/*! @brief Instance records from the query where the object of the triple is a literal */
	private $instanceRecordsObjectLiteral = array();

	/*! @brief Instance records from the query where the object of the triple is a resource */
	private $instanceRecordsObjectResource = array();

	/*! @brief Supported MIME serializations by this web service */
	public static $supportedSerializations = array("text/xml", "application/sparql-results+xml", "application/sparql-results+json", "text/html", "application/rdf+xml", "application/rdf+n3", "application/*", "text/plain", "text/*", "*/*");


	/*!	 @brief Constructor
			 @details 	Initialize the Sparql Web Service
				
			@param[in] $query SPARQL query to send to the triple store of the WSF
			@param[in] $dataset Dataset URI where to send the query
			@param[in] $limit Limit of the number of results to return in the resultset
			@param[in] $offset Offset of the "sub-resultset" from the total resultset of the query
			@param[in] $registered_ip Target IP address registered in the WSF
			@param[in] $requester_ip IP address of the requester
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($query, $dataset, $limit, $offset, $registered_ip, $requester_ip)
	{
		parent::__construct();		
		
		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->query = $query;
		$this->limit = $limit;
		$this->offset = $offset;
		$this->dataset = $dataset;
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

		$this->uri = parent::$wsf_base_url."/wsf/ws/sparql/";	
		$this->title = "Sparql Web Service";	
		$this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
		$this->endpoint = parent::$wsf_base_url."/ws/sparql/";			
		
		$this->dtdURL = "sparql/sparql.dtd";
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
		return;
		
		// Validation of the "requester_ip" to make sure the system that is sending the query as the rights.
		$ws_av = new AuthValidator($this->requester_ip, $this->dataset, $this->uri);
		
		$ws_av->pipeline_conneg("*/*", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
		
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
		
		$ws_av->pipeline_conneg("*/*", $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
		
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
		if($this->conneg->getMime() == "application/sparql-results+xml" ||
		   $this->conneg->getMime() == "application/sparql-results+json")
		{
			return $this->sparqlContent;	
		}
		else
		{
			$labelProperties = array(	Namespaces::$dcterms."title",
												Namespaces::$foaf."name",
												Namespaces::$foaf."givenName",
												Namespaces::$foaf."family_name",
												Namespaces::$rdfs."label",
												Namespaces::$skos_2004."prefLabel",
												Namespaces::$skos_2004."altLabel",
												Namespaces::$skos_2008."prefLabel",
												Namespaces::$skos_2008."altLabel"
												);						
			
			$xml = new ProcessorXML();
		
			// Creation of the RESULTSET
			$resultset = $xml->createResultset();
	
			$subject;

			foreach($this->instanceRecordsObjectResource as $uri => $result)
			{
				// Assigning types
				if(isset($result["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"]))
				{
					foreach($result["http://www.w3.org/1999/02/22-rdf-syntax-ns#type"] as $key => $type)
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
				
				// Assigning object resource properties
				foreach($result as $property => $values)
				{
					if($property != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
					{
						foreach($values as $value)
						{
							$label = "";
							foreach($labelProperties as $labelProperty)
							{
								if($this->instanceRecordsObjectLiteral[$value])
								{
									// The object resource is part of the resultset		
									// This mainly occurs when we export complete datasets		
														
									if(isset($this->instanceRecordsObjectLiteral[$value][$labelProperty]))
									{
										$label = $this->instanceRecordsObjectLiteral[$value][$labelProperty][0];
										break;
									}
								}
								else
								{
									// The object resource is not part of the resultset
									// In the future, we can send another sparql query to get its label.
								}
							}
							
							$pred = $xml->createPredicate($property);
							$object = $xml->createObject("", $value, ($label != "" ? $label : ""));
							$pred->appendChild($object);
							
							$subject->appendChild($pred);
						}
					}
				}
				
				// Assigning object literal properties
				if(isset($this->instanceRecordsObjectLiteral[$uri]))
				{
					foreach($this->instanceRecordsObjectLiteral[$uri] as $property => $values)
					{
						if($property != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
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
							
					$resultset->appendChild($subject);
				}
			}
			
			return($this->injectDoctype($xml->saveXML($resultset)));			
		}
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
		$xmlDoc = substr($xmlDoc, 0, $posHeader)."\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//SPARQL DTD 0.1//EN\" \"".parent::$dtdBaseURL.$this->dtdURL."\">".substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);	
		
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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, Sparql::$supportedSerializations);

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
			
			if($this->dataset == "")
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("No dataset specified for this request");
				return;
			}
			
			if($this->limit > 2000)
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("The maximum number of records returned within the same slice is 2000. Use multiple queries with the OFFSET parameter to build-up the entire resultset.");
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
								$nodesList = $xml->getReificationStatements($object);
								
								if($nodesList->length == 0)
								{
									$objectURI = $xml->getURI($object);						
									$rdf_part .= "        <$predicateType> <$objectURI> ;\n";
								}
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
								$nodesList = $xml->getReificationStatements($object);
								
								if($nodesList->length == 0)
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
					}

					$rdf_part .= "    </".$namespaces[$stNs].":".$stExtension.">\n";
				}

				$rdf_header = "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\" xmlns:wsf=\"http://purl.org/ontology/wsf#\"";

				foreach($namespaces as $ns => $prefix)
				{
					$rdf_header .= " xmlns:$prefix=\"$ns\"";
				}
				
				$rdf_header .= ">\n\n";
				
				$rdf_part = $rdf_header.$rdf_part;
				
				return($rdf_part);
			break;
			
			case "text/xml":
			case "application/sparql-results+xml":
			case "application/sparql-results+json":
				return $this->pipeline_getResultset();		
			break;
		}				
	}
	
	/*!	 @brief Non implemented method (only defined)
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function pipeline_serialize_reification(){}	
	
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
			
			default:
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
	

	/*!	 @brief Send the SPARQL query to the triple store of this WSF
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function process()
	{
		// Make sure there was no conneg error prior to this process call
		if($this->conneg->getStatus() == 200)
		{
			$queryFormat = "";
		
			if(	$this->conneg->getMime() == "application/sparql-results+json" ||
				$this->conneg->getMime() == "application/sparql-results+xml" ||
				$this->conneg->getMime() == "text/html")
			{
				$queryFormat = $this->conneg->getMime();
			}
			elseif($this->conneg->getMime() == "text/xml" ||
					$this->conneg->getMime() == "application/rdf+xml" ||
					$this->conneg->getMime() == "application/rdf+n3")
			{
				$queryFormat = "application/sparql-results+xml";
			}
			
			$ch = curl_init();
	
			// Remove any potential reference to any graph in the sparql query.
			
			// Remove "from" clause
			$this->query = preg_replace("/([\s]*from[\s]*<.*>[\s]*)/Uim", "", $this->query);

			// Remove "from named" clauses
			$this->query = preg_replace("/([\s]*from[\s]*named[\s]*<.*>[\s]*)/Uim", "", $this->query);
	
			// Add a limit to the query
			
			// Disable limits and offset for now until we figure out what to do (not limit on triples, but resources)
//			$this->query .= " limit ".$this->limit." offset ".$this->offset;
			

			curl_setopt($ch, CURLOPT_URL, "http://localhost:8890/sparql?default-graph-uri=".urlencode($this->dataset)."&query=".urlencode($this->query)."&format=".urlencode($queryFormat));

			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: ".$this->conneg->getMime()));
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, TRUE);			
	
			$xml_data = curl_exec($ch);		
			
			$header = substr($xml_data, 0, strpos($xml_data, "\r\n\r\n"));
			
			$data = substr($xml_data, strpos($xml_data, "\r\n\r\n") + 4, strlen($xml_data) - (strpos($xml_data, "\r\n\r\n") - 4));
			
			curl_close($ch);
			
			// check returned message
			
			$httpMsgNum = substr($header, 9, 3);
			$httpMsg = substr($header, 13, strpos($header, "\r\n") - 13);
			
			if($httpMsgNum == "200")
			{
				$this->sparqlContent = $data;
			}
			else
			{
				$this->conneg->setStatus($httpMsgNum);
				$this->conneg->setStatusMsg($httpMsg);
				$this->conneg->setStatusMsgExt($data);
			
				$this->sparqlContent = "";
			}		
			
//			if($this->conneg->getMime() == "text/xml")
//			{
				// Read the XML file and populate the recordInstances variables
				$xml = $this->xml2ary($this->sparqlContent);
	
				if(isset($xml["sparql"]["_c"]["results"]["_c"]["result"]))
				{
					foreach($xml["sparql"]["_c"]["results"]["_c"]["result"] as $result)
					{
						$s = "";
						$p = "";
						$o = "";
						
						foreach($result["_c"]["binding"] as $binding)
						{
							$boundVariable = $binding["_a"]["name"];
							
							$keys = array_keys($binding["_c"]);
							
							$boundType = $keys[0];
							$boundValue = $binding["_c"][$boundType]["_v"];
							
							switch($boundVariable)
							{
								case "s":
									$s = $boundValue;
								break;
									
								case "p":
									$p = $boundValue;
								break;
									
								case "o":
									$o = $boundValue;
								break;	
							}
						}
						
						if($boundType == "uri")
						{
							if(!isset($this->instanceRecordsObjectResource[$s][$p]))
							{
								$this->instanceRecordsObjectResource[$s][$p] = array($o);
							}
							else
							{
								array_push($this->instanceRecordsObjectResource[$s][$p], $o);
							}
						}
						
						if($boundType == "literal")
						{
							if(!isset($this->instanceRecordsObjectLiteral[$s][$p]))
							{
								$this->instanceRecordsObjectLiteral[$s][$p] = array($o);
							}
							else
							{
								array_push($this->instanceRecordsObjectLiteral[$s][$p], $o);
							}
						}
					}
				}	
			//}
			
			if(count($this->instanceRecordsObjectResource) <= 0)
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("No data to import");
			}
			
		}
	}
	
	/*
	    Working with XML. Usage: 
	    $xml=xml2ary(file_get_contents('1.xml'));
	    $link=&$xml['ddd']['_c'];
	    $link['twomore']=$link['onemore'];
	    // ins2ary(); // dot not insert a link, and arrays with links inside!
	    echo ary2xml($xml);
	    
	    from: http://mysrc.blogspot.com/2007/02/php-xml-to-array-and-backwards.html
	*/
	
	// XML to Array
	private function xml2ary(&$string) 
	{
	    $parser = xml_parser_create();
	    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	    xml_parse_into_struct($parser, $string, $vals, $index);
	    xml_parser_free($parser);
	
	    $mnary=array();
	    $ary=&$mnary;
	    foreach ($vals as $r) {
	        $t=$r['tag'];
	        if ($r['type']=='open') {
	            if (isset($ary[$t])) {
	                if (isset($ary[$t][0])) $ary[$t][]=array(); else $ary[$t]=array($ary[$t], array());
	                $cv=&$ary[$t][count($ary[$t])-1];
	            } else $cv=&$ary[$t];
	            if (isset($r['attributes'])) {foreach ($r['attributes'] as $k=>$v) $cv['_a'][$k]=$v;}
	            $cv['_c']=array();
	            $cv['_c']['_p']=&$ary;
	            $ary=&$cv['_c'];
	
	        } elseif ($r['type']=='complete') {
	            if (isset($ary[$t])) { // same as open
	                if (isset($ary[$t][0])) $ary[$t][]=array(); else $ary[$t]=array($ary[$t], array());
	                $cv=&$ary[$t][count($ary[$t])-1];
	            } else $cv=&$ary[$t];
	            if (isset($r['attributes'])) {foreach ($r['attributes'] as $k=>$v) $cv['_a'][$k]=$v;}
	            $cv['_v']=(isset($r['value']) ? $r['value'] : '');
	
	        } elseif ($r['type']=='close') {
	            $ary=&$ary['_p'];
	        }
	    }    
	    
	    $this->_del_p($mnary);
	    return $mnary;
	}
	
	// _Internal: Remove recursion in result array
	private function _del_p(&$ary) 
	{
	    foreach ($ary as $k=>$v) {
	        if ($k==='_p') unset($ary[$k]);
	        elseif (is_array($ary[$k])) $this->_del_p($ary[$k]);
	    }
	}
	
	// Array to XML
	private function ary2xml($cary, $d=0, $forcetag='') 
	{
	    $res=array();
	    foreach ($cary as $tag=>$r) {
	        if (isset($r[0])) {
	            $res[]=ary2xml($r, $d, $tag);
	        } else {
	            if ($forcetag) $tag=$forcetag;
	            $sp=str_repeat("\t", $d);
	            $res[]="$sp<$tag";
	            if (isset($r['_a'])) {foreach ($r['_a'] as $at=>$av) $res[]=" $at=\"$av\"";}
	            $res[]=">".((isset($r['_c'])) ? "\n" : '');
	            if (isset($r['_c'])) $res[]=ary2xml($r['_c'], $d+1);
	            elseif (isset($r['_v'])) $res[]=$r['_v'];
	            $res[]=(isset($r['_c']) ? $sp : '')."</$tag>\n";
	        }
	        
	    }
	    return implode('', $res);
	}
	
	// Insert element into array
	private function ins2ary(&$ary, $element, $pos) 
	{
	    $ar1=array_slice($ary, 0, $pos); $ar1[]=$element;
	    $ary=array_merge($ar1, array_slice($ary, $pos));
	}
	
	
}


//@} 


?>