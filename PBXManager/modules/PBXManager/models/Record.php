<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class PBXManager_Record_Model extends Vtiger_Record_Model{
    
    const moduletableName = 'vtiger_pbxmanager';
    const lookuptableName = 'vtiger_pbxmanager_phonelookup';
    const entitytableName = 'vtiger_crmentity';
    
    static function getCleanInstance($moduleName = ''){
        return new self;
    }
    
    /**
     * Function to get call details(polling)
     * return <array> calls
     */
    public function searchIncomingCall(){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $db = PearDatabase::getInstance();
        $query = 'SELECT * FROM '.self::moduletableName.' AS module_table INNER JOIN '.self::entitytableName.' AS entity_table  WHERE module_table.callstatus IN(?,?) AND ( module_table.direction=? OR module_table.direction=? ) AND module_table.pbxmanagerid=entity_table.crmid AND entity_table.deleted=0';
		
	$query2 = "SELECT vtiger_pbxmanager.* FROM vtiger_pbxmanager INNER JOIN vtiger_crmentity ON vtiger_pbxmanager.pbxmanagerid=vtiger_crmentity.crmid WHERE vtiger_crmentity.deleted=0 AND ( vtiger_pbxmanager.direction = 'inbound' OR vtiger_pbxmanager.direction = 'local' ) AND ( vtiger_pbxmanager.callstatus = 'ringing' OR vtiger_pbxmanager.callstatus = 'in-progress' )";
        //$result = $db->pquery($query,array('ringing','in-progress','inbound','local'));
	$result = $db->pquery($query2,array());
        $recordModels = $recordIds = array();
        $rowCount =  $db->num_rows($result);
	//$logFusion->debug("PBX searchIncomingCall rowCount=".$rowCount." query=".$query);
        for($i=0; $i<$rowCount; $i++) {
            $rowData = $db->query_result_rowdata($result, $i);
            
            $record = new self();
            $record->setData($rowData);
            $recordModels[] = $record;
	    //$logFusion->debug("PBX searchIncomingCall rowCount=".$rowCount." crmid=".$rowData['crmid']);
            //To check if the call status is 'ringing' for >5min
            $starttime = strtotime($rowData['starttime']);
            $currenttime = strtotime(Date('y-m-d H:i:s'));
            $timeDiff = $currenttime - $starttime;
            if($timeDiff > 300 && $rowData['callstatus'] == 'ringing') {
                $recordIds[] = $rowData['crmid'];
            }
            //END
        }    
        
        if(count($recordIds)) $this->updateCallStatus($recordIds);
        
        return $recordModels;
    }
    
    /**
     * To update call status from 'ringing' to 'no-response', if status not updated 
     * for more than 5 minutes
     * @param type $recordIds
     */
    public function updateCallStatus($recordIds) {
        $db = PearDatabase::getInstance();
        $query = "UPDATE ".self::moduletableName." SET callstatus='no-response' 
                  WHERE pbxmanagerid IN (".generateQuestionMarks($recordIds).") 
                  AND callstatus='ringing'";
        $db->pquery($query, $recordIds);
    }

//FusionPBX begin
    public function localCallerName() {
	//$logFusion =& LoggerManager::getLogger('fusion');
        $db = PearDatabase::getInstance();
	$customernumber = $this->get('customernumber');
	$customername='';
	$params=array();
        $query = "SELECT first_name,last_name FROM vtiger_users WHERE phone_crm_extension='".$customernumber."' AND deleted = 0";
        $result = $db->pquery($query, $params);
        $rowCount =  $db->num_rows($result);
	//$logFusion->debug("PBX localCallerName customernumber=".$customernumber." rowCount=".$rowCount);
        if($rowCount){
            $rowData = $db->query_result_rowdata($result,0);
	    $customername=$rowData['first_name']." ".$rowData['last_name'];
	}	
	return $customername;
    }


//FusionPBX end


        /**
     * Function to save PBXManager record with array of params
     * @param <array> $values
     * return <string> $recordid
     */
    public function saveRecordWithArrray($params){
        $moduleModel = Vtiger_Module_Model::getInstance('PBXManager');
        $recordModel = Vtiger_Record_Model::getCleanInstance('PBXManager');
        $recordModel->set('mode', '');
        $details = array_change_key_case($params, CASE_LOWER);
        $fieldModelList = $moduleModel->getFields();
        foreach ($fieldModelList as $fieldName => $fieldModel) {
                $fieldValue = $details[$fieldName];
                $recordModel->set($fieldName, $fieldValue);
        }
        return $moduleModel->saveRecord($recordModel);
    }
    
    /**
     * Function to update call details
     * @param <array> $details
     * $param <string> $callid
     * return true
     */

//FusionPBX begin
    public function updateCallDetailsDialCall($details){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $db = PearDatabase::getInstance();
	$sourceuuid = $details['calluuid'];
	//$logFusion->debug("PBX updateCallDetailsDialCall sourceuuid=".$sourceuuid);
	$direction=$details['direction'];
	$callstatus1=$details['callstatus1'];
	$callstatus2=$details['callstatus2'];
	$starttime=$details['starttime'];
	$crmid=$details['crmid']; 
	$customernumber=$details['customernumber'];
	$customer=$details['customer'];
	$customertype=$details['customertype'];
	$params=array();
	if ($direction=='inbound') {
	    $query1= "UPDATE vtiger_pbxmanager SET callstatus='".$callstatus1."' WHERE  pbxmanagerid='".$crmid."'";
	    $query2="UPDATE vtiger_pbxmanager SET callstatus='".$callstatus2."',endtime='".$starttime."' WHERE sourceuuid='".$sourceuuid."' AND pbxmanagerid != '".$crmid."'";
	    //$logFusion->debug("PBX updateCallDetailsDialCall query1=".$query1." query2=".$query2." crmid=".$crmid);
	    $db->pquery($query1,$params);
	    $db->pquery($query2,$params);	    
	} else {
	    $query="UPDATE vtiger_pbxmanager SET callstatus='".$callstatus1."',direction='".$direction."',customernumber='".$customernumber."',customer='".$customer."',customertype='".$customertype."'  WHERE  sourceuuid='".$sourceuuid."'";
	    $db->pquery($query,$params);
	}	
        return true;
    }



//FusionPBX end


//FusionPBX begin
    public function updateCallDetailsFusion($details){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $db = PearDatabase::getInstance();
        $sourceuuid = $this->get('sourceuuid');
	$pbxmanagerid = $this->get('pbxmanagerid');
        $query = 'UPDATE '.self::moduletableName.' SET ';
        foreach($details as $key => $value){
            $query .= $key . '=?,';
            $params[] = $value;
	    //$logFusion->debug("PBX updateCallDetailsFusion key=".$key." value=".$value);
        }
        $query = substr_replace($query ,"",-1);
        $query .= ' WHERE sourceuuid = ? AND pbxmanagerid = ?';
	//$logFusion->debug("PBX updateCallDetailsFusion query=".$query." callstatus=".$details['callstatus']);
        $params[] = $sourceuuid;
	$params[] = $pbxmanagerid;
        $db->pquery($query, $params);
	
        return true;
    }


//FusionPBX end


    public function updateCallDetails($details){
        $db = PearDatabase::getInstance();
        $sourceuuid = $this->get('sourceuuid');
        $query = 'UPDATE '.self::moduletableName.' SET ';
        foreach($details as $key => $value){
            $query .= $key . '=?,';
            $params[] = $value;
        }
        $query = substr_replace($query ,"",-1);
        $query .= ' WHERE sourceuuid = ?';
        $params[] = $sourceuuid;
        $db->pquery($query, $params);
        return true;
    }
    
    /**
     * To update Assigned to with user who answered the call 
     */
    public function updateAssignedUser($userid){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $callid = $this->get('pbxmanagerid');
	//$logFusion->debug("PBX updateAssignedUser userid=".$userid." crmid=".$callid." getid=".$this->getId());
        $db = PearDatabase::getInstance();
        $query = 'UPDATE '.self::entitytableName.' SET smownerid=? WHERE crmid=?';
        $params = array($userid, $callid);
        $db->pquery($query, $params);
        return true;
    }
//Fusion begin
    public function updateAssignedUserFusion($userid){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $callid = $this->getId();
	//$logFusion->debug("PBX updateAssignedUserFusion userid=".$userid." crmid=".$callid." getid=".$this->getId());
        $db = PearDatabase::getInstance();
        $query = 'UPDATE '.self::entitytableName.' SET smownerid=? WHERE crmid=?';
        $params = array($userid, $callid);
        $db->pquery($query, $params);
        return true;
    }

//Fusion end

    
    public static function getInstanceById($phonecallsid, $module=null){
        $db = PearDatabase::getInstance();
        $record = new self();
        $query = 'SELECT * FROM '.self::moduletableName.' WHERE pbxmanagerid=?';
        $params = array($phonecallsid);
        $result = $db->pquery($query, $params);
        $rowCount =  $db->num_rows($result);
        if($rowCount){
            $rowData = $db->query_result_rowdata($result, 0);
            $record->setData($rowData);
        }
        return $record;
    }

//FusionPBX begin
    public static function getInstanceBySourceUUIDFusion($sourceuuid,$userid){
        $db = PearDatabase::getInstance();
        $record = new self();
        $query = 'SELECT * FROM '.self::moduletableName.' WHERE sourceuuid=? AND user=?';
        $params = array($sourceuuid,$userid);
        $result = $db->pquery($query, $params);
        $rowCount =  $db->num_rows($result);
	
        if($rowCount){
            $rowData = $db->query_result_rowdata($result, 0);
            $record->setData($rowData);
        }
        return $record;
    }


//FusionPBX end
    
//FusionPBX begin
    public static function getInstanceBySourceUUIDUserID($sourceuuid,$userid){
	//$logFusion =& LoggerManager::getLogger('fusion');

        $db = PearDatabase::getInstance();
        $record = new self();
        $query = 'SELECT * FROM '.self::moduletableName.' WHERE sourceuuid=? AND user=?';
        $params = array($sourceuuid,$userid);
        $result = $db->pquery($query, $params);
        $rowCount =  $db->num_rows($result);
	//$logFusion->debug("PBX getInstanceBySourceUUIDUserID sourceuuid=".$sourceuuid." userid=".$userid." rowCount=".$rowCount); 
        if($rowCount){
            $rowData = $db->query_result_rowdata($result, 0);
            $record->setData($rowData);
        }
        return $record;
    }
//FusionPBX end

//FusionPBX begin
//return array of recordModel
    public static function getInstanceBySourceUUIDFusionEndCall($sourceuuid){
	//$logFusion =& LoggerManager::getLogger('fusion');
	$db = PearDatabase::getInstance();
	$query = 'SELECT * FROM '.self::moduletableName.' WHERE sourceuuid=?';
	
        $params = array($sourceuuid);
        $result = $db->pquery($query, $params);
        $rowCount =  $db->num_rows($result);
	$records=array();
	//$logFusion->debug("PBX getInstanceBySourceUUIDFusionEndCall rowCount=".$rowCount." query=".$query);
	for($i=0;$i<$rowCount;$i++) {
	    $rowData = $db->query_result_rowdata($result, $i);
    	    $record = new self();
	    $record->setData($rowData);
	    $records[$i]=$record;
	}
	return $records;	
    }
//FUsionPBX end



    public static function getInstanceBySourceUUID($sourceuuid){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $db = PearDatabase::getInstance();
        $record = new self();
        $query = 'SELECT * FROM '.self::moduletableName.' WHERE sourceuuid=?';
        $params = array($sourceuuid);
        $result = $db->pquery($query, $params);
        $rowCount =  $db->num_rows($result);
        if($rowCount){
            $rowData = $db->query_result_rowdata($result, 0);
            $record->setData($rowData);
        }
	//$logFusion->debug("PBX getInstanceBySourceUUID sourceuuid=".$sourceuuid." callstatus=".$record->get('callstatus')." callstatus_bd=".$rowData['callstatus']);
        return $record;
    }
    
    /**
     * Function to save/update contact/account/lead record in Phonelookup table on every save
     * @param <array> $details
     */
    public function receivePhoneLookUpRecord($fieldName, $details, $new){
        $recordid = $details['crmid'];
        $fnumber = preg_replace('/[-()\s+]/', '',$details[$fieldName]);
        $rnumber = strrev($fnumber);
        $db = PearDatabase::getInstance();
        
        $params = array($recordid, $details['setype'],$fnumber,$rnumber, $fieldName);
        $db->pquery('INSERT INTO '.self::lookuptableName.
                    '(crmid, setype, fnumber, rnumber, fieldname) 
                    VALUES(?,?,?,?,?) 
                    ON DUPLICATE KEY 
                    UPDATE fnumber=VALUES(fnumber), rnumber=VALUES(rnumber)', 
                    $params);
        return true;
    }
    
     /**
      * Function to delete contact/account/lead record in Phonelookup table on every delete
      * @param <string> $recordid
      */
    public function deletePhoneLookUpRecord($recordid){
        $db = PearDatabase::getInstance();
        $db->pquery('DELETE FROM '.self::lookuptableName.' where crmid=?', array($recordid));
    }

     /**
      * * Function to check the customer with number in phonelookup table
      * @param <string> $from
      */
    public static function lookUpRelatedWithNumber($from){
        $db = PearDatabase::getInstance();
        $fnumber = preg_replace('/[-()\s+]/', '',$from);
        $rnumber = strrev($fnumber);
        $result = $db->pquery('SELECT crmid, fieldname FROM '.self::lookuptableName.' WHERE fnumber LIKE "'. $fnumber . '%" OR rnumber LIKE "'. $rnumber . '%" ', array());
        if($db->num_rows($result)){
            $crmid = $db->query_result($result, 0, 'crmid');
            $fieldname = $db->query_result($result, 0, 'fieldname');
            $contact = $db->pquery('SELECT label,setype FROM '.self::entitytableName.' WHERE crmid=? AND deleted=0', array($crmid));
            if($db->num_rows($result)){
                $data['id'] = $crmid;
                $data['name'] = $db->query_result($contact, 0, 'label');
                $data['setype'] = $db->query_result($contact, 0, 'setype');
                $data['fieldname'] = $fieldname;
                return $data;
            }
            else
                return;
        }
        return;
    }

//FusionPBX start
    public static function lookUpRelatedWithNumberCallStart($from){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $db = PearDatabase::getInstance();
        $fnumber = preg_replace('/[-()\s+]/', '',$from);
        $rnumber = strrev($fnumber);
        $result = $db->pquery('SELECT crmid, fieldname FROM '.self::lookuptableName.' WHERE fnumber LIKE "'. $fnumber . '%" OR rnumber LIKE "'. $rnumber . '%" ', array());
	//$logFusion->debug("PBX lookUpRelatedWithNumberCallStart fnumber=".$fnumber." rnumber=".$rnumber." nmb=".$db->num_rows($result));
        if($db->num_rows($result)){
            $crmid = $db->query_result($result, 0, 'crmid');
            $fieldname = $db->query_result($result, 0, 'fieldname');
            $contact = $db->pquery('SELECT label,setype FROM '.self::entitytableName.' WHERE crmid=? AND deleted=0', array($crmid));
            if($db->num_rows($result)){
                $data['id'] = $crmid;
                $data['name'] = $db->query_result($contact, 0, 'label');
                $data['setype'] = $db->query_result($contact, 0, 'setype');
                $data['fieldname'] = $fieldname;
                return $data;
            }
            else
                return;
        }
        return;
    }

    //lookup firstname, lastname, assigned user crm_phone ( or group of multiple assigned users  )
    public static function lookUpRelatedWithNumberEntityId($crmid,$setype){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $db = PearDatabase::getInstance();
	switch ($setype) {
	    case 'Leads':
		$sql="SELECT vtiger_leaddetails.firstname,vtiger_leaddetails.lastname,vtiger_crmentity.smownerid FROM vtiger_leaddetails INNER JOIN vtiger_crmentity ON vtiger_leaddetails.leadid=vtiger_crmentity.crmid WHERE vtiger_leaddetails.leadid='".$crmid."' AND vtiger_crmentity.deleted=0";	
	    break;
	    case 'Contacts':
		$sql="SELECT vtiger_contactdetails.firstname,vtiger_contactdetails.lastname,vtiger_crmentity.smownerid FROM vtiger_contactdetails INNER JOIN vtiger_crmentity ON vtiger_contactdetails.contactid=vtiger_crmentity.crmid WHERE vtiger_contactdetails.contactid='".$crmid."' AND vtiger_crmentity.deleted=0";	
	    break;
	    case 'Accounts':
		$sql="SELECT vtiger_account.accountname,vtiger_crmentity.smownerid FROM vtiger_account INNER JOIN vtiger_crmentity ON vtiger_account.accountid=vtiger_crmentity.crmid WHERE vtiger_account.accountid='".$crmid."' AND vtiger_crmentity.deleted=0";	
	    break;	    	    	    
	}

	$result=$db->pquery($sql);
	//$logFusion->debug("PBX lookUpRelatedWithNumberEntityId crmid=".$crmid." setype=".$setype." sql=".$sql." nmb=".$db->num_rows($result));
 	    
	if($db->num_rows($result)){
	    if ($setype=='Leads' || $setype=='Contacts') {
		$resEntity['firstname']=$db->query_result($result, 0, 'firstname');
		$resEntity['lastname']=$db->query_result($result, 0, 'lastname');
		$resEntity['company']=$db->query_result($result, 0, 'company');
	    } elseif ($setype=='Accounts') {
		$resEntity['company']=$db->query_result($result, 0, 'accountname');
	    }    
	    $smownerid=$db->query_result($result, 0, 'smownerid');
	    //checking smownerid is groupid
	    $resisgroup=$db->pquery("SELECT vtiger_users2group.userid,vtiger_users.phone_crm_extension FROM vtiger_users2group INNER JOIN vtiger_users ON vtiger_users2group.userid=vtiger_users.id WHERE vtiger_users2group.groupid='".$smownerid."' AND vtiger_users.deleted=0 AND vtiger_users.phone_crm_extension != ''");
	    $resEntity['phone_crm_extension']=array();
	    //$logFusion->debug("PBX lookUpRelatedWithNumberEntityId smownerid=".$smownerid." nmbgrp=".$db->num_rows($resisgroup)." sql=SELECT vtiger_users2group.userid,vtiger_users.phone_crm_extension FROM vtiger_users2group INNER JOIN vtiger_users ON vtiger_users2group.userid=vtiger_users.id WHERE vtiger_users2group.groupid='".$smownerid."' AND vtiger_users.deleted=0 AND vtiger_users.phone_crm_extension != ''");
	    if($db->num_rows($resisgroup)){
		$rowCount=$db->num_rows($resisgroup);				
		for($i=0; $i<$rowCount; $i++) {
        	    $resEntity['phone_crm_extension'][] = $db->query_result($resisgroup,$i,'phone_crm_extension');
		}
	    } else {
		$resisown=$db->pquery("SELECT vtiger_users.phone_crm_extension FROM vtiger_users WHERE vtiger_users.id='".$smownerid."' AND vtiger_users.deleted=0 AND vtiger_users.phone_crm_extension !=''");
	    //$logFusion->debug("PBX lookUpRelatedWithNumberEntityId smownerid=".$smownerid." nmbown=".$db->num_rows($resisown));
		if($db->num_rows($resisown)){
		    $resEntity['phone_crm_extension'][] = "\"".$db->query_result($resisown,0,'phone_crm_extension')."\"";
		}
	    }
	    return $resEntity;
	} else {
	    return false;
	}
    }

//FusionPBX end

//FusionPBX start
    public static function lookUpRelatedWithNumberLocal($from){
        $db = PearDatabase::getInstance();
	$sqlquery="SELECT id,first_name,last_name FROM vtiger_users WHERE deleted=0 AND phone_crm_extension='".$from."'";
        $result = $db->pquery($sqlquery);
        if($db->num_rows($result)){
                $data['id'] = $db->query_result($result, 0, 'id');
                $data['name'] = $db->query_result($result, 0, 'first_name')." ".$db->query_result($result, 0, 'last_name');
                $data['setype'] = 'Users';
                return $data;
        }
            else
                return;
    }
//FusionPBX end
    

     /**
      * Function to user details with number
      * @param <string> $number
      */
    public static function getUserInfoWithNumber($number){
        $db = PearDatabase::getInstance();
        if(empty($number)){
            return false;
        }
        $query = PBXManager_Record_Model::buildSearchQueryWithUIType(11, $number, 'Users');
        $result = $db->pquery($query, array());
        if($db->num_rows($result) > 0 ){
            $user['id'] = $db->query_result($result, 0, 'id');
            $user['name'] = $db->query_result($result, 0, 'name');
            $user['setype'] = 'Users';
            return $user;
        }
        return;
    }    

//FusionPBX begin
    public static function getUserInfoAdmin(){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $db = PearDatabase::getInstance();
        //$query = PBXManager_Record_Model::buildSearchQueryWithUIType(11, $number, 'Users');
	$query = "SELECT id,user_name FROM vtiger_users WHERE is_admin='on' AND deleted=0";
        $result = $db->pquery($query, array());
	//$logFusion->debug("PBX getUserInfoAdmin nmb=".$db->num_rows($result)." query=".$query);
        if($db->num_rows($result) > 0 ){
            $user['id'] = $db->query_result($result, 0, 'id');
            $user['name'] = $db->query_result($result, 0, 'name');
            $user['setype'] = 'Users';
            return $user;
        }
        return;
    }    
//FusionPBX end

    
    // Because, User is not related to crmentity 
    public function buildSearchQueryWithUIType($uitype, $value, $module){
        if (empty($value)) {
            return false;
        }
        
        $cachedModuleFields = VTCacheUtils::lookupFieldInfo_Module($module);
        if ($cachedModuleFields === false) {
            getColumnFields($module); // This API will initialize the cache as well
            // We will succeed now due to above function call
            $cachedModuleFields = VTCacheUtils::lookupFieldInfo_Module($module);
        }

        $lookuptables = array();
        $lookupcolumns = array();
        foreach ($cachedModuleFields as $fieldinfo) {
            if (in_array($fieldinfo['uitype'], array($uitype))) {
                $lookuptables[] = $fieldinfo['tablename'];
                $lookupcolumns[] = $fieldinfo['columnname'];
            }
        }

        $entityfields = getEntityField($module);
        $querycolumnnames = implode(',', $lookupcolumns);
        $entitycolumnnames = $entityfields['fieldname'];

        $query = "select id as id, $querycolumnnames, $entitycolumnnames as name ";
        $query .= " FROM vtiger_users";

        if (!empty($lookupcolumns)) {
            $query .=" WHERE deleted=0 AND ";
            $i = 0;
            $columnCount = count($lookupcolumns);
            foreach ($lookupcolumns as $columnname) {
                if (!empty($columnname)) {
                    if ($i == 0 || $i == ($columnCount))
                        $query .= sprintf("%s = '%s'", $columnname, $value);
                    else
                        $query .= sprintf(" OR %s = '%s'", $columnname, $value);
                    $i++;
                }
            }
         }
         return $query;
    }

    public static function getUserNumbers(){
	//$logFusion =& LoggerManager::getLogger('fusion');
        $numbers = null;
        $db = PearDatabase::getInstance();
        $query = 'SELECT id, phone_crm_extension FROM vtiger_users';
        $result = $db->pquery($query, array());
        $count = $db->num_rows($result);
        for($i=0; $i<$count; $i++){
            $number = $db->query_result($result, $i, 'phone_crm_extension');
            $userId = $db->query_result($result, $i, 'id');
            if($number) {
		//$logFusion->debug("PBX getUserNumbers userId=".$userId." number=".$number);
                $numbers[$userId] = $number;
	    }
        }
        return $numbers;
    }
}
?>
