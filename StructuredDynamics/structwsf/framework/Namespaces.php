<?php

/*! @ingroup StructWSFPHPAPIFramework Framework of the structWSF PHP API library */
//@{

/*! @file \StructuredDynamics\structwsf\framework\Namespaces.php
    @brief  List of main ontologies used
 */

namespace StructuredDynamics\structwsf\framework;

/**
* List of main ontologies used 
* These are a list of static variables. This is used to get the URI of the ontologies from 
* anywhere in the code. Instead of writing the URi, we use these variables.
* 
* @author Frederick Giasson, Structured Dynamics LLC.
*/
class Namespaces
{
  public static $iron = "http://purl.org/ontology/iron#";
  public static $owl = "http://www.w3.org/2002/07/owl#";
  public static $rdf = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
  public static $rdfs = "http://www.w3.org/2000/01/rdf-schema#";
  public static $dcterms = "http://purl.org/dc/terms/";
  public static $dc = "http://purl.org/dc/elements/1.1/";
  public static $foaf = "http://xmlns.com/foaf/0.1/";
  public static $bibo = "http://purl.org/ontology/bibo/";
  public static $umbel = "http://umbel.org/umbel#";
  public static $dcam = "http://purl.org/dc/dcam/";
  public static $dctype = "http://purl.org/dc/dcmitype/";
  public static $cc = "http://creativecommons.org/ns#";
  public static $doap = "http://usefulinc.com/ns/doap#";
  public static $geo = "http://www.w3.org/2003/01/geo/wgs84_pos#";
  public static $geoname = "http://www.geonames.org/ontology#";
  public static $bio = "http://purl.org/vocab/bio/0.1/";
  public static $sioc = "http://rdfs.org/sioc/ns#";
  public static $skos_2004 = "http://www.w3.org/2004/02/skos/core#";
  public static $skos_2008 = "http://www.w3.org/2008/05/skos#";
  public static $umbel_ac = "http://umbel.org/umbel/ac/";
  public static $umbel_sc = "http://umbel.org/umbel/sc/";
  public static $umbel_rc = "http://umbel.org/umbel/rc/";
  public static $sco = "http://purl.org/ontology/sco#";
  public static $void = "http://rdfs.org/ns/void#";
  public static $wsf = "http://purl.org/ontology/wsf#";
  public static $aggr = "http://purl.org/ontology/aggregate#";
  public static $xsd = "http://www.w3.org/2001/XMLSchema#";
  public static $vann = "http://purl.org/vocab/vann/";
  public static $vs = "http://www.w3.org/2003/06/sw-vocab-status/ns#";  
  public static $cs = "http://purl.org/vocab/changeset/schema#";
  
  private $namespaces = array();
  
  function __construct()
  {
    $this->namespaces = $this->getNamespaces();  
  }  
  
  /**
  * Get the list of all properties's URI normally used to refer
  * to a label for a record
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public static function getLabelProperties()
  {
    return(array(
        Namespaces::$iron . "prefLabel",
        Namespaces::$iron . "altLabel",
        Namespaces::$dcterms . "title",
        Namespaces::$dc . "title",
        Namespaces::$doap . "name",
        Namespaces::$foaf . "name",
        Namespaces::$foaf . "givenName",
        Namespaces::$foaf . "family_name",
        Namespaces::$rdfs . "label",
        Namespaces::$skos_2004 . "prefLabel",
        Namespaces::$skos_2004 . "altLabel",
        Namespaces::$skos_2008 . "prefLabel",
        Namespaces::$skos_2008 . "altLabel",
        Namespaces::$geoname."name"));
  }  

  /**
  * Get the list of all properties's URI normally used to refer
  * to a preferred label for a record
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public static function getPrefLabelProperties()
  {
    return(array(
        Namespaces::$iron . "prefLabel",
        Namespaces::$dcterms . "title",
        Namespaces::$dc . "title",
        Namespaces::$doap . "name",
        Namespaces::$foaf . "name",
        Namespaces::$rdfs . "label",
        Namespaces::$skos_2004 . "prefLabel",
        Namespaces::$skos_2008 . "prefLabel",
        Namespaces::$geoname."name"));
  }  

  /**
  * Get the list of all properties's URI normally used to refer
  * to an alternative label for a record
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  
  public static function getAltLabelProperties()
  {
    return(array(
        Namespaces::$iron . "altLabel",
        Namespaces::$foaf . "givenName",
        Namespaces::$foaf . "family_name",
        Namespaces::$skos_2004 . "altLabel",
        Namespaces::$skos_2008 . "altLabel"));
  }  
          
  /**
  * Get the list of all properties's URI normally used to refer
  * to a description for a record
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */          
  public static function getDescriptionProperties()
  {
    return(array(
      Namespaces::$iron . "description",
      Namespaces::$dcterms . "description",
      Namespaces::$skos_2004 . "definition",
      Namespaces::$skos_2008 . "definition",
      Namespaces::$bio . "olb",
      Namespaces::$bibo . "abstract",
      Namespaces::$doap . "description",
      Namespaces::$geoname."name"
    ));    
  }  
  
  /**
  * Get the prefixed version of a URI. 
  * For example, "http://xmlns.com/foaf/0.1/Person" would become "foaf:Person"
  * 
  * @param mixed $uri URI to prefixize
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getPrefixedUri($uri)
  {
    // Find the base URI of the ontology
    $pos = strripos($uri, "#");

    if ($pos === FALSE)
    {
      $pos = strripos($uri, "/");
    }

    if ($pos !== FALSE)
    {
      $pos++;
    }

    // Save the URI of the ontology
    $onto = substr($uri, 0, $pos);

    // Save the URI of the class or property passed in parameter
    $resource = substr($uri, $pos, strlen($uri) - $pos);    
  
    foreach($this->namespaces as $prefix => $u)    
    {    
      if($onto === $u)
      {
        return($prefix.":".$resource);
      }
    }
    
    return($uri);
  }
  
  
  /**
  * Get the unprefixed version of a URI. 
  * For example, "foaf:Person" would become "http://xmlns.com/foaf/0.1/Person"
  * 
  * @param mixed $prefixedUri Prefixed URI
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getUnprefixedUri($prefixedUri)
  {
    $prefixedUri = trim($prefixedUri);
    
    $pos = strripos($prefixedUri, ":");
    
    if(!$pos)
    {
      return(FALSE);
    }
    
    // Make sure that we don't have an already unprefixed URI by check if a schema is used in the URI
    if(substr($prefixedUri, $pos, 3) == '://')
    {
      return($prefixedUri);
    }    

    $prefix = substr($prefixedUri, 0, $pos);
    $fragment = substr($prefixedUri, $pos + 1);
    
    $baseUri = $this->getUri($prefix);
    
    return($baseUri . $fragment);
  }
    
  
  /**                                        
  * Get the prefix representing the ontology where a URI come from.
  * For example, "http://xmlns.com/foaf/0.1/Person" would return "foaf"
  * 
  * @param mixed $uri URI to get the prefix from
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getPrefix($uri)
  {
    // Find the base URI of the ontology
    $pos = strripos($uri, "#");

    if ($pos === FALSE)
    {
      $pos = strripos($uri, "/");
    }

    if ($pos !== FALSE)
    {
      $pos++;
    }

    // Save the URI of the ontology
    $onto = substr($uri, 0, $pos);

    // Save the URI of the class or property passed in parameter
    $resource = substr($uri, $pos, strlen($uri) - $pos);    
  
    foreach($this->namespaces as $prefix => $uri)    
    {    
      if($onto === $uri)
      {
        return($prefix);
      }
    }
    
    return("");
  }   
  
  /**
  * Get the URI related to a prefix
  * 
  * @param $prefix Prefix for which you want its related URI
  * 
  * @return Return the URI of the prefix.
  */
  public function getUri($prefix)
  {
    if(isset($this->namespaces[$prefix]))
    {
      return($this->namespaces[$prefix]);
    }
    
    return(FALSE);
  }
  
  /**
  * Get an array of prefixes<->namespaces
  * 
  * @return return an associative array of namespace prefixes and their base URI
  */
  public static function getNamespaces()
  {
    $coreNamespaces = get_class_vars('\StructuredDynamics\structwsf\framework\Namespaces');
    
    unset($coreNamespaces['namespaces']);
    
    // Read custom namespaces
    $namespaces = array();
    
    if(($handle = @fopen(realpath(dirname(__FILE__))."/namespaces.csv", "r")) !== FALSE) 
    {
      while(($namespace = fgetcsv($handle)) !== FALSE) 
      {
        // Ensure we have two columns
        if(count($namespace) == 2)
        {
          // Only keep valie IRI
          if(Namespaces::isValidIRI($namespace[1]))
          {
            $namespaces[$namespace[0]] = $namespace[1];
          }
        }
      }
      
      fclose($handle);
    } 
    
    $namespaces = $coreNamespaces + $namespaces;   
    
    return($namespaces);
  }  
  
  /** Check if a given IRI is valid.
              
      @param $iri The IRI to validate

      @return returns true if the IRI is valid, false otherwise.
      
      @see http://stackoverflow.com/questions/4713216/what-is-the-rfc-complicant-and-working-regular-expression-to-check-if-a-string-i
  */  
  public static function isValidIRI($iri)
  {
    return((!filter_var($value, FILTER_VALIDATE_URL) ? FALSE : TRUE));
    //return((bool) preg_match('/^[a-z](?:[-a-z0-9\+\.])*:(?:\/\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:])*@)?(?:\[(?:(?:(?:[0-9a-f]{1,4}:){6}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|::(?:[0-9a-f]{1,4}:){5}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){4}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4}:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){3}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,2}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){2}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,3}[0-9a-f]{1,4})?::[0-9a-f]{1,4}:(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,4}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,5}[0-9a-f]{1,4})?::[0-9a-f]{1,4}|(?:(?:[0-9a-f]{1,4}:){0,6}[0-9a-f]{1,4})?::)|v[0-9a-f]+[-a-z0-9\._~!\$&\'\(\)\*\+,;=:]+)\]|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}|(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=@])*)(?::[0-9]*)?(?:\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))*)*|\/(?:(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))+)(?:\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))*)*)?|(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))+)(?:\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@]))*)*|(?!(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@])))(?:\?(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@])|[\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}|\x{100000}-\x{10FFFD}\/\?])*)?(?:\#(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\._~\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}!\$&\'\(\)\*\+,;=:@])|[\/\?])*)?$/iu', $iri));
  }
}

//@}

?>