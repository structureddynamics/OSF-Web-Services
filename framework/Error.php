<?php

/*! @ingroup WsFramework Framework for the Web Services */
//@{

/*! @file \ws\framework\Error.php
   @brief The class managing creation of error messages
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief The class managing creation of error messages
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class Error
{
  /*! @brief ID of the error */
  public $id = 0;

  /*! @brief URI of the web service that caused this error */
  public $webservice = "";

  /*! @brief Name of the error */
  public $name = "";

  /*! @brief Description of the error */
  public $description = "";

  /*! @brief Debug information for this error */
  public $debugInfo = "";

  /*! @brief Mime type used to serialize the error message */
  public $mime = "";

  /*! @brief Level of the error: Fatal, Warning, Notice */
  public $level = "";

  /*!   @brief Constructor 
              
      \n
      
      @param[in] $id ID of the error
      @param[in] $webservice URI of the web service that caused this error
      @param[in] $name Name of the error
      @param[in] $description Description of the error
      @param[in] $debugInfo Debug information for this error
      @param[in] $mime Mime type used to serialize the error message
      @param[in] $level Level of the error: Fatal, Warning, Notice
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($id, $webservice, $name, $description, $debugInfo, $mime, $level)
  {
    $this->id = $id;
    $this->webservice = $webservice;
    $this->name = $name;
    $this->description = $description;
    $this->debugInfo = $debugInfo;
    $this->mime = $mime;
    $this->level = $level;
  }

  function __destruct() { }

  /*!   @brief Return the error message serialized given a certain mime type 
              
      \n
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getError()
  {
    $error = "";

    switch($this->mime)
    {
      case "application/json":
        $error = "  {
                  \"id\": \"" . $this->jsonEncode($this->id) . "\",
                  \"webservice\": \"" . $this->jsonEncode($this->webservice) . "\",
                  \"name\": \"" . $this->jsonEncode($this->name) . "\",
                  \"description\": \"" . $this->jsonEncode($this->description) . "\",
                  \"debugInformation\": \"" . $this->jsonEncode($this->debugInfo) . "\",
                  \"level\": \"" . $this->jsonEncode($this->level) . "\"
                }
                ";
      break;

      case "text/plain":
        $error = $this->id . ", " . $this->webservice . ", " . $this->name . ", " . $this->description . ", "
          . $this->debugInfo;
      break;

      case "text/xml":
      default:
        $error = "  <?xml version=\"1.0\" encoding=\"utf-8\"?>
                <error>
                  <id>" . $this->xmlEncode($this->id) . "</id>
                  <webservice>" . $this->xmlEncode($this->webservice) . "</webservice>
                  <name>" . $this->xmlEncode($this->name) . "</name>
                  <description>" . $this->xmlEncode($this->description) . "</description>
                  <debugInformation>" . $this->xmlEncode($this->debugInfo) . "</debugInformation>
                  <level>" . $this->xmlEncode($this->level) . "</level>
                </error>
                ";
      break;
    }

    return ($error);
  }

  /*!   @brief Encode content to be included in XML files
              
      \n
      
      @param[in] $string The content string to be encoded
      
      @return returns the encoded string
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function xmlEncode($string)
    { return str_replace(array ("\\", "&", "<", ">"), array ("%5C", "&amp;", "&lt;", "&gt;"), $string); }

  /*!   @brief Encode a string to put in a JSON value
              
      @param[in] $string The string to escape
              
      \n
      
      @return returns the escaped string
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function jsonEncode($string) { return str_replace(array ('\\', '"'), array ('\\\\', '\\"'), $string); }
}

//@}

?>