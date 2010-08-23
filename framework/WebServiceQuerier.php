<?php

  // $Id$

/*! @defgroup WsFramework Framework for the Web Services */
//@{

/*! @file \ws\framework\WebServiceQuerier.php
   @brief Querying a RESTFull web service endpoint
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
*/

/*!   @brief Query a RESTFul web service endpoint
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class WebServiceQuerier
{
  /*! @brief URL of the web service endpoint to query */
  private $url;

  /*! @brief HTTP method to use to query the endpoint (GET or POST) */
  private $method;

  /*! @brief Parameters to send to the endpoint */
  private $parameters;

  /*! @brief Mime type of the resultset that has to be returned by the web service endpoint */
  private $mime;

  /*! @brief Status of the query */
  private $queryStatus = ""; // The HTTP query status code returned by the server (ex: 200)

  /*! @brief Status message of the query */
  private $queryStatusMessage = ""; // The HTTP query status message returned by the server (ex: OK)

  /*! @brief Extended message of the status of the query */
  private $queryStatusMessageDescription =
    ""; // The HTTP query status message description returned by the server (ex: No subject concept)

  /*! @brief Resultset of the query */
  private $queryResultset = "";

  /*! @brief Internal error ID of the queried web sevice */
  private $errorId = "";

  /*! @brief Internal error name of the queried web sevice */
  private $errorName = "";

  /*! @brief Internal error description of the queried web sevice */
  private $errorDescription = "";

  /*! @brief Internal error debug information of the queried web sevice */
  private $errorDebugInfo = "";

  /*! @brief Internal error of the queried web service. The error doesn't necessarly come from the
   *            queried web service endpoint in the case of a compound web service.
   */
  public $error;

  /*!   @brief Constructor
    
      @param[in] $url URL of the web service endpoint to query
      @param[in] $method HTTP method to use to query the endpoint (GET or POST)
      @param[in] $mime Mime type of the resultset that has to be returned by the web service endpoint
      @param[in] $parameters Parameters to send to the endpoint 
      @param[in] $timeout Timeout (in milliseconds) before ending the query to a remote web service.
          
      \n
      
      @return returns returns a human readable description of the class
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($url, $method, $mime, $parameters, $timeout = 0)
  {
    $this->url = $url;
    $this->method = $method;
    $this->parameters = $parameters;
    $this->mime = $mime;
    $this->timeout = $timeout;

    $this->queryWebService();
  }

  function __destruct(){}

  /*!   @brief Send a query to a web service endpoint.
    
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function queryWebService()
  {
    $ch = curl_init();

    switch ($this->method)
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
          $this->error = new Error("WSF-600", "Fatal", $ws, "Query too big", 
                                   "The query sent to the structWSF endpoint is too big given
                                    the current settings of the instance. The size of the
                                    query is ".number_format(strlen($this->parameters), 0, " ", " ")." bytes, 
                                    and the autorized size of the query is ".$uploadMaxFileSize." bytes", $data);
          
          return(FALSE);
        }
      break;

      default:
        return FALSE;
      break;
    }

    $xml_data = curl_exec($ch);

    if ($xml_data === FALSE)
    {
      $data =
      substr($xml_data, strpos($xml_data, "\r\n\r\n") + 4, strlen($xml_data) - (strpos($xml_data, "\r\n\r\n") - 4));      
      
      // Can't reach the remote server
      $this->queryStatus = "503";
      $this->queryStatusMessage = "Service Unavailable";
      $this->queryStatusMessageDescription = "Can't reach remote server (" . curl_error($ch) . ")";
      $this->queryResultset = $data;

      $this->error = new Error("HTTP-500", "Warning", $this->url, "Can't reach remote server",
        "Can't reach remote server (" . curl_error($ch) . ")", $data);

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

      $this->error = new Error("HTTP-500", "Fatal", $ws, "Parsing Error", "PHP Parsing Error", $data);

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

      $this->error = new Error("HTTP-500", "Fatal", $ws, "Fatal Error", "PHP uncatched Fatal Error", $data);
    }

    // We have to continue. Let fix this to 200 OK so that this never raise errors within the WSF
    if ($this->queryStatus == "100")
    {
      $this->queryStatus = "200";
      $this->queryStatusMessage = "OK";
      $this->queryStatusMessageDescription = "";
      $this->queryResultset = $data;
      return;
    }

    if ($this->queryStatus != "200")
    {
      $this->queryStatusMessageDescription = str_replace(array(
        "\r",
        "\n"
      ), "", $data);

      // XML error messages
      if (strpos($this->queryStatusMessageDescription, "<error>"))
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
        $errorDebugInfo = $errorDebugInfo[1];

        $this->error = new Error($errorId, $errorLevel, $errorWS, $errorName, $errorDescription, $errorDebugInfo);

        return;
      }

    // JSON error messages
    }
    else
    {
      $this->queryResultset = $data;
    }

    return;
  }

  /*!   @brief Get the status of the query
              
      \n
      
      @return returns the status of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getStatus()
  {
    return $this->queryStatus;
  }

  /*!   @brief Get the message of the status of the query
              
      \n
      
      @return returns the message of the status of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getStatusMessage()
  {
    return $this->queryStatusMessage;
  }

  /*!   @brief Get the extended message of the status of the query
              
      \n
      
      @return returns the extended message of the status of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getStatusMessageDescription()
  {
    return $this->queryStatusMessageDescription;
  }

  /*!   @brief Get the resultset of a query
              
      \n
      
      @return returns the resultset of a query
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getResultset()
  {
    return $this->queryResultset;
  }
}

//@}


/*!   @brief The class managing creation of error messages
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
class Error
{
  /*! @brief ID of the error */
  public $id = 0;

  /*! @brief Level of the error */
  public $level = 0;

  /*! @brief URI of the web service that caused this error */
  public $webservice = "";

  /*! @brief Name of the error */
  public $name = "";

  /*! @brief Description of the error */
  public $description = "";

  /*! @brief Debug information for this error */
  public $debugInfo = "";

  /*!   @brief Constructor 
              
      \n
      
      @param[in] $id ID of the error
      @param[in] $level Level of the error (notice, warning, fatal)
      @param[in] $webservice URI of the web service that caused this error
      @param[in] $name Name of the error
      @param[in] $description Description of the error
      @param[in] $debugInfo Debug information for this error
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
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

?>