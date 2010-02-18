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
    /*! @brief ID of the instance record. The ID of an instance record is used to create the global reference of local records belonging to the dataset. */                  
	public $id;
	
    /*! @brief All attributes/values describing the dataset */                  
	public $attributes = array();
	
	function __construct(){}
	
	function __destruct(){}
	
    /*!      @brief Set the value of the ID
                                                    
                    \n
                    
                    @param[in] $id ID of the dataset
                    
                    @author Frederick Giasson, Structured Dynamics LLC.
            
                    \n\n\n
    */    		
	public function setId($id)
	{
		if($id != "")
		{
			$this->id = array($id);

			$this->id["valueType"] = "primitive:id[1]";
		}
	}

    /*!      @brief Set a value for an attribute describing the instance record
                                                    
                    \n
                    
                    @param[in] $attr attribute describing the instance record
                    @param[in] $value value of the attribute
                    @param[in] $valueType type of the value of the attribute (ex: String, Object, etc).
                    
                    @author Frederick Giasson, Structured Dynamics LLC.
            
                    \n\n\n
    */ 
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

    /*!      @brief Set a "ref" attribute
                                                    
                    \n
                    
                    @param[in] $attr attribute describing the instance record
                    @param[in] $metaData metaData that describe the <subject, attribute, value> triple.
                    @param[in] $ref Reference to the local or global ID of the reference
                    @param[in] $valueType type of the value of the attribute (ex: String, Object, etc).
                    
                    @author Frederick Giasson, Structured Dynamics LLC.
            
                    \n\n\n
    */ 
	public function setAttributeRef($attr, $metaData, $ref, $valueType)
	{
		$this->addRef($this->attributes[$attr], $metaData, $ref, $valueType);			
	}

    /*!      @brief Create a reference to a source, creator, curator or maintenainer of an instance record
                                                    
                    \n
                    
                    @param[in] $attr attribute reference
                    @param[in] $metaData metaData that describe the <subject, attribute, value> triple.
                    @param[in] $ref Reference to the local or global ID of the reference
                    @param[in] $valueType Value type of the reference value
                    
                    @author Frederick Giasson, Structured Dynamics LLC.
            
                    \n\n\n
    */  
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
	
    /*!      @brief Get the valueType of an attribute
                                                    
                    \n
                    
                    @param[in] $property Target property you want to get the valueType
                    
                    @author Frederick Giasson, Structured Dynamics LLC.
            
                    \n\n\n
    */  	
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