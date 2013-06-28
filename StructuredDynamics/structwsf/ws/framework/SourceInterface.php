<?php

/** @defgroup WsFramework Framework for the Web Services */
//@{

/**@file \StructuredDynamics\structwsf\ws\framework\SourceInterface.php
   @brief The SourceInterface abstract class.
*/

  namespace StructuredDynamics\structwsf\ws\framework; 

  /**
  * This is the abstract class that define a source interface of any web service endpoint
  * part of the structWSF web services framework.
  * 
  * A "source interface" is a block of code that is executed if a valid query has been
  * sent to any web service endpoint of the framework. This "block of code" is what define
  * the behavior of a endpoint. This code will check what are the parameters requested
  * by the user, then will generate a query, or a series of queries, to one or multiple
  * data management systems. Then this block of code will get back all the result(s) that
  * matches the query(ies) and will populate the "rset" (resultset) object of the web service
  * endpoint. It is the content of the rset object that will be used by the web service endpoint
  * to serialize the resultset to be returned to the requester.
  * 
  * There are two different kind of source interfaces:
  * 
  *   (1) Source interfaces that return a resultset. The returned resultset is a set of one, or 
  *       multiple records' descriptions. Such a source interface could be querying the Solr
  *       web service endpoint for the Crud: Read or Search web service endpoints.
  *   (2) Source interfaces that perform some action within structWSF but that doesn't return any
  *       resultset. Such a source interface could create a new record in Virtyoso & Solr such as
  *       the Crud: Create web service endpoint.
  * 
  * Each web service endpoint in the structWSF framework has a DefaultSourceInterface defined.
  * This default source interface is the default behavior of each web service endpoint. The
  * behavior of a web service endpoint can be modified by creating new source interfaces.
  * 
  * In the implementation of a SourceInterface, all variables are accessible via the "$this->ws" object.
  * This object is a reference to the web service instance that uses this source interface.
  */
  abstract class SourceInterface
  {        
    /** A reference to the web service object instance that  uses this interface instance. */
    protected $ws;
    
    /** 
    * The version of the web service endpoint with which it is compatible.
    * 
    * Versions are defined like: 1.0, 2.1, 4.2, etc.
    */
    protected $compatibleWith;
    
    /** 
    * Version of the Source Interface
    * 
    * The goal of the versioning system is to notice the user of the endpoint if 
    * the behavior of the endpoint changed since the client application was developed 
    * for interacting with it.
    * 
    * The version will increase when the behavior of the source interface changes. The
    * behavior may changes if:
    * 
    * (1) A bug get fixed (and at least a behavior changes)
    * (2) The code get refactored (and at least a behavior changes) [impacts]
    * (3) A new parameter/feature is added to the endpoint (but at least another behavior changed) [impacts]
    * (4) An existing parameter/feature got changed [impacts]
    * (5) An existing parameter/feature got removed [impacts]
    * 
    * In this kind of versioning system, versions are not backward compatible. Because
    * the version only changes when core behaviors of existing featuers changes, the
    * versions can't be backward compatible. It is used to track if behaviors changed, 
    * and to notice users if they did since they last implemented the API.
    */    
    protected $version;
    
    function __construct($webservice)
    {       
      $this->version = "1.0";
      
      $this->ws = $webservice;
    }
    
    public function validateWebServiceCompatibility()
    {
      // Validate that the version of the interface is valid with
      // the version of the web service endpoint.
      if(version_compare($this->compatibleWith, $this->ws->version) != 0)
      {
        return(FALSE);
      }
      
      return(TRUE);      
    }
    
    public function validateInterfaceVersion()
    {
      // Validate if the version requested by the user is compatible
      // with the current one
      
      if(version_compare($this->version, $this->ws->requestedInterfaceVersion) != 0)
      {
        return(FALSE);        
      }
      
      return(TRUE);
    }
    
    /**
    * Get the current version of the source interface.
    * 
    */
    public function getVersion()
    {
      return($this->version);
    }
    
    protected function safeDate($string)
    {
      if(!preg_match("/\d{4}/", $string, $match))
      {
        // Year must be in YYYY form. Return error.
        return(FALSE); 
      }
      
      // Converting the year to integer
      $year = intval($match[0]); 
      
      if($year >= 1970) 
      {
        $timestamp = strtotime($string);
        
        if($timestamp !== FALSE)
        {
          return(gmdate("Y-m-d\TH:i:s\Z", $timestamp));
        }
        else
        {
          return(FALSE);
        }          
      }
      else
      {
        // Calculating the difference between 1975 and the year
        $diff = 1975 - $year; 
        
        // Year + diff = new_year will be for sure > 1970
        $new_year = $year + $diff; 
        
        $timestamp = strtotime(str_replace($year, $new_year, $string));
        
        if($timestamp !== FALSE)
        {
          // Replacing the year with the new_year, try strtotime, rendering the date
          $new_date = gmdate("Y-m-d\TH:i:s\Z", $timestamp); 
          
          // Returning the date with the correct year
          return str_replace($new_year, $year, $new_date); 
        }
        else
        {
          return(FALSE);
        }          
      }
    }    
    
    abstract public function processInterface();
  }
  

//@}  
?>
