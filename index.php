<?php
    
	/*! @ingroup WsFramework Framework for the Web Services */
	//@{ 
	
	/*! @brief Server unique ID generator script.
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.
		 \n\n\n
	 */	
	
	// Check if the SID (Server ID) file is existing. If it is not, we create it.

	$hFile;
	
	$sid = "";
	
	// The Web server has to have writing permissions on the SID directory
	// This variable as to be properly setuped in to have a properly working SID.
	$sidDirectory = "";
	
	if(($hFile = @fopen($sidDirectory."server.sid", "x+")) === FALSE)
	{
		// Read the SID
		$hFile = fopen($sidDirectory."server.sid", "r");
		
		if($hFile) 
		{
		    while (!feof($hFile)) 
			{
		        $sid = fgets($hFile, 4096);
		        break;
		    }
			
		    fclose($hFile);
		}		
	}
	else
	{
		// Generate the SID
		$sid = md5(microtime());
		
		// Write the SID to the server.sid file
		if(fwrite($hFile, $sid) === FALSE)
		{
			die;
		}
	}

	echo $sid;
	
	//@} 	
?>