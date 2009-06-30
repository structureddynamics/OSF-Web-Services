<?php

/*! @ingroup WsFramework Framework for the Web Services */
//@{ 

/*! @file \ws\framework\Namespaces.php
	 @brief List of main ontologies used on the node
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
 */


/*!	@brief List of main ontologies used on the node
		@details These are a list of static variables. This is used to get the URI of the ontologies from anywhere in the code. Instead of writing the URi, we use these variables.
						
		\n

		@author Frederick Giasson, Structured Dynamics LLC.
	
		\n\n\n
*/	
class Namespaces
{
	public static $foaf = "http://xmlns.com/foaf/0.1/";
	public static $rss  = "http://purl.org/rss/1.0/";
	public static $sioc = "http://rdfs.org/sioc/ns#";
	public static $doap = "http://usefulinc.com/ns/doap#";
	public static $geo = "http://www.w3.org/2003/01/geo/wgs84_pos#";
	public static $geonames = "http://www.geonames.org/ontology#";
	public static $cc = "http://creativecommons.org/ns#";
	public static $cyc = "http://sw.cyc.com/2006/07/27/cyc/";
	public static $umbel_sc = "http://umbel.org/umbel/sc/";
	public static $umbel_ac = "http://umbel.org/umbel/ac/";
	public static $umbel = "http://umbel.org/umbel#";
	public static $rdfs = "http://www.w3.org/2000/01/rdf-schema#";
	public static $rdf = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
	public static $owl = "http://www.w3.org/2002/07/owl#";
	public static $dcterms = "http://purl.org/dc/terms/";
	public static $skos_2004 = "http://www.w3.org/2004/02/skos/core#";
	public static $skos_2008 = "http://www.w3.org/2008/05/skos#";
	public static $po = "http://purl.org/ontology/po/";
	public static $event = "http://purl.org/NET/c4dm/event.owl#";
	public static $frbr = "http://purl.org/vocab/frbr/core#";
	public static $mo = "http://purl.org/ontology/mo/";
	public static $bibo = "http://purl.org/ontology/bibo/";
	public static $bkn = "http://purl.org/ontology/bkn#";
	public static $bkn_temp = "http://purl.org/ontology/bkn/temp#";
	public static $bkn_base = "http://purl.org/ontology/bkn/base/";
	public static $bio = "http://purl.org/vocab/bio/0.1/";
	public static $address = "http://schemas.talis.com/2005/address/schema#";
}

//@} 


?>