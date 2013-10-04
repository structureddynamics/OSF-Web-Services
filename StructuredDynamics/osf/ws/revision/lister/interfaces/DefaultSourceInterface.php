<?php
  
  namespace StructuredDynamics\osf\ws\revision\lister\interfaces; 
  
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
        $query = '';
        
        if($this->ws->memcached_enabled)
        {
          $key = $this->ws->generateCacheKey('revision-lister', array(
            $this->ws->mode,
            $revisionsDataset,
            $this->ws->recordUri
          ));
          
          if($return = $this->ws->memcached->get($key))
          {
            $this->ws->setResultset($return);
            
            return;
          }
        }          

        switch($this->ws->mode)
        {
          case 'short':
            $query = "select ?revision ?timestamp
                      from <" . $revisionsDataset . ">
                      where
                      {
                        ?revision <http://purl.org/ontology/wsf#revisionTime> ?timestamp ;
                                  <http://purl.org/ontology/wsf#revisionUri> <".$this->ws->recordUri."> .
                      }
                      order by desc(?timestamp)";               
          break;
          
          case 'long':
            $query = "select ?revision ?timestamp ?performer ?status
                      from <" . $revisionsDataset . ">
                      where
                      {
                        ?revision <http://purl.org/ontology/wsf#revisionTime> ?timestamp ;
                                  <http://purl.org/ontology/wsf#performer> ?performer ;
                                  <http://purl.org/ontology/wsf#revisionStatus> ?status ;
                                  <http://purl.org/ontology/wsf#revisionUri> <".$this->ws->recordUri."> .
                      }
                      order by desc(?timestamp)";              
          break;
          
          default:
            $this->ws->conneg->setStatus(400);
            $this->ws->conneg->setStatusMsg("Bad Request");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_303->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, odbc_errormsg(),
              $this->ws->errorMessenger->_303->level);
            return;            
          break;
        }
        
        $resultset = @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(), FALSE));
        
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
        else
        {
          while(odbc_fetch_row($resultset))
          {            
            switch($this->ws->mode)
            {
              case 'short':
                $revision = odbc_result($resultset, 1);             
                $timestamp = odbc_result($resultset, 2);
                
                $subject = new Subject($revision);
                
                $subject->setType(Namespaces::$wsf.'Revision');
                
                $subject->setDataAttribute(Namespaces::$wsf.'revisionTime', $timestamp, Namespaces::$xsd.'double');
                         
                $this->ws->rset->addSubject($subject);
              break;
              
              case 'long':
                $revision = odbc_result($resultset, 1);             
                $timestamp = odbc_result($resultset, 2);
                $performer = odbc_result($resultset, 3);
                $status = odbc_result($resultset, 4);
                
                $subject = new Subject($revision);
                
                $subject->setType(Namespaces::$wsf.'Revision');
                
                $subject->setDataAttribute(Namespaces::$wsf.'revisionTime', $timestamp, Namespaces::$xsd.'double');
                $subject->setObjectAttribute(Namespaces::$wsf.'performer', $performer);
                $subject->setObjectAttribute(Namespaces::$wsf.'status', $status);
                         
                $this->ws->rset->addSubject($subject);
              break;
            }
          }
        }
        
        if($this->ws->memcached_enabled)
        {
          $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_revision_lister_expire);
        }             
      }
    }      
  }
?>
