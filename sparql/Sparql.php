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

	/*! @brief Requested IP */
	private $registered_ip = "";

	/*! @brief SPARQL query content resultset */
	private $sparqlContent = "";

	/*! @brief Supported MIME serializations by this web service */
	public static $supportedSerializations = array("application/sparql-results+xml", "application/sparql-results+json", "text/html", "application/rdf+xml", "application/rdf+n3", "application/*", "text/plain", "text/*", "*/*");


	/*!	 @brief Constructor
			 @details 	Initialize the Sparql Web Service
				
			@param[in] $query SPARQL query to send to the triple store of the WSF
			@param[in] $dataset Dataset URI where to send the query
			@param[in] $registered_ip Target IP address registered in the WSF
			@param[in] $requester_ip IP address of the requester
							
			\n
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($query, $dataset, $registered_ip, $requester_ip)
	{
		parent::__construct();		
		
		$this->db = new DB_Virtuoso(parent::$db_username, parent::$db_password, parent::$db_dsn, parent::$db_host);
		
		$this->query = $query;
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
		return $this->sparqlContent;			
	}
	
	/*!	 @brief Inject the DOCType in a XML document
							
			\n
			
			@param[in] $xmlDoc The XML document where to inject the doctype
			
			@return a XML document with a doctype
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function injectDoctype($xmlDoc){}

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
		return $this->pipeline_getResultset();		
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
		return $this->pipeline_getResultset();
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
			elseif($this->conneg->getMime() == "application/rdf+xml")
			{
				$queryFormat = "text/rdf+xml";
			}
			elseif($this->conneg->getMime() == "application/rdf+n3")
			{
				$queryFormat = "text/rdf+n3";
			}		
			
			$ch = curl_init();
	
			// Remove any potential reference to any graph in the sparql query.
			
			// Remove "from" clause
			$this->query = preg_replace("/([\s]*from[\s]*<.*>[\s]*)/Uim", "", $this->query);

			// Remove "from named" clauses
			$this->query = preg_replace("/([\s]*from[\s]*named[\s]*<.*>[\s]*)/Uim", "", $this->query);
	
			// Add a limit to the query
			$this->query .= " limit 1000";

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
		}
	}
}


//@} 


?>