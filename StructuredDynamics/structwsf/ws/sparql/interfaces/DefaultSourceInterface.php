<?php
  
  namespace StructuredDynamics\osf\ws\sparql\interfaces; 
  
  use \StructuredDynamics\osf\framework\Namespaces;  
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  use \StructuredDynamics\osf\framework\Subject;

  class DefaultSourceInterface extends SourceInterface
  {
    /** Sparql query */
    private $query = "";
    
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "3.0";
      
      $this->query = $this->ws->query;
    }

    /*
        Working with XML. Usage: 
        $xml=xml2ary(file_get_contents('1.xml'));
        $link=&$xml['ddd']['_c'];
        $link['twomore']=$link['onemore'];
        // ins2ary(); // dot not insert a link, and arrays with links inside!
        echo ary2xml($xml);
        
        from: http://mysrc.blogspot.com/2007/02/php-xml-to-array-and-backwards.html
    */

    // XML to Array
    private function xml2ary(&$string)
    {
      $parser = xml_parser_create();
      xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
      xml_parse_into_struct($parser, $string, $vals, $index);
      xml_parser_free($parser);

      $mnary = array();
      $ary = &$mnary;

      foreach($vals as $r)
      {
        $t = $r['tag'];

        if($r['type'] == 'open')
        {
          if(isset($ary[$t]))
          {
            if(isset($ary[$t][0]))$ary[$t][] = array();
            else $ary[$t] = array ($ary[$t], array());
            $cv = &$ary[$t][count($ary[$t]) - 1];
          }
          else $cv = &$ary[$t];

          if(isset($r['attributes']))
          {
            foreach($r['attributes'] as $k => $v)$cv['_a'][$k] = $v;
          }
          $cv['_c'] = array();
          $cv['_c']['_p'] = &$ary;
          $ary = &$cv['_c'];
        }
        elseif($r['type'] == 'complete')
        {
          if(isset($ary[$t]))
          { // same as open
            if(isset($ary[$t][0]))$ary[$t][] = array();
            else $ary[$t] = array ($ary[$t], array());
            $cv = &$ary[$t][count($ary[$t]) - 1];
          }
          else $cv = &$ary[$t];

          if(isset($r['attributes']))
          {
            foreach($r['attributes'] as $k => $v)$cv['_a'][$k] = $v;
          }
          $cv['_v'] = (isset($r['value']) ? $r['value'] : '');
        }
        elseif($r['type'] == 'close')
        {
          $ary = &$ary['_p'];
        }
      }

      $this->_del_p($mnary);
      return $mnary;
    }

    // _Internal: Remove recursion in result array
    private function _del_p(&$ary)
    {
      foreach($ary as $k => $v)
      {
        if($k === '_p')unset($ary[$k]);
        elseif(is_array($ary[$k]))$this->_del_p($ary[$k]);
      }
    }

    // Array to XML
    private function ary2xml($cary, $d = 0, $forcetag = '')
    {
      $res = array();

      foreach($cary as $tag => $r)
      {
        if(isset($r[0]))
        {
          $res[] = ary2xml($r, $d, $tag);
        }
        else
        {
          if($forcetag)$tag = $forcetag;
          $sp = str_repeat("\t", $d);
          $res[] = "$sp<$tag";

          if(isset($r['_a']))
          {
            foreach($r['_a'] as $at => $av)$res[] = " $at=\"$av\"";
          }
          $res[] = ">" . ((isset($r['_c'])) ? "\n" : '');

          if(isset($r['_c']))$res[] = ary2xml($r['_c'], $d + 1);
          elseif(isset($r['_v']))$res[] = $r['_v'];
          $res[] = (isset($r['_c']) ? $sp : '') . "</$tag>\n";
        }
      }
      return implode('', $res);
    }

    // Insert element into array
    private function ins2ary(&$ary, $element, $pos)
    {
      $ar1 = array_slice($ary, 0, $pos);
      $ar1[] = $element;
      $ary = array_merge($ar1, array_slice($ary, $pos));
    }    
    
    public function processInterface()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        $ch = curl_init();
        
        // Normalize the query to remove the return carriers and line feeds
        // This is performed to help matching the regular expressions patterns.
        $this->query = str_replace(array("\r", "\n"), " ", $this->query);
        
        // remove the possible starting "sparql"
        $this->query = preg_replace("/^[\s\t]*sparql[\s\t]*/Uim", "", $this->query);
        
        // Check if there is a prolog to this SPARQL query.
        
        // First check if there is a "base" declaration
        
        preg_match("/^[\s\t]*base[\s\t]*<.*>/Uim", $this->query, $matches, PREG_OFFSET_CAPTURE);
        
        $baseOffset = -1;
        if(count($matches) > 0)
        {
          $baseOffset = $matches[0][1] + strlen($matches[0][0]);
        }
        
        // Second check for all possible "prefix" clauses
        preg_match_all("/[\s\t]*prefix[\s\t]*.*:.*<.*>/Uim", $this->query, $matches, PREG_OFFSET_CAPTURE);       

        $lastPrefixOffset = -1;
        
        if(count($matches) > 0)
        {
          $lastPrefixOffset = $matches[0][count($matches[0]) - 1][1] + strlen($matches[0][count($matches[0]) - 1][0]);
        }
        
        $prologEndOffset = -1;
        
        if($lastPrefixOffset > -1)
        {
          $prologEndOffset = $lastPrefixOffset;
        }
        elseif($baseOffset > -1)
        {
          $prologEndOffset = $baseOffset;
        }

        $noPrologQuery = $this->query;
        if($prologEndOffset != -1)
        {
          $noPrologQuery = substr($this->query, $prologEndOffset);
        }
        
        // Now extract prefixes references
        $prefixes = array();
        preg_match_all("/[\s\t]*prefix[\s\t]*(.*):(.*)<(.*)>/Uim", $this->query, $matches, PREG_OFFSET_CAPTURE);       
        
        if(count($matches[0]) > 0)
        {
          for($i = 0; $i < count($matches[1]); $i++)
          {
            $p = str_replace(array(" ", " "), "", $matches[1][$i][0]).":".str_replace(array(" ", " "), "", $matches[2][$i][0]);
            $iri = $matches[3][$i][0];
            
            $prefixes[$p] = $iri;
          }
        }
        
        // Drop any SPARUL queries
        // Reference: http://www.w3.org/Submission/SPARQL-Update/
        if(preg_match_all("/^[\s\t]*modify[\s\t]*/Uim",$noPrologQuery , $matches) > 0 ||
           preg_match_all("/^[\s\t]*delete[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
           preg_match_all("/^[\s\t]*insert[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
           preg_match_all("/^[\s\t]*load[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
           preg_match_all("/^[\s\t]*clear[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
           preg_match_all("/^[\s\t]*create[\s\t]*/Uim", $noPrologQuery, $matches) > 0 ||
           preg_match_all("/^[\s\t]*drop[\s\t]*/Uim", $noPrologQuery, $matches) > 0)
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_203->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_203->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_203->name, $this->ws->errorMessenger->_203->description, "",
            $this->ws->errorMessenger->_203->level);

          return;               
        }

        // Detect any CONSTRUCT clause
        $this->ws->isConstructQuery = FALSE;
        if(preg_match_all("/^[\s\t]*construct[\s\t]*/Uim", $noPrologQuery, $matches) > 0)
        {
          $this->ws->isConstructQuery = TRUE;
          /*
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_204->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_204->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_204->name, $this->ws->errorMessenger->_204->description, "",
            $this->ws->errorMessenger->_204->level);

          return;               
          */
        }
        
        // Drop any SPARQL query with a GRAPH clause which are not bound by one, or a series, of FROM NAMED clauses

        if((preg_match_all("/[\s\t]*graph[\s\t]*</Uim", $noPrologQuery, $matches) > 0 ||
            preg_match_all("/[\s\t]*graph[\s\t]*\?/Uim", $noPrologQuery, $matches) > 0 ||
            preg_match_all("/[\s\t]*graph[\s\t]*\$/Uim", $noPrologQuery, $matches) > 0 ||
            preg_match_all("/[\s\t]*graph[\s\t]*[a-zA-Z0-9\-_]*:/Uim", $noPrologQuery, $matches) > 0) &&
           (preg_match_all("/([\s\t]*from[\s\t]*named[\s\t]*<(.*)>[\s\t]*)/Uim", $noPrologQuery, $matches) <= 0 &&
            preg_match_all("/[\s\t]*(from[\s\t]*named)[\s\t]*([^\s\t<]*):(.*)[\s\t]*/Uim", $noPrologQuery, $matches) <= 0))
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_205->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_205->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_205->name, $this->ws->errorMessenger->_205->description, "",
            $this->ws->errorMessenger->_205->level);

          return;               
        }
        
        $graphs = array();   
        
        // Validate DESCRIBE query.
        // The only thing we have to check here, is to get the graph IRI if the DESCRIBE is immediately using
        // IRIRef clause. Possibilities are:
        // "DESCRIBE <test>" -- IRI_REF
        // "DESCRIBE a:" -- PrefixedName
        
        $this->ws->isDescribeQuery = FALSE;
        if(preg_match("/^[\s\t]*describe[\s\t]*/Uim", $noPrologQuery, $matches) > 0)
        {
          $this->ws->isDescribeQuery = TRUE;
        }    
        
        preg_match_all("/^[\s\t]*describe[\s\t]*<(.*)>/Uim", $noPrologQuery, $matches);  
        
        if(count($matches[0]) > 0)
        {
          array_push($graphs, $matches[1][0]);    
        }
        
        preg_match_all("/^[\s\t]*describe[\s\t]*([^<\s\t]*):(.*)[\s\t]*/Uim", $noPrologQuery, $matches);
        
        if(count($matches[0]) > 0)
        {
          for($i = 0; $i < count($matches[0]); $i++)
          {
            $p = $matches[1][$i].":";
            
            if(isset($prefixes[$p]))
            {
              $d = $prefixes[$p].$matches[2][$i];
              array_push($graphs, $d);
            }
          }
        }       
        
        
        // Get all the "from" and "from named" clauses so that we validate if the user has access to them.

        // Check for the clauses that uses direct IRI_REF
        preg_match_all("/([\s\t]*from[\s\t]*<(.*)>[\s\t]*)/Uim", $noPrologQuery, $matches);

        foreach($matches[2] as $match)
        {
          array_push($graphs, $match);
        }

        preg_match_all("/([\s\t]*from[\s\t]*named[\s\t]*<(.*)>[\s\t]*)/Uim", $noPrologQuery, $matches);

        foreach($matches[2] as $match)
        {
          array_push($graphs, $match);
        }
        
        // Check for the clauses that uses PrefixedName
        
        preg_match_all("/[\s\t]*(from|from[\s\t]*named)[\s\t]*([^\s\t<]*):(.*)[\s\t]*/Uim", $noPrologQuery, $matches);

        if(count($matches[0]) > 0)
        {
          for($i = 0; $i < count($matches[0]); $i++)
          {
            $p = $matches[2][$i].":";
            
            if(isset($prefixes[$p]))
            {
              $d = $prefixes[$p].$matches[3][$i];
              array_push($graphs, $d);
            }
          }
        }   
        
        
        if($this->ws->dataset == "" && count($graphs) <= 0)
        {
          $this->ws->conneg->setStatus(400);
          $this->ws->conneg->setStatusMsg("Bad Request");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_201->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_201->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_201->name, $this->ws->errorMessenger->_201->description, "",
            $this->ws->errorMessenger->_201->level);

          return;
        }      

        
        // Validate all graphs of the query for the IP of the requester of this query. 
        // If one of the graph is not accessible to the user, we just return
        // an error for this SPARQL query.
        $datasets = array();
        foreach($graphs as $graph)
        {
          if(substr($graph, strlen($graph) - 12, 12) == "reification/")
          {
            $graph = substr($graph, 0, strlen($graph) - 12);
          }
          
          $datasets[] = $graph;
        }        
        
        if(!$this->ws->validateUserAccess($datasets))
        {
          return;
        }

        // Determine the query format
        $queryFormat = "";

        if($this->ws->conneg->getMime() == "application/sparql-results+json" || 
           $this->ws->conneg->getMime() == "application/sparql-results+xml" || 
           $this->ws->conneg->getMime() == "text/html" ||
           $this->ws->isDescribeQuery === TRUE ||
           $this->ws->isConstructQuery === TRUE)
        {
          $queryFormat = $this->ws->conneg->getMime();
        }
        elseif($this->ws->conneg->getMime() == "text/xml" || 
               $this->ws->conneg->getMime() == "application/json" || 
               $this->ws->conneg->getMime() == "application/rdf+xml" || 
               $this->ws->conneg->getMime() == "application/rdf+n3" ||
               $this->ws->conneg->getMime() == "application/iron+json" ||
               $this->ws->conneg->getMime() == "application/iron+csv")
        {
          $queryFormat = "application/sparql-results+xml";
        }      
        
        if($this->ws->memcached_enabled)
        {
          $key = $this->ws->generateCacheKey('sparql', array(
            $this->ws->dataset,
            $this->query,
            $this->ws->conneg->getMime()
          ));
          
          if($return = $this->ws->memcached->get($key))
          {
            if($queryFormat !== 'application/sparql-results+xml')
            {
              $this->ws->sparqlContent = $return;
            }
            else
            {
              $this->ws->setResultset($return);
              return;
            }
          }
        }
        
        if(empty($this->ws->sparqlContent))
        {          
          curl_setopt($ch, CURLOPT_URL,
            $this->ws->db_host . ":" . $this->ws->triplestore_port . "/sparql?default-graph-uri=" . urlencode($this->ws->dataset) . "&query="
            . urlencode($this->query) . "&format=" . urlencode($queryFormat));

          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_HEADER, TRUE);

          $xml_data = curl_exec($ch);

          $header = substr($xml_data, 0, strpos($xml_data, "\r\n\r\n"));

          $data =
            substr($xml_data, strpos($xml_data, "\r\n\r\n") + 4, strlen($xml_data) - (strpos($xml_data, "\r\n\r\n") - 4));

          curl_close($ch);

          // check returned message

          $httpMsgNum = substr($header, 9, 3);
          $httpMsg = substr($header, 13, strpos($header, "\r\n") - 13);

          if($httpMsgNum == "200")
          {
            $this->ws->sparqlContent = $data;
            
            if($this->ws->memcached_enabled)
            {
              $this->ws->memcached->set($key, $this->ws->sparqlContent, NULL, $this->ws->memcached_sparql_expire);
            }     
          }
          else
          {
            $this->ws->conneg->setStatus($httpMsgNum);
            $this->ws->conneg->setStatusMsg($httpMsg);
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_300->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_300->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_300 > name, $this->ws->errorMessenger->_300->description, $data,
              $this->ws->errorMessenger->_300->level);

            $this->ws->sparqlContent = "";
            return;
          }
        }

        // If a DESCRIBE query as been requested by the user, then we simply returns what is returned by
        // the triple store. We don't have any convertion to do here.
        if($this->ws->isDescribeQuery === TRUE)
        {
           return;
        }

        // If a CONSTRUCT query as been requested by the user, then we simply returns what is returned by
        // the triple store. We don't have any convertion to do here.
        if($this->ws->isConstructQuery === TRUE)
        {
           return;
        }
        
        if($this->ws->conneg->getMime() == "text/xml" || 
           $this->ws->conneg->getMime() == "application/rdf+n3" || 
           $this->ws->conneg->getMime() == "application/rdf+xml" || 
           $this->ws->conneg->getMime() == "application/json" ||
           $this->ws->conneg->getMime() == "application/iron+json" ||
           $this->ws->conneg->getMime() == "application/iron+csv")
        {
          // Read the XML file and populate the recordInstances variables

          $xml = $this->xml2ary($this->ws->sparqlContent);
       
          if(isset($xml["sparql"]["_c"]["results"]["_c"]["result"]))
          {
            $currentSubjectUri = "";
            $subject = null;
            $sourceDataset = "";
            $isPartOfFound = FALSE;
            $g;

            foreach($xml["sparql"]["_c"]["results"]["_c"]["result"] as $result)
            {
              $s = "";
              $p = "";
              $o = "";
              $g = "";
              
              $valueBoundType = "";

              foreach($result["_c"]["binding"] as $binding)
              {
                $boundVariable = $binding["_a"]["name"];

                $keys = array_keys($binding["_c"]);

                $boundType = $keys[0];
                $boundValue = $binding["_c"][$boundType]["_v"];
                
                switch($boundVariable)
                {
                  case "s":
                    $s = $boundValue;
                  break;

                  case "p":
                    $p = $boundValue;
                    
                    if($p == Namespaces::$dcterms."isPartOf")
                    {
                      $isPartOfFound = TRUE;
                    }
                  break;

                  case "o":
                    $o = $boundValue;
                    $valueBoundType = $boundType;
                  break;

                  case "g":
                    $g = $boundValue;
                  break;
                }
              }
              
              if($currentSubject != $s)
              {
                if($subject != null)
                {
                  if($g != "" && $isPartOfFound === FALSE)
                  {
                    $subject->setObjectAttribute(Namespaces::$dcterms."isPartOf", $g);
                    $isPartOfFound = FALSE;
                  }
                  
                  $this->ws->rset->addSubject($subject);
                }
                
                $subject = new Subject($s);
                
                $currentSubject = $s;
              }

              
              // process URI
              if($valueBoundType == "uri" ||
                 $valueBoundType == "bnode")
              {
                if($p == Namespaces::$rdf."type")
                {
                  $subject->setType($o);
                }
                else
                {
                  $subject->setObjectAttribute($p, $o);
                }
              }

              // Process Literal
              if($valueBoundType == "literal")
              {
                $subject->setDataAttribute($p, $o);
              }            
            }
              
            // Add the last subject to the resultset.
            if($subject != null)
            {
              if($g != "" && $isPartOfFound === FALSE)
              {
                $subject->setObjectAttribute(Namespaces::$dcterms."isPartOf", $g);
                $isPartOfFound = FALSE;
              }          
              
              $this->ws->rset->addSubject($subject);          
            }
          }
          
          if($this->ws->memcached_enabled)
          {
            $this->ws->memcached->set($key, $this->ws->rset, NULL, $this->ws->memcached_sparql_expire);
          }     
          
          if(count($this->ws->rset->getResultset()) <= 0)
          {
            $this->ws->conneg->setStatus(400);
            $this->ws->conneg->setStatusMsg("Bad Request");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_301->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, "",
              $this->ws->errorMessenger->_301->level);
          }
        }
      }   
    }
  }
?>
