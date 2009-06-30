<?php

/*! @ingroup WsFramework Framework for the Web Services */
//@{ 

/*! @file \ws\framework\CompoundWebService.php
	 @brief The abstract class that define a compound web service
	
	 \n\n
 
	 @author Frederick Giasson, Structured Dynamics LLC.

	 \n\n\n
 */

/*!	 @brief The abstract class that define a compound web service
						
		\n
		
		@author Frederick Giasson, Structured Dynamics LLC.
	
		\n\n\n
*/

abstract class CompoundWebService extends WebService
{
	function __construct(){}
	function __destruct(){}
	
	// setSupportedSerialization() is used to set the supported serialization for the compound web service. This function
	// find the intersection between all the supported serializations that compose the compound web service. 
	
	// Note: one of the problem with the CompoundWebServices is that they have to be instantiated in order to have $this->supportedSerializations
	// initialialized. This means that if a CompoundWebService include another CompundWebService, then we have to create the first one,
	// then to run the setSupportedSerializations() function.
	public function setSupportedSerializations($supportedSerializations)
	{
		$intersection = array();
		$intersectionSet = array();
		
		foreach($supportedSerializations as $ss)
		{
			foreach($ss as $mime)
			{
				if(isset($intersection[$mime]))
				{
					$intersection[$mime] += 1;
				}
				else
				{
					$intersection[$mime] = 1;
				}
			}	
		}
		
		foreach($intersection as $mime => $nb)
		{
			if($nb == count($supportedSerializations))
			{
				array_push($intersectionSet, $mime);
			}
		}
		
		return($intersectionSet);
	}
}

	//@} 

?>