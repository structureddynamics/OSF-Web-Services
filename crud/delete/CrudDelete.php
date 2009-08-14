<?php

	/*! @defgroup WsCrud Crud Web Service */
	//@{ 

	/*! @file \ws\crud\delete\CrudDelete.php
		 @brief Define the Crud Delete web service

		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief CRUD Delete web service. It removes record instances within dataset indexes on different systems (Virtuoso, Solr, etc).
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class CrudDelete extends WebService
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
	
	/*! @brief Dataset where to index the resource*/
	private $dataset;
	
	/*! @brief URI of the resource to delete */
	private $resourceUri;
	
	/*! @brief Requester's IP used for request validation */
	private $requester_ip = "";

	/*!	 @brief Constructor
			 @details 	Initialize the Crud Delete
			
			@param[in] $uri URI of the instance record to delete
			@param[in] $dataset URI of the dataset where the instance record is indexed
			@param[in] $registered_ip Target IP address registered in the WSF
			@param[in] $requester_ip IP address of the requester
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($uri, $dataset, $registered_ip, $requester_ip)
	{
		parent::__construct();		
		
		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->registered_ip = $registered_ip;
		$this->requester_ip = $requester_ip;
		$this->dataset = $dataset;
		$this->resourceUri = $uri;
		
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
		
		$this->uri = parent::$wsf_base_url."/wsf/ws/crud/delete/";	
		$this->title = "Crud Delete Web Service";	
		$this->crud_usage = new CrudUsage(FALSE, FALSE, FALSE, TRUE);
		$this->endpoint = parent::$wsf_base_url."/ws/crud/delete/";			
		
		$this->dtdURL = "auth/CrudDelete.dtd";
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
		$xmlDoc = substr($xmlDoc, 0, $posHeader)."\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Crud Delete DTD 0.1//EN\" \"".parent::$dtdBaseURL.$this->dtdURL."\">".substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);	
		
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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, CrudDelete::$supportedSerializations);

		// Check for errors
		
		if($this->uri == "")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("No resource URI to delete specified");
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
		
		$ws_dr = new DatasetRead($this->dataset, "false", "self", parent::$wsf_local_ip);	// Here the one that makes the request is the WSF (internal request).
		
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
	

	/*!	 @brief Delete an instance record from all systems that are indexing it 9usually Virtuoso and Solr)
							
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
				// Delete all triples for this URI in that dataset
				$query = "delete from <".$this->dataset.">
								{ 
									<".$this->resourceUri."> ?p ?o. 
								}
								where
								{
									<".$this->resourceUri."> ?p ?o. 
								}";
	
				@$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array(), FALSE));
										
				if (odbc_error())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #crud-delete-100");	
					return;
				}		
				
				// Delete the Solr document in the Solr index
				$solr = new Solr(parent::$wsf_solr_core);
				
				if(!$solr->deleteInstanceRecord($this->resourceUri, $this->dataset))
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #crud-delete-101");	
					return;
				}
				
				if(parent::$solr_auto_commit === FALSE)
				{
					if(!$solr->commit())
					{
						$this->conneg->setStatus(500);
						$this->conneg->setStatusMsg("Internal Error");
						$this->conneg->setStatusMsgExt("Error #crud-create-105");
						return;					
					}
				}
	
			}
		}
	}
}

	//@} 


?>