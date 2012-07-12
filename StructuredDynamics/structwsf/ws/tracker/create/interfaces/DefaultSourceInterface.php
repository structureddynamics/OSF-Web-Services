<?php
  
  namespace StructuredDynamics\structwsf\ws\tracker\create\interfaces; 
  
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
        $this->ws->validateQuery();

        // If the query is still valid
        if($this->ws->conneg->getStatus() == 200)
        {
          $dateTime = date("c");

          /*
            Ordered changes for a record using sparql and this part of the WSF ontology.
          
            sparql select * from <http://.../wsf/track/> where 
            {
              ?s <http://purl.org/ontology/wsf#record> <http://.../wsf/datasets/67/resource/Welfare> .

              ?s <http://purl.org/ontology/wsf#changeTime> ?time.
            }
            ORDER BY asc(xsd:dateTime(?time));
          */
          
          $trackRecord = "<".$this->ws->wsf_graph."track/record/".md5($dateTime.$this->ws->record.$this->ws->fromDataset)."> 
                           a <http://purl.org/ontology/wsf#ChangeState> ;";
                           
          $trackRecord .= "<http://purl.org/ontology/wsf#record> <".$this->ws->record."> ;";
          $trackRecord .= "<http://purl.org/ontology/wsf#fromDataset> <".$this->ws->fromDataset."> ;";
          $trackRecord .= "<http://purl.org/ontology/wsf#changeTime> \"".$dateTime."\"^^xsd:dateTime ;";
          $trackRecord .= "<http://purl.org/ontology/wsf#action> \"".$this->ws->action."\" ;";
          $trackRecord .= "<http://purl.org/ontology/wsf#previousState> \"\"\"".$this->ws->previousState."\"\"\" ;";
          $trackRecord .= "<http://purl.org/ontology/wsf#previousStateMime> \"".$this->ws->previousStateMime."\" ;";
          $trackRecord .= "<http://purl.org/ontology/wsf#performer> \"".$this->ws->performer."\" .";
          
          $this->ws->db->query("DB.DBA.TTLP_MT('"
            . addslashes($trackRecord) . "', '" . $this->ws->wsf_graph."track/" . "', '"
            . $this->ws->wsf_graph."track/" . "')");

          if(odbc_error())
          {
            $this->ws->conneg->setStatus(400);
            $this->ws->conneg->setStatusMsg("Bad Request");
            $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, odbc_errormsg(),
              $this->ws->errorMessenger->_302->level);

            return;
          }
        }
      }      
    }
  }
?>
