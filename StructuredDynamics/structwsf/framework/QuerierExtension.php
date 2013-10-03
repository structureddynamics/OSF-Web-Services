<?php

/*! @ingroup OSFPHPAPIFramework Framework of the OSF PHP API library */
//@{

/*! @file \StructuredDynamics\osf\framework\QuerierExtension.php
    @brief Querying a RESTFull web service endpoint

*/

namespace StructuredDynamics\osf\framework;
use StructuredDynamics\osf\php\api\framework\WebServiceQuery;


/**
* This class allows for interaction with the WebServiceQuerier and
* WebServiceQuery so that systems which wish to perform logging,
* timing or adjust the call being made have points in processing
* where that can happen. It is intended to be subclassed for
* implementing desired functionality though is not defined as
* an interface as it serves as the default QuerierExtension when
* no other is specified. Most operations are no ops in this
* implementation with the exception of displayError
*
* @author Chris Johnson
*/
class QuerierExtension {
  /**
   * Called just before call to the web service is executed
   *
   * @param $wsq WebServiceQuerier that is executing
   */
  function startQuery(WebServiceQuerier $wsq) {}
  /**
   * Called when the call to the web service returns
   *
   * @param $wsq WebServiceQuerier that is executing
   */
  function stopQuery(WebServiceQuerier $wsq, $data) {}

  /**
   * Called after initial processing of response from web service
   * is complete and just before control is returned to the caller
   *
   * @param $wsq WebServiceQuerier that is executing
   */
  function debugQueryReturn(WebServiceQuerier $wsq, $data) {}

  /**
   * Called before the call to the web service is executed
   *
   * @param $wsq WebServiceQuerier that is executing
   * @param $curl_handle cURL handle that will be executed
   */
  function alterQuery(WebServiceQuerier $wsq, $curl_handle) {
  }

  /**
   * Allow for environment specific error message display
   *
   * @param $qe QuerierError to display
   */
  function displayError($qe)
  {
    print 'Error id: ' . $qe->id .
          ' level: ' . $qe->level .
          ' service: ' . $qe->webservice .
          ' name: ' . $qe->name .
          ' description: ' . $qe->description .
          ' debugInfo: ' . $qe->debugInfo .
          "\n";
  }

  /**
   * Called before parameters are assembled for the WebServiceQuerier
   *
   * @param $query WebServiceQuery that is executing
   * @param &$params Array of parameters which can be manipulated
   */
  function alterParams(WebServiceQuery $query, &$params) {}
}

?>
