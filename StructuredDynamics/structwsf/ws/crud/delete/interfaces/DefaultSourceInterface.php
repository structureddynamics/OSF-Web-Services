<?php
  
  namespace StructuredDynamics\structwsf\ws\crud\delete\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\framework\Solr;
  use \StructuredDynamics\structwsf\ws\crud\read\CrudRead;  
  use \StructuredDynamics\structwsf\framework\WebServiceQuerier;  
  use \StructuredDynamics\structwsf\ws\revision\lister\RevisionLister;
  use \StructuredDynamics\structwsf\ws\revision\delete\RevisionDelete;
  use \StructuredDynamics\structwsf\framework\Resultset;
  use \StructuredDynamics\structwsf\framework\Subject;
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "1.0";
    }
    
    public function processInterface()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {        
        // Manage revisions records
        $revisionsDataset = rtrim($this->ws->dataset, '/').'/revisions/';

        // Change the status of the record from 'published' to 'archive'
        $query = "modify <" . $revisionsDataset . ">
                  delete
                  { 
                    ?revision <http://purl.org/ontology/wsf#revisionStatus> <http://purl.org/ontology/wsf#published> .
                  }
                  insert
                  {
                    ?revision <http://purl.org/ontology/wsf#revisionStatus> <http://purl.org/ontology/wsf#archive> .
                  }
                  where
                  {
                    ?revision <http://purl.org/ontology/wsf#revisionUri> <".$this->ws->resourceUri."> .
                  }";

        @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
          FALSE));

        if(odbc_error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_308->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_308->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_308->name, $this->ws->errorMessenger->_308->description, odbc_errormsg(),
            $this->ws->errorMessenger->_308->level);

          return;
        }            
        elseif($this->ws->mode == 'hard')
        {
          // delete all the revisions of this record
          $revisionLister = new RevisionLister($this->ws->resourceUri, $this->ws->dataset, 'short', $this->ws->registered_ip, $this->ws->requester_ip);
          
          $revisionLister->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
                                     $_SERVER['HTTP_ACCEPT_LANGUAGE']);

          $revisionLister->process();
          
          if($revisionLister->pipeline_getResponseHeaderStatus() != 200)
          {
            $this->ws->conneg->setStatus($revisionLister->pipeline_getResponseHeaderStatus());
            $this->ws->conneg->setStatusMsg($revisionLister->pipeline_getResponseHeaderStatusMsg());
            $this->ws->conneg->setStatusMsgExt($revisionLister->pipeline_getResponseHeaderStatusMsgExt());
            $this->ws->conneg->setError($revisionLister->pipeline_getError()->id, $revisionLister->pipeline_getError()->webservice,
              $revisionLister->pipeline_getError()->name, $revisionLister->pipeline_getError()->description,
              $revisionLister->pipeline_getError()->debugInfo, $revisionLister->pipeline_getError()->level);

            return;
          }
          else
          {
            $revisions = new Resultset($this->ws->wsf_base_path);
            
            $revisions->importStructXMLResultset($revisionLister->ws_serialize());
            
            foreach($revisions->getSubjects() as $subject)
            {
              $revisionDelete = new RevisionDelete($subject->getUri(), $this->ws->dataset, $this->ws->registered_ip, $this->ws->requester_ip);
              
              $revisionDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
                                         $_SERVER['HTTP_ACCEPT_LANGUAGE']);

              $revisionDelete->process();
              
              if($revisionDelete->pipeline_getResponseHeaderStatus() != 200)
              {
                $this->ws->conneg->setStatus($revisionDelete->pipeline_getResponseHeaderStatus());
                $this->ws->conneg->setStatusMsg($revisionDelete->pipeline_getResponseHeaderStatusMsg());
                $this->ws->conneg->setStatusMsgExt($revisionDelete->pipeline_getResponseHeaderStatusMsgExt());
                $this->ws->conneg->setError($revisionDelete->pipeline_getError()->id, $revisionDelete->pipeline_getError()->webservice,
                  $revisionDelete->pipeline_getError()->name, $revisionDelete->pipeline_getError()->description,
                  $revisionDelete->pipeline_getError()->debugInfo, $revisionDelete->pipeline_getError()->level);

                return;
              }                
            }
          }             
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
            $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, 
            $solr->errorMessage . '[Debugging information: ]'.$solr->errorMessageDebug,
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
              $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, 
              $solr->errorMessage . '[Debugging information: ]'.$solr->errorMessageDebug,
              $this->ws->errorMessenger->_302->level);

            return;
          }
        }
      }
    }      
  }
?>
