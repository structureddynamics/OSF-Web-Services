<?php

/*! @ingroup WsFramework Framework for the Web Services */
//@{

/*! @file \StructuredDynamics\osf\ws\framework\Error.php
    @brief The class managing creation of error messages
 */

namespace StructuredDynamics\osf\ws\framework;  

/** The class managing creation of error messages

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class Error
{
  /** ID of the error */
  public $id = 0;

  /** URI of the web service that caused this error */
  public $webservice = "";

  /** Name of the error */
  public $name = "";

  /** Description of the error */
  public $description = "";

  /** Debug information for this error */
  public $debugInfo = "";

  /** Mime type used to serialize the error message */
  public $mime = "";

  /** Level of the error: Fatal, Warning, Notice */
  public $level = "";

  /** Constructor 

      @param $id ID of the error
      @param $webservice URI of the web service that caused this error
      @param $name Name of the error
      @param $description Description of the error
      @param $debugInfo Debug information for this error
      @param $mime Mime type used to serialize the error message
      @param $level Level of the error: Fatal, Warning, Notice
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
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

  /** Return the error message serialized given a certain mime type 
              
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getError()
  {
    $error = "";

    switch($this->mime)
    {
      case "application/json":
        $error = "{
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
        $error = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
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

  /** Encode content to be included in XML files

      @param $string The content string to be encoded
      
      @return returns the encoded string
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function xmlEncode($string)
    { return str_replace(array ("&", "<", ">"), array ("&amp;", "&lt;", "&gt;"), $string); }

  /** Encode a string to put in a JSON value
              
      @param $string The string to escape

      @return returns the escaped string
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function jsonEncode($string) { return str_replace(array ('\\', '"', "\n", "\r", "\t"), array ('\\\\', '\\"', " ", " ", "\\t"), $string); }
}

//@}

?>