<?php

/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

require_once 'include/utils/utils.php';
require_once 'vtlib/Vtiger/Net/Client.php';

class PBXManager_PBXManager_Connector {

    private static $SETTINGS_REQUIRED_PARAMETERS = array('webappurl' => 'text','outboundcontext' => 'text', 'outboundtrunk' => 'text' , 'vtigersecretkey' => 'text');
    private static $RINGING_CALL_PARAMETERS = array('From' => 'callerIdNumber', 'SourceUUID' => 'callUUID', 'Direction' => 'Direction');
//FusionPBX begin
    private static $CALLSTART_CALL_PARAMETERS = array('SourceUUID' => 'callUUID', 'Direction' => 'direction');
//FusionPBX end
    private static $NUMBERS = array();
    private $webappurl;
    private $outboundcontext, $outboundtrunk;
    private $vtigersecretkey;
    private $fusionipaddr;
    const RINGING_TYPE = 'ringing';
    const ANSWERED_TYPE = 'answered';
    const HANGUP_TYPE = 'hangup';
    const RECORD_TYPE = 'record';
    
    const INCOMING_TYPE = 'inbound';
    const OUTGOING_TYPE = 'outbound';
    const USER_PHONE_FIELD = 'phone_crm_extension';

    function __construct() {
        $serverModel = PBXManager_Server_Model::getInstance();
        $this->setServerParameters($serverModel);
    }

    /**
     * Function to get provider name
     * returns <string>
     */
    public function getGatewayName() {
        return 'PBXManager';
    }

    public function getPicklistValues($field) {
    }

    public function getServer() {
        return $this->webappurl;
    }

    public function getOutboundContext() { 
        return $this->outboundcontext; 
    } 

    public function getOutboundTrunk() { 
        return $this->outboundtrunk; 
    }
    
    public function getVtigerSecretKey() {
        return $this->vtigersecretkey;
    }

//FusionPBX begin
    public function getFusionIP() {
	return $this->fusionipaddr;
    }

//FusionPBX end    

    public function getXmlResponse() {
        header("Content-type: text/xml; charset=utf-8");
        $response = '<?xml version="1.0" encoding="utf-8"?>';
        $response .= '<Response><Authentication>';
        $response .= 'Failure';
        $response .= '</Authentication></Response>';
        return $response;
    }

    /**
     * Function to set server parameters
     * @param <array>  authdetails
     */
    public function setServerParameters($serverModel) {
        $this->webappurl = $serverModel->get('webappurl');
        $this->outboundcontext = $serverModel->get('outboundcontext'); 
        $this->outboundtrunk = $serverModel->get('outboundtrunk'); 
        $this->vtigersecretkey = $serverModel->get('vtigersecretkey');
	$fusionipaddr=gethostbyname(parse_url($this->webappurl,PHP_URL_HOST));
	if ($fusionipaddr) {
	    $this->fusionipaddr=$fusionipaddr;
	}
	
    }

    /**
     * Function to get Settings edit view params
     * returns <array>
     */
    public function getSettingsParameters() {
        return self::$SETTINGS_REQUIRED_PARAMETERS;
    }

    protected function prepareParameters($details, $type) {
        switch ($type) {
            case 'ringing':
                foreach (self::$RINGING_CALL_PARAMETERS as $key => $value) {
                    $params[$key] = $details->get($value);
                }
                $params['GateWay'] = $this->getGatewayName();
                break;
        }
        return $params;
    }



//FusionPBX begin
    protected function prepareParametersCallStart($details) {
        foreach (self::$CALLSTART_CALL_PARAMETERS as $key => $value) {
                    $params[$key] = $details->get($value);
        }
                $params['GateWay'] = $this->getGatewayName();        
        return $params;
    }
//FusionPBX end


    /**
     * Function to handle the dial call event
     * @param <Vtiger_Request> $details 
     */
//FusionPBX begin
    public function handleDialCallFusion($details) {
	//$logFusion =& LoggerManager::getLogger('fusion');
        $callid = $details->get('callUUID');
        $answeredby = $details->get('number');
        //$caller = $details->get('callerid1');
	$recordModel1 = PBXManager_Record_Model::getInstanceBySourceUUID($callid);
	if ($details->get('direction')) {
	    $direction = $details->get('direction');
	} else {
	    $direction = $recordModel1->get('direction');
	}
	//$logFusion->debug("PBX handleDialCallFusion direction=".$direction." callid=".$callid." request direction=".$details->get('direction'));
        // For Inbound call, answered by will be the user, we should fill the user field
	if ($direction == self::INCOMING_TYPE) {
	    	$numbersCRM=PBXManager_Record_Model::getUserNumbers();
		$userid=array_search($answeredby,$numbersCRM);
    		//$logFusion->debug("PBX handleDialCallFusion direction=".$direction." callid=".$callid." userid=".$userid." answeredby=".$answeredby);
		if ($userid) {
		    $recordModel=PBXManager_Record_Model::getInstanceBySourceUUIDUserID($callid,$userid);		    
		} else {
		    $sendparams['status']="404 Not Found";
		    $sendparams['message']="UserID Not Found";
		    $this->respondToIncomingCallStart($sendparams);		    
		    return;
		}		
	} else {
	    $recordModel = PBXManager_Record_Model::getInstanceBySourceUUID($callid);
	}
	
	if ($details->get('direction')) {
	    $direction = $details->get('direction');
	} else {
	    $direction = $recordModel->get('direction');
	}
    	//$logFusion->debug("PBX handleDialCallFusion L2 direction=".$direction." callid=".$callid." userid=".$userid." answeredby=".$answeredby);

        if ($direction == self::INCOMING_TYPE) {
            // For Incoming call, we should fill the user field if he answered that call 
            $user = PBXManager_Record_Model::getUserInfoWithNumber($answeredby);
            $params['user'] = $user['id'];
	    $params['callstatus2'] = "answered elsewhere";
            $recordModel->updateAssignedUser($user['id']);
	    //$logFusion->debug("PBX handleDialCallFusion L2 direction=".$direction." params_user=".$params['user']);
        } else {
            //$user = PBXManager_Record_Model::getUserInfoWithNumber($caller);
	    $userid=$recordModel->get('user');
            if ($userid) {
                $recordModel->updateAssignedUser($userid);
            }
	    if ($direction=='outbound') {
		$customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumberCallStart($answeredby);
		if ($customerInfo) {
		    $params['customer']=$customerInfo['id'];
		    $params['customertype']=$customerInfo['customertype'];
		}
		$params['customernumber']=$answeredby;
	    } elseif ($direction=='local') {
		    $numbersCRM=PBXManager_Record_Model::getUserNumbers();
		    $useridlocal=array_search($answeredby,$numbersCRM);
		    if(!$useridlocal) {
			$params['customernumber']=$answeredby;
		    }
	    }
        }

	$params['starttime']=$details->get('StartTime');

    	$params['callstatus1'] = "in-progress";
	$params['calluuid']=$callid;

	$params['crmid']=$recordModel->get('pbxmanagerid');

	$params['direction']=$direction;
	//$logFusion->debug("PBX handleDialCallFusion crmid=".$params['crmid']);
        $recordModel->updateCallDetailsDialCall($params);
	$sendparams['status']="200 OK";
	$sendparams['message']="Status in-progress";
	$this->respondToIncomingCallStart($sendparams);		    

    }


//FusionPBX end



    public function handleDialCall($details) {
        $callid = $details->get('callUUID');

        $answeredby = $details->get('callerid2');
        $caller = $details->get('callerid1');

        // For Inbound call, answered by will be the user, we should fill the user field
        $recordModel = PBXManager_Record_Model::getInstanceBySourceUUID($callid);
        $direction = $recordModel->get('direction');
        if ($direction == self::INCOMING_TYPE) {
            // For Incoming call, we should fill the user field if he answered that call 
            $user = PBXManager_Record_Model::getUserInfoWithNumber($answeredby);
            $params['user'] = $user['id'];
            $recordModel->updateAssignedUser($user['id']);
        } else {
            $user = PBXManager_Record_Model::getUserInfoWithNumber($caller);
            if ($user) {
                $params['user'] = $user['id'];
                $recordModel->updateAssignedUser($user['id']);
            }
        }

        $params['callstatus'] = "in-progress";
        $recordModel->updateCallDetails($params);
    }
    
    /**
     * Function to handle the EndCall event
     * @param <Vtiger_Request> $details 
     */

//FusionPBX begin

    public function handleEndCallFusion($details) {
	//$logFusion =& LoggerManager::getLogger('fusion');
        $callid = $details->get('callUUID');
	$direction=$details->get('direction');
	$status=$details->get('status');
        $records = PBXManager_Record_Model::getInstanceBySourceUUIDFusionEndCall($callid);        
	$countrecords=count($records);	
	//$logFusion->debug("PBX handleEndCallFusion countrecords=".$countrecords." direction=".$direction); 
	$sendparam['status']='200 OK';
	for($i=0;$i<$countrecords;$i++) {
	    //$logFusion->debug("PBX handleEndCallFusion countrecords=".$countrecords." direction=".$direction." i=".$i); 
	    if ($direction=='inbound' || $direction=='outbound' || $direction=='local' ) {
		$recordModel=$records[$i];
		    //$logFusion->debug("PBX handleEndCallFusion L1 countrecords=".$countrecords." direction=".$direction." i=".$i); 
		$params=array();
		if ($details->get('src')) {
		    if ($direction=='local') {
			if (!$recordModel->get('customernumber')) {
			    $params['customernumber']=$details->get('callerIdNumber');
			}
		    } else {
			////$logFusion->debug("PBX handleEndCallFusion L2 countrecords=".$countrecords." direction=".$direction." i=".$i); 
			$customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumberCallStart($details->get('callerIdNumber'));
			//$logFusion->debug("PBX handleEndCallFusion customerInfo_id=".$customerInfo['id']." callerIdNumber=".$details->get('callerIdNumber'));
			if ($customerInfo) {
			    $params['Customer'] = $customerInfo['id'];
    			    $params['CustomerType'] = $customerInfo['setype'];
			}
		    }
		}		

		//$params['pbxmanagerid']=$recordModel->get('pbxmanagerid');
		//$logFusion->debug("PBX handleEndCallFusion status=".$details->get('status')." callstatus=".$recordModel->get('callstatus')." pbxmanagerid=".$recordModel->get('pbxmanagerid'));
		if($recordModel->get('callstatus')=='ringing' || $recordModel->get('callstatus')=='callstart')  {
	    	    $params['endtime'] = $details->get('StartTime');		
	    	    switch($status) {
	    		case 'busy':
	    		    $params['callstatus']='busy';		
	    		break;
	    		case 'no answer':
	    		    $params['callstatus']='no-answer';
	    		break;
	    		case 'failed':
	    		    $params['callstatus']='failed';
	    		break;    
	    		case 'answered':
	    		    $params['callstatus']='answered elsewhere';
	    		break;
	    		default:
	    		    $params['callstatus']=$status;
	    		break;    			
	    	    }	    
	    
	    	} elseif ($recordModel->get('callstatus')=='in-progress') {
			//$logFusion->debug("PBX handleEndCallFusion INPROGRESS status=".$details->get('status')." recording=".$details->get('recording')); 
	    		if ($details->get('status')=='answered') {
    	    		    $params['endtime'] = $details->get('StartTime');
			    $timepar=$details->get('time');	
    	    		    $params['totalduration'] = $timepar['duration'];
    	    		    $params['billduration'] =  $timepar['answered'];
	    		    $params['callstatus']='completed';
	    		    if ($details->get('recording')!='') {
	    			$recordingurl = $this->getRecordFusionPBX($details,$recordModel);
				$params['recordingurlfusion'] = $details->get('recording');
	    			if ($recordingurl) {
	    			    $params['recordingurl']=$recordingurl;
	    			} else {
	    			    $sendparam['status']='404 Not Found';
	    			    $sendparam['message']='Recording not found';
	    			} 
	    		    }			    	    			    
	    		} else {
	    		    switch($status) {
	    			case 'busy':
	    			    $params['callstatus']='busy';		
	    			break;
	    			case 'no answer':
	    			    $params['callstatus']='no-answer';
	    			break;
	    			case 'failed':
	    			    $params['callstatus']='failed';
	    			break;    
	    			default:
	    			    $params['callstatus']=$status;
	    			break;    			
	    		    }
	    		}				    
	    	} elseif ($recordModel->get('callstatus')=='completed') {
		    $this->respondToIncomingCallStart($sendparam);
		    return;
		}	    	    	
	    } 
	    //$logFusion->debug("PBX handleEndCallFusion params_callstatus=".$params['callstatus']." pbxmanagerid=".$recordModel->get('pbxmanagerid'));  
	    $recordModel->updateCallDetailsFusion($params);
	}
	$this->respondToIncomingCallStart($sendparam);    
    }



//FusionPBX end





    public function handleEndCall($details) {
        $callid = $details->get('callUUID');
        $recordModel = PBXManager_Record_Model::getInstanceBySourceUUID($callid);
        
        $params['starttime'] = $details->get('starttime');
        $params['endtime'] = $details->get('endtime');
        $params['totalduration'] = $details->get('duration');
        $params['billduration'] = $details->get('billableseconds');

        $recordModel->updateCallDetails($params);
    }
    
    /**
     * Function to handle the hangup call event
     * @param <Vtiger_Request> $details 
     */
    public function handleHangupCall($details) {
        $callid = $details->get('callUUID');
        $recordModel = PBXManager_Record_Model::getInstanceBySourceUUID($callid);
        $hangupcause = $details->get('causetxt');
        
        switch ($hangupcause) {
            // If call is successfull
            case 'Normal Clearing':
                $params['callstatus'] = 'completed';
                if($details->get('HangupCause') == 'NO ANSWER') {
                    $params['callstatus'] = 'no-answer';
                }
                break;
            case 'User busy' :
                $params['callstatus'] = 'busy';
                break;
            case 'Call Rejected':
                $params['callstatus'] = 'busy';
                break;
            default :
                $params['callstatus'] = $hangupcause;
                break;
        }
        
        if($details->get('EndTime') && $details->get('Duration')) {
            $params['endtime'] = $details->get('EndTime');
            $params['totalduration'] = $details->get('Duration');
        }
        
        $recordModel->updateCallDetails($params);
    }
    
    /**
     * Function to handle record event
     * @param <Vtiger_Request> $details 
     */
    public function handleRecording($details) {
        $callid = $details->get('callUUID');
        $recordModel = PBXManager_Record_Model::getInstanceBySourceUUID($callid);
        $params['recordingurl'] = $details->get('recordinglink');
        $recordModel->updateCallDetails($params);
    }
    
    /**
     * Function to handle AGI event
     * @param <Vtiger_Request> $details 
     */

    //FusionPBX begin
    public function handleCallStartFusion($details, $userInfo, $customerInfo) {
	//$logFusion =& LoggerManager::getLogger('fusion');
        global $current_user;
        $params = $this->prepareParametersCallStart($details);
        $direction = $details->get('direction');
        // To add customer and user information in params
	if ($direction != 'local') {
    	    $params['Customer'] = $customerInfo['id'];
    	    $params['CustomerType'] = $customerInfo['setype'];
	} else if ( $direction == 'local' && $details->get('callerIdNumber')) {
	    $params['CustomerNumber']=$details->get('callerIdNumber');
	}
        $params['User'] = $userInfo['id']; 
	//$logFusion->debug("PBX handleCallStart userInfo_id=".$userInfo['id']." starttime=".$details->get('StartTime'));	
	if ($direction != 'local') {
    	    if ($details->get('from')) {
        	$params['CustomerNumber'] = $details->get('from');
    	    } else if ($details->get('to')) {
        	$params['CustomerNumber'] = $details->get('to');
    	    }
        }
	//$logFusion->debug("PBX handleCallStartFusion direction=".$direction." callerIdNumber=".$details->get('callerIdNumber')." params cust number=".$params['CustomerNumber']);
        $params['starttime'] = $details->get('StartTime');
        $params['callstatus'] = "callstart";
        $user = CRMEntity::getInstance('Users');
        $current_user = $user->getActiveAdminUser();
        $recordModel = PBXManager_Record_Model::getCleanInstance();
        $recordModel->saveRecordWithArrray($params);
	$sendparam['status']="200 OK";
	$sendparam['message']="OK";	
	if ($direction == 'inbound') {
	    if ($customerInfo) {
		$callerdata=PBXManager_Record_Model::lookUpRelatedWithNumberEntityId($customerInfo['id'],$customerInfo['setype']);
		if ($callerdata) {
		    //$logFusion->debug("PBX HANDLECALLSTART callerdata_lastname=".$callerdata['lastname']);
		    $callername=$callerdata['firstname']." ".$callerdata['lastname'];
		    $mgrphone='['.implode(",", $callerdata['phone_crm_extension']).']';
		    $sendparam['data']="{ \"name\":\"".$callername."\", \"company\":\"".$callerdata['company']."\",\"manager\":".$mgrphone." }";
		}
	    }	    
	} 
	$this->respondToIncomingCallStart($sendparam);
    }
    
    //FusionPBX end


    //FusionPBX begin
    public function handleStartupCallFusion($details) {
        global $current_user;
	$db = PearDatabase::getInstance();

	//$logFusion =& LoggerManager::getLogger('fusion');
	
        $user = CRMEntity::getInstance('Users');
        $current_user = $user->getActiveAdminUser();

	//$logFusion->debug("PBX handleStartupCallFusion callUUID=".$details->get('callUUID'));
	$recordModel=PBXManager_Record_Model::getInstanceBySourceUUID($details->get('callUUID'));
	if (!$recordModel) {
	    $sendparams['status']="404 Not Found";
	    $sendparams['message']="CallUUID Not Found";
	    $this->respondToIncomingCallStart($sendparams);
	    return;
	}
	$numbersCRM=PBXManager_Record_Model::getUserNumbers();
	$number=$details->get('number');
	$userid=array_search($number,$numbersCRM);
        $params = $this->prepareParametersCallStart($details);
	$params['starttime'] = $details->get('StartTime');
	$params['Direction'] = $recordModel->get('direction');
	if ($recordModel->get('direction')=='local' && $number && $userid) {
	    //$params['customernumber']=$number;
	    $params['customertype']='Users';
	    //$logFusion->debug("PBX handleStartupCallFusion Local number=".$params['customernumber']);
	} else if (!$userid){
	    //$logFusion->debug("PBX handleStartupCallFusion Local customernumber=".$recordModel->get('customernumber'));
	    $testnumber=$recordModel->get('customernumber');
	    if ($testnumber) {
		$userid=array_search($testnumber,$numbersCRM);
		$params['flag']='close';
	    } else if (!$userid) {	    	    
		//Callee user phone number not found in CRM
		$query="UPDATE vtiger_crmentity SET deleted=1 WHERE crmid=?";
		$db->pquery($query,array($recordModel->get('pbxmanagerid')));
		//$logFusion->debug("PBX handleStartupCallFusion Local number=".$number." userid=".$userid." direction=".$recordModel->get('direction'));
		return;
	    }   
	} 
	//$logFusion->debug("PBX handleStartupCallFusion L2 callUUID=".$details->get('callUUID')." userid=".$userid." callstatus=".$recordModel->get('callstatus'));

	if ($userid) {
	    $params['User']=$userid;
	} else {
	    $sendparams['status']="404 Not Found";
	    $sendparams['message']="User CRM Number Not Found";
	    $this->respondToIncomingCallStart($sendparams);
	    return;
	}	
	//$logFusion->debug("PBX handleStartupCallFusion callstatus=".$recordModel->get('callstatus')." direction=".$recordModel->get('direction'));
	if ($recordModel->get('callstatus')=='callstart') {    
	    $params['callstatus'] = "ringing";
	    //$params['pbxmanagerid'] = $recordModel->get('pbxmanagerid');	    
	    $recordModel->updateCallDetailsFusion($params);	    
            $recordModel->updateAssignedUser($userid);
	    $sendparams['message']="User is ringing";
	} else {
	//create new pbxmanager entity
	    $recordModel2 = PBXManager_Record_Model::getCleanInstance();
	    $params['Direction']=$recordModel->get('direction');
	    $params['SourceUUID'] = $recordModel->get('sourceuuid');
	    $params['callstatus'] = "ringing";	    
	    $params['customer'] = $recordModel->get('customer');
	    $params['customernumber'] = $recordModel->get('customernumber');
	    $params['customertype'] = $recordModel->get('customertype');
    	    $recordModel2 = $recordModel2->saveRecordWithArrray($params);	    
	    $recordModel2->updateAssignedUserFusion($userid);
	    //$logFusion->debug("PBX handleStartupCallFusion L3 callUUID=".$details->get('callUUID')." userid=".$userid." callstatus=".$recordModel->get('callstatus')." sourceuuid=".$recordModel->get('sourceuuid')." userid=".$userid." direction=".$recordModel->get('direction')." recordModel2_pbxmanagerid=".$recordModel2->getId());
	    $sendparams['message']="User is new ringing";
    	}
	    $sendparams['status']="200 OK";
	    $this->respondToIncomingCallStart($sendparams);	
    }
    //FusionPBX end

    public function handleStartupCall($details, $userInfo, $customerInfo) {
        global $current_user;
        $params = $this->prepareParameters($details, self::RINGING_TYPE);
        $direction = $details->get('direction');

        // To add customer and user information in params
        $params['Customer'] = $customerInfo['id'];
        $params['CustomerType'] = $customerInfo['setype'];
        $params['User'] = $userInfo['id']; 

        if ($details->get('from')) {
            $params['CustomerNumber'] = $details->get('from');
        } else if ($details->get('to')) {
            $params['CustomerNumber'] = $details->get('to');
        }
        
        $params['starttime'] = $details->get('StartTime');
        $params['callstatus'] = "ringing";
        $user = CRMEntity::getInstance('Users');
        $current_user = $user->getActiveAdminUser();
        
        $recordModel = PBXManager_Record_Model::getCleanInstance();
        $recordModel->saveRecordWithArrray($params);
        
        if ($direction == self::INCOMING_TYPE)
            $this->respondToIncomingCall($details);
        else
            $this->respondToOutgoingCall($params['CustomerNumber']);
    }
    
    /**
     * Function to respond for incoming calls
     * @param <Vtiger_Request> $details 
     */

//FusionPBX begin
    public function respondToIncomingCallStart($params) {
        global $current_user;
        self::$NUMBERS = PBXManager_Record_Model::getUserNumbers();
        
        header("Content-Type: application/json");
	$response = "\"status\":\"".$params['status']."\","."\"message\":\"".$params['message']."\"";
	if ($params['data']) {
	    $response .= ", \"data\": ".$params['data'];
	}
        echo $response;
    }
//FusionPBX end



    public function respondToIncomingCall($details) {
        global $current_user;
        self::$NUMBERS = PBXManager_Record_Model::getUserNumbers();
        
        header("Content-type: text/xml; charset=utf-8");
        $response = '<?xml version="1.0" encoding="utf-8"?>';
        $response .= '<Response><Dial><Authentication>';
        $response .= 'Success</Authentication>';

        if (self::$NUMBERS) {

            foreach (self::$NUMBERS as $userId => $number) {
                $userInstance = Users_Privileges_Model::getInstanceById($userId);
                $current_user = $userInstance;
                $callPermission = Users_Privileges_Model::isPermitted('PBXManager', 'ReceiveIncomingCalls');

                if ($number != $details->get('callerIdNumber') && $callPermission) {
                   if(preg_match("/sip/", $number) || preg_match("/@/", $number)) {
                       $number = trim($number, "/sip:/");
                       $response .= '<Number>SIP/';
                       $response .= $number;
                       $response .= '</Number>';
                   }else {
                       $response .= '<Number>SIP/';
                       $response .= $number;
                       $response .= '</Number>';
                   }
                }
            }
        }else {
            $response .= '<ConfiguredNumber>empty</ConfiguredNumber>';
            $date = date('Y/m/d H:i:s');
            $params['callstatus'] = 'no-answer';
            $params['starttime'] = $date;
            $params['endtime'] = $date;
            $recordModel = PBXManager_Record_Model::getInstanceBySourceUUID($details->get('callUUID'));
            $recordModel->updateCallDetails($params);
        }
        $response .= '</Dial></Response>';
        echo $response;
    }
    
    /**
     * Function to respond for outgoing calls
     * @param <Vtiger_Request> $details 
     */
    public function respondToOutgoingCall($to) {
        header("Content-type: text/xml; charset=utf-8");
        $response = '<?xml version="1.0" encoding="utf-8"?>';
        $response .= '<Response><Dial><Authentication>';
        $response .= 'Success</Authentication>';
        $numberLength = strlen($to);
        
        if(preg_match("/sip/", $to) || preg_match("/@/", $to)) {
            $to = trim($to, "/sip:/");
            $response .= '<Number>SIP/';
            $response .= $to;
            $response .= '</Number>';
        }else {
            $response .= '<Number>SIP/';
            $response .= $to;
            if($numberLength > 5) $response .= '@'.  $this->getOutboundTrunk(); 
            $response .= '</Number>';
        }
        
        $response .= '</Dial></Response>';
        echo $response;
    }

    /**
     * Function to make outbound call 
     * @param <string> $number (Customer)
     * @param <string> $recordid
     */

    function call($number, $record) {
        //$logFusion =& LoggerManager::getLogger('fusion');
	$user = Users_Record_Model::getCurrentUserModel();
        $extension = $user->phone_crm_extension;

        $webappurl = $this->getServer();
        $context = $this->getOutboundContext();
	$outboundtrunk = $this->getOutboundTrunk(); 
        $vtigerSecretKey = $this->getVtigerSecretKey();
	//$logFusion->debug("PBX Click2Dial webappurl=".$webappurl." context=".$context." outboundtrunk=".$outboundtrunk);
	
        $serviceURL  =  $webappurl."/".$context;
        $serviceURL .= '?key=' . urlencode($outboundtrunk) . '&';
        $serviceURL .= 'src=' . urlencode($extension) . '&';
        $serviceURL .= 'dest=' . urlencode($number);
	
	//$logFusion->debug("PBX Click2Dial serviceURL=".$serviceURL);
        $httpClient = new Vtiger_Net_Client($serviceURL);
        $response = $httpClient->doPost(array());
        $response = trim($response); 

        if ($response == "Error" || $response == "" || $response == null
            || $response == "Authentication Failure" ) {
            return false;
        }
        return true;
    }

    public function getRecordFusionPBX($request,$recordModel) {
	//$logFusion =& LoggerManager::getLogger('fusion');
	$recordingurl=$request->get('recording');
	$sourceuuid=$request->get('callUUID');
	$calleduser=$recordModel->get('user');
	$curlRecord = curl_init($recordingurl);
        curl_setopt($curlRecord, CURLOPT_HEADER, 1);
        curl_setopt($curlRecord, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlRecord, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($curlRecord, CURLOPT_FOLLOWLOCATION, true);
        
	$curlresponse = curl_exec($curlRecord);
	$requestInfo = curl_getinfo($curlRecord);
	if( $requestInfo['http_code'] == 200 ) {
	    //$logFusion->debug("PBX getRecordFusionPBX recordingurl=".$recordingurl." sourceuuid=".$sourceuuid." http_code=".$requestInfo['http_code']." calleduser=".$calleduser);
            $headerSize = $requestInfo['header_size'];
            $headerContent = substr($curlresponse, 0, $headerSize);
            $bodyContent = substr($curlresponse, $headerSize);
	    $datef = date("Y-m-d");
	    $filepathvoice='voices/'.$datef;
	    if(!file_exists('voices/')) {
                    mkdir('voices',0777,true);
	    }
            if(!file_exists($filepathvoice)) {
                    mkdir($filepathvoice,0777,true);
            }
	    $purl=parse_url($recordingurl, PHP_URL_PATH);
	    $urlinfo = new SplFileInfo($purl);
	    $urlext=$urlinfo->getExtension();
	    $curlfile = "voices/".$datef."/".$sourceuuid."-".$datef."-".$calleduser.".".$urlext;
	    $curlhandlemp3  = fopen($curlfile, 'wb');
	    fwrite($curlhandlemp3, $bodyContent );
	    fclose($curlhandlemp3);
	//if ($urlext=='wav') {
	//	$curlfilewav = "voices/".temp."-".$sourceuuid.".wav";
	//	$curlhandlewav  = fopen($curlfilewav, 'wb');
	//	fwrite($curlhandlewav, $bodyContent );
	//	fclose($curlhandlewav);
	//	$resOutput = shell_exec("lame -h -b 32 --resample 8 -a $curlfilewav $curlfile");
	//	unlink($curlfilewav);
        //    } elseif ($urlext=='mp3') {
	//	$curlhandlemp3  = fopen($curlfile, 'wb');
	//	fwrite($curlhandlemp3, $bodyContent );
	//	fclose($curlhandlemp3);
	//    }	    
	    return $curlfile;
	} else {
	    return false;
	}
 }	
}