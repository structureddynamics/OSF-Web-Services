<?php

/*! @ingroup StructWSFPHPAPIFramework Framework of the structWSF PHP API library */
//@{

/*! @file \StructuredDynamics\structwsf\framework\WebServiceQuerier.php
    @brief Querying a RESTFull web service endpoint

*/

namespace StructuredDynamics\structwsf\framework;
use \StructuredDynamics\structwsf\framework\QuerierExtension;

/**
* Query a RESTFul web service endpoint
*           
* @author Frederick Giasson, Structured Dynamics LLC.
*/

class WebServiceQuerier
{
  /** URL of the web service endpoint to query */
  private $url;

  /*v HTTP method to use to query the endpoint (GET or POST) */
  private $method;

  /** Parameters to send to the endpoint */
  private $parameters;

  /** Mime type of the resultset that has to be returned by the web service endpoint */
  private $mime;

  /** Status of the query */
  private $queryStatus = ""; // The HTTP query status code returned by the server (ex: 200)

  /** Status message of the query */
  private $queryStatusMessage = ""; // The HTTP query status message returned by the server (ex: OK)

  /** Extended message of the status of the query */
  private $queryStatusMessageDescription =
    ""; // The HTTP query status message description returned by the server (ex: No subject concept)

  /** Resultset of the query */
  private $queryResultset = "";

  /** Internal error ID of the queried web sevice */
  private $errorId = "";

  /** Internal error name of the queried web sevice */
  private $errorName = "";

  /** Internal error description of the queried web sevice */
  private $errorDescription = "";

  /** Internal error debug information of the queried web sevice */
  private $errorDebugInfo = "";

  /** Pointer to an extension object to allow external code to interact with the querier */
  private $extension = NULL;

  /** 
  * Internal error of the queried web service. The error doesn't necessarly come from the
  * queried web service endpoint in the case of a compound web service.
  */
  public $error;

  /**
  *   Constructor
  *  
  *   @param $url URL of the web service endpoint to query
  *   @param $method HTTP method to use to query the endpoint (GET or POST)
  *   @param $mime Mime type of the resultset that has to be returned by the web service endpoint
  *   @param $parameters Parameters to send to the endpoint 
  *   @param $timeout Timeout (in milliseconds) before ending the query to a remote web service.
  *    
  *   @return returns returns a human readable description of the class
  *    
  *   @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($url, $method, $mime, $parameters, $timeout = 0, $extension = NULL)
  {
    $this->url = $url;
    $this->method = $method;
    $this->parameters = $parameters;     
    //$this->parameters = $parameters . "&DBGSESSID=1@localhost:7869;d=1,p=0 ";      
    $this->mime = $mime;
    $this->timeout = $timeout;
    $this->extension = ($extension === NULL) ? new QuerierExtension() : $extension;

    $this->queryWebService();
  }

  function __destruct(){}

  /** 
  * Send a query to a web service endpoint.
  *    
  *    @author Frederick Giasson, Structured Dynamics LLC.
  */
  function queryWebService()
  {
    $ch = curl_init();

    switch (strtolower($this->method))
    {
      case "get":
        curl_setopt($ch, CURLOPT_URL, $this->url . "?" . $this->parameters);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: $this->mime"));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        if ($this->timeout > 0)
        {
          curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
      break;

      case "post":
        // Check if the size of the converted file is bigger than the maximum file size
        // we can upload via Curl.
        $uploadMaxFileSize = ini_get("post_max_size");
        $uploadMaxFileSize = str_replace("M", "000000", $uploadMaxFileSize);
        
        if($uploadMaxFileSize > strlen($this->parameters))
        {
          curl_setopt($ch, CURLOPT_URL, $this->url);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: $this->mime"));
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_POST, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $this->parameters);

          if ($this->timeout > 0)
          {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
          }
          curl_setopt($ch, CURLOPT_HEADER, TRUE);
        }
        else
        {
          $this->error = new QuerierError("WSF-600", "Fatal", $this->url, "Query too big", 
                                   "The query sent to the structWSF endpoint is too big given
                                    the current settings of the instance. The size of the
                                    query is ".number_format(strlen($this->parameters), 0, " ", " ")." bytes, 
                                    and the autorized size of the query is ".$uploadMaxFileSize." bytes", "");
          
          return(FALSE);
        }
      break;

      default:
        return FALSE;
      break;
    }
    
    $this->extension->alterQuery($this, $ch);
    $this->extension->startQuery($this); 
    $xml_data = curl_exec($ch);
    $this->extension->stopQuery($this, $xml_data);

    if ($xml_data === FALSE)
    {
      $data =
      substr($xml_data, strpos($xml_data, "\r\n\r\n") + 4, strlen($xml_data) - (strpos($xml_data, "\r\n\r\n") - 4));      
      
      // Can't reach the remote server
      $this->queryStatus = "503";
      $this->queryStatusMessage = "Service Unavailable";
      $this->queryStatusMessageDescription = "Can't reach remote server (" . curl_error($ch) . ")";
      $this->queryResultset = $data;

      $this->error = new QuerierError("HTTP-500", "Warning", $this->url, "Can't reach remote server",
        "Can't reach remote server (" . curl_error($ch) . ")", $data);

      $this->extension->debugQueryReturn($this, $xml_data);
      return;
    }

    // Remove any possible "HTTP/1.1 100 Continue" message from the web server
    $xml_data = str_replace("HTTP/1.1 100 Continue\r\n\r\n", "", $xml_data);
    $header = substr($xml_data, 0, strpos($xml_data, "\r\n\r\n"));

    $data =
      substr($xml_data, strpos($xml_data, "\r\n\r\n") + 4, strlen($xml_data) - (strpos($xml_data, "\r\n\r\n") - 4));

    curl_close($ch);

    // check returned message
    $this->queryStatus = substr($header, 9, 3);
    $this->queryStatusMessage = substr($header, 13, strpos($header, "\r\n") - 13);

    // Make sure that we don't have a PHP Parsing error in the resultset. If it is the case, we have to return an error
    if (stripos($data, "<b>Parse error</b>") !== false)
    {
      $this->queryStatus = "500";
      $this->queryStatusMessage = "Internal Server Error";
      $this->queryStatusMessageDescription = "Parsing Error";
      $this->queryResultset = $data;

      preg_match("/\/ws\/(.*)\/.*\.php/Uim", $data, $ws);

      $ws = $ws[0];
      $ws = substr($ws, 0, strrpos($ws, "/") + 1);

      $this->error = new QuerierError("HTTP-500", "Fatal", $ws, "Parsing Error", "PHP Parsing Error", $data);
      $this->extension->debugQueryReturn($this, $xml_data);

      return;
    }

    // Make sure that we don't have any rogue PHP uncatched fatal error in the resultset. If it is the case, 
    // we have to return an error
    if (stripos($data, "<b>Fatal error</b>") !== false)
    {
      $this->queryStatus = "500";
      $this->queryStatusMessage = "Internal Server Error";
      $this->queryStatusMessageDescription = "Fatal Error";
      $this->queryResultset = $data;

      preg_match("/\/ws\/(.*)\/.*\.php/Uim", $data, $ws);

      $ws = $ws[0];
      $ws = substr($ws, 0, strrpos($ws, "/") + 1);

      $this->error = new QuerierError("HTTP-500", "Fatal", $ws, "Fatal Error", "PHP uncatched Fatal Error", $data);
      return;
    }

    // We have to continue. Let fix this to 200 OK so that this never raise errors within the WSF
    if ($this->queryStatus == "100")
    {
      $this->queryStatus = "200";
      $this->queryStatusMessage = "OK";
      $this->queryStatusMessageDescription = "";
      $this->queryResultset = $data;
      $this->extension->debugQueryReturn($this, $xml_data);
      return;
    }

    if ($this->queryStatus != "200")
    {
      $this->queryStatusMessageDescription = str_replace(array(
        "\r",
        "\n"
      ), "", $data);

      // XML error messages
      if (strpos($this->queryStatusMessageDescription, "<error>") !== FALSE)
      {
        preg_match("/.*<id>(.*)<\/id>.*/Uim", $this->queryStatusMessageDescription, $errorId);
        $errorId = $errorId[1];

        preg_match("/.*<level>(.*)<\/level>.*/Uim", $this->queryStatusMessageDescription, $errorLevel);
        $errorLevel = $errorLevel[1];

        preg_match("/.*<webservice>(.*)<\/webservice>.*/Uim", $this->queryStatusMessageDescription, $errorWS);
        $errorWS = $errorWS[1];

        preg_match("/.*<name>(.*)<\/name>.*/Uim", $this->queryStatusMessageDescription, $errorName);
        $errorName = $errorName[1];

        preg_match("/.*<description>(.*)<\/description>.*/Uim", $this->queryStatusMessageDescription,
          $errorDescription);
        $errorDescription = $errorDescription[1];

        preg_match("/.*<debugInformation>(.*)<\/debugInformation>.*/Uim", $this->queryStatusMessageDescription,
          $errorDebugInfo);
          
        if(isset($errorDebugInfo[1]))
        {
          $errorDebugInfo = $errorDebugInfo[1];
        }

        $this->error = new QuerierError($errorId, $errorLevel, $errorWS, $errorName, $errorDescription, $errorDebugInfo);

        $this->extension->debugQueryReturn($this, $xml_data);
        return;
      }
      else
      {
        $this->error = new QuerierError("HTTP-500", "Fatal", $this->url, "Fatal Error", "Unspecified Server Fatal Error", $data);

        $this->extension->debugQueryReturn($this, $xml_data);
        return;
      }

    // JSON error messages
    }
    else
    {
      $this->queryResultset = $data;
    }

    $this->extension->debugQueryReturn($this, $xml_data);
    return;
  }

  /**
  * Get the status of the query
  * 
  * @return returns the status of the query
  * 
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getStatus()
  {
    return $this->queryStatus;
  }

  /** 
  * Get the message of the status of the query
  *            
  * @return returns the message of the status of the query
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getStatusMessage()
  {                  
    return $this->queryStatusMessage;
  }

  /**
  * Get the extended message of the status of the query
  *    
  * @return returns the extended message of the status of the query
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getStatusMessageDescription()
  {
    return $this->queryStatusMessageDescription;
  }

  /**
  * Get the resultset of a query
  *    
  * @return returns the resultset of a query
  *  
  * @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getResultset()
  {
    return $this->queryResultset;
  }

  /**
  * Get the URL a query is made to
  *
  * @return returns the URL of the web service endpoint for the query
  *
  * @author Chris Johnson
  */
  public function getURL()
  {
    return $this->url;
  }

  /**
  * Get the parameters a query is made with
  *
  * @return returns the parameters string for the query
  *
  * @author Chris Johnson
  */
  public function getParameters()
  {
    return $this->parameters;
  }

  /**
  * Get the request method (ex. get, post) for the query
  *
  * @return returns the method for the query
  *
  * @author Chris Johnson
  */
  public function getMethod()
  {
    return $this->method;
  }

  /**
  * Get the mime type a query is declared to accept
  *
  * @return returns the mime type the query will accept
  *
  * @author Chris Johnson
  */
  public function getMIME()
  {
    return $this->mime;
  }

  /**
  * Display the error encountered, in the case of no errors
  * were encountered, this is a no-op. This relies on the
  * query extension to handle error display
  *
  * @author Chris Johnson
  */
  public function displayError()
  {
    if ($this->error)
    {
      $this->extension->displayError($this->error);
    }
  }
}

//@}


/**
* The class managing creation of error messages
* @author Frederick Giasson, Structured Dynamics LLC.
*/
class QuerierError
{
  /** ID of the error */
  public $id = 0;

  /** Level of the error */
  public $level = 0;

  /** URI of the web service that caused this error */
  public $webservice = "";

  /** Name of the error */
  public $name = "";

  /** Description of the error */
  public $description = "";

  /** Debug information for this error */
  public $debugInfo = "";

  /**
  *  Constructor 
  *    
  *  @param $id ID of the error
  *  @param $level Level of the error (notice, warning, fatal)
  *  @param $webservice URI of the web service that caused this error
  *  @param $name Name of the error
  *  @param $description Description of the error
  *  @param $debugInfo Debug information for this error
  *    
  *  @return returns NULL
  *  
  *  @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($id, $level, $webservice, $name, $description, $debugInfo)
  {
    $this->id = $id;
    $this->level = strtolower($level);
    $this->webservice = $webservice;
    $this->name = $name;
    $this->description = $description;
    $this->debugInfo = $debugInfo;
  }
  

  function __destruct(){}
}

//@}

?>
