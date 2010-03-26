<?php

/*! @ingroup WsConverterBibtex */
//@{


/*! @file \ws\converter\bibtex\BibtexParserCsv.php
   @brief Parse a bibtex file formatted in CSV
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.
   
  @section Copyrights
  
  Copyright 2009. American Institute of Mathematics.
  
  @section Licence
  
  This subject material is based upon work supported by the National Science Foundation to the Bibliographic Knowledge Network (http://bibkn.org) project under NSF Grant Award No. 0835851.
  
  The U.S. Federal government has a non-exclusive, nontransferable, irrevocable, royalty-free license to exercise or have exercised for or on behalf of the U.S. throughout the world all the exclusive rights provided by this copyright.
  
  Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License accompanying this distribution. Please also refer to the accompanying Notice regarding inherited attributions.
  
  Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  
  See the License for the specific language governing permissions and limitations under the License.      
  
   \n\n\n
 */

include_once("BibtexItem.php");

/*!   @brief Bibtex parsing class for CSV bibtex file format
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class BibtexParserCsv
{
  public $items = array();

  /*! @brief file content to parse */
  private $fileContent = "";

  function __construct($content)
  {
    $this->fileContent = $content;

    // Parse the CSV file
    $this->parse();
  }

  function __destruct() { }

  private function parse()
  {
    $itemsLines = explode("\n", $this->fileContent);

    $uniqueID = "";
    $item;
    $currentType = "";

    foreach($itemsLines as $line)
    {
      $propertyValue = explode("\t", $line);

      $propertyValue[0] = strtolower($propertyValue[0]);

      // Transformation of some properties
      if($currentType == "person")
      {
        switch($propertyValue[0])
        {
          case "title":
            $propertyValue[0] = "name";
          break;
        }
      }

      if($currentType == "subject")
      {
        switch($propertyValue[0])
        {
          case "title":
            $propertyValue[0] = "subjectTitle";
          break;
        }
      }

      if($propertyValue[0] == "id_local")
      {
        if(isset($item) && count($item) > 0)
        {
          $this->items[$uniqueID] = $item;
        }

        $item = new BibtexItem();

        $uniqueID = $propertyValue[1];
        $item->addID($uniqueID);
      }
      else if($propertyValue[0] == "bibtype")
      {
        $propertyValue[1] = strtolower($propertyValue[1]);

        $item->addType($propertyValue[1]);
        $currentType = $propertyValue[1];
      }
      else if($propertyValue[0] == "author")
      {
        $authors = "";

        preg_match_all("|\"#(.*)\"|U", $propertyValue[1], $matches);

        foreach($matches[1] as $author)
        {
          if($authors != "")
          {
            $authors .= ",";
          }

          $authors .= $author;
        }

        $item->addProperty($propertyValue[0], $authors);
      }
      else
      {
        $process = TRUE;

        // Cleaning
        if($propertyValue[0] == "subject")
        {
          $propertyValue[0] = "subjects";
        }

        if($propertyValue[0] == "subjects")
        {
          $propertyValue[1] = str_replace(" ", ",", $propertyValue[1]);
        }

        if(($propertyValue[0] == "bibliography" || $propertyValue[0] == "honor" || $propertyValue[0] == "image"
          || $propertyValue[0] == "biography" || $propertyValue[0] == "memorial") && strlen($propertyValue[1]) > 0)
        {
          $pos = strpos($propertyValue[1], "href=\"");

          if($pos)
          {
            $pos2 = strpos($propertyValue[1], "\"", $pos + 6);
          }

          if($pos && $pos2)
          {
            $propertyValue[1] = substr($propertyValue[1], $pos + 6, $pos2 - 9);
          }
        }

        if($propertyValue[0] == "publisher")
        {
          $propertyValue[1] = strip_tags($propertyValue[1]);
        }

        if($propertyValue[0] == "sici")
        {
          $propertyValue[1] = str_replace(array ("<", ">"), array ("&lt;", "&gt;"), $propertyValue[1]);
        }

        if($propertyValue[0] == "dates")
        {
          $dates = explode("--", $propertyValue[1]);

          $item->addProperty("born_date", $dates[0]);
          $item->addProperty("death_date", $dates[1]);

          $process = FALSE;
        }

        // Adding
        if($process && $propertyValue[0] != "")
        {
          $item->addProperty($propertyValue[0], $propertyValue[1]);
        }
      }
    }
  }
}


//@}

?>