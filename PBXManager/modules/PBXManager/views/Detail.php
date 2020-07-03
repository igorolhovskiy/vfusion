<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class PBXManager_Detail_View extends Vtiger_Detail_View{
    
    /**
     * Overrided to disable Ajax Edit option in Detail View of
     * PBXManager Record
     */
    function isAjaxEnabled($recordModel) {
		return false;
	}
 
    /*
     * Overided to convert totalduration to minutes
     */
    function preProcess(Vtiger_Request $request, $display=true) {
		//$logFusion =& LoggerManager::getLogger('fusion');
		$db = PearDatabase::getInstance();
		$recordId = $request->get('record');
		$moduleName = $request->getModule();
		if(!$this->record){
			$this->record = Vtiger_DetailView_Model::getInstance($moduleName, $recordId);
		}
		$recordModel = $this->record->getRecord();
       // To show recording link only if callstatus is 'completed' 
        if($recordModel->get('callstatus') == 'completed') {
	    $query="SELECT recordingurlfusion FROM vtiger_pbxmanager WHERE pbxmanagerid=?";
	    $result = $db->pquery($query,array($recordId));
    	    if ($result) {
                $rowData = $db->query_result_rowdata($result, 0, 'recordingurlfusion');
		$recordModel->set('recordingurl',$rowData['recordingurlfusion']); 
	    }
    	} else {
	    $recordModel->set('recordingurl', '');
	}
        return parent::preProcess($request, true);
    }
}
