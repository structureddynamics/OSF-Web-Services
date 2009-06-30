<?php

	/*! @defgroup WsDataset Dataset Management Web Service  */
	//@{ 

	/*! @file \ws\dataset\delete\DatasetDelete.php
		 @brief Delete a new graph for this dataset & indexation of its description
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief Dataset Delete Web Service. It deletes an existing graph of the structWSF instance
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class DatasetDelete extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;
	
	/*! @brief Requested IP */
	private $registered_ip = "";

	/*! @brief URI of the dataset to delete */
	private $datasetUri = "";
	
	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");
		
	/*!	 @brief Constructor
			 @details 	Initialize the Auth Web Service
					
			@param[in] $uri URI of the dataset to delete
			@param[in] $registered_ip Target IP address registered in the WSF
			@param[in] $requester_ip IP address of the requester
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($uri, $registered_ip, $requester_ip)
	{
		parent::__construct();		
		
		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->datasetUri = $uri;
		
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
		}			

		$this->uri = parent::$wsf_base_url."/wsf/ws/dataset/delete/";	
		$this->title = "Dataset Delete Web Service";	
		$this->crud_usage = new CrudUsage(FALSE, FALSE, FALSE, TRUE);
		$this->endpoint = parent::$wsf_base_url."/ws/dataset/delete/";			
		
		$this->dtdURL = "dataset/datasetDelete.dtd";
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
		$ws_av = new AuthValidator($this->registered_ip, parent::$wsf_graph."datasets/", $this->uri);
		
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
	public function injectDoctype($xmlDoc){ return ""; }

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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, DatasetDelete::$supportedSerializations);

		// Validate query
		$this->validateQuery();
		
		// If the query is still valid
		if($this->conneg->getStatus() == 200)
		{
			// Check for errors
			if($this->datasetUri == "")
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("No dataset URI specified");
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
	

	/*!	 @brief Delete a dataset from the WSF
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function process()
	{
		// Make sure there was no conneg error prior to this process call
		if($this->conneg->getStatus() == 200)
		{
			// Remove the Graph description in the ".../datasets/" graph.
			$query = "delete from <".parent::$wsf_graph."datasets/> 
							{ 
								<$this->datasetUri> ?p ?o.
							}
							where
							{
								graph <".parent::$wsf_graph."datasets/>
								{
									<$this->datasetUri> ?p ?o.
								}
							}";

			@$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array(), FALSE));
									
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-delete-100");
				return;
			}			
			
			// Removing all accesses for this graph		
			$ws_ara = new AuthRegistrarAccess("", "", $this->datasetUri, "delete_all", "", "", $this->registered_ip);

			$ws_ara->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
			
			$ws_ara->process();
			
			if($ws_ara->pipeline_getResponseHeaderStatus() != 200)
			{
				$this->conneg->setStatus($ws_ara->pipeline_getResponseHeaderStatus());
				$this->conneg->setStatusMsg($ws_ara->pipeline_getResponseHeaderStatusMsg());
				$this->conneg->setStatusMsgExt($ws_ara->pipeline_getResponseHeaderStatusMsgExt());
				return;
			}		
			
			// Drop the entire graph
			$query = "exst('select * from (sparql clear graph <".$this->datasetUri.">) sub')";

			@$this->db->query($query);
									
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-delete-101");	
				return;
			}		
			
			// Remove all documents form the solr index for this Dataset
			$solr = new Solr(parent::$wsf_solr_core);			
			
			if(!$solr->flushDataset($this->datasetUri))
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-delete-102");	
				return;					
			}
			
			if(!$solr->commit())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-delete-103");	
				return;					
			}
/*			
			if(!$solr->optimize())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-delete-104");	
				return;					
			}			
*/			
		}
	}
}

	//@} 


?>