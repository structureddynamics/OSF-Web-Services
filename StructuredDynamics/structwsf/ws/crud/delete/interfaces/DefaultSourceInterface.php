<?php
  
  namespace StructuredDynamics\structwsf\ws\crud\delete\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\framework\Solr;
  
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
        $this->ws->validateQuery();

        // If the query is still valid
        if($this->ws->conneg->getStatus() == 200)
        {
          
          // Track the record description changes
          if($this->ws->track_delete === TRUE)
          {
            // First check if the record is already existing for this record, within this dataset.
            $ws_cr = new CrudRead($this->ws->resourceUri, $this->ws->dataset, FALSE, TRUE, $this->ws->registered_ip, $this->ws->requester_ip);
            
            $ws_cr->ws_conneg("application/rdf+xml", "utf-8", "identity", "en");

            $ws_cr->process();

            $oldRecordDescription = $ws_cr->ws_serialize();
            
            $ws_cr_error = $ws_cr->pipeline_getError();
            
            if($ws_cr->pipeline_getResponseHeaderStatus() != 200)
            {
              // An error occured. Since we can't get the past state of a record, we have to send an error
              // for the CrudUpdate call since we can't create a tracking record for this record.
              $this->ws->conneg->setStatus(400);
              $this->ws->conneg->setStatusMsg("Bad Request");
              $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, 
                "We can't create a track record for the following record: ".$this->ws->resourceUri,
                $this->ws->errorMessenger->_303->level);
                
              break;
            }    
            
            $endpoint = "";
            if($this->ws->tracking_endpoint != "")
            {
              // We send the query to a remove tracking endpoint
              $endpoint = $this->ws->tracking_endpoint."create/";
            }
            else
            {
              // We send the query to a local tracking endpoint
              $endpoint = $this->ws->wsf_base_url."/ws/tracker/create/";
            }
            
            $wsq = new WebServiceQuerier($endpoint, "post",
              "text/xml", "from_dataset=" . urlencode($this->ws->dataset) .
              "&record=" . urlencode($this->ws->resourceUri) .
              "&action=delete" .
              "&previous_state=" . urlencode($oldRecordDescription) .
              "&previous_state_mime=" . urlencode("application/rdf+xml") .
              "&performer=" . urlencode($this->ws->registered_ip) .
              "&registered_ip=self");

            if($wsq->getStatus() != 200)
            {
              $this->ws->conneg->setStatus($wsq->getStatus());
              $this->ws->conneg->setStatusMsg($wsq->getStatusMessage());
              /*
              $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, odbc_errormsg(),
                $this->ws->errorMessenger->_302->level);                
              */
            }

            unset($wsq);              
          }        
          
          // Delete all triples for this URI in that dataset
          $query = "delete from <" . $this->ws->dataset . ">
                  { 
                    <" . $this->ws->resourceUri . "> ?p ?o. 
                  }
                  where
                  {
                    <" . $this->ws->resourceUri . "> ?p ?o. 
                  }";

          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

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

          // Delete the Solr document in the Solr index
          $solr = new Solr($this->ws->wsf_solr_core, $this->ws->solr_host, $this->ws->solr_port, $this->ws->fields_index_folder);

          if(!$solr->deleteInstanceRecord($this->ws->resourceUri, $this->ws->dataset))
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_301->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, odbc_errormsg(),
              $this->ws->errorMessenger->_301->level);

            return;
          }

          if($this->ws->solr_auto_commit === FALSE)
          {
            if(!$solr->commit())
            {
              $this->ws->conneg->setStatus(500);
              $this->ws->conneg->setStatusMsg("Internal Error");
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_302->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, odbc_errormsg(),
                $this->ws->errorMessenger->_302->level);

              return;
            }
          }
        }
      }      
    }
  }
?>
