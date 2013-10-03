<?php
  
  namespace StructuredDynamics\osf\ws\revision\diff\interfaces; 
  
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  use \StructuredDynamics\osf\framework\Subject;
  use \StructuredDynamics\osf\framework\Resultset;
  use \StructuredDynamics\osf\framework\Namespaces;
  use \StructuredDynamics\osf\ws\revision\read\RevisionRead;
  
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
      // If the query is still valid
      if($this->ws->conneg->getStatus() == 200)
      {          
        // Get the description of the revisions
        $lRevisionRead = new RevisionRead($this->ws->lrevuri, $this->ws->dataset, 'record');
                                 
        $lRevisionRead->ws_conneg('text/xml', $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
          $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $lRevisionRead->process();
        
        if($lRevisionRead->pipeline_getResponseHeaderStatus() != 200)
        { 
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_303->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, $lRevisionRead->pipeline_getResponseHeaderStatusMsgExt(),
            $this->ws->errorMessenger->_303->level);

          return;            
        }
        else
        {
          $lrevxml = $lRevisionRead->ws_serialize();
        }         
        
        $lrev = new Resultset($this->ws->wsf_base_path);
        $lrev->importStructXMLResultset($lrevxml);
        $lrev = $lrev->getResultset();
        
        $rRevisionRead = new RevisionRead($this->ws->rrevuri, $this->ws->dataset, 'record');
                                 
        $rRevisionRead->ws_conneg('text/xml', $_SERVER['HTTP_ACCEPT_CHARSET'], $_SERVER['HTTP_ACCEPT_ENCODING'],
          $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $rRevisionRead->process();
        
        if($rRevisionRead->pipeline_getResponseHeaderStatus() != 200)
        { 
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_304->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, $rRevisionRead->pipeline_getResponseHeaderStatusMsgExt(),
            $this->ws->errorMessenger->_304->level);

          return;            
        }
        else
        {
          $rrevxml = $rRevisionRead->ws_serialize();
        }         
        
        $rrev = new Resultset($this->ws->wsf_base_path);
        $rrev->importStructXMLResultset($rrevxml);
        $rrev = $rrev->getResultset();

        // Make sure the two revisions are revisions of the same record
        reset($lrev[$this->ws->dataset]);
        $lrevuri = key($lrev[$this->ws->dataset]);
        reset($rrev[$this->ws->dataset]);
        $rrevuri = key($rrev[$this->ws->dataset]);
        
        $lrev = $lrev[$this->ws->dataset][$lrevuri];
        $rrev = $rrev[$this->ws->dataset][$rrevuri];

        if($lrevuri != $rrevuri)
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, '',
            $this->ws->errorMessenger->_305->level);

          return;             
        }

        $cs = 'http://purl.org/vocab/changeset/schema#';
        
        $changeSet = new Subject($rrevuri.'/'.microtime(TRUE).'/changeset');
        $changeSet->setObjectAttribute($cs.'subjectOfChange', $lrevuri);
        $changeSet->setDataAttribute($cs.'createDate', gmdate("Y-m-d\TH:i:s\Z", time()));
        $changeSet->setType($cs.'ChangeSet');
                  
        $removals = array();
        $additions = array();

        // For each $lrev triple, look for an identical triple in the $rrev set. 
        // If you don't find a match, reify that $lrev triple as an rdf:Statement, 
        // and add that rdf:Statement as acs:removal to the ChangeSet.
        foreach($lrev as $lproperty => $lvalues)
        {
          if($lproperty == 'prefLabel' ||
             $lproperty == 'description' ||
             $lproperty == 'prefURL' ||
             $lproperty == 'type' ||
             $lproperty == 'altLabel')
          {
            continue;
          }
          
          foreach($lvalues as $lvalue)
          {
            $found = FALSE;
            
            foreach($rrev as $rproperty => $rvalues)
            {
              if($rproperty == $lproperty)
              {
                foreach($rvalues as $rvalue)
                {
                  if(isset($lvalue['uri']) && isset($rvalue['uri']))
                  {
                    if($lvalue['uri'] == $rvalue['uri'])                      
                    {
                      $found = TRUE;
                      break;
                    }
                  }
                  elseif(isset($lvalue['value']) && isset($rvalue['value']))
                  {
                    if($lvalue['value'] == $rvalue['value'])                      
                    {
                      $found = TRUE;
                      break;
                    }
                  }
                } 
                
                if($found)
                {
                  break;
                }                 
              }
            }
            
            if(!$found)
            {
              if(!isset($removals[$lproperty]))
              {
                $removals[$lproperty] = array();
              }
              
              $removals[$lproperty][] = $lvalue;
            }  
          }
        }    
        
        // For each $rrev triple, look for an identical triple in the $lrev set. If you don't find a match, 
        // reify that $rrev triple as an rdf:Statement, and add that rdf:Statement as acs:addition to the ChangeSet.
        foreach($rrev as $rproperty => $rvalues)
        {
          if($rproperty == 'prefLabel' ||
             $rproperty == 'description' ||
             $rproperty == 'prefURL' ||
             $rproperty == 'type' ||
             $rproperty == 'altLabel')
          {
            continue;
          }
          
          foreach($rvalues as $rvalue)
          {
            $found = FALSE;
            
            foreach($lrev as $lproperty => $lvalues)
            {
              if($lproperty == $rproperty)
              {
                foreach($lvalues as $lvalue)
                {
                  if(isset($rvalue['uri']) && isset($lvalue['uri']))
                  {
                    if($rvalue['uri'] == $lvalue['uri'])                      
                    {
                      $found = TRUE;
                      break;
                    }
                  }
                  elseif(isset($rvalue['value']) && isset($lvalue['value']))
                  {
                    if($rvalue['value'] == $lvalue['value'])                      
                    {
                      $found = TRUE;
                      break;
                    }
                  }
                } 
                
                if($found)
                {
                  break;
                }                 
              }
            }
            
            if(!$found)
            {
              if(!isset($additions[$rproperty]))
              {
                $additions[$rproperty] = array();
              }
              
              $additions[$rproperty][] = $rvalue;
            }  
          }
        }

        $statements = '';
        foreach($removals as $property => $values)
        {
          foreach($values as $value)
          {                 
            if(isset($value['uri']))
            {
              $bnodeUri = 'removal-'.md5($lrevuri . $property . $value['uri']);
              
              $statement = new Subject($bnodeUri);
              $statement->setType(Namespaces::$rdf.'Statement');
              
              $statement->setObjectAttribute(Namespaces::$rdf.'subject', $lrevuri);
              $statement->setObjectAttribute(Namespaces::$rdf.'predicate', $property);
              $statement->setObjectAttribute(Namespaces::$rdf.'object', $value['uri']);
              
              $changeSet->setObjectAttribute($cs.'removal', $bnodeUri);
            }
            else
            {
              $bnodeUri = 'removal-'.md5($lrevuri . $property . $value['value']);
              
              $statement = new Subject($bnodeUri);
              $statement->setType(Namespaces::$rdf.'Statement');
              
              $statement->setObjectAttribute(Namespaces::$rdf.'subject', $lrevuri);
              $statement->setObjectAttribute(Namespaces::$rdf.'predicate', $property);
              $statement->setDataAttribute(Namespaces::$rdf.'object', $value['value']);
              
              $changeSet->setObjectAttribute($cs.'removal', $bnodeUri);
            }
            
            $this->ws->rset->addSubject($statement);
          }
        }
        
        foreach($additions as $property => $values)
        {
          foreach($values as $value)
          {                 
            if(isset($value['uri']))
            {
              $bnodeUri = 'addition-'.md5($lrevuri . $property . $value['uri']);
              
              $statement = new Subject($bnodeUri);
              $statement->setType(Namespaces::$rdf.'Statement');
              
              $statement->setObjectAttribute(Namespaces::$rdf.'subject', $lrevuri);
              $statement->setObjectAttribute(Namespaces::$rdf.'predicate', $property);
              $statement->setObjectAttribute(Namespaces::$rdf.'object', $value['uri']);
              
              $changeSet->setObjectAttribute($cs.'addition', $bnodeUri);
            }
            else
            {
              $bnodeUri = 'addition-'.md5($lrevuri . $property . $value['value']);
              
              $statement = new Subject($bnodeUri);
              $statement->setType(Namespaces::$rdf.'Statement');
              
              $statement->setObjectAttribute(Namespaces::$rdf.'subject', $lrevuri);
              $statement->setObjectAttribute(Namespaces::$rdf.'predicate', $property);
              $statement->setDataAttribute(Namespaces::$rdf.'object', $value['value']);
              
              $changeSet->setObjectAttribute($cs.'addition', $bnodeUri);
            }
            
            $this->ws->rset->addSubject($statement);
          }
        }          
        
        $this->ws->rset->addSubject($changeSet);
      }      
    }
  }
?>
