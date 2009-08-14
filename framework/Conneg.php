<?php

/*! @ingroup WsFramework Framework for the Web Services */
//@{ 

/*! @file \ws\framework\Conneg.php
	 @brief The class that manage the content negotiation between any web service.
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
 */
 
/*!	 @brief The class that manage the content negotiation between any web service.
						
		\n
		
		@author Frederick Giasson, Structured Dynamics LLC.
	
		\n\n\n
*/

class Conneg
{
	/*! @brief Mime type of the query */	
	private $mime = "text/plain";	// http://www.iana.org/assignments/media-types/

	/*! @brief Charset of the query */	
	private $charset = "utf-8";		// http://www.iana.org/assignments/character-sets

	/*! @brief Encoding of the query */	
	private $encoding = "identity";	// http://www.iana.org/assignments/http-parameters 					(content-coding section)

	/*! @brief Language of the query */	
	private $lang = "en";				// http://www.iana.org/assignments/language-subtag-registry
	
	/*! @brief Status of the query */	
	private $status = 200;	// Check the status of the interaction with the user.

	/*! @brief Status message of the query */	
	private $statusMsg = "OK";

	/*! @brief Extended message of the status of the query */	
	private $statusMsgExt = "";
	
	/*! @brief Supported serializations by the service hanlding this query */	
	private $supported_serializations = "";

	/*!	 @brief Constructor 
							
			\n
			
			@param[in] $accept Accepted mime type(s) for the query
			@param[in] $accept_charset Accepted charset for the query
			@param[in] $accept_encoding Accepted encoding for the query
			@param[in] $accept_language Accepted language for the query
			@param[in] $supported_serializations Supported serializations by the target service
			
			@return returns NULL
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	function __construct($accept="", $accept_charset="", $accept_encoding="", $accept_language="", $supported_serializations)
	{	
		$this->supported_serializations = $supported_serializations;
	
		$this->accept($accept);
		
		if($this->status == 200)
		{
			$this->accept_charset($accept_charset);
		}
		if($this->status == 200)
		{
			$this->accept_encoding($accept_encoding);
		}
		if($this->status == 200)
		{
			$this->accept_language($accept_language);
		}
	}
	
	function __destruct(){}
	
	/*!	 @brief Send an answer to the requester 
							
			\n
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function respond()
	{
		header("HTTP/1.1 ".$this->status." ".$this->statusMsg);	
		header("Content-Type: ".$this->mime."; charset=".$this->charset);
		header("Content-Language: ".$this->lang);
		header("Content-Encoding: ".$this->encoding);
		
		if($this->statusMsgExt != "")
		{
			echo $this->statusMsgExt."\n";
		}
		
		$this->__destruct();
	}
	
	/*!	 @brief Get the mime type of the query 
							
			\n
			
			@return returns accepted mime type
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function getAccept()
	{
		return $this->mime;
	}

	/*!	 @brief Get the charset of the query
							
			\n
			
			@return returns the accepted charset of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	public function getAcceptCharset()
	{
		return $this->charset;
	}
	
	/*!	 @brief Get the encoding of the query
							
			\n
			
			@return returns the accepted encoding of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getAcceptEncoding()
	{
		return $this->encoding;
	}
	
	/*!	 @brief Get the language of the query
							
			\n
			
			@return returns the language charset of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getAcceptLanguage()
	{
		return $this->lang;
	}
	
	/*!	 @brief Set the status of the query
							
			\n
			
			@return returns the status that has been setted.
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function setStatus($status)
	{
		return $this->status = $status;
	}

	/*!	 @brief Set the message of the status of the query
							
			\n
			
			@return returns the message of the status that has been setted.
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function setStatusMsg($statusMsg)
	{
		return $this->statusMsg = $statusMsg;
	}
	
	/*!	 @brief Set the extended message of the status of the query
							
			\n
			
			@return returns the extended message of the status that has been setted.
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function setStatusMsgExt($statusMsgExt)
	{
		return $this->statusMsgExt = $statusMsgExt;
	}

	/*!	 @brief Get the status of the query
							
			\n
			
			@return returns the status of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getStatus()
	{
		return $this->status;
	}
	
	/*!	 @brief Get the message of the status of the query
							
			\n
			
			@return returns the message of the status of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getStatusMsg()
	{
		return $this->statusMsg;
	}

	/*!	 @brief Get the extended message of the status of the query
							
			\n
			
			@return returns the extended message of the status of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getStatusMsgExt()
	{
		return $this->statusMsgExt;
	}

	/*!	 @brief Get the mime of the query
			\n
			
			@return returns the mime of the query
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function getMime()
	{
		return $this->mime;
	}
	
	/*!	 @brief Check if a query mime is accepted and set the status of the query accordingly
					
			@param[in] $header HTTP header of the query 
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function accept($header)
	{
		$accepts = array();
		$mimes = array();
	
		if (strlen($header) > 0)
		{
			// break up string into pieces (languages and q factors)
			preg_match_all('/([^,]+)/', $header, $accepts);
		
			foreach($accepts[0] as $accept)
			{
				$foo = explode(";", str_replace(" ", "", $accept));
				
				if(isset($foo[1]))
				{
					if(stripos($foo[1], "q=") !== FALSE)
					{
						$foo[1] = str_replace("q=", "", $foo[1]);
					}
					else
					{
						$foo[1] = "1";
					}
				}
				else
				{
					array_push($foo, "1");
				}
				
				$mimes[$foo[0]] = $foo[1];
			}
			
			// In the case that there is a Accept: header, but that it is empty. We set it to: anything.
			if(count($mimes) <= 0)
			{
				$mimes["*/*"] = 1;
			}
		
			arsort($mimes, SORT_NUMERIC);
		
			$notAcceptable406 = TRUE;
			
			foreach($mimes as $mime => $q)
			{
				$mime = strtolower($mime);
			
				if($mime == "application/rdf+xml" && array_search("application/rdf+xml", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/rdf+xml";
					
					$notAcceptable406 = FALSE;
					
					break;
				}
				if(($mime == "application/rdf+n3" && array_search("application/rdf+n3", $this->supported_serializations) !== FALSE) || 
				   ($mime == "application/*" && array_search("application/*", $this->supported_serializations) !== FALSE))
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/rdf+n3";
					
					$notAcceptable406 = FALSE;
					
					break;
				}
				if(($mime == "text/plain" && array_search("text/plain", $this->supported_serializations) !== FALSE) || 
				   ($mime == "text/*" && array_search("text/*", $this->supported_serializations) !== FALSE) || 
				   ($mime == "*/*" && array_search("*/*", $this->supported_serializations) !== FALSE))
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/rdf+n3";

					$notAcceptable406 = FALSE;
					
					break;
				}
				if($mime == "application/xhtml+xml" && array_search("application/xhtml+xml", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/xhtml+rdfa";

					$notAcceptable406 = FALSE;
				
					break;
				}		
				if($mime == "application/xhtml+rdfa" && array_search("application/xhtml+rdfa", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/xhtml+rdfa";

					$notAcceptable406 = FALSE;

					break;
				}
				if($mime == "text/xml" && array_search("text/xml", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "text/xml";

					$notAcceptable406 = FALSE;

					break;
				}
				
				if($mime == "text/html" && array_search("text/html", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "text/html";

					$notAcceptable406 = FALSE;

					break;
				}
				
				if($mime == "application/sparql-results+xml" && array_search("application/sparql-results+xml", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/sparql-results+xml";

					$notAcceptable406 = FALSE;

					break;
				}
				
				if($mime == "application/sparql-results+json" && array_search("application/sparql-results+json", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/sparql-results+json";

					$notAcceptable406 = FALSE;

					break;
				}

				if($mime == "text/tsv" && array_search("text/tsv", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "text/tsv";

					$notAcceptable406 = FALSE;

					break;
				}
				
				if($mime == "text/csv" && array_search("text/csv", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "text/csv";

					$notAcceptable406 = FALSE;

					break;
				}
				
				if($mime == "application/sparql-results+json" && array_search("application/sparql-results+json", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/sparql-results+json";

					$notAcceptable406 = FALSE;

					break;
				}
												
				if($mime == "application/x-bibtex" && array_search("application/x-bibtex", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/x-bibtex";

					$notAcceptable406 = FALSE;

					break;
				}
				
									
				if($mime == "application/json" && array_search("application/json", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/json";

					$notAcceptable406 = FALSE;

					break;
				}
												
				if($mime == "application/irv+json" && array_search("application/irv+json", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/irv+json";

					$notAcceptable406 = FALSE;

					break;
				}
												
				if($mime == "application/bib+json" && array_search("application/bib+json", $this->supported_serializations) !== FALSE)
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->mime = "application/bib+json";

					$notAcceptable406 = FALSE;

					break;
				}
												
			}
			
			if($notAcceptable406)
			{
				$this->status = 406;
				$this->statusMsg = "Not Acceptable";
				$this->statusMsgExt = "Unacceptable mime type requested";
			}
		}
		else
		{
			// If no access header specified; it means that the client accept *anything*
			// In such a case we send back rdf+xml
			$this->status = 200;
			$this->statusMsg = "OK";
			$this->mime = "application/rdf+xml";			
		}	
	}
	
	/*!	 @brief Check if a query charset is accepted and set the status of the query accordingly
					
			@param[in] $header HTTP header of the query 
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function accept_charset($header)
	{
		$accepts = array();
		$charsets = array();
	
		if (strlen($header) > 0)
		{
			// break up string into pieces (languages and q factors)
			preg_match_all('/([^,]+)/', $header, $accepts);
		
			foreach($accepts[0] as $accept)
			{
				$foo = explode(";", str_replace(" ", "", $accept));
				
				if(isset($foo[1]))
				{
					if(stripos($foo[1], "q=") !== FALSE)
					{
						$foo[1] = str_replace("q=", "", $foo[1]);
					}
					else
					{
						$foo[1] = "1";
					}
				}
				else
				{
					array_push($foo, "1");
				}
				
				$charsets[$foo[0]] = $foo[1];
			}
			
			// In the case that there is a Accept-Charset: header, but that it is empty. We set it to: anything.
			if(count($charsets) <= 0)
			{
				$charsets["*"] = 1;
			}
		
			arsort($charsets, SORT_NUMERIC);
		
			$notAcceptable406 = TRUE;
			
			foreach($charsets as $charset => $q)
			{
				$charset = strtolower($charset);
			
				if($charset == "utf-8" || $charset == "*")
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->charset = "utf-8";
					
					$notAcceptable406 = FALSE;
					
					break;
				}
			}
			
			if($notAcceptable406)
			{
				$this->status = 406;
				$this->statusMsg = "Not Acceptable";
				$this->statusMsgExt = "Unacceptable charset requested";
			}
		}
		else
		{
			// If no access header specified; it means that the client accept *anything*
			// In such a case we send back utf-8
			$this->status = 200;
			$this->statusMsg = "OK";
			$this->charset = "utf-8";			
		}	
	}	
	
	/*!	 @brief Check if a query encoding is accepted and set the status of the query accordingly
					
			@param[in] $header HTTP header of the query 
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function accept_encoding($header)
	{
		$accepts = array();
		$encodings = array();
	
		if (strlen($header) > 0)
		{
			// break up string into pieces (languages and q factors)
			preg_match_all('/([^,]+)/', $header, $accepts);
		
			foreach($accepts[0] as $accept)
			{
				$foo = explode(";", str_replace(" ", "", $accept));
				
				if(isset($foo[1]))
				{
					if(stripos($foo[1], "q=") !== FALSE)
					{
						$foo[1] = str_replace("q=", "", $foo[1]);
					}
					else
					{
						$foo[1] = "1";
					}
				}
				else
				{
					array_push($foo, "1");
				}
				
				$encodings[$foo[0]] = $foo[1];
			}
			
			// In the case that there is a Accept-Charset: header, but that it is empty. We set it to: anything.
			if(count($encodings) <= 0)
			{
				$encodings["*"] = 1;
			}
		
			arsort($encodings, SORT_NUMERIC);
		
			$notAcceptable406 = TRUE;
			
			foreach($encodings as $encoding => $q)
			{
				$encoding = strtolower($encoding);
			
				if($encoding == "identity" || $encoding == "*")
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->encoding = "identity";
					
					$notAcceptable406 = FALSE;
					
					break;
				}
			}
			
			if($notAcceptable406)
			{
				$this->status = 406;
				$this->statusMsg = "Not Acceptable";
				$this->statusMsgExt = "Unacceptable encoding requested";
			}
		}
		else
		{
			// If no access header specified; it means that the client accept *anything*
			// In such a case we send back utf-8
			$this->status = 200;
			$this->statusMsg = "OK";
			$this->encoding = "identity";			
		}	
	}		
	
	/*!	 @brief Check if a query language is accepted and set the status of the query accordingly
					
			@param[in] $header HTTP header of the query 
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/		
	public function accept_language($header)
	{
		$accepts = array();
		$languages = array();
	
		if (strlen($header) > 0)
		{
			// break up string into pieces (languages and q factors)
			preg_match_all('/([^,]+)/', $header, $accepts);
		
			foreach($accepts[0] as $accept)
			{
				$foo = explode(";", str_replace(" ", "", $accept));
				
				if(isset($foo[1]))
				{
					if(stripos($foo[1], "q=") !== FALSE)
					{
						$foo[1] = str_replace("q=", "", $foo[1]);
					}
					else
					{
						$foo[1] = "1";
					}
				}
				else
				{
					array_push($foo, "1");
				}
				
				$languages[$foo[0]] = $foo[1];
			}
			
			// In the case that there is a Accept-Charset: header, but that it is empty. We set it to: anything.
			if(count($languages) <= 0)
			{
				$languages["*"] = 1;
			}
		
			arsort($languages, SORT_NUMERIC);
		
			$notAcceptable406 = TRUE;
			
			foreach($languages as $language => $q)
			{
				$language = strtolower($language);
			
				if($language == "en" || $language == "*")
				{
					$this->status = 200;
					$this->statusMsg = "OK";
					$this->language = "en";
					
					$notAcceptable406 = FALSE;
					
					break;
				}
			}
			
			if($notAcceptable406)
			{
				$this->status = 406;
				$this->statusMsg = "Not Acceptable";
				$this->statusMsgExt = "Unacceptable language requested";
			}
		}
		else
		{
			// If no access header specified; it means that the client accept *anything*
			// In such a case we send back utf-8
			$this->status = 200;
			$this->statusMsg = "OK";
			$this->language = "en";			
		}	
	}		
	
}

	//@} 

?>