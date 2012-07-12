<?php
  
  namespace StructuredDynamics\structwsf\ws\dataset\read\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\framework\Subject;  
  
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
        /*
          In the future, this single query should be used for that ALL purpose.
          There is currently a bug in virtuoso that doesnt return anything in the resultset (virtuoso v5.0.10)
          if one of the OPTIONAL pattern is not existing in the triple store (so, OPTIONAL doesn't work)
          
          sparql
          select * 
          from named </wsf/datasets/>
          from named </wsf/>
          where
          {
            graph </wsf/>
            {
              ?access <http://purl.org/ontology/wsf#registeredIP> "174.129.251.47::1" ;
              <http://purl.org/ontology/wsf#read> "True" ;
              <http://purl.org/ontology/wsf#datasetAccess> ?dataset .
            }
            
            graph </wsf/datasets/> 
            {
              ?dataset a <http://rdfs.org/ns/void#Dataset> ;
              <http://purl.org/dc/terms/created> ?created.
          
              OPTIONAL{?dataset <http://purl.org/dc/terms/title> ?title.}
              OPTIONAL{?dataset <http://purl.org/dc/terms/description> ?description.}
              OPTIONAL{?dataset <http://purl.org/dc/terms/creator> ?creator.}
              OPTIONAL{?dataset <http://purl.org/dc/terms/modified> ?modified.}
            }    
          };
        */

        $query = "";
        $nbDatasets = 0;
        
        if($this->ws->datasetUri == "all")
        {
          $query = "  select distinct ?dataset ?title ?description ?creator ?created ?modified ?contributor ?meta
                    from named <" . $this->ws->wsf_graph . ">
                    from named <" . $this->ws->wsf_graph . "datasets/>
                    where
                    {
                      graph <" . $this->ws->wsf_graph . ">
                      {
                        ?access <http://purl.org/ontology/wsf#registeredIP> ?ip ;
                              <http://purl.org/ontology/wsf#read> \"True\" ;
                        <http://purl.org/ontology/wsf#datasetAccess> ?dataset .
                        filter( str(?ip) = \"".$this->ws->registered_ip."\" or str(?ip) = \"0.0.0.0\") .
                      }
                      
                      graph <"
            . $this->ws->wsf_graph
            . "datasets/>
                      {
                        ?dataset a <http://rdfs.org/ns/void#Dataset> ;
                        <http://purl.org/dc/terms/created> ?created.
                    
                        OPTIONAL{?dataset <http://purl.org/ontology/wsf#meta> ?meta.}
                        OPTIONAL{?dataset <http://purl.org/dc/terms/title> ?title.}
                        OPTIONAL{?dataset <http://purl.org/dc/terms/description> ?description.}
                        OPTIONAL{?dataset <http://purl.org/dc/terms/modified> ?modified.}
                        OPTIONAL{?dataset <http://purl.org/dc/terms/contributor> ?contributor.}
                        OPTIONAL{?dataset <http://purl.org/dc/terms/creator> ?creator.}
                      }    
                    } ORDER BY ?title";

          $resultset = @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
            array ("dataset", "title", "description", "creator", "created", "modified", "contributor", "meta"), FALSE));

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
            $meta = "";

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
              $meta = odbc_result($resultset, 8);
            }

            $metaDescription = array();

            // We have to add the meta information if available
            /*
            if($meta != "" && $this->ws->addMeta == "true")
            {
              $query = "select ?p ?o (str(DATATYPE(?o))) as ?otype (LANG(?o)) as ?olang
                      from <" . $this->ws->wsf_graph . "datasets/>
                      where
                      {
                        <$meta> ?p ?o.
                      }";

              $resultset =
                @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
                  array ('p', 'o', 'otype', 'olang'), FALSE));

              $contributors = array();

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
              else
              {
                while(odbc_fetch_row($resultset))
                {
                  $predicate = odbc_result($resultset, 1);
                  $object = $this->ws->db->odbc_getPossibleLongResult($resultset, 2);
                  $otype = odbc_result($resultset, 3);
                  $olang = odbc_result($resultset, 4);

                  if(isset($metaDescription[$predicate]))
                  {
                    array_push($metaDescription[$predicate], $object);
                  }
                  else
                  {
                    $metaDescription[$predicate] = array( $object );

                    if($olang && $olang != "")
                    {
                      // If a language is defined for an object, we force its type to be xsd:string
                      $metaDescription[$predicate]["type"] = "http://www.w3.org/2001/XMLSchema#string";
                    }
                    else
                    {
                      $metaDescription[$predicate]["type"] = $otype;
                    }
                  }
                }
              }

              unset($resultset);
            }*/

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
        }
        else
        {
          $dataset = $this->ws->datasetUri;

          $query =
            "select ?title ?description ?creator ?created ?modified ?meta
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
                      OPTIONAL{<$dataset> <http://purl.org/ontology/wsf#meta> ?meta.} .
                    }
                  } ORDER BY ?title";

          $resultset = @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
            array ('title', 'description', 'creator', 'created', 'modified', 'meta'), FALSE));

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
              $meta = odbc_result($resultset, 6);

              unset($resultset);

              /*
              $metaDescription = array();

              // We have to add the meta information if available
              if($meta != "" && $this->ws->addMeta == "true")
              {
                $query = "select ?p ?o (str(DATATYPE(?o))) as ?otype (LANG(?o)) as ?olang
                        from <" . $this->ws->wsf_graph . "datasets/>
                        where
                        {
                          <$meta> ?p ?o.
                        }";

                $resultset =
                  @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
                    array ('p', 'o', 'otype', 'olang'), FALSE));

                $contributors = array();

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
                else
                {
                  while(odbc_fetch_row($resultset))
                  {
                    $predicate = odbc_result($resultset, 1);
                    $object = $this->ws->db->odbc_getPossibleLongResult($resultset, 2);
                    $otype = odbc_result($resultset, 3);
                    $olang = odbc_result($resultset, 4);

                    if(isset($metaDescription[$predicate]))
                    {
                      array_push($metaDescription[$predicate], $object);
                    }
                    else
                    {
                      $metaDescription[$predicate] = array( $object );

                      if($olang && $olang != "")
                      {
                        // If a language is defined for an object, we force its type to be xsd:string 
                        $metaDescription[$predicate]["type"] = "http://www.w3.org/2001/XMLSchema#string";
                      }
                      else
                      {
                        $metaDescription[$predicate]["type"] = $otype;
                      }
                    }
                  }
                }

                unset($resultset);
              }*/


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
