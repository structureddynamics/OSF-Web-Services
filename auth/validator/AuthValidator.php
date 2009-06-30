<?php

	/*! @ingroup WsAuth Authentication / Registration Web Service */
	//@{ 

	/*! @file \ws\auth\validator\AuthValidator.php
		 @brief Define the Authentication / Registration web service
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	
	
	/*!	 @brief Auth Validator Web Service. It validates queries to a web service of the web service framework linked to this authentication web service.
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class AuthValidator extends WebService
{
	/*! @brief Database connection */
	private $db;

	/*! @brief Conneg object that manage the content negotiation capabilities of the web service */
	private $conneg;
	
	/*! @brief URL where the DTD of the XML document can be located on the Web */
	private $dtdURL;
	
	/*! @brief Error message to report */
	private $errorMessages = "";
	
	/*! @brief IP of the requester */
	private $requester_ip = "";
	
	/*! @brief Datasets requested by the requester */
	private $requested_datasets = "";
	
	/*! @brief Web service URI where the request has been made, and that is registered on this web service */
	private $requested_ws_uri = "";
	
	/*! @brief The validation answer of the query */
	private $valid = "False";	

	/*! @brief Supported serialization mime types by this Web service */
	public static $supportedSerializations = array("application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");
		
	/*!	 @brief Constructor
			 @details Initialize the Auth Web Service
				
			@param[in] $requester_ip IP address of the requester
			@param[in] $requested_datasets Target dataset targeted by the query of the user
			@param[in] $requested_ws_uri Target web service endpoint accessing the target dataset
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($requester_ip, $requested_datasets, $requested_ws_uri)
	{
		parent::__construct();		
		
		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->requester_ip = $requester_ip;
		$this->requested_datasets = $requested_datasets;
		$this->requested_ws_uri = $requested_ws_uri;
		
		$this->uri = parent::$wsf_base_url."/wsf/ws/auth/validator/";	
		$this->title = "Authentication Validator Web Service";	
		$this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
		$this->endpoint = parent::$wsf_base_url."/ws/auth/validator/";			
		
		$this->dtdURL = "auth/authValidator.dtd";
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
	protected function validateQuery(){ return TRUE; }
	
	
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
		$xmlDoc = substr($xmlDoc, 0, $posHeader)."\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Auth Validator DTD 0.1//EN\" \"".parent::$dtdBaseURL.$this->dtdURL."\">".substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);	
		
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
		$this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language, AuthValidator::$supportedSerializations);

		// Check for errors
		if($this->requester_ip == "")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("No requester IP available");
			return;
		}

		if($this->requested_datasets == "")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("No target dataset");
			return;
		}

		if($this->requested_ws_uri == "")
		{
			$this->conneg->setStatus(400);
			$this->conneg->setStatusMsg("Bad Request");
			$this->conneg->setStatusMsgExt("No web service URI available");
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
	

	/*!	 @brief Validate the request
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function process()
	{
		// Make sure there was no conneg error prior to this process call
		if($this->conneg->getStatus() == 200)
		{
			// Get the CRUD usage of the target web service
			$resultset = $this->db->query($this->db->build_sparql_query("select ?_wsf ?_create ?_read ?_update ?_delete from <".
														parent::$wsf_graph."> where {?_wsf a <http://purl.org/ontology/wsf#WebServiceFramework>.".
														" ?_wsf <http://purl.org/ontology/wsf#hasWebService> <$this->requested_ws_uri>. ".
														"<$this->requested_ws_uri> <http://purl.org/ontology/wsf#hasCrudUsage> ?crudUsage. ".
														"?crudUsage <http://purl.org/ontology/wsf#create> ?_create; <http://purl.org/ontology/wsf#read> ".
														"?_read; <http://purl.org/ontology/wsf#update> ?_update; <http://purl.org/ontology/wsf#delete> ".
														"?_delete. }", array ('_wsf', '_create', '_read', '_update', '_delete'), FALSE));
			
			if (odbc_error())
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Error #auth-validator-100");				
				return;	
			}			
			elseif(odbc_fetch_row($resultset))
			{
				$wsf = odbc_result($resultset, 1);
				$ws_create = odbc_result($resultset, 2);
				$ws_read = odbc_result($resultset, 3);
				$ws_update = odbc_result($resultset, 4);
				$ws_delete = odbc_result($resultset, 5);
			}
						
			unset($resultset);
	
			// Check if the web service is registered
			if($wsf == "")
			{
				$this->conneg->setStatus(500);
				$this->conneg->setStatusMsg("Internal Error");
				$this->conneg->setStatusMsgExt("Target web service ($this->requested_ws_uri) not registered to this Web Services Framework");	
				return;
			}		
			
			// Check the list of datasets
			$datasets = explode(";", $this->requested_datasets);
			
			foreach($datasets as $dataset)
			{
				// Decode potentially encoded ";" character.
				$dataset = str_ireplace("%3B", ";", $dataset);
				
				$query = "select ?_access ?_create ?_read ?_update ?_delete 
								from <".parent::$wsf_graph."> 
								where 
								{ 
								    {
										?_access <http://purl.org/ontology/wsf#webServiceAccess> <$this->requested_ws_uri>; 
										<http://purl.org/ontology/wsf#datasetAccess> <$dataset>; 
										<http://purl.org/ontology/wsf#registeredIP> ?ip; 
										<http://purl.org/ontology/wsf#create> ?_create; 
										<http://purl.org/ontology/wsf#read> ?_read; 
										<http://purl.org/ontology/wsf#update> ?_update; 
										<http://purl.org/ontology/wsf#delete> ?_delete. 
										filter(str(?ip) = \"$this->requester_ip\").
									}
									UNION
									{
										?_access <http://purl.org/ontology/wsf#webServiceAccess> <$this->requested_ws_uri>; 
										<http://purl.org/ontology/wsf#datasetAccess> <$dataset>; 
										<http://purl.org/ontology/wsf#registeredIP> ?ip; 
										<http://purl.org/ontology/wsf#create> ?_create; 
										<http://purl.org/ontology/wsf#read> ?_read; 
										<http://purl.org/ontology/wsf#update> ?_update; 
										<http://purl.org/ontology/wsf#delete> ?_delete. 
										filter(str(?ip) = \"0.0.0.0\").
									}
								}";
				
				$resultset = @$this->db->query($this->db->build_sparql_query(str_replace(array("\n", "\r", "\t"), "", $query), array ('_access', '_create', '_read', '_update', '_delete'), FALSE));

				$access = array();
				$create = array();
				$read = array();
				$update = array();
				$delete = array();
				
				if (odbc_error())
				{
					$this->conneg->setStatus(500);
					$this->conneg->setStatusMsg("Internal Error");
					$this->conneg->setStatusMsgExt("Error #auth-validator-101");					
					return;
				}			
				while(odbc_fetch_row($resultset))
				{
					array_push($access, strtolower(odbc_result($resultset, 1)));
					array_push($create, strtolower(odbc_result($resultset, 2)));
					array_push($read, strtolower(odbc_result($resultset, 3)));
					array_push($update, strtolower(odbc_result($resultset, 4)));
					array_push($delete, strtolower(odbc_result($resultset, 5)));
				}		
				
				unset($resultset);			
				
				// Check if an access is defined for this IP, dataset and registered web service
				if(count($access) <= 0)
				{
					$this->conneg->setStatus(400);
					$this->conneg->setStatusMsg("Bad Request");
					$this->conneg->setStatusMsgExt("No access defined for this requester IP ($this->requester_ip), dataset ($dataset) and web service ($this->requested_ws_uri)");
					return;
				}
				
				// Check if the user has permissions to perform one of the CRUD operation needed by the web service
				
				if(strtolower($ws_create) == "true")
				{
					if(array_search("true", $create) === FALSE)
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("The target web service ($this->requested_ws_uri) needs create access and the requested user ($this->requester_ip) doesn't have this access for that dataset ($dataset).");
						return;
					}				
				}
				
				if(strtolower($ws_update) == "true")
				{
					if(array_search("true", $update) === FALSE)
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("The target web service ($this->requested_ws_uri) needs update access and the requested user ($this->requester_ip) doesn't have this access for that dataset ($dataset).");
						return;
					}				
				}
				
				if(strtolower($ws_read) == "true")
				{
					if(array_search("true", $read) === FALSE)
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("The target web service ($this->requested_ws_uri) needs read access and the requested user ($this->requester_ip) doesn't have this access for that dataset ($dataset).");
						return;
					}				
				}		
				
				if(strtolower($ws_delete) == "true")
				{
					if(array_search("true", $delete) === FALSE)
					{
						$this->conneg->setStatus(400);
						$this->conneg->setStatusMsg("Bad Request");
						$this->conneg->setStatusMsgExt("The target web service ($this->requested_ws_uri) needs delete access and the requested user ($this->requester_ip) doesn't have this access for that dataset ($dataset).");
						return;
					}				
				}						
			}
		}
	}
}

	//@} 


?>