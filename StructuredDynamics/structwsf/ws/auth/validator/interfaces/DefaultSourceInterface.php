<?php
  
  namespace StructuredDynamics\structwsf\ws\auth\validator\interfaces; 
  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
    }
    
    public function processInterface()
    {  
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {  
        // Get the CRUD usage of the target web service
        $resultset =
          $this->ws->db->query($this->ws->db->build_sparql_query("select ?_wsf ?_create ?_read ?_update ?_delete from <"
          . $this->ws->wsf_graph . "> where {?_wsf a <http://purl.org/ontology/wsf#WebServiceFramework>." .
          " ?_wsf <http://purl.org/ontology/wsf#hasWebService> <".$this->ws->requested_ws_uri.">. " .
          "<".$this->ws->requested_ws_uri."> <http://purl.org/ontology/wsf#hasCrudUsage> ?crudUsage. " .
          "?crudUsage <http://purl.org/ontology/wsf#create> ?_create; <http://purl.org/ontology/wsf#read> " .
          "?_read; <http://purl.org/ontology/wsf#update> ?_update; <http://purl.org/ontology/wsf#delete> " .
          "?_delete. }", array ('_wsf', '_create', '_read', '_update', '_delete'), FALSE));

        if(odbc_error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_300->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_300->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_300->name, $this->ws->errorMessenger->_300->description, odbc_errormsg(),
            $this->ws->errorMessenger->_300->level);
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
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_301->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description,
            "Target web service (".$this->ws->requested_ws_uri.") not registered to this Web Services Framework",
            $this->ws->errorMessenger->_301->level);
          return;
        }

        // Check the list of datasets
        $datasets = explode(";", $this->ws->requested_datasets);

        foreach($datasets as $dataset)
        {
          // Decode potentially encoded ";" character.
          $dataset = str_ireplace("%3B", ";", $dataset);

          $query =
            "select ?_access ?_create ?_read ?_update ?_delete 
                  from <" . $this->ws->wsf_graph
            . "> 
                  where 
                  { 
                      {
                      ?_access <http://purl.org/ontology/wsf#webServiceAccess> <".$this->ws->requested_ws_uri.">; 
                      <http://purl.org/ontology/wsf#datasetAccess> <$dataset>; 
                      <http://purl.org/ontology/wsf#registeredIP> ?ip; 
                      <http://purl.org/ontology/wsf#create> ?_create; 
                      <http://purl.org/ontology/wsf#read> ?_read; 
                      <http://purl.org/ontology/wsf#update> ?_update; 
                      <http://purl.org/ontology/wsf#delete> ?_delete. 
                      filter(str(?ip) = \"".$this->ws->requester_ip."\").
                    }
                    UNION
                    {
                      ?_access <http://purl.org/ontology/wsf#webServiceAccess> <".$this->ws->requested_ws_uri.">; 
                      <http://purl.org/ontology/wsf#datasetAccess> <$dataset>; 
                      <http://purl.org/ontology/wsf#registeredIP> ?ip; 
                      <http://purl.org/ontology/wsf#create> ?_create; 
                      <http://purl.org/ontology/wsf#read> ?_read; 
                      <http://purl.org/ontology/wsf#update> ?_update; 
                      <http://purl.org/ontology/wsf#delete> ?_delete. 
                      filter(str(?ip) = \"0.0.0.0\").
                    }
                  }";

          $resultset = @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
            array ('_access', '_create', '_read', '_update', '_delete'), FALSE));

          $access = array();
          $create = array();
          $read = array();
          $update = array();
          $delete = array();

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_302->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, odbc_errormsg(),
              $this->ws->errorMessenger->_302->level);
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
            $this->ws->conneg->setStatus(403);
            $this->ws->conneg->setStatusMsg("Forbidden");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_303->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description,
              "No access defined for this requester IP (".$this->ws->requester_ip."), dataset ($dataset) and web service (".$this->ws->requested_ws_uri.")",
              $this->ws->errorMessenger->_303->level);
            return;
          }

          // Check if the user has permissions to perform one of the CRUD operation needed by the web service

          if(strtolower($ws_create) == "true")
          {
            if(array_search("true", $create) === FALSE)
            {
              $this->ws->conneg->setStatus(403);
              $this->ws->conneg->setStatusMsg("Forbidden");
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_304->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description,
                "The target web service (".$this->ws->requested_ws_uri.") needs create access and the requested user (".$this->ws->requester_ip.") doesn't have this access for that dataset ($dataset).",
                $this->ws->errorMessenger->_304->level);
            }
          }

          if(strtolower($ws_update) == "true")
          {
            if(array_search("true", $update) === FALSE)
            {
              $this->ws->conneg->setStatus(403);
              $this->ws->conneg->setStatusMsg("Forbidden");
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description,
                "The target web service (".$this->ws->requested_ws_uri.") needs update access and the requested user (".$this->ws->requester_ip.") doesn't have this access for that dataset ($dataset).",
                $this->ws->errorMessenger->_305->level);
            }
          }

          if(strtolower($ws_read) == "true")
          {
            if(array_search("true", $read) === FALSE)
            {
              $this->ws->conneg->setStatus(403);
              $this->ws->conneg->setStatusMsg("Forbidden");
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description,
                "The target web service (".$this->ws->requested_ws_uri.") needs read access and the requested user (".$this->ws->requester_ip.") doesn't have this access for that dataset ($dataset).",
                $this->ws->errorMessenger->_306->level);

              return;
            }
          }

          if(strtolower($ws_delete) == "true")
          {
            if(array_search("true", $delete) === FALSE)
            {
              $this->ws->conneg->setStatus(403);
              $this->ws->conneg->setStatusMsg("Forbidden");
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_307->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_307->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_307->name, $this->ws->errorMessenger->_307->description,
                "The target web service needs delete access and the requested user doesn't have this access for that dataset.",
                $this->ws->errorMessenger->_307->level);

              return;
            }
          }
        }
      }
    }
  }
?>
