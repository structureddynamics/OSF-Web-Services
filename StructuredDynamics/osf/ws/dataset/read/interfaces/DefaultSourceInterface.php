<?php
  
  namespace StructuredDynamics\osf\ws\dataset\read\interfaces; 
  
  use \StructuredDynamics\osf\framework\Namespaces;  
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
        $query = "";
        $nbDatasets = 0;
        
        if($this->ws->datasetUri == "all")
        {
          if($this->ws->memcached_enabled)
          {
            $key = $this->ws->generateCacheKey('dataset-read:all', array(
              $this->ws->wsf_graph,
              $this->ws->headers['OSF-USER-URI']
            ));
            
            if($return = $this->ws->memcached->get($key))
            {
              $this->ws->setResultset($return);
              
              return;
            }
          }          
                    
          $query = "prefix wsf: <http://purl.org/ontology/wsf#>
                    select distinct ?dataset ?title ?description ?creator ?created ?modified ?contributor
                    from named <" . $this->ws->wsf_graph . ">
                    from named <" . $this->ws->wsf_graph . "datasets/>
                    where
                    {
                      graph <" . $this->ws->wsf_graph . ">
                      {
                        <". $this->ws->headers['OSF-USER-URI'] ."> a wsf:User ;
                          wsf:hasGroup ?group .
                          
                        ?access wsf:groupAccess ?group ;
                                wsf:datasetAccess ?dataset .                      
                      }
                      
                      graph <". $this->ws->wsf_graph ."datasets/>
                      {
                        ?dataset a <http://rdfs.org/ns/void#Dataset> ;
                        <http://purl.org/dc/terms/created> ?created.
                    
                        OPTIONAL{?dataset <http://purl.org/dc/terms/title> ?title.}
                        OPTIONAL{?dataset <http://purl.org/dc/terms/description> ?description.}
                        OPTIONAL{?dataset <http://purl.org/dc/terms/modified> ?modified.}
                        OPTIONAL{?dataset <http://purl.org/dc/terms/contributor> ?contributor.}
                        OPTIONAL{?dataset <http://purl.org/dc/terms/creator> ?creator.}
                      }    
                    } ORDER BY ?title";

          $resultset = @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
            array ("dataset", "title", "description", "creator", "created", "modified", "contributor"), FALSE));

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
          else
          {
            $dataset = "";
            $title = "";
            $description = "";
            $creator = "";
            $created = "";
            $modified = "";
            $contributors = array();

            while(odbc_fetch_row($resultset))
            {            
              $dataset2 = odbc_result($resultset, 1);

              if($dataset2 != $dataset && $dataset != "")
              {
                $subject = new Subject($dataset);
                $subject->setType(Namespaces::$void."Dataset");
                
                if($title != ""){$subject->setDataAttribute(Namespaces::$dcterms."title", $title);}
                if($description != ""){$subject->setDataAttribute(Namespaces::$dcterms."description", $description);}
                if($creator != ""){$subject->setObjectAttribute(Namespaces::$dcterms."creator", $creator, null, "sioc:User");}
                if($created != ""){$subject->setDataAttribute(Namespaces::$dcterms."created", $created);}
                if($modified != ""){$subject->setDataAttribute(Namespaces::$dcterms."modified", $modified);}
                
                foreach($contributors as $contributor)
                {
                  if($contributor != "")
                  {
                    $subject->setObjectAttribute(Namespaces::$dcterms."contributor", $contributor, null, "sioc:User");
                  }
                }  
                  
                $this->ws->rset->addSubject($subject);  
                $nbDatasets++;
                
                $contributors = array();
              }

              $dataset = $dataset2;

              $title = odbc_result($resultset, 2);
              $description = $this->ws->db->odbc_getPossibleLongResult($resultset, 3);

              $creator = odbc_result($resultset, 4);
              $created = odbc_result($resultset, 5);
              $modified = odbc_result($resultset, 6);
              array_push($contributors, odbc_result($resultset, 7));
            }

            if($dataset != "")
            {
              $subject = new Subject($dataset);
              $subject->setType(Namespaces::$void."Dataset");
              
              if($title != ""){$subject->setDataAttribute(Namespaces::$dcterms."title", $title);}
              if($description != ""){$subject->setDataAttribute(Namespaces::$dcterms."description", $description);}
              if($creator != ""){$subject->setObjectAttribute(Namespaces::$dcterms."creator", $creator, null, "sioc:User");}
              if($created != ""){$subject->setDataAttribute(Namespaces::$dcterms."created", $created);}
              if($modified != ""){$subject->setDataAttribute(Namespaces::$dcterms."modified", $modified);}
              
              foreach($contributors as $contributor)
              {
                if($contributor != "")
                {
                  $subject->setObjectAttribute(Namespaces::$dcterms."contributor", $contributor, null, "sioc:User");
                }
              }  
                
              $this->ws->rset->addSubject($subject);  
              $nbDatasets++;
            }
            
            unset($resultset);
          }
          
          if($this->ws->memcached_enabled)
          {
            $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_dataset_read_expire);
          }               
        }
        else
        {
          if($this->ws->memcached_enabled)
          {
            $key = $this->ws->generateCacheKey('dataset-read', array($this->ws->datasetUri));
            
            if($return = $this->ws->memcached->get($key))
            {
              $this->ws->setResultset($return);
              
              return;
            }
          }            
          
          $dataset = $this->ws->datasetUri;

          $query =
            "select ?title ?description ?creator ?created ?modified
                  from named <" . $this->ws->wsf_graph . "datasets/>
                  where
                  {
                    graph <" . $this->ws->wsf_graph
            . "datasets/>
                    {
                      <$dataset> a <http://rdfs.org/ns/void#Dataset> ;
                      <http://purl.org/dc/terms/created> ?created.
                      
                      OPTIONAL{<$dataset> <http://purl.org/dc/terms/title> ?title.} .
                      OPTIONAL{<$dataset> <http://purl.org/dc/terms/description> ?description.} .
                      OPTIONAL{<$dataset> <http://purl.org/dc/terms/creator> ?creator.} .
                      OPTIONAL{<$dataset> <http://purl.org/dc/terms/modified> ?modified.} .
                    }
                  } ORDER BY ?title";

          $resultset = @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
            array ('title', 'description', 'creator', 'created', 'modified'), FALSE));

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_301->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, odbc_errormsg(),
              $this->ws->errorMessenger->_301->level);

            return;
          }
          else
          {
            if(odbc_fetch_row($resultset))
            {
              $title = odbc_result($resultset, 1);
              $description = $this->ws->db->odbc_getPossibleLongResult($resultset, 2);
              $creator = odbc_result($resultset, 3);
              $created = odbc_result($resultset, 4);
              $modified = odbc_result($resultset, 5);

              unset($resultset);

              // Get all contributors (users that have CUD perissions over the dataset)
              $query =
                "select ?contributor 
                      from <" . $this->ws->wsf_graph
                . "datasets/>
                      where
                      {
                        <$dataset> a <http://rdfs.org/ns/void#Dataset> ;
                        <http://purl.org/dc/terms/contributor> ?contributor.
                      }";

              $resultset =
                @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
                  array( 'contributor' ), FALSE));

              $contributors = array();

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
              elseif(odbc_fetch_row($resultset))
              {
                array_push($contributors, odbc_result($resultset, 1));
              }
              
              $subject = new Subject($dataset);
              $subject->setType(Namespaces::$void."Dataset");
              
              if($title != ""){$subject->setDataAttribute(Namespaces::$dcterms."title", $title);}
              if($description != ""){$subject->setDataAttribute(Namespaces::$dcterms."description", $description);}
              if($creator != ""){$subject->setObjectAttribute(Namespaces::$dcterms."creator", $creator, null, "sioc:User");}
              if($created != ""){$subject->setDataAttribute(Namespaces::$dcterms."created", $created);}
              if($modified != ""){$subject->setDataAttribute(Namespaces::$dcterms."modified", $modified);}
              
              foreach($contributors as $contributor)
              {
                if($contributor != "")
                {
                  $subject->setObjectAttribute(Namespaces::$dcterms."contributor", $contributor, null, "sioc:User");
                }
              }  
                
              $this->ws->rset->addSubject($subject);  
              $nbDatasets++;
            }
          }
          
          if($this->ws->memcached_enabled)
          {
            $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_dataset_read_expire);
          }                 
        }
        
        if($nbDatasets == 0 && $this->ws->datasetUri != "all")
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt("This dataset doesn't exist in this WSF");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_304->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, "",
            $this->ws->errorMessenger->_304->level);
        }
      }      
    }
  }
?>
