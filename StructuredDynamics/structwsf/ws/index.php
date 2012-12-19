<?php
    
	/*! @ingroup WsFramework Framework for the Web Services */
	//@{ 
	
	/** 
  @file \StructuredDynamics\structwsf\ws\index.php
  @brief Server unique ID generator script.
 */	
	
	// Check if the SID (Server ID) file is existing. If it is not, we create it.

	$hFile;
	
	$sid = "";
	
	// The Web server has to have writing permissions on the SID directory
	// This variable as to be properly setuped in to have a properly working SID.
	$sidDirectory = "";
	
	if(file_exists($sidDirectory."server.sid"))
	{
		// Read the SID
    $sid = file_get_contents($sidDirectory."server.sid");
	}
	else
	{
		// Generate the SID
		$sid = md5(microtime());
		
		// Write the SID to the server.sid file
    file_put_contents($sidDirectory."server.sid", $sid);
	}

	echo $sid;
	
	//@} 	
?>