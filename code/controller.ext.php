<?php
/**
 *
 * Domain Transfer Module for Sentora 1.0
 * Version : 1.0.5
 * Author :  Aderemi Adewale (modpluz @ Sentora Forums)
 * Email : goremmy@gmail.com
 */


class module_controller {

    static $complete;
    static $error;
    static $writeerror;
    static $nodomain;
    static $nouser;
    static $quotausedup;
    static $selfdomaintransfer;
    static $ok;

   /* Load CSS and JS files */
    static function getInit() {
        global $controller;
        $line = '<link rel="stylesheet" type="text/css" href="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/transfer_domain.css">';
        $line .= '<script type="text/javascript" src="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/transfer_domain.js"></script>';
        return $line;
    }

    /**
     * The 'worker' methods.
     */
    static function ListDomains($uid=0) {
        global $zdbh;
		
		$users_id_s = $uid;
		
		// in order to fetch domains related to this user, we need to also fetch all users belonging to this user (sub users)
		//$currentuser = ctrl_users::GetUserDetail();
		$users_id_sub = self::subUsers($uid);
		if($users_id_sub){
		   $users_id_s .= ','.$users_id_sub;
		}

        //if ($currentuser['usergroupid'] == 1) {
            $sql = "SELECT vh_acc_fk,vh_name_vc,vh_id_pk FROM x_vhosts WHERE find_in_set(vh_acc_fk, :user_ids) 
            		AND vh_deleted_ts IS NULL AND vh_type_in=1 ORDER BY vh_name_vc ASC";
        //} else {
          //  $sql = "SELECT * FROM x_vhosts WHERE vh_acc_fk=" . $uid . " AND vh_deleted_ts IS NULL AND vh_type_in=1 ORDER BY vh_name_vc ASC";
        //}
        $bindArray = array(':user_ids' => $users_id_s);                                        
        $zdbh->bindQuery($sql, $bindArray);
        $rows = $zdbh->returnRows(); 

        if (count($rows) > 0) {
            $res = array();
            foreach($rows as $row_idx=>$row) {
                array_push($res, array(
                    'uid' => $row['vh_acc_fk'],
                    'name' => $row['vh_name_vc'],
                    'id' => $row['vh_id_pk'],
                ));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function ListClients($uid=0) {
        global $zdbh;

		//$currentuser = ctrl_users::GetUserDetail();
        $sql = "SELECT ac_id_pk,ac_user_vc FROM x_accounts WHERE (ac_reseller_fk=:user_id OR ac_id_pk=:user_id) AND ac_deleted_ts IS NULL";
        $bindArray = array(':user_id' => $uid);                                        
        $zdbh->bindQuery($sql, $bindArray);
        $rows = $zdbh->returnRows(); 

        if (count($rows) > 0) {
            $res = array();
            foreach($rows as $row_idx=>$row) {
				if($row['ac_id_pk'] == $uid){
					$row['ac_user_vc'] = 'Me('.$row['ac_user_vc'].')';
				}
               array_push($res, array('id' => $row['ac_id_pk'],
				                        'name' => $row['ac_user_vc']));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function subUsers($uid) {
        global $zdbh;
		$users_id_s = '';
        $sql = "SELECT ac_id_pk FROM x_accounts WHERE ac_id_pk<>:user_id AND ac_reseller_fk=:user_id AND ac_deleted_ts IS NULL";
        $bindArray = array(':user_id' => $uid);                                        
        $zdbh->bindQuery($sql, $bindArray);
        $rows = $zdbh->returnRows(); 

        if (count($rows) > 0) {
            $res = array();
            foreach($rows as $row_idx=>$row) {
				if($row['ac_id_pk']){				
					$users_id_s .= $row['ac_id_pk'].',';
					/* un-commenting the line below(96) will allow listing of domains owned by a 
					   sub-account of the current $row_users['ac_id_pk']
					   Although, i don't think these domains should be visible to the logged in 
						user unless they directly belong to one of his/her sub-accounts */
					//$users_id_s .= self::subUsers($row_users['ac_id_pk']).',';
				}
            }
		}
		
		$users_id_s = str_replace(',,', ',', $users_id_s);

		if($users_id_s){
			$users_id_s = substr($users_id_s,0,-1);
		}
		return $users_id_s;
    }
	

    static function CheckTransferForErrors($uid,$domain_id) {
        //global $zdbh;
        // validate user id and domain id before we proceed...
        if ($uid == '' || $uid == 0 || !is_numeric($uid)) {
            self::$nouser = true;
            return false;
        }

        if ($domain_id == '' || $domain_id == 0 || !is_numeric($domain_id)) {
            self::$nodomain = true;
            return false;
        }
 
        return true;
    }
	

    /**
     * End 'worker' methods.
     */

    /**
     * Webinterface sudo methods.
     */
    static function getDomainList() {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $res = array();
        $domains = self::ListDomains($currentuser['userid']);
        if (!fs_director::CheckForEmptyValue($domains)) {
            foreach ($domains as $row) {
                array_push($res, array('name' => $row['name'],
                    'id' => $row['id']));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getClientList() {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $res = array();
        $users = self::ListClients($currentuser['userid']);
        if (!fs_director::CheckForEmptyValue($users)) {
            foreach ($users as $row) {
                array_push($res, array('client_name' => $row['name'],
                    'client_id' => $row['id']));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getCSFR_Tag() {
        return runtime_csfr::Token();
    }

    static function getModuleName() {
        $module_name = ui_module::GetModuleName();
        return $module_name;

    }

    static function getModuleIcon() {
        global $controller;
        $module_icon = "/modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/icon.png";
        return $module_icon;
    }


    static function getModuleDesc() {
        $message = ui_language::translate(ui_module::GetModuleDescription());
        return $message;
    }


    /*static function doConfirmDomainTransfer() {
        global $controller;
        runtime_csfr::Protect();
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
        if (!fs_director::CheckForEmptyValue(self::CheckTransferForErrors($formvars['transfer_uid'],$formvars['transfer_domain_id']))) {
            return false;

        } else {
            return true;
        }
        return;
    }*/



    static function ExecuteTransferDomain($uid, $domain_id) {
        global $zdbh;
        $retval = false;
        runtime_hook::Execute('OnBeforeTransferDomain');
		if (!fs_director::CheckForEmptyValue(self::CheckTransferForErrors($uid,$domain_id))) {
	        $currentuser = ctrl_users::GetUserDetail();

			// transfer user info	        
			$transfer_user_info = ctrl_users::GetUserDetail($uid);
			$transfer_user_domain_usage = ctrl_users::GetQuotaUsages('domains', $uid);
			$transfer_user_domain_quota = $transfer_user_info['domainquota'];

			// transfer domain owner info and domain directory
			$sql = "SELECT vh_acc_fk,vh_directory_vc,vh_name_vc FROM x_vhosts 
						WHERE vh_id_pk=:domain_id AND vh_type_in=1";
            $bindArray = array(':domain_id' => $domain_id);                                        
            $zdbh->bindQuery($sql, $bindArray);
            $domain_info = $zdbh->returnRow(); 
			

			//making sure transfer user haven't used up their domain quota			
			if((($transfer_user_domain_quota - $transfer_user_domain_usage) < 1) && ($transfer_user_domain_quota != '-1')){
				self::$quotausedup = true;
			} elseif($domain_info['vh_acc_fk'] == $uid){
				self::$selfdomaintransfer = true;
			} else {
				// domain previous owner id
			
				// is there one or more sub domains that belongs to this domain?
				//$sub_domain_tld = $domain_info['vh_name_vc'];
				$sql = "SELECT vh_id_pk,vh_directory_vc FROM x_vhosts WHERE vh_acc_fk=".$domain_info['vh_acc_fk']." 
							AND vh_name_vc LIKE '%:vh_name%' AND vh_type_in=2";
                $bindArray = array(':vh_name' => $domain_info['vh_name_vc']);                                        
                $zdbh->bindQuery($sql, $bindArray);
                $rows = $zdbh->returnRows(); 

                $sub_domains = array();
                if (count($rows) > 0) {					
                    foreach($rows as $row_idx=>$row_sub_domain) {
						array_push($sub_domains, array('vh_directory_vc' => $row_sub_domain['vh_directory_vc'],
														'vh_id_pk' => $row_sub_domain['vh_id_pk']));
					}
				}

			
				// current domain user info	        
				$domain_user_info = ctrl_users::GetUserDetail($domain_info['vh_acc_fk']);
				$current_domain_path = ctrl_options::GetSystemOption('hosted_dir') . $domain_user_info['username'] . "/public_html".$domain_info['vh_directory_vc'];
				$log_path = str_replace("hostdata", "logs", ctrl_options::GetSystemOption('hosted_dir'));
				$current_log_path = $log_path.'domains/'.$domain_user_info['username'];
				
				$new_domain_path = ctrl_options::GetSystemOption('hosted_dir') . $transfer_user_info['username'] . "/public_html".$domain_info['vh_directory_vc'];
				$new_log_path = $log_path.'domains/'.$transfer_user_info['username'];

				if(is_dir($current_domain_path)){
					// move directory
					$cmd = 'mv "'.$current_domain_path.'" "'.$new_domain_path.'"'; 
					@exec($cmd);
					
					//move log files
                    $current_log_path = fs_director::ConvertSlashes($current_log_path);
                    /*if(!fs_director::CheckFolderExists($current_log_path)){
                        fs_director::CreateDirectory($current_log_path);
                    }*/

                    $new_log_path = fs_director::ConvertSlashes($new_log_path);
                    /*if(!fs_director::CheckFolderExists($new_log_path)){
                        fs_director::CreateDirectory($new_log_path);
                    }*/

                    if(fs_director::CheckFolderExists($current_log_path) && fs_director::CheckFolderExists($new_log_path)){
                        //access log
                        $log_current_file_path = fs_director::ConvertSlashes($current_log_path.'/'.$domain_info['vh_name_vc'].'-access.log');
                        $log_new_file_path = fs_director::ConvertSlashes($new_log_path.'/'.$domain_info['vh_name_vc'].'-access.log');


                        if(fs_director::CheckFileExists($log_current_file_path)){
                            @exec('mv "'.$log_current_file_path.'" "'.$log_new_file_path.'"');
                        }

                        //bandwidth log
                        $log_current_file_path = fs_director::ConvertSlashes($current_log_path.'/'.$domain_info['vh_name_vc'].'-bandwidth.log');
                        $log_new_file_path = fs_director::ConvertSlashes($new_log_path.'/'.$domain_info['vh_name_vc'].'-bandwidth.log');
                        if(fs_director::CheckFileExists($log_current_file_path)){
                            @exec('mv "'.$log_current_file_path.'" "'.$log_new_file_path.'"');
                        }

                        //error log
                        $log_current_file_path = fs_director::ConvertSlashes($current_log_path.'/'.$domain_info['vh_name_vc'].'-error.log');
                        $log_new_file_path = fs_director::ConvertSlashes($new_log_path.'/'.$domain_info['vh_name_vc'].'-error.log');
                        if(fs_director::CheckFileExists($log_current_file_path)){
                            @exec('mv "'.$log_current_file_path.'" "'.$log_new_file_path.'"');
                        }
                    }
                    
					//move sub domains (if any)
					if(is_array($sub_domains) && count($sub_domains) > 0){
						foreach($sub_domains as $sub_domain){
							$current_sub_domain_path = ctrl_options::GetSystemOption('hosted_dir') . $domain_user_info['username'] . "/public_html".$sub_domain['vh_directory_vc'];
							$new_sub_domain_path = ctrl_options::GetSystemOption('hosted_dir') . $transfer_user_info['username'] . "/public_html".$sub_domain['vh_directory_vc'];
							
							if(is_dir($current_sub_domain_path)){
								// move directory
								@exec('mv "'.$current_sub_domain_path.'" "'.$new_sub_domain_path.'"'); 

					            //move log files                                
                                if(fs_director::CheckFolderExists($current_log_path) && fs_director::CheckFolderExists($new_log_path)){
                                    //access log
                                    $log_current_file_path = fs_director::ConvertSlashes($current_log_path.'/'.$sub_domain['vh_name_vc'].'-access.log');
                                    $log_new_file_path = fs_director::ConvertSlashes($new_log_path.'/'.$sub_domain['vh_name_vc'].'-access.log');
                                    if(fs_director::CheckFileExists($log_current_file_path)){
                                        @exec('mv "'.$log_current_file_path.'" "'.$log_new_file_path.'"');
                                    }

                                    //bandwidth log
                                    $log_current_file_path = fs_director::ConvertSlashes($current_log_path.'/'.$sub_domain['vh_name_vc'].'-bandwidth.log');
                                    $log_new_file_path = fs_director::ConvertSlashes($new_log_path.'/'.$sub_domain['vh_name_vc'].'-bandwidth.log');
                                    if(fs_director::CheckFileExists($log_current_file_path)){
                                        @exec('mv "'.$log_current_file_path.'" "'.$log_new_file_path.'"');
                                    }

                                    //error log
                                    $log_current_file_path = fs_director::ConvertSlashes($current_log_path.'/'.$sub_domain['vh_name_vc'].'-error.log');
                                    $log_new_file_path = fs_director::ConvertSlashes($new_log_path.'/'.$sub_domain['vh_name_vc'].'-error.log');
                                    if(fs_director::CheckFileExists($log_current_file_path)){
                                        @exec('mv "'.$log_current_file_path.'" "'.$log_new_file_path.'"');
                                    }
                                }

								//update sub domain user id				
								$sql = "UPDATE x_vhosts SET vh_acc_fk=:vh_acc_fkid WHERE vh_id_pk=:vh_pk_id";
                                $bindArray = array(':vh_acc_fkid' => $uid,':vh_pk_id' => $sub_domain['vh_id_pk']);
                                $zdbh->bindQuery($sql, $bindArray);
							}
						}
					}

					//update domain user id				
					$sql = "UPDATE x_vhosts SET vh_acc_fk=:vh_acc_fkid WHERE vh_id_pk=:vh_pk_id";
                    $bindArray = array(':vh_acc_fkid' => $uid,':vh_pk_id' => $domain_id);
                    $zdbh->bindQuery($sql, $bindArray);

					// update apache_changed
					$sql = $zdbh->prepare("UPDATE x_settings SET so_value_tx='true'	WHERE so_name_vc='apache_changed'");
					$sql->execute();
			
					self::$ok = true;
					$retval = true;
				} else {
					self::$error = true;
				}				


			}

		}
        runtime_hook::Execute('OnAfterTransferDomain');
		

        return $retval;
    }

    static function getisTransferDomain() {
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        $formvars = $controller->GetAllControllerRequests('FORM');
		
		if(!$formvars){
			return false;
		}

		if (!fs_director::CheckForEmptyValue(self::CheckTransferForErrors($formvars['transfer_uid'],$formvars['transfer_domain_id']))) {
	        if ((isset($urlvars['action'])) && ($urlvars['action'] == "ConfirmDomainTransfer"))
	            return true;

		}
        return false;
    }
	
	static function getTransferDomainName(){
        global $controller, $zdbh;
		$domain_id = (int) $controller->GetControllerRequest('FORM', 'transfer_domain_id');
        if ($domain_id) {
			$sql = "SELECT vh_name_vc FROM x_vhosts WHERE vh_id_pk=:vh_id AND vh_deleted_ts IS NULL AND vh_type_in=1";
            $bindArray = array(':vh_id' => $domain_id);                                        
            $zdbh->bindQuery($sql, $bindArray);
            $row = $zdbh->returnRow(); 
			return $row['vh_name_vc'];
        } else {
            return "";
        }
	}

	static function getTransferDomainID(){
        global $controller;
        if ($controller->GetControllerRequest('FORM', 'transfer_domain_id')) {
            return $controller->GetControllerRequest('FORM', 'transfer_domain_id');
        } else {
            return "";
        }
	}

	static function getTransferUser(){
        global $controller, $zdbh;
		$transfer_uid = (int) $controller->GetControllerRequest('FORM', 'transfer_uid');
        if ($transfer_uid) {
			$sql = "SELECT ac_user_vc FROM x_accounts WHERE ac_id_pk=:acc_pk_id AND ac_deleted_ts IS NULL;";
            $bindArray = array(':acc_pk_id' => $transfer_uid);                                        
            $zdbh->bindQuery($sql, $bindArray);
            $row = $zdbh->returnRow(); 
			return $row['ac_user_vc'];
        } else {
            return "";
        }
	}

	static function getTransferUserID(){
        global $controller;
        if ($controller->GetControllerRequest('FORM', 'transfer_uid')) {
            return $controller->GetControllerRequest('FORM', 'transfer_uid');
        } else {
            return "";
        }
	}


	static function doTransferDomain(){
        global $controller;
        runtime_csfr::Protect();
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
        if (self::ExecuteTransferDomain($formvars['transfer_uid'], $formvars['transfer_domain_id'])) {
           self::$ok = true;
           return true;			
        } else {
           return false;
        }
        return;		
	}



    static function getResult() {
        if (!fs_director::CheckForEmptyValue(self::$nouser)) {
            return ui_sysmessage::shout(ui_language::translate("Please specify a valid user account to transfer domain to and try again."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$nodomain)) {
            return ui_sysmessage::shout(ui_language::translate("Please specify a valid domain to transfer and try again."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$ok)) {
            return ui_sysmessage::shout(ui_language::translate("Domain has been transferred successfully."), "zannounceok");
        }
        if (!fs_director::CheckForEmptyValue(self::$quotausedup)) {
            return ui_sysmessage::shout(ui_language::translate("This user have already reached their domain quota."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$selfdomaintransfer)) {
            return ui_sysmessage::shout(ui_language::translate("You cannot transfer this domain to the same user that presently owns it."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$error)) {
            return ui_sysmessage::shout(ui_language::translate("An error has occurred with this transfer, please ensure that domain physical directory exists."), "zannounceerror");
        }
        return;
    }

    /**
     * Webinterface sudo methods.
     */
}

?>
