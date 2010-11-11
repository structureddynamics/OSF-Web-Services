The scones (subject concepts or named entities) tagger is how you identify and then tag information in document and text content in accordance with the ontology or named entities common to your domain. 

To install Scones, you have to install a few other softwares as well. Briefly, what you will need is:

(1) A running Tomcat instance
(2) Gate ran by the Tomcat install
(3) Php/Java Bridge
(4) A working Gate application

The goal is to create a Gate application using the Gate Developer application. Then to use that Gate application to run on the Tomcat instance. It is that instance that will be used by the Scones web service endpoint. The Gate application will use the OWL ontology with some named entities dictionaries to tag documents.

Here are the manuals to install and configure Scones:

(1) Installing and using Gate on your desktop: http://techwiki.openstructs.org/index.php/Installing_GATE
(2) Install and configure Tomcat6, Php/Java Bridge, Gate and Scones: http://techwiki.openstructs.org/index.php/Scones_and_structScones_Installation_Instructions
(3) Scones webservice endpoint documentation: http://techwiki.openstructs.org/index.php/Scones