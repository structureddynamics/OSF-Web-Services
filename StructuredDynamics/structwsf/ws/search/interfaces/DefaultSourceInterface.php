<?php
  
  namespace StructuredDynamics\structwsf\ws\search\interfaces; 
  
  use \StructuredDynamics\structwsf\framework\Namespaces;  
  use \StructuredDynamics\structwsf\ws\framework\SourceInterface;
  use \StructuredDynamics\structwsf\ws\framework\ProcessorXML;
  use \StructuredDynamics\structwsf\ws\framework\Solr;
  use \StructuredDynamics\structwsf\framework\Subject;
  use \StructuredDynamics\structwsf\ws\auth\lister\AuthLister;
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
        
        $specialChars = array('"', 'AND', 'OR', '?', '*', '~', '+', '-', 'NOT', '&&', '||', '!', '^');
        
        if($this->ws->query != "")
        {      
          /* 
            Check if characters of the Solr query language is used for this query. If some does, we *only* take
            them to create the query.
          */
          $useLuceneSyntax = FALSE;
          
          foreach($specialChars as $char)
          {
            if(strpos($this->ws->query, $char) !== FALSE)
            {
              $useLuceneSyntax = TRUE;
              break;          
            }
          }
          
          if(!$useLuceneSyntax)
          {
            $queryParam = "%22" . urlencode(implode(" +", explode(" ", $this->escapeSolrValue($this->ws->query)))) . "%22~5";
          }
          else
          {
            $queryParam = urlencode($this->ws->query);
          }        
        }

        $distanceQueryRevelencyBooster = "";
        
        if($this->ws->geoEnabled && isset($this->ws->resultsLocationAggregator[0]) && 
           isset($this->ws->resultsLocationAggregator[1]) && ($this->ws->distanceFilter || $this->ws->rangeFilter))
        {
          // Here, "^0" is added to zero-out the boost (revelency) value of the keyword
          // in this query, we simply want to aggregate the results related to their
          // distance of the center point.
          $distanceQueryRevelencyBooster = '^0 AND _val_:"recip(dist(2, lat, long, '.$this->ws->resultsLocationAggregator[0].', '.$this->ws->resultsLocationAggregator[1].'), 1, 1, 0)"^100';  
        }
        
        if(strtolower($this->ws->datasets) == "all")
        {
          $datasetList = "";

          $solrQuery = "q=$queryParam$distanceQueryRevelencyBooster&start=" . $this->ws->page . "&rows=" . $this->ws->items
            . (strtolower($this->ws->includeAggregates) == "true" ? "&facet=true&facet.sort=count&facet.limit=-1&facet.field=type" .
            "&facet.field=attribute" . (strtolower($this->ws->inference) == "on" ? "&facet.field=inferred_type" : "") . "&" .
            "facet.field=dataset&facet.mincount=1" : "");

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

          $solrQuery = "q=$queryParam$distanceQueryRevelencyBooster&start=" . $this->ws->page . "&rows=" . $this->ws->items
            . (strtolower($this->ws->includeAggregates) == "true" ? "&facet=true&facet.sort=count&facet.limit=-1&facet.field=type" .
            "&facet.field=attribute" . (strtolower($this->ws->inference) == "on" ? "&facet.field=inferred_type" : "") . "&" .
            "facet.field=dataset&facet.mincount=1" : "");

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
        
        if($this->ws->attributes != "all")
        {
          // Lets include the information to facet per type.
          $attributes = explode(";", $this->ws->attributes);

          $nbProcessed = 0;
          
          if(file_exists($this->ws->fields_index_folder."fieldsIndex.srz"))
          {
            $indexedFields = unserialize(file_get_contents($this->ws->fields_index_folder."fieldsIndex.srz"));
          }
          else
          {
            $indexedFields = array();
          }

          foreach($attributes as $attribute)
          {
            $attributeValue = explode("::", $attribute);
            $attribute = urldecode($attributeValue[0]);

            if(isset($attributeValue[1]) && $attributeValue[1] != "")
            {
              // Fix the reference to some of the core attributes
              $coreAttr = FALSE;
              switch($attribute)
              {
                case Namespaces::$iron."prefLabel":
                  $attribute = "prefLabel";
                  $coreAttr = TRUE;
                  
                  // Check if we are performing an autocompletion task on the pref label
                  $label = urldecode($attributeValue[1]);
                  if(substr($label, strlen($label) - 2) == "**")
                  {
                    $attribute = "prefLabelAutocompletion";
                    $attributeValue[1] = urlencode(str_replace(" ", "\\ ", substr($label, 0, strlen($label) -1)));
                  }
                break;
                
                case Namespaces::$iron."altLabel":
                  $attribute = "altLabel";
                  $coreAttr = TRUE;
                break;
                
                case Namespaces::$iron."description":
                  $attribute = "description";
                  $coreAttr = TRUE;
                break;
                
                case Namespaces::$geo."lat":
                  if(!is_numeric(urldecode($attributeValue[1])))
                  {
                    // If the value is not numeric, we skip that attribute/value. 
                    // Otherwise an exception will be raised by Solr.
                    continue;
                  }
                                
                  $attribute = "lat";
                  $coreAttr = TRUE;
                break;
                
                case Namespaces::$geo."long":
                  if(!is_numeric(urldecode($attributeValue[1])))
                  {
                    // If the value is not numeric, we skip that attribute/value. 
                    // Otherwise an exception will be raised by Solr.
                    continue;
                  }
                  
                  $attribute = "long";
                  $coreAttr = TRUE;
                break;
                
                case Namespaces::$sco."polygonCoordinates":              
                  $attribute = "polygonCoordinates";
                  $coreAttr = TRUE;
                break;
                
                case Namespaces::$sco."polylineCoordinates":
                  $attribute = "polylineCoordinates";
                  $coreAttr = TRUE;
                break;
                
                case Namespaces::$rdf."type":
                  $attribute = "type";
                  $coreAttr = TRUE;
                break;
                
                case Namespaces::$geonames."locatedIn":
                  $attribute = "located_in";
                  $coreAttr = TRUE;
                break;
              }            

              // A filtering value as been defined for this attribute.
              $val = urldecode($attributeValue[1]);
              
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
                      $solrQuery .= "&fq=(".$attribute.":".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
                    break;
                  }
                }
                else
                {
                  $solrQuery .= "&fq=(";
                  
                  $addOR = FALSE;
                  $empty = TRUE;                
                  
                  // We have to detect if the fields are existing in Solr, otherwise Solr will throw
                  // "undefined fields" errors, and there is no way to ignore them and process
                  // the query anyway.
                  if(array_search(urlencode($attribute), $indexedFields) !== FALSE)
                  {
                    $solrQuery .= "(".urlencode(urlencode($attribute)).":".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";  
                    $addOR = TRUE;
                    $empty = FALSE;
                  }

                  if(array_search(urlencode($attribute."_attr"), $indexedFields) !== FALSE)
                  {
                    if($addOR)
                    {
                      $solrQuery .= " OR ";
                    }
                    
                    $solrQuery .= "(".urlencode(urlencode($attribute))."_attr:".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
                    $addOR = TRUE;
                    $empty = FALSE;
                  }

                  if(array_search(urlencode($attribute."_attr_obj"), $indexedFields) !== FALSE)
                  {
                    if($addOR)
                    {
                      $solrQuery .= " OR ";
                    }
                    
                    $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj:".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
                    $addOR = TRUE;
                    $empty = FALSE;
                  }
                  
                  if(array_search(urlencode($attribute."_attr_obj_uri"), $indexedFields) !== FALSE)
                  {
                    if($addOR)
                    {
                      $solrQuery .= " OR ";
                    }
                    
                    $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj_uri:".urlencode($this->escapeSolrValue($val)).")";
                    $addOR = TRUE;
                    $empty = FALSE;
                  }                
                  
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
                      $solrQuery .= " ".$this->ws->attributesBooleanOperator." (".$attribute.":".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
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
                  
                  $addOR = FALSE;
                  $empty = TRUE;
                  
                  // We have to detect if the fields are existing in Solr, otherwise Solr will throw
                  // "undefined fields" errors, and there is no way to ignore them and process
                  // the query anyway.
                  if(array_search(urlencode($attribute), $indexedFields) !== FALSE)
                  {
                    $solrQuery .= "(".urlencode(urlencode($attribute)).":".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
                    $addOR = TRUE;
                    $empty = FALSE;
                  }

                  if(array_search(urlencode($attribute."_attr"), $indexedFields) !== FALSE)
                  {
                    if($addOR)
                    {
                      $solrQuery .= " OR ";
                    }
                    
                    $solrQuery .= "(".urlencode(urlencode($attribute))."_attr:".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
                    $addOR = TRUE;
                    $empty = FALSE;
                  }

                  if(array_search(urlencode($attribute."_attr_obj"), $indexedFields) !== FALSE)
                  {
                    if($addOR)
                    {
                      $solrQuery .= " OR ";
                    }
                    
                    $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj:".urlencode(preg_replace("/[^A-Za-z0-9\.\s\*\\\]/", " ", $val)).")";
                    $addOR = TRUE;
                    $empty = FALSE;
                  }

                  if(array_search(urlencode($attribute."_attr_obj_uri"), $indexedFields) !== FALSE)
                  {
                    if($addOR)
                    {
                      $solrQuery .= " OR ";
                    }
                    
                    $solrQuery .= "(".urlencode(urlencode($attribute))."_attr_obj_uri:".urlencode($this->escapeSolrValue($val)).")";
                    $addOR = TRUE;
                    $empty = FALSE;
                  }

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
            $solrQuery .= "&facet.field=".urlencode($attribute);
            $solrQuery .= "&f.".urlencode($attribute).".facet.limit=".$this->ws->aggregateAttributesNb;
          }
        }
        
        // Only return these fields in the resultset
        if(count($this->ws->includeAttributesList) > 0)
        {
          $solrQuery .= "&fl=";
          
          foreach($this->ws->includeAttributesList as $atl)
          {
            $solrQuery .= urlencode(urlencode($atl))."_attr ";
            $solrQuery .= urlencode(urlencode($atl))."_attr_obj ";
            $solrQuery .= urlencode(urlencode($atl))."_attr_obj_uri ";
          }
          
          // Also add the core attributes to the mixte
          $solrQuery .= "prefLabel ";
          $solrQuery .= "altLabel ";
          $solrQuery .= "description ";
          $solrQuery .= "lat ";
          $solrQuery .= "long ";
          $solrQuery .= "polygonCoordinates ";
          $solrQuery .= "polylineCoordinates ";
          $solrQuery .= "type ";
          $solrQuery .= "uri ";
          $solrQuery .= "locatedIn ";
          $solrQuery .= "dataset ";
          $solrQuery .= "prefURL ";
          $solrQuery .= "geohash ";
          $solrQuery .= "inferred_type ";
          $solrQuery .= "prefLabelAutocompletion";
          
        }
        
        // Remove possible left-over introduced by the procedure above for some rare usecases.
        $solrQuery = str_replace("fq= OR ", "fq=", $solrQuery);      
        $solrQuery = str_replace("fq= AND ", "fq=", $solrQuery);      

        $resultset = $solr->select($solrQuery);

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
          $founds = $xpath->query("//*/lst[@name='facet_fields']//lst[@name='$attribute']/int");

          foreach($founds as $found)
          {
            $subject = new Subject($this->ws->uri . "aggregate/" . md5(microtime()));
            $subject->setType(Namespaces::$aggr."Aggregate");
            $subject->setObjectAttribute(Namespaces::$aggr."property", str_replace(array("_attr_facets", "_attr_obj_uri"), "", urldecode($attribute)));
            
            if($this->ws->aggregateAttributesObjectType == "uri")
            {          
              $subject->setObjectAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
            }
            else
            {
              $subject->setDataAttribute(Namespaces::$aggr."object", $found->attributes->getNamedItem("name")->nodeValue);
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
          $resultURI = $xpath->query("arr[@name='uri']/str", $result);

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
          $resultDatasetURI = $xpath->query("arr[@name='dataset']/str", $result);

          if($resultDatasetURI->length > 0)
          {
            $subject->setObjectAttribute(Namespaces::$dcterms."isPartOf", $resultDatasetURI->item(0)->nodeValue);
          }                

          // get records preferred label
          $resultPrefLabelURI = $xpath->query("arr[@name='prefLabel']/str", $result);

          if($resultPrefLabelURI->length > 0)
          {
            $subject->setPrefLabel($resultPrefLabelURI->item(0)->nodeValue);
          }

          // get records aternative labels
          $resultAltLabelURI = $xpath->query("arr[@name='altLabel']/str", $result);

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
          
          // get possible locatedIn URI(s)
          $resultLocatedIn = $xpath->query("arr[@name='located_in']/str", $result);

          if($resultLocatedIn->length > 0)
          {
            $subject->setObjectAttribute(Namespaces::$geonames."locatedIn", $resultLocatedIn->item(0)->nodeValue);
          }           

          // get records description
          $resultDescriptionURI = $xpath->query("arr[@name='description']/str", $result);

          if($resultDescriptionURI->length > 0)
          {
            $subject->setDescription($resultDescriptionURI->item(0)->nodeValue);
          }

          // Get all dynamic fields attributes.
          $resultProperties = $xpath->query("arr", $result);

          $objectPropertyLabels = array();
          $objectPropertyUris = array();
          
          foreach($resultProperties as $property)
          {
            $attribute = $property->getAttribute("name");

            // Check what kind of attribute it is
            $attributeType = "";

            if(($pos = stripos($attribute, "_reify")) !== FALSE)
            {
              $attributeType = substr($attribute, $pos, strlen($attribute) - $pos);
            }
            elseif(($pos = stripos($attribute, "_attr")) !== FALSE)
            {
              $attributeType = substr($attribute, $pos, strlen($attribute) - $pos);
            }

            // Get the URI of the attribute
            $attributeURI = urldecode(str_replace($attributeType, "", $attribute));

            switch($attributeType)
            {
              case "_attr":
                $values = $property->getElementsByTagName("str");

                foreach($values as $value)
                {
                  $subject->setDataAttribute($attributeURI, $value->nodeValue);
                }
              break;

              case "_attr_obj":
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
              case "_reify_value": break;
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
  }
?>
