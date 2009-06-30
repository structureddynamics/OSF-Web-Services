<?php

	/*! @ingroup WsFramework Framework for the Web Services */
	//@{ 
	
	/*! @file \ws\framework\ClassHierarchy.php
		 @brief The class hierarchy of the system.
		
		 \n\n
	 
		 @author Frederick Giasson, Structured Dynamics LLC.

		 \n\n\n
	 */
	 
	 
	/*!	 @brief The class hierarchy of the system.
			 @details This class structure is used by multiple modules to leverage the class structure of a node. It is used to get the super-classes-of, sub-classes-of, labels and descriptions of classes.
							
			\n

			@todo Load this structure from the database system instead of the file system (using include_once).			
		
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	class ClassHierarchy
	{
		/*! @brief ClassNode(s) that define the class structure */
		public $classes = array();
		
		/*!	 @brief Constructor
								
				\n
				
				@param[in] $rootClass Root class of the class hierarchy. This is normally owl:Thing.
				
				@author Frederick Giasson, Structured Dynamics LLC.
			
				\n\n\n
		*/	
		function __construct($rootClass)
		{
			$this->classes[$rootClass] = new ClassNode($rootClass, "");
		}
		
		function __destruct(){}

		
		/*!	 @brief Add a class to the class hierarchy
								
				\n
				
				@param[in] $class URI of the class to add to the hierarchy
				@param[in] $subClassOf URI of the super-class of the class being added.
			
				@author Frederick Giasson, Structured Dynamics LLC.
			
				\n\n\n
		*/	
		public function addClassRelationship($class, $subClassOf)
		{
			// Make sure the relationship doesn't already exists
			if(isset($this->classes[$class]->subClassOf))
			{
				foreach($this->classes[$class]->subClassOf as $sc)
				{
					if($sc->name == $subClassOf)
					{
						return;
					}
				}
			}
			
			// First, check if the superClass exists. If it doesn't, we link it to owl:Thing
			if(!isset($this->classes[$subClassOf]))
			{
				$this->addClassRelationship($subClassOf, "http://www.w3.org/2002/07/owl#Thing");	
			}
		
			// Then check if the class already belong to the structure. If it does, we only have to re-link the structure
			if(isset($this->classes[$class]))
			{
				$target = $this->classes[$class];
				
				$superClass = $this->classes[$subClassOf];
				
				array_push($superClass->superClassOf, $target);
				
				array_push($target->subClassOf, $superClass);


				// Lets remove the owl:Thing link if it was existing (introduced at step 1).				
				///////////////////////////////////////////////////
				$newSubclassArray = array();
				
				foreach($target->subClassOf as $sc)
				{
					if($sc->name != "http://www.w3.org/2002/07/owl#Thing")
					{
						array_push($newSubclassArray, $sc);
					}
					else
					{
						// Remove the link from the subClassOf owl:Thing too!
						$newSuperClassArray = array();
						
						$owlThing = $this->classes["http://www.w3.org/2002/07/owl#Thing"];
						
						foreach($owlThing->superClassOf as $sc)
						{
							if($sc->name != $target->name)
							{
								array_push($newSuperClassArray, $sc);
							}
						}
						
						$owlThing->superClassOf = $newSuperClassArray;
					}
				}
				
				$target->subClassOf = $newSubclassArray;
				///////////////////////////////////////////////////
			}
			else
			{	
				// Otherwise we have a new node to add to the structure.
				$newClass =  new ClassNode($class, $superClass);
				$this->classes[$class] = $newClass;
				
				$superClass = $this->classes[$subClassOf];
				
				array_push($superClass->superClassOf, &$newClass);
				
				array_push($newClass->subClassOf, $superClass);
			}
		}
		
		/*!	 @brief Returns a list of references to the superclasses
								
				\n
				
				@param[in] $class URI of the class to get its super-classes references
				
				@return returns a list of references to the superclasses
			
				@author Frederick Giasson, Structured Dynamics LLC.
			
				\n\n\n
		*/	
		public function getSuperClasses($class)
		{
			$superClasses = array();
			$stack = array();

			if(isset($this->classes[$class]))
			{			
				// Initialize the stack
				foreach($this->classes[$class]->subClassOf as $sc)
				{
					if(array_search($sc, $stack) === FALSE)
					{ 
						array_push($stack, $sc);
					}
				}
				
				while(count($stack) > 0)
				{
					$target = array_pop($stack);
					
					array_push($superClasses, $target);
					
					if(isset($target->subClassOf))
					{
						foreach($target->subClassOf as $sc)
						{
							if(array_search($sc, $stack) === FALSE)
							{ 
								array_push($stack, $sc);
							}
						}
					}
				}		
			}
					
			return $superClasses;
		}
		
		/*!	 @brief Returns a list of references to the subclasses
								
				\n
				
				@param[in] $class URI of the class to get its sub-classes references
				
				@return returns a list of references to the subclasses
			
				@author Frederick Giasson, Structured Dynamics LLC.
			
				\n\n\n
		*/			
		public function getSubClasses($class)
		{
			$subClasses = array();
			$stack = array();
			
			if(isset($this->classes[$class]))
			{			
				// Initialize the stack
				foreach($this->classes[$class]->superClassOf as $sc)
				{
					if(array_search($sc, $stack) === FALSE)
					{ 
						array_push($stack, $sc);
					}
				}
				
				while(count($stack) > 0)
				{
					$target = array_pop($stack);
					
					array_push($subClasses, $target);
					
					if(isset($target->superClassOf))
					{
						foreach($target->superClassOf as $sc)
						{
							if(array_search($sc, $stack) === FALSE)
							{ 
								array_push($stack, $sc);
							}
						}
					}
				}		
			}
			
			return $subClasses;
		}	
		
		/*!	 @brief Check if a class if a sub-class of another class
								
				\n
				
				@param[in] $subClass URI of the class to check if it is a sub-class of another class
				@param[in] $superClass URI of the super class of the class
				
				@return returns TRUE if the class is a sub-class of the super-class; FALSE otherwise
			
				@author Frederick Giasson, Structured Dynamics LLC.
			
				\n\n\n
		*/	
		public function isSubClassOf($subClass, $superClass)
		{
			$superClasses = $this->getSuperClasses($subClass);

			foreach($superClasses as $sc)
			{
				if($sc->name == $superClass)
				{
					return(TRUE);
				}
			}
			
			return(FALSE);
		}
	}
	
	/*!	 @brief Class node structure that populate the class hierarchy
							
			\n
			
			@author Frederick Giasson, Structured Dynamics LLC.
		
			\n\n\n
	*/	
	class ClassNode
	{
		/*! @brief URI of the class */
		public $name = "";

		/*! @brief Label that defines that class */
		public $label = "";

		/*! @brief Description of the class */
		public $description = "";

		/*! @brief Array of references to immediate super-classes-of this node  */
		public $subClassOf = array();	// array of pointers

		/*! @brief Array of references to immediate sub-classes-of this node  */
		public $superClassOf = array();		// array of pointers
		
		/*!	 @brief Constructor
								
				\n
				
				@param[in] $name URI of the class node
				@param[in] $subClassOf Array of references to the super-classes of this class node
				
				@author Frederick Giasson, Structured Dynamics LLC.
			
				\n\n\n
		*/	
		function __construct($name, $subClassOf)
		{
			$this->name = $name;
			
			if(isset($subClassOf->name) && $subClassOf->name != "")
			{
				$this->subClassOf[$subClassOf->name] = $subClassOf;
			}
		}
		
		function __destruct(){}
	}

	//@} 


?>