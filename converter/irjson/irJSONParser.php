<?php

	/*! @ingroup WsConverterIrJSON */
	//@{ 


	/*! @file \ws\converter\irjson\irJSONParser.php
		 @brief Parse a json item
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.
		
		 \n\n\n
	 */


/*!	 @brief JSON parsing class
						
		\n
		
		@author Frederick Giasson, Structured Dynamics LLC.
	
		\n\n\n
*/

class irJSONParser
{

	public $instanceRecords;
	public $linkageSchemas = array();
//	public $structureSchemas = array();
	public $dataset;
	public $jsonErrors = array();
	public $irvErrors = array();
	public $irvNotices = array();
	
	private $jsonContent = "";
	
/*	
	private $irvStructureSchema = "{
										\"structureSchema\": {
											\"version\": \"0.1\",
											
											\"properties\": {
												\"id\": {
													\"type\": \"string\"
												},
												\"label\": {
													\"type\": \"string\"
												},
												\"altLabel\": {
													\"type\": [\"string\", \"array(string)\"]
												},
												\"description\": {
													\"type\": \"string\"
												},
												\"source\": {
													\"type\": [\"object\", \"array(object)\"]
												},
												\"create_date\": {
													\"type\": \"string\",
													\"format\": \"date\"
												},
												\"creator\": {
													\"type\": [\"object\", \"array(object)\"]
												},
												\"curator\": {
													\"type\": [\"object\", \"array(object)\"]
												},
												\"maintainer\": {
													\"type\": [\"object\", \"array(object)\"]
												},
												\"linkageSchema\": {
													\"type\": \"array(string)\"
												},
												\"structureSchema\": {
													\"type\": \"string\"
												},
												\"type\": {
													\"type\": [\"string\", \"array(string)\"]
												},
												\"sameAs\": {
													\"type\": [\"string\", \"array(string)\"]
												},
												\"href\": {
													\"type\": \"string\",
													\"format\": \"url\"
												},
												\"ref\": {
													\"type\": \"string\"
												},
												\"version\": {
													\"type\": \"string\"
												},
												\"linkedFormat\": {
													\"type\": \"string\"
												},
												\"prefixes\": {
													\"type\": \"object\"
												},
												\"properties\": {
													\"type\": \"object\"
												},
												\"types\": {
													\"type\": \"object\"
												},
												\"format\": {
													\"type\": \"string\"
												},			
												\"mapTo\": {
													\"type\": \"string\"
												},		
												\"add\": {
													\"type\": \"string\"
												},	
												\"subPropertyOf\": {
													\"type\": \"string\"
												},			
												\"equivalentPropertyTo\": {
													\"type\": \"string\"
												},			
												\"subTypeOf\": {
													\"type\": \"string\"
												},			
												\"equivalentTypeTo\": {
													\"type\": \"string\"
												}
											}
										}
									}";
*/

	function __construct($content) 
	{
		$this->jsonContent = json_decode($content);
		
		
		/*
			// Only for PHP greater than 5.3
			
			$error = json_last_error();
			echo "<$error>";
			if($error != JSON_ERROR_NONE)
			{
		        switch($error)
		        {
		            case JSON_ERROR_DEPTH:
		                $this->error =  'Maximum stack depth exceeded';
	                break;
		            case JSON_ERROR_CTRL_CHAR:
		                $this->error = 'Unexpected control character found';
	                break;
		            case JSON_ERROR_SYNTAX:
		                $this->error = 'Syntax error, malformed JSON';
	                break;
		        }	
				
				echo $this->error;
						
				return FALSE;
			}
		
		*/
		
		if($this->jsonContent === NULL)
		{
			array_push($this->jsonErrors, "Syntax error, malformed JSON. Cant parse: '$content'");

			return FALSE;		
		}
		else
		{
			$this->parse();
		}
	}
	
	function __destruct(){}
	
	private function parse() 
	{
		// Populate the Dataset object
		$this->dataset = new Dataset();

		// Set ID
		if(isset($this->jsonContent->dataset->id))
		{
			$this->dataset->setId($this->jsonContent->dataset->id);
		}
		else
		{
			array_push($this->irvErrors, "Dataset ID not specified");
		}
		
		// Set attributes
		foreach($this->jsonContent->dataset as $attribute => $value)
		{
			if($attribute != "id" && $attribute != "type")
			{
				// Check if we have an array of something
				if(is_array($this->jsonContent->dataset->{$attribute}))
				{
					foreach($this->jsonContent->dataset->{$attribute} as $arrayValue)
					{
						if(gettype($arrayValue) == "string")
						{
							$this->dataset->setAttribute($attribute, $arrayValue, "primitive:string[".count($this->jsonContent->dataset->{$attribute})."]");
						}
						
						if(gettype($arrayValue) == "object")
						{
							// Create the metaData array
							$metaData = array();
							foreach($arrayValue as $metaAttribute => $metaValue)
							{
								if($metaAttribute != "ref")
								{
									array_push($metaData, array($metaAttribute => $metaValue));
								}
							}
							
							$this->dataset->setAttributeRef($attribute, $metaData, $arrayValue->ref, "type:object[".$this->jsonContent->dataset->{$attribute}."]");
						}
					}		
				}
				else
				{
					if(gettype($value) == "string")
					{
						$this->dataset->setAttribute($attribute, $value, "primitive:string[1]");							
					}
					
					if(gettype($value) == "object")
					{
						// Create the metaData array
						$metaData = array();
						foreach($value as $metaAttribute => $metaValue)
						{
							if($metaAttribute != "ref")
							{
								array_push($metaData, array($metaAttribute => $metaValue));
							}
						}
												
						$this->dataset->setAttributeRef($attribute, $metaData, $value->ref, "type:object[1]");
					}
				}
			}	
		}


/*		
		// Structured Schema
		
		// Load the internal structure schema in the schemas array.
		$structureSchemas = array();
		
		$parsedContent = json_decode($this->irvStructureSchema);

		if($parsedContent === NULL)
		{
			array_push($this->jsonErrors, "Syntax error while parsing core structure schema, malformed JSON");
			
			return FALSE;		
		}
		
		array_push($structureSchemas, $parsedContent);
		
		
		if(isset($this->jsonContent->dataset->structureSchema))
		{
			array_push($structureSchemas, $this->jsonContent->dataset->structureSchema);
		}
		
		// Get the decoded schema
		if(gettype($this->jsonContent->dataset->structureSchema) != "object")
		{
			// It is a URL link to a linkage schema
			if(isset($this->jsonContent->dataset->structureSchema))
			{
				// Save the URL reference
				$this->dataset->setStructureSchema($this->jsonContent->dataset->structureSchema);
				
				$ch = curl_init();
				
				curl_setopt($ch, CURLOPT_URL, $this->jsonContent->dataset->structureSchema);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
				
			
				$data = curl_exec($ch);
				
				if(curl_errno($ch)) 
				{
					array_push($this->irvNotices, "Cannot access the structure schema from: ".$this->jsonContent->dataset->structureSchema." - Ignored");
					
					curl_close($ch);
				}
				else
				{
					$data = trim($data);
	
					curl_close($ch);
	
					$parsedContent = json_decode($data);
	
					if($parsedContent === NULL)
					{
						array_push($this->jsonErrors, "Syntax error while parsing structure schema '".$this->jsonContent->dataset->structureSchema."', malformed JSON");
						
						return FALSE;		
					}
	
					array_push($structureSchemas, $parsedContent);
				}
			}
		}
		else
		{
			if(isset($this->jsonContent->dataset->structureSchema))
			{
				array_push($structureSchemas, $this->jsonContent->dataset->structureSchema);
			}
		}		
		
		// Now populate the schema object.
		foreach($structureSchemas as $structureSchema)
		{
			$structureSchema = $structureSchema->structureSchema;
			$tempSchema = new StructureSchema();
			
			// Set version
			$tempSchema->setVersion($structureSchema->version);			

			// Set properties structureSchemas
			if(isset($structureSchema->properties))
			{
				foreach($structureSchema->properties as $property => $values)
				{
					$tempSchema->setPropertyX($property, $values->type, $values->format, $values->equivalentPropertyTo, $values->subPropertyOf);
				}
			}
			
			// Set types
			if(isset($structureSchema->types))
			{
				foreach($structureSchema->types as $type => $values)
				{
					$tempSchema->setTypeX($type, $values->mapTo, $values->equivalentTypeTo, $values->subTypeOf);
				}
			}

			array_push($this->structureSchemas, $tempSchema);
		}
*/		

		// Linkage Schemas
		$linkageSchemas = array();

		// Get the decoded schema
		if(gettype($this->jsonContent->dataset->linkage) != "object")
		{
			// It is a URL link to a linkage schema
			if(isset($this->jsonContent->dataset->linkage))
			{
				foreach($this->jsonContent->dataset->linkage as $ls)
				{
					$data = "";
					
					if(gettype($ls) != "object")
					{
						// It is a URL

						// Save the URL reference
						$this->dataset->setLinkageSchema($ls);
						
						$ch = curl_init();
						
						curl_setopt($ch, CURLOPT_URL, $ls);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
						
						$data = curl_exec($ch);
						
						if(curl_errno($ch) || $data == "") 
						{
							array_push($this->irvNotices, "Cannot access the linkage schema from: $ls - Ignored");
							curl_close($ch);
							continue;
						}
						
						$data = trim($data);
	
						curl_close($ch);
					}
					else
					{
						// It is an embedded linkage schema.
						array_push($linkageSchemas, $ls);
					}
					
					if($data != "")
					{
						$parsedContent = json_decode($data);
		
						if($parsedContent === NULL)
						{
							array_push($this->jsonErrors, "Syntax error while parsing core linkage schema '".$ls."', malformed JSON");
							
							return FALSE;		
						}
						
						// Check if a linkage schema of the same linkageType exists. If it exists, we merge them together.
						
						// Merging rules:
						// (1) if a type already exists, the type of the first schema will be used
						// (2) if an attribute already exists, the attribute of the first schema will be used.
						
						$parsedContent = $parsedContent->linkage;
						
						$merged = FALSE;
						
						foreach($linkageSchemas as $linkageSchema)
						{
							if($linkageSchema->linkageType == $parsedContent->linkageType)
							{
								// merge prefixes
								if(isset($parsedContent->prefixList))
								{
									foreach($parsedContent->prefixList as $prefix => $uri)
									{
										if(!isset($linkageSchema->prefixList->{$prefix}))
										{
											$linkageSchema->prefixList->{$prefix} = $uri;
										}
									}
								}
								
								// merge types
								if(isset($parsedContent->typeList))
								{
									foreach($parsedContent->typeList as $type => $typeObject)
									{
										if(!isset($linkageSchema->typeList->{$type}))
										{
											$linkageSchema->typeList->{$type} = $typeObject;
										}
									}
								}
								
								// merge attributes
								if(isset($parsedContent->attributeList))
								{
									foreach($parsedContent->attributeList as $attribute => $attributeObject)
									{
										if(!isset($linkageSchema->attributeList->{$attribute}))
										{
											$linkageSchema->attributeList->{$attribute} = $attributeObject;
										}
									}
								}
							}
							
							$merged = TRUE;
						}
						
						if(!$merged)
						{
							array_push($linkageSchemas, $parsedContent);
						}
					}					
					
				}
			}
		}
		else
		{
			if(isset($this->jsonContent->dataset->linkage))
			{
				array_push($linkageSchemas, $this->jsonContent->dataset->linkage);
			}
		}
		
		// Now populate the schema object.
		foreach($linkageSchemas as $linkageSchema)
		{
			$tempSchema = new LinkageSchema();
			
			// Set version
			$tempSchema->setVersion($linkageSchema->version);			

			// Set linkedFormat
			$tempSchema->setLinkedFormat($linkageSchema->linkedFormat);
			
			// Set prefixes
			if(isset($linkageSchema->prefixList))
			{			
				foreach($linkageSchema->prefixList as $prefix => $uri)
				{
					$tempSchema->setPrefix($prefix, $uri);
				}			
			}
			
			// Set attributes
			if(isset($linkageSchema->attributeList))
			{			
				foreach($linkageSchema->attributeList as $property => $values)
				{
					// Throw an error if mapTo is used without specifying a linkedFormat attribute.
					if(!isset($linkageSchema->linkedFormat) && isset($values->mapTo))
					{
						array_push($this->irvErrors, "A 'linkedFormat' attribute has to be defined for this schema since the 'mapTo' attribute is used in the schema.");					
					}
					
					$error = "";
					
					$tempSchema->setPropertyX($property, $values->mapTo, $error);
					
					if($error != "")
					{
						array_push($this->irvErrors, $error);
					}
				}
			}
			
			// Set types
			if(isset($linkageSchema->typeList))
			{
				foreach($linkageSchema->typeList as $type => $values)
				{
					$adds = array();
					
					if(isset($values->add))
					{
						foreach($values->add as $key => $value)
						{
							$adds[$key] = $value;
						}
					}
					
					$error = "";
					
					$tempSchema->setTypeX($type, $values->mapTo, $adds, $error);
					
					if($error != "")
					{
						array_push($this->irvErrors, $error);
					}
				}
			}

			array_push($this->linkageSchemas, $tempSchema);
		}

		$this->instanceRecords = array();

		foreach($this->jsonContent->recordList as $ir)
		{
			$instanceRecord = new InstanceRecord();

			// Set ID
			if(isset($ir->id))
			{
				$instanceRecord->setId($ir->id);
			}
			else
			{
				// Generate a random ID for that instance record
				$instanceRecord->setId(md5(microtime()));
			}

			// Set default type in case that no type has been defined for this record
			if(isset($ir->type))
			{
				if(is_array($ir->type))
				{
					foreach($ir->type as $type)
					{
						$instanceRecord->setAttribute("type", $type, "type:object[".count($ir->type)."]");
					}
				}
				else
				{
					$instanceRecord->setAttribute("type", $ir->type, "type:object[1]");
				}
			}
			else
			{
				$instanceRecord->setAttribute("type", "Object", "type:object[1]");
			}
			
			// Set attributes
			foreach($ir as $attribute => $value)
			{
				if($attribute != "id" && $attribute != "type")
				{
					// Check if we have an array of something
					if(is_array($ir->{$attribute}))
					{
						foreach($ir->{$attribute} as $arrayValue)
						{
							if(gettype($arrayValue) == "string")
							{
								$instanceRecord->setAttribute($attribute, $arrayValue, "primitive:string[".count($ir->{$attribute})."]");
							}
							
							if(gettype($arrayValue) == "object")
							{
								// Create the metaData array
								$metaData = array();
								foreach($arrayValue as $metaAttribute => $metaValue)
								{
									if($metaAttribute != "ref")
									{
										array_push($metaData, array($metaAttribute => $metaValue));
									}
								}
								$instanceRecord->setAttributeRef($attribute, $metaData, $arrayValue->ref, "type:object[".$ir->{$attribute}."]");
							}
						}		
					}
					else
					{
						if(gettype($value) == "string")
						{
							$instanceRecord->setAttribute($attribute, $value, "primitive:string[1]");							
						}
						
						if(gettype($value) == "object")
						{
							$metaData = array();
							foreach($value as $metaAttribute => $metaValue)
							{
								if($metaAttribute != "ref")
								{
									array_push($metaData, array($metaAttribute => $metaValue));
								}
							}
							
							$instanceRecord->setAttributeRef($attribute, $metaData, $value->ref, "type:object[1]");
						}
					}
				}	
			}	

			array_push($this->instanceRecords, $instanceRecord);
		}
		
		
/*		
		
		// Now lets validate the types of the values of the attributes that have been parsed.
			
		// Dataset types.
		
		foreach($this->dataset as $property => $value)
		{
			if($this->dataset->getValueType($property) === FALSE)
			{
				// Property not defined.
				continue;
			}
			
			$possibleTypes = array();
			$defined = FALSE;

			foreach($this->structureSchemas as $structureSchema)
			{
				$validType = FALSE;
				
				if($structureSchema->getPropertyTypes($property) !== FALSE)
				{
					$defined = TRUE;
					foreach($structureSchema->getPropertyTypes($property) as $type)
					{
						// array(object) and object; and; array(string) and string are the same types
						$t = $type;
						
						if(strpos($t, "array") !== FALSE)
						{
							$t = substr($t, 6, strlen($t) - 7);
						}

						$prop = $this->dataset->getValueType($property);

						if(strpos($prop, "array") !== FALSE)
						{
							$prop = substr($prop, 6, strlen($prop) - 7);
						}
						
						if($t == $prop)
						{
							// The type of the value has been validated by one of the structure schema.
							$validType = TRUE;
							break;
						}
					
						array_push($possibleTypes, $type);
					}
				}
				
				if($validType){ break; }		
			}

			if($validType === FALSE && $defined === TRUE)
			{
				// The type of the value is not valid according to the structure schemas.
				array_push($this->irvErrors, "Dataset property '".$property."' with value type '".$this->dataset->getValueType($property)."' is not valid according to the definition of the structure schema (should be one of: ".$this->listTypes($possibleTypes)." )");					
			}
				
			if($defined === FALSE)
			{
				// The type of the value is not valid according to the structure schemas.
				array_push($this->irvNotices, "Dataset property '".$property."' used without being part of the core structure schema.)");					
			}	
		}
		
		// Instance Record types

		foreach($this->instanceRecords as $key => $instanceRecord)
		{
			foreach($instanceRecord as $attribute => $value)
			{
				if($attribute == "attributeX")
				{
					foreach($value as $attr => $val)
					{
						if($instanceRecord->getValueType($attr) === FALSE)
						{
							// Property not defined.
							continue;
						}
						
						$this->validateAttributeType($instanceRecord, $attr);
					}
				}
				else
				{
					if($instanceRecord->getValueType($attribute) === FALSE)
					{
						// Property not defined.
						continue;
					}

					$this->validateAttributeType($instanceRecord, $attribute);
				}
			}	
		}
*/		
	}
	
	private function validateAttributeType(&$instanceRecord, $attribute)
	{
		$possibleTypes = array();
		$defined = FALSE;		
		
		foreach($this->structureSchemas as $structureSchema)
		{
			$validType = FALSE;
			
			if($structureSchema->getPropertyTypes($attribute) !== FALSE)
			{
				$defined = TRUE;
				foreach($structureSchema->getPropertyTypes($attribute) as $type)
				{
					// array(object) and object; and; array(string) and string are the same types
					$t = $type;
					
					if(strpos($t, "array") !== FALSE)
					{
						$t = substr($t, 6, strlen($t) - 7);
					}

					$prop = $instanceRecord->getValueType($attribute);

					if(strpos($prop, "array") !== FALSE)
					{
						$prop = substr($prop, 6, strlen($prop) - 7);
					}					
					
					if($t == $prop)
					{
						// The type of the value has been validted by one of the structure schema.
						$validType = TRUE;
						break;
					}
						
					array_push($possibleTypes, $type);
				}		
			}
			
			if($validType){ break; }		
		}

		if($validType === FALSE && $defined === TRUE)
		{
			// The type of the value is not valid according to the structure schemas.
			array_push($this->irvErrors, "Instance record attribute '".$attribute."' with value type '".$instanceRecord->getValueType($attribute)."' is not valid according to the definition of the structure schema (should be one of: ".$this->listTypes($possibleTypes)." )");
		}
		
		if($defined === FALSE)
		{
			// The type of the value is not valid according to the structure schemas.
			array_push($this->irvNotices, "Instance record attribute '".$attribute."' used without being part of the structure schema.)");					
		}	
		
		
		return($validType);				
	}
	
	private function listTypes($types)
	{
		$typeStr = "";
		
		foreach($types as $type)
		{
			$typeStr .= $type.", ";
		}
		
		return(substr($typeStr, 0, strlen($typeStr) - 2));
	}
}


	//@} 

?>