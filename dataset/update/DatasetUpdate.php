<?php

	/*! @defgroup WsDataset Dataset Management Web Service  */
	//@{ 

	/*! @file \ws\dataset\update\DatasetUpdate.php
		 @brief Update a new graph for this dataset & indexation of its description
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief Dataset Update Web Service. It updates description of dataset of the structWSF instance.
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class DatasetUpdate extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;
	
	/*! @brief IP of the requester */
	private $requester_ip = "";

	/*! @brief URI of the dataset to update */
	private $datasetUri = "";
	
	/*! @brief Title of the dataset */
	private $datasetTitle = "";
	
	/*! @brief Description of the dataset */
	private $description = "";

	/*! @brief List of contributors to the dataset */
	private $contributors = "";	

	/*! @brief Last modification date of the dataset */
	private $modified = "";	
	
	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");
		
	/*!	 @brief Constructor
			 @details 	Initialize the Auth Web Service
				
			@param[in] $uri Unique identifier used to refer to the dataset to update
			@param[in] $title (optional).  Title of the dataset to update
			@param[in] $description (optional).Description of the dataset to update
			@param[in] $contributors (optional).List of contributor URIs seperated by ";"
			@param[in] $modified (optional).Date of the modification of the dataset
			@param[in] $requester_ip IP address of the requester
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($uri, $title, $description, $contributors, $modified, $requester_ip)
	{
		parent::__construct();		
		
		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->datasetUri = $uri;
		$this->datasetTitle = $title;
		$this->description = $description;
		$this->contributors = $contributors;
		$this->modified = $modified;
		$this->requester_ip = $requester_ip;

		$this->uri = parent::$wsf_base_url."/wsf/ws/dataset/update/";	
		$this->title = "Dataset Update Web Service";	
		$this->crud_usage = new CrudUsage(FALSE, FALSE, TRUE, FALSE);
		$this->endpoint = parent::$wsf_base_url."/ws/dataset/update/";			
		
		$this->dtdURL = "dataset/datasetUpdate.dtd";
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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, DatasetUpdate::$supportedSerializations);

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
			
			// Check if the dataset is existing
			$query .= "	select ?dataset 
								from <".parent::$wsf_graph."datasets/>
								where
								{
									<$this->datasetUri> a ?dataset .
								}";

			$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array("dataset"), FALSE));
								
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-update-100");		
				return;			
			}
			elseif(odbc_fetch_row($resultset) === FALSE)
			{
				$this->conneg->setStatus(400);
				$this->conneg->setStatusMsg("Bad Request");
				$this->conneg->setStatusMsgExt("This dataset doesn't exist in this WSF");
				
				unset($resultset);
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
	

	/*!	 @brief Update information about a dataset of the WSF
							
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
			$query = "modify <".parent::$wsf_graph."datasets/>
							delete
							{ 
								".($this->datasetTitle != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle ." : "")."
								".($this->description != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/description> ?description ." : "")."
								".($this->modified != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/modified> ?modified ." : "")."
								".(count($this->contributors) > 0 && isset($contributor[0]) ? "<$this->datasetUri> <http://purl.org/dc/terms/contributor> ?contributors ." : "")."
							}
							insert
							{
								".($this->datasetTitle != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/title> \"\"\"$this->datasetTitle\"\"\" ." : "")."
								".($this->description != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/description> \"\"\"$this->description\"\"\" ." : "")."
								".($this->modified != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/modified> \"\"\"$this->modified\"\"\" ." : "")."";
								
			foreach($this->contributors as $contributor)
			{
				$query .=   ($this->contributor != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/contributor> <$contributor> ." : "");
			}								
								
			$query .= "}									
							where
							{
								graph <".parent::$wsf_graph."datasets/>
								{
									<$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
									".($this->datasetTitle != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle ." : "")."
									".($this->description != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/description> ?description ." : "")."
									".($this->modified != "" ? "<$this->datasetUri> <http://purl.org/dc/terms/modified> ?modified ." : "")."
									".(count($this->contributors) > 0 ? "<$this->datasetUri> <http://purl.org/dc/terms/contributor> ?contributors ." : "")."
								}
							}";
*/							

			// Note: here we can't create a single SPARUL query to update everything because if one of the clause is not existing in the "delete" pattern, 
			//          then nothing will be updated. Also, the problem come from the fact that "OPTIONAL" clauses only happen at the level of the "where" clause
			//          and can't be used in the "delete" clause.
							
			// Updating the title if it exists in the description		
			if($this->datasetTitle != "")
			{

				$query = "delete from <".parent::$wsf_graph."datasets/>
								{ 
									<$this->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle .
								}
								where
								{
									graph <".parent::$wsf_graph."datasets/>
									{
										<$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
										<$this->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle .
									}
								}
								".(	$this->datasetTitle != "-delete-" ? "
								insert into <".parent::$wsf_graph."datasets/>
								{
									<$this->datasetUri> <http://purl.org/dc/terms/title> \"\"\"".str_replace("'", "\'", $this->datasetTitle)."\"\"\" .
								}" : "");							
			}
							
			@$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array(), FALSE));
									
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-update-101");	
				return;
			}					
			
			// Updating the description if it exists in the description		
			if($this->description != "")
			{
				$query = "delete from <".parent::$wsf_graph."datasets/>
								{ 
									<$this->datasetUri> <http://purl.org/dc/terms/description> ?description .
								}
								where
								{
									graph <".parent::$wsf_graph."datasets/>
									{
										<$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
										<$this->datasetUri> <http://purl.org/dc/terms/description> ?description .
									}
								}
								".(	$this->description != "-delete-" ? "
								insert into <".parent::$wsf_graph."datasets/>
								{
									<$this->datasetUri> <http://purl.org/dc/terms/description> \"\"\"".str_replace("'", "\'", $this->description)."\"\"\" .
								}" : "");							
			}
							
			@$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array(), FALSE));
									
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-update-102");	
				return;
			}					
							
			// Updating the modification date if it exists in the description		
			if($this->modified != "")
			{
				$query = "delete from <".parent::$wsf_graph."datasets/>
								{ 
									<$this->datasetUri> <http://purl.org/dc/terms/modified> ?modified .
								}
								where
								{
									graph <".parent::$wsf_graph."datasets/>
									{
										<$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
										<$this->datasetUri> <http://purl.org/dc/terms/modified> ?modified .
									}
								}
								".(	$this->modified != "-delete-" ? "
								insert into <".parent::$wsf_graph."datasets/>
								{
									<$this->datasetUri> <http://purl.org/dc/terms/modified> \"\"\"".str_replace("'", "\'", $this->modified)."\"\"\" .
								}" : "");							
			}
							
			@$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array(), FALSE));
									
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-update-103");	
				return;
			}					
			
			// Updating the contributors list if it exists in the description		
			if($this->contributors != "")
			{
				$query = "delete from <".parent::$wsf_graph."datasets/>
								{ 
									<$this->datasetUri> <http://purl.org/dc/terms/contributor> ?contributor .
								}
								where
								{
									graph <".parent::$wsf_graph."datasets/>
									{
										<$this->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
										<$this->datasetUri> <http://purl.org/dc/terms/contributor> ?contributor .
									}
								}";
				if($this->contributors != "-delete-")
				{
					$cons = array();
					
					if(strpos($this->contributors, ";") !== FALSE)
					{
						$cons = explode(";", $this->contributors);
					}
					
					$query .= "insert into <".parent::$wsf_graph."datasets/>
									{";

					foreach($cons as $contributor)
					{
						$query .=   "<$this->datasetUri> <http://purl.org/dc/terms/contributor> <$contributor> .";
					}				
					
					if(count($cons) == 0)
					{
						$query .=   "<$this->datasetUri> <http://purl.org/dc/terms/contributor> <$this->contributors> .";
					}
					$query .=	"}";	
				}
			}
			
			

							
			@$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array(), FALSE));
									
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #dataset-update-104");	
				return;
			}					
							
							
		}
	}
}

	//@} 


?>