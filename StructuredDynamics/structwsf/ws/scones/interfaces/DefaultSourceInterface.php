<?php
  
  namespace StructuredDynamics\structwsf\ws\scones\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \Exception;
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
    }
    

    /** Fix namespaces of the type of the tagged named entities      

        @author Frederick Giasson, Structured Dynamics LLC.
    */
    public function fixNamedEntitiesNamespaces()
    {
      $annotatedNeXML = new SimpleXMLElement($this->annotatedDocument);

      foreach($annotatedNeXML->xpath('//AnnotationSet') as $annotationSet) 
      {
        if((string) $annotationSet['Name'] == $this->config_ini["gate"]["neAnnotationSetName"])
        {
          foreach($annotationSet->Annotation as $annotation) 
          {
            foreach($annotation->Feature as $feature)
            {
              if((string) $feature->Name == "majorType")
              {
                $feature->Value = urldecode((string) $feature->Value);
              }
            }  
          }         
        }
      }
      
      $this->annotatedDocument = $annotatedNeXML->asXML();
    }    
    
    public function processInterface()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        // Check which instance is available right now
        $processed = FALSE;
        
        // Get accessible sessions (threads) from the running Scones instance
        while($processed === FALSE) // Continue until we get a free running thread
        {
          for($i = 1; $i <= $this->ws->config_ini["gate"]["nbSessions"]; $i++)
          {
            // Make sure the issued is not currently used by another user/process
            if(java_values($this->ws->SconesSession->get("session".$i."_used")) === FALSE)
            {
              $this->ws->SconesSession->put("session".$i."_used", TRUE);
              
              // Process the incoming article
              $corpus = $this->ws->SconesSession->get("session".$i."_instance")->getCorpus();

              $document;
              $gateFactory = java("gate.Factory");
              
              if($this->ws->isValidIRI($this->ws->document))            
              {
                // Create the Gate document from the URL
                $document = $gateFactory->newDocument(new java("java.net.URL", $this->ws->document));
              }
              else
              {
                // Create the Gate document from the text document
                $document = $gateFactory->newDocument(new java("java.lang.String", $this->ws->document));
              }
              
              // Create the corpus
              $corpus = $gateFactory->newCorpus(new java("java.lang.String", "Scones Corpus"));
              
              // Add the document to the corpus
              $corpus->add($document);
              
              // Add the corpus to the corpus controler (the application)
              $this->ws->SconesSession->get("session".$i."_instance")->setCorpus($corpus);
              
              // Execute the pipeline
              try 
              {
                $this->ws->SconesSession->get("session".$i."_instance")->execute();        
              } 
              catch (Exception $e) 
              {
                $this->ws->SconesSession->put("session".$i."_used", FALSE);
              }            
              
              // output the XML document
              $this->ws->annotatedDocument =  $document->toXML();
              
              // Empty the corpus
              $corpus->clear();
              
              // Stop the thread seeking process
              $processed = TRUE;
              
              // Liberate the thread for others to use
              $this->ws->SconesSession->put("session".$i."_used", FALSE);
              
              // Fix namespaces of the type of the tagged named entities
              $this->fixNamedEntitiesNamespaces();
              
              break;
            }
          }
          
          sleep(1);
        }      
      }     
    }
  }
?>
