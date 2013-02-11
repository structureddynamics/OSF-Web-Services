<?php
  
  namespace StructuredDynamics\structwsf\ws\dataset\delete\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\auth\registrar\access\AuthRegistrarAccess;
  use \StructuredDynamics\structwsf\ws\framework\Solr;
  
  
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
        // Make sure this is not an ontology dataset
        $query = "select ?holdOntology
                        from <" . $this->ws->wsf_graph . "datasets/>
                        where
                        {
                          <".$this->ws->datasetUri."> <http://purl.org/ontology/wsf#holdOntology> ?holdOntology .
                        }";

        $resultset =
          @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query),
            array ('holdOntology'), FALSE));

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
        else
        {
          while(odbc_fetch_row($resultset))
          {
            $holdOntology = odbc_result($resultset, 1);

            if(strtolower($holdOntology) == "true")
            {
              $this->ws->conneg->setStatus(400);
              $this->ws->conneg->setStatusMsg("Bad Request");
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, odbc_errormsg(),
                $this->ws->errorMessenger->_306->level);

              return;            
            }
          }
        }

        unset($resultset);      
        
        // Remove  all the possible other meta descriptions
        // of the dataset introduced by the wsf:meta property.

        $query = "  delete from <" . $this->ws->wsf_graph . "datasets/> 
                { 
                  ?meta ?p_meta ?o_meta.
                }
                where
                {
                  graph <"
          . $this->ws->wsf_graph
          . "datasets/>
                  {
                    <".$this->ws->datasetUri."> <http://purl.org/ontology/wsf#meta> ?meta.
                    ?meta ?p_meta ?o_meta.
                  }
                }";

        @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
          FALSE));

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


        // Remove the Graph description in the ".../datasets/"

        $query = "  delete from <" . $this->ws->wsf_graph . "datasets/> 
                { 
                  <".$this->ws->datasetUri."> ?p ?o.
                }
                where
                {
                  graph <" . $this->ws->wsf_graph . "datasets/>
                  {
                    <".$this->ws->datasetUri."> ?p ?o.
                  }
                }";

        @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
          FALSE));

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

        // Removing all accesses for this graph
        $ws_ara = new AuthRegistrarAccess("", "", $this->ws->datasetUri, "delete_all", "", "", $this->ws->registered_ip);

        $ws_ara->pipeline_conneg($this->ws->conneg->getAccept(), $this->ws->conneg->getAcceptCharset(),
          $this->ws->conneg->getAcceptEncoding(), $this->ws->conneg->getAcceptLanguage());

        $ws_ara->process();

        if($ws_ara->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->ws->conneg->setStatus($ws_ara->pipeline_getResponseHeaderStatus());
          $this->ws->conneg->setStatusMsg($ws_ara->pipeline_getResponseHeaderStatusMsg());
          $this->ws->conneg->setStatusMsgExt($ws_ara->pipeline_getResponseHeaderStatusMsgExt());
          $this->ws->conneg->setError($ws_ara->pipeline_getError()->id, $ws_ara->pipeline_getError()->webservice,
            $ws_ara->pipeline_getError()->name, $ws_ara->pipeline_getError()->description,
            $ws_ara->pipeline_getError()->debugInfo, $ws_ara->pipeline_getError()->level);
          return;
        }

        // Drop the entire graph
        $query = "sparql clear graph <" . $this->ws->datasetUri . ">";

        @$this->ws->db->query($query);

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

        // Drop the reification graph related to this dataset
        $query = "sparql clear graph <" . $this->ws->datasetUri . "reification/>";

        @$this->ws->db->query($query);

        if(odbc_error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt("Error #dataset-delete-105");
          $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, odbc_errormsg(),
            $this->ws->errorMessenger->_303->level);

          return;
        }


        // Remove all documents from the solr index for this Dataset
        $solr = new Solr($this->ws->wsf_solr_core, $this->ws->solr_host, $this->ws->solr_port, $this->ws->fields_index_folder);

        if(!$solr->flushDataset($this->ws->datasetUri))
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_304->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, odbc_errormsg(),
            $this->ws->errorMessenger->_304->level);

          return;
        }

        if($this->ws->solr_auto_commit === FALSE)
        {
          if(!$solr->commit())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, odbc_errormsg(),
              $this->ws->errorMessenger->_305->level);

            return;
          }
        }


      /*      
            if(!$solr->optimize())
            {
              $this->ws->conneg->setStatus(500);
              $this->ws->conneg->setStatusMsg("Internal Error");
              $this->ws->conneg->setStatusMsgExt("Error #dataset-delete-104");  
              return;          
            }      
      */
      }      
    }
  }
?>
