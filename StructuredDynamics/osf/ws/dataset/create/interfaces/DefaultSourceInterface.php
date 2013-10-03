<?php
  
  namespace StructuredDynamics\osf\ws\dataset\create\interfaces; 
  
  use \StructuredDynamics\osf\framework\Namespaces;  
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  use \StructuredDynamics\osf\ws\auth\lister\AuthLister;
  use \StructuredDynamics\osf\ws\framework\ProcessorXML;
  
  
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
        $query = "insert into <" . $this->ws->wsf_graph . "datasets/>
                {
                  <" . $this->ws->datasetUri . "> a <http://rdfs.org/ns/void#Dataset> ;
                  " . ($this->ws->datasetTitle != "" ? "<http://purl.org/dc/terms/title> \"\"\"" . str_replace("'", "\'", $this->ws->datasetTitle) . "\"\"\" ; " : "") . "
                  " . ($this->ws->description != "" ? "<http://purl.org/dc/terms/description> \"\"\"" . str_replace("'", "\'", $this->ws->description) . "\"\"\" ; " : "") . "
                  " . ($this->ws->creator != "" ? "<http://purl.org/dc/terms/creator> <".$this->ws->creator."> ; " : "") . "
                  <http://purl.org/dc/terms/created> \"" . date("Y-n-j") . "\" .
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
      }
    }
  }
?>
