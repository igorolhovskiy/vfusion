<?php

/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class PBXManager_PBXManager_Controller {

    function getConnector() {
        return new PBXManager_PBXManager_Connector;
    }

    /**
     * Function to process the request
     * @params <array> call details
     * return Response object
     */
    function process($request) {
	//$logFusion =& LoggerManager::getLogger('fusion');
//PBXFusion begin
	$request=$this->mapRequestVars($request);
        $mode = $request->get('callstatus');
	//$logFusion->debug("PBX CONTROLLER PROCESS mode=".$mode);
//PBXFusion end
        switch ($mode) {
	//PBXFusion begin
    	    case "CallStart" :
                $this->processCallStart($request);
                break;
	//PBXFusion end
            case "StartApp" :
                $this->processStartupCallFusion($request);
                break;
            case "DialAnswer" :
                $this->processDialCallFusion($request);
                break;
            case "Record" :
                $this->processRecording($request);
                break;
            case "EndCall" :
                $this->processEndCallFusion($request);
                break;
            case "Hangup" :
                $callCause = $request->get('causetxt');
                if ($callCause == "null") {
                    break;
                }
                $this->processHangupCall($request);
                break;
        }
    }

//FusionPBX begin
    /** 
    * 	API request variables mapping
    *   uuid -> callUUID
    *   callstatus -> callstatus ( callstartother if CRM User is not found )
    *	timestamp -> StartTime 	          
    *	direction -> direction (not exist) 
    * 	callerIdNumber -> src=>number
    *	callerIdName -> src=>name (not exist)
    *	dst -> dst ( not exist )
    */

    function mapRequestVars($request) {
	$callstatus=$request->get('callstatus');
	$uuid=$request->get('uuid');
	$request->set("callUUID",$uuid);
	$date = new DateTime();
	$date->setTimestamp($request->get('timestamp'));
	$request->set("StartTime",$date->format('Y-m-d H:i:s'));
	if ($request->get('callstate')) 
	    $request->set('callstatus',$request->get('callstate'));
	switch ($callstatus) {
	    case "call_start" :
		$request->set("callstatus",'CallStart');
		$src=$request->get('src');
		$request->set("callerIdNumber",$src['number']);
		$request->set("callerIdName",$src['name']);    
	    break;
	    case "call_ringing" :	    
		$request->set("callstatus",'StartApp');			
    	    break;
	    case "call_answered" :	    
		$request->set("callstatus",'DialAnswer');			
    	    break;
	    case "call_end" :
		if ($request->get('src')) {
		    $src=$request->get('src');
		    $request->set("callerIdNumber",$src['number']);
		    $request->set("callerIdName",$src['name']);    
		}	    
		$request->set("callstatus",'EndCall');			
    	    break;

	}
	
	return $request;
    }


    /**
     * Function to process Incoming call request
     * @params <array> incoming call details
     * return Response object
     */

//FusionPBX begin
    function processCallStart($request) {
    //$logFusion =& LoggerManager::getLogger('fusion');	
        $connector = $this->getConnector();
	$direction = $request->get('direction');
        $callerNumber = $request->get('callerIdNumber');
	$request->set('callstatus','callstart');
	$request->set('Direction', $direction);
	if ($direction == 'inbound' || $direction == 'outbound') {    	    	    
	    if ($direction=='oubound') {
    		$userInfo = PBXManager_Record_Model::getUserInfoWithNumber($callerNumber);
		if ($request->get('callerIdNumber')) {
            	    $to = $request->get('callerIdNumber');
        	} else if ($request->get('callerIdName')) {
            	    $to = $request->get('callerIdName');
        	} 
        	$request->set('to', $to);
        	$customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumber($to);
		if ($userInfo) 
		    $connector->handleCallStartFusion($request, $userInfo, $customerInfo);
		else {
		    //CRM User is not found 
		    $param['status'] = "404 Not Found";
		    $param['message'] = "CRM User Not Found";	    
		    $connector->respondToIncomingCallStart($param);
		}
	    } else {
	    //inbound call
		//$logFusion->debug("PBX PROCESSCALLSTART");
        	$customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumber($request->get('callerIdNumber'));
		//$logFusion->debug("PBX PROCESSCALLSTART customerInfo_id=".$customerInfo['id']);
        	$request->set('from', $request->get('callerIdNumber'));
		//CRM User is unknown now 	
		$userInfo = PBXManager_Record_Model::getUserInfoAdmin();	    
		$connector->handleCallStartFusion($request, $userInfo, $customerInfo);
	    }	    
	} else {
	//local call
		$request->set('Direction', 'local');
		//$logFusion->debug("PBX processCallStart Local callerIdNumber=".$request->get('callerIdNumber')." callerNumber=".$callerNumber);	    
        	$customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumberLocal($request->get('callerIdNumber'));
		$userInfo = PBXManager_Record_Model::getUserInfoWithNumber($callerNumber);
		if (!$userInfo)
		    $userInfo = PBXManager_Record_Model::getUserInfoAdmin();	    
		$connector->handleCallStartFusion($request, $userInfo, $customerInfo);
	}
    }
//FusionPBX end


//FusionPBX begin
    function processStartupCallFusion($request) {
	//$logFusion =& LoggerManager::getLogger('fusion');
	//$logFusion->debug("PBX processStartupCallFusion callstatus=".$request->get('callstatus')." uuid=".$request->get('uuid')." CallUUID=".$request->get('callUUID'));
	$connector = $this->getConnector();
	$connector->handleStartupCallFusion($request);	
    }

//FusionPBX end


    function processStartupCall($request) {
        $connector = $this->getConnector();

        $temp = $request->get('channel');
        $temp = explode("-", $temp);
        $temp = explode("/", $temp[0]);

        $callerNumber = $request->get('callerIdNumber');
        $userInfo = PBXManager_Record_Model::getUserInfoWithNumber($callerNumber);

        if (!$userInfo) {
            $callerNumber = $temp[1];
            if (is_numeric($callerNumber)) {
                $userInfo = PBXManager_Record_Model::getUserInfoWithNumber($callerNumber);
            }
        }

        if ($userInfo) {
            // Outbound Call
            $request->set('Direction', 'outbound');

            if ($request->get('callerIdNumber') == $temp[1]) {
                $to = $request->get('callerIdName');
            } else if ($request->get('callerIdNumber')) {
                $to = $request->get('callerIdNumber');
            } else if ($request->get('callerId')) {
                $to = $request->get('callerId');
            }

            $request->set('to', $to);
            $customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumber($to);
            $connector->handleStartupCall($request, $userInfo, $customerInfo);
        } else {
            // Inbound Call
            $request->set('Direction', 'inbound');
            $customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumber($request->get('callerIdNumber'));
            $request->set('from', $request->get('callerIdNumber'));
            $connector->handleStartupCall($request, $userInfo, $customerInfo);
        }
    }

    /**
     * Function to process Dial call request
     * @params <array> Dial call details
     * return Response object
     */
    function processDialCallFusion($request) {
        $connector = $this->getConnector();
        $connector->handleDialCallFusion($request);
    }

    /**
     * Function to process EndCall event
     * @params <array> Dial call details
     * return Response object
     */
    function processEndCallFusion($request) {
        $connector = $this->getConnector();
        $connector->handleEndCallFusion($request);
    }

    /**
     * Function to process Hangup call request
     * @params <array> Hangup call details
     * return Response object
     */
    function processHangupCall($request) {
        $connector = $this->getConnector();
        $connector->handleHangupCall($request);
    }

    /**
     * Function to process recording
     * @params <array> recording details
     * return Response object
     */
    function processRecording($request) {
        $connector = $this->getConnector();
        $connector->handleRecording($request);
    }

}