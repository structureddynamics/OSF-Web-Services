<?php

	/*! @ingroup WsConverterIrJSON */
	//@{ 

	/*! @file \ws\converter\irjson\InstanceRecord.php
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
	public $attributes = array();
	
	function __construct(){}
	
	function __destruct(){}
	
	public function setId($id)
	{
		if($id != "")
		{
			$this->id = array($id);

			$this->id["valueType"] = "primitive:id[1]";
		}
	}

	public function setAttribute($attr, $value, $valueType)
	{
		if($value != "")
		{
			if(!is_array($this->attributes[$attr]))
			{		
				$this->attributes[$attr] = array($value);
			}
			else
			{
				array_push($this->attributes[$attr], $value);
			}
			
			$this->attributes[$attr]["valueType"] = $valueType;
		}
	}	

	public function setAttributeRef($attr, $metaData, $ref, $valueType)
	{
		$this->addRef($this->attributes[$attr], $metaData, $ref, $valueType);			
	}

	private function addRef(&$attr, $metaData, $ref, $valueType)
	{
		if(!is_array($attr))
		{
			$attr = array(array("metaData" => $metaData,"ref" => $ref));
		}
		else
		{
			array_push($attr, array("metaData" => $metaData,"ref" => $ref));
		}
		
		$attr["valueType"] = $valueType;
	}
	
	public function getValueType($property)
	{
		if(isset($this->{$property}["valueType"]))
		{
			return($this->{$property}["valueType"]);
		}
		else
		{
			// Check if it is part of "attributes"
			if(isset($this->{"attributes"}[$property]["valueType"]))
			{
				return($this->{"attributes"}[$property]["valueType"]);
			}

			return(FALSE);
		}
	}
}

	//@} 

?>