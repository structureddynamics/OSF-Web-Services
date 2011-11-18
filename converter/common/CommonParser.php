<?php

/*! @file CommonParser.php
   @brief commON parser implementation file
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief commON serialization parsing class
     @details This class will parse a commON CSV file and parse it to extract commON instance records.
            
    \n

    @todo Implementing the "metaFile" keyword
    @todo Implementing the structure Schema & "schema" keyword
    @todo Implementing the "listSeparator" keyword
    @todo Implementing the "listSeparatorEscape" keyword
    @todo Implementing the "seqNum" keyword
  
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
class CommonParser
{
  /*! @page internalDataStructures Internal Data Structures used by the commON Parser
   
     @section CSVParserInternalParsedRecordsStructure CSV Parser Internal Parsed Records Structure
     
     @n
     
     The structure of the parsed CSV records is saved in the $csvRecords private member. Its content looks like:
     
     @n
     
     @verbatim
    Array
    (
        [0] => &&recordList
    )
    Array
    (
        [0] => &id
        [1] => &type
        [2] => &prefLabel
        [3] => &homepage
        [4] => &authorClaimsPage
        [5] => &isAuthorOfTitle
        [6] => &isAuthorOfTitle&prefURL
    )
    Array
    (
        [0] => info:lib:am:2009-02-18:maria_francisca_abad_garcia
        [1] => Person
        [2] => Maria Francisca Abad-Garcia
        [3] =>
        [4] => http://authorclaim.org/profile/pab1/
        [5] => Acceso Abierto y revistas médicas españolas
        [6] => http://eprints.rclis.org/11490/1/open_acces_Medicina_Cl%C3%ADnica_2006_versi%C3%B3n_aceptada_del_autor.pdf
    )
     
     ...
     @endverbatim

     @n
     
     The "&&recordList" processor keyword tells the parser that a recordList is introduced.
     
     Then you have the CSV column structure that tels the parser how to recreate the key/value pairs
     
     And then you have a series of records.
     
     @n
     @n
       */

  /*! @brief All CSV records extracted from the CSV file */
  private $csvRecords = array();

  /*! @page internalDataStructures Internal Data Structures used by the commON Parser
 
   @section CommonParserInternalParsedRecordsStructure Common Parser Internal Parsed Records Structure
   
   @n
   
   The parser commON records are saved in the $commonRecords private member. Its structure looks like:
   
   @n
   
   @verbatim
  Array
  (
      [0] => Array
          (
              [&id] => Array
                  (
                      [0] => Array
                          (
                              [value] => info:lib:am:2009-02-18:maria_francisca_abad_garcia
                              [reify] =>
                          )
  
                  )
  
              [&type] => Array
                  (
                      [0] => Array
                          (
                              [value] => Person
                              [reify] =>
                          )
  
                  )
  
              [&prefLabel] => Array
                  (
                      [0] => Array
                          (
                              [value] => Maria Francisca Abad-Garcia
                              [reify] =>
                          )
  
                  )

                    [&homepage] => Array
                  (
                      [0] => Array                        
                          (
                              [value] => Personal Data in a Large Digital Library.
                              [reify] => Array
                                  (
                                      [&prefURL] => Array
                                          (
                                              [0] => http://dblp.uni-trier.de/db/conf/ercimdl/ecdl2000.html#CruzKK00
                                          )
  
                                  )
  
                          )
          )
                  
        ...
      )
      
    ...
  
  )
  @endverbatim

  @n
  
  Where the first array is an array of records. Then for each record item, you have a list of attributes. Each attribute
  is a list of values. Then each value is an array with two keys: "value" and "reify". "value" is the value of the triple:
  "record" "attribute" "value". "reify" is an array of meta data (reifications) attribute/value pairs about the triple
  statement.
  
  @n
  @n
   
*/

/*! @brief Array with all parsed commON records */
  private $commonRecords = array();

  /*! @page internalDataStructures Internal Data Structures used by the commON Parser
   
     @section LinkageSchemaInternalStructure Linkage Schema Internal Structure

    The Linkage Schema structure is used to map attributes and types used in a commON dataset to
    external vocabularies/taxonomies/ontologies. This structure is saved in the $commonLinkageSchema
    private member and looks like:
    
    @n
    
    @verbatim
    Array
    (
        [description] => Array
            (
                [&version] => Array
                    (
                        [0] => 0.1
                    )
    
                [&linkageType] => Array
                    (
                        [0] => application/rdf+xml
                    )
    
            )
    
        [properties] => Array
            (
                [0] => Array
                    (
                        [&attributeList] => Array
                            (
                                [0] => prefLabel
                            )
    
                        [&mapTo] => Array
                            (
                                [0] => http://www.w3.org/2000/01/rdf-schema#label
                            )
    
                    )
    
                [1] => Array
                    (
                        [&attributeList] => Array
                            (
                                [0] => homepage
                            )
    
                        [&mapTo] => Array
                            (
                                [0] => http://xmlns.com/foaf/0.1/homepage
                            )
    
                    )
    
                [2] => Array
                    (
                        [&attributeList] => Array
                            (
                                [0] => prefURL
                            )
    
                        [&mapTo] => Array
                            (
                                [0] => http://purl.org/ontology/bibo/uri
                            )
    
                    )
    
            )
    
        [types] => Array
            (
                [0] => Array
                    (
                        [&typeList] => Array
                            (
                                [0] => Person
                            )
    
                        [&mapTo] => Array
                            (
                                [0] => http://xmlns.com/foaf/0.1/Person
                            )
    
                    )
    
            )
    
    )
    ...
    @endverbatim
   */

  /*! @brief Array describing the linkage schema (if defined) of a commON file */
  private $commonLinkageSchema = array();

  /*! @brief Array describing the dataset */
  private $commonDataset = array();

  /*! @brief CSV Parsing errors stack */
  private $csvErrors = array();
  
  /*! @brief commON Validation errors stack */
  private $commonErrors = array();

  /*!   @brief Constructor. It takes the commON CSV file content as input.
              
      \n
      
      @param[in] $content commON CSV file content
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  function __construct($content)
  {
    $this->content = $content;

    // Parse the CSV file
    $this->csvParser();

    // Parse the commON records
    $this->commonParser();
  }

  /*!   @brief Returns the array of records parsed from the CSV file
              
      \n
      
      @return returns an array of records.
      
      @see @ref CSVParserInternalParsedRecordsStructure
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getCsvRecords() { return ($this->csvRecords); }


  /*!   @brief Returns the array of parsed commON records
              
      \n
      
      @return returns an array of commON records.
      
      @see @ref CommonParserInternalParsedRecordsStructure        
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getCommonRecords() { return ($this->commonRecords); }

  /*!   @brief Returns the array of the parsed linkage schema.
              
      \n
      
      @return returns an array of the parsed linkage schema
      
      @see @ref LinkageSchemaInternalStructure        
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getLinkageSchema() { return ($this->commonLinkageSchema); }

  /*!   @brief Returns the array of the parsed dataset.
              
      \n
      
      @return returns an array of the parsed dataset
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getDataset() { return ($this->dataset); }

  /*!   @brief Check for CSV parsing errors
              
      \n
      
      @return Return FALSE if no errors; returns an array of error messages if any.
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getCsvErrors()
  {
    if(count($this->csvErrors) == 0)
    {
      return (FALSE);
    }

    return ($this->csvErrors);
  }

  /*!   @brief Check for commON parsing errors
              
      \n
      
      @return Return FALSE if no errors; returns an array of error messages if any.
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getCommonErrors()
  {
    if(count($this->commonErrors) == 0)
    {
      return (FALSE);
    }

    return ($this->commonErrors);
  }

  /*!   @brief Parse a CSV files to produce the structure used by the commonParser function.
              
      \n
      
      @return returns NULL
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function csvParser()
  {
    /* Index pointing to the begenning of a record in the CSV file string */
    $startRecord = 0;

    /* Index pointing to the end of a record in the CSV file string */
    $endRecord = 0;

    /* Tells the parser if we started to parse the CSV file */
    $start = TRUE;

    /* A single record that get extracted from the CSV file */

    /*
      The structure of this record looks like:
      
      Array
      (
          [0] => info:lib:am:2009-02-18:maria_francisca_abad_garcia
          [1] => Person
          [2] => Maria Francisca Abad-Garcia
          [3] =>
          [4] => http://authorclaim.org/profile/pab1/
          [5] => Acceso Abierto y revistas médicas españolas
          [6] => http://eprints.rclis.org/11490/1/open_acces_Medicina_Cl%C3%ADnica_2006_versi%C3%B3n_aceptada_del_autor.pdf
      )

      Each keys refer to columns description such as:

      Array
      (
          [0] => &id
          [1] => &type
          [2] => &prefLabel
          [3] => &homepage
          [4] => &authorClaimsPage
          [5] => &isAuthorOfTitle
          [6] => &isAuthorOfTitle&prefURL
      )
      
       Key/value pairs can be recreated by binding the keys,  such as: "&type -> Person"
       
     */
    $record = array();

    /* Check if a string is in double quotes (necessary for proper escaping) */
    $inDoubleQuotes = FALSE;

    // Remove all extra carrier return. We normalize with "\r"
    $this->content = preg_replace("/[\r\n]+/", "\r", $this->content);

    for($i = 0; $i < strlen($this->content); $i++)
    {
      if($inDoubleQuotes)
      {
        // If we are in double quotes, we get everything until we read the other double quotes.
        if($this->content[$i] == '"')
        {
          // check if the next char is another double quote, if it is, we ignore it
          if($this->content[$i + 1] != '"')
          {
            $inDoubleQuotes = FALSE;

            // Check if the next character is a comma, or a return charrier. If it is not, we got an error
            if(($this->content[$i + 1] != "," && ($this->content[$i + 1] == " " && $this->content[$i + 2] != ","))
              && ($this->content[$i + 1] != "\r" && ($this->content[$i + 1] == " " && $this->content[$i + 2] != "\r")))
            {
              array_push($this->csvErrors,
                "CSV parser (001): A comma or a return carrier is expected after an un-escaped double quotes.");
              return;
            }
          }
          else
          {
            // We move the pointer to skip the next double quote
            $i++;
          }
        }
      }
      elseif($start && substr($this->content, 0, 1) == '"')
      {
        // First thing we have to check is if we start with double quotes
        $inDoubleQuotes = TRUE;

        $startRecord++;
        $start = FALSE;
      }
      else
      {
        // If we are not in double quotes, we get everything until we reach a comma or a line break.
        if(($this->content[$i] == "\n") || ($this->content[$i] == "\r")
          || ($this->content[$i] == "\r" && $this->content[$i + 1] == "\n"))
        {
          if($this->content[$i - 1] == '"')
          {
            $endRecord = $i - 1;
          }
          else
          {
            $endRecord = $i;
          }

          array_push($record,
            str_replace('""', '"', substr($this->content, $startRecord, ($endRecord - $startRecord))));

          $startRecord = $i + 1;


          // Add this new record to the records list
          array_push($this->csvRecords, $record);
          $record = array();

          if($this->content[$i] == "\r" && $this->content[$i + 1] == "\n")
          {
            $i++;
          }
        }
        elseif($this->content[$i] == ",")
        {
          if($this->content[$i - 1] == '"')
          {
            $endRecord = $i - 1;
          }
          else
          {
            $endRecord = $i;
          }

          array_push($record,
            str_replace('""', '"', substr($this->content, $startRecord, ($endRecord - $startRecord))));

          $startRecord = $i + 1;
        }
        elseif($this->content[$i] == '"')
        {
          if($this->content[$i - 1] == " ")
          {
            if($this->content[$i - 2] == ",")
            {
              $inDoubleQuotes = TRUE;
              $startRecord = $i + 1;
            }
            else
            {
              array_push($this->csvErrors, "CSV parser (002): An un-escaped double quote has been detected.");
              return;
            }
          }
          else
          {
            if($this->content[$i - 1] == "," || $this->content[$i - 1] == "\r")
            {
              $inDoubleQuotes = TRUE;
              $startRecord = $i + 1;
            }
            else
            {
              array_push($this->csvErrors, "CSV parser (003): An un-escaped double quote has been detected (around: '... "
                . str_replace(array ("\n", "\r"), " ", substr($this->content, $i - 5, 10)) . " ... (char #$i)').");
              return;
            }
          }
        }
      }
    }
  }

  /*!   @brief Create the commON records form the parsed CSV records
              
      \n
      
      @return returns NULL
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function commonParser()
  {
    /* Check what is the current section being processed: (1) record, (2) dataset or (3) linkage */
    $currentSection = "";

    /* Reference on the current record being processed */
    $currentRecord = "";

    /* A commON record description */
    $commonRecord = array();

    /* The record structure where to match commON record descriptions to their values */
    $recordStructure = array();

    $shouldBeRecordDescription = FALSE;

    foreach($this->csvRecords as $record)
    {
      // Check for blank lines.
      $blank = TRUE;

      foreach($record as $value)
      {
        if($value != "")
        {
          $blank = FALSE;
          break;
        }
      }

      // If we have a blank line, with skip it and continue
      if($blank)
      {
        continue;
      }

      // Change the section pointer.
      if($record[0][0] == "&" && $record[0][1] == "&")
      {
        switch($record[0])
        {
          case "&&recordList":
            $currentSection = "record";
            $shouldBeRecordDescription = TRUE;
          break;

          case "&&dataset":
            $currentSection = "dataset";
            $shouldBeRecordDescription = TRUE;
          break;

          case "&&linkage":
            $currentSection = "linkage";
            $shouldBeRecordDescription = TRUE;
          break;

          default:
            return ("Unknown section $record[0]");
          break;
        }
      }
      else
      {
        if($shouldBeRecordDescription === FALSE && $currentSection == "linkage" && $record[0][0] == "&")
        {
          // We are expecting the description of a commonRecord
          $shouldBeRecordDescription = TRUE;
        }

        if($shouldBeRecordDescription)
        {
          $recordStructure = array();

          // Lets define the record structure for the next records to parse
          foreach($record as $property)
          {
            if($property != "")
            {
              if($property[0] == "&")
              {
                array_push($recordStructure, $property);
              }
              else
              {
                array_push($this->commonErrors,
                  "commON Parser (001): A record structure property has been defined without starting with '&' ($property)");
                return;
              }
            }
            else
            {
              // If an empty column is defined for the structure section of a record, we
              // add it in the $recordStructure array. We then consider that the data publisher
              // needed it for its own spreadsheet layout.

              // This ensure that if "padding" empty-column is added to all records of the file, that it doesn't
              // raise the "Too many properties defined for the record according to the linkage schema record structure"
              // commON parsing error

              // Additionally this ensure a compatibility with some spreadsheet software such as Excel.

              array_push($recordStructure, "");
            }
          }

          if(count($recordStructure) <= 0)
          {
            array_push($this->commonErrors, "commON Parser (002): No properties defined for this record structure");
            return;
          }

          $shouldBeRecordDescription = FALSE;
        }
        else
        {
          // Depending on the processing section, we populate different parsing structures
          switch($currentSection)
          {
            // We are parsing the dataset description
            case "dataset":
              if(count($recordStructure) > count($record))
              {
                // Pad the record with empty properties values
                for($i = 0; $i < (count($recordStructure) - count($record)); $i++)
                {
                  array_push($record, "");
                }
              }

              if(count($recordStructure) < count($record))
              {
                array_push($this->commonErrors,
                  "commON Parser (003): Too many properties defined for the record according to the record structure.
                   Please make sure that you don't have empty cells in ending columns for your records, that are
                   not defined in the attribute definition line.");
                return;
              }

              foreach($recordStructure as $key => $rs)
              {
                // We simply skip empty recordStructure columns.
                if($rs == "")
                {
                  continue;
                }

                if($rs == "&id")
                {
                  // Set the ID
                  $this->dataset["&id"] = array(array ("value" => $record[$key], "reify" => "") );
                }
                
                // Check if it is a reification attribute
                elseif(($reifiedAttribute = $this->getReifiedAttribute($rs)) !== FALSE)
                {
                  if(isset($this->dataset[$reifiedAttribute["attribute"]]))
                  {
                    if(strpos($record[$key], "||") === FALSE)
                    {
                      $reificationStatementId = count($this->dataset[$reifiedAttribute["attribute"]]) - 1;

                      if($record[$key] != "")
                      {
                        if(isset($this->dataset[$reifiedAttribute["attribute"]][$reificationStatementId]["reify"]) &&
                           is_array($this->dataset[$reifiedAttribute["attribute"]][$reificationStatementId]["reify"]))
                        {
                          array_push($this->dataset[$reifiedAttribute["attribute"]][$reificationStatementId]["reify"][
                                       $reifiedAttribute["reifiedAttribute"]], $record[$key]);
                        }
                        else
                        {
                          $this->dataset[$reifiedAttribute["attribute"]][$reificationStatementId]["reify"][
                            $reifiedAttribute["reifiedAttribute"]] = array( $record[$key] );
                        }
                      }
                    }
                    else
                    {
                      if(!isset($this->dataset[$reifiedAttribute["attribute"]]["reify"]) ||
                         !is_array($this->dataset[$reifiedAttribute["attribute"]]["reify"]))
                      {
                        $this->dataset[$reifiedAttribute["attribute"]]["reify"] = array();
                      }

                      $vs = explode("||", $record[$key]);

                      foreach($vs as $v)
                      {
                        array_push($this->dataset[$reifiedAttribute["attribute"]]["reify"], $v);
                      }
                    }
                  }
                }
                else
                {
                  if(strpos($record[$key], "||") === FALSE)
                  {
                    if($record[$key] != "")
                    {
                      if(isset($this->dataset[$rs]) && is_array($this->dataset[$rs]))
                      {
                        array_push($this->dataset[$rs], array ("value" => $record[$key], "reify" => ""));
                      }
                      else
                      {
                        $this->dataset[$rs] = array( array ("value" => $record[$key], "reify" => "") );
                      }
                    }
                  }
                  else
                  {
                    if(!isset($this->dataset[$rs]) || !is_array($this->dataset[$rs]))
                    {
                      $this->dataset[$rs] = array();
                    }

                    $vs = explode("||", $record[$key]);

                    foreach($vs as $v)
                    {
                      array_push($this->dataset[$rs], array ("value" => $v, "reify" => ""));
                    }
                  }
                }
              }              
            break;
            
            // We are parsing a record.
            case "record":
              if(count($recordStructure) > count($record))
              {
                // Pad the record with empty properties values
                for($i = 0; $i < (count($recordStructure) - count($record)); $i++)
                {
                  array_push($record, "");
                }
              }

              if(count($recordStructure) < count($record))
              {
                array_push($this->commonErrors,
                  "commON Parser (004): Too many properties defined for the record ID#".$record[0]." according to the record structure. 
                   Please make sure that you don't have empty cells in ending columns for your records, that are
                   not defined in the attribute definition line. Also check to make sure that the commas are escaped.");
                return;
              }

              foreach($recordStructure as $key => $rs)
              {
                // We simply skip empty recordStructure columns.
                if($rs == "")
                {
                  continue;
                }

                if($rs == "&id")
                {
                  if($currentRecord != $record[$key] && $record[$key] != "")
                  {
                    if($currentRecord == "")
                    {
                      // Change the reference
                      $currentRecord = $record[$key];

                      // Set th ID
                      $commonRecord[$rs] = array( array ("value" => $record[$key], "reify" => "") );
                    }
                    else
                    {
                      // Change the reference
                      $currentRecord = $record[$key];

                      // Archive the record before processing the next one
                      array_push($this->commonRecords, $commonRecord);

                      // Reinitialize the commRecord structure
                      $commonRecord = array();

                      // Set th ID
                      $commonRecord[$rs] = array( array ("value" => $record[$key], "reify" => "") );
                    }
                  }
                }
                // Check if it is a reification attribute
                elseif(($reifiedAttribute = $this->getReifiedAttribute($rs)) !== FALSE)
                {
                  if(isset($commonRecord[$reifiedAttribute["attribute"]]))
                  {
                    if(strpos($record[$key], "||") === FALSE)
                    {
                      $reificationStatementId = count($commonRecord[$reifiedAttribute["attribute"]]) - 1;

                      if($record[$key] != "")
                      {
                        if(isset($commonRecord[$reifiedAttribute["attribute"]][$reificationStatementId]["reify"]) &&
                           is_array($commonRecord[$reifiedAttribute["attribute"]][$reificationStatementId]["reify"]))
                        {
                          array_push($commonRecord[$reifiedAttribute["attribute"]][$reificationStatementId]["reify"][
                                       $reifiedAttribute["reifiedAttribute"]], $record[$key]);
                        }
                        else
                        {
                          $commonRecord[$reifiedAttribute["attribute"]][$reificationStatementId]["reify"][
                            $reifiedAttribute["reifiedAttribute"]] = array( $record[$key] );
                        }
                      }
                    }
                    else
                    {
                      if(!isset($commonRecord[$reifiedAttribute["attribute"]]["reify"]) ||
                         !is_array($commonRecord[$reifiedAttribute["attribute"]]["reify"]))
                      {
                        $commonRecord[$reifiedAttribute["attribute"]]["reify"] = array();
                      }

                      $vs = explode("||", $record[$key]);

                      foreach($vs as $v)
                      {
                        array_push($commonRecord[$reifiedAttribute["attribute"]]["reify"], $v);
                      }
                    }
                  }
                }
                else
                {
                  if(strpos($record[$key], "||") === FALSE)
                  {
                    if($record[$key] != "")
                    {
                      if(isset($commonRecord[$rs]) && is_array($commonRecord[$rs]))
                      {
                        array_push($commonRecord[$rs], array ("value" => $record[$key], "reify" => ""));
                      }
                      else
                      {
                        $commonRecord[$rs] = array( array ("value" => $record[$key], "reify" => "") );
                      }
                    }
                  }
                  else
                  {
                    if(!isset($commonRecord[$rs]) || !is_array($commonRecord[$rs]))
                    {
                      $commonRecord[$rs] = array();
                    }

                    $vs = explode("||", $record[$key]);

                    foreach($vs as $v)
                    {
                      array_push($commonRecord[$rs], array ("value" => $v, "reify" => ""));
                    }
                  }
                }
              }
            break;

            // We are parsing a linkage schema
            case "linkage":
              if(array_search("&attributeList", $recordStructure) !== FALSE)
              {
                // Description of the linkage schema.
                if(count($recordStructure) > count($record))
                {
                  // Pad the record with empty properties values
                  for($i = 0; $i < (count($recordStructure) - count($record)); $i++)
                  {
                    array_push($record, "");
                  }
                }

                if(count($recordStructure) < count($record))
                {
                  array_push($this->commonErrors,
                    "commON Parser (005): Too many properties defined for the record according to the linkage schema 
                     record structure. Please make sure that you don't have empty cells in ending columns for your 
                     records, that are not defined in the attribute definition line.");
                  return;
                }

                if(!isset($this->commonLinkageSchema["properties"]) || 
                   !is_array($this->commonLinkageSchema["properties"]))
                {
                  $this->commonLinkageSchema["properties"] = array();
                }

                $propertiesRecord = array();

                foreach($recordStructure as $key => $rs)
                {
                  if(strpos($record[$key], "||") === FALSE)
                  {
                    $propertiesRecord[$rs] = array( $record[$key] );
                  }
                  else
                  {
                    $propertiesRecord[$rs] = explode("||", $record[$key]);
                  }
                }

                array_push($this->commonLinkageSchema["properties"], $propertiesRecord);
              }
              elseif(array_search("&prefixList", $recordStructure) !== FALSE)
              {
                if(!isset($this->commonLinkageSchema["prefixes"]) || !is_array($this->commonLinkageSchema["prefixes"]))
                {
                  $this->commonLinkageSchema["prefixes"] = array();
                }

                $prefixesRecord = array();

                foreach($recordStructure as $key => $rs)
                {
                  if(strpos($record[$key], "||") === FALSE)
                  {
                    $prefixesRecord[$rs] = array( $record[$key] );
                  }
                  else
                  {
                    $prefixesRecord[$rs] = explode("||", $record[$key]);
                  }
                }

                array_push($this->commonLinkageSchema["prefixes"], $prefixesRecord);
              }
              elseif(array_search("&typeList", $recordStructure) !== FALSE)
              {
                // Description of the linkage schema.
                if(count($recordStructure) > count($record))
                {
                  // Pad the record with empty properties values
                  for($i = 0; $i < (count($recordStructure) - count($record)); $i++)
                  {
                    array_push($record, "");
                  }
                }

                if(count($recordStructure) < count($record))
                {
                  array_push($this->commonErrors,
                    "commON Parser (006): Too many properties defined for the record according to the linkage schema 
                     record structure. Please make sure that you don't have empty cells in ending columns for your 
                     records, that are not defined in the attribute definition line.");
                  return;
                }

                if(!isset($this->commonLinkageSchema["types"]) || !is_array($this->commonLinkageSchema["types"]))
                {
                  $this->commonLinkageSchema["types"] = array();
                }

                $typesRecord = array();

                foreach($recordStructure as $key => $rs)
                {
                  if(strpos($record[$key], "||") === FALSE)
                  {
                    $typesRecord[$rs] = array( $record[$key] );
                  }
                  else
                  {
                    $typesRecord[$rs] = explode("||", $record[$key]);
                  }
                }

                array_push($this->commonLinkageSchema["types"], $typesRecord);
              }
              else
              {
                // Description of the linkage schema.
                if(count($recordStructure) > count($record))
                {
                  // Pad the record with empty properties values
                  for($i = 0; $i < (count($recordStructure) - count($record)); $i++)
                  {
                    array_push($record, "");
                  }
                }

                if(count($recordStructure) < count($record))
                {
                  array_push($this->commonErrors,
                    "commON Parser (007): Too many properties defined for the record according to the linkage schema 
                     record structure. Please make sure that you don't have empty cells in ending columns for your 
                     records, that are not defined in the attribute definition line.");
                  return;
                }

                if(!isset($this->commonLinkageSchema["description"]) || 
                   !is_array($this->commonLinkageSchema["description"]))
                {
                  $this->commonLinkageSchema["description"] = array();
                }

                foreach($recordStructure as $key => $rs)
                {
                  if(strpos($rs, "||") === FALSE)
                  {
                    $this->commonLinkageSchema["description"][$rs] = array( $record[$key] );
                  }
                  else
                  {
                    $this->commonLinkageSchema["description"][$rs] = explode("||", $record[$key]);
                  }
                }

                $shouldBeRecordDescription = TRUE;
              }
            break;
          }
        }
      }
    }

    array_push($this->commonRecords, $commonRecord);

    // Fix the attributeList structure with the prefixes if any have been defined.
    if(count($this->commonLinkageSchema["prefixes"][0]["&prefixList"]) > 0)
    {
      // Fix types
      foreach($this->commonLinkageSchema["types"] as $keyType => $type)
      {
        if(substr($type["&mapTo"][0], 0, 7) != "http://")
        {
          $pos = stripos($type["&mapTo"][0], ":");

          if($pos !== FALSE)
          {
            $prefix = substr($type["&mapTo"][0], 0, $pos);

            foreach($this->commonLinkageSchema["prefixes"] as $keyPrefix => $pref)
            {
              if($pref["&prefixList"][0] == $prefix)
              {
                $this->commonLinkageSchema["types"][$keyType]["&mapTo"][0] = str_replace($pref["&prefixList"][0] . ":",
                  $this->commonLinkageSchema["prefixes"][$keyPrefix]["&mapTo"][0],
                  $this->commonLinkageSchema["types"][$keyType]["&mapTo"][0]);
              }
            }
          }
        }
      }

      // Fix attributes
      foreach($this->commonLinkageSchema["properties"] as $keyProperty => $property)
      {
        if(substr($property["&mapTo"][0], 0, 7) != "http://")
        {
          $pos = stripos($property["&mapTo"][0], ":");

          if($pos !== FALSE)
          {
            $prefix = substr($property["&mapTo"][0], 0, $pos);

            foreach($this->commonLinkageSchema["prefixes"] as $keyPrefix => $pref)
            {
              if($pref["&prefixList"][0] == $prefix)
              {
                $this->
                  commonLinkageSchema["properties"][$keyProperty]["&mapTo"][0] = str_replace($pref["&prefixList"][0]
                  . ":", $this->commonLinkageSchema["prefixes"][$keyPrefix]["&mapTo"][0],
                  $this->commonLinkageSchema["properties"][$keyProperty]["&mapTo"][0]);
              }
            }
          }
        }
      }
    }
  }

/*!   @brief Check if an attribute is a reification attribute.
            
    \n
    
    @return return FALSE if it is not a reification attribute, return the structure array( "attribute" => "...", "reifidAttribute" => "...") structure.
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
  private function getReifiedAttribute($attribute)
  {
    // Check if it is a reification attribute
    if(($pos = strpos($attribute, "&", 1)) !== FALSE)
    {
      return (array ("attribute" => substr($attribute, 0, $pos),
        "reifiedAttribute" => substr($attribute, $pos, strlen($attribute) - $pos)));
    }

    return (FALSE);
  }

  /*!   @brief Generate a RDF file serialized in N3 by using the parsed commON records and the related linkage schema.
        
      @param[in] $baseInstance  Base URI of the instance records to be converted
      @param[in] $baseOntology  Base URI of the ontology used to create new attributes and types. This is used
                          when there is nothing defined in the linkage schema for an attribute or type.
              
      \n
      
      @return return the serialized RDF file in N3
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function getRdfN3($baseInstance = "", $baseOntology = "")
  {
    // Serialized file content
    $n3 = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n\n";

    // Serialization of reification statements to be happened to the $n3 file content.
    $n3ReificationStatements = "";

    // Convert each record that have been converted
    foreach($this->commonRecords as $record)
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
          $t = $this->getLinkedType($rt["value"]);

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
      $recordId = $baseInstance . $record["&id"][0]["value"];

      // Serialize the type(s) used to define the record
      if(count($type) == 1)
      {
        $n3 .= "\n<" . $recordId . "> a <" . $type[0] . "> .\n";
      }
      else
      {
        $n3 .= "\n";

        foreach($type as $key => $t)
        {
          $n3 .= "<" . $recordId . "> a <$t> .\n";
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
              // Check if this attribute is part of the linkage schema
              $p = $this->getLinkedProperty($property);

              if($p == "")
              {
                // If the attribute to be converted is not part of the linakge schema, then we
                // simply create a "on-the-fly" attribute by using the $baseOntology URI.
                $p = $baseOntology . substr($property, 1, strlen($property) - 1);
              }

              // Check if the value is an external record reference
              if(substr($value["value"], 0, 2) == "@@")
              {
                $n3 .= "<" . $recordId . "> <" . $p . "> <" . $this->unprefixize(substr($value["value"],2)) . "> .\n";
              }
              elseif(substr($value["value"], 0, 1) == "@")
              {
                $n3 .= "<" . $recordId . "> <" . $p . "> <" . $baseInstance . substr($value["value"],1) . "> .\n";
              }
              else
              {
                // The value is a literal
                $n3 .= "<" . $recordId . "> <" . $p . "> \"\"\"" . $this->escapeN3($value["value"]) . "\"\"\" .\n";
              }                
              
              // Check if there is some statements to reify
              if(isset($value["reify"]) && is_array($value["reify"]))
              {
                foreach($value["reify"] as $reifiedAttribute => $reiValues)
                {
                  $rp = $this->getLinkedProperty($reifiedAttribute);

                  // Create serialized reification statements that will be happened to the end of the record
                  // serialized file
                  // Reification re-use RDF's reification vocabulary: http://www.w3.org/TR/REC-rdf-syntax/#reification

                  if($rp == "")
                  {
                    $reiProperty = $baseOntology . substr($reifiedAttribute, 1, strlen($reifiedAttribute) - 1);

                  
                  // @TODO: Check if "@" or "@@"
                  foreach($reiValues as $reiValue)
                  {
                    $n3ReificationStatements .= "_:" . md5($recordId . $p . $value["value"]) . " a rdf:Statement ;\n";

                    $n3ReificationStatements .= "    rdf:subject <" . $recordId . "> ;\n";
                    $n3ReificationStatements .= "    rdf:predicate <" . $p . "> ;\n";
                    $n3ReificationStatements .= "    rdf:object \"\"\"" . $this->escapeN3($value["value"])
                      . "\"\"\" ;\n";
                    $n3ReificationStatements .= "    <" . $reiProperty . "> \"\"\"" . $this->escapeN3($reiValue)
                      . "\"\"\" .\n\n";
                    }
                  }
                  else
                  {
                    // @TODO: Check if "@" or "@@"
                    foreach($reiValues as $reiValue)
                    {
                      $n3ReificationStatements .= "_:" . md5($recordId . $p . $value["value"]) . " a rdf:Statement ;\n";

                      $n3ReificationStatements .= "    rdf:subject <" . $recordId . "> ;\n";
                      $n3ReificationStatements .= "    rdf:predicate <" . $p . "> ;\n";
                      $n3ReificationStatements .= "    rdf:object \"\"\"" . $this->escapeN3($value["value"])
                        . "\"\"\" ;\n";
                      $n3ReificationStatements .= "    <" . $rp . "> \"\"\"" . $this->escapeN3($reiValue)
                        . "\"\"\" .\n\n";
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    
    return ($n3 . $n3ReificationStatements);
  }

/*!   @brief Return the URI of the property that has been linked to a commON attribute by the Linkage Schema.
      
    @param[in] $targetAttribute Target attribute, from the commON file, that we try to link to an external vocabulary/schema/ontology 
            
    \n
    
    @return return the URI of the linked property, or an empty string if such a linked property doesn't exist.
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
  public function getLinkedProperty($targetAttribute)
  {
    // Remve the processing character if it is present at the beginning of the attr
    if(substr($targetAttribute, 0, 1) == "&")
    {
      $targetAttribute = substr($targetAttribute, 1, strlen($targetAttribute) - 1);
    }

    if(isset($this->commonLinkageSchema["properties"]) && is_array($this->commonLinkageSchema["properties"]))
    {
      foreach($this->commonLinkageSchema["properties"] as $property)
      {
        if($property["&attributeList"][0] == $targetAttribute)
        {
          return ($property["&mapTo"][0]);
        }
      }
    }

    // Linked property not found, return an empty string
    return ("");
  }

/*!   @brief Return the URI of the type that has been linked to a commON type by the Linkage Schema.
      
    @param[in] $targetType Target type, from the commON file, that we try to link to an external vocabulary/schema/ontology 
            
    \n
    
    @return return the URI of the linked type, or an empty string if such a linked type doesn't exist.
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/
  public function getLinkedType($targetType)
  {
    // Remve the processing character if it is present at the beginning of the attr
    if(substr($targetType, 0, 1) == "&")
    {
      $targetType = substr($targetType, 1, strlen($targetType) - 1);
    }

    if(isset($this->commonLinkageSchema["types"]) && is_array($this->commonLinkageSchema["types"]))
    {
      foreach($this->commonLinkageSchema["types"] as $type)
      {
        if($type["&typeList"][0] == $targetType)
        {
          return ($type["&mapTo"][0]);
        }
      }
    }

    // Linked type not found, return an empty string
    return ("");
  }

  /*!   @brief Apply N3 serialization escaping rules to a literal
        
      @param[in] $literal Literal to be escaped 
              
      \n
      
      @return return the N3 escaped literal ready to be used in a N3 serialized file.
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function escapeN3($literal)
  {
    $literal = str_replace("\\", "\\\\", $literal);

    return str_replace(array ('"', "'"), array ('\\"', "\\'"), $literal);
  }
  
  private function unprefixize($val)
  {
    if(count($this->commonLinkageSchema["prefixes"][0]["&prefixList"]) > 0)
    {
      $pos = stripos($val, ":");

      if($pos !== FALSE)
      {
        $prefix = substr($val, 0, $pos);

        foreach($this->commonLinkageSchema["prefixes"] as $keyPrefix => $pref)
        {
          if($pref["&prefixList"][0] == $prefix)
          {
            return(str_replace($pref["&prefixList"][0] . ":", $this->commonLinkageSchema["prefixes"][$keyPrefix]["&mapTo"][0], $val));
          }
        }
      }
    }    
    
    return($val);
  }
}
?>