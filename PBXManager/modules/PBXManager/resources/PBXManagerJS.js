/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

var Vtiger_PBXManager_Js = {

        //SalesPlatform.ru end PBXManager porting
	/**
	 * Function registers PBX for popups
	 */
	registerPBXCall : function() {
            Vtiger_PBXManager_Js.requestPBXgetCalls();
	},

	/**
	 * Function registers PBX for Outbound Call
	 */
	registerPBXOutboundCall : function(number,record) {
		Vtiger_PBXManager_Js.makeOutboundCall(number,record);
	},
	/**
	 * Function request for PBX popups
	 */
	requestPBXgetCalls : function() {
		var url = 'index.php?module=PBXManager&action=IncomingCallPoll&mode=searchIncomingCalls';
		app.request.get({url : url}).then(function(err, data) {

			if (typeof data === 'string') {
				location.href = 'index.php';
			}
			if(!err) {
				for(var i = 0; i < data.length; i++) {
					var record = data[i];
					var wndflag = record.flag;
					//app.request.get(url).then(function(data){
					//if(data.success && data.result) {
					//        for(i=0; i< data.result.length; i++) {
					//                var record = data.result[i];
					//SalesPlatform.ru end PBXManager porting
					if(jQuery('#pbxcall_'+record.pbxmanagerid+'').size()== 0 && wndflag != 'close' ) {
                        Vtiger_PBXManager_Js.showPBXIncomingCallPopup(record);
                    } else {
                        Vtiger_PBXManager_Js.updatePBXIncomingCallPopup(record);
					}
				}
            }
		});
		Vtiger_PBXManager_Js.removeCompletedCallPopup();
	},

	/**
	 * Function display the PBX popup
	 */
	showPBXIncomingCallPopup : function(record) {
		var contactFieldStyle = ((record.customer != null && record.customer != '') ? '' : '');

		var options = {
			icon: 'fa fa-check-circle',
			title: app.vtranslate('JS_PBX_INCOMING_CALL'),
			message: `<div class="row-fluid pbxcall" id="pbxcall_${record.pbxmanagerid}" callid=${record.pbxmanagerid} style="color:white">
                <span class="col-sm-12" id="caller" value="${record.customernumber}">${app.vtranslate('JS_PBX_CALL_FROM')} : ${record.customernumber}</span>
                <span class="col-sm-12 ${contactFieldStyle} hidden" id="contactsave_${record.pbxmanagerid}">
                <input class="col-sm-8" id="lastname_${record.pbxmanagerid}" type="text" style="color:black" placeholder="${app.vtranslate('Enter Last Name')}"></input>
                <select class="input-medium col-sm-3 col-sm-offset-1" style="color:black" id="module_${record.pbxmanagerid}">
                <option value="Select" selected>${app.vtranslate('Select')}</option>
                </select>
                </span>
                <br/>
                <span class="col-sm-12 ${contactFieldStyle}" id="contactclose_${record.pbxmanagerid}">\n\
                <span>
                <br/>
                <button class="btn btn-success pull-right hidden" id="pbxcontactsave_${record.pbxmanagerid}" recordid="${record.pbxmanagerid}" type="submit">${app.vtranslate('Save')}</button>
                <button class="btn btn-warning pull-left" id="pbxcontactclose_${record.pbxmanagerid}" recordid="${record.pbxmanagerid}" type="submit">${app.vtranslate('Close')}</button>
                </span>
                </span>
                <br/>
                <span class="col-sm-12" style="display:none" id="owner"> &nbsp;:&nbsp;
                <span id="ownername"></span>
                </span>
                </div>`,
            };
        
		var settings = {
			template: 	`<div data-notify="container" class="col-xs-11 col-sm-3 vt-notification vt-notification-{0}" role="alert">
						<div class="notificationHeader">
						<span data-notify="icon"></span>
						<span data-notify="title">{1}</span>
						</div>
						<div data-notify="message">{2}</div>
						<div class="progress" data-notify="progressbar">
						<div class="progress-bar progress-bar-{0}" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>
						</div>
						<a href="{3}" target="{4}" data-notify="url"></a>
						</div>`,
			delay: 0,
			placement: {
				from: "bottom",
				align: "right"
			},
			offset: 20
		};
		jQuery.notify(options, settings);


                    
		//To remove the popup for all users except answeredby (existing record)
		if(record.user) {
			if(record.user != record.current_user_id) {
				Vtiger_PBXManager_Js.removeCallPopup(record.pbxmanagerid);
			}
		}

		// To check if it is new or existing contact
		Vtiger_PBXManager_Js.checkIfRelatedModuleRecordExist(record);

		if(record.answeredby!=null){
			jQuery('#answeredbyname','#pbxcall_' + record.pbxmanagerid).text(record.answeredby);
			jQuery('#answeredby','#pbxcall_' + record.pbxmanagerid).show();
		}

		jQuery('#pbxcontactsave_'+record.pbxmanagerid+'').bind('click', function(e) {
			var pbxmanagerid = jQuery(e.currentTarget).attr('recordid');

			//if(jQuery('#module_'+pbxmanagerid+'').val() == 'Select'){
			//	jQuery('#alert_msg').show();
				//return false;
			//}
			//if(jQuery('#lastname_'+pbxmanagerid+'').val() == ""){
				//jQuery('#alert_msg').show();
				//return false;
			//}

			Vtiger_PBXManager_Js.createRecord(e, record);
			//To restrict the save button action to one click
			jQuery('#pbxcontactsave_' + record.pbxmanagerid).unbind('click');
		});
		
		jQuery('#pbxcontactclose_' + record.pbxmanagerid).bind('click', function(e) {
			var pbxmanagerid = jQuery(e.currentTarget).attr('recordid');
			Vtiger_PBXManager_Js.closeRecordPopup(e, record);
			//To restrict the save button action to one click
			jQuery('#pbxcontactclose_' + record.pbxmanagerid).unbind('click');
		});

	},

	closeRecordPopup: function(e, record) {
		var pbxmanagerid = jQuery(e.currentTarget).attr('recordid');
		var flag = 'close';	
		var url = 'index.php?module=PBXManager&action=IncomingCallPoll&mode=closeRecordPopup&pbxmanagerid='+record.pbxmanagerid+'&flag='+flag+'&callid='+record.sourceuuid;
		// Salesplatform.ru begin PBXManager porting
        app.request.get({'url': url}).then(function(err, data){
			if(!err) {
				jQuery('#contactclose_' + pbxmanagerid).hide();
			}
		 });
	},
	


	createRecord: function(e, record) {
		var pbxmanagerid = jQuery(e.currentTarget).attr('recordid');
		var lastname = jQuery('#lastname_' + pbxmanagerid).val();
		var moduleName = jQuery('#module_' + pbxmanagerid).val();				
		var number = jQuery('#caller','#pbxcall_' + pbxmanagerid).attr("value");
		if (lastname == '') {
		    lastname = number;
		}    
		if (moduleName == 'Select') {
		    moduleName = 'Contacts';
		}
		var url = 'index.php?module=PBXManager&action=IncomingCallPoll&mode=createRecord&number='+encodeURIComponent(number)+'&lastname='+encodeURIComponent(lastname)+'&callid='+record.sourceuuid+'&modulename='+moduleName;
		// Salesplatform.ru begin PBXManager porting
        app.request.get({'url': url}).then(function(err, data){
			if(!err) {
                //app.request.get(url).then(function(data){
                //      if(data.success && data.result) {
                //SalesPlatform.ru end PBXManager porting
				jQuery('#contactsave_' + pbxmanagerid).hide();
				jQuery('#pbxcontactsave_' + pbxmanagerid).hide();
			}
		 });
	},

	checkIfRelatedModuleRecordExist: function(record) {
		var direction = record.direction;
		switch(record.callername){
			case null:	
			    var url = 'index.php?module=PBXManager&action=IncomingCallPoll&mode=checkModuleViewPermission&view=EditView';
				app.request.get({'url': url}).then(function(err, data){
					//app.request.get(url).then(function(data){
					//var responsedata = JSON.parse(data);
					//SalesPlatform.ru end PBXManager porting   
					var showSaveOption = false;
					var moduleList = data.modules;
					var contents = jQuery('#module_' + record.pbxmanagerid);
					var newEle;														for(var module in moduleList){
						if(moduleList.hasOwnProperty(module)) {
							if(moduleList[module]){
								newEle = '<option id="select_'+module+'" value="'+module+'">'+app.vtranslate(module)+'</option>'; 
								contents.append(newEle);
								showSaveOption = true;
							}
						}
					}
					//if(data && showSaveOption && record.direction !='local') {
					if( data && showSaveOption && direction != 'local' ) {
						jQuery('#contactsave_' + record.pbxmanagerid).removeClass('hidden').show();
						jQuery('#pbxcontactsave_' + record.pbxmanagerid).removeClass('hidden').show();
					} else {
						jQuery('#contactsave_' + record.pbxmanagerid).hide();
						jQuery('#pbxcontactsave_' + record.pbxmanagerid).hide();
					}
				});
				break;
			default:	
			    if (direction == 'local') {
					jQuery('#caller','#pbxcall_' + record.pbxmanagerid).html(app.vtranslate('JS_PBX_CALL_FROM')+'&nbsp;'+record.callername);
					jQuery('#contactsave_' + record.pbxmanagerid).hide();
					jQuery('#pbxcontactsave_' + record.pbxmanagerid).hide();
			    } else {
					jQuery('#caller','#pbxcall_' + record.pbxmanagerid).html(app.vtranslate('JS_PBX_CALL_FROM')+' :&nbsp;<a href="index.php?module='+record.customertype+'&view=Detail&record='+record.customer+'">'+record.callername+'</a>');
			    }
			    // SalesPlatform.ru begin
                        //jQuery('#ownername','#pbxcall_'+record.pbxmanagerid+'').text(record.ownername);
                        //jQuery('#owner','#pbxcall_'+record.pbxmanagerid+'').show();
                        // SalesPlatform.ru end
                break;
		}
	},

	 /**
	 * Function to update the popup with answeredby, hide contactsave option e.t.c.,
	 */
	updatePBXIncomingCallPopup: function(record){
		if(record.answeredby!=null){
			jQuery('#answeredbyname','#pbxcall_'+record.pbxmanagerid+'').text(record.answeredby);
			jQuery('#answeredby','#pbxcall_'+record.pbxmanagerid+'').show();
		}
		if(record.customer!=null && record.customer!=''){
			jQuery('#caller','#pbxcall_' + record.pbxmanagerid).html(app.vtranslate('JS_PBX_CALL_FROM')+' :&nbsp;<a href="index.php?module='+record.customertype+'&view=Detail&record='+record.customer+'">'+record.callername+'</a>');
			jQuery('#contactsave_' + record.pbxmanagerid).hide();
			jQuery('#pbxcontactsave_' + record.pbxmanagerid).hide();
		}
		//To remove the popup for all users except answeredby (new record)
		if(record.user) {
			if(record.user != record.current_user_id) {
				 Vtiger_PBXManager_Js.removeCallPopup(record.pbxmanagerid);
			}
		}
	},

	 /**
	 * Function to remove the call popup which is completed
	 */
	removeCompletedCallPopup:function(){
		var callid = null;
		var pbxcall = jQuery('.pbxcall');
		for(var i=0; i<pbxcall.length;i++){
			callid = pbxcall[i].getAttribute('callid');
			var url = 'index.php?module=PBXManager&action=IncomingCallPoll&mode=getCallStatus&callid='+encodeURIComponent(callid)+'';

            app.request.get({url : url}).then(function(err, data){
				if(data && data.callstatus !='in-progress' && data.callstatus !='ringing' ){	
							//SalesPlatform.ru end PBXManager porting 
					if (data.callstatus == 'completed' && data.flag == 'close' ) {
						Vtiger_PBXManager_Js.removeCallPopup(callid);
					} else if (data.callstatus != 'completed') {
										Vtiger_PBXManager_Js.removeCallPopup(callid);	
					} else if ( data.callstatus == 'completed' && data.customer != '') {
						Vtiger_PBXManager_Js.removeCallPopup(callid);
					} else if ( data.callstatus == 'completed' && data.customer == '' && data.direction == 'local' ) {
						Vtiger_PBXManager_Js.removeCallPopup(callid);
					} 
				} else if (data && data.flag == 'close' ) {
					Vtiger_PBXManager_Js.removeCallPopup(callid);
				}
			});
		}
	},

	/**
	 * Function to remove call popup
	 */
	//removeCallPopup: function(callid) {
	//	jQuery('#pbxcall_'+callid+'').parent().parent().parent().remove();
	//},
	removeCallPopup: function(callid) {
	    jQuery('#pbxcall_'+callid+'').parent().parent().remove();
	},


	 /**
	 * To get contents holder based on the view
	 */
	getContentHolder:function(view){
		if(view == 'List') {
			return jQuery('.listViewContentDiv');
		}
		return jQuery('.detailViewContainer');
	},

	 /**
	 * Function to forward call to number
	 */
	makeOutboundCall : function(number, record){
		var params = {
			data: {
				number : number,
				record : record,
				module : 'PBXManager',
				action : 'OutgoingCall',
			}      
		}
                // Salesplatform.ru begin PBXManager porting
		app.request.get(params).then(function(err, data){
			if (!err) {
				params = {
					text : app.vtranslate('JS_PBX_OUTGOING_SUCCESS'),
					type : 'info'
				}
			} else {
				params = {
					text : app.vtranslate('JS_PBX_OUTGOING_FAILURE'),
					type : 'error'
				}
			}
			Vtiger_PBXManager_Js.showPnotify(params);
		});
	},

	 /**
		* Function to register required events
		*/
	 registerEvents : function() {
		var thisInstance = this;
		//for polling
		var url = 'index.php?module=PBXManager&action=IncomingCallPoll&mode=checkPermissionForPolling';
                
                // Salesplatform.ru begin PBXManager porting
		app.request.get({url : url}).then(function(err, data){
			if (!err) {
				Vtiger_PBXManager_Js.registerPBXCall();
				Visibility.every(2000, function () {
					Vtiger_PBXManager_Js.registerPBXCall();
				});
			}
		});
	}

}

//On Page Load
jQuery(document).ready(function() {
    Vtiger_PBXManager_Js.registerEvents();
});
