<?php
  
  namespace StructuredDynamics\structwsf\ws\revision\delete\interfaces; 
  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\framework\Subject;
  use \StructuredDynamics\structwsf\framework\Namespaces;
  
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
          $revisionsDataset = rtrim($this->ws->dataset, '/').'/revisions/';
          
          // Make sure the lifecycle is not 'published'
          $query = "select ?status
                    from <" . $revisionsDataset . ">
                    where
                    {
                      <".$this->ws->revuri."> <http://purl.org/ontology/wsf#revisionStatus> ?status .
                    }
                    limit 1
                    offset 0";

          $resultset = @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array('status'), FALSE));

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_303->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, odbc_errormsg(),
              $this->ws->errorMessenger->_303->level);

            return;
          }
          else
          {
            $status = odbc_result($resultset, 1);
                            
            if($status == Namespaces::$wsf.'published')
            {
              $this->ws->conneg->setStatus(400);
              $this->ws->conneg->setStatusMsg("Bad Request");
              $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, $errorsOutput,
                $this->ws->errorMessenger->_304->level);

              return;                        
            }            
          }          
          
          // Delete the revision  
          $query = "delete from <" . $revisionsDataset . ">
                    { 
                      <".$this->ws->revuri."> ?s ?p .
                    }
                    where
                    {
                      <".$this->ws->revuri."> ?s ?p .
                    }";

          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, odbc_errormsg(),
              $this->ws->errorMessenger->_305->level);

            return;
          }          
          
          // Delete possible reification statements for this revision
          $query = "delete from <" . $revisionsDataset . ">
                    { 
                      ?statement ?p ?o.
                    }
                    where
                    {
                      ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <".$this->ws->revuri."> .
                      ?statement ?p ?o.
                    }";

          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
            FALSE));

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, odbc_errormsg(),
              $this->ws->errorMessenger->_306->level);

            return;
          }           
        }      
      }
    }
  }
?>
