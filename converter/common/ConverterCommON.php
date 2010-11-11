<?php

/*! @defgroup WsConverterCommON Converter commON Web Service */
//@{

/*! @file \ws\converter\common\ConverterCommON.php
   @brief Define the commON converter class
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.
   
   \n\n\n
 */


/*!   @brief Convert commON data into RDF.
     @details   This class takes commON files as input, convert them into RDF using linkage schemas, 
            and output RDF in different formats.
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
class ConverterCommON extends WebService
{
  /*! @brief Database connection */
  private $db;

  /*! @brief Conneg object that manage the content negotiation capabilities of the web service */
  private $conneg;

  /*! @brief URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /*! @brief Text being converted */
  private $text;

  /*! @brief Mime type of the document */
  private $docmime;

  /*! @brief Type of the resource being converted */
  private $type;

  /*! @brief Error message to report */
  private $errorMessages = "";

  /*! @brief IP of the requester */
  private $requester_ip = "";

  /*! @brief Requested IP */
  private $registered_ip = "";

  /*! @brief Parser */
  private $parser;

  /*! @brief Defined dummany namespaces/prefixes used for data conversion for some serializations */
  private $namespaces = array();

  /*! @brief   Custom linkage schema used to include within the dataset's description when no linkage schemas exists
   *         for some types and attributes.
   */
  private $customLinkageSchema;

  /*! @brief Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/iron+csv", "application/rdf+n3", "application/*", "text/xml", "text/*", "*/*");

  /*! @brief Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/converter/common/",
                        "_200": {
                          "id": "WS-CONVERTER-COMMON-200",
                          "level": "Warning",
                          "name": "No linkage file specified",
                          "description": "No linkage file of type \'RDF\' has been defined for this Instance Record Vocabulary file."
                        },
                        "_201": {
                          "id": "WS-CONVERTER-COMMON-201",
                          "level": "Warning",
                          "name": "No data to convert",
                          "description": "No data is available for conversion"
                        },
                        "_300": {
                          "id": "WS-CONVERTER-COMMON-300",
                          "level": "Warning",
                          "name": "CSV parsing error(s)",
                          "description": "CSV parsing error(s)"
                        },
                        "_301": {
                          "id": "WS-CONVERTER-COMMON-301",
                          "level": "Warning",
                          "name": "commON validation error(s)",
                          "description": "commON validation error(s)"
                        },  
                        "_302": {
                          "id": "WS-CONVERTER-COMMON-302",
                          "level": "Warning",
                          "name": "Unsupported Document Mime",
                          "description": "The MIME type of the document you sent to this commON conversion web service is not supported."
                        }
                      }';


  /*!   @brief Constructor
       @details   Initialize the commON Converter Web Service
              
      \n
      
      @param[in] $document Text of a commON document
      @param[in] $docmime Mime type of the document
      @param[in] $registered_ip Target IP address registered in the WSF
      @param[in] $requester_ip IP address of the requester
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($document = "", $docmime = "application/iron+csv", $registered_ip, $requester_ip)
  {
    parent::__construct();

    $this->text = $document;
    $this->docmime = $docmime;

    $this->requester_ip = $requester_ip;

    if($registered_ip == "")
    {
      $this->registered_ip = $requester_ip;
    }
    else
    {
      $this->registered_ip = $registered_ip;
    }

    if(strtolower(substr($this->registered_ip, 0, 4)) == "self")
    {
      $pos = strpos($this->registered_ip, "::");

      if($pos !== FALSE)
      {
        $account = substr($this->registered_ip, $pos + 2, strlen($this->registered_ip) - ($pos + 2));

        $this->registered_ip = $requester_ip . "::" . $account;
      }
      else
      {
        $this->registered_ip = $requester_ip;
      }
    }

    $this->irJSONResources = array();

    $this->uri = $this->wsf_base_url . "/wsf/ws/converter/common/";
    $this->title = "CommON Converter Web Service";
    $this->crud_usage = new CrudUsage(FALSE, TRUE, FALSE, FALSE);
    $this->endpoint = $this->wsf_base_url . "/ws/converter/common/";

    $this->dtdURL = "converter/common.dtd";

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct()
  {
    parent::__destruct();

    if(isset($this->db))
    {
      $this->db->close();
    }
  }

  /*!   @brief Validate a query to this web service
              
      \n
      
      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  protected function validateQuery() { return; }

  protected function splitUri($str, &$base, &$ext)
  {
    $pos = FALSE;

    $base = "";
    $ext = "";

    if(($pos = strrpos($str, "#")) === FALSE)
    {
      $pos = strrpos($str, "/");
    }

    if($pos !== FALSE)
    {
      $base = substr($str, 0, $pos);
      $ext = substr($str, $pos + 1, strlen($str) - $pos - 1);
    }
    else
    {
      $base = "";
      $ext = $str;
    }
  }

  /*!   @brief Returns the error structure
              
      \n
      
      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getError() { return ($this->conneg->error); }

  /*!   @brief Generate the converted irJSON items using the internal XML representation
              
      \n
      
      @return a XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResultset()
  {
    if($this->docmime == "application/iron+csv")
    {
      // Get the ID of the dataset has the base ID of the records
      $dataset = $this->parser->getDataset();
      $datasetID = "";
      
      if(isset($dataset["&id"][0]["value"]))
      {
        $datasetID = $dataset["&id"][0]["value"];
      }
      
      $commonLinkageSchema = $this->parser->getLinkageSchema();
      
      // Check if a linkage file of kind RDF has been defined for this irJSON file.
      if(isset($commonLinkageSchema["description"]["&linkedType"]) &&
         $commonLinkageSchema["description"]["&linkedType"][0] == "application/rdf+xml")
      {
        $structXML = '
          <resultset>
            <prefix entity="owl" uri="http://www.w3.org/2002/07/owl#"/>
            <prefix entity="rdf" uri="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/>
            <prefix entity="rdfs" uri="http://www.w3.org/2000/01/rdf-schema#"/>
        
        ';
        
        // Convert each record that have been converted
        foreach($this->parser->getCommonRecords() as $record)
        {
          // Map ID & type
          $type = array();

          if(count($record["&type"]) == 0)
          {
            array_push($type, "http://www.w3.org/2002/07/owl#Thing");
          }
          elseif(count($record["&type"]) >= 1)
          {
            foreach($record["&type"] as $rt)
            {
              // check in the linkage file for the type
              $t = $this->parser->getLinkedType($rt["value"]);

              if($t == "")
              {
                // If the type doesn't exist, we simply use the generic owl:Thing type
                array_push($type, "http://www.w3.org/2002/07/owl#Thing");
              }
              else
              {
                // Otherwise we use the linked type
                array_push($type, $t);
              }
            }
          }
          
          // Get the ID of the record
          $recordId = $datasetID . $record["&id"][0]["value"];

          // Serialize the type(s) used to define the record
          if(count($type) == 1)
          {
            $structXML .= "<subject type=\"" . $this->xmlEncode($type[0]) . "\" uri=\"" . $this->xmlEncode($recordId) . 
                                                                                                                "\">\n";
          }
          else
          {
            $n3 .= "\n";

            $first = TRUE;
            
            foreach($type as $key => $t)
            {
              if($first === TRUE)
              {
                $structXML .= "<subject type=\"" . $this->xmlEncode($t) . "\" uri=\"" . $this->xmlEncode($recordId) . 
                                                                                                                "\">\n";
                $first = FALSE;
              }
              else
              {
                // Multiple types defined for this subject.
                $structXML .= "
                  <predicate type=\"rdf:type\">
                    <object uri=\"" . $this->xmlEncode($t) . "\">1282</object>
                  </predicate>
                ";
              }
            }
          }

          // Map properties / values of the record
          foreach($record as $property => $values)
          {
            // Make sure we don't process twice the ID and the TYPE
            if($property != "&id" && $property != "&type")
            {
              foreach($values as $value)
              {
                if($value != "")
                {
                  // Check for possible reification statements
                  $reifications = "";
                  
                  if(is_array($value["reify"]))
                  {
                    foreach($value["reify"] as $reifiedAttribute => $reiValues)
                    {
                      $rp = $this->parser->getLinkedProperty($reifiedAttribute);

                      // Reification re-use RDF's reification vocabulary: http://www.w3.org/TR/REC-rdf-syntax/#reification

                      if($rp == "")
                      {
                        $reiProperty = $datasetID . substr($reifiedAttribute, 1, strlen($reifiedAttribute) - 1);
                      }
                      // @TODO: Check if "@" or "@@"
                      $reifications .= "<reify type=\"" . $this->xmlEncode($reiProperty) . "\" value=\"" . 
                                        $this->xmlEncode($reiValue) . "\" />\n";
                    }
                  }                  

                  // Check if this attribute is part of the linkage schema
                  $p = $this->parser->getLinkedProperty($property);

                  if($p == "")
                  {
                    // If the attribute to be converted is not part of the linakge schema, then we
                    // simply create a "on-the-fly" attribute by using the $baseOntology URI.
                    $p = $datasetID . substr($property, 1, strlen($property) - 1);
                  }

                  // Check if the value is a local record reference
                  if(substr($value["value"], 0, 1) == "@")
                  {
                    if($reifications != "")
                    {
                      $structXML .= "
                        <predicate type=\"" . $this->xmlEncode($p) . "\">
                          <object uri=\"" . $this->xmlEncode($datasetID . $value["value"]) . "\">
                            " . $this->xmlEncode($reifications) . "
                          </object>
                        </predicate>
                      ";                    
                    }
                    else
                    {
                      $structXML .= "
                        <predicate type=\"" . $this->xmlEncode($p) . "\">
                          <object uri=\"" . $this->xmlEncode($datasetID . $value["value"]) . "\" />
                        </predicate>
                      ";                    
                    }
                  }
                  // Check if the value is an external record reference
                  elseif(substr($value["value"], 0, 2) == "@@")
                  {
                    if($reifications != "")
                    {
                      $structXML .= "
                        <predicate type=\"" . $this->xmlEncode($p) . "\">
                          <object uri=\"" . $this->xmlEncode($value["value"]) . "\">
                            " . $this->xmlEncode($reifications) . "
                          </object>
                        </predicate>
                      ";                    
                    }
                    else
                    {
                      $structXML .= "
                        <predicate type=\"" . $this->xmlEncode($p) . "\">
                          <object uri=\"" . $this->xmlEncode($value["value"]) . "\" />
                        </predicate>
                      ";                    
                    }                   
                  }
                  else
                  {
                    // @TODO adding reification statements here once the @ @@ are handled. 
                    //       See task: http://community.openstructs.org/content/updating-reification-structxml
                    
                    // The value is a literal
                    $structXML .= "
                      <predicate type=\"" . $this->xmlEncode($p) . "\">
                        <object type=\"rdfs:Literal\">".$this->xmlEncode($value["value"])."</object>
                      </predicate>
                    ";                    
                  }                  
                }
              }
            }
          }
          
          $structXML .= "</subject>\n";
        }        
        
        
        $structXML .= '
          </resultset>
        ';
        
        return($structXML);  
      }

      // No RDF linkage file exists for this irJSON file, then we throw an error
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
      $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
        $this->errorMessenger->_200->name, $this->errorMessenger->_200->description,
        "No linkage file of type 'RDF' has been defined for this commON file. Cant convert this file in '"
        . $this->conneg->getMime() . "'", $this->errorMessenger->_200->level);
      return;
    }

    // Unsupported docmime type

    $this->conneg->setStatus(400);
    $this->conneg->setStatusMsg("Bad Request");
    $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
    $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
      $this->errorMessenger->_302->name, $this->errorMessenger->_302->description,
      "Mime type you requested: " . $this->docmime, $this->errorMessenger->_302->level);
  }

  /*!   @brief Get the domain of a URL
  
      \n
      
      @param[in] $url the full URL
             
      @return the domain name of the URL *with* the prefix "http://"

      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function get_domain($url)
  {
    if(strlen($url) > 8)
    {
      $pos = strpos($url, "/", 8);

      if($pos === FALSE)
      {
        return $url;
      }
      else
      {
        return substr($url, 0, $pos);
      }
    }
    else
    {
      return $url;
    }
  }

  /*!   @brief Inject the DOCType in a XML document
              
      \n
      
      @param[in] $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function injectDoctype($xmlDoc)
  {
    $posHeader = strpos($xmlDoc, '"?>') + 3;
    $xmlDoc = substr($xmlDoc, 0, $posHeader)
      . "\n<!DOCTYPE resultset PUBLIC \"-//Structured Dynamics LLC//Converter commON DTD 0.1//EN\" \""
      . $this->dtdBaseURL . $this->dtdURL . "\">" . substr($xmlDoc, $posHeader, strlen($xmlDoc) - $posHeader);

    return ($xmlDoc);
  }


  /*!   @brief Do content negotiation as an external Web Service
              
      \n
      
      @param[in] $accept Accepted mime types (HTTP header)
      
      @param[in] $accept_charset Accepted charsets (HTTP header)
      
      @param[in] $accept_encoding Accepted encodings (HTTP header)
  
      @param[in] $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
  {
    $this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language,
      ConverterCommON::$supportedSerializations);

    // No text to process? Throw an error.
    if($this->text == "")
    {
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
      $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
        $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
        $this->errorMessenger->_201->level);
    }
  }

  /*!   @brief Do content negotiation as an internal, pipelined, Web Service that is part of a Compound Web Service
              
      \n
      
      @param[in] $accept Accepted mime types (HTTP header)
      
      @param[in] $accept_charset Accepted charsets (HTTP header)
      
      @param[in] $accept_encoding Accepted encodings (HTTP header)
  
      @param[in] $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
    { $this->ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language); }

  /*!   @brief Returns the response HTTP header status
              
      \n
      
      @return returns the response HTTP header status
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatus() { return $this->conneg->getStatus(); }

  /*!   @brief Returns the response HTTP header status message
              
      \n
      
      @return returns the response HTTP header status message
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatusMsg() { return $this->conneg->getStatusMsg(); }

  /*!   @brief Returns the response HTTP header status message extension
              
      \n
      
      @return returns the response HTTP header status message extension
    
      @note The extension of a HTTP status message is
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_getResponseHeaderStatusMsgExt() { return $this->conneg->getStatusMsgExt(); }

  /*!   @brief Serialize the converted UCB Memorial Data content into different serialization formats
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize()
  {
    $rdf_part = "";

    switch($this->conneg->getMime())
    {
      case "text/xml":
      {
        return($this->pipeline_getResultset());
      }
      break;
      
      case "application/rdf+n3":
      {
        $dataset = $this->parser->getDataset();
        
        $datasetID = "";
      
        if(isset($dataset["&id"][0]["value"]))
        {
          $datasetID = $dataset["&id"][0]["value"];
        }

        return($this->parser->getRdfN3($datasetID, $datasetID));
      }
      break;
    }
  }

  /*!   @brief Non implemented method (only defined)
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function pipeline_serialize_reification()
  {
    $rdf_reification = "";

    switch($this->conneg->getMime())
    {

    }
  }

  /*!   @brief Serialize the converted UCB Memorial Data content into different serialization formats
              
      \n
      
      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_serialize()
  {
    // Check for parsing errors
    if($this->conneg->getStatus() != 200)
    {
      return;
    }
    else
    {
      switch($this->conneg->getMime())
      {
        case "text/xml":
        case "application/rdf+n3":
          return ($this->pipeline_serialize());
        break;
      }
    }
  }

  /*!   @brief Get the namespace of a URI
              
      @param[in] $uri Uri of the resource from which we want the namespace
              
      \n
      
      @return returns the extracted namespace      
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function getNamespace($uri)
  {
    $pos = strpos($uri, "#");

    if($pos !== FALSE)
    {
      return array (substr($uri, 0, $pos) . "#", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
    }
    else
    {
      $pos = strrpos($uri, "/");

      if($pos !== FALSE)
      {
        return array (substr($uri, 0, $pos) . "/", substr($uri, $pos + 1, strlen($uri) - ($pos + 1)));
      }
    }

    return (FALSE);
  }


  /*!   @brief Normalize the remaining of a URI
              
      \n
      
      @param[in] $uri The remaining of a URI to normalize
      
      @return a Normalized remaining URI
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function uriEncode($uri)
  {
    $uri = preg_replace("|[^a-zA-z0-9]|", " ", $uri);
    $uri = preg_replace("/\s+/", " ", $uri);
    $uri = str_replace(" ", "_", $uri);

    return ($uri);
  }


  /*!   @brief Sends the HTTP response to the requester
              
      \n
      
      @param[in] $content The content (body) of the response.
      
      @return NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function ws_respond($content)
  {
    // First send the header of the request
    $this->conneg->respond();

    // second, send the content of the request

    // Make sure there is no error.
    if($this->conneg->getStatus() == 200)
    {
      echo $content;
    }

    $this->__destruct();
  }

  /*!   @brief Convert the target document
              
      \n
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function process()
  {
    if($this->conneg->getStatus() == 200)
    {        
      switch($this->docmime)
      {
        case "application/iron+csv":
          $this->parser = new CommonParser($this->text);

//          var_dump($this->parser);die;

          $csvErrors = $this->parser->getCsvErrors();
          $commonErrors = $this->parser->getCommonErrors();

          if($csvErrors && count($csvErrors) > 0)
          {
            $errorMsg = "";

            foreach($csvErrors as $key => $error)
            {
              $errorMsg .= "\n(" . ($key + 1) . ") $error \n";
            }

            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_300->name);
            $this->conneg->setError($this->errorMessenger->_300->id, $this->errorMessenger->ws,
              $this->errorMessenger->_300->name, $this->errorMessenger->_300->description, $errorMsg,
              $this->errorMessenger->_300->level);
          }
          elseif($commonErrors && count($commonErrors) > 0)
          {
            $errorMsg = "";

            foreach($commonErrors as $key => $error)
            {
              $errorMsg .= "\n(" . ($key + 1) . ") $error \n";
            }

            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
            $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
              $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, $errorMsg,
              $this->errorMessenger->_301->level);
          }
        break;

        case "text/xml": break;
      }
    }
  }
}

//@}

?>