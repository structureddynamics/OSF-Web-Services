<?php

	/*! @ingroup WsConverterIrv */
	//@{ 

	/*! @file \ws\converter\irv\LinkageSchema.php
		 @brief Define an linkage schema item
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */


	/*!	 @brief Linkage Schema item description
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class LinkageSchema
{
	public $version;
	public $linkedFormat;
	public $prefixes;
	public $propertyX = array();
	public $typeX = array();
	
	function __construct(){}
	
	function __destruct(){}
	
	public function setVersion($version)
	{
		$this->version = $version;
	}

	public function setLinkedFormat($linkedFormat)
	{
		$this->linkedFormat = $linkedFormat;
	}
	
	public function setPrefix($prefix, $uri)
	{
		if(!is_array($this->prefixes))
		{		
			$this->prefixes = array($prefix => $uri);
		}
		else
		{
			$this->prefixes[$prefix] = $uri;
		}
	}

	public function setPropertyX($property, $mapTo, &$error)
	{
		$this->addProperty($this->propertyX[$property], $mapTo, $error);			
	}

	public function setTypeX($type, $mapTo, $add, &$error)
	{
		$this->addType($this->typeX[$type], $mapTo, $add, $error);			
	}

	private function addProperty(&$property, $mapTo, &$error)
	{
		// Check for a prefix to create the full URI of the type
		$maptToUri = $mapTo;
		
		if(substr($mapTo, 0, 7) != "http://" && ($pos = strpos($mapTo, ":")) !== FALSE)
		{
			$prefix = substr($mapTo, 0, $pos);
			
			if(!isset($this->prefixes[$prefix]))
			{
				$error = "The prefix used '$prefix:' is undefined in the linkage file.";
				return(FALSE);
			}
			else
			{
				$maptToUri = $this->prefixes[$prefix].substr($mapTo, $pos + 1, strlen($mapTo) - ($pos + 1));
			}
		}

		if(!is_array($property))
		{
			$property = array(array("mapTo" => $maptToUri));
		}
		else
		{
			// Make sure the property doesn't already exist
			$reject = FALSE;
			foreach($property as $map)
			{
				if($map["mapTo"] == $maptToUri)
				{
					$reject = TRUE;
					break;
				}
			}
			
			if($reject === FALSE)
			{
				array_push($property, array("mapTo" => $maptToUri));
			}
		}
	}
	
	private function addType(&$type, $mapTo, $add, &$error)
	{
		// case unsensitive;
		$type = strtolower($type);
		
		// Check for a prefix to create the full URI of the type
		$mapToUri = $mapTo;

		if(substr($mapTo, 0, 7) != "http://" && ($pos = strpos($mapTo, ":")) !== FALSE)
		{
			$prefix = substr($mapTo, 0, $pos);
			if(!isset($this->prefixes[$prefix]))
			{
				$error = "The prefix used '$prefix:' is undefined in the linkage file.";
				return(FALSE);
			}
			else
			{
				$mapToUri = $this->prefixes[$prefix].substr($mapTo, $pos + 1, strlen($mapTo) - ($pos + 1));
			}
		}
		
		$adds = array();
		
		foreach($add as $key => $value)
		{
			$k;
			$v;
			
			if(($pos = strpos($key, ":")) !== FALSE)
			{
				$prefix = substr($key, 0, $pos);
				
				if(!isset($this->prefixes[$prefix]))
				{
					$error = "The prefix used '$prefix:' is undefined in the linkage file.";
					return(FALSE);
				}
				else
				{
					$k = $this->prefixes[$prefix].substr($key, $pos + 1, strlen($key) - ($pos + 1));
				}
			}

			if(($pos = strpos($value, ":")) !== FALSE)
			{
				$prefix = substr($value, 0, $pos);
				
				if(!isset($this->prefixes[$prefix]))
				{
					$error = "The prefix used '$prefix:' is undefined in the linkage file.";
					return(FALSE);
				}
				else
				{
					$v = $this->prefixes[$prefix].substr($value, $pos + 1, strlen($value) - ($pos + 1));
				}
			}
			
			$adds[$k] = $v;
		}
		
		
		if(!is_array($type))
		{
			$type = array(array("mapTo" => $mapToUri, "add" => $adds));
		}
		else
		{
			// Make sure the property doesn't already exist
			$reject = FALSE;
			foreach($type as $map)
			{
				if($map["mapTo"] == $maptToUri)
				{
					$reject = TRUE;
					break;
				}
			}
			
			if($reject === FALSE)
			{			
				array_push($type, array("mapTo" => $maptToUri, "add" => $add));
			}
		}
	}
	
	public function generateJsonSerialization()
	{
		$schema = "{\n";
		$schema .= "    \"linkageSchema\": {\n";
		
		if($this->version != "")
		{
			$schema .= "        \"version\": \"".$this->version."\",\n";
		}

		if($this->linkedFormat != "")
		{
			$schema .= "        \"linkedFormat\": \"".$this->linkedFormat."\",\n";
		}
		
		if(count($this->prefixes) > 0)
		{
			$schema .= "        \"prefixes\": {\n";

			foreach($this->prefixes as $prefix => $uri)
			{
				$schema .= "            \"".$prefix."\": \"".$uri."\",\n";
			}

			$schema = substr($schema, 0, strlen($schema) - 2)."\n";

			$schema .= "        },\n";
		}
		
		if(count($this->propertyX) > 0)
		{
			$schema .= "        \"properties\": {\n";

			foreach($this->propertyX as $property => $maps)
			{
				$schema .= "            \"".$property."\": {\n";
				
				// Could be extended to create arrays of "mapTo" (if more than one property mapTo this attribute)
				$schema .= "                \"mapTo\": \"".$maps[0]["mapTo"]."\"\n";					
	
				$schema .= "            },\n";				
			}

			$schema = substr($schema, 0, strlen($schema) - 2)."\n";

			$schema .= "        },\n";
		}
		
		if(count($this->typeX) < 1)
		{
			$schema = substr($schema, 0, strlen($schema) - 2)."\n";			
		}
		
		if(count($this->typeX) > 0)
		{
			$schema .= "        \"types\": {\n";

			foreach($this->typeX as $type => $maps)
			{
				$schema .= "            \"".$type."\": {\n";
				
				// Could be extended to create arrays of "mapTo" (if more than one property mapTo this attribute)
				$schema .= "                \"mapTo\": \"".$maps[0]["mapTo"]."\"\n";					
	
				$schema .= "            },\n";				
			}

			$schema = substr($schema, 0, strlen($schema) - 2)."\n";

			$schema .= "        }\n";
		}
		
		$schema .= "    }\n";
		
		/*
{
	"linkageSchema": {

		
		"properties": {
			"address": {
				"mapTo": "address:localityName"
			},
		
		*/
		$schema .= "}\n";
		
		return($schema);
	}
}

//@} 

?>