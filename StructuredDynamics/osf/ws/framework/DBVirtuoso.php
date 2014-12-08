<?php

/** @defgroup WsFramework Framework for the Web Services */
//@{

/*! @file \StructuredDynamics\osf\ws\framework\DBVirtuoso.php
    @brief Database connectivity layer
*/

namespace StructuredDynamics\osf\ws\framework; 

use \StructuredDynamics\osf\ws\framework\WebService;

/** Database connector to the Virtuoso datastore
            
    @author Frederick Giasson, Structured Dynamics LLC.
*/

class DBVirtuoso
{
  /** Database host address */
  private $db_host;

  /** Database username */
  private $db_user;

  /** Database password */
  private $db_pass;

  /** Database DSN */
  private $dsn;

  /** Query resultset */
  private $resultset;

  /** Database connection link */
  private $db_link;

  /** Query process time in milliseconds; This is used for benchmarking purposes */
  private $queryProcessTime = 0; // In milliseconds
  
  /** Main version of the Virtuoso server used by this OSF instance (4, 5 or 6) */
  private $virtuoso_main_version = "7";
  
  /** Enable the Long Read Len feature of Virtuoso. */
  private $enable_lrl = FALSE;
                               
  /** Creating a connection to the datbase system.
  
      @param $username Database username
      @param $password Database  password
      @param $dsn Database DSN
      @param $host Database host
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function __construct($username, $password, $dsn, $host)
  {
    $osf_ini = parse_ini_file(WebService::$osf_ini . "osf.ini", TRUE);

    $this->virtuoso_main_version = $osf_ini["triplestore"]["virtuoso_main_version"];    
    $this->enable_lrl = $osf_ini["triplestore"]["enable_lrl"];
    
    // Connection Database informations
    $this->db_host = $host;
    $this->db_user = $username;
    $this->db_pass = $password;
    $this->dsn = $dsn;

    $this->connect();
  }

  /** Connect to the database server.
  
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function connect()
  {    
    if($this->virtuoso_main_version >= 6)
    {
      ini_set("odbc.default_cursortype", "0");
      $this->db_link = odbc_connect($this->dsn, $this->db_user, $this->db_pass);
    }

    if($this->virtuoso_main_version <= 5)
    {
      $this->db_link = odbc_connect($this->dsn, $this->db_user, $this->db_pass, SQL_CUR_USE_ODBC);
    }

    return;
  }

  public function getError() { return (odbc_error($this->db_link)); }

  public function getErrorMsg() { return (odbc_errormsg($this->db_link)); }

  /** Send a query to the server
  
      @param $db_query Query to send to the datastore (SQL or SPARQL)
      @param $benchmark Enable benchmarking capabilities of the system
      
      @return returns the resltset of the query
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function query($db_query, $benchmark = FALSE)
  {
    if(isset($_GET['benchmark']) && $_GET['benchmark'] == 1)
    {
      //  Start TIMER
      //  -----------
      $stimer = explode(' ', microtime());
      $stimer = $stimer[1] + $stimer[0];
      //  -----------

      $resultset = odbc_exec($this->db_link, $db_query);

      //  End TIMER
      //  ---------
      $etimer = explode(' ', microtime());
      $etimer = $etimer[1] + $etimer[0];
      //  ---------

      $this->queryProcessTime = (($etimer - $stimer) * 1000);

      echo "<div>";

      echo "Sparql query: <em>" . str_replace(array ("<", ">"), array ("&lt;", "&gt;"), $db_query) . "</em><br>";

      echo "Execution time: " . $this->queryProcessTime . " milliseconds<br><br>\n";

      echo "</div>";
    }

    if((isset($_GET['debug']) && $_GET['debug'] == 2) || $benchmark === TRUE)
    {
      //  Start TIMER
      //  -----------
      $stimer = explode(' ', microtime());
      $stimer = $stimer[1] + $stimer[0];
      //  -----------

      $resultset = odbc_exec($this->db_link, $db_query);

      //  End TIMER
      //  ---------
      $etimer = explode(' ', microtime());
      $etimer = $etimer[1] + $etimer[0];
      //  ---------

      $this->queryProcessTime = (($etimer - $stimer) * 1000);

      if(isset($_GET['debug']) && $_GET['debug'] == 2)
      {
        echo '<p style="margin:auto; text-align:center">';
        printf("Query <em>" . str_replace(array ("<", ">"), array ("&lt;", "&gt;"), $db_query)
          . "</em> loaded in <b>%f</b> milliseconds.", $this->queryProcessTime);

        echo '</p>';
      }

      return ($resultset);
    }

    return (@odbc_exec($this->db_link, $db_query));
  }


  /** Close a connection with the server.
              
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function close()
  {
    @odbc_close($this->db_link);

    $this->delete();
  }


  /** Delete this object from the memory.
              
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  private function delete() { unset($this); }

  /** Build a SPARQL query to send to the datastore. This wrap the sparql query for delevery of the query via a ODB channel
              
      @param $query SPARQL query to to wrap
      @param $query_variables An array that list all the variables used in the SPARLQ query to wrap (take care of the order!)
      @param $sponger Enables the sponger
      
      @return returns the SPARQL query to send the to triple store
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function build_sparql_query($query, $query_variables, $sponger)
  {
    $sparql_query = "sparql ";
      
    if($sponger == TRUE)
    {
      $sparql_query .= "define get:soft \"replacing\" ";
    }

    $sparql_query .= $query;

    return $sparql_query;
  }
  
  public function odbc_getPossibleLongResult(&$resultset, $fieldID)
  {
    $longValue = "";
    
    $longValue = odbc_result($resultset, $fieldID);
    
    if($this->enable_lrl && 
       $longValue != "" && 
       odbc_field_len($resultset, $fieldID) > ini_get("odbc.defaultlrl"))
    {
      while(($chunk = odbc_result($resultset, $fieldID)) !== FALSE)
      {
        $longValue .= $chunk;
      } 
    }
    
    return($longValue);       
  }  
}

//@}

?>