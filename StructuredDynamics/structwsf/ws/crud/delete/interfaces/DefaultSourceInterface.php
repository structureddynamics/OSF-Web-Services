<?php
  
  namespace StructuredDynamics\structwsf\ws\crud\delete\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\framework\Solr;
  use \StructuredDynamics\structwsf\ws\crud\read\CrudRead;  
  use \StructuredDynamics\structwsf\framework\WebServiceQuerier;  
  
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
        $this->ws->validateQuery();

        // If the query is still valid
        if($this->ws->conneg->getStatus() == 200)
        {        
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
  }
?>
