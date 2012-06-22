<?php

/*! @ingroup WsConverterBibtex */
//@{


/*! @file \StructuredDynamics\structwsf\ws\converter\bibtex\BibtexParser.php
    @brief Parse a bibtex item
 */

namespace StructuredDynamics\structwsf\ws\converter\bibtex; 
 
use \StructuredDynamics\structwsf\ws\converter\bibtex\BibtexItem; 


/** Bibtex parsing class

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class BibtexParser
{
  public $items = array();
  private $fileContent = "";
  private $cursor = 0; // The parser's file cursor

  function __construct($content)
  {
    $this->fileContent = $content;

    // Lets normalize the content of the file
    $this->fileContent = str_replace(array ("\t", "\r", "\n"), "", $this->fileContent);

    // Remove additional spaces.
    $this->fileContent = preg_replace("#\s\s+#", " ", $this->fileContent);

    // Fix potential bibtex format issues
    $this->fixFormatIssues();

    $this->parse();
  }

  function __destruct() { }

  private function parse()
  {
    // Iterates for all bibtex items.

    while($this->nextItem() !== FALSE)
    {
      // Create a new bib item
      $item = new BibtexItem();

      $item->addType($this->getItemType());
      $item->addID($this->getItemID());

      $property;

      while(($property = $this->getItemProperty()) !== FALSE)
      {
        $item->addProperty($property[0], $property[1]);
      }

      array_push($this->items, $item);
    }
  }

  // Move the cursor to the next bib item
  private function nextItem()
  {
    $this->cursor = strpos($this->fileContent, "@", $this->cursor);

    if($this->cursor !== FALSE)
    {
      $this->cursor++;
      return $this->cursor;
    }
    else
    {
      return FALSE;
    }
  }

  // Get the type of the item at cursor's position
  private function getItemType()
  {
    $end = strpos($this->fileContent, "{", $this->cursor);

    $type = strtolower(substr($this->fileContent, $this->cursor, ($end - $this->cursor)));

    // Move the cursor
    $this->cursor = $end + 1;

    // Lets remove all spaces and tabs
    return (str_replace(" ", "", $type));
  }

  // Get the ID of the item at cursor's position
  private function getItemID()
  {
    $end = strpos($this->fileContent, ",", $this->cursor);

    $id = substr($this->fileContent, $this->cursor, ($end - $this->cursor));

    // Move the cursor
    $this->cursor = $end + 1;

    // Lets remove all spaces and tabs
    return ($id);
  }

  // Get the next Property of the item at cursor's position
  private function getItemProperty()
  {
    // First, check if we reached the end of the bib item.
    if($this->fileContent[$this->cursor] == "}")
    {
      // Move the cursor
      $this->cursor += 1;
      return (FALSE);
    }

    // Then lets check if we are facing an integer value:
    $pattern = '/(.*)?[\s]*=(.*),/U';

    if(preg_match($pattern, $this->fileContent, $matches, NULL, $this->cursor))
    {
      if(strpos($matches[0], '"') === FALSE && strpos($matches[0], '{') === FALSE)
      {
        // Move the cursor
        $this->cursor += strlen($matches[0]);

        return (array (strtolower(str_replace(" ", "", $matches[1])), str_replace(" ", "", $matches[2])));
      }
      else
      {
        // End patterns:
        // (1) "}    =>   ["]{1}[\s]*[\}]{1}
        // (2) },}    =>   [\}]{1}[\s]*[,]{1}[\s]*[\}]{1}
        // (3) }}    =>   [\}]{1}[\s]*[\s]*[\}]{1}

        // (["]{1}[\s]*[\}]{1}|[\}]{1}[\s]*[,]{1}[\s]*[\}]{1}){1}

        // Next items patterns:
        // (1) ",      =>   ["]{1}[\s]*[,]{1}
        // (2) },    =>   [\}]{1}[\s]*[,]{1}
        // (3) "}    =>   ["]{1}[\s]*[\}]{1}

        // (["]{1}[\s]*[,]{1}|[\}]{1}[\s]*[,]{1}|["]{1}[\s]*[\}]{1}){1}

        // Then  extract the property->value for that bib item
        $pattern = '/(.*)[\s]*=[\s]*["\{]{1}(.*)(["]{1}[\s]*[,]{1}|[\}]{1}[\s]*[,]{1}|["]{1}[\s]*[\}]{1}){1}/U';

        if(preg_match($pattern, $this->fileContent, $matches, NULL, $this->cursor))
        {
          // Move the cursor
          $this->cursor += strlen($matches[0]);

          return (array (strtolower(str_replace(" ", "", $matches[1])), $matches[2]));
        }
        else
        {
          return (FALSE);
        }
      }
    }
  }

  private function fixFormatIssues()
  {
    // Let fix the ending of a bibtex item from "} }" to "}, }"
    $pattern = '/(((\}[\s]*\}[\s]*)@)|(\}[\s]*\}[\s]*$))/U';

    if(preg_match_all($pattern, $this->fileContent, $matches))
    {
      $replaces;

      foreach($matches[0] as $match)
      {
        $replaces[$match] = $match;
      }

      foreach($replaces as $replace)
      {
        $replaceWith = str_replace(" ", "", $replace);
        $replaceWith = str_replace("}}", "},}", $replaceWith);

        $this->fileContent = str_replace($replace, $replaceWith, $this->fileContent);
      }
    }
  }
}


//@}

?>