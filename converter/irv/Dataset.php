<?php

	/*! @ingroup WsConverterIrv */
	//@{ 

	/*! @file \ws\converter\irv\Dataset.php
		 @brief Define a dataset item
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */


	/*!	 @brief Dataset item description
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class Dataset
{
	public $id;
	public $label;
	public $description;
	public $source;
	public $created;
	public $creator;
	public $curator;
	public $maintainer;
	public $linkageSchema;
	public $structureSchema;
	
	function __construct(){}
	
	function __destruct(){}
	
	public function setId($id)
	{
		if($id != "")
		{
			$this->id = array($id);

			$this->id["valueType"] = "string";
		}
	}
	
	public function setLabel($label, $valueType)
	{
		if($label != "")
		{		
			if(!is_array($this->label))
			{		
				$this->label = array($label);
			}
			else
			{
				$this->label = array($label);
			}
			
			$this->label["valueType"] = $valueType;
		}
	}
	
	public function setDescription($description)
	{
		if($description != "")
		{
			$this->description = array($description);

			$this->description["valueType"] = "string";
		}
	}
	
	public function setSource($label, $href, $ref, $valueType)
	{
		$this->addRef($this->source, $label, $href, $ref, $valueType);
	}
	
	public function setCreated($created)
	{
		if($created != "")
		{
			$this->created = array($created);

			$this->created["valueType"] = "string";
		}
	}
	
	public function setCreator($label, $href, $ref, $valueType)
	{
		$this->addRef($this->creator, $label, $href, $ref, $valueType);
	}
	
	public function setCurator($label, $href, $ref)
	{
		$this->addRef($this->curator, $label, $href, $ref, $valueType);
	}
	
	public function setMaintainer($label, $href, $ref, $valueType)
	{
		$this->addRef($this->maintainer, $label, $href, $ref, $valueType);
	}
	
	public function setLinkageSchema($linkageSchema)
	{
		if($linkageSchema != "")
		{
			$this->linkageSchema = array($linkageSchema);

			$this->linkageSchema["valueType"] = "array(string)";
		}
	}
	
	public function setStructureSchema($structureSchema)
	{
		if($structureSchema != "")
		{
			$this->structureSchema = array($structureSchema);

			$this->structureSchema["valueType"] = "string";
		}
	}

	private function addRef(&$attr, $label, $href, $ref, $valueType)
	{
		if($label != "" || $href != "" || $ref != "")
		{
			if(!is_array($attr))
			{
				$attr = array(array("label" => $label, "href" => $href, "ref" => $ref));
			}
			else
			{
				array_push($attr, array("label" => $label, "href" => $href, "ref" => $ref));
			}
			
			$attr["valueType"] = $valueType;
		}
	}
	
	public function getValueType($property)
	{
		if(isset($this->{$property}["valueType"]))
		{
			return($this->{$property}["valueType"]);
		}
		else
		{
			return(FALSE);
		}
	}
	
}

	//@} 

?>