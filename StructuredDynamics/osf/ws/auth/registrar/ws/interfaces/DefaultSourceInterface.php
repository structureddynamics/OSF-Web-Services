<?php
  
  namespace StructuredDynamics\osf\ws\auth\registrar\ws\interfaces; 
  
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "3.0";
    }
    
    public function processInterface()
    {  
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        // Create and describe the resource being registered
        // Note: we make sure we remove any previously defined triples that we are 
        //       about to re-enter in the graph. All information other than these 
        //       new properties will remain in the graph

        $query = "delete from <" . $this->ws->wsf_graph . ">
                { 
                  <".$this->ws->registered_uri."> a <http://purl.org/ontology/wsf#WebService> .
                  <".$this->ws->registered_uri."> <http://purl.org/dc/terms/title> ?title . 
                  <".$this->ws->registered_uri."> <http://purl.org/ontology/wsf#endpoint> ?endpoint .
                  <".$this->ws->registered_uri."> <http://purl.org/ontology/wsf#hasCrudUsage> ?crud_usage .
                  ?crud_usage ?crud_property ?crud_value .
                }
                where
                {
                  graph <" . $this->ws->wsf_graph . ">
                  {
                    <".$this->ws->registered_uri."> a <http://purl.org/ontology/wsf#WebService> .
                    <".$this->ws->registered_uri."> <http://purl.org/dc/terms/title> ?title . 
                    <".$this->ws->registered_uri."> <http://purl.org/ontology/wsf#endpoint> ?endpoint .
                    <".$this->ws->registered_uri."> <http://purl.org/ontology/wsf#hasCrudUsage> ?crud_usage .
                    ?crud_usage ?crud_property ?crud_value .
                  }
                }
                insert into <" . $this->ws->wsf_graph . ">
                {
                  <".$this->ws->registered_uri."> a <http://purl.org/ontology/wsf#WebService> .
                  <".$this->ws->registered_uri."> <http://purl.org/dc/terms/title> \"".$this->ws->registered_title."\" .
                  <".$this->ws->registered_uri."> <http://purl.org/ontology/wsf#endpoint> \"".$this->ws->registered_endpoint."\" .
                  <".$this->ws->registered_uri."> <http://purl.org/ontology/wsf#hasCrudUsage> <" . $this->ws->registered_uri . "usage/> .
                  
                  <" . $this->ws->registered_uri . "usage/> a <http://purl.org/ontology/wsf#CrudUsage> ;
                  <http://purl.org/ontology/wsf#create> " . ($this->ws->crud_usage->create ? "\"True\"" : "\"False\"") . " ;
                  <http://purl.org/ontology/wsf#read> " . ($this->ws->crud_usage->read ? "\"True\"" : "\"False\"") . " ;
                  <http://purl.org/ontology/wsf#update> " . ($this->ws->crud_usage->update ? "\"True\"" : "\"False\"") . " ;
                  <http://purl.org/ontology/wsf#delete> " . ($this->ws->crud_usage->delete ? "\"True\"" : "\"False\"") . " .
                  
                  <" . $this->ws->wsf_graph . "> <http://purl.org/ontology/wsf#hasWebService> <".$this->ws->registered_uri.">.
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
      }
      
      // Invalidate caches
      if($this->ws->memcached_enabled)
      {
        $this->ws->invalidateCache('auth-validator');
        $this->ws->invalidateCache('auth-lister:ws');
        $this->ws->invalidateCache('auth-lister:dataset');
        $this->ws->invalidateCache('auth-lister:access_user');
        $this->ws->invalidateCache('auth-lister:access_dataset');
        $this->ws->invalidateCache('auth-lister:access_group');
      }
    }
  }
?>
