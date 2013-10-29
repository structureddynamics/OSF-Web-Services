<?php
  
  namespace StructuredDynamics\osf\ws\auth\registrar\group\interfaces; 
  
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
        if($this->ws->action === 'create')
        {
          $query = "insert into <" . $this->ws->wsf_graph . ">
                    {
                      <".$this->ws->group_uri."> a <http://purl.org/ontology/wsf#Group> .
                      <".$this->ws->group_uri."> <http://purl.org/ontology/wsf#appID> \"".$this->ws->app_id."\" .
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
        
        if($this->ws->action === 'delete')
        {
          // Delete the group and its members
          $query = "delete from graph <" . $this->ws->wsf_graph . ">
                    { 
                      <".$this->ws->group_uri."> ?group_p ?group_o .
                      
                      ?user <http://purl.org/ontology/wsf#hasGroup> <".$this->ws->group_uri."> .
                    }
                    where
                    {
                      {
                        <".$this->ws->group_uri."> ?group_p ?group_o .
                      }
                      union
                      {                             
                        ?user <http://purl.org/ontology/wsf#hasGroup> <".$this->ws->group_uri."> .
                      }
                    }";
      
          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_304->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, odbc_errormsg(),
              $this->ws->errorMessenger->_304->level);

            return;
          }
          
          // Delete the permissions related to that group
          $query =
            "delete from graph <" . $this->ws->wsf_graph . ">
                  { 
                    ?access ?p ?o. 
                  }
                  where
                  {
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                      <http://purl.org/ontology/wsf#groupAccess> <".$this->ws->group_uri."> ;
                      ?p ?o.
                  }";

          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, odbc_errormsg(),
              $this->ws->errorMessenger->_303->level);
            return;
          }
          
        }
        
        // Invalidate caches
        if($this->ws->memcached_enabled)
        {
          $this->ws->invalidateCache('auth-validator');
          $this->ws->invalidateCache('auth-lister:dataset');
          $this->ws->invalidateCache('auth-lister:groups');
          $this->ws->invalidateCache('auth-lister:group_users');
          $this->ws->invalidateCache('auth-lister:access_user');
          $this->ws->invalidateCache('auth-lister:access_dataset');        
          $this->ws->invalidateCache('auth-lister:access_group');
        }
      }
    }
  }
?>
