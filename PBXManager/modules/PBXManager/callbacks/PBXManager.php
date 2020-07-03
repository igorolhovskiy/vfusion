<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
chdir(dirname(__FILE__) . '/../../../');
include_once 'include/Webservices/Relation.php';
include_once 'vtlib/Vtiger/Module.php';
include_once 'includes/main/WebUI.php';
vimport('includes.http.Request');

class PBXManager_PBXManager_Callbacks {
    
    function validateRequest($vtigersecretkey,$request) {
	//$logFusion =& LoggerManager::getLogger('fusion');
        if($vtigersecretkey == $request->get('vtigersignature')){
            return true;
        }
        return false;
    }

    function process($request){
	    //$logFusion =& LoggerManager::getLogger('fusion');
	  if ($_SERVER["REQUEST_METHOD"] == "POST"){
        	$request_json = json_decode(file_get_contents('php://input'),TRUE);
	  }    
	  foreach ($request_json as $key => $value) {
		$request->set($key,$value);
	  }    

	$pbxmanagerController = new PBXManager_PBXManager_Controller();
        $connector = $pbxmanagerController->getConnector();
	$ipfusion = $connector->getFusionIP();
	//$logFusion->debug("PBX PROCESS REQUEST REMOTE_ADDR=".$_SERVER['REMOTE_ADDR']." ipfusion=".$ipfusion);
	if ($ipfusion) {
	    if ( $_SERVER['REMOTE_ADDR'] === $ipfusion ) {
		////$logFusion->debug("PBX PROCESS REQUEST OK REMOTE_ADDR=".$_SERVER['REMOTE_ADDR']." ipfusion=".$ipfusion);
	    } else {
		//$logFusion->debug("PBX PROCESS REQUEST NOT OK REMOTE_ADDR=".$_SERVER['REMOTE_ADDR']." ipfusion=".$ipfusion);
		return;
	    }
	}
        if($this->validateRequest($connector->getVtigerSecretKey(),$request)) {
            $pbxmanagerController->process($request);
        }else {
            $response = $connector->getXmlResponse();
            echo $response;
        }
    }
}
$pbxmanager = new PBXManager_PBXManager_Callbacks();
	    $pbxmanager->process(new Vtiger_Request($_REQUEST));
?>