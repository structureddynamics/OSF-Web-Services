<?php
  
  namespace StructuredDynamics\structwsf\ws\ontology\read\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\framework\OWLOntology;
  use \StructuredDynamics\structwsf\framework\Subject;
  use \StructuredDynamics\structwsf\ws\framework\ClassHierarchy;
  use \StructuredDynamics\structwsf\ws\framework\PropertyHierarchy;
  use \Exception;  
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "1.0";
    }
    
    private function generationSerializedClassHierarchy($OwlApiSession)
    {
      $ontologiesUri = OWLOntology::getLoadedOntologiesUri($OwlApiSession);
      
      $classHierarchy = new ClassHierarchy("http://www.w3.org/2002/07/owl#Thing");
      
      foreach($ontologiesUri as $ontologyUri)
      {
        $onto = new OWLOntology($ontologyUri, $OwlApiSession, TRUE);
        
        $onto->setLanguage($this->ws->lang);
        
        if(strtolower($this->ws->owlapiReasoner) == "pellet")                        
        {
          $onto->usePelletReasoner();
        }
        elseif(strtolower($this->ws->owlapiReasoner) == "hermit")
        {
          $onto->useHermitReasoner();
        }
        elseif(strtolower($this->ws->owlapiReasoner) == "factpp")
        {
          $onto->useFactppReasoner();
        }        
        
        $this->populateClassHierarchy("http://www.w3.org/2002/07/owl#Thing", $onto, $ontologyUri, $classHierarchy);
      }  
      
      return(serialize($classHierarchy));
    }    
    
    private function generationSerializedPropertyHierarchy($OwlApiSession)
    {
      $ontologiesUri = OWLOntology::getLoadedOntologiesUri($OwlApiSession);

      $propertyHierarchy = new PropertyHierarchy("http://www.w3.org/2002/07/owl#Thing");
      
      foreach($ontologiesUri as $ontologyUri)
      {
        $onto = new OWLOntology($ontologyUri, $OwlApiSession, TRUE);
        
        $onto->setLanguage($this->ws->lang);
        
        if(strtolower($this->ws->owlapiReasoner) == "pellet")                        
        {
          $onto->usePelletReasoner();
        }
        elseif(strtolower($this->ws->owlapiReasoner) == "hermit")
        {
          $onto->useHermitReasoner();
        }
        elseif(strtolower($this->ws->owlapiReasoner) == "factpp")
        {
          $onto->useFactppReasoner();
        }
        
        $this->populatePropertyHierarchy("http://www.w3.org/2002/07/owl#topObjectProperty", $onto, $ontologyUri, $propertyHierarchy, FALSE);
        $this->populatePropertyHierarchy("http://www.w3.org/2002/07/owl#topDataProperty", $onto, $ontologyUri, $propertyHierarchy, TRUE);
      }  
                  
      return(serialize($propertyHierarchy));
    }     
    
    private function populateClassHierarchy($parentClass, $ontology, $ontologyUri, &$classHierarchy)
    {
      $subClasses = $ontology->getSubClassesDescription($parentClass, TRUE);

      if(isset($subClasses[Namespaces::$owl."Nothing"]))
      {
        return;
      }
      
      foreach($subClasses as $subClass => $description)
      {
        $classHierarchy->addClassRelationship($subClass, $parentClass);

        $classHierarchy->classes[$subClass]->label = preg_replace('/\s\s+/', ' ', str_replace(array("\r", "\n"), "", $this->getLabel($subClass, $description))); 
        $classHierarchy->classes[$subClass]->description = preg_replace('/\s\s+/', ' ', str_replace(array("\r", "\n"), "", $this->getDescription($description))); 
        
        $classHierarchy->classes[$subClass]->isDefinedBy = $ontologyUri;
        
        // Dig into the structure...
        $this->populateClassHierarchy($subClass, $ontology, $ontologyUri, $classHierarchy);
      }
    }    
    
    private function returnError($statusCode, $statusMsg, $wsErrorCode, $debugInfo = "")
    {
      $this->ws->conneg->setStatus($statusCode);
      $this->ws->conneg->setStatusMsg($statusMsg);
      $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->{$wsErrorCode}->name);
      $this->ws->conneg->setError($this->ws->errorMessenger->{$wsErrorCode}->id, $this->ws->errorMessenger->ws,
        $this->ws->errorMessenger->{$wsErrorCode}->name, $this->ws->errorMessenger->{$wsErrorCode}->description, $debugInfo,
        $this->ws->errorMessenger->{$wsErrorCode}->level);
    }    

    public function getDescription($description)
    {
      if(isset($description[Namespaces::$iron . "description"]))
      {               
        return $description[Namespaces::$iron . "description"][0]["value"];
      }

      if(isset($description[Namespaces::$skos_2008 . "definition"]))
      {
        return $description[Namespaces::$skos_2008 . "definition"][0]["value"];
      }

      if(isset($description[Namespaces::$skos_2004 . "definition"]))
      {
        return $description[Namespaces::$skos_2004 . "definition"][0]["value"];
      }

      if(isset($description[Namespaces::$rdfs . "comment"]))
      {
        return $description[Namespaces::$rdfs . "comment"][0]["value"];
      }

      if(isset($description[Namespaces::$dcterms . "description"]))
      {
        return $description[Namespaces::$dcterms . "description"][0]["value"];
      }

      if(isset($description[Namespaces::$dc . "description"]))
      {
        return $description[Namespaces::$dc . "description"][0]["value"];
      }

      return "No description available";
    }
    
    public function getLabel($uri, $description)  
    {
      $prefLabelAttributes = array(
        Namespaces::$rdfs."label",
        Namespaces::$skos_2004."prefLabel",
        Namespaces::$skos_2008."prefLabel",
        Namespaces::$umbel."prefLabel",
        Namespaces::$dcterms."title",
        Namespaces::$dc."title",
        Namespaces::$iron."prefLabel",
        Namespaces::$skos_2004."altLabel",
        Namespaces::$skos_2008."altLabel",
        Namespaces::$umbel."altLabel",
        Namespaces::$iron."altLabel"
      );
      
      foreach($prefLabelAttributes as $attribute)
      {
        if(isset($description[$attribute]))
        {
          return($description[$attribute][0]["value"]);
        }
      }

      // Find the base URI of the ontology
      $pos = strripos($uri, "#");

      if($pos === FALSE)
      {
        $pos = strripos($uri, "/");
      }

      if($pos !== FALSE)
      {
        $pos++;
      }

      $resource = substr($uri, $pos, strlen($uri) - $pos);

      return $resource;
    }     

    public function ironPrefixize($uri, &$prefixes)
    {
      foreach($prefixes as $prefix => $u)
      {
        if(stripos($uri, $u) !== FALSE)
        {
          return(str_replace($u, $prefix."_", $uri));
        }
      }
      
      return($uri);
    } 
    
    public function manageIronPrefixes($uri, &$prefixes)
    {
      if(strrpos($uri, "#") !== FALSE)
      {
        $p = substr($uri, strrpos($uri, "/") + 1, strrpos($uri, "#") - (strrpos($uri, "/") + 1));
        
        $p = preg_replace("/[^A-Za-z0-9]/", "-", $p);
        
        if(!isset($prefixes[$p]))
        {
          // Check if the first character is numeric. If it is, 
          // we have to use a non-numeric character first.
          if(is_numeric(substr($p, 0, 1)))
          {
            $p = "sd".$p;
          }
          
          $baseUri = substr($uri, 0, strrpos($uri, "#") + 1);
        
          if(!isset($prefixes[$p]))
          {
            $prefixes[$p] = $baseUri;
          }
          else
          {
            // Check to make sure that the $baseUri is already defined for this prefix.
            // otherwise it means that two different ontology were trying to use the
            // same prefix.
            
            // Make sure the base uri doesn't already have another prefix
            if(array_search($baseUri, $prefixes) === FALSE)
            {
              // Find a new distinct prefix for the baseUri.
              if($prefixes[$p] != $baseUri)
              {
                for($i = 0; $i < 256; $i++)
                {
                  if(!isset($prefixes[$p.$i]))
                  {
                    $p = $p.$i;
                    
                    $prefixes[$p] = $baseUri;
                    
                    break;
                  }
                }                        
              }
            }
          }                    
        }
      }
      elseif(strrpos($uri, "/") !== FALSE)
      {
        // http://www.agls.gov.au/agls/terms/availability
        
        $uriMod = substr($uri, 0, strrpos($uri, "/", strrpos($uri, "/")));
        
        $p = substr($uriMod, strrpos($uriMod, "/") + 1);

        $p = preg_replace("/[^A-Za-z0-9]/", "-", $p);

        // Check if the first character is numeric. If it is, 
        // we have to use a non-numeric character first.
        if(is_numeric(substr($p, 0, 1)))
        {
          $p = "sd".$p;
        }
        
        $baseUri = substr($uri, 0, strrpos($uri, "/", strrpos($uri, "/")) + 1);
        
        if(!isset($prefixes[$p]))
        {
          $prefixes[$p] = $baseUri;
        }
        else
        {
          // Check to make sure that the $baseUri is already defined for this prefix.
          // otherwise it means that two different ontology were trying to use the
          // same prefix.
          
          // Make sure the base uri doesn't already have another prefix
          if(array_search($baseUri, $prefixes) === FALSE)
          {
            // Find a new distinct prefix for the baseUri.
            if($prefixes[$p] != $baseUri)
            {
              for($i = 0; $i < 256; $i++)
              {
                if(!isset($prefixes[$p.$i]))
                {
                  $p = $p.$i;
                  
                  $prefixes[$p] = $baseUri;
                  
                  break;
                }
              }                        
            }
          }
        }
      }
    }     
      
    private function populatePropertyHierarchy($parentProperty, $ontology, $ontologyUri, &$propertyHierarchy, $isDataProperty)
    {
      $subProperties = $ontology->getSubPropertiesDescription($parentProperty, TRUE, $isDataProperty);

      foreach($subProperties as $subProperty => $description)
      {
        $propertyHierarchy->addPropertyRelationship($subProperty, $parentProperty);

        $propertyHierarchy->properties[$subProperty]->label = preg_replace('/\s\s+/', ' ', str_replace(array("\r", "\n"), "", $this->getLabel($subProperty, $description))); 
        $propertyHierarchy->properties[$subProperty]->description = preg_replace('/\s\s+/', ' ', str_replace(array("\r", "\n"), "", $this->getDescription($description))); 
        
        $propertyHierarchy->properties[$subProperty]->isDefinedBy = $ontologyUri;
        
        // Add in-domain-of
        $domainClasses = array();
        if(isset($description[Namespaces::$rdfs."domain"]))
        {
          foreach($description[Namespaces::$rdfs."domain"] as $domain)
          {
            array_push($rangeClasses, $domain["uri"]);
            
            array_push($domainClasses, $ontology->getSubClassesUri($domain["uri"], FALSE));
          }
        }
        
        $domainClasses = array_unique($domainClasses);
        
        $propertyHierarchy->properties[$subProperty]->domain = $domainClasses;
        
        // Add in-range-of
        $rangeClasses = array();
        if(isset($description[Namespaces::$rdfs."range"]))
        {
          foreach($description[Namespaces::$rdfs."range"] as $range)
          {
            array_push($rangeClasses, $range["uri"]);
            
            array_push($rangeClasses, $ontology->getSubClassesUri($range["uri"], FALSE));            
          }
        }
             
        $rangeClasses = array_unique($rangeClasses);
        
        $propertyHierarchy->properties[$subProperty]->range = $rangeClasses;
        
        // Add minimum cardinality
        if(isset($description[Namespaces::$sco."minCardinality"]))
        {
          $propertyHierarchy->properties[$subProperty]->minCardinality = $description[Namespaces::$sco."minCardinality"][0]["value"];
        }
        
        // Add maximum cardinality
        if(isset($description[Namespaces::$sco."maxCardinality"]))
        {
          $propertyHierarchy->properties[$subProperty]->maxCardinality = $description[Namespaces::$sco."maxCardinality"][0]["value"];
        }
        
        // Add absolute cardinality
        if(isset($description[Namespaces::$sco."cardinality"]))
        {
          $propertyHierarchy->properties[$subProperty]->cardinality = $description[Namespaces::$sco."cardinality"][0]["value"];
        }
        
        // Dig into the structure...
        $this->populatePropertyHierarchy($subProperty, $ontology, $ontologyUri, $propertyHierarchy, $isDataProperty);
      }
    }    
    
    public function processInterface()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        // Starts the OWLAPI process/bridge  
        require_once($this->ws->owlapiBridgeURI);
        
        // Create the OWLAPI session object that could have been persisted on the OWLAPI instance.
        // Second param "false" => we re-use the pre-created session without destroying the previous one
        // third param "0" => it nevers timeout.
        if($this->ws->OwlApiSession == null)
        {
          $this->ws->OwlApiSession = java_session("OWLAPI", false, 0);      
        }      

        $ontology;
        
        try
        {
          $ontology = new OWLOntology($this->ws->ontologyUri, $this->ws->OwlApiSession, TRUE);   
          
          $ontology->setLanguage($this->ws->lang);
          
          if(strtolower($this->ws->owlapiReasoner) == "pellet")                        
          {
            $ontology->usePelletReasoner();
          }
          elseif(strtolower($this->ws->owlapiReasoner) == "hermit")
          {
            $ontology->useHermitReasoner();
          }
          elseif(strtolower($this->ws->owlapiReasoner) == "factpp")
          {
            $ontology->useFactppReasoner();
          }
          
        }
        catch(Exception $e)
        {
          if(strtolower($this->ws->function) != "getserializedclasshierarchy" &&
             strtolower($this->ws->function) != "getserializedpropertyhierarchy" &&
             strtolower($this->ws->function) != "getironxmlschema" &&
             strtolower($this->ws->function) != "getironjsonschema" &&
             strtolower($this->ws->function) != "getloadedontologies")
          {        
            $this->returnError(400, "Bad Request", "_300");
            return;
          }
        }
         
        if(isset($ontology))
        {                  
          if($this->ws->useReasoner)
          {   
            $ontology->useReasoner();
          }
          else
          {
            $ontology->stopUsingReasoner();
          }
        }
        
        if(isset($this->ws->parameters["direct"]) && $this->ws->parameters["direct"] != "")
        {
          $this->ws->parameters["direct"] = strtolower($this->ws->parameters["direct"]);
          
          if($this->ws->parameters["direct"] == "false" ||
             $this->ws->parameters["direct"] == "0" ||
             $this->ws->parameters["direct"] == "off")
           {
             $this->ws->parameters["direct"] = false;
           }
           else
           {
             $this->ws->parameters["direct"] = true;
           }
        }

        switch(strtolower($this->ws->function))
        {
          case "getserialized":
            $this->ws->conneg->setStatus(200);
            $this->ws->conneg->setStatusMsg("OK");        
            $this->ws->getSerialized = $ontology->getSerialization();        
            return;
          break;
          
          case "getclass":
            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }

            $class = $ontology->_getClass($this->ws->parameters["uri"]);
            
            if($class == null)
            {
              $this->returnError(400, "Bad Request", "_205"); 
            }
            else
            {
              $subject = new Subject($this->ws->parameters["uri"]);
              $subject->setSubject($ontology->_getClassDescription($class));
              $this->ws->rset->addSubject($subject);
            }          
          break;
          
          case "getclasses":

            $limit = -1;
            $offset = 0;
            
            if(isset($this->ws->parameters["limit"]))
            {
              $limit = $this->ws->parameters["limit"];
            }
            
            if(isset($this->ws->parameters["offset"]))
            {
              $offset = $this->ws->parameters["offset"];
            }          
            
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
              
                $classes = $ontology->getClassesUri($limit, $offset);
               
                foreach($classes as $class)
                {
                  $subject = new Subject($class);
                  $subject->setType("owl:Class");
                  $this->ws->rset->addSubject($subject);                  
                }
              break;
              
              case "descriptions":
                $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getClassesDescription($limit, $offset)));
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;            
            }
            
          break;
          
          case "getnamedindividual":
            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }
            
            $namedIndividual = $ontology->_getNamedIndividual($this->ws->parameters["uri"]);
            
            if($namedIndividual == null)
            {
              $this->returnError(400, "Bad Request", "_206"); 
            }
            else
            {          
              $subject = new Subject($this->ws->parameters["uri"]);
              $subject->setSubject($ontology->_getNamedIndividualDescription($namedIndividual));
              $this->ws->rset->addSubject($subject);
            }
          break;        
          
          case "getnamedindividuals":

            $limit = -1;
            $offset = 0;
            
            $direct = true;
            
            if(isset($this->ws->parameters["limit"]))
            {
              $limit = $this->ws->parameters["limit"];
            }
            
            if(isset($this->ws->parameters["offset"]))
            {
              $offset = $this->ws->parameters["offset"];
            }

            if(isset($this->ws->parameters["direct"]))
            {
              switch($this->ws->parameters["direct"])
              {
                case "0":
                  $direct = false;
                break;
                case "1":
                  $direct = true;
                break;
              }
            }
            
            $classUri = "all";
            
            if(!isset($this->ws->parameters["classuri"]))
            {
              $classUri = $this->ws->parameters["classuri"];
            }          
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                $namedindividuals = $ontology->getNamedIndividualsUri($classUri, $direct, $limit, $offset);
               
                foreach($namedindividuals as $ni)
                {
                  $subject = new Subject($ni);
                  $subject->setType("owl:NamedIndividual");
                  $this->ws->rset->addSubject($subject);                  
                }
              break;
              
              case "descriptions":
                $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getNamedIndividualsDescription($classUri, $direct, $limit, $offset)));            
              break;
              
              case "list":
                $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getNamedIndividualsDescription($classUri, $direct, $limit, $offset, TRUE)));            
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201", "Mode provided: ".$this->ws->parameters["mode"]);
                return;
              break;            
            }
            
          break;
          
          case "getsubclasses":

            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }

            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                $classes = $ontology->getSubClassesUri($this->ws->parameters["uri"], $this->ws->parameters["direct"]);
                
                foreach($classes as $class)
                {
                  $subject = new Subject($class);
                  $subject->setType("owl:Class");
                  $this->ws->rset->addSubject($subject);                  
                }
              break;
              
              case "descriptions":
                $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getSubClassesDescription($this->ws->parameters["uri"], $this->ws->parameters["direct"])));            
              break;

              case "hierarchy":
                $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getSubClassesDescription($this->ws->parameters["uri"], TRUE, TRUE)));
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;
            }
            
          break;
          
          case "getsuperclasses":

            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                $classes = $ontology->getSuperClassesUri($this->ws->parameters["uri"], $this->ws->parameters["direct"]);
                
                foreach($classes as $class)
                {
                  $subject = new Subject($class);
                  $subject->setType("owl:Class");
                  $this->ws->rset->addSubject($subject);                  
                }
              break;
              
              case "descriptions":
                $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getSuperClassesDescription($this->ws->parameters["uri"], $this->ws->parameters["direct"])));
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;
            }
            
          break;        
          
          case "getequivalentclasses":

            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                $classes = $ontology->getEquivalentClassesUri($this->ws->parameters["uri"]);
                
                foreach($classes as $class)
                {
                  $subject = new Subject($class);
                  $subject->setType("owl:Class");
                  $this->ws->rset->addSubject($subject);                  
                }
              break;
              
              case "descriptions":
                $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getEquivalentClassesDescription($this->ws->parameters["uri"])));
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;
            }
            
          break;                
                  
          case "getdisjointclasses":

            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                $classes = $ontology->getDisjointClassesUri($this->ws->parameters["uri"]);
                
                foreach($classes as $class)
                {
                  $subject = new Subject($class);
                  $subject->setType("owl:Class");
                  $this->ws->rset->addSubject($subject);                  
                }
              break;
              
              case "descriptions":
                $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getDisjointClassesDescription($this->ws->parameters["uri"])));
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;
            }
            
          break;  
          
          case "getontologies":
         
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                $ontologies = $ontology->getOntologiesUri();
                
                foreach($ontologies as $ontology)
                {
                  $subject = new Subject($ontology);
                  $subject->setType("owl:Ontology");
                  $this->ws->rset->addSubject($subject);                  
                }
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;
            }
            
          break;        
          
          case "getloadedontologies":
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                $ontologies = OWLOntology::getLoadedOntologiesUri($this->ws->OwlApiSession);
                
                foreach($ontologies as $ontology)
                {
                  $subject = new Subject($ontology);
                  $subject->setType("owl:Ontology");
                  $this->ws->rset->addSubject($subject);                  
                }
              break;
              
              case "descriptions":
                $this->ws->rset->setResultset(Array($this->ws->ontologyUri => OWLOntology::getLoadedOntologiesDescription($this->ws->OwlApiSession)));            
              break;
              
              default:
                $this->ws->conneg->setStatus(400);
                $this->ws->conneg->setStatusMsg("Bad Request");
                $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_201->name);
                $this->ws->conneg->setError($this->ws->errorMessenger->_201->id, $this->ws->errorMessenger->ws,
                  $this->ws->errorMessenger->_201->name, $this->ws->errorMessenger->_201->description, "",
                  $this->ws->errorMessenger->_201->level);
                return;
              break;
            }
            
          break;
          
          case "getserializedclasshierarchy":
            $sch = $this->generationSerializedClassHierarchy($this->ws->OwlApiSession);

            $subject = new Subject($this->ws->ontologyUri);
            $subject->setType("owl:Ontology");
            $subject->setDataAttribute(Namespaces::$wsf."serializedClassHierarchy", $sch);
            $this->ws->rset->addSubject($subject);
            
          break;
          
          case "getserializedpropertyhierarchy":
            $sch = $this->generationSerializedPropertyHierarchy($this->ws->OwlApiSession);

            $subject = new Subject($this->ws->ontologyUri);
            $subject->setType("owl:Ontology");
            $subject->setDataAttribute(Namespaces::$wsf."serializedPropertyHierarchy", $sch);
            $this->ws->rset->addSubject($subject);          
                        
          break;
          
          case "getironxmlschema":
            $subjectTriples = $ontology->getClassesDescription($limit, $offset);
            
            $schema = '<schema><version>0.1</version><typeList>';
            
            $prefixes = array();
            
            foreach($subjectTriples as $uri => $subject)
            {
              $this->manageIronPrefixes($uri, $prefixes);
              
              $schema .= "<".$this->ironPrefixize($uri, $prefixes).">";

              $schema .= "<description>".$this->ws->xmlEncode($this->getDescription($subject))."</description>";
              $schema .= "<prefLabel>".$this->ws->xmlEncode($this->getLabel($uri, $subject))."</prefLabel>";
              
              foreach($subject as $predicate => $values)
              {
                foreach($values as $value)
                {
                  switch($predicate)
                  {
                    case Namespaces::$rdfs."subClassOf":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<subTypeOf>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</subTypeOf>";
                    break;
                    
                    case Namespaces::$sco."displayControl":
                      
                      if(isset($value["uri"]))
                      {
                        $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $displayControl = $value["value"];
                      }
                      
                      $schema .= "<displayControl>".$this->ws->xmlEncode($displayControl)."</displayControl>";
                    break;

                    
                    case Namespaces::$sco."ignoredBy":
                      
                      if(isset($value["uri"]))
                      {
                        $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $ignoredBy = $value["value"];
                      }
                      
                      $schema .= "<ignoredBy>".$this->ws->xmlEncode($ignoredBy)."</ignoredBy>";
                    break;
                    
                    case Namespaces::$sco."shortLabel":
                      $schema .= "<shortLabel>".$this->ws->xmlEncode($value["value"])."</shortLabel>";
                    break;
                    
                    case Namespaces::$sco."mapMarkerImageUrl":
                      $schema .= "<mapMarkerImageUrl>".$this->ws->xmlEncode($value["value"])."</mapMarkerImageUrl>";
                    break;
                    
                    case Namespaces::$sco."relationBrowserNodeType":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<relationBrowserNodeType>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</relationBrowserNodeType>";
                    break;
                  }              
                }
              }
              
              $schema .= "</".$this->ironPrefixize($uri, $prefixes).">";            
            }
            
            $schema .= "</typeList>";
            $schema .= "<attributeList>";

            $subjectTriples = $ontology->getPropertiesDescription(TRUE);

            foreach($subjectTriples as $uri => $subject)
            {
              $this->manageIronPrefixes($uri, $prefixes);
              
              $schema .= "<".$this->ironPrefixize($uri, $prefixes).">";

              $schema .= "<description>".$this->ws->xmlEncode($this->getDescription($subject))."</description>";
              $schema .= "<prefLabel>".$this->ws->xmlEncode($this->getLabel($uri, $subject))."</prefLabel>";
              
              foreach($subject as $predicate => $values)
              {
                foreach($values as $value)
                {
                  switch($predicate)
                  {
                    case Namespaces::$rdfs."domain":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<allowedType>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</allowedType>";
                    break;
                    
                    case Namespaces::$sco."displayControl":
                    
                      if(isset($value["uri"]))
                      {
                        $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $displayControl = $value["value"];
                      }
                      
                      $schema .= "<displayControl>".$this->ws->xmlEncode($displayControl)."</displayControl>";
                    break;

                    case Namespaces::$sco."ignoredBy":
                    
                      if(isset($value["uri"]))
                      {
                        $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $ignoredBy = $value["value"];
                      }
                      
                      $schema .= "<ignoredBy>".$this->ws->xmlEncode($ignoredBy)."</ignoredBy>";
                    break;

                    case Namespaces::$sco."comparableWith":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<comparableWith>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</comparableWith>";
                    break;

                    case Namespaces::$sco."unitType":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<unitType>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</unitType>";
                    break;
                    
                    case Namespaces::$sco."shortLabel":
                      $schema .= "<shortLabel>".$this->ws->xmlEncode($value["value"])."</shortLabel>";
                    break;
                    
                    case Namespaces::$sco."minCardinality":
                      $schema .= "<minCardinality>".$this->ws->xmlEncode($value["value"])."</minCardinality>";
                    break;
                    
                    case Namespaces::$sco."maxCardinality":
                      $schema .= "<maxCardinality>".$this->ws->xmlEncode($value["value"])."</maxCardinality>";
                    break;
                                        
                    case Namespaces::$sco."cardinality":
                      $schema .= "<cardinality>".$this->ws->xmlEncode($value["value"])."</cardinality>";
                    break;
                    
                    case Namespaces::$sco."orderingValue":
                      $schema .= "<orderingValue>".$this->ws->xmlEncode($value["value"])."</orderingValue>";
                    break;  
                    
                    case Namespaces::$rdfs."subPropertyOf":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<subPropertyOf>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</subPropertyOf>";
                    break;
                    
                    case Namespaces::$iron."allowedValue":
                      $schema .= "<allowedValue><primitive>".$this->ws->xmlEncode($value["value"])."</primitive></allowedValue>";
                    break;                  
                  }
                }
              }
              
              $schema .= "</".$this->ironPrefixize($uri, $prefixes).">";
            }
            
            $subjectTriples = $ontology->getPropertiesDescription(FALSE, TRUE);

            foreach($subjectTriples as $uri => $subject)
            {
              $this->manageIronPrefixes($uri, $prefixes);
              
              $schema .= "<".$this->ironPrefixize($uri, $prefixes).">";

              $schema .= "<description>".$this->ws->xmlEncode($this->getDescription($subject))."</description>";
              $schema .= "<prefLabel>".$this->ws->xmlEncode($this->getLabel($uri, $subject))."</prefLabel>";
              
              foreach($subject as $predicate => $values)
              {
                foreach($values as $value)
                {
                  switch($predicate)
                  {
                    case Namespaces::$rdfs."domain":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<allowedType>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</allowedType>";
                    break;
                    
                    case Namespaces::$rdfs."range":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<allowedValue><type>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</type></allowedValue>";
                    break;
                    
                    case Namespaces::$sco."displayControl":
                    
                      if(isset($value["uri"]))
                      {
                        $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $displayControl = $value["value"];
                      }
                      
                      $schema .= "<displayControl>".$this->ws->xmlEncode($displayControl)."</displayControl>";
                    break;
                    
                    case Namespaces::$sco."ignoredBy":
                    
                      if(isset($value["uri"]))
                      {
                        $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $ignoredBy = $value["value"];
                      }
                      
                      $schema .= "<ignoredBy>".$this->ws->xmlEncode($ignoredBy)."</ignoredBy>";
                    break;

                    case Namespaces::$sco."comparableWith":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<comparableWith>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</comparableWith>";
                    break;
                    
                    case Namespaces::$sco."shortLabel":
                      $schema .= "<shortLabel>".$this->ws->xmlEncode($value["value"])."</shortLabel>";
                    break;
                    
                    case Namespaces::$sco."minCardinality":
                      $schema .= "<minCardinality>".$this->ws->xmlEncode($value["value"])."</minCardinality>";
                    break;
                    
                    case Namespaces::$sco."maxCardinality":
                      $schema .= "<maxCardinality>".$this->ws->xmlEncode($value["value"])."</maxCardinality>";
                    break;
                                        
                    case Namespaces::$sco."cardinality":
                      $schema .= "<cardinality>".$this->ws->xmlEncode($value["value"])."</cardinality>";
                    break;                  
                    
                    case Namespaces::$sco."unitType":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= "<unitType>".$this->ws->xmlEncode($this->ironPrefixize($value["uri"], $prefixes))."</unitType>";
                    break;                  
                    
                    case Namespaces::$sco."orderingValue":
                      $schema .= "<orderingValue>".$this->ws->xmlEncode($value["value"])."</orderingValue>";
                    break;          
                    
                    case Namespaces::$iron."allowedValue":
                      $schema .= "<allowedValue><primitive>".$this->ws->xmlEncode($value["value"])."</primitive></allowedValue>";
                    break;    
                  }
                }
              }
              
              $schema .= "</".$this->ironPrefixize($uri, $prefixes).">";            
            }
            
            $schema .= "</attributeList>";
            $schema .= "<prefixList>";                    

            foreach($prefixes as $prefix => $ns)
            {
              $schema .= "    <$prefix>$ns</$prefix>";
            }
            
            $schema .= "</prefixList>";          
            $schema .= "</schema>";    
            
            $subjectTriples = "";
            
            $subject = new Subject($this->ws->ontologyUri);
            $subject->setType("owl:Ontology");
            $subject->setDataAttribute(Namespaces::$wsf."serializedIronXMLSchema", str_replace(array ("\\", "&", "<", ">"), array ("%5C", "&amp;", "&lt;", "&gt;"), $schema));
            $this->ws->rset->addSubject($subject);          
            
  /*
            <schema>
              <version>0.1</version>
              <prefLabel>PEG schema</prefLabel>
              <prefixList>
                <sco>http://purl.org/ontology/sco#</sco>
              </prefixList>
              <typeList>
                <peg_Neighborhood>
                  <subTypeOf>pegf_Organization</subTypeOf>
                  <description>Neighborhood community organization</description>
                  <prefLabel>neighborhood</prefLabel>
                  <displayControl>sRelationBrowser</displayControl>
                </peg_Neighborhood>
              </typeList>
              <attributeList>
                <peg_neighborhoodNumber>
                  <prefLabel>neighborhood number</prefLabel>
                  <description>Neighborhood identification number</description>
                  <allowedType>Neighborhood</allowedType>
                  <allowedType>City</allowedType>
                  <allowedType>Province</allowedType>
                  <allowedType>Country</allowedType>
                  <allowedValue>
                    <primitive>String</primitive>
                  </allowedValue>
                  <maxValues>1</maxValues>
                </peg_neighborhoodNumber>
              </attributeList>
            </schema>
  */          

          break;
          
          
          case "getironjsonschema":
            $subjectTriples = $ontology->getClassesDescription($limit, $offset);
            
            $schema = '{ "schema": { "version": "0.1", "typeList": {';
            
            $prefixes = array();
            
            foreach($subjectTriples as $uri => $subject)
            {
              $this->manageIronPrefixes($uri, $prefixes);
              
              $schema .= '"'.$this->ironPrefixize($uri, $prefixes).'": {';

              $schema .= '"description": "'.$this->ws->jsonEncode($this->getDescription($subject)).'",';
              $schema .= '"prefLabel": "'.$this->ws->jsonEncode($this->getLabel($uri, $subject)).'",';
              
              foreach($subject as $predicate => $values)
              {  
                switch($predicate)
                {
                  case Namespaces::$rdfs."subClassOf":
                    $schema .= '"subTypeOf": [';
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    $schema .= '"displayControl": [';
                  break;
                  
                  case Namespaces::$sco."ignoredBy":
                    $schema .= '"ignoredBy": [';
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= '"shortLabel": [';
                  break;
                  
                  case Namespaces::$sco."minCardinality":
                    $schema .= '"minCardinality": [';
                  break;
                  
                  case Namespaces::$sco."maxCardinality":
                    $schema .= '"maxCardinality": [';
                  break;
                  
                  case Namespaces::$sco."cardinality":
                    $schema .= '"cardinality": [';
                  break;
                  
                  case Namespaces::$sco."color":
                    $schema .= '"color": [';
                  break;
                  
                  case Namespaces::$sco."mapMarkerImageUrl":
                    $schema .= '"mapMarkerImageUrl": [';
                  break;
                  
                  case Namespaces::$sco."relationBrowserNodeType":
                    $schema .= '"relationBrowserNodeType": [';
                  break;
                }              
                              
                foreach($values as $value)
                {
                  switch($predicate)
                  {
                    case Namespaces::$rdfs."subClassOf":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;
                    
                    case Namespaces::$sco."displayControl":
                      
                      if(isset($value["uri"]))
                      {
                        $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $displayControl = $value["value"];
                      }
                                         
                      $schema .= '"'.$this->ws->jsonEncode($displayControl).'",';
                    break;
                    
                    case Namespaces::$sco."ignoredBy":
                      
                      if(isset($value["uri"]))
                      {
                        $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $ignoredBy = $value["value"];
                      }
                                         
                      $schema .= '"'.$this->ws->jsonEncode($ignoredBy).'",';
                    break;
                    
                    case Namespaces::$sco."shortLabel":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."minCardinality":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."maxCardinality":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."cardinality":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."color":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."mapMarkerImageUrl":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."relationBrowserNodeType":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;
                  }              
                }
                
                switch($predicate)
                {
                  case Namespaces::$rdfs."subClassOf":
                  case Namespaces::$sco."displayControl":
                  case Namespaces::$sco."ignoredBy":
                  case Namespaces::$sco."shortLabel":
                  case Namespaces::$sco."color":
                  case Namespaces::$sco."mapMarkerImageUrl":
                  case Namespaces::$sco."relationBrowserNodeType":
                    $schema = rtrim($schema, ",");     
                    $schema .= '],';
                  break;
                }                
              }
              
              $schema = rtrim($schema, ",");
              
              $schema .= "},";            
            }
            
            $schema = rtrim($schema, ",");
            
            $schema .= "},";            
            
            $schema .= '"attributeList": {';

            $subjectTriples = $ontology->getPropertiesDescription(TRUE);

            foreach($subjectTriples as $uri => $subject)
            {
              $this->manageIronPrefixes($uri, $prefixes);
              
              $schema .= '"'.$this->ironPrefixize($uri, $prefixes).'": {';
              
              $schema .= '"description": "'.$this->ws->jsonEncode($this->getDescription($subject)).'",';
              $schema .= '"prefLabel": "'.$this->ws->jsonEncode($this->getLabel($uri, $subject)).'",';
              
              foreach($subject as $predicate => $values)
              {             
                switch($predicate)
                {
                  case Namespaces::$iron."allowedValue":
                    $schema .= '"allowedValue": {"primitive": "'.$this->ws->jsonEncode($value["value"]).'"},';
                  break;                
                  
                  case Namespaces::$rdfs."subPropertyOf":
                    $schema .= '"subPropertyOf": [';
                  break;                
                  
                  case Namespaces::$rdfs."domain":
                    $schema .= '"allowedType": [';
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    $schema .= '"displayControl": [';
                  break;
                  
                  case Namespaces::$sco."ignoredBy":
                    $schema .= '"ignoredBy": [';
                  break;

                  case Namespaces::$sco."comparableWith":
                    $schema .= '"comparableWith": [';
                  break;

                  case Namespaces::$sco."unitType":
                    $schema .= '"unitType": [';
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= '"shortLabel": [';
                  break;    
                    
                  case Namespaces::$sco."minCardinality":
                    $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                  break;
                  
                  case Namespaces::$sco."maxCardinality":
                    $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                  break;
                  
                  case Namespaces::$sco."cardinality":
                    $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                  break;                  
                  
                  case Namespaces::$sco."orderingValue":
                    $schema .= '"orderingValue": [';
                  break;  
                  
                  case Namespaces::$rdfs."subPropertyOf":
                    $schema .= '"subPropertyOf": [';
                  break;
                }              
                
                foreach($values as $value)
                {
                  switch($predicate)
                  {
                    case Namespaces::$rdfs."domain":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;
                    
                    case Namespaces::$sco."displayControl":
                      if(isset($value["uri"]))
                      {
                        $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $displayControl = $value["value"];
                      }
                      
                      $schema .= '"'.$this->ws->jsonEncode($displayControl).'",';
                    break;
                    
                    case Namespaces::$sco."ignoredBy":
                      if(isset($value["uri"]))
                      {
                        $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $ignoredBy = $value["value"];
                      }
                      
                      $schema .= '"'.$this->ws->jsonEncode($ignoredBy).'",';
                    break;

                    case Namespaces::$sco."comparableWith":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;

                    case Namespaces::$sco."unitType":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;
                    
                    case Namespaces::$sco."shortLabel":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                                        
                    case Namespaces::$sco."minCardinality":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."maxCardinality":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."cardinality":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."orderingValue":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;  
                    
                    case Namespaces::$rdfs."subPropertyOf":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;
                  }
                }
                
                switch($predicate)
                {
                  case Namespaces::$rdfs."domain":
                  case Namespaces::$sco."displayControl":
                  case Namespaces::$sco."ignoredBy":
                  case Namespaces::$sco."comparableWith":
                  case Namespaces::$sco."unitType":
                  case Namespaces::$sco."shortLabel":
                  case Namespaces::$sco."orderingValue":
                  case Namespaces::$rdfs."subPropertyOf":
                    $schema = rtrim($schema, ",");
                    $schema .= '],';  
                  break;
                }               
              }
              
              $schema = rtrim($schema, ",");
              
              $schema .= "},";                 
            }
            
            $subjectTriples = $ontology->getPropertiesDescription(FALSE, TRUE);

            foreach($subjectTriples as $uri => $subject)
            {
              $this->manageIronPrefixes($uri, $prefixes);
              
              $schema .= '"'.$this->ironPrefixize($uri, $prefixes).'": {';
              
              $schema .= '"description": "'.$this->ws->jsonEncode($this->getDescription($subject)).'",';
              $schema .= '"prefLabel": "'.$this->ws->jsonEncode($this->getLabel($uri, $subject)).'",';
              
              foreach($subject as $predicate => $values)
              {
                switch($predicate)
                {
                  case Namespaces::$rdfs."domain":
                    $schema .= '"allowedType": [';
                  break;
                  
                  case Namespaces::$rdfs."range":
                    $schema .= '"allowedValue": [';
                  break;
                  
                  case Namespaces::$sco."displayControl":
                    $schema .= '"displayControl": [';
                  break;
                  
                  case Namespaces::$sco."ignoredBy":
                    $schema .= '"ignoredBy": [';
                  break;

                  case Namespaces::$sco."comparableWith":
                    $schema .= '"comparableWith": [';
                  break;

                  case Namespaces::$sco."unitType":
                    $schema .= '"unitType": [';
                  break;
                  
                  case Namespaces::$sco."shortLabel":
                    $schema .= '"shortLabel": [';
                  break;
                  
                  case Namespaces::$sco."orderingValue":
                    $schema .= '"orderingValue": [';
                  break;  
                  
                  case Namespaces::$rdfs."subPropertyOf":
                    $schema .= '"subPropertyOf": [';
                  break;
                }                 
                
                foreach($values as $value)
                {
                  switch($predicate)
                  {
                    case Namespaces::$iron."allowedValue":
                      $schema .= '{"primitive": "'.$this->ws->jsonEncode($value["value"]).'"},';
                    break;                
                    
                    case Namespaces::$rdfs."domain":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;
                    
                    case Namespaces::$rdfs."range":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '{ "type": "'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'"},';
                    break;
                    
                    case Namespaces::$sco."displayControl":
                      if(isset($value["uri"]))
                      {
                        $displayControl = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $displayControl = $value["value"];
                      }
                      
                      $schema .= '"'.$this->ws->jsonEncode($displayControl).'",';
                    break;
                    
                    case Namespaces::$sco."ignoredBy":
                      if(isset($value["uri"]))
                      {
                        $ignoredBy = substr($value["uri"], strripos($value["uri"], "#") + 1);
                      }
                      else
                      {
                        $ignoredBy = $value["value"];
                      }
                      
                      $schema .= '"'.$this->ws->jsonEncode($ignoredBy).'",';
                    break;

                    case Namespaces::$sco."comparableWith":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;
                    
                    case Namespaces::$sco."shortLabel":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;
                    
                    case Namespaces::$sco."unitType":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;                  
                    
                    case Namespaces::$sco."orderingValue":
                      $schema .= '"'.$this->ws->jsonEncode($value["value"]).'",';
                    break;     
                                                                     
                    case Namespaces::$rdfs."subPropertyOf":
                      $this->manageIronPrefixes($value["uri"], $prefixes);
                      
                      $schema .= '"'.$this->ws->jsonEncode($this->ironPrefixize($value["uri"], $prefixes)).'",';
                    break;
                  }
                }
                
                switch($predicate)
                {
                  case Namespaces::$rdfs."domain":
                  case Namespaces::$rdfs."range":
                  case Namespaces::$sco."displayControl":
                  case Namespaces::$sco."ignoredBy":
                  case Namespaces::$sco."comparableWith":
                  case Namespaces::$sco."unitType":
                  case Namespaces::$sco."shortLabel":
                  case Namespaces::$sco."orderingValue":
                  case Namespaces::$rdfs."subPropertyOf":
                    $schema = rtrim($schema, ",");
                    $schema .= '],';  
                  break;
                }                
              }
              
              $schema = rtrim($schema, ",");
              
              $schema .= "},";              
            }
            
            $schema = rtrim($schema, ",");
            
            $schema .= "},";
            $schema .= '"prefixList": {';                    

            foreach($prefixes as $prefix => $ns)
            {
              $schema .= "    \"$prefix\": \"$ns\",";
            }
            
            $schema = rtrim($schema, ",");
            
            $schema .= "}";          
            $schema .= "}";    
            $schema .= "}";    
            
            $subjectTriples = "";
            
            $subject = new Subject($this->ws->ontologyUri);
            $subject->setType("owl:Ontology");
            $subject->setDataAttribute(Namespaces::$wsf."serializedIronJSONSchema", $schema);
            $this->ws->rset->addSubject($subject);                     
            
  /*
      
  {
      "schema": {
          "version": "0.1",
          "typeList": {
              "bibo_ThesisDegree": {
                  "description": "The academic degree of a Thesis",
                  "prefLabel": "Thesis degree",
                  "subTypeOf": [
                      "owl_Thing"
                  ]
              },
              "0_1_Agent": {
                  "description": "No description available",
                  "prefLabel": "Agent",
                  "subTypeOf": [
                      "owl_Thing"
                  ]
              },
              "bibo_Event": {
                  "description": "No description available",
                  "prefLabel": "Event",
                  "subTypeOf": [
                      "owl_Thing"
                  ]
              }
          },
      
          "attributeList": {
              "bibo_sici": {
                  "description": "No description available",
                  "prefLabel": "sici",
                  "allowedValue": {
                      "primitive": "String"
                  },
                  "subPropertyOf": [
                      "bibo_identifier"
                  ]
              },
      
              "terms_rights": {
                  "description": "No description available",
                  "prefLabel": "rights",
                  "subPropertyOf": [
                      "owl_topObjectProperty"
                  ]
              },
              "0_1_based_near": {
                  "description": "No description available",
                  "prefLabel": "based_near",
                  "subPropertyOf": [
                      "owl_topObjectProperty"
                  ]
              }
          },
          "prefixList": {
              "bibo": "http://purl.org/ontology/bibo/",
              "owl": "http://www.w3.org/2002/07/owl#",
              "0_1": "http://xmlns.com/foaf/0.1/",
              "event_owl": "http://purl.org/NET/c4dm/event.owl#",
              "rdf_schema": "http://www.w3.org/2000/01/rdf-schema#",
              "22_rdf_syntax_ns": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
              "terms": "http://purl.org/dc/terms/",
              "basic": "http://prismstandard.org/namespaces/1.2/basic/",
              "schema": "http://schemas.talis.com/2005/address/schema#"
          }
      }
  }    

  */          

          break;        
          
          case "getproperty":
            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }
            
            $property = $ontology->_getProperty($this->ws->parameters["uri"]);
            
            if($property == NULL)
            {
              $this->returnError(400, "Bad Request", "_204");
            }
            else
            {
              $subject = new Subject($this->ws->parameters["uri"]);
              $subject->setSubject($ontology->_getPropertyDescription($property));
              $this->ws->rset->addSubject($subject);
            }
          break;
          
          case "getproperties":
          
            $limit = -1;
            $offset = 0;
            
            if(isset($this->ws->parameters["limit"]))
            {
              $limit = $this->ws->parameters["limit"];
            }
            
            if(isset($this->ws->parameters["offset"]))
            {
              $offset = $this->ws->parameters["offset"];
            }
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $properties = $ontology->getPropertiesUri(TRUE, FALSE, FALSE, $limit, $offset);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:DatatypeProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  case "objectproperty":
                    $properties = $ontology->getPropertiesUri(FALSE, TRUE, FALSE, $limit, $offset);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:ObjectProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  case "annotationproperty":
                    $properties = $ontology->getPropertiesUri(FALSE, FALSE, TRUE, $limit, $offset);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:AnnotationProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }
              break;
              
              case "descriptions":
                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getPropertiesDescription(TRUE, FALSE, FALSE, $limit, $offset)));
                  break;
                  
                  case "objectproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getPropertiesDescription(FALSE, TRUE, FALSE, $limit, $offset)));
                  break;
                  
                  case "annotationproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getPropertiesDescription(FALSE, FALSE, TRUE, $limit, $offset)));
                  break;

                  case "all":
                    $subjectTriples = array();
                    $subjectTriples = array_merge($subjectTriples, $ontology->getPropertiesDescription(TRUE, FALSE, FALSE, $limit, $offset));
                    $subjectTriples = array_merge($subjectTriples, $ontology->getPropertiesDescription(FALSE, TRUE, FALSE, $limit, $offset));
                    $subjectTriples = array_merge($subjectTriples, $ontology->getPropertiesDescription(FALSE, FALSE, TRUE, $limit, $offset));
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $subjectTriples));
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }              
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;         
            }
          break;
          
          case "getsubproperties":
          
            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $properties = $ontology->getSubPropertiesUri((string)$this->ws->parameters["uri"], $this->ws->parameters["direct"], TRUE);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:DatatypeProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  case "objectproperty":
                    $properties = $ontology->getSubPropertiesUri((string)$this->ws->parameters["uri"], $this->ws->parameters["direct"], FALSE);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:ObjectProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }
              break;
              
              case "descriptions":
                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getSubPropertiesDescription((string)$this->ws->parameters["uri"], $this->ws->parameters["direct"], TRUE)));
                  break;
                  
                  case "objectproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getSubPropertiesDescription((string)$this->ws->parameters["uri"], $this->ws->parameters["direct"], FALSE)));
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }              
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;         
            }
          break;        
          
          case "getsuperproperties":
          
            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $properties = $ontology->getSuperPropertiesUri((string)$this->ws->parameters["uri"], $this->ws->parameters["direct"], TRUE);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:DatatypeProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  case "objectproperty":
                    $properties = $ontology->getSuperPropertiesUri((string)$this->ws->parameters["uri"], $this->ws->parameters["direct"], FALSE);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:ObjectProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }
              break;
              
              case "descriptions":

                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getSuperPropertiesDescription((string)$this->ws->parameters["uri"], $this->ws->parameters["direct"], TRUE)));
                  break;
                  
                  case "objectproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getSuperPropertiesDescription((string)$this->ws->parameters["uri"], $this->ws->parameters["direct"], FALSE)));
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }              
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;         
            }
          break;   
          
          case "getequivalentproperties":
          
            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $properties = $ontology->getEquivalentPropertiesUri((string)$this->ws->parameters["uri"], TRUE);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:DatatypeProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  case "objectproperty":
                    $properties = $ontology->getEquivalentPropertiesUri((string)$this->ws->parameters["uri"], FALSE);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:ObjectProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }
              break;
              
              case "descriptions":
                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getEquivalentPropertiesDescription((string)$this->ws->parameters["uri"], TRUE)));
                  break;
                  
                  case "objectproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getEquivalentPropertiesDescription((string)$this->ws->parameters["uri"], FALSE)));
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }              
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;         
            }
          break;
          
          case "getdisjointproperties":
          
            if(!isset($this->ws->parameters["uri"]) || $this->ws->parameters["uri"] == "")
            {
              $this->returnError(400, "Bad Request", "_202");
              return;              
            }
          
            switch(strtolower($this->ws->parameters["mode"]))
            {
              case "uris":
                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $properties = $ontology->getDisjointPropertiesUri((string)$this->ws->parameters["uri"], TRUE);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:DatatypeProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  case "objectproperty":
                    $properties = $ontology->getDisjointPropertiesUri((string)$this->ws->parameters["uri"], FALSE);
                    
                    foreach($properties as $property)
                    {
                      $subject = new Subject($property);
                      $subject->setType("owl:ObjectProperty");
                      $this->ws->rset->addSubject($subject);
                    }
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }
              break;
              
              case "descriptions":
                switch(strtolower($this->ws->parameters["type"]))
                {
                  case "dataproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getDisjointPropertiesDescription((string)$this->ws->parameters["uri"], TRUE)));
                  break;
                  
                  case "objectproperty":
                    $this->ws->rset->setResultset(Array($this->ws->ontologyUri => $ontology->getDisjointPropertiesDescription((string)$this->ws->parameters["uri"], FALSE)));
                  break;
                  
                  default:
                    $this->returnError(400, "Bad Request", "_203");
                    return;
                  break;         
                }              
              break;
              
              default:
                $this->returnError(400, "Bad Request", "_201");
                return;
              break;         
            }
          break;        
          
          default:
            $this->ws->conneg->setStatus(400);
            $this->ws->conneg->setStatusMsg("Bad Request");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_200->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_200->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_200->name, $this->ws->errorMessenger->_200->description, "This function is not defined
              for this endpoint:".$this->ws->function,
              $this->ws->errorMessenger->_200->level);

            return;          
          break;
        }
      }      
    }
  }
?>
