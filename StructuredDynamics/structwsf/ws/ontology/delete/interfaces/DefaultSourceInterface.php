<?php
  
  namespace StructuredDynamics\structwsf\ws\ontology\delete\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\crud\delete\CrudDelete;
  use \StructuredDynamics\structwsf\ws\dataset\delete;
  use \StructuredDynamics\structwsf\ws\framework\OWLOntology;
  use \StructuredDynamics\structwsf\ws\dataset\delete\DatasetDelete;
  use \Exception;
  
  class DefaultSourceInterface extends SourceInterface
  {
    public $OwlApiSession;    
    
    function __construct($webservice)
    {   
      parent::__construct($webservice);
    }
    
    /**
    * 
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    private function getOntologyReference()
    {
      try
      {
        $this->ws->ontology = new OWLOntology($this->ws->ontologyUri, $this->OwlApiSession, TRUE);
      }
      catch(Exception $e)
      {
        $this->ws->returnError(400, "Bad Request", "_300");
      }    
    }   
    
    /**
    * 
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    private function initiateOwlBridgeSession()
    {
      // Starts the OWLAPI process/bridge
      require_once($this->ws->owlapiBridgeURI);

      // Create the OWLAPI session object that could have been persisted on the OWLAPI instance.
      // Second param "false" => we re-use the pre-created session without destroying the previous one
      // third param "0" => it nevers timeout.
      if($this->OwlApiSession == null)
      {
        $this->OwlApiSession = java_session("OWLAPI", false, 0);
      }    
    }
    
    /**
    * 
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    private function isValid()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        $this->ws->validateQuery();

        // If the query is still valid
        if($this->ws->conneg->getStatus() == 200)
        {
          return(TRUE);
        }
      }
      
      return(FALSE);    
    }
    
    /**
    * 
    *   
    * @param mixed $uri
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    public function deleteClass($uri)
    {
      if($this->isValid())
      {
        if($uri == "")
        {
          $this->ws->returnError(400, "Bad Request", "_204");
          return;
        }
        
        $this->initiateOwlBridgeSession();

        $this->getOntologyReference();

        // Delete the OWLAPI class entity
        $this->ws->ontology->removeClass($uri);

        // Check to delete potential datasets that have been created within structWSF
        $crudDelete = new CrudDelete($uri, $this->ws->ontologyUri, $this->ws->registered_ip, $this->ws->requester_ip);

        $crudDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
          $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $crudDelete->process();

        if($crudDelete->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->ws->conneg->setStatus($crudDelete->pipeline_getResponseHeaderStatus());
          $this->ws->conneg->setStatusMsg($crudDelete->pipeline_getResponseHeaderStatusMsg());
          $this->ws->conneg->setStatusMsgExt($crudDelete->pipeline_getResponseHeaderStatusMsgExt());
          $this->ws->conneg->setError($crudDelete->pipeline_getError()->id,
            $crudDelete->pipeline_getError()->webservice, $crudDelete->pipeline_getError()->name,
            $crudDelete->pipeline_getError()->description, $crudDelete->pipeline_getError()->debugInfo,
            $crudDelete->pipeline_getError()->level);

          return;
        }

        // Update the name of the file of the ontology to mark it as "changed"
        $this->ws->ontology->addOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");    
      }
    }  
    
    /**
    * 
    *  
    * @param mixed $uri
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    public function deleteNamedIndividual($uri)
    {
      if($this->isValid())
      {
        if($uri == "")
        {
          $this->ws->returnError(400, "Bad Request", "_203");
          return;
        }  
    
        $this->initiateOwlBridgeSession();

        $this->getOntologyReference();      

        // Delete the OWLAPI named individual entity
        $this->ws->ontology->removeNamedIndividual($uri);

        // Check to delete potential datasets that have been created within structWSF
        $crudDelete =
          new CrudDelete($uri, $this->ws->ontologyUri, $this->ws->registered_ip, $this->ws->requester_ip);

        $crudDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
          $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $crudDelete->process();

        if($crudDelete->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->ws->conneg->setStatus($crudDelete->pipeline_getResponseHeaderStatus());
          $this->ws->conneg->setStatusMsg($crudDelete->pipeline_getResponseHeaderStatusMsg());
          $this->ws->conneg->setStatusMsgExt($crudDelete->pipeline_getResponseHeaderStatusMsgExt());
          $this->ws->conneg->setError($crudDelete->pipeline_getError()->id,
            $crudDelete->pipeline_getError()->webservice, $crudDelete->pipeline_getError()->name,
            $crudDelete->pipeline_getError()->description, $crudDelete->pipeline_getError()->debugInfo,
            $crudDelete->pipeline_getError()->level);

          return;
        }

        // Update the name of the file of the ontology to mark it as "changed"
        $this->ws->ontology->addOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");    
      }
    }  
    
    /**
    * 
    * 
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    public function deleteOntology()
    {
      if($this->isValid())
      {
        $this->initiateOwlBridgeSession();

        $this->getOntologyReference();

        // Delete the OWLAPI instance
        if($this->ws->ontology)
        {
          $this->ws->ontology->delete();
        }
        
        // Remove the holdOntology tag before deleting the ontology
        $query = "delete data from <" . $this->ws->wsf_graph . "datasets/>
                {
                  <" . $this->ws->ontologyUri . "> <http://purl.org/ontology/wsf#holdOntology> \"true\" .
                }";

        @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
          FALSE));    

        // Check to delete potential datasets that have been created within structWSF
        $datasetDelete = new DatasetDelete($this->ws->ontologyUri, $this->ws->registered_ip, $this->ws->requester_ip);

        $datasetDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
          $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $datasetDelete->process();

        if($datasetDelete->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->ws->conneg->setStatus($datasetDelete->pipeline_getResponseHeaderStatus());
          $this->ws->conneg->setStatusMsg($datasetDelete->pipeline_getResponseHeaderStatusMsg());
          $this->ws->conneg->setStatusMsgExt($datasetDelete->pipeline_getResponseHeaderStatusMsgExt());
          $this->ws->conneg->setError($datasetDelete->pipeline_getError()->id,
            $datasetDelete->pipeline_getError()->webservice, $datasetDelete->pipeline_getError()->name,
            $datasetDelete->pipeline_getError()->description, $datasetDelete->pipeline_getError()->debugInfo,
            $datasetDelete->pipeline_getError()->level);

          return;
        }
      }
    } 
    
    /**
    * 
    *  
    * @param mixed $uri
    *  
    * @author Frederick Giasson, Structured Dynamics LLC.
    */
    public function deleteProperty($uri)
    {
      if($this->isValid())
      {
        if($uri == "")
        {
          $this->ws->returnError(400, "Bad Request", "_202");
          return;
        }  
    
        $this->initiateOwlBridgeSession();

        $this->getOntologyReference();      

        // Delete the OWLAPI property entity
        $this->ws->ontology->removeProperty($uri);

        // Check to delete potential datasets that have been created within structWSF
        $crudDelete =
          new CrudDelete($uri, $this->ws->ontologyUri, $this->ws->registered_ip, $this->ws->requester_ip);

        $crudDelete->ws_conneg($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_ACCEPT_CHARSET'],
          $_SERVER['HTTP_ACCEPT_ENCODING'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $crudDelete->process();

        if($crudDelete->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->ws->conneg->setStatus($crudDelete->pipeline_getResponseHeaderStatus());
          $this->ws->conneg->setStatusMsg($crudDelete->pipeline_getResponseHeaderStatusMsg());
          $this->ws->conneg->setStatusMsgExt($crudDelete->pipeline_getResponseHeaderStatusMsgExt());
          $this->ws->conneg->setError($crudDelete->pipeline_getError()->id,
            $crudDelete->pipeline_getError()->webservice, $crudDelete->pipeline_getError()->name,
            $crudDelete->pipeline_getError()->description, $crudDelete->pipeline_getError()->debugInfo,
            $crudDelete->pipeline_getError()->level);

          return;
        }

        // Update the name of the file of the ontology to mark it as "changed"
        $this->ws->ontology->addOntologyAnnotation("http://purl.org/ontology/wsf#ontologyModified", "true");    
      }
    }       
    
    public function processInterface()
    {
    }
  }
?>
