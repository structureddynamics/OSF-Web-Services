<?php
    
	/*! @ingroup WsFramework Framework for the Web Services */
	//@{ 
	
	/*! @brief Broadcast structWSF querying statistics
		
      @details This script is used to create XML statistic files of a structWSF instance. It can be useful to monitor
               the usage of the instance. Such statistics can be used to plan future scalability deployment
               plans, or simply to make sure that everything is working normally. Additionally, it can be used
               in conjonction with software systems such as Ganglia to monitor WS response errors in a global
               system monitoring infrastructure.
               
               Additionally, you can use this script to participate to the Global structWSF Statistical Service.
               To be part of the global structWSF statistics, you only have to subscribe your structWSF network
               at this address:
               
                      http://openstructs.org/structwsf/stats/subscribe
               
               You only have to put the URL where this script can be queried, and your stats will be automatically
               aggregated with stats of other structWSF networks.
               
               If you want to use that script for any reason, you have to set the value of the 
               $enableStatisticsBroadcast variable to TRUE.
               
               Note: eventually this script will be created as a Statistics & Monitoring web service endpoint.
               That way, structWSF system administrators will be able to manage it like any other web services,
               and leverage the structWSF permissions.
    
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.
		 \n\n\n
	 */	
   
   
  /*
    Set this variable to TRUE is you want to make your statistics available publicly.
    If this variable is set to TRUE, it means that anybody will be able to get statistics
    of your structWSF instance by query the script at this address:
      
      http://[your-web-site]/ws/statisticsBroker.php
      
    If you don't want to, you can simply remove this file from your web server.  
  */
  $enableStatisticsBroadcast = FALSE;
  
  
  if($enableStatisticsBroadcast) 
  {
    include_once("framework/WebService.php");
    include_once("framework/db.php");

    $data_ini = parse_ini_file(WebService::$data_ini . "data.ini", TRUE);

    $db = new DB_Virtuoso($data_ini["triplestore"]["username"], $data_ini["triplestore"]["password"],
      $data_ini["triplestore"]["dsn"], $data_ini["triplestore"]["host"]);
                  

    $sparql = " select count(?record) as ?nb_records
                from <".$data_ini["datasets"]["wsf_graph"]."datasets/>
                where
                {
                  ?dataset a <http://rdfs.org/ns/void#Dataset>.
                  graph ?dataset
                  {
                    ?record a ?type.
                  }
                }";
    
                             
    $resultset = @$db->query($db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $sparql), array(), FALSE));

    $nbRecords = "0";  
    if(!odbc_error())
    {
      $nbRecords = odbc_result($resultset, 1);
    }        

    $sparql = " select count(?dataset) as ?nb_datasets
                from <".$data_ini["datasets"]["wsf_graph"]."datasets/>
                where
                {
                  ?dataset a <http://rdfs.org/ns/void#Dataset>.
                }";
    
                             
    $resultset = @$db->query($db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $sparql), array(), FALSE));

    $nbDatasets = "0";  
    if(!odbc_error())
    {
      $nbDatasets = odbc_result($resultset, 1);
    }       
    
    $sparql = " select count(?o) as ?nb_triples
                from <".$data_ini["datasets"]["wsf_graph"]."datasets/>
                where
                {
                  ?dataset a <http://rdfs.org/ns/void#Dataset>.
                  graph ?dataset
                  {
                    ?record ?p ?o.
                  }
                }";
    
                             
    $resultset = @$db->query($db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $sparql), array(), FALSE));

    $nbTriples = "0";  
    if(!odbc_error())
    {
      $nbTriples = odbc_result($resultset, 1);
    }        
     


    $statisticsXML = "<statistics>\n";
    
    $statisticsXML .= "  <datasets nb=\"$nbDatasets\" nbTriples=\"$nbTriples\" nbRecords=\"$nbRecords\" />\n";

    
    
    $statisticsXML .= "  <webservices>\n";

    $resultset = @$db->query("select distinct requested_web_service from ".$data_ini["triplestore"]["log_table"]);

    $webservices = array();
    
    if(!odbc_error())
    {
      while(odbc_fetch_row($resultset))
      {     
          array_push($webservices, odbc_result($resultset, 1));
      }
    }        
    
    foreach($webservices as $ws)
    {
      $resultset = @$db->query("select count(*) from ".$data_ini["triplestore"]["log_table"]." where requested_web_service = '$ws'");

      $nbQueries = "0";    
      if(!odbc_error())
      {
        $nbQueries = odbc_result($resultset, 1);
      }      
      

      $resultset = @$db->query("select avg(request_processing_time) as average from ".$data_ini["triplestore"]["log_table"]." where 
                                requested_web_service = '$ws'");

      $averageTime = "0";    
      if(!odbc_error())
      {
        $averageTime = odbc_result($resultset, 1);
      }      
      
      $statisticsXML .= "    <".str_replace("/", "_", $ws)." nbQueries=\"$nbQueries\" averageTimePerQuery=\"$averageTime\">\n";
      
      $statisticsXML .= "      <httpMessages>\n";
      
      $resultset = @$db->query("select distinct request_http_response_status, count(request_http_response_status) as nb 
                                from ".$data_ini["triplestore"]["log_table"]." where requested_web_service = '$ws' 
                                group by request_http_response_status");

      if(!odbc_error())
      {
        while(odbc_fetch_row($resultset))
        {     
            $statisticsXML .= "        <msg type=\"".odbc_result($resultset, 1)."\" count=\"".odbc_result($resultset, 2).
                              "\" />\n";
        }
      }    
      
      $statisticsXML .= "      </httpMessages>\n";


      $statisticsXML .= "      <requestedMimes>\n";
      
      $resultset = @$db->query("select distinct requested_mime, count(requested_mime) as nb from ".$data_ini["triplestore"]["log_table"]." ".
                               "where requested_web_service = '$ws' group by requested_mime");

      if(!odbc_error())
      {
        while(odbc_fetch_row($resultset))
        {     
            $statisticsXML .= "        <mime type=\"".odbc_result($resultset, 1)."\" count=\"".odbc_result($resultset, 2).
                              "\" />\n";
        }
      }    
      
      $statisticsXML .= "      </requestedMimes>\n";

      
      $statisticsXML .= "    </".str_replace("/", "_", $ws).">\n";
    }  
    
    $statisticsXML .= "  </webservices>\n";

    
    
    $statisticsXML .= "</statistics>\n";
   
    header("Content-Type: text/xml; charset=utf-8");
    
    echo $statisticsXML;
  }
	
	
	//@} 	
?>