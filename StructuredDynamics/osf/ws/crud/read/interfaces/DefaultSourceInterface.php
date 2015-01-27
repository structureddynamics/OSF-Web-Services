<?php
  
  namespace StructuredDynamics\osf\ws\crud\read\interfaces; 
  
  use \StructuredDynamics\osf\framework\Namespaces;  
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
        $uris = explode(";", $this->ws->resourceUri);
        $datasets = explode(";", $this->ws->dataset);

        if(count($uris) > 64)
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_301->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, "",
            $this->ws->errorMessenger->_301->level);

          return;
        }
        
        $subjects = array();
        foreach($uris as $key => $u)
        {
          // Decode potentially encoded ";" character.
          $u = str_ireplace("%3B", ";", $u);
          $d = str_ireplace("%3B", ";", $datasets[$key]);
          
          if($this->ws->memcached_enabled)
          {
            $cacheKey = $this->ws->generateCacheKey('crud-read', array(
              $this->ws->include_linksback,
              $this->ws->include_reification,
              md5((is_array($this->ws->include_attributes_list) ? implode(';', $this->ws->include_attributes_list) : '')),
              $this->ws->lang,
              md5($u.' '.$d)
            ));
            
            if($return = $this->ws->memcached->get($cacheKey))
            {
              $subjects[$u] = $return;
              
              continue;
            }
          }          
          

          $query = "";

          $attributesFilter = "";
          
          // At least return the type
          if(is_array($this->ws->include_attributes_list) && count($this->ws->include_attributes_list) > 0)
          {
            $attributesFilter = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>,';
            
            foreach($this->ws->include_attributes_list as $attr)
            {
              if($attr != "")
              {
                $attributesFilter .= "<$attr>,";
              }
            }
            
            $attributesFilter = trim($attributesFilter, ",");              
          }

          if($this->ws->globalDataset === FALSE)
          {
            $d = str_ireplace("%3B", ";", $datasets[$key]);

            // Archiving suject triples
            $query = "select ?p ?o (DATATYPE(?o)) as ?otype (LANG(?o)) as ?olang 
                      from <" . $d . "> 
                      where 
                      {
                        <$u> ?p ?o.
                        ".($attributesFilter == "" ? "" : "FILTER (?p IN($attributesFilter))")."
                      }";
          }
          else
          {
            $d = "";

            foreach($datasets as $dataset)
            {
              if($dataset != "")
              {
                $d .= " from named <$dataset> ";
              }
            }

            // Archiving suject triples
            $query = "select ?p ?o (DATATYPE(?o)) as ?otype (LANG(?o)) as ?olang ?g 
                      $d 
                      where 
                      {
                        graph ?g
                        {
                          <$u> ?p ?o.
                        }
                        ".($attributesFilter == "" ? "" : "FILTER (?p IN($attributesFilter))")."
                      }";     
          }

          $this->ws->sparql->query($query);

          if($this->ws->sparql->error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_302->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, 
              $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_302->level);
          }
          
          $g = "";
          
          while($this->ws->sparql->fetch_binding())
          {
            if(!isset($subjects[$u]))
            {
              $subjects[$u] = Array("type" => Array(),
                                    "prefLabel" => "",
                                    "altLabel" => Array(),
                                    "prefURL" => "",
                                    "description" => "");              
            }
          
            $p = $this->ws->sparql->value('p');
            $o = $this->ws->sparql->value('o');
            $otype = $this->ws->sparql->value('otype');
            $olang = $this->ws->sparql->value('olang');

            if($this->ws->lang != "" && $olang != "" && $olang != $this->ws->lang)
            {
              continue;
            }
                      
            if($g == "")
            {
              if($this->ws->globalDataset === FALSE)
              {
                $g = str_ireplace("%3B", ";", $datasets[$key]);
              }
              else
              {
                $g = $this->ws->sparql->value('g');
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
            
            if($this->ws->globalDataset === TRUE) 
            {
              if($p == Namespaces::$rdf."type")
              {
                if(array_search($o, $subjects[$u]["type"]) !== FALSE)
                {
                  continue;
                }
                
                array_push($subjects[$u]["type"], $o);
              }
              else
              {
                /** 
                * If we are using the globalDataset, there is a possibility that triples get duplicated
                * if the same triples, exists in two different datasets. It is why we have to filter them there
                * so that we don't duplicate them in the serialized dataset.
                */
                $found = FALSE;
                if(isset($subjects[$u][$p]) && is_array($subjects[$u][$p]))
                {
                  foreach($subjects[$u][$p] as $value)
                  {
                    if(isset($value["value"]) && $value["value"] == $o)
                    {
                      $found = TRUE;
                      break;
                    }
                    
                    if(isset($value["uri"]) && $value["uri"] == $o)
                    {
                      $found = TRUE;
                      break;
                    }
                  }
                }     
                
                if($found === FALSE)
                {     
                  if(!isset($subjects[$u][$p]) || !is_array($subjects[$u][$p]))
                  {
                    $subjects[$u][$p] = array();
                  }
                  
                  if(!empty($otype))
                  {
                    array_push($subject[$p], Array("value" => $o, 
                                                   "lang" => $olang,
                                                   "type" => ($otype == 'http://www.w3.org/2001/XMLSchema#string' ? '' : $otype)));
                  }
                  else
                  {
                    array_push($subject[$p], Array("uri" => $o, 
                                                   "type" => ""));
                  }                  
                }
              }
            }
            else
            {
              if($p == Namespaces::$rdf."type")
              {
                if(array_search($o, $subjects[$u]["type"]) === FALSE)
                {
                  array_push($subjects[$u]["type"], $o);
                }
              }
              else
              {
                if(!isset($subjects[$u][$p]) || !is_array($subjects[$u][$p]))
                {
                  $subjects[$u][$p] = array();
                }
                
                if(!empty($otype))
                {
                  array_push($subjects[$u][$p], Array("value" => $o, 
                                                      "lang" => $olang,
                                                      "type" => ($otype == 'http://www.w3.org/2001/XMLSchema#string' ? '' : $otype)));
                }
                else
                {
                  array_push($subjects[$u][$p], Array("uri" => $o, 
                                                      "type" => ""));
                }                   
              }
            }
          }

          // Assigning the Dataset relationship
          if($g != "")
          {         
            if(!isset($subjects[$u]["http://purl.org/dc/terms/isPartOf"]) || !is_array($subjects[$u]["http://purl.org/dc/terms/isPartOf"]))
            {
              $subjects[$u]["http://purl.org/dc/terms/isPartOf"] = array();
            }
            
            array_push($subjects[$u]["http://purl.org/dc/terms/isPartOf"], Array("uri" => $g, 
                                                                                 "type" => ""));            
          }        

          // Archiving object triples
          if(strtolower($this->ws->include_linksback) == "true")
          {
            $query = "";

            if($this->ws->globalDataset === FALSE)
            {
              $query = "select ?s ?p from <" . $d . "> where {?s ?p <" . $u . ">.}";
            }
            else
            {
              $d = "";

              foreach($datasets as $dataset)
              {
                if($dataset != "")
                {
                  $d .= " from named <$dataset> ";
                }
              }

              $query = "select ?s ?p $d where {graph ?g{?s ?p <" . $u . ">.}}";
            }
              
            $this->ws->sparql->query($query);

            if($this->ws->sparql->error())
            {
              $this->ws->conneg->setStatus(500);
              $this->ws->conneg->setStatusMsg("Internal Error");
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_303->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, 
                $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_303->level);
            }

            while($this->ws->sparql->fetch_binding())
            {
              $s = $this->ws->sparql->value('s');
              $p = $this->ws->sparql->value('p');

              // Make sure that the linkback record is not a record that is already returned by the endpoint
              if(in_array($s, $uris))
              {
                continue;  
              }
              
              if(!isset($subjects[$s]))
              {
                $subjects[$s] = array( "type" => array(),
                                       "prefLabel" => "",
                                       "altLabel" => array(),
                                       "prefURL" => "",
                                       "description" => "");
              }
              
              if(!isset($subjects[$s][$p]))
              {
                $subjects[$s][$p] = array();
              }
              
              array_push($subjects[$s][$p], array("uri" => $u, "type" => ""));            
            }
          }

          // Get reification triples
          if(strtolower($this->ws->include_reification) == "true")
          {
            $query = "";

            if($this->ws->globalDataset === FALSE)
            {
              $query = "  select ?statement ?rei_p ?rei_o ?p ?o from <" . $d . "reification/> 
                      where 
                      {
                        ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <". $u . ">.
                        ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> ?rei_p.
                        ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> ?rei_o.
                        ?statement ?p ?o.
                      }";
            }
            else
            {
              $d = "";

              foreach($datasets as $dataset)
              {
                if($dataset != "")
                {
                  $d .= " from named <" . $dataset . "reification/> ";
                }
              }

              $query = "  select ?statement ?rei_p ?rei_o ?p ?o $d 
                      where 
                      {
                        graph ?g
                        {
                          ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#subject> <". $u .">.
                          ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate> ?rei_p.
                          ?statement <http://www.w3.org/1999/02/22-rdf-syntax-ns#object> ?rei_o.
                          ?statement ?p ?o.
                        }
                      }";
            }
          
            $this->ws->sparql->query($query);

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
              $statement = $this->ws->sparql->value('statement');
              $rei_p = $this->ws->sparql->value('rei_p');
              $rei_o = $this->ws->sparql->value('rei_o');
              $p = $this->ws->sparql->value('p');
              $o = $this->ws->sparql->value('o');

              if($p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#subject"
                && $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate"
                && $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#object"
                && $p != "http://www.w3.org/1999/02/22-rdf-syntax-ns#type")
              {
                if(isset($subjects[$u][$rei_p]))
                {
                  foreach($subjects[$u][$rei_p] as $key => $value)
                  {
                    if((isset($value["uri"]) && $value["uri"] == $rei_o) ||
                       (isset($value["value"]) && $value["value"] == $rei_o))
                    {
                      if(!isset($subjects[$u][$rei_p][$key]["reify"]))
                      {
                        $subjects[$u][$rei_p][$key]["reify"] = array();
                      }
                      
                      if(!isset($subjects[$u][$rei_p][$key]["reify"][$p]))
                      {
                        $subjects[$u][$rei_p][$key]["reify"][$p] = array();
                      }
                      
                      array_push($subjects[$u][$rei_p][$key]["reify"][$p], $o);
                    }
                  }
                }
              }
            }
          }
          
          if($this->ws->memcached_enabled)
          {
            if(isset($subjects[$u]))
            {
              $this->ws->memcached->set($cacheKey, $subjects[$u], NULL, $this->ws->memcached_crud_read_expire);
            }
          }
        }

        if(count($subjects) <= 0)
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_300->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_300->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_300->name, $this->ws->errorMessenger->_300->description, "",
            $this->ws->errorMessenger->_300->level);
        }
        else
        {
          $this->ws->rset->setResultset(Array("unspecified" => $subjects));
        }
      }          
    }
  }
?>
