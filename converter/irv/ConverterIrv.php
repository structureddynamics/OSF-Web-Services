<?php

	/*! @defgroup WsConverterIrv Converter IRV Web Service */
	//@{ 

	/*! @file \ws\converter\irv\ConverterIrv.php
		 @brief Define the IRV converter class
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.
		 
		 \n\n\n
	 */
	
	
	/*!	 @brief Convert IRV data into RDF.
			 @details 	This class takes IRV (table separeted values) files as input, convert them into RDF using the BKN Ontology, 
							and output RDF in different formats.
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class ConverterIrv extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;
	
	/*! @brief Text being converted */
	private $text;

	/*! @brief Mime type of the document */
	private $docmime;

	/*! @brief Type of the resource being converted */
	private $type;
	
	/*! @brief Error message to report */
	private $errorMessages = "";

	/*! @brief IP of the requester */
	private $requester_ip = "";

	/*! @brief Requested IP */
	private $registered_ip = "";	
	
	/*! @brief Parser */
	private $parser;	
	
	/*! @brief Defined dummany namespaces/prefixes used for data conversion for some serializations */
	private $namespaces = array();
	
	/*! @brief 	Custom linkage schema used to include within the dataset's description when no linkage schemas exists
	 * 				for some types and attributes.
	 */
	private $customLinkageSchema;
	
	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/bib+json", "application/irv+json", "application/wsf+json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/tsv", "text/csv", "text/xml", "text/*", "*/*");
		
	/*!	 @brief Constructor
			 @details 	Initialize the IRV Converter Web Service
							
			\n
			
			@param[in] $document Text of a Bibtex document
			@param[in] $docmime Mime type of the document
			@param[in] $registered_ip Target IP address registered in the WSF
			@param[in] $requester_ip IP address of the requester
		
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($document="", $docmime="application/irv+json", $registered_ip, $requester_ip)
	{
		parent::__construct();		
		
		$this->text = $document;
		$this->docmime = $docmime;
		
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
		
		$this->irvResources = array();
		
		$this->uri = parent::$wsf_base_url."/wsf/ws/converter/irv/";	
		$this->title = "Instance Record Vocabulary Converter Web Service";	
		$this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
		$this->endpoint = parent::$wsf_base_url."/ws/converter/irv/";			
		
		$this->dtdURL = "converter/irv.dtd";	
		
		$this->customLinkageSchema = new LinkageSchema();
		$this->customLinkageSchema->setLinkedFormat("rdf");
	}

	function __destruct() 
	{
		parent::__destruct();
		
		if(isset($this->db))
		{
			$this->db->close();
		}
	}
	
	/*!	 @brief Validate a query to this web service
							
			\n
			
			@return TRUE if valid; FALSE otherwise
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	protected function validateQuery(){ return; }	
	
	protected function splitUri($str, &$base, &$ext)
	{
		$pos = FALSE;
		
		$base = "";
		$ext = "";
		
		if(($pos = strrpos($str, "#")) === FALSE)
		{
			$pos = strrpos($str, "/");
		}
		
		if($pos !== FALSE)
		{
			$base = substr($str, 0, $pos);
			$ext = substr($str, $pos + 1, strlen($str) - $pos - 1);
		}
	}

	/*!	 @brief Generate the converted IRV items using the internal XML representation
							
			\n
			
			@return a XML document
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function pipeline_getResultset()
	{
		if($this->docmime == "text/xml")
		{
			return($this->text);
		}
		else
		{
			// Check if a linkage file of kind RDF has been defined for this IRV file.
			foreach($this->parser->linkageSchemas as $linkageSchema)
			{
				if(strtolower($linkageSchema->linkedFormat) == "rdf")
				{
					$xml = new ProcessorXML();
			
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
					
					//
					// Map dataset
					//
					
					$datasetSubject = $xml->createSubject("http://rdfs.org/ns/void#Dataset", $this->parser->dataset->id[0]); 
						
					// Map labels
					if(isset($this->parser->dataset->label))
					{
						foreach($this->parser->dataset->label as $key => $label)
						{
							if(gettype($key) == "integer")
							{
								$pred = $xml->createPredicate("http://www.w3.org/2000/01/rdf-schema#label");
								$object = $xml->createObjectContent($label);
								
								$pred->appendChild($object);
								$datasetSubject->appendChild($pred);			
							}
						}
					}				

					// Map descriptions
					if(isset($this->parser->dataset->description))
					{
						foreach($this->parser->dataset->description as $key => $description)
						{
							if(gettype($key) == "integer")
							{
								$pred = $xml->createPredicate("http://www.w3.org/2000/01/rdf-schema#comment");
								$object = $xml->createObjectContent($description);
								
								$pred->appendChild($object);
								$datasetSubject->appendChild($pred);			
							}
						}
					}	
					
					// Map the Linkage Schema
					if(isset($this->parser->dataset->linkageSchema))
					{
						foreach($this->parser->dataset->linkageSchema as $key => $ls)
						{
							if(gettype($key) == "integer")
							{
								$pred = $xml->createPredicate("http://purl.org/ontology/wsf#linkageSchema");
								$object = $xml->createObjectContent($ls);
								
								$pred->appendChild($object);
								$datasetSubject->appendChild($pred);			
							}
						}
					}						

					// Map the Structure Schema
					if(isset($this->parser->dataset->structureSchema))
					{
						foreach($this->parser->dataset->structureSchema as $key => $ss)
						{
							if(gettype($key) == "integer")
							{
								$pred = $xml->createPredicate("http://purl.org/ontology/wsf#structureSchema");
								$object = $xml->createObjectContent($ss);
								
								$pred->appendChild($object);
								$datasetSubject->appendChild($pred);			
							}
						}
					}						

					
					// Map created
					if(isset($this->parser->dataset->created))
					{
						foreach($this->parser->dataset->created as $key => $created)
						{
							if(gettype($key) == "integer")
							{
								$pred = $xml->createPredicate("http://purl.org/dc/terms/created");
								$object = $xml->createObjectContent($created);
								
								$pred->appendChild($object);
								$datasetSubject->appendChild($pred);			
							}
						}
					}						
				
					// Map sources, creators, maintainers and curators
					$variables = array("source", "creator", "maintainer", "curator");
					foreach($variables as $var)
					{
						if(isset($this->parser->dataset->{$var}))
						{
							foreach($this->parser->dataset->{$var} as $key => $value)
							{
								if(gettype($key) == "integer")
								{
									$pred = $xml->createPredicate("http://purl.org/ontology/irv#".$var);
									
									if(isset($value["ref"]))
									{
										$value["ref"] = $this->parser->dataset->id[0].substr($value["ref"], 1, strlen($value["ref"]) - 1);
									} 
									else
									{
										$value["ref"] = parent::$wsf_graph."irs/".md5(microtime());
									}
									
									$object = $xml->createObject("", $value["ref"]);
									$pred->appendChild($object);
		
									// Check if we have to reify UI related information about this property-object pair
									if(isset($value["label"]))
									{
										$reify = $xml->createReificationStatement("http://www.w3.org/2000/01/rdf-schema#label", $value["label"]);
										$object->appendChild($reify);
									}
									
									if(	isset($value["href"]))
									{
										$reify = $xml->createReificationStatement("http://purl.org/ontology/bibo/uri", $value["href"]);
										$object->appendChild($reify);
									}
									
									$datasetSubject->appendChild($pred);		
								}
							}
						}
					}



					$resultset->appendChild($datasetSubject);
					
					//
					// Map instance records
					//

					foreach($this->parser->instanceRecords as $instanceRecord)
					{
						$uri = $this->parser->dataset->id[0].$instanceRecord->id[0];
						
						$subject;
						
						// Map types
						if(isset($instanceRecord->type))
						{
							foreach($instanceRecord->type as $key => $type)
							{
								if(gettype($key) != "string")
								{
									if(isset($linkageSchema->typeX[$type][0]["mapTo"]))
									{
										$type = $linkageSchema->typeX[$type][0]["mapTo"];
									}
									else
									{
//										$type = "http://www.w3.org/2002/07/owl#Thing";
/*! @TODO removing the end of $type and add an entry in the automatically generated linkage schema.*/
//										$type = parent::$wsf_graph."ontology/types/".$type;

////////// Temporaty //////////////
												if(strpos($type, "http://") === FALSE)
												{
													$type = parent::$wsf_graph."ontology/types/".$type;
												}
/////////////////////////////////////	

									}
								}
								
								if($key == "0")
								{
									$subject = $xml->createSubject($type, $uri);
								}
								elseif(gettype($key) != "string")
								{
									$pred = $xml->createPredicate("http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
									$object = $xml->createObject("", $type);
									
									$pred->appendChild($object);
									$subject->appendChild($pred);			
								}
							}
						}
						else
						{
							$subject = $xml->createSubject("http://www.w3.org/2002/07/owl#Thing", $uri);
						}
						
						// Map labels
						if(isset($instanceRecord->label))
						{
							foreach($instanceRecord->label as $key => $label)
							{
								if(gettype($key) == "integer")
								{
									$pred = $xml->createPredicate("http://www.w3.org/2000/01/rdf-schema#label");
									$object = $xml->createObjectContent($label);
									
									$pred->appendChild($object);
									$subject->appendChild($pred);			
								}
							}
						}

						// Map description
						if(isset($instanceRecord->description))
						{
							foreach($instanceRecord->description as $key => $description)
							{
								if(gettype($key) == "integer")
								{
									$pred = $xml->createPredicate("http://www.w3.org/2000/01/rdf-schema#comment");
									$object = $xml->createObjectContent($description);
									
									$pred->appendChild($object);
									$subject->appendChild($pred);			
								}
							}
						}

						// Map sameAs / isLike
						if(isset($instanceRecord->sameAs))
						{
							foreach($instanceRecord->sameAs as $key => $sameAs)
							{
								if(gettype($key) == "integer")
								{
									$pred = $xml->createPredicate("http://umbel.org/umbel#isLike");
									$object = $xml->createObject("", $sameAs);
									
									$pred->appendChild($object);
									$subject->appendChild($pred);			
								}
							}
						}

						// Map other attributes
						if(isset($instanceRecord->attributeX))
						{
							foreach($instanceRecord->attributeX as $property => $object)
							{
								if(	$instanceRecord->attributeX[$property]["valueType"] == "string" ||
									$instanceRecord->attributeX[$property]["valueType"] == "array(string)")
								{
									foreach($instanceRecord->attributeX[$property] as $key => $value)
									{
										if(gettype($key) == "integer")
										{
											// Get the linked property.
											$prop;
											
											if(isset($linkageSchema->propertyX[$property][0]["mapTo"]))
											{
												$prop = $linkageSchema->propertyX[$property][0]["mapTo"];
											}
											else
											{
												// If the property is not linked, we create one in the temporary ontology of the node.
/*! @TODO removing the end of $type and add an entry in the automatically generated linkage schema.*/
//												$prop = parent::$wsf_graph."ontology/properties/".$property;

////////// Temporaty //////////////
												if(strpos($property, "http://") === FALSE)
												{
													$prop = parent::$wsf_graph."ontology/properties/".$property;
												}
												else
												{
													$prop = $property;
												}
/////////////////////////////////////												
												
									//			$prop = $property;
											}
											
											$pred = $xml->createPredicate($prop);
											$object = $xml->createObjectContent($value);
											
											$pred->appendChild($object);
											$subject->appendChild($pred);			
										}
									}
								}

								if(	$instanceRecord->attributeX[$property]["valueType"] == "object" ||
									$instanceRecord->attributeX[$property]["valueType"] == "array(object)")
								{
									
									foreach($instanceRecord->attributeX[$property] as $key => $value)
									{
										// Fix the reference before conversion
										if(!isset($instanceRecord->attributeX[$property][$key]["ref"]))
										{
											// If no reference has been specified, we have to create a BNode for it even if the
											// object (instance record) is not defined anywhere.
											$instanceRecord->attributeX[$property][$key]["ref"] = parent::$wsf_graph."irs/".md5(microtime());
										}
										else
										{
											if(substr($instanceRecord->attributeX[$property][$key]["ref"], 0, 1) == "@")
											{
												$instanceRecord->attributeX[$property][$key]["ref"] = $this->parser->dataset->id[0].substr($instanceRecord->attributeX[$property][$key]["ref"], 1, strlen($instanceRecord->attributeX[$property][$key]["ref"]) - 1);
											}
										}
										
										if(gettype($key) == "integer")
										{
											// Get the linked property.
											$prop;
											
											if(isset($linkageSchema->propertyX[$property][0]["mapTo"]))
											{
												$prop = $linkageSchema->propertyX[$property][0]["mapTo"];
											}
											else
											{
												// If the property is not linked, we create one in the temporary ontology of the node.
/*! @TODO removing the end of $type and add an entry in the automatically generated linkage schema.*/
//												$prop = parent::$wsf_graph."ontology/properties/".$property;

////////// Temporaty //////////////
												if(strpos($property, "http://") === FALSE)
												{
													$prop = parent::$wsf_graph."ontology/properties/".$property;
												}
												else
												{
													$prop = $property;
												}
/////////////////////////////////////	

//												$prop = $property;
											}

											$pred = $xml->createPredicate($prop);
											$object = $xml->createObject("", $instanceRecord->attributeX[$property][$key]["ref"]);
											
											// Check if we have to reify UI related information about this property-object pair
											if(isset($instanceRecord->attributeX[$property][$key]["label"]))
											{
												$reify = $xml->createReificationStatement("http://www.w3.org/2000/01/rdf-schema#label", $instanceRecord->attributeX[$property][$key]["label"]);
												$object->appendChild($reify);
											}
											if(isset($instanceRecord->attributeX[$property][$key]["href"]))
											{
												$reify = $xml->createReificationStatement("http://purl.org/ontology/bibo/uri", $instanceRecord->attributeX[$property][$key]["href"]);
												$object->appendChild($reify);
											}

											$pred->appendChild($object);

											$subject->appendChild($pred);			
										}
									}
								}
							}
						}

						$resultset->appendChild($subject);
					}

					return($this->injectDoctype($xml->saveXML($resultset)));		
				}
			}
			
			// No RDF linkage file exists for this IRV file, then we throw an error
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("No linkage file of type 'RDF' has been defined for this Instance Record Vocabulary file. Cant convert this file in '".$this->conneg->getMime()."'");
			
		}
	}
	
	/*!	 @brief Get the domain of a URL
	
			\n
			
			@param[in] $url the full URL
					   
			@return the domain name of the URL *with* the prefix "http://"

			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	private function get_domain($url)
	{
		if(strlen($url) > 8)
		{
			$pos = strpos($url, "/", 8);
			
			if($pos === FALSE)
			{
				return $url;
			}
			else
			{
				return substr($url, 0, $pos);
			}
		}
		else
		{
			return $url;
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
		$xmlDoc = substr($xmlDoc, 0, $posHeader)."\n<!DOCTYPE resultset PUBLIC \"-//Bibliographic Knowledge Network//Converter IRV DTD 0.1//EN\" \"".parent::$dtdBaseURL.$this->dtdURL."\">".substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);	
		
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
		$this->conneg = new Conneg(	$accept, $accept_charset, $accept_encoding, $accept_language, ConverterIrv::$supportedSerializations);

		// No text to process? Throw an error.
		if($this->text == "")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("No data to convert");
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

	/*!	 @brief Serialize the converted UCB Memorial Data content into different serialization formats
							
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
			case "application/irv+json":
			case "application/bib+json":
				$irv = "{\n";

				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());
				
				$subjects = $xml->getSubjects();
				
				if($this->conneg->getMime() == "application/bib+json")
				{
					$datasetJson = "    \"Metadata\": {\n";
					$instanceRecordsJson = "    \"Records\": [\n";
				}
				else
				{
					$datasetJson = "    \"Dataset\": {\n";
					$instanceRecordsJson = "    \"InstanceRecords\": [\n";
				}

				// The first thing we have to check is if a linkage schema is available for this dataset.
				// If it is not, then we simply use the RDF properties and values to populate the IRV+JSON file.
				
				$accesses = $xml->getSubjectsByType("http://rdfs.org/ns/void#Dataset");

				$ls = array();
				$linkageSchemas = array();

				foreach($accesses as $access)
				{
					// We check if there is a link to a linkage schema
					$predicates = $xml->getPredicatesByType($access, "http://purl.org/ontology/wsf#linkageSchema");
					
					if($predicates->length > 0)
					{
						$objects = $xml->getObjects($predicates->item(0));
						
						$linkageSchemaUrl = $xml->getContent($objects->item(0));
						if(substr($linkageSchemaUrl, 0, 7) == "http://")
						{
							$ch = curl_init();
							
							curl_setopt($ch, CURLOPT_URL, $linkageSchemaUrl);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
							
							$data = curl_exec($ch);
							$data = trim($data);
						
							if(!curl_errno($ch) && $data != "") 
							{
								$parsedContent = json_decode($data);
								
								array_push($ls, $parsedContent);								
							}
							
							curl_close($ch);
						}
					}
					else
					{
						// If no link, we create a inline schema.
					}
						
					// We check if there is a link to a structure schema
						
					// If no link, we create an inline schema.					
				}
				
				// Now populate the linkage schema object.
				foreach($ls as $linkageSchema)
				{
					$linkageSchema = $linkageSchema->linkageSchema;
					
					$tempSchema = new LinkageSchema();
					
					// Set version
					$tempSchema->setVersion($linkageSchema->version);			
		
					// Set linkedFormat
					$tempSchema->setLinkedFormat($linkageSchema->linkedFormat);
					
					// Set prefixes
					foreach($linkageSchema->prefixes as $prefix => $uri)
					{
						$tempSchema->setPrefix($prefix, $uri);
					}			
					
					// Set propertieslinkageSchemas
					foreach($linkageSchema->properties as $property => $values)
					{
						$tempSchema->setPropertyX($property, $values->mapTo, $error);
					}
					
					// Set types
					foreach($linkageSchema->types as $type => $values)
					{
						$adds = array();
						
						if(isset($values->add))
						{
							foreach($values->add as $key => $value)
							{
								$adds[$key] = $value;
							}
						}
						
						$error = "";
						
						$tempSchema->setTypeX($type, $values->mapTo, $adds, $error);
					}
		
					array_push($linkageSchemas, $tempSchema);
				}		

				// Now lets create the IRV+JSON serialization for dataset description and instance records descriptions.
				
				// Get the base (dataset) ID
				$datasetID = "";
				foreach($subjects as $subject)
				{
					$subjectURI = $xml->getURI($subject);
					$subjectType = $xml->getType($subject, FALSE);
					
					if($subjectType == "http://rdfs.org/ns/void#Dataset")
					{
						$datasetID = $subjectURI;
					}
				}
				
				foreach($subjects as $subject)
				{
					$subjectURI = $xml->getURI($subject);
					$subjectType = $xml->getType($subject, FALSE);
					
					if($subjectType == "http://rdfs.org/ns/void#Dataset")
					{
						$datasetJson .= "        \"id\": \"$datasetID\",\n";						
						
						$predicates = $xml->getPredicates($subject);
						
						$processingPredicateNum = 1;
						$predicateType = "";						
						
						foreach($predicates as $predicate)
						{
							$objects = $xml->getObjects($predicate);
							
							foreach($objects as $object)
							{
								$objectType = $xml->getType($object);						
								
								if($predicateType != $xml->getType($predicate, FALSE))
								{				
									$predicateType = $xml->getType($predicate, FALSE);
									$processingPredicateNum = 1;
								}
								else
								{
									$processingPredicateNum++;
								}
								$objectContent = $xml->getContent($object);
								
								$predicateName = "";

								switch($predicateType)
								{
									case "http://www.w3.org/2000/01/rdf-schema#label":
										$predicateName = "label";
									break;
									
									case "http://www.w3.org/2000/01/rdf-schema#comment":
										$predicateName = "description";
									break;
									
									case "http://purl.org/ontology/wsf#linkageSchema":
										$predicateName = "linkageSchema";
									break;
									
									case "http://purl.org/ontology/wsf#structureSchema":
										$predicateName = "structureSchema";
									break;
									
									case "http://purl.org/dc/terms/created":
										$predicateName = "created";
									break;
									
									case "http://purl.org/ontology/irv#source":
										$predicateName = "source";
									break;
									
									case "http://purl.org/ontology/irv#creator":
										$predicateName = "creator";
									break;
									
									case "http://purl.org/ontology/irv#maintainer":
										$predicateName = "maintainer";
									break;
									
									case "http://purl.org/ontology/irv#curator":
										$predicateName = "curator";
									break;
								}								

								if($predicateName != "")
								{
									if(($this->nbPredicates($subject, $xml, $predicateType) > 1 && $processingPredicateNum == 1))
									{
										$datasetJson .= "        \"$predicateName\": [";
									}
									
									if($objectType == "rdfs:Literal")
									{
										$objectValue = $xml->getContent($object);						
										
											if($objects->length == 1)
											{
												if($predicateName == "linkageSchema")
												{
													$datasetJson .= "        \"$predicateName\": [\"".$this->jsonEscape($objectValue)."\"],\n";
												}
												else
												{
													$datasetJson .= "        \"$predicateName\": \"".$this->jsonEscape($objectValue)."\",\n";
												}
											}
											else
											{
												$datasetJson .= "        \"".$this->jsonEscape($objectValue)."\",";
											}
	
									}
									else
									{
										$objectURI = $xml->getURI($object);
									
										$datasetJson .= "        \"$predicateName\": {\n";
										$datasetJson .= "            \"ref\": \"".$this->jsonEscape(str_replace($datasetID, "@", $objectURI))."\",\n";
										
										$reifies = $xml->getReificationStatements($object);
	
										foreach($reifies as $reify)
										{
											$v = $xml->getValue($reify);
											$t = $xml->getType($reify, FALSE);
											
											if($t == "http://www.w3.org/2000/01/rdf-schema#label")
											{
												$datasetJson .= "            \"label\": \"".$this->jsonEscape($v)."\",\n";
											}
											
											if($t == "http://purl.org/ontology/bibo/uri")
											{
												$datasetJson .= "            \"href\": \"".$this->jsonEscape($v)."\",\n";
											}
										}
									
										$datasetJson = substr($datasetJson, 0, strlen($datasetJson) - 2)."\n";
	
										$datasetJson .= "        },\n";
										
									}
								}
							}
							
							if($this->nbPredicates($subject, $xml, $predicateType) > 1 && $processingPredicateNum == $this->nbPredicates($subject, $xml, $predicateType))
							{
								$datasetJson = substr($datasetJson, 0, strlen($datasetJson) - 2);
								$datasetJson .= " ],\n";
							}
							
						}
						
						$datasetJson = substr($datasetJson, 0, strlen($datasetJson) - 2)."\n";
						$datasetJson .= "    },\n";
						
					}
					else
					{
						$instanceRecordsJson .= "        {\n";
						
						$instanceRecordsJson .= "            \"id\": \"".str_replace($datasetID, "", $subjectURI)."\",\n";						
						
						// Get the type
						$typeName = $subjectType;
						$break = FALSE;
						foreach($linkageSchemas as $linkageSchema)
						{
							if(strtolower($linkageSchema->linkedFormat) == "rdf")
							{				
								foreach($linkageSchema->typeX as $type => $value)
								{
									if($value[0]["mapTo"] == $subjectType)
									{
										$typeName = $type;
										$break = TRUE;
										break;
									}
								}		
								
								if($break)
								{
									break;
								}
							}
						}
						
						// If there is no linked type for this type, we use the end of the automatically RDF type generated.
						if($break === FALSE)
						{
/*! @TODO: custom linkage file */							
							$typeName = str_replace(parent::$wsf_graph."ontology/types/", "", $typeName);
							
							$this->splitUri($typeName, $base, $ext);
							$this->customLinkageSchema->setTypeX($ext, $typeName, "", $error);
							
							$typeName = $ext;
						}						

						if($this->nbPredicates($subject, $xml, "http://www.w3.org/1999/02/22-rdf-syntax-ns#type") >= 1)
						{
							$instanceRecordsJson .= "            \"type\": [ \n";						
							
							$predicates = $xml->getPredicates($subject);
							
							foreach($predicates as $predicate)
							{
								$pt = $xml->getType($predicate, FALSE);

								if($pt == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
								{
									$objects = $xml->getObjects($predicate);
									
									$objectValue = $xml->getURI($objects->item(0));
									
									// Get the type
									$break = FALSE;
									foreach($linkageSchemas as $linkageSchema)
									{
										if(strtolower($linkageSchema->linkedFormat) == "rdf")
										{				
											foreach($linkageSchema->typeX as $type => $value)
											{
												if($value[0]["mapTo"] == $objectValue)
												{
													$objectValue = $type;
													$break = TRUE;
													break;
												}
											}		
											
											if($break)
											{
												break;
											}
										}
									}
									
									// If there is no linked type for this type, we use the end of the automatically RDF type generated.
									if($break === FALSE)
									{
			/*! @TODO: custom linkage file */							
										$objectValue = str_replace(parent::$wsf_graph."ontology/types/", "", $objectValue);
										
										$this->splitUri($objectValue, $base, $ext);
										$this->customLinkageSchema->setTypeX($ext, $objectValue, "", $error);
										
										$objectValue = $ext;
									}											
									
									
									$instanceRecordsJson .= "                \"".$objectValue."\", \n";													
								}							
							}
							
							$instanceRecordsJson .= "                \"".$typeName."\" ],\n";						
						}
						else
						{
							$instanceRecordsJson .= "            \"type\": \"".$typeName."\",\n";						
						}

						$predicates = $xml->getPredicates($subject);
						
						$processingPredicateNum = 1;
						$predicateType = "";
						
						foreach($predicates as $predicate)
						{
							$objects = $xml->getObjects($predicate);
							
							foreach($objects as $object)
							{
								$objectType = $xml->getType($object);		
								
								if($predicateType != $xml->getType($predicate, FALSE))
								{				
									$predicateType = $xml->getType($predicate, FALSE);
									$processingPredicateNum = 1;
								}
								else
								{
									$processingPredicateNum++;
								}
								
								$objectContent = $xml->getContent($object);
								
								$predicateName = "";

								$break = FALSE;

								switch($predicateType)
								{
									case "http://www.w3.org/2000/01/rdf-schema#label":
										$predicateName = "label";
									break;
									
									case "http://www.w3.org/2000/01/rdf-schema#comment":
										$predicateName = "description";
									break;
									
									case "http://www.w3.org/1999/02/22-rdf-syntax-ns#type":
										$break = TRUE;
									break;
									
									case "http://umbel.org/umbel#isLike":
										$predicateName = "sameAs";
									break;
								}		
								
								if($break)
								{
									break;
								}
								
								if($predicateName == "")
								{
									// Check for a linked property.
									foreach($linkageSchemas as $linkageSchema)
									{
										if(strtolower($linkageSchema->linkedFormat) == "rdf")
										{				
											foreach($linkageSchema->propertyX as $property => $value)
											{
												if($value[0]["mapTo"] == $predicateType)
												{
													$predicateName = $property;
												}
											}		
										}
									}
								}			
								
								if($predicateName == "")
								{
/*! @TODO: custom linkage file */							
									
									// If we still dont have a reference to a property name, we use the end of the RDF generated one.
									$predicateName = str_replace(parent::$wsf_graph."ontology/properties/", "", $predicateType);
									
									$this->splitUri($predicateType, $base, $ext);
									$this->customLinkageSchema->setPropertyX($ext, $predicateType, $error);
									
									$predicateName = $ext;
								}

								if($this->nbPredicates($subject, $xml, $predicateType) > 1 && $processingPredicateNum == 1)
								{
									$instanceRecordsJson .= "            \"$predicateName\": [\n";
								}
							
								if($objectType == "rdfs:Literal")
								{
									$objectValue = $xml->getContent($object);						
/*
									if($predicateName == "type")
									{
										// Get the type
										$break = FALSE;
										foreach($linkageSchemas as $linkageSchema)
										{
											if(strtolower($linkageSchema->linkedFormat) == "rdf")
											{				
												foreach($linkageSchema->typeX as $type => $value)
												{
													if($value[0]["mapTo"] == $objectValue)
													{
														$objectValue = $type;
														$break = TRUE;
														break;
													}
												}		
												
												if($break)
												{
													break;
												}
											}
										}							
									}
*/									
									if($this->nbPredicates($subject, $xml, $predicateType) == 1)
									{
										$instanceRecordsJson .= "            \"$predicateName\": \"".$this->jsonEscape($objectValue)."\",\n";
									}
									else
									{
										$instanceRecordsJson .= "            \"".$this->jsonEscape($objectValue)."\",\n";
									}

								}
								else
								{
									$objectURI = $xml->getURI($object);
								
									if($this->nbPredicates($subject, $xml, $predicateType) > 1)
									{								
										$instanceRecordsJson .= "            {\n";
									}
									else
									{
										$instanceRecordsJson .= "            \"$predicateName\": {\n";
									}
									
									$instanceRecordsJson .= "                \"ref\": \"".$this->jsonEscape(str_replace($datasetID, "@", $objectURI))."\",\n";
									 
									$reifies = $xml->getReificationStatements($object);

									foreach($reifies as $reify)
									{
										$v = $xml->getValue($reify);
										$t = $xml->getType($reify, FALSE);
										
										if($t == "http://www.w3.org/2000/01/rdf-schema#label")
										{
											$instanceRecordsJson .= "                \"label\": \"".$this->jsonEscape($v)."\",\n";
										}
										
										if($t == "http://purl.org/ontology/bibo/uri")
										{
											$instanceRecordsJson .= "                \"href\": \"".$this->jsonEscape($v)."\",\n";
										}
									}
								
									$instanceRecordsJson = substr($instanceRecordsJson, 0, strlen($instanceRecordsJson) - 2)."\n";

									$instanceRecordsJson .= "            },\n";
								}
							}
							
							if($this->nbPredicates($subject, $xml, $predicateType) > 1 && $processingPredicateNum == $this->nbPredicates($subject, $xml, $predicateType))
							{
								$instanceRecordsJson = substr($instanceRecordsJson, 0, strlen($instanceRecordsJson) - 2);
								$instanceRecordsJson .= "     ],\n";
							}
							
						}
						
						$instanceRecordsJson = substr($instanceRecordsJson, 0, strlen($instanceRecordsJson) - 2)."\n";
										
						$instanceRecordsJson .= "        },\n";
					}
				}

				$instanceRecordsJson = substr($instanceRecordsJson, 0, strlen($instanceRecordsJson) - 2)."\n";

				$instanceRecordsJson .= "        ]\n";

				$irv .= $datasetJson.$instanceRecordsJson;


				$irv .= "}";
				
				// Inject the custom linakge schema in the dataset description.
				
				if(($pos = stripos($irv, '"linkageSchema"')) !== FALSE)
				{
					$posStart = strpos($irv, "[", $pos);
					$irv = substr($irv, 0, $posStart + 1).$this->customLinkageSchema->generateJsonSerialization().",\n".substr($irv, $posStart + 1, strlen($irv) - $posStart);
				}
				else
				{
					// no linakgeSchema property found. Lets create one.
					if(($pos = stripos($irv, '"Dataset"')) !== FALSE)
					{
						$posStart = strpos($irv, "{", $pos);
						$irv = substr($irv, 0, $posStart + 1)."\n        \"linkageSchema\": [\n".$this->customLinkageSchema->generateJsonSerialization()."        ],\n".substr($irv, $posStart + 1, strlen($irv) - $posStart);
					}
				}
				
				return($irv);		
			break;
			
			case "text/tsv":
			case "text/csv":

				$tsv = "";
				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());
				
				$subjects = $xml->getSubjects();
			
				foreach($subjects as $subject)
				{
					$subjectURI = $xml->getURI($subject);
					$subjectType = $xml->getType($subject, FALSE);
				
					$tsv .= str_replace($this->delimiter, urlencode($this->delimiter), $subjectURI).$this->delimiter."http://www.w3.org/1999/02/22-rdf-syntax-ns#type".$this->delimiter.str_replace($this->delimiter, urlencode($this->delimiter), $subjectType)."\n";

					$predicates = $xml->getPredicates($subject);
					
					foreach($predicates as $predicate)
					{
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$objectType = $xml->getType($object);						
							$predicateType = $xml->getType($predicate, FALSE);
							$objectContent = $xml->getContent($object);
							
							if($objectType == "rdfs:Literal")
							{
								$objectValue = $xml->getContent($object);						
								$tsv .= str_replace($this->delimiter, urlencode($this->delimiter), $subjectURI).$this->delimiter.str_replace($this->delimiter, urlencode($this->delimiter), $predicateType).$this->delimiter.str_replace($this->delimiter, urlencode($this->delimiter), $objectValue)."\n";
							}
							else
							{
								$objectURI = $xml->getURI($object);						
								$tsv .= str_replace($this->delimiter, urlencode($this->delimiter), $subjectURI).$this->delimiter.str_replace($this->delimiter, urlencode($this->delimiter), $predicateType).$this->delimiter.str_replace($this->delimiter, urlencode($this->delimiter), $objectURI)."\n";
							}
						}
					}
				}

				return($tsv);					
			break;
			
			case "application/rdf+n3":
				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());
				
				$subjects = $xml->getSubjects();
			
				foreach($subjects as $subject)
				{
					$subjectURI = $xml->getURI($subject);
					$subjectType = $xml->getType($subject, FALSE);
				
					$rdf_part .= "\n    <$subjectURI> a <$subjectType> ;\n";

					$predicates = $xml->getPredicates($subject);
					
					foreach($predicates as $predicate)
					{
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$objectType = $xml->getType($object);						
							$predicateType = $xml->getType($predicate, FALSE);
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
				
				$this->namespaces = array(	"http://www.w3.org/2002/07/owl#" => "owl",
														"http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
														"http://www.w3.org/2000/01/rdf-schema#" => "rdfs",
														"http://purl.org/ontology/wsf#" => "wsf");				
				
				$nsId = 0;
				
				foreach($subjects as $subject)
				{
					$subjectURI = $xml->getURI($subject);
					$subjectType = $xml->getType($subject, FALSE);
				
					$ns = $this->getNamespace($subjectType);
					$stNs = $ns[0];
					$stExtension = $ns[1];
				
					if(!isset($this->namespaces[$stNs]))
					{
						$this->namespaces[$stNs] = "ns".$nsId;
						$nsId++;
					}
				
					$rdf_part .= "\n    <".$this->namespaces[$stNs].":".$stExtension." rdf:about=\"$subjectURI\">\n";

					$predicates = $xml->getPredicates($subject);
					
					foreach($predicates as $predicate)
					{
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$objectType = $xml->getType($object);						
							$predicateType = $xml->getType($predicate, FALSE);
							
							if($objectType == "rdfs:Literal")
							{
								$objectValue = $xml->getContent($object);	
								
								$ns = $this->getNamespace($predicateType);
								$ptNs = $ns[0];
								$ptExtension = $ns[1];
							
								if(!isset($this->namespaces[$ptNs]))
								{
									$this->namespaces[$ptNs] = "ns".$nsId;
									$nsId++;
								}
													
								$rdf_part .= "        <".$this->namespaces[$ptNs].":".$ptExtension.">".$this->xmlEncode($objectValue)."</".$this->namespaces[$ptNs].":".$ptExtension.">\n";
							}
							else
							{
								$objectURI = $xml->getURI($object);		
								
								$ns = $this->getNamespace($predicateType);
								$ptNs = $ns[0];
								$ptExtension = $ns[1];
							
								if(!isset($this->namespaces[$ptNs]))
								{
									$this->namespaces[$ptNs] = "ns".$nsId;
									$nsId++;
								}
												
								$rdf_part .= "        <".$this->namespaces[$ptNs].":".$ptExtension." rdf:resource=\"$objectURI\" />\n";
								}
						}
					}

					$rdf_part .= "    </".$this->namespaces[$stNs].":".$stExtension.">\n";
				}

				return($rdf_part);
			break;
		}		
	}
	
	public function jsonEscape($str)
	{
		return str_replace('"', '', $str);
	}

	public function nbPredicates(&$subject, &$xml, $predicate)
	{
		$predicates = $xml->getPredicatesByType($subject, $predicate);
		
		return($predicates->length);
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
						$predicateType = $xml->getType($predicate, FALSE);
						
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$reifies = $xml->getReificationStatements($object);
							
							$first = 0;
							
							foreach($reifies as $reify)
							{
								if($first == 0)
								{
									$rdf_reification .= "_:bnode".$bnodeCounter." a rdf:Statement ;\n";
									$bnodeCounter++;
									$rdf_reification .= "    rdf:subject <".$xml->getURI($subject)."> ;\n";
									$rdf_reification .= "    rdf:predicate <".$predicateType."> ;\n";
									$rdf_reification .= "    rdf:object _:bnode".$bnodeCounter." ;\n";
								}
								
								$first++;
								
								$reifyingProperty = $xml->getType($reify, FALSE);								
								
								$rdf_reification .= "    <$reifyingProperty> \"".$xml->getValue($reify)."\" ;\n";
							}
							
							if($first > 0)
							{
								$bnodeCounter++;
								$rdf_reification = substr($rdf_reification, 0, strlen($rdf_reification) - 2).".\n\n";
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
						$predicateType = $xml->getType($predicate, FALSE);
					
						$objects = $xml->getObjects($predicate);
						
						foreach($objects as $object)
						{
							$reifies = $xml->getReificationStatements($object);

							$first = 0;

							foreach($reifies as $reify)
							{
								if($first == 0)
								{
									$rdf_reification .= "    <rdf:Statement>\n";
									$rdf_reification .= "        <rdf:subject rdf:resource=\"".$xml->getURI($subject)."\" />\n";
									$rdf_reification .= "        <rdf:predicate rdf:resource=\"".$predicateType."\" />\n";
									$rdf_reification .= "        <rdf:object rdf:resource=\"".$xml->getURI($object)."\" />\n";
								}
								
								$first++;
								
								$nsId = count($this->namespaces);
								
								$reifyingProperty = $xml->getType($reify, FALSE);
								
								$ns = $this->getNamespace($reifyingProperty);
								
								$ptNs = $ns[0];
								$ptExtension = $ns[1];
							
								if(!isset($this->namespaces[$ptNs]))
								{
									$this->namespaces[$ptNs] = "ns".$nsId;
								}
								
								$rdf_reification .= "        <".$this->namespaces[$ptNs].":".$ptExtension.">".$xml->getValue($reify)."</".$this->namespaces[$ptNs].":".$ptExtension.">\n";
							}
							
							if($first > 0)
							{
								$rdf_reification .= "    </rdf:Statement>	\n\n";
							}
						}
					}
				}
				
				return($rdf_reification);			
				
			break;
		}	
	}	
	
	/*!	 @brief Serialize the converted UCB Memorial Data content into different serialization formats
							
			\n
			
			@return returns the serialized content
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function ws_serialize()
	{
		// Check for parsing errors
		if($this->conneg->getStatus() != 200)
		{
			return;
		}
		else
		{
			switch($this->conneg->getMime())
			{
				case "text/tsv":
				case "text/csv":
					return $this->pipeline_serialize();
				break;
				
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
					$rdf_header = "<?xml version=\"1.0\"?>\n";
				
				
					$rdf_document .= $this->pipeline_serialize();
					
					$rdf_document .= $this->pipeline_serialize_reification();

					$rdf_header .= "<rdf:RDF ";
	
					foreach($this->namespaces as $ns => $prefix)
					{
						$rdf_header .= " xmlns:$prefix=\"$ns\"";
					}
					
					$rdf_header .= ">\n\n";				

				
					$rdf_document .= "</rdf:RDF>";
				
					return $rdf_header.$rdf_document;
				break;
	
				case "text/xml":
					return $this->pipeline_getResultset();
				break;
				
				case "application/irv+json":
				case "application/bib+json":
					return($this->pipeline_serialize());
				break;
			}	
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
	
	
	/*!	 @brief Normalize the remaining of a URI
							
			\n
			
			@param[in] $uri The remaining of a URI to normalize
			
			@return a Normalized remaining URI
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/			
	private function uriEncode($uri) 
	{
		$uri = preg_replace("|[^a-zA-z0-9]|", " ", $uri);
		$uri = preg_replace("/\s+/", " ", $uri);
		$uri = str_replace(" ", "_", $uri);
		
		return($uri);
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
	
	/*!	 @brief Parse the TSV file for declaraton error (properties or classes used in the file that are not defined on the node)
							
			\n
			
			@return returns TRUE if there is errors; FALSE otherwise
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	private function irvParsingError()
	{
		return FALSE;
	}	
	
	/*!	 @brief Convert the target document
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function process()
	{
		if($this->conneg->getStatus() == 200)
		{
			switch($this->docmime)
			{	
				case "application/irv+json":
				case "application/bib+json":

					$this->parser = new JsonParser($this->text, $this->docmime);

					if(count($this->parser->jsonErrors) > 0)
					{
						$errorMsg = "";
						foreach($this->parser->jsonErrors as $key => $error)
						{
							$errorMsg .= "\n(".($key + 1).") $error \n";
						}

						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("JSON parsing error(s): ".$errorMsg);						
					}
					elseif(count($this->parser->irvErrors) > 0)
					{
						$errorMsg = "";
						foreach($this->parser->irvErrors as $key => $error)
						{
							$errorMsg .= "\n(".($key + 1).") $error \n";
						}
						
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("IRV validation error(s): ".$errorMsg);						
					}			
				break;
				
				case "text/xml":
				break;
			}
			
			
		}
	}
}

	//@} 


?>