<?php

/*! @ingroup UserAuth Authentication / Registration Web Service */
//@{

/*! @file \StructuredDynamics\osf\ws\auth\registrar\user\AuthRegistrarUser.php
    @brief Define the Authentication / Registration User web service
 */

namespace StructuredDynamics\osf\ws\auth\registrar\user;   

use \StructuredDynamics\osf\ws\framework\CrudUsage;
use \StructuredDynamics\osf\ws\framework\Conneg;

/** AuthRegister User Web Service. It registers a User on the OSF instance

    @author Frederick Giasson, Structured Dynamics LLC.
*/

class AuthRegistrarUser extends \StructuredDynamics\osf\ws\framework\WebService
{
  /** URL where the DTD of the XML document can be located on the Web */
  private $dtdURL;

  /** Supported serialization mime types by this Web service */
  public static $supportedSerializations =
    array ("application/json", "application/rdf+xml", "application/rdf+n3", "application/*", "text/xml", "text/*",
      "*/*");

  /** URI of the user to create */
  private $user_uri = "";

  /** URI of the group where to register the user */
  private $group_uri = "";

  /** Error messages of this web service */
  private $errorMessenger =
    '{
                        "ws": "/ws/auth/registrar/ws/",
                        "_200": {
                          "id": "WS-AUTH-REGISTRAR-USER-200",
                          "level": "Warning",
                          "name": "No User URI",
                          "description": "No user URI defined for this query."
                        },
                        "_201": {
                          "id": "WS-AUTH-REGISTRAR-USER-201",
                          "level": "Warning",
                          "name": "No Group URI",
                          "description": "No Group URI defined for this query."
                        },
                        "_202": {
                          "id": "WS-AUTH-REGISTRAR-USER-202",
                          "level": "Fatal",
                          "name": "Can\'t check if the user was already registered to this WSF",
                          "description": "An error occured when we tried to check if the user was already registered to this web service network."
                        },                        
                        "_203": {
                          "id": "WS-AUTH-REGISTRAR-USER-203",
                          "level": "Fatal",
                          "name": "User already registered",
                          "description": "The user is already registered to this group."
                        },                        
                        "_204": {
                          "id": "WS-AUTH-REGISTRAR-USER-204",
                          "level": "Fatal",
                          "name": "Unexisting group",
                          "description": "The group where you are trying to register the user is unexisting."
                        },         
                        "_205": {
                          "id": "WS-AUTH-REGISTRAR-USER-205",
                          "level": "Fatal",
                          "name": "Unexisting action",
                          "description": "An unexisting action as been specified. The action parameter can be one of: (1) join, or (2) leave"
                        }, 
                        "_300": {
                          "id": "WS-AUTH-REGISTRAR-USER-300",
                          "level": "Fatal",
                          "name": "Couldn\'t register user",
                          "description": "An internal error occured when we tried to register this user to the web service network."
                        },
                        "_301": {
                          "id": "WS-AUTH-REGISTRAR-USER-301",
                          "level": "Fatal",
                          "name": "Requested source interface not existing",
                          "description": "The source interface you requested is not existing for this web service endpoint."
                        },
                        "_302": {
                          "id": "WS-AUTH-REGISTRAR-USER-302",
                          "level": "Fatal",
                          "name": "Requested incompatible Source Interface version",
                          "description": "The version of the source interface you requested is not compatible with the version of the source interface currently hosted on the system. Please make sure that your tool get upgraded for using this current version of the endpoint."
                        },
                        "_303": {
                          "id": "WS-AUTH-REGISTRAR-USER-303",
                          "level": "Fatal",
                          "name": "Source Interface\'s version not compatible with the web service endpoint\'s",
                          "description": "The version of the source interface you requested is not compatible with the one of the web service endpoint. Please contact the system administrator such that he updates the source interface to make it compatible with the new endpoint version."
                        },
                        "_304": {
                          "id": "WS-AUTH-REGISTRAR-USER-304",
                          "level": "Fatal",
                          "name": "Couldn\'t leave group",
                          "description": "An internal error occured when we tried to leave the user from the group to the web service network."
                        },
                        "_305": {
                          "id": "WS-AUTH-REGISTRAR-USER-305",
                          "level": "Warning",
                          "name": "Invalid user URI",
                          "description": "The URI of the user is not valid."
                        },
                        "_306": {
                          "id": "WS-AUTH-REGISTRAR-USER-306",
                          "level": "Warning",
                          "name": "Invalid group URI",
                          "description": "The URI of the group is not valid."
                        }                        
                      }';

                      
  /**
  * Implementation of the __get() magic method. We do implement it to create getter functions
  * for all the protected and private variables of this class, and to all protected variables
  * of the parent class.
  * 
  * This implementation is needed by the interfaces layer since we want the SourceInterface
  * class to access the variables of the web service class for which it is used as a 
  * source interface.
  * 
  * This means that all the privated and propected variables of these web service objects
  * are available to users; but they won't be able to set values for them.
  * 
  * Also note that This method is about 4 times slower than having the varaible as public instead 
  * of protected and private. However, these variables are only accessed about 10 to 200 times 
  * per script call. This means that for accessing these undefined variable using the __get magic 
  * method call, then it adds about 0.00022 seconds to the call or, about 0.22 milli-second 
  * (one fifth of a millisecond) For the gain of keeping the variables protected and private, 
  * we can spend this one fifth of a milli-second. This is a good compromize.  
  * 
  * @param mixed $name Name of the variable that is currently not defined for this object
  */
  public function __get($name)
  {
    // Check if the variable exists (so, if it is private or protected). If it is, then
    // we return the value. Otherwise a fatal error will be returned by PHP.
    if(isset($this->{$name}))
    {
      return($this->{$name});
    }
  }                      

  /** Constructor

      @param $user_uri URI of the user to create
      @param $group_uri URI of the group where to register the user
      @param $action Action to perform with this endpoint query. Can be one of:
                       (1) "join": join a user to an existing group
                       (2) "leave": leave a user from an existing group
      @param $interface Name of the source interface to use for this web service query. Default value: 'default'                            
      @param $requestedInterfaceVersion Version used for the requested source interface. The default is the latest 
                                        version of the interface.
      
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  function __construct($user_uri, $group_uri, $action, $interface='default', $requestedInterfaceVersion="")
  {
    parent::__construct();
    
    $this->version = "3.0";

    $this->user_uri = $user_uri;
    $this->group_uri = $group_uri;
    $this->action = $action;
    
    if(strtolower($interface) == "default")
    {
      $this->interface = $this->default_interfaces["auth_registrar_ws"];
    }
    else
    {
      $this->interface = $interface;
    }
        
    $this->requestedInterfaceVersion = $requestedInterfaceVersion;     
    
    $this->uri = $this->wsf_base_url . "/wsf/ws/auth/registrar/user/";
    $this->title = "Authentication User Registration Web Service";
    $this->crud_usage = new CrudUsage(TRUE, TRUE, FALSE, TRUE);
    $this->endpoint = $this->wsf_base_url . "/ws/auth/registrar/user/";

    $this->errorMessenger = json_decode($this->errorMessenger);
  }

  function __destruct()
  {
    parent::__destruct();

    // If we are in pipeline mode, then we *don't* close the ODBC connection.
    // If we are *not* then we have to close the connection.
    if(isset($this->db) && !$this->isInPipelineMode)
    {
      @$this->db->close();
    }
  }

  /** Validate a query to this web service

      @return TRUE if valid; FALSE otherwise
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function validateQuery()
  { 
    if($this->validateUserAccess($this->wsf_graph))
    {
      if($this->action !== 'join' && $this->action !== 'leave')
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_204->name);
        $this->conneg->setError($this->errorMessenger->_204->id, $this->errorMessenger->ws,
          $this->errorMessenger->_204->name, $this->errorMessenger->_204->description, "",
          $this->errorMessenger->_204->level);
        return;
      }      
      
      if($this->user_uri == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_200->name);
        $this->conneg->setError($this->errorMessenger->_200->id, $this->errorMessenger->ws,
          $this->errorMessenger->_200->name, $this->errorMessenger->_200->description, "",
          $this->errorMessenger->_200->level);
        return;
      }      
      
      
      if(!empty($this->user_uri) && !$this->isValidIRI($this->user_uri))
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_305->name);
        $this->conneg->setError($this->errorMessenger->_305->id, $this->errorMessenger->ws,
          $this->errorMessenger->_305->name, $this->errorMessenger->_305->description, "",
          $this->errorMessenger->_305->level);

        return;
      }       
      
      if($this->group_uri == "")
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_201->name);
        $this->conneg->setError($this->errorMessenger->_201->id, $this->errorMessenger->ws,
          $this->errorMessenger->_201->name, $this->errorMessenger->_201->description, "",
          $this->errorMessenger->_201->level);
        return;
      } 
            
      if(!empty($this->group_uri) && !$this->isValidIRI($this->group_uri))
      {
        $this->conneg->setStatus(400);
        $this->conneg->setStatusMsg("Bad Request");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_306->name);
        $this->conneg->setError($this->errorMessenger->_306->id, $this->errorMessenger->ws,
          $this->errorMessenger->_306->name, $this->errorMessenger->_306->description, "",
          $this->errorMessenger->_306->level);

        return;
      }       

      // Check if the group exists
      $resultset = $this->db->query($this->db->build_sparql_query("
        select ?type
        from <" . $this->wsf_graph. "> 
        where 
        {
          <". $this->group_uri ."> a ?type . 
        }",
        array ('type'), FALSE));

      if(odbc_error())
      {
        $this->conneg->setStatus(500);
        $this->conneg->setStatusMsg("Internal Error");
        $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
        $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
          $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
          $this->errorMessenger->_202->level);

        return;
      }
      else
      {
        odbc_fetch_row($resultset);
        
        $type = odbc_result($resultset, 1);

        if($type === FALSE)
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_204->name);
          $this->conneg->setError($this->errorMessenger->_204->id, $this->errorMessenger->ws,
            $this->errorMessenger->_204->name, $this->errorMessenger->_204->description, "",
            $this->errorMessenger->_204->level);

          unset($resultset);
          return;
        }
      }

      unset($resultset);      
      
      // Check if the user is already registered to that group
      if($this->action == 'join')
      {
        $resultset = $this->db->query($this->db->build_sparql_query("
          select ?type
          from <" . $this->wsf_graph. "> 
          where 
          {
            <". $this->user_uri ."> a ?type ;
                                    <http://purl.org/ontology/wsf#hasGroup>  <". $this->group_uri ."> .
                                     
          }",
          array ('type'), FALSE));

        if(odbc_error())
        {
          $this->conneg->setStatus(500);
          $this->conneg->setStatusMsg("Internal Error");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_202->name);
          $this->conneg->setError($this->errorMessenger->_202->id, $this->errorMessenger->ws,
            $this->errorMessenger->_202->name, $this->errorMessenger->_202->description, "",
            $this->errorMessenger->_202->level);

          return;
        }
        elseif(odbc_fetch_row($resultset))
        {
          $type = odbc_result($resultset, 1);

          if($type == 'http://purl.org/ontology/wsf#User')
          {
            $this->conneg->setStatus(400);
            $this->conneg->setStatusMsg("Bad Request");
            $this->conneg->setStatusMsgExt($this->errorMessenger->_203->name);
            $this->conneg->setError($this->errorMessenger->_203->id, $this->errorMessenger->ws,
              $this->errorMessenger->_203->name, $this->errorMessenger->_203->description, "",
              $this->errorMessenger->_203->level);

            unset($resultset);
            return;
          }
        }
      }

      unset($resultset);           
    }               
  }

  /** Returns the error structure

      @return returns the error structure
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getError() { return ($this->conneg->error); }


  /**  @brief Create a resultset in a pipelined mode based on the processed information by the Web service.

      @return a resultset XML document
      
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResultset() { return ""; }

  /** Inject the DOCType in a XML document

      @param $xmlDoc The XML document where to inject the doctype
      
      @return a XML document with a doctype
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function injectDoctype($xmlDoc){ }
  
  /** Do content negotiation as an external Web Service

      @param $accept Accepted mime types (HTTP header)
      
      @param $accept_charset Accepted charsets (HTTP header)
      
      @param $accept_encoding Accepted encodings (HTTP header)
  
      @param $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
  {
    $this->conneg = new Conneg($accept, $accept_charset, $accept_encoding, $accept_language,
      AuthRegistrarUser::$supportedSerializations);

    // Validate call
    $this->validateCall();  
      
    // Validate query
    if($this->conneg->getStatus() == 200)
    {
      $this->validateQuery();
    }      
  }

  /** Do content negotiation as an internal, pipelined, Web Service that is part of a Compound Web Service

      @param $accept Accepted mime types (HTTP header)
      
      @param $accept_charset Accepted charsets (HTTP header)
      
      @param $accept_encoding Accepted encodings (HTTP header)
  
      @param $accept_language Accepted languages (HTTP header)
    
      @return returns NULL
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_conneg($accept, $accept_charset, $accept_encoding, $accept_language)
  {     
    $this->ws_conneg($accept, $accept_charset, $accept_encoding, $accept_language); 
    
    $this->isInPipelineMode = TRUE;
  }

  /** Returns the response HTTP header status

      @return returns the response HTTP header status
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResponseHeaderStatus() { return $this->conneg->getStatus(); }

  /** Returns the response HTTP header status message

      @return returns the response HTTP header status message
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResponseHeaderStatusMsg() { return $this->conneg->getStatusMsg(); }

  /** Returns the response HTTP header status message extension

      @return returns the response HTTP header status message extension
    
      @note The extension of a HTTP status message is
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function pipeline_getResponseHeaderStatusMsgExt() { return $this->conneg->getStatusMsgExt(); }

  /** Serialize the web service answer.

      @return returns the serialized content
    
      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function ws_serialize() { return ""; }
   
  /** Register a new Web Service endpoint to the OSF instance

      @author Frederick Giasson, Structured Dynamics LLC.
  */
  public function process()
  {
    // Check if the interface called by the user is existing
    $class = $this->sourceinterface_exists(rtrim($this->wsf_base_path, "/")."/auth/registrar/user/interfaces/");
    
    if($class != "")
    {
      $class = 'StructuredDynamics\osf\ws\auth\registrar\user\interfaces\\'.$class;
      
      $interface = new $class($this);
      
   // Validate versions
      if($this->requestedInterfaceVersion == "")
      {
        // The default requested version is the last version of the interface
        $this->requestedInterfaceVersion = $interface->getVersion();
      }
      else
      {
        if(!$interface->validateWebServiceCompatibility())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_303->name);
          $this->conneg->setError($this->errorMessenger->_303->id, $this->errorMessenger->ws,
            $this->errorMessenger->_303->name, $this->errorMessenger->_303->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_303->level);
            
          return;        
        }
        
        if(!$interface->validateInterfaceVersion())
        {
          $this->conneg->setStatus(400);
          $this->conneg->setStatusMsg("Bad Request");
          $this->conneg->setStatusMsgExt($this->errorMessenger->_302->name);
          $this->conneg->setError($this->errorMessenger->_302->id, $this->errorMessenger->ws,
            $this->errorMessenger->_302->name, $this->errorMessenger->_302->description, 
            "Requested Source Interface: ".$this->interface,
            $this->errorMessenger->_302->level);  
            
            return;
        }
      }     
      
      // Process the code defined in the source interface
      $interface->processInterface();
    }
    else
    { 
      // Interface not existing
      $this->conneg->setStatus(400);
      $this->conneg->setStatusMsg("Bad Request");
      $this->conneg->setStatusMsgExt($this->errorMessenger->_301->name);
      $this->conneg->setError($this->errorMessenger->_301->id, $this->errorMessenger->ws,
        $this->errorMessenger->_301->name, $this->errorMessenger->_301->description, 
        "Requested Source Interface: ".$this->interface,
        $this->errorMessenger->_301->level);
    }
  }
}

//@}

?>