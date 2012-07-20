<?php
  
  namespace StructuredDynamics\structwsf\ws\dataset\create\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\auth\registrar\access\AuthRegistrarAccess;
  use \StructuredDynamics\structwsf\ws\auth\lister\AuthLister;
  use \StructuredDynamics\structwsf\ws\framework\ProcessorXML;
  
  
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

        /* 
          If the dataset has been created, the next step is to create the permissions for this user (full crud)
          and for the public one (no crud).
        */

        /* Get the list of web services registered to this structWSF instance. */
        if(strtolower($this->ws->webservices) == "all")
        {      
          $ws_al = new AuthLister("ws", $this->ws->datasetUri, $this->ws->requester_ip, $this->ws->wsf_local_ip);

          $ws_al->pipeline_conneg($this->ws->conneg->getAccept(), $this->ws->conneg->getAcceptCharset(),
            $this->ws->conneg->getAcceptEncoding(), $this->ws->conneg->getAcceptLanguage());

          $ws_al->process();

          if($ws_al->pipeline_getResponseHeaderStatus() != 200)
          {
            $this->ws->conneg->setStatus($ws_al->pipeline_getResponseHeaderStatus());
            $this->ws->conneg->setStatusMsg($ws_al->pipeline_getResponseHeaderStatusMsg());
            $this->ws->conneg->setStatusMsgExt($ws_al->pipeline_getResponseHeaderStatusMsgExt());
            $this->ws->conneg->setError($ws_al->pipeline_getError()->id, $ws_al->pipeline_getError()->webservice,
              $ws_al->pipeline_getError()->name, $ws_al->pipeline_getError()->description,
              $ws_al->pipeline_getError()->debugInfo, $ws_al->pipeline_getError()->level);

            return;
          }

          /* Get all web services */

          $webservices = "";

          $xml = new ProcessorXML();
          $xml->loadXML($ws_al->pipeline_getResultset());

          $webServiceElements = $xml->getXPath('//predicate/object[attribute::type="wsf:WebService"]');

          foreach($webServiceElements as $element)
          {
            $webservices .= $xml->getURI($element) . ";";
          }

          $webservices = substr($webservices, 0, strlen($webservices) - 1);
          unset($xml);
          unset($ws_al);         
          
          $this->ws->webservices = $webservices;
        }



        /* Register full CRUD for the creator of the dataset, for the dataset's ID */

        $ws_ara = new AuthRegistrarAccess("True;True;True;True", $this->ws->webservices, $this->ws->datasetUri, "create", "",
          $this->ws->requester_ip, $this->ws->wsf_local_ip);

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

        unset($ws_ara);

        /* Register no CRUD for the public user, for the dataset's ID */

        $ws_ara =
          new AuthRegistrarAccess($this->ws->globalPermissions, $this->ws->webservices, $this->ws->datasetUri, "create", "", "0.0.0.0",
            $this->ws->wsf_local_ip);

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

        unset($ws_ara);
      }
    }
  }
?>
