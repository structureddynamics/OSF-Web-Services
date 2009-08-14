<?php

	/*! @ingroup WsConverterIrv */
	//@{ 


	/*! @file \ws\converter\irv\JsonParser.php
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

class JsonParser
{
	public $instanceRecords;
	public $linkageSchemas = array();
	public $structureSchemas = array();
	public $dataset;
	public $jsonErrors = array();
	public $irvErrors = array();
	public $irvNotices = array();
	
	private $mime = "application/irv+json";
	private $jsonContent = "";
	
	private $irvStructureSchema = "{
										\"structureSchema\": {
											\"version\": \"0.1\",
											
											\"properties\": {
												\"id\": {
													\"type\": \"string\"
												},
												\"label\": {
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

	function __construct($content, $mime) 
	{
		$this->mime = $mime;

		$content = $this->subFormatsConversion($content);

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
			array_push($this->jsonErrors, "Syntax error, malformed JSON");

			return FALSE;		
		}
		else
		{
			$this->parse();
		}
	}
	
	function __destruct(){}
	
	/*!	 @brief Fix the parsed array structure to comply with subFormats of irJSON such as BibJSON.
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	
	private function subFormatsConversion($content)
	{
		switch($this->mime)
		{
			case "application/bib+json":

				// Dataset -> Metadata
				$content = preg_replace("/\"Metadata\"[\s\t]*:/Uim", "\"Dataset\" :", $content);				

				// InstanceRecords -> Records
				$content = preg_replace("/\"Records\"[\s\t]*:/Uim", "\"InstanceRecords\" :", $content);
				
			break;
		}
		
		return($content);
	}

	private function parse() 
	{
		// Populate the Dataset object
		$this->dataset = new Dataset();

		// Set ID
		if(isset($this->jsonContent->Dataset->id))
		{
			$this->dataset->setId($this->jsonContent->Dataset->id);
		}
		else
		{
			array_push($this->irvErrors, "Dataset ID not specified");
		}
		
		// Set label
		if(is_array($this->jsonContent->Dataset->label))
		{
			foreach($this->jsonContent->Dataset->label as $label)
			{
				$this->dataset->setLabel($label, "array(string)");	
			}
		}
		else
		{
			$this->dataset->setLabel($this->jsonContent->Dataset->label, "string");
		}
		
		// Set description
		$this->dataset->setDescription($this->jsonContent->Dataset->description);
				
		// Set sources		
		if(is_array($this->jsonContent->Dataset->source))
		{
			foreach($this->jsonContent->Dataset->source as $source)
			{
				$this->dataset->setSource($source->label, $source->href, $source->ref, "array(object)");	
			}		
		}
		else
		{
			$this->dataset->setSource($this->jsonContent->Dataset->source->label, $this->jsonContent->Dataset->source->href, $this->jsonContent->Dataset->source->ref, "object");
		}


		// Set created
		$this->dataset->setCreated($this->jsonContent->Dataset->created);		

		// Set creator		
		if(is_array($this->jsonContent->Dataset->creator))
		{
			foreach($this->jsonContent->Dataset->creator as $creator)
			{
				$this->dataset->setCreator($creator->label, $creator->href, $creator->ref, "array(object)");	
			}		
		}
		else
		{
			$this->dataset->setCreator($this->jsonContent->Dataset->creator->label, $this->jsonContent->Dataset->creator->href, $this->jsonContent->Dataset->creator->ref, "object");
		}

		// Set curator		
		if(is_array($this->jsonContent->Dataset->curator))
		{
			foreach($this->jsonContent->Dataset->curator as $curator)
			{
				$this->dataset->setCurator($curator->label, $curator->href, $curator->ref, "array(object)");	
			}		
		}
		else
		{
			$this->dataset->setCurator($this->jsonContent->Dataset->curator->label, $this->jsonContent->Dataset->curator->href, $this->jsonContent->Dataset->curator->ref, "object");
		}
		
		// Set maintainer		
		if(is_array($this->jsonContent->Dataset->maintainer))
		{
			foreach($this->jsonContent->Dataset->maintainer as $maintainer)
			{
				$this->dataset->setMaintainer($maintainer->label, $maintainer->href, $maintainer->ref, "array(object)");	
			}		
		}
		else
		{
			$this->dataset->setMaintainer($this->jsonContent->Dataset->maintainer->label, $this->jsonContent->Dataset->maintainer->href, $this->jsonContent->Dataset->maintainer->ref, "object");
		}		
		
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
		
		
		if(isset($this->jsonContent->Dataset->structureSchema))
		{
			array_push($structureSchemas, $this->jsonContent->Dataset->structureSchema);
		}
		
		// Get the decoded schema
		if(gettype($this->jsonContent->Dataset->structureSchema) != "object")
		{
			// It is a URL link to a linkage schema
			if(isset($this->jsonContent->Dataset->structureSchema))
			{
				// Save the URL reference
				$this->dataset->setStructureSchema($this->jsonContent->Dataset->structureSchema);
				
				$ch = curl_init();
				
				curl_setopt($ch, CURLOPT_URL, $this->jsonContent->Dataset->structureSchema);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
				
			
				$data = curl_exec($ch);
				
				if(curl_errno($ch)) 
				{
					array_push($this->irvNotices, "Cannot access the structure schema from: ".$this->jsonContent->Dataset->structureSchema." - Ignored");
					
					curl_close($ch);
				}
				else
				{
					$data = trim($data);
	
					curl_close($ch);
	
					$parsedContent = json_decode($data);
	
					if($parsedContent === NULL)
					{
						array_push($this->jsonErrors, "Syntax error while parsing structure schema '".$this->jsonContent->Dataset->structureSchema."', malformed JSON");
						
						return FALSE;		
					}
	
					array_push($structureSchemas, $parsedContent);
				}
			}
		}
		else
		{
			if(isset($this->jsonContent->Dataset->structureSchema))
			{
				array_push($structureSchemas, $this->jsonContent->Dataset->structureSchema);
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
		
		
		// Linkage Schemas
		$linkageSchemas = array();

		// Get the decoded schema
		if(gettype($this->jsonContent->Dataset->linkageSchema) != "object")
		{
			// It is a URL link to a linkage schema
			if(isset($this->jsonContent->Dataset->linkageSchema))
			{
				$data;

				foreach($this->jsonContent->Dataset->linkageSchema as $ls)
				{
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
				}

				if($data != "")
				{
					$parsedContent = json_decode($data);
	
					if($parsedContent === NULL)
					{
						array_push($this->jsonErrors, "Syntax error while parsing core linkage schema '".$url."', malformed JSON");
						
						return FALSE;		
					}
	
					array_push($linkageSchemas, $parsedContent);
				}
			}
		}
		else
		{
			if(isset($this->jsonContent->Dataset->linkageSchema))
			{
				array_push($linkageSchemas, $this->jsonContent->Dataset->linkageSchema);
			}
		}
		
		// Now populate the schema object.
		foreach($linkageSchemas as $linkageSchema)
		{
			$linkageSchema = $linkageSchema->linkageSchema;
			
			$tempSchema = new LinkageSchema();
			
			// Set version
			$tempSchema->setVersion($linkageSchema->version);			

			// Set linkedFormat
			$tempSchema->setLinkedFormat($linkageSchema->linkedFormat);
			
			// Set prefixes
			foreach($linkageSchema->prefixes as $prefix => $uri)
			{
				$tempSchema->setPrefix($prefix, $uri);
			}			
			
			// Set propertieslinkageSchemas
			foreach($linkageSchema->properties as $property => $values)
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
			
			// Set types
			foreach($linkageSchema->types as $type => $values)
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

			array_push($this->linkageSchemas, $tempSchema);
		}

		$this->instanceRecords = array();

		foreach($this->jsonContent->InstanceRecords as $ir)
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

			// Set type
			if(isset($ir->type))
			{
				if(is_array($ir->type))
				{
					foreach($ir->type as $type)
					{
						$instanceRecord->setType($type, "array(string)");	
					}
				}
				else
				{
					$instanceRecord->setType($ir->type, "string");
				}
			}
			else
			{
				$instanceRecord->setType("thing", "string");
			}
			
			// Set label
			if(is_array($ir->label))
			{
				foreach($ir->label as $label)
				{
					$instanceRecord->setLabel($label, "array(string)");	
				}
			}
			else
			{
				$instanceRecord->setLabel($ir->label, "string");
			}
			
			// Set description
			$instanceRecord->setDescription($ir->description);
			
			// Set sameAs
			if(is_array($ir->sameAs))
			{
				foreach($ir->sameAs as $sameAs)
				{
					$instanceRecord->setSameAs($sameAs, "array(string)");	
				}
			}
			else
			{
				$instanceRecord->setSameAs($ir->sameAs, "string");
			}					
			
			// Set attributeX
//			print_r($ir);
			foreach($ir as $attribute => $value)
			{
				if(	$attribute != "id" &&
					$attribute != "type" &&
					$attribute != "label" &&
					$attribute != "description" &&
					$attribute != "sameAs")
				{

					// Check if we have an array of something
					if(is_array($ir->{$attribute}))
					{
//						echo "Attribute: $attribute :gettype: ".gettype($value)."\n";
												
						foreach($ir->{$attribute} as $arrayValue)
						{
							if(gettype($arrayValue) == "string")
							{
//								echo "Attribute: $attribute :: array(string)\n";
								$instanceRecord->setAttributeX($attribute, $arrayValue, "array(string)");
							}
							
							if(gettype($arrayValue) == "object")
							{
//								echo "Attribute: $attribute :: array(object)\n";
								$instanceRecord->setAttributeXRef($attribute, $arrayValue->label, $arrayValue->href, $arrayValue->ref, "array(object)");
							}
						}		
					}
					else
					{
//						echo "Attribute: $attribute :gettype: ".gettype($value)."\n";						
						
						if(gettype($value) == "string")
						{
//							echo "Attribute: $attribute :: string\n";
							$instanceRecord->setAttributeX($attribute, $value, "string");							
						}
						
						if(gettype($value) == "object")
						{
//							echo "Attribute: $attribute :: object\n";
							$instanceRecord->setAttributeXRef($attribute, $value->label, $value->href, $value->ref, "object");
						}
					}

/*
					if(is_array($ir->{$attribute}))
					{
						foreach($ir->{$attribute} as $attr)
						{
							$instanceRecord->setAttributeXRef($attribute, $attr->label, $attr->href, $attr->ref, "array(object)");
						}		
					}
					else
					{
						// Check if it is an array of strings
						if(is_array($ir->{$attribute}))
						{
							$instanceRecord->setAttributeX($attribute, $value, "array(string)");
						}
						else
						{
							// It is a single value
							$instanceRecord->setAttributeX($attribute, $value, "string");
						}
					}
*/					
				}	
			}	

			array_push($this->instanceRecords, $instanceRecord);
		}
		
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
		
/*		
			print_r($this->linkageSchemas);
			print_r($this->structureSchemas);
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