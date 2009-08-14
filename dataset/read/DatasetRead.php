<?php

	/*! @defgroup WsDataset Dataset Management Web Service  */
	//@{ 

	/*! @file \ws\dataset\read\DatasetRead.php
		 @brief Read a graph for this dataset & indexation of its description
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief Dataset Read Web Service. It reads description of datasets of a structWSF instance
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class DatasetRead extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;
	
	/*! @brief IP of the requester */
	private $requester_ip = "";

	/*! @brief Requested IP */
	private $registered_ip = "";

	/*! @brief URI of the target dataset(s). "all"mean all datasets visible to thatuser. */
	private $datasetUri = "";

	/*! @brief Description of one or multiple datasets */
	private $datasetsDescription = array();

	/*! @brief Add meta information to the resultset */
	private $addMeta = "false";


	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");
		
	/*!	 @brief Constructor
			 @details 	Initialize the Auth Web Service
							
			@param[in] $uri URI of the dataset to read (get its description)
			@param[in] $meta Add meta information with the resultset
			@param[in] $registered_ip Target IP address registered in the WSF
			@param[in] $requester_ip IP address of the requester
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($uri, $meta, $registered_ip, $requester_ip)
	{
		parent::__construct();		
		
		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->datasetUri = $uri;
		$this->requester_ip = $requester_ip;
		$this->addMeta = strtolower($meta);
		
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


		$this->uri = parent::$wsf_base_url."/wsf/ws/dataset/read/";	
		$this->title = "Dataset Read Web Service";	
		$this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
		$this->endpoint = parent::$wsf_base_url."/ws/dataset/read/";			
		
		$this->dtdURL = "dataset/datasetRead.dtd";
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
		$ws_av = new AuthValidator($this->requester_ip, parent::$wsf_graph."datasets/", $this->uri);
		
		$ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
		
		$ws_av->process();
		
		if($ws_av->pipeline_getResponseHeaderStatus() != 200)
		{
			$this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
			$this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
			$this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
		}
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
		$wsf = $xml->createPrefix("wsf", "http://purl.org/ontology/wsf#");
		$resultset->appendChild($wsf);
		$void = $xml->createPrefix("void", "http://rdfs.org/ns/void#");
		$resultset->appendChild($void);
		$rdf = $xml->createPrefix("rdf", "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
		$resultset->appendChild($rdf);
		$sioc = $xml->createPrefix("sioc", "http://rdfs.org/sioc/ns#");
		$resultset->appendChild($sioc);
		$dcterms = $xml->createPrefix("dcterms", "http://purl.org/dc/terms/");
		$resultset->appendChild($dcterms);
		$dcterms = $xml->createPrefix("rdfs", "http://www.w3.org/2000/01/rdf-schema#");
		$resultset->appendChild($dcterms);

		// Creation of the SUBJECT of the RESULTSET		
		foreach($this->datasetsDescription as $dd)
		{
			if($dd->uri != "")
			{
				$subject = $xml->createSubject("void:Dataset", $dd->uri);
		
				if($dd->title != "")
				{
					$pred = $xml->createPredicate("dcterms:title");
					$object = $xml->createObjectContent($this->xmlEncode($dd->title));
					$pred->appendChild($object);
					$subject->appendChild($pred);
				}
				
				if($dd->description != "")
				{
					$pred = $xml->createPredicate("dcterms:description");
					$object = $xml->createObjectContent($this->xmlEncode($dd->description));
					$pred->appendChild($object);
					$subject->appendChild($pred);
				}
				
				$pred = $xml->createPredicate("dcterms:created");
				$object = $xml->createObjectContent($this->xmlEncode($dd->created));
				$pred->appendChild($object);
				$subject->appendChild($pred);
				
				if($dd->modified != "")
				{
					$pred = $xml->createPredicate("dcterms:modified");
					$object = $xml->createObjectContent($this->xmlEncode($dd->modified));
					$pred->appendChild($object);
					$subject->appendChild($pred);
				}
				
				if($dd->creator != "")
				{
					$pred = $xml->createPredicate("dcterms:creator");
					$object = $xml->createObject("sioc:User", $dd->creator);
					$pred->appendChild($object);
					$subject->appendChild($pred);
				}

				if($dd->meta != "" && $this->addMeta == "true")
				{
					$pred = $xml->createPredicate("wsf:meta");
					$object = $xml->createObject("", $dd->meta);
					$pred->appendChild($object);
					$subject->appendChild($pred);
				}

				if(count($dd->metaDescription) > 0 && $this->addMeta == "true")
				{
					$subjectMeta = $xml->createSubject("void:Dataset", $dd->meta);		
					
					foreach($dd->metaDescription as $predicate => $values)
					{
						foreach($values as $key => $value)
						{
							if(gettype($key) == "integer" && $value != "")
							{
								if($dd->metaDescription[$predicate]["type"] == "http://www.w3.org/2001/XMLSchema#string")
								{
									$pred = $xml->createPredicate($predicate);
									$object = $xml->createObjectContent($this->xmlEncode($value));
									$pred->appendChild($object);
									$subjectMeta->appendChild($pred);									
								}
								else
								{
									$pred = $xml->createPredicate($predicate);
									$object = $xml->createObject("", $value);
									
									// Check if we have to reify statements to this object
									$query = "	select  ?s ?p ?o
													from <".$this->datasetUri."reification/>
													where
													{
														?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <".$dd->meta.">.
														?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> <".$predicate.">.
														?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> <".$value.">.
														?s ?p ?o.
													}";
		
									$rset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array("s", "p", "o"), FALSE));
															
									if(!odbc_error())
									{
										while(odbc_fetch_row($rset))
										{
											$s = odbc_result($rset, 1);
											$p = odbc_result($rset, 2);
											$o = odbc_result($rset, 3);
											
											if( $p == "http://purl.org/ontology/bibo/uri" ||
												$p == "http://www.w3.org/2000/01/rdf-schema#label")
											{
												$reify = $xml->createReificationStatement($p, $o);
												$object->appendChild($reify);												
											}
										}										
									}										
									
									
									$pred->appendChild($object);
									$subjectMeta->appendChild($pred);								
								}
							}
						}
					}	
					
					$resultset->appendChild($subjectMeta);		
				}

		
				foreach($dd->contributors as $contributor)
				{
					if($contributor != "")
					{
						$pred = $xml->createPredicate("dcterms:contributor");
						$object = $xml->createObject("sioc:User", $contributor);
						$pred->appendChild($object);
						$subject->appendChild($pred);
					}
				}
				
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
		$xmlDoc = substr($xmlDoc, 0, $posHeader)."\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Dataset Read DTD 0.1//EN\" \"".parent::$dtdBaseURL.$this->dtdURL."\">".substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);	
		
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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, DatasetRead::$supportedSerializations);

		// Validate query
		$this->validateQuery();
		
		// If the query is still valid
		if($this->conneg->getStatus() == 200)
		{
			// Check for errors
			if($this->uri == "")
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("No URI specified for any dataset");
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

				$dataset = $xml->getSubjectsByType("void:Dataset");
				
				$dataset_uri = $xml->getURI($dataset->item(0));
				
				$rdf_part .= "<$dataset_uri> a void:Dataset ;\n";						

				// Get title
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:title");
				if($predicates->item(0))
				{
					$objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
					$rdf_part .= "dcterms:title \"\"\"".$xml->getContent($objects->item(0))."\"\"\" ;\n";
				}

				// Get description
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:description");
				if($predicates->item(0))
				{				
					$objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
					$rdf_part .= "dcterms:description \"\"\"".$xml->getContent($objects->item(0))."\"\"\" ;\n";
				}

				// Get created
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:created");
				if($predicates->item(0))
				{				
					$objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
					$rdf_part .= "dcterms:created \"\"\"".$xml->getContent($objects->item(0))."\"\"\" ;\n";
				}

				// Get modified
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:modified");
				if($predicates->item(0))
				{
					$objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
					$rdf_part .= "dcterms:modified \"\"\"".$xml->getContent($objects->item(0))."\"\"\" ;\n";
				}

				// Get creator
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:creator");
				
				foreach($predicates as $predicate)
				{
					$objects = $xml->getObjectsByType($predicate, "sioc:User");
					$rdf_part .= "dcterms:creator <".$xml->getURI($objects->item(0))."> ;\n";						
				}

				// Get contributors
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:contributor");
				
				foreach($predicates as $predicate)
				{
					$objects = $xml->getObjectsByType($predicate, "sioc:User");
					$rdf_part .= "dcterms:contributor <".$xml->getURI($objects->item(0))."> ;\n";						
				}
				
				return($rdf_part);				
				
				
			break;
			case "application/rdf+xml":
			
				$xml = new ProcessorXML();
				$xml->loadXML($this->pipeline_getResultset());


				/*! @TODO Implementing the "nsX" generation for the RDF output of dataset read. This is needed otherwise
				 * 				 the generated document wont be valid if meta data values are added.
				 */
/*
				$namespaces = array(	"http://www.w3.org/2002/07/owl#" => "owl",
												"http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
												"http://www.w3.org/2000/01/rdf-schema#" => "rdfs",
												"http://purl.org/ontology/wsf#" => "wsf");				
				$nsId = 0;
*/

				$dataset = $xml->getSubjectsByType("void:Dataset");
				
				$dataset_uri = $xml->getURI($dataset->item(0));
				
				$rdf_part .= "<void:Dataset rdf:about=\"$dataset_uri\">\n";						

				// Get title
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:title");
				if($predicates->item(0))
				{
					$objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
					$rdf_part .= "<dcterms:title>".$xml->getContent($objects->item(0))."</dcterms:title>\n";
				}

				// Get description
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:description");
				if($predicates->item(0))
				{				
					$objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
					$rdf_part .= "<dcterms:description>".$xml->getContent($objects->item(0))."</dcterms:description>\n";
				}

				// Get created
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:created");
				if($predicates->item(0))
				{				
					$objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
					$rdf_part .= "<dcterms:created>".$xml->getContent($objects->item(0))."</dcterms:created>\n";
				}

				// Get modified
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:modified");
				if($predicates->item(0))
				{
					$objects = $xml->getObjectsByType($predicates->item(0), "rdfs:Literal");
					$rdf_part .= "<dcterms:modified>".$xml->getContent($objects->item(0))."</dcterms:modified>\n";
				}

				// Get creator
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:creator");
				
				foreach($predicates as $predicate)
				{
					$objects = $xml->getObjectsByType($predicate, "sioc:User");
					$rdf_part .= "<dcterms:creator rdf:resource=\"".$xml->getURI($objects->item(0))."\" />\n";						
				}

				// Get contributors
				$predicates = $xml->getPredicatesByType($dataset->item(0), "dcterms:contributor");
				
				foreach($predicates as $predicate)
				{
					$objects = $xml->getObjectsByType($predicate, "sioc:User");
					$rdf_part .= "<dcterms:contributor rdf:resource=\"".$xml->getURI($objects->item(0))."\" />\n";						
				}
				
				$rdf_part .= "</void:Dataset>\n";

				return($rdf_part);
			break;
		}		
		
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
				$rdf_document .= "@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n";
				$rdf_document .= "@prefix void: <http://rdfs.org/ns/void#> .\n";
				$rdf_document .= "@prefix dcterms: <http://purl.org/dc/terms/> .\n";
				$rdf_document .= "@prefix wsf: <http://purl.org/ontology/wsf#> .\n";
				
				$rdf_document .= $this->pipeline_serialize();
				
				$rdf_document = substr($rdf_document, 0, strlen($rdf_document) - 2).".\n";
			
				return $rdf_document;
			break;
	
			case "application/rdf+xml":
				$rdf_document = "";
				$rdf_document .= "<?xml version=\"1.0\"?>\n";
				$rdf_document .= "<rdf:RDF xmlns:wsf=\"http://purl.org/ontology/wsf#\" xmlns:void=\"http://rdfs.org/ns/void#\" xmlns:dcterms=\"http://purl.org/dc/terms/\" xmlns:rdfs=\"http://www.w3.org/2000/01/rdf-schema#\" xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\">\n\n";
				
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
    			$json_document .="  \"prefixes\": [ \n";
				$json_document .="    {\n";
				$json_document .="      \"rdf\": \"http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#\",\n"; 
				$json_document .="      \"rdfs\": \"http://www.w3.org/2000/01/rdf-schema#\",\n"; 
				$json_document .="      \"void\": \"http://rdfs.org/ns/void#\",\n"; 
				$json_document .="      \"dcterms\": \"http://purl.org/dc/terms/\"\n"; 
				$json_document .="    } \n";
				$json_document .="  ],\n";					
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
	

	/*!	 @brief Read informationa about a target dataset
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function process()
	{
		// Make sure there was no conneg error prior to this process call
		if($this->conneg->getStatus() == 200)
		{
			/*
				In the future, this single query should be used for that ALL purpose.
				There is currently a bug in virtuoso that doesnt return anything in the resultset (virtuoso v5.0.10)
				if one of the OPTIONAL pattern is not existing in the triple store (so, OPTIONAL doesn't work)
				
				sparql
				select * 
				from named </wsf/datasets/>
				from named </wsf/>
				where
				{
					graph </wsf/>
					{
						?access <http://purl.org/ontology/wsf#registeredIP> "174.129.251.47::1" ;
						<http://purl.org/ontology/wsf#read> "True" ;
						<http://purl.org/ontology/wsf#datasetAccess> ?dataset .
					}
					
					graph </wsf/datasets/> 
					{
						?dataset a <http://rdfs.org/ns/void#Dataset> ;
						<http://purl.org/dc/terms/created> ?created.
				
						OPTIONAL{?dataset <http://purl.org/dc/terms/title> ?title.}
						OPTIONAL{?dataset <http://purl.org/dc/terms/description> ?description.}
						OPTIONAL{?dataset <http://purl.org/dc/terms/creator> ?creator.}
						OPTIONAL{?dataset <http://purl.org/dc/terms/modified> ?modified.}
					}		
				};
			*/
		
		
		
		
			$query = "";
			$datasets = array();

			if($this->datasetUri == "all")
			{
				$query = "	select distinct ?dataset ?title ?description ?creator ?created ?modified ?contributor ?meta
									from named <".parent::$wsf_graph.">
									from named <".parent::$wsf_graph."datasets/>
									where
									{
										graph <".parent::$wsf_graph.">
										{
											?access <http://purl.org/ontology/wsf#registeredIP> ?ip ;
          									<http://purl.org/ontology/wsf#read> \"True\" ;
											<http://purl.org/ontology/wsf#datasetAccess> ?dataset .
											filter( str(?ip) = \"$this->registered_ip\" or str(?ip) = \"0.0.0.0\") .
										}
										
										graph <".parent::$wsf_graph."datasets/>
										{
											?dataset a <http://rdfs.org/ns/void#Dataset> ;
											<http://purl.org/dc/terms/created> ?created.
									
											OPTIONAL{?dataset <http://purl.org/ontology/wsf#meta> ?meta.}
											OPTIONAL{?dataset <http://purl.org/dc/terms/title> ?title.}
											OPTIONAL{?dataset <http://purl.org/dc/terms/description> ?description.}
											OPTIONAL{?dataset <http://purl.org/dc/terms/modified> ?modified.}
											OPTIONAL{?dataset <http://purl.org/dc/terms/contributor> ?contributor.}
											OPTIONAL{?dataset <http://purl.org/dc/terms/creator> ?creator.}
										}		
									}";
		
				$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array("dataset", "title", "description", "creator", "created", "modified", "contributor", "meta"), FALSE));
										
				if (odbc_error())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #dataset-read-100");	
					return;
				}	
				else
				{
					$dataset = "";
					$title = "";
					$description = "";
					$creator = "";
					$created = "";
					$modified = "";
					$contributors = array();
					$meta = "";
					
					while(odbc_fetch_row($resultset))
					{
						$dataset2 = odbc_result($resultset, 1);
						
						if($dataset2 != $dataset && $dataset != "")
						{
							array_push($this->datasetsDescription, new DatasetDescription($dataset, $title, $description, $creator, $created, $modified, $contributors));															
							$contributors = array();
						}
						
						$dataset = $dataset2;
						
						$title = odbc_result($resultset, 2);
						$description = odbc_result($resultset, 3);
						$creator = odbc_result($resultset, 4);
						$created = odbc_result($resultset, 5);
						$modified = odbc_result($resultset, 6);
						array_push($contributors, odbc_result($resultset, 7));
						$meta = odbc_result($resultset, 8);
					}

					$metaDescription = array();

					// We have to add the meta information if available
					if($meta != "" && $this->addMeta == "true")
					{
						$query = "select ?p ?o (str(DATATYPE(?o))) as ?otype
										from <".parent::$wsf_graph."datasets/>
										where
										{
											<$meta> ?p ?o.
										}";							
						
						$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array('p', 'o', 'otype'), FALSE));
							
						$contributors = array();					
												
						if (odbc_error())
						{
							$this->conneg->setStatus(500);
							$this->conneg->setStatusMsg("Internal Error");
							$this->conneg->setStatusMsgExt("Error #dataset-read-105");	
							return;
						}	
						else
						{
							while(odbc_fetch_row($resultset))
							{								
								$predicate = odbc_result($resultset, 1);
								$object = odbc_result($resultset, 2);
								$otype = odbc_result($resultset, 3);
								 
								if(isset($metaDescription[$predicate]))
								{
									array_push($metaDescription[$predicate], $object);
								}
								else
								{
									$metaDescription[$predicate] = array($object);
									$metaDescription[$predicate]["type"] = $otype;
								}
							}
						}	
						
						unset($resultset);						
					}


					if($dataset != "")
					{
						array_push($this->datasetsDescription, new DatasetDescription($dataset, $title, $description, $creator, $created, $modified, $contributors, $meta, $metaDescription));															
					}
					
					unset($resultset);
				}			
			
/*			
				// Get all datasets this user has access to
				$query = "select ?dataset 
								from named <".parent::$wsf_graph."datasets/>
								from named <".parent::$wsf_graph.">
								where
								{
									graph <".parent::$wsf_graph."datasets/>
									{
										?dataset a <http://rdfs.org/ns/void#Dataset> .
									}
								
									graph <".parent::$wsf_graph.">
									{
										?access <http://purl.org/ontology/wsf#registeredIP> \"$this->registered_ip\" ;
													  <http://purl.org/ontology/wsf#read> \"True\" ;
													  <http://purl.org/ontology/wsf#datasetAccess> ?dataset .
									}
								}";								

				$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array("dataset"), FALSE));
										
				if (odbc_error())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #dataset-read-100");	
					return;
				}	
				else
				{
					while(odbc_fetch_row($resultset))
					{
						array_push($datasets, odbc_result($resultset, 1));
					}
					
					unset($resultset);
				}
*/

				
			}
			else
			{
				$dataset = $this->datasetUri;

				$query = "select ?title ?description ?creator ?created ?modified ?meta
								from named <".parent::$wsf_graph."datasets/>
								where
								{
									graph <".parent::$wsf_graph."datasets/>
									{
										<$dataset> a <http://rdfs.org/ns/void#Dataset> ;
										<http://purl.org/dc/terms/created> ?created.
										
										OPTIONAL{<$dataset> <http://purl.org/dc/terms/title> ?title.} .
										OPTIONAL{<$dataset> <http://purl.org/dc/terms/description> ?description.} .
										OPTIONAL{<$dataset> <http://purl.org/dc/terms/creator> ?creator.} .
										OPTIONAL{<$dataset> <http://purl.org/dc/terms/modified> ?modified.} .
										OPTIONAL{<$dataset> <http://purl.org/ontology/wsf#meta> ?meta.} .
									}
								}";				
										
				$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array('title', 'description', 'creator', 'created', 'modified', 'meta'), FALSE));
										
				if (odbc_error())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #dataset-read-101");	
					return;
				}		
				else
				{
					if(odbc_fetch_row($resultset))
					{
						$title = odbc_result($resultset, 1);
						$description = odbc_result($resultset, 2);
						$creator = odbc_result($resultset, 3);
						$created = odbc_result($resultset, 4);
						$modified = odbc_result($resultset, 5);
						$meta = odbc_result($resultset, 6);

						unset($resultset);
						
						$metaDescription = array();
						
						// We have to add the meta information if available
						if($meta != "" && $this->addMeta == "true")
						{
							$query = "select ?p ?o (str(DATATYPE(?o))) as ?otype
											from <".parent::$wsf_graph."datasets/>
											where
											{
												<$meta> ?p ?o.
											}";							
							
							$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array('p', 'o', 'otype'), FALSE));
												
							$contributors = array();					
													
							if (odbc_error())
							{
								$this->conneg->setStatus(500);
								$this->conneg->setStatusMsg("Internal Error");
								$this->conneg->setStatusMsgExt("Error #dataset-read-104");	
								return;
							}	
							else
							{
								while(odbc_fetch_row($resultset))
								{								
									$predicate = odbc_result($resultset, 1);
									$object = odbc_result($resultset, 2);
									$otype = odbc_result($resultset, 3);
									 
									if(isset($metaDescription[$predicate]))
									{
										array_push($metaDescription[$predicate], $object);
									}
									else
									{
										$metaDescription[$predicate] = array($object);
										$metaDescription[$predicate]["type"] = $otype;
									}
								}
							}	
							
							unset($resultset);						
						}

						
						// Get all contributors (users that have CUD perissions over the dataset)				
						$query = "select ?contributor 
										from <".parent::$wsf_graph."datasets/>
										where
										{
											<$dataset> a <http://rdfs.org/ns/void#Dataset> ;
											<http://purl.org/dc/terms/contributor> ?contributor.
										}";
										
				
						$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array('contributor'), FALSE));
											
						$contributors = array();					
												
						if (odbc_error())
						{
							$this->conneg->setStatus(500);
							$this->conneg->setStatusMsg("Internal Error");
							$this->conneg->setStatusMsgExt("Error #dataset-read-103");	
							return;
						}	
						elseif(odbc_fetch_row($resultset))
						{
							array_push($contributors, odbc_result($resultset, 1));
						}									
						
						array_push($this->datasetsDescription, new DatasetDescription($dataset, $title, $description, $creator, $created, $modified, $contributors, $meta, $metaDescription));
					}
				}
							
			}

			if(count($this->datasetsDescription) == 0 && $this->datasetUri != "all")
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("This dataset doesn't exist in this WSF");	
			}
		}
	}
}

/*!	 @brief Description of a dataset described in a WSF network
						
		\n
		
		@author Frederick Giasson, Structured Dynamics LLC.
	
		\n\n\n
*/

class DatasetDescription
{
	/*! @brief URI of the dataset  */
	public $uri = "";
	
	/*! @brief Title of the dataset */
	public $title = "";

	/*! @brief Description of the dataset */
	public $description = "";

	/*! @brief Creator of the dataset */
	public $creator = "";

	/*! @brief Creation date of the dataset */
	public $created = "";

	/*! @brief Last modification date of the dataset */
	public $modified = "";

	/*! @brief Contributors of the dataset */
	public $contributors = array();

	/*! @brief Meta resource URI about the dataset */
	public $meta = "";
	
	/*! @brief Meta description about the dataset */
	public $metaDescription = "";
	
	
	function __construct($uri, $title, $description, $creator, $created, $modified, $contributors, $meta="", $metaDescription=array())
	{
		$this->uri = $uri;
		$this->title = $title;
		$this->description = $description;
		$this->creator = $creator;
		$this->created = $created;
		$this->modified = $modified;
		$this->contributors = $contributors;
		$this->meta = $meta;
		$this->metaDescription = $metaDescription;
	}	
}

	//@} 


?>