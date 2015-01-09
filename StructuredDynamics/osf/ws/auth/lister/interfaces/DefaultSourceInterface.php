<?php
  
  namespace StructuredDynamics\osf\ws\auth\lister\interfaces; 
  
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  use \StructuredDynamics\osf\framework\Subject;
  
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
        if(strtolower($this->ws->mode) == "dataset")
        {
          if($this->ws->memcached_enabled)
          {
            $key = $this->ws->generateCacheKey('auth-lister:dataset', array(
              $this->ws->wsf_graph,
              $this->ws->headers['OSF-USER-URI'],
            ));
            
            if($return = $this->ws->memcached->get($key))
            {
              $this->ws->setResultset($return);
              
              return;
            }
          }
          
          $this->ws->sparql->query(" prefix wsf: <http://purl.org/ontology/wsf#>
              select distinct ?dataset 
              from <". $this->ws->wsf_graph ."> 
              where 
              { 
                <". $this->ws->headers['OSF-USER-URI'] ."> a wsf:User ;
                  wsf:hasGroup ?group .
                  
                ?access wsf:groupAccess ?group ;
                        wsf:datasetAccess ?dataset .
              }");

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_300->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_300->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_300->name, $this->ws->errorMessenger->_300->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_300->level);
            return;
          }

          $subject = new Subject("bnode:".md5(microtime()));
          $subject->setType("rdf:Bag");
          
          while($this->ws->sparql->fetch_binding())
          {
            $dataset = $this->ws->sparql->value('dataset');
            
            $subject->setObjectAttribute("rdf:li", $dataset, null, "void:Dataset");
          }
          
          $this->ws->rset->addSubject($subject);                     
          
          if($this->ws->memcached_enabled)
          {
            $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_auth_lister_expire);
          }          
        }
        elseif(strtolower($this->ws->mode) == "ws")
        {
          if($this->ws->memcached_enabled)
          {
            $key = $this->ws->generateCacheKey('auth-lister:ws', array(
              $this->ws->wsf_graph,
            ));
            
            if($return = $this->ws->memcached->get($key))
            {
              $this->ws->setResultset($return);
              
              return;
            }
          }
          
          $this->ws->sparql->query("select distinct ?ws from <". $this->ws->wsf_graph  .">
                  where
                  {
                    ?wsf a <http://purl.org/ontology/wsf#WebServiceFramework> ;
                          <http://purl.org/ontology/wsf#hasWebService> ?ws .
                  }");

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_301->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_301->level);
            return;
          }
          
          $subject = new Subject("bnode:".md5(microtime()));
          $subject->setType("rdf:Bag");          

          while($this->ws->sparql->fetch_binding())
          {
            $ws = $this->ws->sparql->value('ws');
      
            $subject->setObjectAttribute("rdf:li", $ws, null, "wsf:WebService");
          }
          
          $this->ws->rset->addSubject($subject);   
          
          if($this->ws->memcached_enabled)
          {
            $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_auth_lister_expire);
          }
        }
        elseif(strtolower($this->ws->mode) == "groups")
        {
          if($this->ws->memcached_enabled)
          {
            $key = $this->ws->generateCacheKey('auth-lister:groups', array(
              $this->ws->wsf_graph,
            ));
            
            if($return = $this->ws->memcached->get($key))
            {
              $this->ws->setResultset($return);
              
              return;
            }
          }          
          
          $this->ws->sparql->query(" prefix wsf: <http://purl.org/ontology/wsf#>
              select distinct ?group ?appID 
              from <". $this->ws->wsf_graph ."> 
              where 
              { 
                ?group a wsf:Group ;
                       wsf:appID ?appID .
              }");

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_300->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_300->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_300->name, $this->ws->errorMessenger->_300->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_300->level);
            return;
          }

          while($this->ws->sparql->fetch_binding())
          {
            $group = $this->ws->sparql->value('group');
            $appID = $this->ws->sparql->value('appID');
            
            $subject = new Subject($group);
            $subject->setType("wsf:Group");
            
            $subject->setDataAttribute("wsf:appID", $appID);
            
            $this->ws->rset->addSubject($subject);   
          }          
          
          if($this->ws->memcached_enabled)
          {
            $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_auth_lister_expire);
          }                  
        }
        elseif(strtolower($this->ws->mode) == "group_users")
        {
          if($this->ws->memcached_enabled)
          {
            $key = $this->ws->generateCacheKey('auth-lister:group_users', array(
              $this->ws->wsf_graph,
              $this->ws->group,
            ));
            
            if($return = $this->ws->memcached->get($key))
            {
              $this->ws->setResultset($return);
              
              return;
            }
          }
          
          $this->ws->sparql->query("prefix wsf: <http://purl.org/ontology/wsf#>
              select distinct ?user 
              from <". $this->ws->wsf_graph ."> 
              where 
              { 
                ?user wsf:hasGroup <". $this->ws->group ."> .
              }");

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_300->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_300->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_300->name, $this->ws->errorMessenger->_300->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_300->level);
            return;
          }

          $subject = new Subject("bnode:".md5(microtime()));
          $subject->setType("rdf:Bag");
          
          while($this->ws->sparql->fetch_binding())
          {
            $user = $this->ws->sparql->value('user');
            
            $subject->setObjectAttribute("rdf:li", $user, null, "wsf:User");
          }
          
          $this->ws->rset->addSubject($subject);     
          
          if($this->ws->memcached_enabled)
          {
            $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_auth_lister_expire);
          }                  
        }
        elseif(strtolower($this->ws->mode) == "user_groups")
        {
          $this->ws->sparql->query("prefix wsf: <http://purl.org/ontology/wsf#>
              select distinct ?group 
              from <". $this->ws->wsf_graph ."> 
              where 
              { 
                <". $this->ws->headers['OSF-USER-URI'] ."> wsf:hasGroup ?group .
              }");

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_300->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_300->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_300->name, $this->ws->errorMessenger->_300->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_300->level);
            return;
          }

          $subject = new Subject("bnode:".md5(microtime()));
          $subject->setType("rdf:Bag");
          
          while($this->ws->sparql->fetch_binding())
          {
            $group = $this->ws->sparql->value('group');
            
            $subject->setObjectAttribute("rdf:li", $group, null, "wsf:Group");
          }
          
          $this->ws->rset->addSubject($subject);          
        }
        else
        { 
          $query = '';
          
          if(strtolower($this->ws->mode) == "access_user")
          {   
            if($this->ws->memcached_enabled)
            {
              $key = $this->ws->generateCacheKey('auth-lister:access_user', array(
                $this->ws->wsf_graph,
                $this->ws->headers['OSF-USER-URI'],
                $this->ws->targetWebservice,
              ));
              
              if($return = $this->ws->memcached->get($key))
              {
                $this->ws->setResultset($return);
                
                return;
              }
            }
            
            $query = "select ?access ?datasetAccess ?create ?read ?update ?delete ?group ".($this->ws->targetWebservice == "all" ? "?webServiceAccess" : "")."
                      from <" . $this->ws->wsf_graph . ">
                      where
                      {
                        <".$this->ws->headers['OSF-USER-URI']."> a <http://purl.org/ontology/wsf#User> ;
                              <http://purl.org/ontology/wsf#hasGroup> ?group .
                              
                        ?access a <http://purl.org/ontology/wsf#Access> ;
                              <http://purl.org/ontology/wsf#datasetAccess> ?datasetAccess ;
                              ".($this->ws->targetWebservice == "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> ?webServiceAccess ;" : "")."
                              ".($this->ws->targetWebservice != "none" && $this->ws->targetWebservice != "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> <".$this->ws->targetWebservice."> ;" : "")."
                              <http://purl.org/ontology/wsf#groupAccess> ?group .
                              
                        optional
                        {
                          ?access <http://purl.org/ontology/wsf#create> ?create ;
                                  <http://purl.org/ontology/wsf#read> ?read ;
                                  <http://purl.org/ontology/wsf#update> ?update ;
                                  <http://purl.org/ontology/wsf#delete> ?delete .
                        }
                              
                      }"; 
          }
          elseif(strtolower($this->ws->mode) == "access_group")
          {   
            if($this->ws->memcached_enabled)
            {
              $key = $this->ws->generateCacheKey('auth-lister:access_group', array(
                $this->ws->wsf_graph,
                $this->ws->headers['OSF-USER-URI'],
                $this->ws->group,
                $this->ws->targetWebservice,
              ));
              
              if($return = $this->ws->memcached->get($key))
              {
                $this->ws->setResultset($return);
                
                return;
              }
            }
            
            $query = "select ?access ?datasetAccess ?create ?read ?update ?delete ".($this->ws->targetWebservice == "all" ? "?webServiceAccess" : "")."
                      from <" . $this->ws->wsf_graph . ">
                      where
                      {
                        ?access a <http://purl.org/ontology/wsf#Access> ;
                              <http://purl.org/ontology/wsf#datasetAccess> ?datasetAccess ;
                              ".($this->ws->targetWebservice == "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> ?webServiceAccess ;" : "")."
                              ".($this->ws->targetWebservice != "none" && $this->ws->targetWebservice != "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> <".$this->ws->targetWebservice."> ;" : "")."
                              <http://purl.org/ontology/wsf#groupAccess> <".$this->ws->group."> .
                              
                        optional
                        {
                          ?access <http://purl.org/ontology/wsf#create> ?create ;
                                  <http://purl.org/ontology/wsf#read> ?read ;
                                  <http://purl.org/ontology/wsf#update> ?update ;
                                  <http://purl.org/ontology/wsf#delete> ?delete .
                        }
                              
                      }"; 
          }
          else // access_dataset
          {
            if($this->ws->memcached_enabled)
            {
              $key = $this->ws->generateCacheKey('auth-lister:access_dataset', array(
                $this->ws->wsf_graph,
                $this->ws->targetWebservice,
                $this->ws->dataset
              ));
              
              if($return = $this->ws->memcached->get($key))
              {
                $this->ws->setResultset($return);
                
                return;
              }
            }
                        
            $query = "  select ?access ?group ?create ?read ?update ?delete ".($this->ws->targetWebservice == "all" ? "?webServiceAccess" : "")." 
                    from <" . $this->ws->wsf_graph . ">
                    where
                    {
                      ?access a <http://purl.org/ontology/wsf#Access> ;
                            <http://purl.org/ontology/wsf#groupAccess> ?group ;
                            ".($this->ws->targetWebservice == "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> ?webServiceAccess ;" : "")."
                            ".($this->ws->targetWebservice != "none" && $this->ws->targetWebservice != "all" ? "<http://purl.org/ontology/wsf#webServiceAccess> <".$this->ws->targetWebservice."> ;" : "")."
                            <http://purl.org/ontology/wsf#datasetAccess> <".$this->ws->dataset."> .
                            
                      optional
                      {
                        ?access <http://purl.org/ontology/wsf#create> ?create ;
                                <http://purl.org/ontology/wsf#read> ?read ;
                                <http://purl.org/ontology/wsf#update> ?update ;
                                <http://purl.org/ontology/wsf#delete> ?delete .                      
                      }
                    }";
          }

          $this->ws->sparql->query($query);

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");

            if(strtolower($this->ws->mode) == "access_user" || strtolower($this->ws->mode) == "access_dataset")
            {
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_302->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, 
                $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_302->level);
            }
            else
            {
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_303->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, 
                $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_303->level);
            }

            return;
          }
          
          $accessPreviousId = "";
          
          $subject = null;

          while($this->ws->sparql->fetch_binding())
          {
            $accessId = $this->ws->sparql->value('access');
            
            if($accessPreviousId != $accessId)
            {
              if($subject != null)
              {
                $this->ws->rset->addSubject($subject);
              }
              
              $subject = new Subject($accessId);
              $subject->setType("wsf:Access"); 
              
              $accessPreviousId = $accessId;
            
              $lastElement = "";

              if(strtolower($this->ws->mode) == "access_user")
              {                
                $subject->setObjectAttribute("wsf:datasetAccess", $this->ws->sparql->value('datasetAccess'), null, "void:Dataset");  
                $subject->setDataAttribute("wsf:create", $this->ws->sparql->value('create'));
                $subject->setDataAttribute("wsf:read", $this->ws->sparql->value('read'));
                $subject->setDataAttribute("wsf:update", $this->ws->sparql->value('update'));
                $subject->setDataAttribute("wsf:delete", $this->ws->sparql->value('delete'));
                $subject->setObjectAttribute("wsf:groupAccess", $this->ws->sparql->value('group'), null, 'wsf:Group');
                                                    
                if($this->ws->targetWebservice == "all")
                {                                                    
                  $subject->setObjectAttribute("wsf:webServiceAccess", $this->ws->sparql->value('webServiceAccess'), null, "wsf:WebService");  
                }
              }
              elseif(strtolower($this->ws->mode) == "access_group")
              {                
                $subject->setObjectAttribute("wsf:datasetAccess", $this->ws->sparql->value('datasetAccess'), null, "void:Dataset");  
                $subject->setDataAttribute("wsf:create", $this->ws->sparql->value('create'));
                $subject->setDataAttribute("wsf:read", $this->ws->sparql->value('read'));
                $subject->setDataAttribute("wsf:update", $this->ws->sparql->value('update'));
                $subject->setDataAttribute("wsf:delete", $this->ws->sparql->value('delete'));
                                                    
                if($this->ws->targetWebservice == "all")
                {                                                    
                  $subject->setObjectAttribute("wsf:webServiceAccess", $this->ws->sparql->value('webServiceAccess'), null, "wsf:WebService");  
                }
              }              
              else // access_dataset
              {
                $subject->setObjectAttribute("wsf:groupAccess", $this->ws->sparql->value('group'), null, 'wsf:Group');
                $subject->setDataAttribute("wsf:create", $this->ws->sparql->value('create'));
                $subject->setDataAttribute("wsf:read", $this->ws->sparql->value('read'));
                $subject->setDataAttribute("wsf:update", $this->ws->sparql->value('update'));
                $subject->setDataAttribute("wsf:delete", $this->ws->sparql->value('delete'));
                                                                                                        
                                                    
                if($this->ws->targetWebservice == "all")
                {                                                    
                  $subject->setObjectAttribute("wsf:webServiceAccess", $this->ws->sparql->value('webServiceAccess'), null, "wsf:WebService");                    
                }
              }            
            }
            else
            {
              $subject->setObjectAttribute("wsf:webServiceAccess", $this->ws->sparql->value('webServiceAccess'), null, "wsf:WebService");  
            }
          }
          
          // Add the last subject
          $this->ws->rset->addSubject($subject);
          
          if($this->ws->memcached_enabled)
          {
            $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_auth_lister_expire);
          }          
        }
      }    
    }  
  }
?>
