<?php

/*! @defgroup WsFramework Framework for the Web Services */
//@{ 

/*! @file \ws\framework\WebServiceQuerier.php
	 @brief Querying a RESTFull web service endpoint
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
*/

/*!	 @brief Query a RESTFul web service endpoint
						
		\n
		
		@author Frederick Giasson, Structured Dynamics LLC.
	
		\n\n\n
*/

class WebServiceQuerier
{
	/*! @brief URL of the web service endpoint to query */	
	private $url;

	/*! @brief HTTP method to use to query the endpoint (GET or POST) */	
	private $method;

	/*! @brief Parameters to send to the endpoint */	
	private $parameters;

	/*! @brief Mime type of the resultset that has to be returned by the web service endpoint */	
	private $mime;
	
	/*! @brief Status of the query */	
	private $queryStatus = "";	// The HTTP query status code returned by the server (ex: 200)

	/*! @brief Status message of the query */	
	private $queryStatusMessage = "";	// The HTTP query status message returned by the server (ex: OK)

	/*! @brief Extended message of the status of the query */	
	private $queryStatusMessageDescription = "";	// The HTTP query status message description returned by the server (ex: No subject concept)

	/*! @brief Resultset of the query */	
	private $queryResultset = "";
	
	/*!	 @brief Constructor
		
			@param[in] $url URL of the web service endpoint to query
			@param[in] $method HTTP method to use to query the endpoint (GET or POST)
			@param[in] $mime Mime type of the resultset that has to be returned by the web service endpoint
			@param[in] $parameters Parameters to send to the endpoint 
					
			\n
			
			@return returns returns a human readable description of the class
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	function __construct($url, $method, $mime, $parameters)
	{
		$this->url = $url;
		$this->method = $method;
		$this->parameters = $parameters;
		$this->mime = $mime;
		
		$this->queryWebService();
	}
	
	function __destruct(){}

	/*!	 @brief Send a query to a web service endpoint.
		
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	function queryWebService()
	{
		if(isset($_GET["wsf_debug"]))
		{
			//  Start TIMER
			//  -----------
			$stimer = explode( ' ', microtime() );
			$stimer = $stimer[1] + $stimer[0];
			//  -----------
		}
		
		$ch = curl_init();
		
		switch($this->method)
		{
			case "get":
				curl_setopt($ch, CURLOPT_URL, $this->url."?".$this->parameters);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: $this->mime"));
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_HEADER, TRUE);			
			break;
			
			case "post":
				curl_setopt($ch, CURLOPT_URL, $this->url);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: $this->mime"));
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->parameters);			
				curl_setopt($ch, CURLOPT_HEADER, TRUE);			
			break;
			
			default:
				return FALSE;
			break;
		}		


		$xml_data = curl_exec($ch);		
		
		if($xml_data === FALSE)
		{
			// Can't reach the remote server
			
			$this->queryStatus = "503";
			$this->queryStatusMessage = "Service Unavailable";
			$this->queryStatusMessageDescription = "Can't reach remote server (".curl_error($ch).")";
			$this->queryResultset = $data;
			
			if(isset($_GET["wsf_debug"]))
			{
				drupal_set_message(t("Web service query: [[url: $this->url] [method: $this->method] [mime: $this->mime] [parameters: $this->parameters]] (status: @status) @status_message - @status_message_description. [[[[".str_replace(array("<", ">", "\r", "\n"), array("&lt;", "&gt;", "<br>", "<br>"), $data)."]]]]", array("@status" =>strip_tags($this->getStatus()),  "@status_message" => strip_tags($this->getStatusMessage()), "@status_message_description" => strip_tags($this->getStatusMessageDescription()))), "error", TRUE);		
			}
			
			return;
		}
		
		// Remove any possible "HTTP/1.1 100 Continue" message from the web server
		$xml_data = str_replace("HTTP/1.1 100 Continue\r\n\r\n", "", $xml_data);
		
		$header = substr($xml_data, 0, strpos($xml_data, "\r\n\r\n"));
		
		$data = substr($xml_data, strpos($xml_data, "\r\n\r\n") + 4, strlen($xml_data) - (strpos($xml_data, "\r\n\r\n") - 4));
		
		curl_close($ch);
		
		// check returned message
		
		$this->queryStatus = substr($header, 9, 3);
		$this->queryStatusMessage = substr($header, 13, strpos($header, "\r\n") - 13);
		
		
		if(isset($_GET["wsf_debug"]))
		{
			//  End TIMER
			//  ---------
			$etimer = explode( ' ', microtime() );
			$etimer = $etimer[1] + $etimer[0];
			$execTime = $etimer - $stimer;
			//  ---------
			
			switch($_GET["wsf_debug"])
			{
				case "1":
					drupal_set_message(t("Web service query: [[url: <b>$this->url</b>] [method: $this->method] [mime: $this->mime] [parameters: $this->parameters] [execution time: <b>@exectime</b>]] (status: @status) @status_message - @status_message_description. [[[[".str_replace(array("<", ">", "\r", "\n"), array("&lt;", "&gt;", "<br>", "<br>"), $data)."]]]]", array("@status" =>strip_tags($this->getStatus()),  "@status_message" => strip_tags($this->getStatusMessage()), "@status_message_description" => strip_tags($this->getStatusMessageDescription()), "@exectime" => $execTime)), "warning", TRUE);		
				break;
				
				case"2":
					drupal_set_message(t("Web service query: [[url: <b>$this->url</b>] [method: $this->method] [mime: $this->mime] [parameters: $this->parameters] [execution time: <b>@exectime</b>]] (status: @status) @status_message - @status_message_description.", array("@status" =>strip_tags($this->getStatus()),  "@status_message" => strip_tags($this->getStatusMessage()), "@status_message_description" => strip_tags($this->getStatusMessageDescription()), "@exectime" => $execTime)), "warning", TRUE);		
				break;	
			}
		}
		
		// We have to continue. Let fix this to 200 OK so that this never raise errors within the WSF
		if($this->queryStatus == "100")
		{
			$this->queryStatus = "200";
			$this->queryStatusMessage = "OK";
			$this->queryStatusMessageDescription = "";
			$this->queryResultset = $data;
			return;
		}
		
		if($this->queryStatus != "200")
		{
			$this->queryStatusMessageDescription = str_replace(array("\r", "\n"), "", $data);
		}
		else
		{
			$this->queryResultset = $data;
		}
		
		return;			
	}
	
	/*!	 @brief Get the status of the query
							
			\n
			
			@return returns the status of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getStatus()
	{
		return $this->queryStatus;
	}

	/*!	 @brief Get the message of the status of the query
							
			\n
			
			@return returns the message of the status of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getStatusMessage()
	{
		return $this->queryStatusMessage;
	}
	
	/*!	 @brief Get the extended message of the status of the query
							
			\n
			
			@return returns the extended message of the status of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getStatusMessageDescription()
	{
		return $this->queryStatusMessageDescription;
	}
	
	/*!	 @brief Get the resultset of a query
							
			\n
			
			@return returns the resultset of a query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getResultset()
	{
		return $this->queryResultset;
	}	
	
}

//@} 


?>