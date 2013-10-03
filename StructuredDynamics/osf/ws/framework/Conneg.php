<?php

/*! @ingroup WsFramework Framework for the Web Services */
//@{

/*! @file \StructuredDynamics\osf\ws\framework\Conneg.php
    @brief The class that manage the content negotiation between any web service.
 */

namespace StructuredDynamics\osf\ws\framework;  
 
/** The class that manage the content negotiation between any web service.

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class Conneg
{
  /** Mime type of the query */
  private $mime = "text/plain"; // http://www.iana.org/assignments/media-types/

  /** Charset of the query */
  private $charset = "utf-8"; // http://www.iana.org/assignments/character-sets

  /** Encoding of the query */
  private $encoding = "identity"; // http://www.iana.org/assignments/http-parameters           (content-coding section)

  /** Language of the query */
  private $lang = "en"; // http://www.iana.org/assignments/language-subtag-registry

  /** Status of the query */
  private $status = 200; // Check the status of the interaction with the user.

  /** Status message of the query */
  private $statusMsg = "OK";

  /** Extended message of the status of the query */
  private $statusMsgExt = "";

  /** Supported serializations by the service hanlding this query */
  private $supported_serializations = "";

  /** Error structure that handle error reporting to users */
  public $error;

  /** Constructor 

      @param $accept Accepted mime type(s) for the query
      @param $accept_charset Accepted charset for the query
      @param $accept_encoding Accepted encoding for the query
      @param $accept_language Accepted language for the query
      @param $supported_serializations Supported serializations by the target service
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($accept = "", $accept_charset = "", $accept_encoding = "", $accept_language = "",
    $supported_serializations)
  {
    $this->supported_serializations = $supported_serializations;

    $this->accept($accept);

    if($this->status == 200)
    {
      $this->accept_charset($accept_charset);
    }

    if($this->status == 200)
    {
      $this->accept_encoding($accept_encoding);
    }

    if($this->status == 200)
    {
      $this->accept_language($accept_language);
    }
  }

  function __destruct() { }

  /** Send an answer to the requester 
              
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function respond()
  {
    // Make sure the output buffer is empty when we output the result of the 
    // web service endpoint. 
    ob_clean();    
    
    header("HTTP/1.1 " . $this->status . " " . $this->statusMsg);
    header("Content-Type: " . $this->mime . "; charset=" . $this->charset);
    header("Content-Language: " . $this->lang);
    header("Content-Encoding: " . $this->encoding);

    if(!isset($this->error))
    {
      if($this->statusMsgExt != "")
      {
        echo $this->statusMsgExt . "\n";
      }
    }
    else
    {
      echo $this->error->getError() . "\n";
    }

    $this->__destruct();
  }

  /** Get the mime type of the query 

      @return returns accepted mime type
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getAccept() { return $this->mime; }

  /** Get the charset of the query

      @return returns the accepted charset of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getAcceptCharset() { return $this->charset; }

  /** Get the encoding of the query

      @return returns the accepted encoding of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getAcceptEncoding() { return $this->encoding; }

  /** Get the language of the query

      @return returns the language charset of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getAcceptLanguage() { return $this->lang; }

  /** Set the status of the query

      @return returns the status that has been setted.
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setStatus($status) { return $this->status = $status; }

  /** Set the message of the status of the query

      @return returns the message of the status that has been setted.
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setStatusMsg($statusMsg) { return $this->statusMsg = $statusMsg; }

  /** Set the extended message of the status of the query

      @return returns the extended message of the status that has been setted.
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setStatusMsgExt($statusMsgExt) { return $this->statusMsgExt = $statusMsgExt; }

  /** Set the error message to embed in the body of the HTTP message  

      @param $id ID of the error
      @param $webservice URI of the web service that caused this error
      @param $name Name of the error
      @param $description Description of the error
      @param $debugInfo Debug information for this error
      @param $level Error level of the error (warning, error, fatal)
      
      @return returns NULL      
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function setError($id, $webservice, $name, $description, $debugInfo, $level)
  {
    $mime = "text/plain";

    switch($this->mime)
    {
      case "application/rdf+xml":
      case "application/xhtml+rdfa":
      case "text/rdf+n3":
      case "text/xml":
      case "text/html":
      case "application/sparql-results+xml":
      case "application/rdf+n3":
        $mime = "text/xml";
      break;

      case "application/sparql-results+json":
      case "application/json":
      case "application/iron+json":
      case "application/bib+json":
      case "application/rdf+json":
        $mime = "application/json";
      break;

      case "text/tsv":
      case "text/csv":
      case "application/iron+csv":
      case "application/x-bibtex":
        $mime = "text/plain";
      break;
    }

    $this->error = new Error($id, $webservice, $name, $description, $debugInfo, $mime, $level);
  }

  /** Get the status of the query

      @return returns the status of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getStatus() { return $this->status; }

  /** Get the message of the status of the query

      @return returns the message of the status of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getStatusMsg() { return $this->statusMsg; }

  /** Get the extended message of the status of the query

      @return returns the extended message of the status of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getStatusMsgExt() { return $this->statusMsgExt; }

  /** Get the mime of the query

      @return returns the mime of the query
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function getMime() { return $this->mime; }

  /** Check if a query mime is accepted and set the status of the query accordingly
          
      @param $header HTTP header of the query 

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function accept($header)
  {
    $accepts = array();
    $mimes = array();

    if(strlen($header) > 0)
    {
      // break up string into pieces (languages and q factors)
      preg_match_all('/([^,]+)/', $header, $accepts);

      // It is used to make a distinction in an array of mimes are doesn't have any weight value.
      // If the requester has 3 mimes, without any weight for them, then we want to keep the order
      // when we sort the mimes array. It is what this value is used for.
      $equalityDistinguisher = 0.0001;
      
      foreach($accepts[0] as $accept)
      {
        $foo = explode(";", str_replace(" ", "", $accept));

        if(isset($foo[1]))
        {
          if(stripos($foo[1], "q=") !== FALSE)
          {
            $foo[1] = str_replace("q=", "", $foo[1]);
            
            /*
             This is to ensure that the "q=1" parameter will be prioritary on the "non-q" accept mimes.
             It is the reason why we set it to 3
             
             This is particularly interesting in some usecases when a user agent "highjack" the accept header sent by 
             another user agent. One good usecase is the one of a Flash Movie embedded in a FireFox Browser window.
             When using the HTTPService API, the FireFox browser will add its default accept mimes to the query
             sent by the embeded flash movie. By seting "q=1" for any flash movies that send a query, it ensures
             that that mime will be the one selected by the WS, and not the ones added by FireFox.
            */
            if($foo[1] == "1")
            {
              $foo[1] = 3;
            }
          }
          else
          {
            $foo[1] = 1 - $equalityDistinguisher;
            
            $equalityDistinguisher += 0.0001;
          }
        }
        else
        {
          array_push($foo, 1 - $equalityDistinguisher);
          
          $equalityDistinguisher += 0.0001;
        }

        $mimes[$foo[0]] = $foo[1];
      }

      // In the case that there is a Accept: header, but that it is empty. We set it to: anything.
      if(count($mimes) <= 0)
      {
        $mimes["*/*"] = 1;
      }

      arsort($mimes, SORT_NUMERIC);

      $notAcceptable406 = TRUE;

      foreach($mimes as $mime => $q)
      {
        $mime = strtolower($mime);

        if($mime == "application/rdf+xml"
          && array_search("application/rdf+xml", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/rdf+xml";

          $notAcceptable406 = FALSE;

          break;
        }

        if(($mime == "application/rdf+n3"
          && array_search("application/rdf+n3", $this->supported_serializations) !== FALSE) ||
          ($mime == "text/rdf+n3"
          && array_search("text/rdf+n3", $this->supported_serializations) !== FALSE)
          || ($mime == "application/*" && array_search("application/*", $this->supported_serializations) !== FALSE))
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/rdf+n3";

          $notAcceptable406 = FALSE;

          break;
        }

        if(($mime == "text/plain" && array_search("text/plain", $this->supported_serializations) !== FALSE)
          || ($mime == "text/*" && array_search("text/*", $this->supported_serializations) !== FALSE)
          || ($mime == "application/*" && array_search("application/*", $this->supported_serializations) !== FALSE)
            || ($mime == "*/*" && array_search("*/*", $this->supported_serializations) !== FALSE))
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "text/xml";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/xhtml+xml"
          && array_search("application/xhtml+xml", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/xhtml+rdfa";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/xhtml+rdfa"
          && array_search("application/xhtml+rdfa", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/xhtml+rdfa";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "text/xml" && array_search("text/xml", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "text/xml";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/xml" && array_search("application/xml", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/xml";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "text/html" && array_search("text/html", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "text/html";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/sparql-results+xml"
          && array_search("application/sparql-results+xml", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/sparql-results+xml";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/sparql-results+json"
          && array_search("application/sparql-results+json", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/sparql-results+json";

          $notAcceptable406 = FALSE;

          break;
        }
        
        if($mime == "application/rdf+json"
          && array_search("application/rdf+json", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/rdf+json";

          $notAcceptable406 = FALSE;

          break;
        }        

        if($mime == "text/tsv" && array_search("text/tsv", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "text/tsv";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "text/csv" && array_search("text/csv", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "text/csv";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/sparql-results+json"
          && array_search("application/sparql-results+json", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/sparql-results+json";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/x-bibtex"
          && array_search("application/x-bibtex", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/x-bibtex";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/json" && array_search("application/json", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/json";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/iron+json"
          && array_search("application/iron+json", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/iron+json";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/iron+csv"
          && array_search("application/iron+csv", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/iron+csv";

          $notAcceptable406 = FALSE;

          break;
        }

        if($mime == "application/bib+json"
          && array_search("application/bib+json", $this->supported_serializations) !== FALSE)
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->mime = "application/bib+json";

          $notAcceptable406 = FALSE;

          break;
        }
      }

      if($notAcceptable406)
      {
        $this->status = 406;
        $this->statusMsg = "Not Acceptable";
        $this->statusMsgExt = "Unacceptable mime type requested";
      }
    }
    else
    {
      // If no access header specified; it means that the client accept *anything*
      // In such a case we send back rdf+xml
      $this->status = 200;
      $this->statusMsg = "OK";
      $this->mime = "application/rdf+xml";
    }
  }

  /** Check if a query charset is accepted and set the status of the query accordingly
          
      @param $header HTTP header of the query 

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function accept_charset($header)
  {
    $accepts = array();
    $charsets = array();

    if(strlen($header) > 0)
    {
      // break up string into pieces (languages and q factors)
      preg_match_all('/([^,]+)/', $header, $accepts);

      foreach($accepts[0] as $accept)
      {
        $foo = explode(";", str_replace(" ", "", $accept));

        if(isset($foo[1]))
        {
          if(stripos($foo[1], "q=") !== FALSE)
          {
            $foo[1] = str_replace("q=", "", $foo[1]);
          }
          else
          {
            $foo[1] = "1";
          }
        }
        else
        {
          array_push($foo, "1");
        }

        $charsets[$foo[0]] = $foo[1];
      }

      // In the case that there is a Accept-Charset: header, but that it is empty. We set it to: anything.
      if(count($charsets) <= 0)
      {
        $charsets["*"] = 1;
      }

      arsort($charsets, SORT_NUMERIC);

      $notAcceptable406 = TRUE;

      foreach($charsets as $charset => $q)
      {
        $charset = strtolower($charset);

        if($charset == "utf-8" || $charset == "*")
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->charset = "utf-8";

          $notAcceptable406 = FALSE;

          break;
        }
      }

      if($notAcceptable406)
      {
        $this->status = 406;
        $this->statusMsg = "Not Acceptable";
        $this->statusMsgExt = "Unacceptable charset requested";
      }
    }
    else
    {
      // If no access header specified; it means that the client accept *anything*
      // In such a case we send back utf-8
      $this->status = 200;
      $this->statusMsg = "OK";
      $this->charset = "utf-8";
    }
  }

  /** Check if a query encoding is accepted and set the status of the query accordingly
          
      @param $header HTTP header of the query 

      @author Frederick Giasson, Structured Dynamics LLC.
    
      @bug With a Post query using HTTPService of Flex, apparently the Encoding header create a 406 here. 
  */
  public function accept_encoding($header)
  {
    $accepts = array();
    $encodings = array();

    if(strlen($header) > 0)
    {
      // break up string into pieces (languages and q factors)
      preg_match_all('/([^,]+)/', $header, $accepts);

      foreach($accepts[0] as $accept)
      {
        $foo = explode(";", str_replace(" ", "", $accept));

        if(isset($foo[1]))
        {
          if(stripos($foo[1], "q=") !== FALSE)
          {
            $foo[1] = str_replace("q=", "", $foo[1]);
          }
          else
          {
            $foo[1] = "1";
          }
        }
        else
        {
          array_push($foo, "1");
        }

        $encodings[$foo[0]] = $foo[1];
      }

      // In the case that there is a Accept-Charset: header, but that it is empty. We set it to: anything.
      if(count($encodings) <= 0)
      {
        $encodings["*"] = 1;
      }

      arsort($encodings, SORT_NUMERIC);

      $notAcceptable406 = TRUE;

      foreach($encodings as $encoding => $q)
      {
        $encoding = strtolower($encoding);

        if($encoding == "identity" || $encoding == "*")
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->encoding = "identity";

          $notAcceptable406 = FALSE;

          break;
        }
      }

      if($notAcceptable406)
      {
      /*        $this->status = 406;
              $this->statusMsg = "Not Acceptable";
              $this->statusMsgExt = "Unacceptable encoding requested";*/
      }
    }
    else
    {
      // If no access header specified; it means that the client accept *anything*
      // In such a case we send back utf-8
      $this->status = 200;
      $this->statusMsg = "OK";
      $this->encoding = "identity";
    }
  }

  /** Check if a query language is accepted and set the status of the query accordingly
          
      @param $header HTTP header of the query 

      @author Frederick Giasson, Structured Dynamics LLC.
    
      @bug With a Post query using HTTPService of Flex, apparently the Language header create a 406 here. 
    
  */
  public function accept_language($header)
  {
    $accepts = array();
    $languages = array();

    if(strlen($header) > 0)
    {
      // break up string into pieces (languages and q factors)
      preg_match_all('/([^,]+)/', $header, $accepts);

      foreach($accepts[0] as $accept)
      {
        $foo = explode(";", str_replace(" ", "", $accept));

        if(isset($foo[1]))
        {
          if(stripos($foo[1], "q=") !== FALSE)
          {
            $foo[1] = str_replace("q=", "", $foo[1]);
          }
          else
          {
            $foo[1] = "1";
          }
        }
        else
        {
          array_push($foo, "1");
        }

        $languages[$foo[0]] = $foo[1];
      }

      // In the case that there is a Accept-Charset: header, but that it is empty. We set it to: anything.
      if(count($languages) <= 0)
      {
        $languages["*"] = 1;
      }

      arsort($languages, SORT_NUMERIC);

      $notAcceptable406 = TRUE;

      foreach($languages as $language => $q)
      {
        $language = strtolower($language);

        if($language == "en" || $language == "*")
        {
          $this->status = 200;
          $this->statusMsg = "OK";
          $this->language = "en";

          $notAcceptable406 = FALSE;

          break;
        }
      }

      if($notAcceptable406)
      {
      /*        $this->status = 406;
              $this->statusMsg = "Not Acceptable";
              $this->statusMsgExt = "Unacceptable language requested";*/
      }
    }
    else
    {
      // If no access header specified; it means that the client accept *anything*
      // In such a case we send back utf-8
      $this->status = 200;
      $this->statusMsg = "OK";
      $this->language = "en";
    }
  }
}

//@}

?>