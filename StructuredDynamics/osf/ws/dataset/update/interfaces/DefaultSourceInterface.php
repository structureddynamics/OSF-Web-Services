<?php
  
  namespace StructuredDynamics\osf\ws\dataset\update\interfaces; 
  
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
        // Note: here we can't create a single SPARUL query to update everything because if one of the clause is not existing in the "delete" pattern,
        //          then nothing will be updated. Also, the problem come from the fact that "OPTIONAL" clauses only happen at the level of the "where" clause
        //          and can't be used in the "delete" clause.

        // Updating the title if it exists in the description
        if($this->ws->datasetTitle != "")
        {

          $this->ws->sparql->query("delete from <" . $this->ws->wsf_graph . "datasets/>
                  { 
                    <".$this->ws->datasetUri."> <http://purl.org/dc/terms/title> ?datasetTitle .
                  }
                  where
                  {
                    graph <" . $this->ws->wsf_graph . "datasets/>
                    {
                      <".$this->ws->datasetUri."> a <http://rdfs.org/ns/void#Dataset> .
                      <".$this->ws->datasetUri."> <http://purl.org/dc/terms/title> ?datasetTitle .
                    }
                  }
                  " . ($this->ws->datasetTitle != "-delete-" ? "
                  insert into <" . $this->ws->wsf_graph . "datasets/>
                  {
                    <".$this->ws->datasetUri."> <http://purl.org/dc/terms/title> \"\"\"" . str_replace("'", "\'", $this->ws->datasetTitle) . "\"\"\" .
                  }" : ""));
        }

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

        // Updating the description if it exists in the description
        if($this->ws->description != "")
        {
          $this->ws->sparql->query("delete from <" . $this->ws->wsf_graph . "datasets/>
                  { 
                    <".$this->ws->datasetUri."> <http://purl.org/dc/terms/description> ?description .
                  }
                  where
                  {
                    graph <" . $this->ws->wsf_graph . "datasets/>
                    {
                      <".$this->ws->datasetUri."> a <http://rdfs.org/ns/void#Dataset> .
                      <".$this->ws->datasetUri."> <http://purl.org/dc/terms/description> ?description .
                    }
                  }
                  " . ($this->ws->description != "-delete-" ? "
                  insert into <" . $this->ws->wsf_graph . "datasets/>
                  {
                    <".$this->ws->datasetUri."> <http://purl.org/dc/terms/description> \"\"\"" . str_replace("'", "\'", $this->ws->description) . "\"\"\" .
                  }" : ""));
        }

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

        // Updating the modification date if it exists in the description
        if($this->ws->modified != "")
        {
          $this->ws->sparql->query("delete from <" . $this->ws->wsf_graph . "datasets/>
                  { 
                    <".$this->ws->datasetUri."> <http://purl.org/dc/terms/modified> ?modified .
                  }
                  where
                  {
                    graph <" . $this->ws->wsf_graph . "datasets/>
                    {
                      <".$this->ws->datasetUri."> a <http://rdfs.org/ns/void#Dataset> .
                      <".$this->ws->datasetUri."> <http://purl.org/dc/terms/modified> ?modified .
                    }
                  }
                  " . ($this->ws->modified != "-delete-" ? "
                  insert into <" . $this->ws->wsf_graph . "datasets/>
                  {
                    <".$this->ws->datasetUri."> <http://purl.org/dc/terms/modified> \"\"\"" . str_replace("'", "\'", $this->ws->modified) . "\"\"\" .
                  }" : ""));
        }

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_302->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_302->level);

          return;
        }

        // Updating the contributors list if it exists in the description
        if($this->ws->contributors != "")
        {
          $query = "delete from <" . $this->ws->wsf_graph . "datasets/>
                  { 
                    <".$this->ws->datasetUri."> <http://purl.org/dc/terms/contributor> ?contributor .
                  }
                  where
                  {
                    graph <"
            . $this->ws->wsf_graph
            . "datasets/>
                    {
                      <".$this->ws->datasetUri."> a <http://rdfs.org/ns/void#Dataset> .
                      <".$this->ws->datasetUri."> <http://purl.org/dc/terms/contributor> ?contributor .
                    }
                  }";

          if($this->ws->contributors != "-delete-")
          {
            $cons = array();

            if(strpos($this->ws->contributors, ";") !== FALSE)
            {
              $cons = explode(";", $this->ws->contributors);
            }

            $query .= "insert into <" . $this->ws->wsf_graph . "datasets/>
                    {";

            foreach($cons as $contributor)
            {
              $query .= "<".$this->ws->datasetUri."> <http://purl.org/dc/terms/contributor> <$contributor> .";
            }

            if(count($cons) == 0)
            {
              $query .= "<".$this->ws->datasetUri."> <http://purl.org/dc/terms/contributor> <".$this->ws->contributors."> .";
            }
            $query .= "}";
          }
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

          return;
        }
        
        // Invalidate caches
        if($this->ws->memcached_enabled)
        {
          $this->ws->invalidateCache('dataset-read');
          $this->ws->invalidateCache('dataset-read:all');
        }
      }      
    }
  }
?>
