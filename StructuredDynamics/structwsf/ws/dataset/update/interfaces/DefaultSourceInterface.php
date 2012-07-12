<?php
  
  namespace StructuredDynamics\structwsf\ws\dataset\update\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  
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
        $query = "modify <".$this->ws->wsf_graph."datasets/>
                delete
                { 
                  ".($this->ws->datasetTitle != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle ." : "")."
                  ".($this->ws->description != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/description> ?description ." : "")."
                  ".($this->ws->modified != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/modified> ?modified ." : "")."
                  ".(count($this->ws->contributors) > 0 && isset($contributor[0]) ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/contributor> ?contributors ." : "")."
                }
                insert
                {
                  ".($this->ws->datasetTitle != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/title> \"\"\"$this->ws->datasetTitle\"\"\" ." : "")."
                  ".($this->ws->description != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/description> \"\"\"$this->ws->description\"\"\" ." : "")."
                  ".($this->ws->modified != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/modified> \"\"\"$this->ws->modified\"\"\" ." : "")."";
                  
        foreach($this->ws->contributors as $contributor)
        {
          $query .=   ($this->ws->contributor != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/contributor> <$contributor> ." : "");
        }                
                  
        $query .= "}                  
                where
                {
                  graph <".$this->ws->wsf_graph."datasets/>
                  {
                    <$this->ws->datasetUri> a <http://rdfs.org/ns/void#Dataset> .
                    ".($this->ws->datasetTitle != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/title> ?datasetTitle ." : "")."
                    ".($this->ws->description != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/description> ?description ." : "")."
                    ".($this->ws->modified != "" ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/modified> ?modified ." : "")."
                    ".(count($this->ws->contributors) > 0 ? "<$this->ws->datasetUri> <http://purl.org/dc/terms/contributor> ?contributors ." : "")."
                  }
                }";
  */

  // Note: here we can't create a single SPARUL query to update everything because if one of the clause is not existing in the "delete" pattern,
  //          then nothing will be updated. Also, the problem come from the fact that "OPTIONAL" clauses only happen at the level of the "where" clause
  //          and can't be used in the "delete" clause.

  // Updating the title if it exists in the description
        if($this->ws->datasetTitle != "")
        {

          $query = "delete from <" . $this->ws->wsf_graph . "datasets/>
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
                  }" : "");
        }

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

        // Updating the description if it exists in the description
        if($this->ws->description != "")
        {
          $query = "delete from <" . $this->ws->wsf_graph . "datasets/>
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
                  }" : "");
        }

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

        // Updating the modification date if it exists in the description
        if($this->ws->modified != "")
        {
          $query = "delete from <" . $this->ws->wsf_graph . "datasets/>
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
                  }" : "");
        }

        @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
          FALSE));

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

        @$this->ws->db->query($this->ws->db->build_sparql_query(str_replace(array ("\n", "\r", "\t"), " ", $query), array(),
          FALSE));

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
      }      
    }
  }
?>
