<?php
  
  namespace StructuredDynamics\structwsf\ws\search\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\framework\ProcessorXML;
  use \StructuredDynamics\structwsf\ws\framework\Solr;
  use \StructuredDynamics\structwsf\framework\Subject;
  use \StructuredDynamics\structwsf\ws\auth\lister\AuthLister;
  use \StructuredDynamics\structwsf\framework\Datatypes;
  use \DOMDocument;
  use \DOMXPath;  
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "1.0";
    }
    
    private function escapeSolrValue($string)
    {
      $match = array('\\', '+', '-', '&', '|', '!', '(', ')', '{', '}', '[', ']', '^', '~', '*', '?', ':', '"', ';', ' ');
      $replace = array('\\\\', '\\+', '\\-', '\\&', '\\|', '\\!', '\\(', '\\)', '\\{', '\\}', '\\[', '\\]', '\\^', '\\~', '\\*', '\\?', '\\:', '\\"', '\\;', '\\ ');
      $string = str_replace($match, $replace, $string);

      return $string;
    }  
    
    private function escapeURIValue($uri)
    {
      $match = array(':');
      $replace = array('\\:');
      $uri = str_replace($match, $replace, $uri);

      return $uri;
    }  
    
    /**
    * Arrange a value before adding it to a Solr query. If some characters that belongs to the
    * Lucene query syntax are used, then we return the string without modifying anything. Otherwise
    * we clean it a bit to only keep the alpha-numeric characters and a few other ones.
    * 
    * @param mixed $string The string to arrange
    */
    private function arrangeSolrValue($string)
    {
      if(!$this->isUsingLuceneSyntax($string))
      {
        return(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $string));  
      }

      // If the string is using some character from the lucene syntax, then we keep the string 
      // intact.
      return($string);  
    }                    

    /**
    * Check if a string is using some characters that belongs to the Lucene query syntax
    *     
    * @param mixed $string String value to check
    */
    private function isUsingLuceneSyntax($string)
    {
      $specialChars = array('"', 'AND', 'OR', '?', '*', '~', '+', '-', 'NOT', '&&', '||', '!', '^',
                            '(', ')', '{', '}', '[', ']', '*', ':', '\\');      
      
      foreach($specialChars as $char)
      {
        if(($pos = strpos($string, $char)) !== FALSE)
        {
          // Make sure that the character is not escaped
          if(substr($string, $pos - 1, 1) != '\\')
          {
            return(TRUE);
            break;          
          }
        }
      }      
      
      return(FALSE);
    } 
    
    private function isCoreAttribute($attribute, &$coreAttributeField)
    {
      switch($attribute)
      {
        case "uri":
          $coreAttributeField = "uri";
          return(TRUE);
        break;  
        
        case "dataset":
          $coreAttributeField = "dataset";
          return(TRUE);
        break;  
        
        case "preflabel":
        case "prefLabel":
        case Namespaces::$iron."prefLabel":
          $coreAttributeField = "prefLabel_".$this->ws->lang;
          return(TRUE);
        break;
        
        case "altlabel":
        case "altLabel":
        case Namespaces::$iron."altLabel":
          $coreAttributeField = "altLabel_".$this->ws->lang;
          return(TRUE);
        break;
        
        case "description":
        case Namespaces::$iron."description":
          $coreAttributeField = "description_".$this->ws->lang;
          return(TRUE);
        break;
        
        case "lat":
        case Namespaces::$geo."lat":
          $coreAttributeField = "lat";
          return(TRUE);
        break;
        
        case "long":
        case Namespaces::$geo."long":
          $coreAttributeField = "long";
          return(TRUE);
        break;
        
        case "polygoncoordinates":
        case "polygonCoordinates":
        case Namespaces::$sco."polygonCoordinates":              
          $coreAttributeField = "polygonCoordinates";
          return(TRUE);
        break;
        
        case "polylinecoordinates":
        case "polylineCoordinates":
        case Namespaces::$sco."polylineCoordinates":
          $coreAttributeField = "polylineCoordinates";
          return(TRUE);
        break;
        
        case "type":
        case Namespaces::$rdf."type":
          $coreAttributeField = "type";
          return(TRUE);
        break;
        
        case "inferred_type":
          $coreAttributeField = "inferred_type";
          return(TRUE);
        break;
                    
        case "located_in":
        case Namespaces::$geoname."locatedIn":
          $coreAttributeField = "located_in";
          return(TRUE);
        break;
      }      
      
      return(FALSE);
    }
    
    public function processInterface()
    {  
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        $solr = new Solr($this->ws->wsf_solr_core, $this->ws->solr_host, $this->ws->solr_port, $this->ws->fields_index_folder);

        $solrQuery = "";

        // Get all datasets accessible to that user
        $accessibleDatasets = array();

        $ws_al = new AuthLister("access_user", "", $this->ws->registered_ip, $this->ws->wsf_local_ip, "none");

        $ws_al->pipeline_conneg($this->ws->conneg->getAccept(), $this->ws->conneg->getAcceptCharset(),
          $this->ws->conneg->getAcceptEncoding(), $this->ws->conneg->getAcceptLanguage());

        $ws_al->process();

        $xml = new ProcessorXML();
        $xml->loadXML($ws_al->pipeline_getResultset());

        $accesses = $xml->getSubjectsByType("wsf:Access");

        foreach($accesses as $access)
        {
          $predicates = $xml->getPredicatesByType($access, "wsf:datasetAccess");
          $objects = $xml->getObjects($predicates->item(0));
          $datasetUri = $xml->getURI($objects->item(0));

          $predicates = $xml->getPredicatesByType($access, "wsf:read");
          $objects = $xml->getObjects($predicates->item(0));
          $read = $xml->getContent($objects->item(0));

          if(strtolower($read) == "true" && array_search($datasetUri, $accessibleDatasets) === FALSE)
          {          
            array_push($accessibleDatasets, $datasetUri);
          }
        }
        
        unset($ws_al);
        
        /*
          if registered_ip != requester_ip, this means that the query is sent by a registered system
          on the behalf of someone else. In this case, we want to make sure that that system 
          (the one that send the actual query) has access to the same datasets. Otherwise, it means that
          it tries to personificate that registered_ip user.
        */
        if($this->ws->registered_ip != $this->ws->requester_ip)
        {
          // Get all datasets accessible to that system
          $accessibleDatasetsSystem = array();

          $ws_al = new AuthLister("access_user", "", $this->ws->requester_ip, $this->ws->wsf_local_ip, "none");

          $ws_al->pipeline_conneg($this->ws->conneg->getAccept(), $this->ws->conneg->getAcceptCharset(),
            $this->ws->conneg->getAcceptEncoding(), $this->ws->conneg->getAcceptLanguage());

          $ws_al->process();

          $xml = new ProcessorXML();
          $xml->loadXML($ws_al->pipeline_getResultset());

          $accesses = $xml->getSubjectsByType("wsf:Access");

          foreach($accesses as $access)
          {
            $predicates = $xml->getPredicatesByType($access, "wsf:datasetAccess");
            $objects = $xml->getObjects($predicates->item(0));
            $datasetUri = $xml->getURI($objects->item(0));

            $predicates = $xml->getPredicatesByType($access, "wsf:read");
            $objects = $xml->getObjects($predicates->item(0));
            $read = $xml->getContent($objects->item(0));

            if(strtolower($read) == "true")
            {
              array_push($accessibleDatasetsSystem, $datasetUri);
            }
          }      
          
          unset($ws_al);         
        
          /*
            Finally we use the intersection of the two set of dataset URIs as the list of accessible
            datasets to include for the query.
          */ 
          $accessibleDatasets = array_intersect($accessibleDatasets, $accessibleDatasetsSystem);
        }

        if(count($accessibleDatasets) <= 0)
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_300->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_300->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_300->name, $this->ws->errorMessenger->_300->description, "",
            $this->ws->errorMessenger->_300->level);
          return;
        }
        
        $queryParam = "*:*";
        
        if($this->ws->query != "")
        {      
          $queryParam = urlencode($this->ws->query);
        }

        $distanceQueryRevelencyBooster = "";
        
        if($this->ws->geoEnabled && isset($this->ws->resultsLocationAggregator[0]) && 
           isset($this->ws->resultsLocationAggregator[1]) && ($this->ws->distanceFilter || $this->ws->rangeFilter))
        {
          // Here, "^0" is added to zero-out the boost (revelency) value of the keyword
          // in this query, we simply want to aggregate the results related to their
          // distance of the center point.
          //$distanceQueryRevelencyBooster = '^0 AND _val_:"recip(dist(2, lat, long, '.$this->ws->resultsLocationAggregator[0].', '.$this->ws->resultsLocationAggregator[1].'), 1, 1, 0)"^100';  
          $distanceQueryRevelencyBooster = '^0 AND _val_:"recip(geodist(geohash, '.$this->ws->resultsLocationAggregator[0].', '.$this->ws->resultsLocationAggregator[1].'), 1, 1, 0)"^100';  
        }
        
        $boostingRules = "";
        
        // Types boosting rules
        if($this->ws->typesBoost != "")
        {
         $boostRules = explode(";", $this->ws->typesBoost);
         
         foreach($boostRules as $key => $rule)
         {
           $boost = explode("^", $rule);
           
           $type = str_replace(array("%3B", "%5E"), array(";", "^"), $boost[0]);
           $modifier = $boost[1];
           
           $boostingRules .= '&bq=type:"'.urlencode($type).'"^'.$modifier;
           
           if(strtolower($this->ws->inference) == "on")
           {
             $boostingRules .= '&bq=inferred_type:"'.urlencode($type).'"^'.$modifier;
           }
         }
         
         $insertOR = TRUE;
        }

        // Datasets boosting rules
        if($this->ws->datasetsBoost != "")
        {
         $boostRules = explode(";", $this->ws->datasetsBoost);
         
         foreach($boostRules as $key => $rule)
         {
           $boost = explode("^", $rule);
           
           $dataset = str_replace(array("%3B", "%5E"), array(";", "^"), $boost[0]);
           $modifier = $boost[1];
           
           $boostingRules .= '&bq=dataset:"'.urlencode($dataset).'"^'.$modifier;
         }
         
         $insertOR = TRUE;
        }

        // Attributes & Attributes/values boosting rules
        if($this->ws->attributesBoost != "")
        {
         $boostRules = explode(";", $this->ws->attributesBoost);
         
         foreach($boostRules as $key => $rule)
         {
           $isAttributeValue = FALSE;
           
           if(strpos($rule, '::') !== FALSE)
           {
             $isAttributeValue = TRUE;
           }
           
           $boost = explode("^", $rule);
           
           $attribute = str_replace(array("%3B", "%5E"), array(";", "^"), $boost[0]);
           $modifier = $boost[1];
           
           if($isAttributeValue)
           {
             $attribute = explode("::", $attribute);
             
             $attributeValue = $attribute[1];
             $attribute = $attribute[0];
             
             $indexedFields = array();

             if(file_exists($this->ws->fields_index_folder."fieldsIndex.srz"))
             {
               $indexedFields = unserialize(file_get_contents($this->ws->fields_index_folder."fieldsIndex.srz"));
             }                 
             
             // Fix the reference to some of the core attributes                 
             $isCoreAttribute = $this->isCoreAttribute($attribute, $attribute);
              
             // If it is not a core attribute, check if we have to make that attribute
             // single-valued. We check that by checking if a single_valued version
             // of that field is currently used in the Solr index.              
             $singleValuedDesignator = "";

             if(!$isCoreAttribute &&                  
                (array_search(urlencode($attribute."_attr_".$this->ws->lang."_single_valued"), $indexedFields) !== FALSE ||
                 array_search(urlencode($attribute."_attr_date_single_valued"), $indexedFields) !== FALSE ||
                 array_search(urlencode($attribute."_attr_int_single_valued"), $indexedFields) !== FALSE ||
                 array_search(urlencode($attribute."_attr_float_single_valued"), $indexedFields) !== FALSE))
             {
               $singleValuedDesignator = "_single_valued";
             }  
              
             $attributeFound = FALSE;
              
             // Get the Solr field ID for this attribute
             if(!$isCoreAttribute)
             {
               // Check if it is an object property, and check if the pattern of this object property
               // is using URIs as values
               $valuesAsURI = FALSE;
               
               if(stripos($attribute, "[uri]") !== FALSE)
               {
                 $valuesAsURI = TRUE;
                 $attribute = str_replace("[uri]", "", $attribute);
               }
                
               $attribute = urlencode($attribute);
               
               if(array_search($attribute."_attr_date".$singleValuedDesignator, $indexedFields) !== FALSE)
               {              
                 $attribute = urlencode($attribute)."_attr_date".$singleValuedDesignator;
               }
               elseif(array_search($attribute."_attr_int".$singleValuedDesignator, $indexedFields) !== FALSE)
               {              
                 $attribute = urlencode($attribute)."_attr_int".$singleValuedDesignator;
               }
               elseif(array_search($attribute."_attr_float".$singleValuedDesignator, $indexedFields) !== FALSE)
               {              
                 $attribute = urlencode($attribute)."_attr_float".$singleValuedDesignator;
               }
               elseif(array_search($attribute."_attr_".$this->ws->lang.$singleValuedDesignator, $indexedFields) !== FALSE && $valuesAsURI === FALSE)
               {              
                 $attribute = urlencode($attribute)."_attr_".$this->ws->lang.$singleValuedDesignator;
               }
               elseif(array_search($attribute."_attr_obj_".$this->ws->lang.$singleValuedDesignator, $indexedFields) !== FALSE)
               {      
                 // Check if the value of that filter is a URI or not.
                 if($valuesAsURI)
                 {
                   $attribute = urlencode($attribute)."_attr_obj_uri";  
                 } 
                 else
                 {
                   $attribute = urlencode($attribute)."_attr_obj_".$this->ws->lang.$singleValuedDesignator;
                 }                                       
               }                    
             }                 
             
             $boostingRules .= '&bq='.$attribute.':"'.urlencode($attributeValue).'"^'.$modifier;
           }
           else
           {
             $boostingRules .= '&bq=attribute:"'.urlencode($attribute).'"^'.$modifier;
           }
          }
        }
        
        // Attributes phrases boosting rules
        if($this->ws->attributesPhraseBoost != "")
        {
          $boostRules = explode(";", $this->ws->attributesPhraseBoost);

          $indexedFields = array();

          if(file_exists($this->ws->fields_index_folder."fieldsIndex.srz"))
          {
            $indexedFields = unserialize(file_get_contents($this->ws->fields_index_folder."fieldsIndex.srz"));
          }             
          
          foreach($boostRules as $key => $rule)
          {
            if($rule == '')
            {
              continue;
            }
                        
            $boost = explode("^", $rule);

            $attribute = str_replace(array("%3B", "%5E"), array(";", "^"), $boost[0]);
            $modifier = $boost[1];
           
            $isCoreAttribute = $this->isCoreAttribute($attribute, $attribute);
           
            $singleValuedDesignator = "";

            if(!$isCoreAttribute)
            {
              if(array_search(urlencode($attribute."_attr_".$this->ws->lang."_single_valued"), $indexedFields) !== FALSE)
              {
                $singleValuedDesignator = "_single_valued";
              }             
             
              if(array_search($attribute."_attr_".$this->ws->lang.$singleValuedDesignator, $indexedFields) !== FALSE && $valuesAsURI === FALSE)
              {              
                $attribute = str_replace($attribute, urlencode($attribute)."_attr_".$this->ws->lang.$singleValuedDesignator, $attribute);

                $attributeFound = TRUE;
              }
              elseif(array_search($attribute."_attr_obj_".$this->ws->lang.$singleValuedDesignator, $indexedFields) !== FALSE)
              {      
                $attribute = str_replace($attribute.":", urlencode($attribute)."_attr_obj_".$this->ws->lang.$singleValuedDesignator.":", $attribute);

                $attributeFound = TRUE;
              }           
            }           
           
            $boostingRules .= '&pf='.urlencode($attribute).'^'.$modifier;
          }
          
          $boostingRules .= '&qs='.$this->ws->phraseBoostDistance;
        }
        
        $restrictionRules = '';
        
        // Define properties restricted for search and their boost
        if(!empty($this->ws->searchRestrictions))
        {
          $restrictionRules .= '&qf=';
          
          $restrictions = explode(';', $this->ws->searchRestrictions);
          
          foreach($restrictions as $restriction)
          {
            if(empty($restriction))
            {
              continue;
            }
            
            $modifier = 1;
            $attribute = $restriction;
            
            if(strpos($restriction, '^'))
            {
              $res = explode('^', $restriction);
              
              $modifier =  $res[1];
              $attribute = $res[0];
            }
            
            $indexedFields = array();

            if(file_exists($this->ws->fields_index_folder."fieldsIndex.srz"))
            {
              $indexedFields = unserialize(file_get_contents($this->ws->fields_index_folder."fieldsIndex.srz"));
            }                 

            // Fix the reference to some of the core attributes                 
            $isCoreAttribute = $this->isCoreAttribute($attribute, $attribute);

            // If it is not a core attribute, check if we have to make that attribute
            // single-valued. We check that by checking if a single_valued version
            // of that field is currently used in the Solr index.              
            $singleValuedDesignator = "";

            if(!$isCoreAttribute &&                  
                (array_search(urlencode($attribute."_attr_".$this->ws->lang."_single_valued"), $indexedFields) !== FALSE ||
                 array_search(urlencode($attribute."_attr_date_single_valued"), $indexedFields) !== FALSE ||
                 array_search(urlencode($attribute."_attr_int_single_valued"), $indexedFields) !== FALSE ||
                 array_search(urlencode($attribute."_attr_float_single_valued"), $indexedFields) !== FALSE))
            {
              $singleValuedDesignator = "_single_valued";
            }  

            $attributeFound = FALSE;

            // Get the Solr field ID for this attribute
            if(!$isCoreAttribute)
            {
             $attribute = urlencode($attribute);
             
             /*
              @NOTE: here we can only include fields that uses the "solr.StopFilterFactory" setting in their
                     defined workflow. Otherwise, if a stopword is used in the search query, 0 results
                     will always be returned with the eDisMax query parser.
             */
             
             if(array_search($attribute."_attr_".$this->ws->lang.$singleValuedDesignator, $indexedFields) !== FALSE)
             {              
               $attribute = urlencode($attribute)."_attr_".$this->ws->lang.$singleValuedDesignator;
               $attributeFound = TRUE;
             }
             elseif(array_search($attribute."_attr_obj_".$this->ws->lang.$singleValuedDesignator, $indexedFields) !== FALSE)
             {      
               $attribute = urlencode($attribute)."_attr_obj_".$this->ws->lang.$singleValuedDesignator;
               $attributeFound = TRUE;
             }                    
            }
            else
            {
              $attributeFound = TRUE;
            }                 

            if($attributeFound)
            {
              $restrictionRules .= $attribute.'^'.$modifier.' ';            
            }            
          }
        }
        
        $restrictionRules = rtrim($restrictionRules);        

        $solrQuery = "q=".$queryParam.$distanceQueryRevelencyBooster.$boostingRules.$restrictionRules.
                     "&start=" . $this->ws->page . 
                     "&rows=" . $this->ws->items .
                     (strtolower($this->ws->includeAggregates) == "true" ? 
                        "&facet=true".
                        "&facet.sort=count".
                        "&facet.limit=-1".
                        "&facet.field=type".
                        "&facet.field=attribute" . 
                         (strtolower($this->ws->inference) == "on" ?  
                            "&facet.field=inferred_type" 
                         : "") .
                        "&facet.field=dataset&facet.mincount=1" 
                     : "");
        
        if(strtolower($this->ws->datasets) == "all")
        {
          $datasetList = "";

          foreach($accessibleDatasets as $key => $dataset)
          {
            if($key == 0)
            {
              $solrQuery .= "&fq=dataset:%22" . urlencode($dataset) . "%22";
            }
            else
            {
              $solrQuery .= " OR dataset:%22" . urlencode($dataset) . "%22";
            }
          }
        }
        else
        {
          $datasets = explode(";", $this->ws->datasets);

          $solrQuery .= "&fq=dataset:%22%22";

          foreach($datasets as $dataset)
          {
            // Check if the dataset is accessible to the user
            if(array_search($dataset, $accessibleDatasets) !== FALSE)
            {
              // Decoding potentially encoded ";" characters
              $dataset = str_replace(array ("%3B", "%3b"), ";", $dataset);

              $solrQuery .= " OR dataset:%22" . urlencode($dataset) . "%22";
            }
          }
        }

        if($this->ws->types != "all")
        {
          // Lets include the information to facet per type.

          $types = explode(";", $this->ws->types);

          $nbProcessed = 0;

          foreach($types as $type)
          {
            // Decoding potentially encoded ";" characters
            $type = str_replace(array ("%3B", "%3b"), ";", $type);

            if($nbProcessed == 0)
            {
              $solrQuery .= "&fq=type:%22" . urlencode($type) . "%22";
            }
            else
            {
              $solrQuery .= " OR type:%22" . urlencode($type) . "%22";
            }

            $nbProcessed++;

            if(strtolower($this->ws->inference) == "on")
            {
              $solrQuery .= " OR inferred_type:%22" . urlencode($type) . "%22";
            }
          }
        }
        
        if($this->ws->extendedFilters != "")
        {
          // Get the fields (attributes) from the extended attributes query
          preg_match_all("/([#%\.A-Za-z0-9_\-\[\]]+):[\(\"#%\.A-Za-z0-9_\-\+*]+/Uim", $this->ws->extendedFilters, $matches);
          
          $attributes = $matches[1];

          $indexedFields = array();
          
          if(file_exists($this->ws->fields_index_folder."fieldsIndex.srz"))
          {
            $indexedFields = unserialize(file_get_contents($this->ws->fields_index_folder."fieldsIndex.srz"));
          }
          
          $attributes = array_unique($attributes);

          foreach($attributes as $attribute)
          {
            $attribute = urldecode($attribute);
             
            if($attribute == "dataset")
            {
              // Make sure the user has access to this dataset
              
              // Get all the dataset values referenced in the extended filters
              $usedDatasets = array();
              
              preg_match_all("/dataset:[\"(](.*)[\")]/Uim", $this->ws->extendedFilters, $matches);
              
              $usedDatasets = array_merge($usedDatasets, $matches[1]);

              preg_match_all("/dataset:([^\"()]*)[\s\$)]+/Uim", $this->ws->extendedFilters, $matches);

              $usedDatasets = array_merge($usedDatasets, $matches[1]);
              
              $usedDatasets = array_unique($usedDatasets);
              
              // Make sure that all defined dataset extended filters are accessible to the requester
              foreach($usedDatasets as $key => $usedDataset)
              {
                // Unescape values (remove "\" from the Solr query)
                $usedDataset = str_replace('\\', '', $usedDataset);

                if(array_search($usedDataset, $accessibleDatasets) === FALSE)
                {
                  $this->ws->conneg->setStatus(400);
                  $this->ws->conneg->setStatusMsg("Bad Request");
                  $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_309->name);
                  $this->ws->conneg->setError($this->ws->errorMessenger->_309->id, $this->ws->errorMessenger->ws,
                    $this->ws->errorMessenger->_309->name, $this->ws->errorMessenger->_309->description, "Unaccessible dataset: ".$usedDataset,
                    $this->ws->errorMessenger->_309->level);
                  return;
                }
              }
            }
            
            // Fix the reference to some of the core attributes
            $coreAttribute = "";
            
            $isCoreAttribute = $this->isCoreAttribute($attribute, $coreAttribute);
            
            // If it is not a core attribute, check if we have to make that attribute
            // single-valued. We check that by checking if a single_valued version
            // of that field is currently used in the Solr index.              
            $singleValuedDesignator = "";

            if(!$isCoreAttribute &&                  
                (array_search(urlencode($attribute."_attr_".$this->ws->lang."_single_valued"), $indexedFields) !== FALSE ||
                 array_search(urlencode($attribute."_attr_date_single_valued"), $indexedFields) !== FALSE ||
                 array_search(urlencode($attribute."_attr_int_single_valued"), $indexedFields) !== FALSE ||
                 array_search(urlencode($attribute."_attr_float_single_valued"), $indexedFields) !== FALSE))
            {
              $singleValuedDesignator = "_single_valued";
            }  
            
            $attributeFound = FALSE;
            
            // Get the Solr field ID for this attribute
            if($isCoreAttribute)
            {
              $attribute = urlencode($attribute);
              
              $this->ws->extendedFilters = str_replace($attribute, $coreAttribute, $this->ws->extendedFilters);
              
              $attributeFound = TRUE;
            }
            else
            {
              // Check if it is an object property, and check if the pattern of this object property
              // is using URIs as values
              $valuesAsURI = FALSE;
              
              if(stripos($attribute, "[uri]") !== FALSE)
              {
                $valuesAsURI = TRUE;
                $attribute = str_replace("[uri]", "", $attribute);
              }
              
              $attribute = urlencode($attribute);
    
              if(array_search($attribute."_attr_date".$singleValuedDesignator, $indexedFields) !== FALSE)
              {              
                $this->ws->extendedFilters = str_replace($attribute, urlencode($attribute)."_attr_date".$singleValuedDesignator, $this->ws->extendedFilters);
              
                $attributeFound = TRUE;
              }
              elseif(array_search($attribute."_attr_int".$singleValuedDesignator, $indexedFields) !== FALSE)
              {              
                $this->ws->extendedFilters = str_replace($attribute, urlencode($attribute)."_attr_int".$singleValuedDesignator, $this->ws->extendedFilters);
              
                $attributeFound = TRUE;
              }
              elseif(array_search($attribute."_attr_float".$singleValuedDesignator, $indexedFields) !== FALSE)
              {              
                $this->ws->extendedFilters = str_replace($attribute, urlencode($attribute)."_attr_float".$singleValuedDesignator, $this->ws->extendedFilters);
              
                $attributeFound = TRUE;
              }
              elseif(array_search($attribute."_attr_".$this->ws->lang.$singleValuedDesignator, $indexedFields) !== FALSE && $valuesAsURI === FALSE)
              {              
                $this->ws->extendedFilters = str_replace($attribute, urlencode($attribute)."_attr_".$this->ws->lang.$singleValuedDesignator, $this->ws->extendedFilters);
              
                $attributeFound = TRUE;
              }
              elseif(array_search($attribute."_attr_obj_".$this->ws->lang.$singleValuedDesignator, $indexedFields) !== FALSE)
              {      
                // Check if the value of that filter is a URI or not.
                if($valuesAsURI)
                {
                  $this->ws->extendedFilters = str_replace($attribute."[uri]:", urlencode($attribute)."_attr_obj_uri:", $this->ws->extendedFilters);  
                } 
                else
                {
                  $this->ws->extendedFilters = str_replace($attribute.":", urlencode($attribute)."_attr_obj_".$this->ws->lang.$singleValuedDesignator.":", $this->ws->extendedFilters);
                }                                       
              
                $attributeFound = TRUE;
              }
            }
            
            if($attributeFound === FALSE)
            {
              $this->ws->conneg->setStatus(400);
                $this->ws->conneg->setStatusMsg("Bad Request");
                $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_310->name);
                $this->ws->conneg->setError($this->ws->errorMessenger->_310->id, $this->ws->errorMessenger->ws,
                  $this->ws->errorMessenger->_310->name, $this->ws->errorMessenger->_310->description, "Undefined filter: ".urldecode($attribute),
                  $this->ws->errorMessenger->_310->level);
                return;              
            }
          }
          
          $solrQuery .= "&fq=".$this->ws->extendedFilters;
        }
        
        if($this->ws->attributes != "all")
        {                    
          // Lets include the information to facet per type.
          $attributes = explode(";", $this->ws->attributes);

          $nbProcessed = 0;

          $indexedFields = array();          
          
          if(file_exists($this->ws->fields_index_folder."fieldsIndex.srz"))
          {
            $indexedFields = unserialize(file_get_contents($this->ws->fields_index_folder."fieldsIndex.srz"));
          }

          foreach($attributes as $attribute)
          {
            $attributeValue = explode("::", $attribute);
            $attribute = urldecode($attributeValue[0]);

            // Skip possible "dataset" field request. This is handled bia the "dataset" parameter
            // of this web service endpoint.
            if($attribute == "dataset")
            {
              continue;
            }
            
            if(isset($attributeValue[1]) && $attributeValue[1] != "")
            {
              // Fix the reference to some of the core attributes
              $coreAttr = $this->isCoreAttribute($attribute, $attribute);
              
              // Special handling for some core attributes
              switch(urldecode($attributeValue[0]))
              {
                case "prefLabel":
                case Namespaces::$iron."prefLabel":
                  // Check if we are performing an autocompletion task on the pref label
                  $label = urldecode($attributeValue[1]);
                  if(substr($label, strlen($label) - 2) == "**")
                  {
                    $attribute = "prefLabelAutocompletion_".$this->ws->lang;
                    $attributeValue[1] = urlencode(strtolower(str_replace(" ", "\\ ", substr($label, 0, strlen($label) -1))));
                  }
                break;

                case "lat":
                case Namespaces::$geo."lat":
                  if(!is_numeric(urldecode($attributeValue[1])))
                  {
                    // If the value is not numeric, we skip that attribute/value. 
                    // Otherwise an exception will be raised by Solr.
                    continue;
                  }
                break;

                case "long":                
                case Namespaces::$geo."long":
                  if(!is_numeric(urldecode($attributeValue[1])))
                  {
                    // If the value is not numeric, we skip that attribute/value. 
                    // Otherwise an exception will be raised by Solr.
                    continue;
                  }
                break;
              }            

              // A filtering value as been defined for this attribute.
              $val = urldecode($attributeValue[1]);
              
              // If it is not a core attribute, check if we have to make that attribute
              // single-valued. We check that by checking if a single_valued version
              // of that field is currently used in the Solr index.              
              $singleValuedDesignator = "";
              
              if(!$coreAttr &&                  
                 (array_search(urlencode($attribute."_attr_".$this->ws->lang."_single_valued"), $indexedFields) !== FALSE ||
                  array_search(urlencode($attribute."_attr_date_single_valued"), $indexedFields) !== FALSE ||
                  array_search(urlencode($attribute."_attr_int_single_valued"), $indexedFields) !== FALSE ||
                  array_search(urlencode($attribute."_attr_float_single_valued"), $indexedFields) !== FALSE))
              {
                $singleValuedDesignator = "_single_valued";
              }
              
              if($nbProcessed == 0)
              {
                if($coreAttr)
                {
                  switch($attribute)
                  {                
                    case "type":
                      if(strtolower($this->ws->inference) == "on")
                      {
                        $solrQuery .= "&fq=((type:".urlencode($this->escapeSolrValue($val)).") OR (inferred_type:".urlencode($this->escapeSolrValue($val))."))";
                      }
                      else
                      {
                        $solrQuery .= "&fq=(type:".urlencode($this->escapeSolrValue($val)).")";
                      }
                    break;
                    
                    case "located_in":
                      $solrQuery .= "&fq=(located_in:".urlencode($this->escapeSolrValue($val)).")";
                    break;
                    
                    default:
                      $solrQuery .= "&fq=(".$attribute.":".urlencode($this->arrangeSolrValue($val)).")";
                    break;
                  }
                }
                else
                {
                  $solrQuery .= "&fq=(";              
                }
              }
              else
              {
                if($coreAttr)
                {
                  switch($attribute)
                  {                
                    case "type":
                      if(strtolower($this->ws->inference) == "on")
                      {
                        $solrQuery .= " ".$this->ws->attributesBooleanOperator." ((type:".urlencode($this->escapeSolrValue($val)).") OR (inferred_type:".urlencode($this->escapeSolrValue($val))."))";
                      }
                      else
                      {
                        $solrQuery .= " ".$this->ws->attributesBooleanOperator." (type:".urlencode($this->escapeSolrValue($val)).")";
                      }
                    break;                  
                    case "located_in":
                      $solrQuery .= " ".$this->ws->attributesBooleanOperator." (located_in:".urlencode($this->escapeSolrValue($val)).")";
                    break;
                    
                    default:
                      $solrQuery .= " ".$this->ws->attributesBooleanOperator." (".$attribute.":".urlencode($this->escapeSolrValue($this->arrangeSolrValue($val))).")";
                    break;
                  }                
                  
                }
                else
                {
                  if(substr($solrQuery, strlen($solrQuery) - 3) != "fq=")
                  {
                    $solrQuery .= " ".$this->ws->attributesBooleanOperator." (";
                  }
                  else
                  {
                    $solrQuery .= "(";
                  }
                }
              }            

              $addOR = FALSE;
              $empty = TRUE;                
              
              // We have to detect if the fields are existing in Solr, otherwise Solr will throw
              // "undefined fields" errors, and there is no way to ignore them and process
              // the query anyway.
              if(!$coreAttr && array_search(urlencode($attribute), $indexedFields) !== FALSE)
              {
                $solrQuery .= "(".urlencode(urlencode($attribute)).":".urlencode($this->escapeSolrValue($this->arrangeSolrValue($val))).")";  
                $addOR = TRUE;
                $empty = FALSE;
              }

              if(array_search(urlencode($attribute."_attr_".$this->ws->lang.$singleValuedDesignator), $indexedFields) !== FALSE)
              {
                if($addOR)
                {
                  $solrQuery .= " OR ";
                }
                
                $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_".$this->ws->lang.$singleValuedDesignator.":".urlencode($this->escapeSolrValue($this->arrangeSolrValue($val))).")";
                $addOR = TRUE;
                $empty = FALSE;
              }

              if(array_search(urlencode($attribute."_attr_int".$singleValuedDesignator), $indexedFields) !== FALSE)
              {                  
                if($addOR)
                {
                  $solrQuery .= " OR ";
                }
                
                if(is_numeric($val))
                {
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_int".$singleValuedDesignator.":".$val.")";                      
                }
                else
                {
                  // Extract the FROM and TO numbers range
                  $numbers = explode(" TO ", trim(str_replace(" to ", " TO ", $val), "[]"));
                  
                  if($numbers[0] != "*")
                  {
                    if(!is_numeric($numbers[0]))
                    {
                      $this->ws->conneg->setStatus(400);
                      $this->ws->conneg->setStatusMsg("Bad Request");
                      $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
                      $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
                        $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, "",
                        $this->ws->errorMessenger->_306->level);
                      return;
                    }
                  }
                  
                  if($numbers[1] != "*")
                  {
                    if(!is_numeric($numbers[1]))
                    {
                      $this->ws->conneg->setStatus(400);
                      $this->ws->conneg->setStatusMsg("Bad Request");
                      $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
                      $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
                        $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, "",
                        $this->ws->errorMessenger->_306->level);
                      return;
                    }
                  } 
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_int".$singleValuedDesignator.":".urlencode("[".$numbers[0]." TO ".$numbers[1]."]").")";
                }
                  
                  
                $addOR = TRUE;
                $empty = FALSE;
              }   
              
              if(array_search(urlencode($attribute."_attr_float".$singleValuedDesignator), $indexedFields) !== FALSE)
              {                  
                if($addOR)
                {
                  $solrQuery .= " OR ";
                }
                      
                if(is_numeric($val))
                {
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_float".$singleValuedDesignator.":".$val.")";
                }
                else
                {                    
                  // Extract the FROM and TO numbers range
                  $numbers = explode(" TO ", trim(str_replace(" to ", " TO ", $val), "[]"));
                  
                  if($numbers[0] != "*")
                  {
                    if(!is_numeric($numbers[0]))
                    {
                      $this->ws->conneg->setStatus(400);
                      $this->ws->conneg->setStatusMsg("Bad Request");
                      $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
                      $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
                        $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, "",
                        $this->ws->errorMessenger->_306->level);
                      return;
                    }
                  }
                  
                  if($numbers[1] != "*")
                  {
                    if(!is_numeric($numbers[1]))
                    {
                      $this->ws->conneg->setStatus(400);
                      $this->ws->conneg->setStatusMsg("Bad Request");
                      $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
                      $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
                        $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, "",
                        $this->ws->errorMessenger->_306->level);
                      return;
                    }
                  } 
                  
                  $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_float".$singleValuedDesignator.":".urlencode("[".$numbers[0]." TO ".$numbers[1]."]").")";
                }
                $addOR = TRUE;
                $empty = FALSE;
              }
              
              if(array_search(urlencode($attribute."_attr_date".$singleValuedDesignator), $indexedFields) !== FALSE)
              {
                if($addOR)
                {
                  $solrQuery .= " OR ";
                }
                
                $dateFrom = "";
                $dateTo = "NOW";
                
                // Check if it is a range query
                if(substr($val, 0, 1) == "[" && substr($val, strlen($val) - 1, 1) == "]")
                {
                  // Extract the FROM and TO dates range
                  $dates = explode(" TO ", trim(str_replace(" to ", " TO ", $val), "[]"));
                  
                  if($dates[0] != "*")
                  {
                    $dateFrom = $this->safeDate($dates[0]);
                    
                    if($dateFrom === FALSE)
                    {
                      $this->ws->conneg->setStatus(400);
                      $this->ws->conneg->setStatusMsg("Bad Request");
                      $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
                      $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
                        $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, "",
                        $this->ws->errorMessenger->_305->level);
                      return;
                    }
                  }
                  
                  if($dates[1] != "*")
                  {
                    $dateTo = $this->safeDate($dates[1]);
                    
                    if($dateTo === FALSE)
                    {
                      $this->ws->conneg->setStatus(400);
                      $this->ws->conneg->setStatusMsg("Bad Request");
                      $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
                      $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
                        $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, "",
                        $this->ws->errorMessenger->_305->level);
                      return;
                    }                        
                  }
                }
                else
                {
                  // If no range is defined, we consider the input date to be the initial date to use
                  // until now.
                  $dateFrom = $this->safeDate($val);
                  
                  if($dateFrom === FALSE)
                  {
                    $this->ws->conneg->setStatus(400);
                    $this->ws->conneg->setStatusMsg("Bad Request");
                    $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
                    $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
                      $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, "",
                      $this->ws->errorMessenger->_305->level);
                    return;
                  }                       
                }
                
                $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_date".$singleValuedDesignator.":".urlencode("[".$dateFrom." TO ".$dateTo)."]".")";
                $addOR = TRUE;
                $empty = FALSE;
              }

              if(array_search(urlencode($attribute."_attr_obj_".$this->ws->lang.$singleValuedDesignator), $indexedFields) !== FALSE)
              {
                if($addOR)
                {
                  $solrQuery .= " OR ";
                }
                
                $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj_".$this->ws->lang.$singleValuedDesignator.":".urlencode($this->escapeSolrValue($val)).")";
                $addOR = TRUE;
                $empty = FALSE;
              }
              
              if(array_search(urlencode($attribute."_attr_obj_uri"), $indexedFields) !== FALSE)
              {
                if($addOR)
                {
                  $solrQuery .= " OR ";
                }
                
                $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj_uri:".urlencode($this->escapeURIValue($val)).")";
                $addOR = TRUE;
                $empty = FALSE;
              }   
              
              if($nbProcessed == 0)
              {
                if(!$coreAttr)
                {
                  if($empty)
                  {
                    $solrQuery = substr($solrQuery, 0, strlen($solrQuery) - 1);
                  }
                  else
                  {
                    $solrQuery .= ")";                
                  }                
                }
              }
              else
              {
                if(!$coreAttr)
                {
                  if(substr($solrQuery, strlen($solrQuery) - 4) != "fq=(")
                  {
                    if($empty)
                    {
                      $solrQuery = substr($solrQuery, 0, strlen($solrQuery) - 5);
                    }
                    else
                    {
                      $solrQuery .= ")";                
                    }
                  }
                  else
                  {
                    $solrQuery = substr($solrQuery, 0, strlen($solrQuery) - 1);
                  }
                }
              }            
                            
            }
            else
            {
              if($nbProcessed == 0)
              {
                $solrQuery .= "&fq=(attribute:%22" . urlencode($attribute) . "%22)";
              }
              else
              {
                $solrQuery .= " ".$this->ws->attributesBooleanOperator." (attribute:%22" . urlencode($attribute) . "%22)";
              }
            }

            $nbProcessed++;
          }
        }
        
        // Check if this query is geo-enabled and if a distance-filter is requested
        if($this->ws->geoEnabled && $this->ws->distanceFilter != "")
        {
          /*
            $params[0] == latitude
            $params[1] == longitude
            $params[2] == distance
            $params[3] == (0) distance in kilometers, (1) distance in miles
          */
          $params = explode(";", $this->ws->distanceFilter);
          
          $earthRadius = 6371;
          
          if($params[3] == 1)
          {
            $earthRadius = 3963.205;
          }
          
          $solrQuery .= "&fq={!frange l=0 u=".$params[2]."}hsin(".$params[0].",".$params[1].
                        " , lat_rad, long_rad, ".$earthRadius.")";
        }
        
        // Check if this query is geo-enabled and if a range-filter is requested
        if($this->ws->geoEnabled && $this->ws->rangeFilter != "")
        {
          /*
            $params[0] == latitude top-left
            $params[1] == longitude top-left
            $params[2] == latitude bottom-right
            $params[3] == longitude bottom-right
          */
          $params = explode(";", $this->ws->rangeFilter);
          
          // Make sure the ranges are respected according to the way the cartesian coordinate
          // system works.
          $p1 = $params[0];
          $p2 = $params[2];
          $p3 = $params[1];
          $p4 = $params[3];
          
          if($params[0] > $params[2])
          {
            $p1 = $params[2];
            $p2 = $params[0];
          }
          
          if($params[1] > $params[3])
          {
            $p3 = $params[3];
            $p4 = $params[1];
          }
          
          $solrQuery .= "&fq=lat:[".$p1." TO ".$p2."]&fq=long:[".$p3." TO ".$p4."]";
        }

        // Add the attribute/value aggregates if needed
        if(count($this->ws->aggregateAttributes) > 0 && strtolower($this->ws->includeAggregates) == "true") 
        {
          foreach($this->ws->aggregateAttributes as $attribute)
          {
            $solrQuery .= "&facet.field=".urlencode(urlencode($attribute));
            $solrQuery .= "&f.".urlencode(urlencode($attribute)).".facet.limit=".$this->ws->aggregateAttributesNb;
          }
        }
        
        // Only return these fields in the resultset
        if(count($this->ws->includeAttributesList) > 0)
        { 
          if(strtolower($this->ws->includeAttributesList[0]) == "none")
          {
            $solrQuery .= "&fl=";
            $solrQuery .= "uri ";
            $solrQuery .= "type ";
            $solrQuery .= "inferred_type ";
            $solrQuery .= "dataset ";
            $solrQuery .= "prefLabelAutocompletion_".$this->ws->lang."";
          }
          else
          {
            $solrQuery .= "&fl=";
            
            foreach($this->ws->includeAttributesList as $atl)
            {
              if($atl != "none")
              {
                $isCoreAttribute = $this->isCoreAttribute($atl, $atl);
                
                if(!$isCoreAttribute)
                {
                  $solrQuery .= urlencode(urlencode($atl))."_attr_".$this->ws->lang." ";
                  $solrQuery .= urlencode(urlencode($atl))."_attr_obj_".$this->ws->lang." ";
                  $solrQuery .= urlencode(urlencode($atl))."_attr_obj_uri ";
                }
                else
                {
                  $solrQuery .= $atl." ";
                }
              }
            }
            
            // Also add the core attributes to the mixte
            $solrQuery .= "uri ";
            $solrQuery .= "type ";
            $solrQuery .= "inferred_type ";
            $solrQuery .= "dataset ";
            $solrQuery .= "prefLabelAutocompletion_".$this->ws->lang."";
          }
        }
        
        // Remove possible left-over introduced by the procedure above for some rare usecases.
        $solrQuery = str_replace("fq= OR ", "fq=", $solrQuery);      
        $solrQuery = str_replace("fq= AND ", "fq=", $solrQuery);      

        // Set the default field of the search
        $solrQuery .= "&df=all_text_".$this->ws->lang;
        
        // The the sorting parameter
        if(count($this->ws->sort) > 0)
        {  
          $indexedFields = array();
          
          if(file_exists($this->ws->fields_index_folder."fieldsIndex.srz"))
          {
            $indexedFields = unserialize(file_get_contents($this->ws->fields_index_folder."fieldsIndex.srz"));
          
            $solrQuery .= "&sort=";
            
            foreach($this->ws->sort as $sortProperty => $order)
            {
              $lSortProperty = strtolower($sortProperty);
              
              if($lSortProperty == "preflabel")
              {
                $sortProperty = "prefLabelAutocompletion_".$this->ws->lang;
              }
              else if($lSortProperty == "type")
              {
                $sortProperty = "type_single_valued";
              }
              else if( $lSortProperty != "uri" &&
                       $lSortProperty != "dataset" &&
                       $lSortProperty != "score")
              {              
                $uSortProperty = urlencode($sortProperty);
                
                if(array_search($uSortProperty."_attr_date_single_valued", $indexedFields) !== FALSE)
                {
                  $sortProperty = urlencode($uSortProperty)."_attr_date_single_valued";
                }
                else if(array_search($uSortProperty."_attr_float_single_valued", $indexedFields) !== FALSE)
                {
                  $sortProperty = urlencode($uSortProperty)."_attr_float_single_valued";
                }
                else if(array_search($uSortProperty."_attr_int_single_valued", $indexedFields) !== FALSE)
                {
                  $sortProperty = urlencode($uSortProperty)."_attr_int_single_valued";
                }
                else if(array_search($uSortProperty."_attr_obj_".$this->ws->lang."_single_valued", $indexedFields) !== FALSE)
                {
                  $sortProperty = urlencode($uSortProperty)."_attr_obj_".$this->ws->lang."_single_valued";
                }
                else if(array_search($uSortProperty."_attr_".$this->ws->lang."_single_valued", $indexedFields) !== FALSE)
                {
                  $sortProperty = urlencode($uSortProperty)."_attr_".$this->ws->lang."_single_valued";
                }                             
              }
              
              $solrQuery .= $sortProperty." ".$order.",";
            }
            
            $solrQuery = rtrim($solrQuery, ",");
          }
        }
        
        if(!empty($this->ws->includeScores))
        {
          $solrQuery .= '&fl='.urlencode('* score');
        }
        
        // Specify the default search operator
        if($this->ws->defaultOperator != 'and')
        {
          if(strpos($this->ws->defaultOperator, '::') !== FALSE)
          {
            $orOp = explode('::', $this->ws->defaultOperator .= '&q.op=AND');
            
            if($orOp[0] == 'or')
            {
              $solrQuery .= '&q.op=OR';
              $solrQuery .= '&mm='.$orOp[1];
            }
          }
          else
          {
            if($this->ws->defaultOperator == 'or')
            {
              $solrQuery .= '&q.op=OR';
            }
          }
        }
        else
        {
          $solrQuery .= '&q.op=AND';
        }
        
        
        // Check if we enable the spellchecker
        if($this->ws->spellcheck === TRUE)
        {
           $solrQuery .= '&spellcheck=true';
        }
        
        // Specifies that the query parser is eDisMax
        $solrQuery .= '&defType=edismax';
    
        $resultset = $solr->select($solrQuery);
        
        if($resultset === FALSE)
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_311->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_311->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_311->name, $this->ws->errorMessenger->_311->description, $solr->errorMessage,
            $this->ws->errorMessenger->_311->level);
          return;          
        }

        // Create the internal representation of the resultset.      
        $domResultset = new DomDocument("1.0", "utf-8");

        $domResultset->loadXML($resultset);
        
        $xpath = new DOMXPath($domResultset);

        // Get the number of results
        $founds = $xpath->query("*[@numFound]");

        foreach($founds as $found)
        {
          $nbResources = $found->attributes->getNamedItem("numFound")->nodeValue;
          break;
        }
        
        // If no results are returned, check if spellchecking was enabled, if it was then
        // try to get the spell suggestions & the the collation
        if($nbResources <= 0 && $this->ws->spellcheck === TRUE)
        {
          // Try to get the collation          
          $collation = $xpath->query("//*/lst[@name='suggestions']//str[@name='collation']");
          
          if($collation->length > 0)
          {
            $collation = $collation->item(0)->nodeValue;
          }
          
          $subject = new Subject($this->ws->uri . "suggestions/" . md5(microtime()));
          $subject->setType(Namespaces::$wsf."SpellSuggestion");
          $subject->setDataAttribute(Namespaces::$wsf."collation", $collation);
          
          $this->ws->rset->addSubject($subject);    

          // Try to get the suggested terms
          $suggestedWords = $xpath->query("//*/lst[@name='suggestions']/lst");
          
          foreach($suggestedWords as $sw)
          {
            $misspelledWord = $sw->getAttribute('name');
            
            $suggestion = $xpath->query("arr[@name='suggestion']/lst", $sw);
            
            foreach($suggestion as $word_frequency)
            {
              $word = $word_frequency->getElementsByTagName('str')->item(0)->nodeValue;
              $frequency = $word_frequency->getElementsByTagName('int')->item(0)->nodeValue;
              
              $subject = new Subject($this->ws->uri . "suggestions/" . md5(microtime()));
              $subject->setType(Namespaces::$wsf."SpellSuggestion");
              $subject->setDataAttribute(Namespaces::$wsf."misspelledWord", $misspelledWord);
              $subject->setDataAttribute(Namespaces::$wsf."suggestion", $word);
              $subject->setDataAttribute(Namespaces::$wsf."frequency", $frequency);
              
              $this->ws->rset->addSubject($subject);                
            }
          }          
        }

        // Get all the "type" facets with their counts
        $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='type']/int");
        
        // Set types aggregates
        foreach($founds as $found)
        {
          $subject = new Subject($this->ws->uri . "aggregate/" . md5(microtime()));
          $subject->setType(Namespaces::$aggr."Aggregate");
          $subject->setObjectAttribute(Namespaces::$aggr."property", Namespaces::$rdf."type");
          $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
          $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
          $this->ws->rset->addSubject($subject);
        }

        // Set types aggregates for inferred types
        if(strtolower($this->ws->inference) == "on")
        {
          // Get all the "inferred_type" facets with their counts
          $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='inferred_type']/int");

          // Get types counts
          foreach($founds as $found)
          {
            $subject = new Subject($this->ws->uri . "aggregate/" . md5(microtime()));
            $subject->setType(Namespaces::$aggr."Aggregate");
            $subject->setObjectAttribute(Namespaces::$aggr."property", Namespaces::$rdf."type");
            $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
            $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
            $this->ws->rset->addSubject($subject);
          }
        }                  
        
        // Set the dataset aggregates
        $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='dataset']/int");

        foreach($founds as $found)
        {
          $subject = new Subject($this->ws->uri . "aggregate/" . md5(microtime()));
          $subject->setType(Namespaces::$aggr."Aggregate");
          $subject->setObjectAttribute(Namespaces::$aggr."property", Namespaces::$void."Dataset");
          $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
          $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
          $this->ws->rset->addSubject($subject);        
        }

        // Set the attributes aggregates
        $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='attribute']/int");

        foreach($founds as $found)
        {
          $subject = new Subject($this->ws->uri . "aggregate/" . md5(microtime()));
          $subject->setType(Namespaces::$aggr."Aggregate");
          $subject->setObjectAttribute(Namespaces::$aggr."property", Namespaces::$rdf."Property");
          $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
          $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
          $this->ws->rset->addSubject($subject);        
        }
        
        // Set all the attributes/values aggregates
        foreach($this->ws->aggregateAttributes as $attribute)
        {
          $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='".urlencode($attribute)."']/int");

          foreach($founds as $found)
          {
            $subject = new Subject($this->ws->uri . "aggregate/" . md5(microtime()));
            $subject->setType(Namespaces::$aggr."Aggregate");
            $subject->setObjectAttribute(Namespaces::$aggr."property", str_replace(array("_attr_uri_label_facets","_attr_facets", "_attr_obj_uri"), "", urldecode($attribute)));
            
            if($this->ws->aggregateAttributesObjectType == "uri")
            {          
              $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
            }
            elseif($this->ws->aggregateAttributesObjectType == "literal")
            {
              $subject->setDataAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
            }
            elseif($this->ws->aggregateAttributesObjectType == "uriliteral")
            {
              $values = explode("::", $found->attributes->getNamedItem("name")->nodeValue);
              
              $subject->setObjectAttribute(Namespaces::$aggr."object", $values[0]);
              $subject->setDataAttribute(Namespaces::$aggr."object", $values[1]);
            }
            
            $subject->setDataAttribute(Namespaces::$aggr."count", $found->nodeValue);
            $this->ws->rset->addSubject($subject);
          }          
        }


        // Set all the results
        $resultsDom = $xpath->query("//doc");

        foreach($resultsDom as $result)
        {
          // get URI
          $resultURI = $xpath->query("str[@name='uri']", $result);

          $uri = "";

          if($resultURI->length > 0)
          {
            $uri = $resultURI->item(0)->nodeValue;
          }
          else
          {
            continue;
          }
          
          $subject = new Subject($uri);
          
          // get Dataset URI
          $resultDatasetURI = $xpath->query("str[@name='dataset']", $result);

          if($resultDatasetURI->length > 0)
          {
            $subject->setObjectAttribute(Namespaces::$dcterms."isPartOf", $resultDatasetURI->item(0)->nodeValue);
          }                

          // get records preferred label
          $resultPrefLabelURI = $xpath->query("str[@name='prefLabel_".$this->ws->lang."']", $result);

          if($resultPrefLabelURI->length > 0)
          {
            $subject->setPrefLabel($resultPrefLabelURI->item(0)->nodeValue);
          }

          // get records aternative labels
          $resultAltLabelURI = $xpath->query("arr[@name='altLabel_".$this->ws->lang."']/str", $result);

          for($i = 0; $i < $resultAltLabelURI->length; ++$i) 
          {
            $subject->setAltLabel($resultAltLabelURI->item($i)->nodeValue);
          }
                  
          // Get possible Lat/Long and shapes descriptions
          
          // First check if there is a polygonCoordinates pr a polylineCoordinates attribute for that record
          // If there is one, then we simply ignore the lat/long coordinates since they come from these
          // attributes and that we don't want to duplicate that information.
          $skipLatLong = FALSE;
          
          $resultPolygonCoordinates = $xpath->query("arr[@name='polygonCoordinates']/str", $result);
          
          if($resultPolygonCoordinates->length > 0)
          {
            foreach($resultPolygonCoordinates as $value)
            {
              $subject->setDataAttribute(Namespaces::$sco."polygonCoordinates", $value->nodeValue);
            }            
            
            $skipLatLong = TRUE;
          }
          
          $resultPolylineCoordinates = $xpath->query("arr[@name='polylineCoordinates']/str", $result);
          
          if($resultPolylineCoordinates->length > 0)
          {
            foreach($resultPolylineCoordinates as $value)
            {
              $subject->setDataAttribute(Namespaces::$sco."polylineCoordinates", $value->nodeValue);
            }            
            
            $skipLatLong = TRUE;
          }          
          
          if(!$skipLatLong)
          {
            $resultDescriptionLat = $xpath->query("arr[@name='lat']/double", $result);

            if($resultDescriptionLat->length > 0)
            {
              $subject->setDataAttribute(Namespaces::$geo."lat", $resultDescriptionLat->item(0)->nodeValue);
            }

            $resultDescriptionLong = $xpath->query("arr[@name='long']/double", $result);

            if($resultDescriptionLong->length > 0)
            {
              $subject->setDataAttribute(Namespaces::$geo."long", $resultDescriptionLong->item(0)->nodeValue);
            }
          }
          
          // Set possible score
          if(!empty($this->ws->includeScores))
          {          
            $score = $xpath->query("float[@name='score']", $result);

            if($score->length > 0)
            {
              $subject->setDataAttribute(Namespaces::$wsf."score", $score->item(0)->nodeValue);
            }            
          }
          
          // get possible locatedIn URI(s)
          $resultLocatedIn = $xpath->query("arr[@name='located_in']/str", $result);

          if($resultLocatedIn->length > 0)
          {
            $subject->setObjectAttribute(Namespaces::$geoname."locatedIn", $resultLocatedIn->item(0)->nodeValue);
          }           

          // get records description
          $resultDescriptionURI = $xpath->query("arr[@name='description_".$this->ws->lang."']/str", $result);

          if($resultDescriptionURI->length > 0)
          {
            $subject->setDescription($resultDescriptionURI->item(0)->nodeValue);
          }

          // Get all dynamic fields attributes that are multi-valued
          $resultProperties = $xpath->query("arr", $result);

          $objectPropertyLabels = array();
          $objectPropertyUris = array();
          
          foreach($resultProperties as $property)
          {
            $attribute = $property->getAttribute("name");

            // Check what kind of attribute it is
            $attributeType = $this->getSolrAttributeType($attribute);
            
            // Get the URI of the attribute
            $attributeURI = urldecode(str_replace($attributeType, "", $attribute));
            
            // Exclude the attributes that have to be ignored by the Search endpoint
            // when creating the new resultset.
            if(array_search($attributeURI, $this->ws->searchExcludedAttributes) !== FALSE)
            {
              continue;
            }
            
            if($attributeURI == Namespaces::$rdf."type")
            {
              continue;
            }

            switch($attributeType)
            {
              case "_attr_".$this->ws->lang:
                $values = $property->getElementsByTagName("str");

                foreach($values as $value)
                {
                  $subject->setDataAttribute($attributeURI, $value->nodeValue);
                }
              break;

              case "_attr_date":
                $values = $property->getElementsByTagName("date");

                foreach($values as $value)
                {
                  $subject->setDataAttribute($attributeURI, $value->nodeValue, Datatypes::$date);
                }
              break;
              
              case "_attr_int":
                $values = $property->getElementsByTagName("int");

                foreach($values as $value)
                {
                  $subject->setDataAttribute($attributeURI, $value->nodeValue, Datatypes::$int);
                }
              break;

              case "_attr_float":
                $values = $property->getElementsByTagName("float");

                foreach($values as $value)
                {
                  $subject->setDataAttribute($attributeURI, $value->nodeValue, Datatypes::$float);
                }
              break;

              case "_attr_obj_".$this->ws->lang:
                $values = $property->getElementsByTagName("str");

                foreach($values as $value)
                {
                  if(!is_array($objectPropertyLabels[$attributeURI]))
                  {
                    $objectPropertyLabels[$attributeURI] = array();
                  }
                  
                  array_push($objectPropertyLabels[$attributeURI], $value->nodeValue);
                }
              break;

              case "_attr_obj_uri":
                $values = $property->getElementsByTagName("str");

                foreach($values as $value)
                {
                  if(!is_array($objectPropertyUris[$attributeURI]))
                  {
                    $objectPropertyUris[$attributeURI] = array();
                  }
                  
                  array_push($objectPropertyUris[$attributeURI], $value->nodeValue);
                }
              break;

              case "_reify_attr":
              case "_reify_attr_obj":
              case "_reify_obj":
              case "_reify_value_".$this->ws->lang: break;
            }
          }
          
          // Get all dynamic fields attributes that are single-valued and for which the value is a string
          $resultProperties = $xpath->query("str", $result);

          foreach($resultProperties as $property)
          {
            $attribute = $property->getAttribute("name");

            // Check what kind of attribute it is
            $attributeType = $this->getSolrAttributeType($attribute);
            
            // Get the URI of the attribute
            $attributeURI = urldecode(str_replace($attributeType, "", $attribute));

            if($attributeType == "_attr_".$this->ws->lang."_single_valued")
            {
              $subject->setDataAttribute($attributeURI, $property->nodeValue);
            }
          }          
          
          // Get all dynamic fields attributes that are single-valued and for which the value is a date
          $resultProperties = $xpath->query("date", $result);

          foreach($resultProperties as $property)
          {
            $attribute = $property->getAttribute("name");

            // Check what kind of attribute it is
            $attributeType = $this->getSolrAttributeType($attribute);
            
            // Get the URI of the attribute
            $attributeURI = urldecode(str_replace($attributeType, "", $attribute));
            
            if($attributeType == "_attr_date_single_valued")
            {
              $subject->setDataAttribute($attributeURI, $property->nodeValue, Datatypes::$date);
            }
          }          
          
          // Get all dynamic fields attributes that are single-valued and for which the value is a integer
          $resultProperties = $xpath->query("int", $result);

          foreach($resultProperties as $property)
          {
            $attribute = $property->getAttribute("name");

            // Check what kind of attribute it is
            $attributeType = $this->getSolrAttributeType($attribute);
            
            // Get the URI of the attribute
            $attributeURI = urldecode(str_replace($attributeType, "", $attribute));
            
            if($attributeType == "_attr_int_single_valued")
            {
              $subject->setDataAttribute($attributeURI, $property->nodeValue, Datatypes::$int);
            }
          } 
          
          // Get all dynamic fields attributes that are single-valued and for which the value is a float
          $resultProperties = $xpath->query("float", $result);

          foreach($resultProperties as $property)
          {
            $attribute = $property->getAttribute("name");

            // Check what kind of attribute it is
            $attributeType = $this->getSolrAttributeType($attribute);
            
            // Get the URI of the attribute
            $attributeURI = urldecode(str_replace($attributeType, "", $attribute));
            
            if($attributeType == "_attr_float_single_valued")
            {
              $subject->setDataAttribute($attributeURI, $property->nodeValue, Datatypes::$float);
            }
          } 
          
          foreach($objectPropertyUris as $attributeUri => $objectUris)
          {
            foreach($objectUris as $key => $objectUri)
            {
              if(isset($objectPropertyLabels[$attributeUri][$key]))
              {
                $subject->setObjectAttribute($attributeUri, $objectUri, Array(Namespaces::$wsf."objectLabel" => Array($label = $objectPropertyLabels[$attributeUri][$key])));
              }
              else
              {
                $subject->setObjectAttribute($attributeUri, $objectUri);
              }
            }
          }
          
          unset($objectPropertyUris);
          unset($objectPropertyLabels);

          // Get the types of the resource.
          $resultTypes = $xpath->query("arr[@name='type']/str", $result);

          for($i = 0; $i < $resultTypes->length; ++$i) 
          {
            if($resultTypes->item($i)->nodeValue != "-")
            {
              $subject->setType($resultTypes->item($i)->nodeValue);
            }  
          }
          
          $this->ws->rset->addSubject($subject, $resultDatasetURI->item(0)->nodeValue);
        }
      }     
    }
    
    private function getSolrAttributeType($attribute)
    {
      if(($pos = stripos($attribute, "_reify")) !== FALSE)
      {
        return(substr($attribute, $pos, strlen($attribute) - $pos));
      }
      elseif(($pos = stripos($attribute, "_attr")) !== FALSE)
      {
        return(substr($attribute, $pos, strlen($attribute) - $pos));
      }
      
      return("");
    }
  }
?>
