<?php
  
  namespace StructuredDynamics\osf\ws\revision\read\interfaces; 
  
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
        $subjectUri = $this->ws->revuri;
            
        $revisionsDataset = rtrim($this->ws->dataset, '/').'/revisions/';
        
        if($this->ws->memcached_enabled)
        {
          $key = $this->ws->generateCacheKey('revision-read', array(
            $subjectUri,
            $revisionsDataset
          ));
          
          if($return = $this->ws->memcached->get($key))
          {
            $this->ws->setResultset($return);
            
            return;
          }
        }          
        
        // Archiving suject triples
        $this->ws->sparql->query("select ?p ?o (DATATYPE(?o)) as ?otype (LANG(?o)) as ?olang 
          from <" . $revisionsDataset . "> 
          where 
          {
            <".$this->ws->revuri."> ?p ?o.
          }");

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_303->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_303->level);
        }
        
        $subject = Array("type" => Array(),
                         "prefLabel" => "",
                         "altLabel" => Array(),
                         "prefURL" => "",
                         "description" => "");           
       
        $found = FALSE; 
        while($this->ws->sparql->fetch_binding())
        {
          $found = TRUE;
          
          $p = $this->ws->sparql->value('p');          
          $o = $this->ws->sparql->value('o');
          $otype = $this->ws->sparql->value('otype');
          $olang = $this->ws->sparql->value('olang');

          if($this->ws->mode == 'record')
          {
            if($p == Namespaces::$wsf.'revisionUri')
            {
              $subjectUri = $o;
            }
            
            if($p == Namespaces::$wsf.'revisionUri' ||
               $p == Namespaces::$wsf.'fromDataset' ||
               $p == Namespaces::$wsf.'revisionTime' ||
               $p == Namespaces::$wsf.'performer' ||
               $p == Namespaces::$wsf.'revisionStatus')              
            {
              continue;
            }
          }            
          
          if(!$olang)
          {
            $olang = '';
          }
          elseif($olang != '')
          {
            /* If a language is defined for an object, we force its type to be xsd:string */
            $otype = "http://www.w3.org/2001/XMLSchema#string";
          }
          
          if($p == Namespaces::$rdf."type")
          {
            if($this->ws->mode == 'record' && $o == Namespaces::$wsf.'Revision')
            {
              continue;
            }
            
            if(array_search($o, $subject["type"]) === FALSE)
            {
              array_push($subject["type"], $o);
            }
          }
          else
          {
            if(!isset($subject[$p]) || !is_array($subject[$p]))
            {
              $subject[$p] = array();
            }
            
            if(!empty($otype))
            {
              array_push($subject[$p], Array("value" => $o, 
                                             "lang" => $olang,
                                             "type" => ($otype == 'http://www.w3.org/2001/XMLSchema#string' ? '' :$otype)));
            }
            else
            {
              array_push($subject[$p], Array("uri" => $o, 
                                             "type" => ""));
            }
          }
        }
        
        // Get reification triples
        $this->ws->sparql->query("select ?rei_p ?rei_o ?p ?o from <" . $revisionsDataset . "> 
                  where 
                  {
                    ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <".$this->ws->revuri.">.
                    ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> ?rei_p.
                    ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> ?rei_o.
                    ?statement ?p ?o.
                  }");
                  
        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_304->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_304->level);
        }

        while($this->ws->sparql->fetch_binding())
        {
          $rei_p = $this->ws->sparql->value('rei_p');
          $rei_o = $this->ws->sparql->value('rei_o');
          $p = $this->ws->sparql->value('p');
          $o = $this->ws->sparql->value('o');

          if($p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#subject" &&
             $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate" &&
             $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#object" &&
             $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
          {
            foreach($subject[$rei_p] as $key => $value)
            {
              if((isset($value["uri"]) && $value["uri"] == $rei_o) ||
                 (isset($value["value"]) && $value["value"] == $rei_o))
              {
                if(!isset($subject[$rei_p][$key]["reify"]))
                {
                  $subject[$rei_p][$key]["reify"] = array();
                }
                
                if(!isset($subject[$rei_p][$key]["reify"][$p]))
                {
                  $subject[$rei_p][$key]["reify"][$p] = array();
                }
                
                array_push($subject[$rei_p][$key]["reify"][$p], $o);
              }
            }
          }
        }

        if($found)
        {
          if(!isset($subject[Namespaces::$dcterms.'isPartOf']))
          {
            $subject[Namespaces::$dcterms.'isPartOf'] = array(array("uri" => $this->ws->dataset, 
                                                                    "type" => ""));
          }
          
          $this->ws->rset->setResultset(Array($this->ws->dataset => array($subjectUri => $subject)));
          
          if($this->ws->memcached_enabled)
          {
            $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_revision_read_expire);
          }     
        }
        else
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_306->level);            
        }
      }      
    }
  }
?>
