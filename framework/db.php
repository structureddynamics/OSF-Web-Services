<?php

/*! @defgroup WsFramework Framework for the Web Services */
//@{

/*! @file \ws\framework\db.php
   @brief Database connectivity layer
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
*/

/*!   @brief Database connector to the Virtuoso datastore
     @details   
            
    \n

    @author Frederick Giasson, Structured Dynamics LLC.
  
    \n\n\n
*/

class DB_Virtuoso
{
  /*! @brief Database host address */
  private $db_host;

  /*! @brief Database username */
  private $db_user;

  /*! @brief Database password */
  private $db_pass;

  /*! @brief Database DSN */
  private $dsn;

  /*! @brief Query resultset */
  private $resultset;

  /*! @brief Database connection link */
  private $db_link;

  /*! @brief Query process time in milliseconds; This is used for benchmarking purposes */
  private $queryProcessTime = 0; // In milliseconds
  
  /*! @brief Main version of the Virtuoso server used by this structWSF instance (4, 5 or 6) */
  private $virtuoso_main_version = "6";
                               
  /*!   @brief Creating a connection to the datbase system.
              
      \n
  
      @param[in] $username Database username
      @param[in] $password Database  password
      @param[in] $dsn Database DSN
      @param[in] $host Database host
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function DB_Virtuoso($username, $password, $dsn, $host)
  {
    include_once("WebService.php");
    
    $this->virtuoso_main_version = WebService::$virtuoso_main_version;    
    
    // Connection Database informations
    $this->db_host = $host;
    $this->db_user = $username;
    $this->db_pass = $password;
    $this->dsn = $dsn;

    $this->connect();
  }

  /*!   @brief Connect to the database server.
              
      \n
  
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
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

  /*!   @brief Send a query to the server
              
      \n
  
      @param[in] $db_query Query to send to the datastore (SQL or SPARQL)
      @param[in] $benchmark Enable benchmarking capabilities of the system
      
      @return returns the resltset of the query
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
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


  /*!   @brief Close a connection with the server.
              
      \n
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  public function close()
  {
    @odbc_close($this->db_link);

    $this->delete();
  }


  /*!   @brief Delete this object from the memory.
              
      \n
  
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
  */
  private function delete() { unset($this); }

  /*!   @brief Build a SPARQL query to send to the datastore. This wrap the sparql query for delevery of the query via a ODB channel
              
      \n

      @param[in] $query SPARQL query to to wrap
      @param[in] $query_variables An array that list all the variables used in the SPARLQ query to wrap (take care of the order!)
      @param[in] $sponger Enables the sponger
      
      @return returns the SPARQL query to send the to triple store
      
      @author Frederick Giasson, Structured Dynamics LLC.
    
      \n\n\n
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
    
    if($longValue != "" && odbc_field_len($resultset, $fieldID) > ini_get("odbc.defaultlrl"))
    {
      while(($chunk = odbc_result($resultset, $fieldID)) !== FALSE)
      {
        $longValue .= $chunk;
      } 
    }
    
    return($longValue);       
    
    /*
    $longValue = "";
    
    if(odbc_field_len($resultset, $fieldID) > ini_get("odbc.defaultlrl"))
    {
      while(($chunk = odbc_result($resultset, $fieldID)) !== FALSE)
      {
        $longValue .= $chunk;
      } 
    }
    else
    {
      $longValue = odbc_result($resultset, $fieldID);
    } 
    
    return($longValue);   
    */
  }  
}

//@}

?>