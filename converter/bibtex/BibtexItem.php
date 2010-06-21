<?php

/*! @ingroup WsConverterBibtex */
//@{

/*! @file \ws\converter\bibtex\BibtexItem.php
   @brief Define a bibtex item
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */


/*!   @brief Bibtex item description
            
    \n
    
    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class BibtexItem
{
  public $itemType = ""; // The Bibtex entry type.
  public $itemID = "";   // The Bibtex entry ID.

/*  
  public $address = ""; // Publisher's address (usually just the city, but can be the full address for lesser-known publishers)
    public $annote = ""; // An annotation for annotated bibliography styles (not typical)
    public $author = ""; // The name(s) of the author(s) (in the case of more than one author, separated by and)
    public $booktitle = ""; // The title of the book, if only part of it is being cited
    public $chapter = ""; // The chapter number
    public $crossref = ""; // The key of the cross-referenced entry
    public $edition = ""; // The edition of a book, long form (such as "first" or "second")
    public $editor = ""; // The name(s) of the editor(s)
    public $eprint = ""; // A specification of an electronic publication, often a preprint or a technical report
    public $howpublished = ""; // How it was published, if the publishing method is nonstandard
    public $institution = ""; // The institution that was involved in the publishing, but not necessarily the publisher
    public $journal = ""; // The journal or magazine the work was published in
    public $key = ""; // A hidden field used for specifying or overriding the alphabetical order of entries (when the "author" and "editor" fields are missing). Note that this is very different from the key (mentioned just after this list) that is used to cite or cross-reference the entry.
    public $month = ""; // The month of publication (or, if unpublished, the month of creation)
    public $note = ""; // Miscellaneous extra information
    public $number = ""; // The "number" of a journal, magazine, or tech-report, if applicable. (Most publications have a "volume", but no "number" field.)
    public $organization = ""; // The conference sponsor
    public $pages = ""; // Page numbers, separated either by commas or double-hyphens. For books, the total number of pages.
    public $publisher = ""; // The publisher's name
    public $school = ""; // The school where the thesis was written
    public $series = ""; // The series of books the book was published in (e.g. "The Hardy Boys" or "Lecture Notes in Computer Science")
    public $title = ""; // The title of the work
    public $type = ""; // The type of tech-report, for example, "Research Note"
    public $url = ""; // The WWW address
    public $volume = ""; // The volume of a journal or multi-volume book
    public $year = ""; // The year of publication (or, if unpublished, the year of creation)
*/
  public $properties = array();

  function __construct() { }

  function __destruct() { }

  public function addType($type) { $this->itemType = $type; }

  public function addID($id) { $this->itemID = $id; }

  public function addProperty($property, $value) { $this->properties[$property] = $value;

  /*  
      switch($property)
      {
        case "address":
          $this->address = $value;
        break;
        
        case "annote":
          $this->annote = $value;
        break;
        
        case "author":
          $this->author = $value;
        break;
        
        case "booktitle":
          $this->booktitle = $value;
        break;
        
        case "chapter":
          $this->chapter = $value;
        break;
        
        case "crossref":
          $this->crossref = $value;
        break;
        
        case "edition":
          $this->edition = $value;
        break;
        
        case "editor":
          $this->editor = $value;
        break;
        
        case "eprint":
          $this->eprint = $value;
        break;
        
        case "howpublished":
          $this->howpublished = $value;
        break;
        
        case "institution":
          $this->institution = $value;
        break;
        
        case "journal":
          $this->journal = $value;
        break;
        
        case "key":
          $this->key = $value;
        break;
        
        case "month":
          $this->month = $value;
        break;
        
        case "note":
          $this->note = $value;
        break;
  
        case "number":
          $this->number = $value;
        break;
        
        case "organization":
          $this->organization = $value;
        break;
        
        case "pages":
          $this->pages = $value;
        break;
        
        case "publisher":
          $this->publisher = $value;
        break;
        
        case "school":
          $this->school = $value;
        break;
        
        case "series":
          $this->series = $value;
        break;
        
        case "title":
          $this->title = $value;
        break;
      
        case "type":
          $this->type = $value;
        break;
        
        case "url":
          $this->url = $value;
        break;
        
        case "volume":
          $this->volume = $value;
        break;
        
        case "year":
          $this->year = $value;
        break;
        
        default:
        break;
      }
  */
  }
}

//@}

?>