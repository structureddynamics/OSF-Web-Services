<?php
  
  namespace StructuredDynamics\structwsf\ws\auth\registrar\access\interfaces; 
  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  
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
        if(strtolower($this->ws->action) == "create")
        {
          // Create and describe the resource being registered
          // Note: we make sure we remove any previously defined triples that we are about to re-enter in the graph. 
          //       All information other than these new properties will remain in the graph

          $query = "delete from graph <" . $this->ws->wsf_graph . ">
                  { 
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#groupAccess> <".$this->ws->group."> ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <".$this->ws->dataset."> ;
                    ?p ?o.
                  }
                  where
                  {
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#groupAccess> <".$this->ws->group."> ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <".$this->ws->dataset."> ;
                    ?p ?o.
                  }
                  insert into <"
            . $this->ws->wsf_graph . ">
                  {
                    <" . $this->ws->wsf_graph . "access/" . md5($this->ws->group . $this->ws->dataset)
            . "> a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#groupAccess> <".$this->ws->group."> ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <".$this->ws->dataset."> ;";

          foreach($this->ws->ws_uris as $uri)
          {
            if($uri != "")
            {
              $query .= "<http://purl.org/ontology/wsf#webServiceAccess> <$uri> ;";
            }
          }

          $query .= "  <http://purl.org/ontology/wsf#create> " . ($this->ws->crud->create ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#read> " . ($this->ws->crud->read ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#update> " . ($this->ws->crud->update ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#delete> " . ($this->ws->crud->delete ? "\"True\"" : "\"False\"") . " .
                  }";

          $this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
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
        elseif(strtolower($this->ws->action) == "update")
        {
          // Update and describe the resource being registered

          $query = "modify graph <" . $this->ws->wsf_graph . ">
                  delete
                  { 
                    <".$this->ws->target_access_uri."> a <http://purl.org/ontology/wsf#Access> ;
                    ?p ?o.
                  }
                  insert
                  {
                    <"
            . $this->ws->wsf_graph . "access/" . md5($this->ws->group . $this->ws->dataset)
            . "> a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#groupAccess> <".$this->ws->group."> ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <".$this->ws->dataset."> ;";

          foreach($this->ws->ws_uris as $uri)
          {
            if($uri != "")
            {            
              $query .= "<http://purl.org/ontology/wsf#webServiceAccess> <$uri> ;";
            }
          }

          $query .= "  <http://purl.org/ontology/wsf#create> " . ($this->ws->crud->create ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#read> " . ($this->ws->crud->read ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#update> " . ($this->ws->crud->update ? "\"True\"" : "\"False\"") . " ;
                    <http://purl.org/ontology/wsf#delete> " . ($this->ws->crud->delete ? "\"True\"" : "\"False\"")
            . " .
                  }                  
                  where
                  {
                    <".$this->ws->target_access_uri."> a <http://purl.org/ontology/wsf#Access> ;
                    ?p ?o.
                  }";

          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_301->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, odbc_errormsg() . $query,
              $this->ws->errorMessenger->_301->level);
            return;
          }
        }
        elseif(strtolower($this->ws->action) == "delete_target")
        {
          // Just delete target access
          $query =
            "delete from graph <" . $this->ws->wsf_graph
            . ">
                  { 
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#groupAccess> <".$this->ws->group."> ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <".$this->ws->dataset."> ;
                    ?p ?o.
                  }
                  where
                  {
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#groupAccess> <".$this->ws->group."> ; 
                    <http://purl.org/ontology/wsf#datasetAccess> <".$this->ws->dataset."> ;
                    ?p ?o.
                  }";

          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
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
        elseif(strtolower($this->ws->action) == "delete_specific")
        {
          // Just delete target access
          $query =
            "delete from graph <" . $this->ws->wsf_graph
            . ">
                  { 
                    <".$this->ws->target_access_uri."> a <http://purl.org/ontology/wsf#Access> ;
                    ?p ?o.
                  }
                  where
                  {
                    <".$this->ws->target_access_uri."> a <http://purl.org/ontology/wsf#Access> ;
                    ?p ?o.
                  }";

          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_307->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_307->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_307->name, $this->ws->errorMessenger->_307->description, odbc_errormsg(),
              $this->ws->errorMessenger->_307->level);
            return;
          }
        }          
        else
        {
          // Delete all access to a specific dataset
          $query =
            "delete from graph <" . $this->ws->wsf_graph
            . ">
                  { 
                    ?access ?p ?o. 
                  }
                  where
                  {
                    ?access a <http://purl.org/ontology/wsf#Access> ;
                    <http://purl.org/ontology/wsf#datasetAccess> <".$this->ws->dataset."> ;
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
          $this->ws->invalidateCache('auth-lister:access_user');
          $this->ws->invalidateCache('auth-lister:access_dataset');
        }
      }
    }
  }
?>
