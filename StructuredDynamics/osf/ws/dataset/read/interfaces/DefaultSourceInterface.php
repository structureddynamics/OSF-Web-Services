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
                    
          $this->ws->sparql->query("prefix wsf: <http://purl.org/ontology/wsf#>
                    select distinct ?dataset ?title ?description ?creator ?created ?modified ?holdOntology ?contributor
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
                        OPTIONAL{?dataset <http://purl.org/ontology/wsf#holdOntology> ?holdOntology.}
                      }    
                    } ORDER BY ?title");

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
          else
          {
            $dataset = "";
            $title = "";
            $description = "";
            $creator = "";
            $created = "";
            $modified = "";
            $contributors = array();
            $holdOntology = "";

            while($this->ws->sparql->fetch_binding())
            {            
              $dataset2 = $this->ws->sparql->value('dataset');

              if($dataset2 != $dataset && $dataset != "")
              {
                $subject = new Subject($dataset);
                $subject->setType(Namespaces::$void."Dataset");
                
                if($title != ""){$subject->setDataAttribute(Namespaces::$dcterms."title", $title);}
                if($description != ""){$subject->setDataAttribute(Namespaces::$dcterms."description", $description);}
                if($creator != ""){$subject->setObjectAttribute(Namespaces::$dcterms."creator", $creator, null, "sioc:User");}
                if($created != ""){$subject->setDataAttribute(Namespaces::$dcterms."created", $created);}
                if($modified != ""){$subject->setDataAttribute(Namespaces::$dcterms."modified", $modified);}
                if($holdOntology != ""){$subject->setDataAttribute(Namespaces::$wsf."holdOntology", $holdOntology);}
                
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

              $title = $this->ws->sparql->value('title');
              $description = $this->ws->sparql->value('description');

              $creator = $this->ws->sparql->value('creator');
              $created = $this->ws->sparql->value('created');
              $modified = $this->ws->sparql->value('modified');
              $holdOntology = $this->ws->sparql->value('holdOntology');
              
              if(empty($holdOntology))
              {
                $holdOntology = 'false';
              }
              
              array_push($contributors, $this->ws->sparql->value('contributor'));
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
              if($holdOntology != ""){$subject->setDataAttribute(Namespaces::$wsf."holdOntology", $holdOntology);}
              
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

          $this->ws->sparql->query("select ?title ?description ?creator ?created ?modified ?holdOntology
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
                      OPTIONAL{<$dataset> <http://purl.org/ontology/wsf#holdOntology> ?holdOntology.}
                    }
                  } ORDER BY ?title");

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
          else
          {
            if($this->ws->sparql->fetch_binding())
            {
              $title = $this->ws->sparql->value('title');
              $description = $this->ws->sparql->value('description');
              $creator = $this->ws->sparql->value('creator');
              $created = $this->ws->sparql->value('created');
              $modified = $this->ws->sparql->value('modified');
              $holdOntology = $this->ws->sparql->value('holdOntology');

              if(empty($holdOntology))
              {
                $holdOntology = 'false';
              }              
              
              // Get all contributors (users that have CUD perissions over the dataset)
              $this->ws->sparql->query("select ?contributor 
                      from <" . $this->ws->wsf_graph . "datasets/>
                      where
                      {
                        <$dataset> a <http://rdfs.org/ns/void#Dataset> ;
                        <http://purl.org/dc/terms/contributor> ?contributor.
                      }");

              $contributors = array();

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
                while($this->ws->sparql->fetch_binding())
                {            
                  array_push($contributors, $this->ws->sparql->value('contributor'));
                }
              }
              
              $subject = new Subject($dataset);
              $subject->setType(Namespaces::$void."Dataset");
              
              if($title != ""){$subject->setDataAttribute(Namespaces::$dcterms."title", $title);}
              if($description != ""){$subject->setDataAttribute(Namespaces::$dcterms."description", $description);}
              if($creator != ""){$subject->setObjectAttribute(Namespaces::$dcterms."creator", $creator, null, "sioc:User");}
              if($created != ""){$subject->setDataAttribute(Namespaces::$dcterms."created", $created);}
              if($modified != ""){$subject->setDataAttribute(Namespaces::$dcterms."modified", $modified);}
              if($holdOntology != ""){$subject->setDataAttribute(Namespaces::$wsf."holdOntology", $holdOntology);}
              
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
