<?php

/*! @ingroup WsFramework */
//@{ 

/*! @file \ws\framework\Logger.php
	 @brief Logging system used to log all queries send to any structWSF web service endpoints
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
 */


/*
	drop table SD.WSF.ws_queries_log;
	create table "SD"."WSF"."ws_queries_log"
	(
	  "id" INTEGER IDENTITY,
	  "requested_web_service" VARCHAR,
	  "requester_ip" VARCHAR,
	  "request_parameters" VARCHAR,
	  "requested_mime" VARCHAR,
	  "request_datetime" DATETIME,
	  "request_processing_time" DECIMAL,
	  "request_http_response_status" VARCHAR,
	  "requester_user_agent" VARCHAR,
	  PRIMARY KEY ("id")
	);
	create index sd_wsf_requested_web_service_index on SD.WSF.ws_queries_log (requested_web_service);
	create index sd_wsf_requester_ip_index on SD.WSF.ws_queries_log (requester_ip);
	create index sd_wsf_requested_mime_index on SD.WSF.ws_queries_log (requested_mime);
	create index sd_wsf_request_datetime_index on SD.WSF.ws_queries_log (request_datetime);
	create index sd_wsf_request_http_response_status_index on SD.WSF.ws_queries_log (request_http_response_status);
	create index sd_wsf_requester_user_agent_index on SD.WSF.ws_queries_log (requester_user_agent);
*/

/*
 	Some interesting SQL queries to send against that log table:
 	
 	-- Get the number of queries sent to the WSF:
 	select count(*) as nb from SD.WSF.ws_queries_log;
 	
 	-- Get the last 10 queries sent to the WSF:
 	select top 10 * from SD.WSF.ws_queries_log order by ID desc;
 	
 	-- Get the average number of milliseconds per query sent to the syste
 	select avg(request_processing_time) as average_query_time from SD.WSF.ws_queries_log order by ID desc;
 	
 	-- Get the average query time for a specific web service endpoint
 	select avg(request_processing_time) as average_query_time from SD.WSF.ws_queries_log where requested_web_service = 'browse' order by ID desc;
 
 */

/*!	 @brief Log Web service queries
						
		\n

		@author Frederick Giasson, Structured Dynamics LLC.
	
		\n\n\n
*/

class Logger
{
	private $db_username = "username";
	private $db_password = "password";
	private $db_dsn = "dsn";
	private $db_host = "host";
	
	protected $db;
	
	function __construct($requestedWebService, $requesterIp, $requestParameters, $requestedMime, $requestTime, $requestProcessingTime, $requestHttpResponseStatus, $requesterUserAgent="")
	{
		$this->db = new DB_Virtuoso($this->db_username, $this->db_password, $this->db_dsn, $this->db_host);

	
		$this->db->query("insert into SD.WSF.ws_queries_log(requested_web_service, requester_ip, request_parameters, requested_mime, request_datetime, request_processing_time, request_http_response_status, requester_user_agent) values('".str_replace("'", "\'", $requestedWebService)."', '".str_replace("'", "\'", $requesterIp)."', '".str_replace("'", "\'", $requestParameters)."', '".str_replace("'", "\'", $requestedMime)."', '".str_replace("'", "\'", $requestTime)."', '".str_replace("'", "\'", $requestProcessingTime)."', '".str_replace("'", "\'", $requestHttpResponseStatus)."', '".str_replace("'", "\'", $requesterUserAgent)."')");
		
		
		$this->__destruct();
	}
	
	function __destruct(){}
}

//@} 

?>