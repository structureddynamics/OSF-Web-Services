<?php

use \StructuredDynamics\osf\ws\converter\irjson\irJSONParser;

$irJSONFile =
  "{
                                                    \"dataset\": {
                                                        \"id\": \"http://bibserver.berkeley.edu/datasets/\",
                                                        \"prefLabel\": \"Publications of James Pitman\",
                                                        \"description\": \"Publications of James Pitman\",
                                                        \"source\": {
                                                            \"prefLabel\": \"Stanford University\",
                                                            \"prefURL\": \"http://www.stanford.edu/\",
                                                            \"ref\": \"@ustanford\"
                                                        },
                                                        \"createDate\": \"01/01/2008\",
                                                        \"creator\": {
                                                            \"prefLabel\": \"Jim Pitman\",
                                                            \"prefURL\": \"http://www.stat.berkeley.edu/~pitman/\",
                                                            \"ref\": \"@jpitman\"
                                                        },
                                                        \"linkage\": {
                                                            \"version\": \"0.1\",
                                                            \"linkedType\": \"application/rdf+xml\",
                                                            \"prefixList\": {
                                                                \"bibo\": \"http://purl.org/ontology/bibo/\",
                                                                \"dcterms\": \"http://purl.org/dc/elements/1.1/\",
                                                                \"rdfs\": \"http://www.w3.org/2000/01/rdf-schema#\",
                                                                \"rdf\": \"http://www.w3.org/1999/02/22-rdf-syntax-ns#\",
                                                                \"bibo_degrees\": \"http://purl.org/ontology/bibo/degrees/\",
                                                                \"address\": \"http://schemas.talis.com/2005/address/schema#\",
                                                                \"bkn\": \"http://purl.org/ontology/bkn#\"
                                                            },
                                                            \"attributeList\": {
                                                                \"address\": {
                                                                    \"mapTo\": \"address:localityName\"
                                                                },
                                                                \"author\": {
                                                                    \"mapTo\": \"dcterms:creator\"
                                                                },
                                                                \"title\": {
                                                                    \"mapTo\": \"dcterms:title\"
                                                                },
                                                                \"chapter\": {
                                                                    \"mapTo\": \"bibo:chapter\"
                                                                },
                                                                \"ref\": {
                                                                    \"mapTo\": \"rdf:resource\"
                                                                },
                                                                \"edition\": {
                                                                    \"mapTo\": \"bibo:edition\"
                                                                },
                                                                \"editor\": {
                                                                    \"mapTo\": \"bibo:editor\"
                                                                },
                                                                \"eprint\": {
                                                                    \"mapTo\": \"rdfs:seeAlso\"
                                                                },
                                                                \"howpublished\": {
                                                                    \"mapTo\": \"dcterms:publisher\"
                                                                },
                                                                \"institution\": {
                                                                    \"mapTo\": \"dcterms:contributor\"
                                                                },
                                                                \"journal\": {
                                                                    \"mapTo\": \"dcterms:isPartOf\"
                                                                },
                                                                \"key\": {
                                                                    \"mapTo\": \"foo:bar\"
                                                                },
                                                                \"month\": {
                                                                    \"mapTo\": \"dcterms:date\"
                                                                },
                                                                \"note\": {
                                                                    \"mapTo\": \"skos:note\"
                                                                },
                                                                \"number\": {
                                                                    \"mapTo\": \"bibo:number\"
                                                                },
                                                                \"organization\": {
                                                                    \"mapTo\": \"bibo:organizer\"
                                                                },
                                                                \"pages\": {
                                                                    \"mapTo\": \"bibo:pages\"
                                                                },
                                                                \"publisher\": {
                                                                    \"mapTo\": \"dcterms:publisher\"
                                                                },
                                                                \"school\": {
                                                                    \"mapTo\": \"rdfs:seeAlso\"
                                                                },
                                                                \"series\": {
                                                                    \"mapTo\": \"dcterms:isPartOf\"
                                                                },
                                                                \"type\": {
                                                                    \"mapTo\": \"rdf:type\"
                                                                },
                                                                \"href\": {
                                                                    \"mapTo\": \"bkn:url\"
                                                                },
                                                                \"volume\": {
                                                                    \"mapTo\": \"bibo:volume\"
                                                                },
                                                                \"year\": {
                                                                    \"mapTo\": \"dcterms:date\"
                                                                }
                                                            },
                                                            \"typeList\": {
                                                                \"article\": {
                                                                    \"mapTo\": \"bibo:Article\"
                                                                },
                                                                \"book\": {
                                                                    \"mapTo\": \"bibo:Book\"
                                                                },
                                                                \"booklet\": {
                                                                    \"mapTo\": \"bibo:Booklet\"
                                                                },
                                                                \"conference\": {
                                                                    \"mapTo\": \"bibo:Conference\"
                                                                },
                                                                \"inbook\": {
                                                                    \"mapTo\": \"bibo:Chapter\"
                                                                },
                                                                \"incollection\": {
                                                                    \"mapTo\": \"bibo:BookSection\"
                                                                },
                                                                \"inproceedings\": {
                                                                    \"mapTo\": \"bibo:Article\"
                                                                },
                                                                \"manual\": {
                                                                    \"mapTo\": \"bibo:Manual\"
                                                                },
                                                                \"mastersthesis\": {
                                                                    \"mapTo\": \"bibo:Thesis\",
                                                                    \"addMapping\": {
                                                                        \"bibo:degree\": \"bibo_degrees:ma\"
                                                                    }
                                                                },
                                                                \"misc\": {
                                                                    \"mapTo\": \"bibo:Document\"
                                                                },
                                                                \"phdthesis\": {
                                                                    \"mapTo\": \"bibo:Thesis\",
                                                                    \"addMapping\": {
                                                                        \"bibo:degree\": \"bibo_degrees:phd\"
                                                                    }
                                                                },
                                                                \"proceedings\": {
                                                                    \"mapTo\": \"bibo:Proceedings\"
                                                                },
                                                                \"techreport\": {
                                                                    \"mapTo\": \"bibo:Report\"
                                                                },
                                                                \"unpublished\": {
                                                                    \"mapTo\": \"bibo:Document\",
                                                                    \"addMapping\": {
                                                                        \"bibo:status\": \"bibo:unpublished\"
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    },
                                                    \"recordList\": [
                                                        {
                                                            \"id\": \"MR2276901\",
                                                            \"type\": \"Article\",
                                                            \"prefLabel\": \"Two recursive decompositions of Brownian bridge related to the asymptotics of random mappings\",
                                                            \"description\": \"Aldous and Pitman (1994) studied asymptotic distributions, as n tends to infinity, of various functionals of a uniform random mapping of a set of n elements, by constructing a mapping-walk and showing these mapping-walks converge weakly to a reflecting Brownian bridge. Two different ways to encode a mapping as a walk lead to two different decompositions of the Brownian bridge, each defined by cutting the path of the bridge at an increasing sequence of recursively defined random times in the zero set of the bridge. The random mapping asymptotics entail some remarkable identities involving the random occupation measures of the bridge fragments defined by these decompositions. We derive various extensions of these identities for Brownian and Bessel bridges, and characterize the distributions of various path fragments involved, using the theory of Poisson processes of excursions for a self-similar Markov process whose zero set is the range of a stable subordinator of index between 0 and 1.\",
                                                            \"year\": \"2006\",
                                                            \"pages\": \"269--303\",
                                                            \"arxiv\": \"math.PR/0402399\",
                                                            \"keywords\": [
                                                                \"Path decomposition\",
                                                                \"Path rearrangement\",
                                                                \"Random mapping\",
                                                                \"Combinatorial stochastic process\"
                                                            ],
                                                            \"bibnumer\": \"117\",
                                                            \"volume\": \"1874\",
                                                            \"address\": \"Berlin\",
                                                            \"mrclass\": \"60C05 (60J65)\",
                                                            \"author\": [
                                                                {
                                                                    \"prefLabel\": \"Aldous, David\"
                                                                },
                                                                {
                                                                    \"prefLabel\": \"Pitman, Jim\",
                                                                    \"prefURL\": \"http://www.stat.berkeley.edu/~pitman/\",
                                                                    \"ref\": \"@jpitman\"
                                                                }
                                                            ],
                                                            \"isPartOf\": [
                                                                {
                                                                    \"prefLabel\": \"In memoriam Paul-Andre Meyer: Seeminaire de Probabilites\",
                                                                    \"ref\": \"@book_id\"
                                                                }
                                                            ]
                                                        },
                                                        {
                                                            \"id\": \"jpitman\",
                                                            \"type\": \"Person\",
                                                            \"prefLabel\": \"Jim Pitman\",
                                                            \"homepage\": \"http://www.stat.berkeley.edu/~pitman/\"
                                                        },
                                                        {
                                                            \"id\": \"ustanford\",
                                                            \"type\": \"Organization\",
                                                            \"prefLabel\": \"Stanford University\",
                                                            \"homepage\": \"http://www.stanford.edu/\"
                                                        },
                                                        {
                                                            \"id\": \"book_id\",
                                                            \"type\": \"Book\",
                                                            \"title\": \"In memoriam Paul-Andree Meyer: Seminaire de Probabilites\",
                                                            \"editors\": [
                                                                {
                                                                    \"prefLabel\": \"Michel emery\"
                                                                },
                                                                {
                                                                    \"prefLabel\": \"Marc Yor\"
                                                                }
                                                            ],
                                                            \"isPartOf\": [
                                                                {
                                                                    \"prefLabel\": \"Lecture Notes in Math.\",
                                                                    \"ref\": \"@series_id\"
                                                                }
                                                            ]
                                                        },
                                                        {
                                                            \"id\": \"series_id\",
                                                            \"type\": \"Series\",
                                                            \"title\": \"Lecture Notes in Math.\",
                                                            \"volume\": \"1874\",
                                                            \"publisher\": [
                                                                {
                                                                    \"prefLabel\": \"Springer\"
                                                                }
                                                            ]
                                                        }
                                                    ]
                                                }";

$parser = new irJSONParser($irJSONFile);

if(count($parser->jsonErrors) > 0)
{
  echo "<h2>JSON Parsing Errors</h2>";

  echo "<ul>\n";

  foreach($parser->jsonErrors as $error)
  {
    echo "<li>$error</li>\n";
  }

  echo "<ul>\n";
}

if(count($parser->irjsonErrors) > 0)
{
  echo "<h2>irJSON Parsing Errors</h2>";

  echo "<ul>\n";

  foreach($parser->irjsonErrors as $error)
  {
    echo "<li>$error</li>\n";
  }

  echo "<ul>\n";
}

if(count($parser->irjsonNotices) > 0)
{
  echo "<h2>irJSON Parsing Notices</h2>";

  echo "<ul>\n";

  foreach($parser->irjsonNotices as $notice)
  {
    echo "<li>$notice</li>\n";
  }

  echo "<ul>\n";
}

echo "<h2>Dataset description</h2>";

echo "<pre>";
var_dump($parser->dataset);

echo "</pre>";

echo "<h2>Linkage Schema description</h2>";

echo "<pre>";
var_dump($parser->linkageSchemas);

echo "</pre>";

echo "<h2>Structure Schema description</h2>";

echo "<pre>";
var_dump($parser->structureSchemas);

echo "</pre>";

echo "<h2>Instance Records</h2>";

echo "<pre>";
var_dump($parser->instanceRecords);

echo "</pre>";
?>