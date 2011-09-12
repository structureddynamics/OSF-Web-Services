<?php

/*! @ingroup WsAuth Authentication / Registration Web Service */
//@{

/*! @file \ws\auth\wsf_indexer.php
   @brief Temporary WSF description indexation script
  
   \n\n
 
   @author Frederick Giasson, Structured Dynamics LLC.

   \n\n\n
 */

ini_set("display_errors", "On");
error_reporting(E_ALL);

 
$action = "";

if(isset($_GET['action']))
{
  $action = $_GET['action'];
}

// Address of the server of the WSF
$server_address = "";

if(isset($_GET['server_address']))
{
  $server_address = $_GET['server_address'];
}

// Address of the user that has full access (IP)

$user_address = "";

if(isset($_GET['user_address']))
{
  $user_address = $_GET['user_address'];
}

error_reporting(E_ALL);
ini_set("memory_limit", "164M");
set_time_limit(86400);

// Database connectivity procedures
include_once("../framework/db.php");

$rdf = "";

include_once("../framework/WebService.php");

$data_ini = parse_ini_file(WebService::$data_ini . "data.ini", TRUE);
$network_ini = parse_ini_file(WebService::$network_ini . "network.ini", TRUE);

$username = $data_ini["triplestore"]["username"];
$password = $data_ini["triplestore"]["password"];
$dsn = $data_ini["triplestore"]["dsn"];
$host = $data_ini["triplestore"]["host"];
$wsf_local_ip = $network_ini["network"]["wsf_local_ip"];

switch($action)
{
  case "create_wsf":
    $rdf =
      "@prefix wsf: <http://purl.org/ontology/wsf#> .
            @prefix void: <http://rdfs.org/ns/void#> .
            @prefix dcterms: <http://purl.org/dc/terms/> .
            @prefix foaf: <http://xmlns.com/foaf/0.1/> .
            @prefix owl: <http://www.w3.org/2002/07/owl#> .
            @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
            @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            
            <$server_address/wsf/> rdf:type wsf:WebServiceFramework ;
              wsf:hasAuthenticationWebService <$server_address/wsf/ws/auth/validator/> ;
              wsf:hasWebService <$server_address/wsf/ws/auth/lister/> ;
              wsf:hasWebService <$server_address/wsf/ws/sparql/> ;
              wsf:hasWebService <$server_address/wsf/ws/converter/bibtex/> ;
              wsf:hasWebService <$server_address/wsf/ws/converter/tsv/> ;
              wsf:hasWebService <$server_address/wsf/ws/converter/irjson/> ;
              wsf:hasWebService <$server_address/wsf/ws/search/> ;
              wsf:hasWebService <$server_address/wsf/ws/browse/> ;
              wsf:hasWebService <$server_address/wsf/ws/auth/registrar/ws/> ;
              wsf:hasWebService <$server_address/wsf/ws/auth/registrar/access/> ;
              wsf:hasWebService <$server_address/wsf/ws/dataset/create/> ;
              wsf:hasWebService <$server_address/wsf/ws/dataset/read/> ;
              wsf:hasWebService <$server_address/wsf/ws/dataset/update/> ;
              wsf:hasWebService <$server_address/wsf/ws/dataset/delete/> ;
              wsf:hasWebService <$server_address/wsf/ws/crud/create/> ;
              wsf:hasWebService <$server_address/wsf/ws/crud/read/> ;
              wsf:hasWebService <$server_address/wsf/ws/crud/update/> ;
              wsf:hasWebService <$server_address/wsf/ws/crud/delete/> ;
              wsf:hasWebService <$server_address/wsf/ws/ontology/create/> ;
              wsf:hasWebService <$server_address/wsf/ws/ontology/delete/> ;
              wsf:hasWebService <$server_address/wsf/ws/ontology/read/> ;
              wsf:hasWebService <$server_address/wsf/ws/ontology/update/>.
              
              
            <$server_address/wsf/access/5b2b633495a58612b63724ef71729ea6> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the authentication registrar web service to register new web services to the WSF\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/auth/lister/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/auth/registrar/ws/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/auth/registrar/access/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/create/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/read/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/delete/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/update/> ;
              wsf:datasetAccess <$server_address/wsf/> .
            
            <$server_address/wsf/access/459f32962858ffa9677a27c4612cb875> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the admin of the WSF to generate and manage ontologies of the WSF\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/create/> ;
              wsf:datasetAccess <$server_address/wsf/ontologies/> .  
              
            <$server_address/wsf/access/459f32962858ffa9677a27c4612cb876> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the admin of the WSF to generate and manage ontologies of the WSF\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/delete/> ;
              wsf:datasetAccess <$server_address/wsf/ontologies/> .  
              
            <$server_address/wsf/access/459f32962858ffa9677a27c4612cb877> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the admin of the WSF to generate and manage ontologies of the WSF\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/read/> ;
              wsf:datasetAccess <$server_address/wsf/ontologies/> .  
              
            <$server_address/wsf/access/459f32962858ffa9677a27c4612cb878> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the admin of the WSF to generate and manage ontologies of the WSF\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/update/> ;
              wsf:datasetAccess <$server_address/wsf/ontologies/> .  
              
              
              
            <$server_address/wsf/access/44b0867f6cd9170bead8d774fad4685b> rdf:type wsf:Access ;
              dcterms:description \"\"\"Access to be able to create new datasets\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/create/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/read/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/delete/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/update/> ;
              wsf:datasetAccess <$server_address/wsf/datasets/> .
            
            <$server_address/wsf/ws/auth/validator/> rdf:type wsf:AuthenticationWebService ;
              dcterms:title \"Authentication Validator web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/auth/validator/\"\"\";
              wsf:hasAccess <$server_address/wsf/access/44b0867f6cd9170bead8d774fad4685b> ;
              wsf:hasAccess <$server_address/wsf/access/5b2b633495a58612b63724ef71729ea6> ;
              wsf:hasAccess <$server_address/wsf/access/459f32962858ffa9677a27c4612cb875> ;
              wsf:hasCrudUsage <$server_address/wsf/usage/auth/validator/> .
            
            <$server_address/wsf/usage/auth/validator/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .
              
            <$server_address/wsf/ws/auth/registrar/ws/> rdf:type wsf:WebService ;
              dcterms:title \"Web Service(s) Registration web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/auth/registrar/ws/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/auth/registrar/ws/> .
            
            <$server_address/wsf/usage/auth/registrar/ws/> rdf:type wsf:CrudUsage ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .
                
            <$server_address/wsf/ws/auth/registrar/access/> rdf:type wsf:WebService ;
              dcterms:title \"Access Registration web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/auth/registrar/access/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/auth/registrar/access/> .
            
            <$server_address/wsf/usage/auth/registrar/access/> rdf:type wsf:CrudUsage ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .
            
            <$server_address/wsf/ws/auth/lister/> rdf:type wsf:WebService ;
              dcterms:title \"Web Service(s), Datasets and Access Listing web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/auth/lister/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/auth/lister/> .
            
            <$server_address/wsf/usage/auth/lister/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .
              
            <$server_address/wsf/ws/crud/create/> rdf:type wsf:WebService ;
              dcterms:title \"Crud Create web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/crud/create/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/crud/create/> .
            
            <$server_address/wsf/usage/crud/create/> rdf:type wsf:CrudUsage ;
              wsf:create \"True\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .    

            <$server_address/wsf/ws/crud/read/> rdf:type wsf:WebService ;
              dcterms:title \"Crud Read web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/crud/read/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/crud/read/> .
            
            <$server_address/wsf/usage/crud/read/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .  
              
            <$server_address/wsf/ws/crud/update/> rdf:type wsf:WebService ;
              dcterms:title \"Crud Update web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/crud/update/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/crud/update/> .
            
            <$server_address/wsf/usage/crud/update/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"True\" ;
              wsf:delete \"False\" .    
              
            <$server_address/wsf/ws/crud/delete/> rdf:type wsf:WebService ;
              dcterms:title \"Crud Delete web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/crud/delete/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/crud/delete/> .
            
            <$server_address/wsf/usage/crud/delete/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"True\" .    
            
            <$server_address/wsf/ws/dataset/create/> rdf:type wsf:WebService ;
              dcterms:title \"Dataset Create web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/dataset/create/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/dataset/create/> .
            
            <$server_address/wsf/usage/dataset/create/> rdf:type wsf:CrudUsage ;
              wsf:create \"True\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .
            
            <$server_address/wsf/ws/dataset/read/> rdf:type wsf:WebService ;
              dcterms:title \"Dataset Read web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/dataset/read/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/dataset/read/> .
            
            <$server_address/wsf/usage/dataset/read/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .

            <$server_address/wsf/ws/dataset/update/> rdf:type wsf:WebService ;
              dcterms:title \"Dataset Delete web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/dataset/update/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/dataset/update/> .
            
            <$server_address/wsf/usage/dataset/update/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"True\" ;
              wsf:delete \"False\" .  
            
            <$server_address/wsf/ws/dataset/delete/> rdf:type wsf:WebService ;
              dcterms:title \"Dataset Delete web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/dataset/delete/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/dataset/delete/> .
            
            <$server_address/wsf/usage/dataset/delete/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"True\" .  
            
            <$server_address/wsf/ws/ontology/create/> rdf:type wsf:WebService ;
              dcterms:title \"Ontology Create web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/ontology/create/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/ontology/create/> .

            <$server_address/wsf/usage/ontology/create/> rdf:type wsf:CrudUsage ;
              wsf:create \"True\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" . 
            
            <$server_address/wsf/ws/ontology/read/> rdf:type wsf:WebService ;
              dcterms:title \"Ontology Read web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/ontology/read/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/ontology/read/> .    
                        
            <$server_address/wsf/usage/ontology/read/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .                         
                        
            <$server_address/wsf/ws/ontology/update/> rdf:type wsf:WebService ;
              dcterms:title \"Ontology Update web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/ontology/update/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/ontology/update/> .
              
            <$server_address/wsf/usage/ontology/update/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"True\" ;
              wsf:delete \"False\" .               
              
            <$server_address/wsf/ws/ontology/delete/> rdf:type wsf:WebService ;
              dcterms:title \"Ontology Delete web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/ontology/delete/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/ontology/delete/> .              

             <$server_address/wsf/usage/ontology/delete/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"True\" . 

            <$server_address/wsf/ws/search/> rdf:type wsf:WebService ;
              dcterms:title \"Search web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/search/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/search/> .
            
            <$server_address/wsf/usage/search/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .  

            <$server_address/wsf/ws/browse/> rdf:type wsf:WebService ;
              dcterms:title \"Browse web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/browse/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/browse/> .
            
            <$server_address/wsf/usage/browse/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .  
              
            <$server_address/wsf/ws/sparql/> rdf:type wsf:WebService ;
              dcterms:title \"SPARQL web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/sparql/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/sparql/> .
            
            <$server_address/wsf/usage/sparql/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .  
              
            <$server_address/wsf/ws/converter/bibtex/> rdf:type wsf:WebService ;
              dcterms:title \"Converter Bibtex web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/converter/bibtex/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/converter/bibtex/> .
            
            <$server_address/wsf/usage/converter/bibtex/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .

            <$server_address/wsf/ws/converter/irjson/> rdf:type wsf:WebService ;
              dcterms:title \"Converter irJSON web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/converter/irjson/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/converter/irjson/> .
            
            <$server_address/wsf/usage/converter/irjson/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .
              
            <$server_address/wsf/ws/converter/tsv/> rdf:type wsf:WebService ;
              dcterms:title \"Converter TSV web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/converter/tsv/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/converter/tsv/> .
            
            <$server_address/wsf/usage/converter/tsv/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .                                
              ";
  break;

  case "create_world_readable_dataset_read":
      $rdf = "@prefix wsf: <http://purl.org/ontology/wsf#> .
              @prefix void: <http://rdfs.org/ns/void#> .
              @prefix dcterms: <http://purl.org/dc/terms/> .
              @prefix foaf: <http://xmlns.com/foaf/0.1/> .
              @prefix owl: <http://www.w3.org/2002/07/owl#> .
              @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
              @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
              @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
              
              <$server_address/wsf/access/" . md5("$server_address/wsf/datasets/0.0.0.0/") . "> rdf:type wsf:Access ;
                wsf:registeredIP \"0.0.0.0\" ;
                wsf:create \"False\" ;
                wsf:read \"True\" ;
                wsf:update \"False\" ;
                wsf:delete \"False\" ;
                wsf:webServiceAccess <$server_address/wsf/ws/dataset/read/> ;
                wsf:datasetAccess <$server_address/wsf/datasets/> .";
  break;

  case "create_world_creatable_dataset_create":
      $rdf = "@prefix wsf: <http://purl.org/ontology/wsf#> .
              @prefix void: <http://rdfs.org/ns/void#> .
              @prefix dcterms: <http://purl.org/dc/terms/> .
              @prefix foaf: <http://xmlns.com/foaf/0.1/> .
              @prefix owl: <http://www.w3.org/2002/07/owl#> .
              @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
              @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
              @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
              
              <$server_address/wsf/access/" . md5("$server_address/wsf/datasets/0.0.0.0/") . "> rdf:type wsf:Access ;
                wsf:registeredIP \"0.0.0.0\" ;
                wsf:create \"True\" ;
                wsf:read \"False\" ;
                wsf:update \"False\" ;
                wsf:delete \"False\" ;
                wsf:webServiceAccess <$server_address/wsf/ws/dataset/create/> ;
                wsf:datasetAccess <$server_address/wsf/datasets/> .";
  break;
  
  case "create_tracker_ws":
    $rdf =
      "@prefix wsf: <http://purl.org/ontology/wsf#> .
            @prefix void: <http://rdfs.org/ns/void#> .
            @prefix dcterms: <http://purl.org/dc/terms/> .
            @prefix foaf: <http://xmlns.com/foaf/0.1/> .
            @prefix owl: <http://www.w3.org/2002/07/owl#> .
            @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
            @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            
            <$server_address/wsf/> wsf:hasWebService <$server_address/wsf/ws/tracker/create/> .
              
            <$server_address/wsf/ws/tracker/create/> rdf:type wsf:WebService ;
              dcterms:title \"Tracker Create web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/tracker/create/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/tracker/create/> .
            
            <$server_address/wsf/usage/tracker/create/> rdf:type wsf:CrudUsage ;
              wsf:create \"True\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .                
              
            <$server_address/wsf/access/5b2b633495a58612b63724ef71729ea6102> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the authentication registrar web service to register new web services to the WSF\"\"\";
              wsf:registeredIP \"$wsf_local_ip\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/tracker/create/> ;
              wsf:datasetAccess <$server_address/wsf/track/> .";
  break;
  
  case "create_world_accessible_ontology":
      $rdf = "@prefix wsf: <http://purl.org/ontology/wsf#> .
              @prefix void: <http://rdfs.org/ns/void#> .
              @prefix dcterms: <http://purl.org/dc/terms/> .
              @prefix foaf: <http://xmlns.com/foaf/0.1/> .
              @prefix owl: <http://www.w3.org/2002/07/owl#> .
              @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
              @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
              @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
              
              <$server_address/wsf/access/" . md5("$server_address/wsf/ontology/0.0.0.0/") . "> rdf:type wsf:Access ;
                wsf:registeredIP \"0.0.0.0\" ;
                wsf:create \"True\" ;
                wsf:read \"True\" ;
                wsf:update \"True\" ;
                wsf:delete \"True\" ;
                wsf:webServiceAccess <$server_address/wsf/ws/ontology/create/> ;
                wsf:webServiceAccess <$server_address/wsf/ws/ontology/delete/> ;
                wsf:webServiceAccess <$server_address/wsf/ws/ontology/read/> ;
                wsf:webServiceAccess <$server_address/wsf/ws/ontology/update/> ;
                wsf:datasetAccess <$server_address/wsf/ontologies/> .";
  break;
  
  
  case "create_user_full_access":
    $rdf = "@prefix wsf: <http://purl.org/ontology/wsf#> .
            @prefix void: <http://rdfs.org/ns/void#> .
            @prefix dcterms: <http://purl.org/dc/terms/> .
            @prefix foaf: <http://xmlns.com/foaf/0.1/> .
            @prefix owl: <http://www.w3.org/2002/07/owl#> .
            @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
            @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            
            <$server_address/wsf/access/" . md5("$server_address/wsf/" . $user_address) . "> rdf:type wsf:Access ;
              wsf:registeredIP \"$user_address\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/auth/lister/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/auth/registrar/ws/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/auth/registrar/access/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/create/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/read/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/delete/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/update/> ;
              wsf:datasetAccess <$server_address/wsf/> .
            
            <$server_address/wsf/access/" . md5("$server_address/wsf/ontologies/" . $user_address) . "> rdf:type wsf:Access ;
              wsf:registeredIP \"$user_address\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/create/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/delete/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/read/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/update/> ;
              wsf:datasetAccess <$server_address/wsf/ontologies/> .  
              
            <$server_address/wsf/access/" . md5("$server_address/wsf/datasets/" . $user_address) . "> rdf:type wsf:Access ;
              wsf:registeredIP \"$user_address\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/create/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/read/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/delete/> ;
              wsf:webServiceAccess <$server_address/wsf/ws/dataset/update/> ;
              wsf:datasetAccess <$server_address/wsf/datasets/> .
            
            <$server_address/wsf/ws/auth/validator/> rdf:type wsf:AuthenticationWebService ;
              wsf:hasAccess <$server_address/wsf/access/" . md5("$server_address/wsf/datasets/" . $user_address) . "> ;
              wsf:hasAccess <$server_address/wsf/access/" . md5("$server_address/wsf/ontologies/" . $user_address) . "> ;    
              wsf:hasAccess <$server_address/wsf/access/" . md5("$server_address/wsf/" . $user_address) . "> .";

  break;
  
  case "update_wsf_for_ontology_ws":
    $rdf =
      "@prefix wsf: <http://purl.org/ontology/wsf#> .
            @prefix void: <http://rdfs.org/ns/void#> .
            @prefix dcterms: <http://purl.org/dc/terms/> .
            @prefix foaf: <http://xmlns.com/foaf/0.1/> .
            @prefix owl: <http://www.w3.org/2002/07/owl#> .
            @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
            @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            
            <$server_address/wsf/> rdf:type wsf:WebServiceFramework ;
              wsf:hasWebService <$server_address/wsf/ws/ontology/create/> ;
              wsf:hasWebService <$server_address/wsf/ws/ontology/delete/> ;
              wsf:hasWebService <$server_address/wsf/ws/ontology/read/> ;
              wsf:hasWebService <$server_address/wsf/ws/ontology/update/>.
        
            <$server_address/wsf/access/459f32962858ffa9677a27c4612cb875> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the admin of the WSF to generate and manage ontologies of the WSF\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/create/> ;
              wsf:datasetAccess <$server_address/wsf/ontologies/> .  
              
            <$server_address/wsf/access/459f32962858ffa9677a27c4612cb876> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the admin of the WSF to generate and manage ontologies of the WSF\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/delete/> ;
              wsf:datasetAccess <$server_address/wsf/ontologies/> .  
              
            <$server_address/wsf/access/459f32962858ffa9677a27c4612cb877> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the admin of the WSF to generate and manage ontologies of the WSF\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/read/> ;
              wsf:datasetAccess <$server_address/wsf/ontologies/> .  
              
            <$server_address/wsf/access/459f32962858ffa9677a27c4612cb878> rdf:type wsf:Access ;
              dcterms:description \"\"\"This access is used to enable the admin of the WSF to generate and manage ontologies of the WSF\"\"\";
              wsf:registeredIP \"127.0.0.1\" ;
              wsf:create \"True\" ;
              wsf:read \"True\" ;
              wsf:update \"True\" ;
              wsf:delete \"True\" ;
              wsf:webServiceAccess <$server_address/wsf/ws/ontology/update/> ;
              wsf:datasetAccess <$server_address/wsf/ontologies/> .    

            <$server_address/wsf/ws/ontology/create/> rdf:type wsf:WebService ;
              dcterms:title \"Ontology Create web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/ontology/create/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/ontology/create/> .

            <$server_address/wsf/usage/ontology/create/> rdf:type wsf:CrudUsage ;
              wsf:create \"True\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" . 
            
            <$server_address/wsf/ws/ontology/read/> rdf:type wsf:WebService ;
              dcterms:title \"Ontology Read web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/ontology/read/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/ontology/read/> .    
                        
            <$server_address/wsf/usage/ontology/read/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"True\" ;
              wsf:update \"False\" ;
              wsf:delete \"False\" .                         
                        
            <$server_address/wsf/ws/ontology/update/> rdf:type wsf:WebService ;
              dcterms:title \"Ontology Update web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/ontology/update/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/ontology/update/> .
              
            <$server_address/wsf/usage/ontology/update/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"True\" ;
              wsf:delete \"False\" .               
              
            <$server_address/wsf/ws/ontology/delete/> rdf:type wsf:WebService ;
              dcterms:title \"Ontology Delete web service\" ;
              wsf:endpoint \"\"\"$server_address/ws/ontology/delete/\"\"\";
              wsf:hasCrudUsage <$server_address/wsf/usage/ontology/delete/> .              

             <$server_address/wsf/usage/ontology/delete/> rdf:type wsf:CrudUsage ;
              wsf:create \"False\" ;
              wsf:read \"False\" ;
              wsf:update \"False\" ;
              wsf:delete \"True\" .";

  break;          

  case "reset":

    $db = new DB_Virtuoso($username, $password, $dsn, $host);

    $query = "exst('select * from (sparql clear graph <$server_address/wsf/ontologies/>) sub')";
    $db->query($query);

    $query = "exst('select * from (sparql clear graph <$server_address/wsf/datasets/>) sub')";
    $db->query($query);

    $query = "exst('select * from (sparql clear graph <$server_address/wsf/>) sub')";
    $db->query($query);

    $db->close();

    echo "WSF instance reseted";

    return;
  break;
}

$db = new DB_Virtuoso($username, $password, $dsn, $host);

$db->query("DB.DBA.TTLP_MT('" . preg_replace("/\\\*'/", "\\\'", $rdf)
  . "', '$server_address/wsf/', '$server_address/wsf/')");

$db->close();

echo "Done";

//@}

?>