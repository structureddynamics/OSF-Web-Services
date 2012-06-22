<?php


/*! @file \StructuredDynamics\structwsf\ws\converter\tsv\TsvParser.php
    @brief Define the TSV Parser class
  
   @author Frederick Giasson, Structured Dynamics LLC.
   
   @section Copyrights
  
   Copyright 2009. American Institute of Mathematics.
  
   @section Licence
  
   This subject material is based upon work supported by the National Science Foundation to the Bibliographic Knowledge Network (http://bibkn.org) project under NSF Grant Award No. 0835851.
  
   The U.S. Federal government has a non-exclusive, nontransferable, irrevocable, royalty-free license to exercise or have exercised for or on behalf of the U.S. throughout the world all the exclusive rights provided by this copyright.
  
   Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License accompanying this distribution. Please also refer to the accompanying Notice regarding inherited attributions.
  
   Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  
   See the License for the specific language governing permissions and limitations under the License.      
 */
 
namespace StructuredDynamics\structwsf\ws\converter\tsv; 

/** Parse a TSV file

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class TsvParser
{
  public $resources = array();

  /** file content to parse */
  private $fileContent = "";

  private $delimiter = "";

  function __construct($content, $delimiter)
  {
    $this->fileContent = $content;
    $this->delimiter = $delimiter;

    // Parse the CSV file
    $this->parse();
  }

  function __destruct() { }

  private function parse()
  {
    $resourcesLines = explode("\n", $this->fileContent);

    $uri = "";

    foreach($resourcesLines as $line)
    {
      $lineValues = explode($this->delimiter, $line);

      if($lineValues[0] != "")
      {
        $uri = $lineValues[0];

        if(!isset($this->resources[$uri]))
        {
          $this->resources[$uri] = array();
        }
      }
      else
      {
        continue;
      }

      if($lineValues[1] != "" && $lineValues[2] != "")
      {
        array_push($this->resources[$uri], array ($lineValues[1], $lineValues[2]));
      }
    }
  }
}


//@}

?>