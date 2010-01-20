<?php

	/*! @defgroup WsDataset Dataset Management Web Service  */
	//@{ 

	/*! @file \ws\dataset\create\DatasetCreate.php
		 @brief Create a new graph for this dataset & indexation of its description
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief Dataset Create Web Service. It creates a new graph for this dataset & indexation of its description
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class DatasetCreate extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;
	
	/*! @brief IP of the requester */
	private $requester_ip = "";

	/*! @brief Unique ID for the dataset */
	private $datasetUri = "";
	
	/*! @brief Title of the dataset */
	private $datasetTitle = "";
	
	/*! @brief Description of the dataset */
	private $description = "";
	
	/*! @brief URI of the creator of the dataset */
	private $creator = "";
	
	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");
		
	/*! @brief Error messages of this web service */
	private $errorMessenger = '{
												"ws": "/ws/dataset/create/",
												"_200": {
													"id": "WS-DATASET-UPDATE-200",
													"level": "Warning",
													"name": "No unique identifier specified for this dataset",
													"description": "No URI defined for this new dataset"
												},
												"_201": {
													"id": "WS-DATASET-UPDATE-201",
													"level": "Fatal",
													"name": "Can\'t check if the dataset is already existing",
													"description": "An error occured when we tried to check if the dataset was already existing in the system"
												},
												"_202": {
													"id": "WS-DATASET-UPDATE-202",
													"level": "Warning",
													"name": "Dataset already existing",
													"description": "This dataset is already existing in this web services framework"
												},
												"_300": {
													"id": "WS-DATASET-UPDATE-300",
													"level": "Fatal",
													"name": "Can\'t create the dataset",
													"description": "An error occured when we tried to register the new dataset to the web service framework"
												}	
											}';	
		
		
	/*!	 @brief Constructor
			 @details 	Initialize the Auth Web Service
					
			@param[in] $uri URI to refer to this new dataset
			@param[in] $datasetTitle Title of the dataset to create
			@param[in] $description Description of the dataset to create
			@param[in] $creator Unique identifier used to refer to the creator of this dataset
			@param[in] $requester_ip IP address of the requester
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($uri, $datasetTitle="", $description="", $creator="", $requester_ip)
	{
		parent::__construct();		

		$this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);
		
		
		$this->datasetUri = $uri;
		$this->datasetTitle = $datasetTitle;
		$this->description = $description;
		$this->creator = $creator;
		$this->requester_ip = $requester_ip;

		$this->uri = $this->wsf_base_url."/wsf/ws/dataset/create/";	
		$this->title = "Dataset Create Web Service";	
		$this->crud_usage = new CrudUsage(TRUE, FALSE, FALSE, FALSE);
		$this->endpoint = $this->wsf_base_url."/ws/dataset/create/";			
		
		$this->dtdURL = "dataset/datasetCreate.dtd";
		
		$this->errorMessenger = json_decode($this->errorMessenger);		
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
		$ws_av = new AuthValidator($this->requester_ip, $this->wsf_graph."datasets/", $this->uri);
		
		$ws_av->pipeline_conneg($this->conneg->getAccept(), $this->conneg->getAcceptCharset(), $this->conneg->getAcceptEncoding(), $this->conneg->getAcceptLanguage());
		
		$ws_av->process();
		
		if($ws_av->pipeline_getResponseHeaderStatus() != 200)
		{
			$this->conneg->setStatus($ws_av->pipeline_getResponseHeaderStatus());
			$this->conneg->setStatusMsg($ws_av->pipeline_getResponseHeaderStatusMsg());
			$this->conneg->setStatusMsgExt($ws_av->pipeline_getResponseHeaderStatusMsgExt());
			$this->conneg->setError($ws_av->pipeline_getError()->id, 
												$ws_av->pipeline_getError()->webservice, 
												$ws_av->pipeline_getError()->name, 
												$ws_av->pipeline_getError()->description, 
												$ws_av->pipeline_getError()->debugInfo,
												$ws_av->pipeline_getError()->level);

												
			return;												
		}
	}
	
	/*!	 @brief Returns the error structure
							
			\n
			
			@return returns the error structure
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function pipeline_getError()
	{
		return($this->conneg->error);
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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, DatasetCreate::$supportedSerializations);

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
				$this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
				$this->conneg->setError($this->errorMessenger->_200->id, 
													$this->errorMessenger->ws, 
													$this->errorMessenger->_200->name, 
													$this->errorMessenger->_200->description, 
													"",
													$this->errorMessenger->_200->level);					
				
				return;
			}
		
			// Check if the dataset is already existing
			$query .= "	select ?dataset 
								from <".$this->wsf_graph."datasets/>
								where
								{
									<".$this->datasetUri."> a ?dataset .
								}";

			$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array("dataset"), FALSE));
								
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
				$this->conneg->setError($this->errorMessenger->_201->id, 
													$this->errorMessenger->ws, 
													$this->errorMessenger->_201->name, 
													$this->errorMessenger->_201->description, 
													odbc_errormsg(),
													$this->errorMessenger->_201->level);					
				
				return;			
			}
			elseif(odbc_fetch_row($resultset) !== FALSE)
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
				$this->conneg->setError($this->errorMessenger->_202->id, 
													$this->errorMessenger->ws, 
													$this->errorMessenger->_202->name, 
													$this->errorMessenger->_202->description, 
													"",
													$this->errorMessenger->_202->level);					
				
				unset($resultset);
				return;
			}
						
			unset($resultset);			
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
	

	/*!	 @brief Create a new dataset within the WSF
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function process()
	{
		// Make sure there was no conneg error prior to this process call
		if($this->conneg->getStatus() == 200)
		{
			$query = "insert into <".$this->wsf_graph."datasets/>
							{
								<".$this->datasetUri."> a <http://rdfs.org/ns/void#Dataset> ;
								".($this->datasetTitle != "" ? "<http://purl.org/dc/terms/title> \"\"\"".str_replace("'", "\'", $this->datasetTitle)."\"\"\" ; " : "")."
								".($this->description != "" ? "<http://purl.org/dc/terms/description> \"\"\"".str_replace("'", "\'", $this->description)."\"\"\" ; " : "")."
								".($this->creator != "" ? "<http://purl.org/dc/terms/creator> <$this->creator> ; " : "")."
								<http://purl.org/dc/terms/created> \"".date("Y-m-j")."\" .
							}";

			@$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), " ", $query), array(), FALSE));
									
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
				$this->conneg->setError($this->errorMessenger->_300->id, 
													$this->errorMessenger->ws, 
													$this->errorMessenger->_300->name, 
													$this->errorMessenger->_300->description, 
													odbc_errormsg(),
													$this->errorMessenger->_300->level);					
				
				return;
			}				
		}
	}
}

	//@} 


?>