<?php
  
  namespace StructuredDynamics\osf\ws\revision\delete\interfaces; 
  
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  use \StructuredDynamics\osf\framework\Subject;
  use \StructuredDynamics\osf\framework\Namespaces;
  
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
        $revisionsDataset = rtrim($this->ws->dataset, '/').'/revisions/';
        
        // Make sure the lifecycle is not 'published'
        $this->ws->sparql->query("select ?status
                  from <" . $revisionsDataset . ">
                  where
                  {
                    <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionStatus> ?status .
                  }
                  limit 1
                  offset 0");

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_303->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_303->level);

          return;
        }
        else
        {
          $this->ws->sparql->fetch_binding();
          $status = $this->ws->sparql->value('status');
                          
          if($status == Namespaces::$wsf.'published')
          {
            $this->ws->conneg->setStatus(400);
            $this->ws->conneg->setStatusMsg("Bad Request");
            $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_304->level);

            return;                        
          }            
        }          
        
        // Delete the revision  
        $this->ws->sparql->query("delete from <" . $revisionsDataset . ">
                  { 
                    <".$this->ws->revuri."> ?s ?p .
                  }
                  where
                  {
                    <".$this->ws->revuri."> ?s ?p .
                  }");

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_305->level);

          return;
        }          
        
        // Delete possible reification statements for this revision
        $this->ws->sparql->query("delete from <" . $revisionsDataset . ">
                  { 
                    ?statement ?p ?o.
                  }
                  where
                  {
                    ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <".$this->ws->revuri."> .
                    ?statement ?p ?o.
                  }");

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_306->level);

          return;
        } 
        
        // Invalidate caches
        if($this->ws->memcached_enabled)
        {
          $this->ws->invalidateCache('revision-read');
          $this->ws->invalidateCache('revision-lister');
          $this->ws->invalidateCache('search');
          $this->ws->invalidateCache('sparql');        
          $this->ws->invalidateCache('crud-read');         
        }
      }      
    }
  }
?>
