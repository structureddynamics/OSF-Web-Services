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
    protected $ws;
    
    function __construct($webservice)
    {       
      $this->ws = $webservice;
    }
    
    abstract public function processInterface();
  }
  

//@}  
?>
