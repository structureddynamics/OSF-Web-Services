<?php

	/*! @ingroup WsConverterIrv */
	//@{ 

	/*! @file \ws\converter\irv\InstanceRecord.php
		 @brief Define an instance record item
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */


	/*!	 @brief Instance Record item description
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/

class InstanceRecord
{
	public $id;
	public $type;
	public $label;
	public $description;
	public $sameAs;
	public $attributeX = array();
	
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

	public function setType($type, $valueType)
	{
		// case unsensitive;
		//$type = strtolower($type);
				
		if($type != "")
		{	
			if(!is_array($this->type))
			{		
				$this->type = array($type);
			}
			else
			{
				array_push($this->type, $type);
			}	
			
			$this->type["valueType"] = $valueType;
		}
	}
	
	public function setLabel($label = "", $valueType)
	{
		if($label != "")
		{			
			if(!is_array($this->label))
			{		
				$this->label = array($label);
			}
			else
			{
				array_push($this->label, $label);
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
	
	public function setSameAs($sameAs, $valueType)
	{
		if($sameAs != "")
		{	
			if(!is_array($this->sameAs))
			{		
				$this->sameAs = array($sameAs);
			}
			else
			{
				array_push($this->sameAs, $sameAs);
			}
			
			$this->sameAs["valueType"] = $valueType;
		}
	}	

	public function setAttributeX($attr, $value, $valueType)
	{
		if(!is_array($this->attributeX[$attr]))
		{		
			$this->attributeX[$attr] = array($value);
		}
		else
		{
			array_push($this->attributeX[$attr], $value);
		}
		
		$this->attributeX[$attr]["valueType"] = $valueType;
	}	

	public function setAttributeXRef($attr, $label, $href, $ref, $valueType)
	{
		$this->addRef($this->attributeX[$attr], $label, $href, $ref, $valueType);			
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
			// Check if it is part of "attributeX"
			if(isset($this->{"attributeX"}[$property]["valueType"]))
			{
				return($this->{"attributeX"}[$property]["valueType"]);
			}

			return(FALSE);
		}
	}
}

	//@} 

?>