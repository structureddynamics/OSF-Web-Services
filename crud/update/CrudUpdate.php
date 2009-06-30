<?php

include_once("../../framework/ClassHierarchy.php");


	/*! @defgroup WsAuth Authentication / Registration Web Service */
	//@{ 

	/*! @file \ws\crud\update\CrudUpdate.php
		 @brief Define the Crud Update web service
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief CRUD Update web service. It updates instance records descriptions from dataset indexes on different systems (Virtuoso, Solr, etc).
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class CrudUpdate extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;
	
	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");

	/*! @brief IP being registered */
	private $registered_ip = "";
	
	/*! @brief Dataset where to index the resource*/
	private $dataset;
	
	/*! @brief RDF document where resource(s) to be added are described. Maximum size (by default) is 8M (default php.ini setting). */
	private $document = array();
	
	/*! @brief Mime of the RDF document serialization */
	private $mime = "";

	/*! @brief Requester's IP used for request validation */
	private $requester_ip = "";

		
	/*!	 @brief Constructor
			 @details 	Initialize the Crud Update
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($document, $mime, $dataset, $registered_ip, $requester_ip)
	{
		parent::__construct();		

		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->registered_ip = $registered_ip;
		$this->requester_ip = $requester_ip;
		$this->dataset = $dataset;
		$this->document = $document;
		$this->mime = $mime;
		
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
		
		$this->uri = parent::$wsf_base_url."/wsf/ws/crud/update/";	
		$this->title = "Crud Update Web Service";	
		$this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
		$this->endpoint = parent::$wsf_base_url."/ws/crud/update/";			
		
		$this->dtdURL = "auth/CrudUpdate.dtd";
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
		$xmlDoc = substr($xmlDoc, 0, $posHeader)."\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Crud Update DTD 0.1//EN\" \"".parent::$dtdBaseURL.$this->dtdURL."\">".substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);	
		
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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudUpdate::$supportedSerializations);

		// Check for errors
		
		if($this->document == "")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("No RDF document to index");
			return;
		}

		if($this->mime != "application/rdf+xml" && $this->mime != "application/rdf+n3")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("Unknown MIME type for this RDF document");
			return;
		}

		if($this->dataset == "")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("No dataset specified");
			return;
		}
		
		// Check if the dataset is created
		
		$ws_dr = new DatasetRead($this->dataset, "self", parent::$wsf_local_ip);	// Here the one that makes the request is the WSF (internal request).
//		$ws_dr = new DatasetRead($this->dataset, $this->registered_ip, $this->requester_ip);
		
		$ws_dr->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
		
		$ws_dr->process();
		
		if($ws_dr->pipeline_getResponseHeaderStatus() != 200)
		{
			$this->conneg->setStatus($ws_dr->pipeline_getResponseHeaderStatus());
			$this->conneg->setStatusMsg($ws_dr->pipeline_getResponseHeaderStatusMsg());
			$this->conneg->setStatusMsgExt($ws_dr->pipeline_getResponseHeaderStatusMsgExt());
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
	

	/*!	 @brief Update the information of a given instance record
							
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
				// Step #1: indexing the incomming rdf document in its own temporary graph
				$tempGraphUri = "temp-graph-".md5($this->document);
				
				if($this->mime == "application/rdf+xml")
				{
					@$this->db->query("DB.DBA.RDF_LOAD_RDFXML_MT('".str_replace("'", "\'", $this->document)."', '$tempGraphUri', '$tempGraphUri', 0)");	
				}
				
				if($this->mime == "application/rdf+n3")
				{
					@$this->db->query("DB.DBA.TTLP_MT('".str_replace("'", "\'", $this->document)."', '$tempGraphUri', '$tempGraphUri', 0, 0)");		
				}
				
						
				if(odbc_error())
				{
					$this->conneg->setStatus(400);
					$this->conneg->setStatusMsg("Bad Request");
					$this->conneg->setStatusMsgExt("Error #crud-update-100. Syntax error in the RDF document: ".odbc_errormsg());
					return;
				}
				
				// Step #2: use that temp graph to modify (delete/insert using SPARUL) the target graph of the update query
				$query = "delete from <".$this->dataset.">
								{ 
									?s ?p_original ?o_original.
								}
								where
								{
									graph <".$tempGraphUri.">
									{
										?s ?p ?o.
									}
									
									graph <".$this->dataset.">
									{
										?s ?p_original ?o_original.
									}
								}
								
								insert into <".$this->dataset.">
								{
									?s ?p ?o.
								}									
								where
								{
									graph <".$tempGraphUri.">
									{
										?s ?p ?o.
									}
								}";
	
				@$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array(), FALSE));
										
				if (odbc_error())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #crud-update-101");	
					return;
				}			
				
				
				// Get the list of resources to use to create Solr documents
				$tempGraphUri = "temp-graph-".md5($this->document);

				if($this->mime == "application/rdf+xml")
				{
					@$this->db->query("DB.DBA.RDF_LOAD_RDFXML_MT('".str_replace("'", "\'", $this->document)."', '$tempGraphUri', '$tempGraphUri', 0)");	
				}
				
				if($this->mime == "application/rdf+n3")
				{
					@$this->db->query("DB.DBA.TTLP_MT('".str_replace("'", "\'", $this->document)."', '$tempGraphUri', '$tempGraphUri', 0, 0)");		
				}

				$query = "select distinct ?s 					
								from <$tempGraphUri> 
								where 
								{
									?s ?p ?o.
								}";
	
				$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array("s"), FALSE));
										
				if (odbc_error())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #crud-create-101");	
					return;
				}					
				
				$subjects = array();
				
				while(odbc_fetch_row($resultset))
				{
					array_push($subjects, odbc_result($resultset, 1));
				}
				
				unset($resultset);				
				
				
				// Step #3: Remove the temp graph
				$query = "clear graph <".$tempGraphUri.">";
	
				@$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array(), FALSE));
										
				if (odbc_error())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #crud-update-102 (".str_replace(array("\n", "\r", "\t"), "", $query).")");	
					return;
				}					
						
				// Step #4: Update Solr index
				
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
				
/*!
				@todo Fixing this to use the DB.
				
				// This method is currently not working. The problem is that we ahve an issue in CrudCreate and Virtuoso's
				// LONG VARCHAR column. It appears that there is a bug somewhere in the "php -> odbc -> virtuoso" path.
				// If we are not requesting to return the LONG VARCHAR column, everything works fine.
*/ 		
/*		
 				$resultset = $this->db->query("select * from SD.WSF.ws_ontologies where struct_type = 'class'");
				
				odbc_binmode($resultset, ODBC_BINMODE_PASSTHRU);
				odbc_longreadlen($resultset, 16384); 			
				
				odbc_fetch_row($resultset);
				$classHierarchy = unserialize(odbc_result($resultset, "struct"));
				
				if (odbc_error())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #crud-create-103");	
					return;
				}					
*/

				$filename = parent::$wsf_base_path."/framework/ontologies/classHierarchySerialized.srz";
				$file = fopen($filename, "r");
				$classHierarchy = fread($file, filesize($filename));
				$classHierarchy = unserialize($classHierarchy);
				fclose($file);
				
				// Index in Solr
				
				$solr = new Solr(parent::$wsf_solr_core);
				
				foreach($subjects as $subject)
				{
					$add = "<add><doc><field name=\"uid\">".md5($this->dataset.$subject)."</field>";
					$add .= "<field name=\"uri\">$subject</field>";
					$add .= "<field name=\"dataset\">".$this->dataset."</field>";
					
					// Get types for this subject.
					$query = $this->db->build_sparql_query("select ?type from <".$this->dataset."> where {<$subject> a ?type.}", array ('type'), FALSE);
					
					$resultset = $this->db->query($query);
					
					$types = array();
					
					while(odbc_fetch_row($resultset))
					{
						$type = odbc_result($resultset, 1);
						
						array_push($types, $type);
						
						$add .= "<field name=\"type\">$type</field>";
					}
					
					unset($resultset);
					
				
					// Get properties with the type of the object
					$query = $this->db->build_sparql_query("select ?p ?o (str(DATATYPE(?o))) as ?otype from <".$this->dataset."> where {<$subject> ?p ?o.}", array ('p', 'o', 'otype'), FALSE);
					
					$resultset2 = $this->db->query($query);
					
					while(odbc_fetch_row($resultset2))
					{
						$property = odbc_result($resultset2, 1);
						$object = odbc_result($resultset2, 2);
						$otype = odbc_result($resultset2, 3);
						
						if($otype == "http://www.w3.org/2001/XMLSchema#string")
						{					  
							$add .= "<field name=\"property\">".$this->xmlEncode($property)."</field>";
							$add .= "<field name=\"text\">".$this->xmlEncode($object)."</field>";
						}
						elseif($otype == "" && strpos($property, "http://www.w3.org/1999/02/22-rdf-syntax-ns#") === FALSE && strpos($property, "http://www.w3.org/2000/01/rdf-schema#") === FALSE && strpos($property, "http://www.w3.org/2002/07/owl#") === FALSE)
						{
							$add .= "<field name=\"object_property\">".$this->xmlEncode($property)."</field>";
							
							$query = $db->build_sparql_query("select ?p ?o from <".$this->dataset."> where {<$object> ?p ?o.}", array ('p', 'o'), FALSE);
					
							$resultset3 = $db->query($query);
							
							$subjectTriples = array();
							
							while(odbc_fetch_row($resultset3))
							{
								$p = odbc_result($resultset3, 1);
								$o = odbc_result($resultset3, 2);
								
								if(!isset($subjectTriples[$p]))
								{
									$subjectTriples[$p] = array();
								}
								
								array_push($subjectTriples[$p], $o);
							}
							
							unset($resultset3);
			
							$labels = "";
							foreach($labelProperties as $property)
							{
								if(isset($subjectTriples[$property]))
								{
									$labels = $subjectTriples[$property][0]." ";
								}
							}
							
							if($labels != "")
							{
								$add .= "<field name=\"object_label\">".$this->xmlEncode($labels)."</field>";
							}
							else
							{
								$add .= "<field name=\"object_label\">-</field>";
							}
						}
					}
					
					unset($resultset2);
			
/*					
					// Get all types by inference
					foreach($types as $type)
					{
						$superClasses = $classHierarchy->getSuperClasses($type);
						
						foreach($superClasses as $sc)
						{
							$add .= "<field name=\"inferred_type\">".$this->xmlEncode($sc->name)."</field>";
						}
					}
*/				
					$add .= "</doc></add>";
					
					if(!$solr->update($add))
					{
						$this->conneg->setStatus(500);
						$this->conneg->setStatusMsg("Internal Error");
						$this->conneg->setStatusMsgExt("Error #crud-create-103");	
						return;					
					}
				}
				
				if(!$solr->commit())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #crud-create-104");
					return;					
				}
/*				
				if(!$solr->optimize())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #crud-create-105");
					return;					
				}
*/				
			}
		}
	}
}

	//@} 


?>