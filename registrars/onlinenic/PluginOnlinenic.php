<?php
require_once 'modules/admin/models/RegistrarPlugin.php';
require_once dirname(__FILE__).'/class.onlinenic.php';

/**
* @package Plugins
*/
class PluginOnlinenic extends RegistrarPlugin
{
    function getVariables()
    {
        $variables = array(
            lang('Plugin Name') => array (
                                'type'          =>'hidden',
                                'description'   =>lang('How CE sees this plugin (not to be confused with the Signup Name)'),
                                'value'         =>lang('OnlineNIC')
                               ),
            lang('Use testing server') => array(
                                'type'          =>'yesno',
                                'description'   =>lang('Select Yes if you wish to use OnlineNIC\'s testing environment, so that transactions are not actually made.'),
                                'value'         => 0
                               ),
            lang('Username') => array(
                                'type'          => 'text',
                                'description'   => lang('Enter your username for your OnlineNIC reseller account.'),
                                'value'         => '',
                            ),
            lang('Private Key')  => array(
                                'type'          => 'password',
                                'description'   => lang('Enter your OnlineNIC reseller password.'),
                                'value'         => '',
                            ),
           lang('Supported Features')  => array(
                                'type'          => 'label',
                                'description'   => '* '.lang('TLD Lookup').'<br>* '.lang('Domain Registration').' <br>',
                                'value'         => ''
                                ),
            lang('Actions') => array (
                                'type'          => 'hidden',
                                'description'   => lang('Current actions that are active for this plugin (when a domain isn\'t registered)'),
                                'value'         => 'Register'
                                ),
            lang('Registered Actions') => array (
                                'type'          => 'hidden',
                                'description'   => lang('Current actions that are active for this plugin (when a domain is registered)'),
                                'value'         => 'Cancel',
                                )
        );

        return $variables;
    }

    function getError($return, $request)
    {
        $errorMessage = '';

        if($return == null) {
            $errorMessage = "OnlineNIC Error: Ensure port 20001 is open and PHP is compiled with OpenSSL.";
            CE_Lib::log(4, $errorMessage);
            return true;
        }

        if($return['result'][0]['@']['code'] != 1000 && $return['result'][0]['@']['code'] != 1300 && $return['result'][0]['@']['code'] != 1500 ){
            if($return['result'][0]['@']['code'] == 1700 && $request == 'Register Domain'){
                $errorMessage = "OnlineNIC ".$request." Successfully, but fail to keep it in OnlineNIC's database. Please contact OnlineNIC: ".$return['result'][0]['#']['msg'][0]['#'];
            }else{
                $errorMessage = "OnlineNIC ".$request." Failed: ".$return['result'][0]['#']['msg'][0]['#'];
                if(isset($return['result'][0]['#']['value'][0]['#']) && $return['result'][0]['#']['value'][0]['#'] != 'no value') $errorMessage .= " (".$return['result'][0]['#']['value'][0]['#'].")";
            }
        }

        if($errorMessage != ''){
            CE_Lib::log(4, $errorMessage);
            throw new CE_Exception($errorMessage);
        }

        return $errorMessage;
    }

    function checkDomain($params)
    {
        $host = 'onlinenic.com';
        //if ($params['Use testing server']) $host = '218.5.81.149';

        $onlinenic = new OnlineNIC($host,
                                   $params['Username'],
                                   $params['Private Key']);

        $loginReturn = $onlinenic->login();

        // Check for login errors
        if($this->getError($loginReturn, 'Login') != '') {
            $status = 5;
        }

        $return = $onlinenic->lookup_domain(strtolower($params['sld']), strtolower($params['tld']));

        // Check for lookup errors
        if($this->getError($return, 'Lookup') != '') {
            $status = 5;
        }

        $available = $return['resData'][0]['#']['domain:chkData'][0]['#']['domain:cd'][0]['@']['x'];

        $logoutReturn = $onlinenic->logout();

        // Check for logout errors
        $this->getError($logoutReturn, 'Logout');

        if ($available == '-') {
            $status = 0;
        } else if ($available == '+') {
            $status = 1;
        } else {
            $status = 5;
        }
        $domains[] = array("tld"=>$params['tld'],"domain"=>$params['sld'],"status"=>$status);
        return array("result"=>$domains);
    }


    /**
     * Register domain name
     *
     * @param array $params
     */
    function doRegister($params)
    {
        $userPackage = new UserPackage($params['userPackageId']);
        $orderid = $this->registerDomain($this->buildRegisterParams($userPackage,$params));
        $userPackage->setCustomField("Registrar Order Id",$userPackage->getCustomField("Registrar").'-'.$orderid);
        return $userPackage->getCustomField('Domain Name') . ' has been registered.';
    }

    function registerDomain($params)
    {
        if ($params['Use testing server']) {
            $host = '218.5.81.149';
            $params['NS1']['hostname'] = "ns2.onlinenic.com";
            $params['NS2']['hostname'] = "ns3.onlinenic.com";
            $params['Custom NS'] = 1;
            for ($i = 3; $i <= 12; $i++) {
                unset($params['NS'.$i]);
            }
        } else {
            $host = 'onlinenic.com';
            if (isset($params['NS1'])) {
               $params['Custom NS'] = 1;
            } else {
                $params['Custom NS'] = 0;
            }

            if ( @$params['NS1']['hostname'] == '' ) {
                $params['NS1']['hostname'] = "ns2.onlinenic.com";
                $params['NS2']['hostname'] = "ns3.onlinenic.com";
            }
        }

        $onlinenic = new OnlineNIC($host,
                                   $params['Username'],
                                   $params['Private Key'],
                                   $this->logger);

        $params['domain'] = strtolower($params['sld'].".".$params['tld']);
        $params['RegistrantPhone'] = $this->_plugin_onlinenic_validatePhone($params['RegistrantPhone'],$params['RegistrantCountry']);
        if ($params['RegistrantOrganizationName'] == "") $params['RegistrantOrganizationName'] = $params['RegistrantFirstName']." ".$params['RegistrantLastName'];

        /* Grab some information that isn't passed by default */
        $query = "SELECT id from customuserfields where type='8'";
        $result = $this->db->query($query);
        list($fieldid) = $result->fetch();

        $query = "SELECT id FROM users WHERE email=?";
        $result = $this->db->query($query, $params['RegistrantEmailAddress']);
        list($userid) = $result->fetch();

        $query = "SELECT value FROM user_customuserfields WHERE customid=? AND userid=?";
        $result = $this->db->query($query, $fieldid, $userid);
        list($lang) = $result->fetch();
        if (strtolower($lang) == 'french') $params['RegistrantLanguage'] = 'FR';
        else $params['RegistrantLanguage'] = 'EN';

        $loginReturn = $onlinenic->login();

        // Check for login errors
        $errorMessage = $this->getError($loginReturn, 'Login');
        if($errorMessage != '') return array(-1, array($errorMessage));


//      Review in DataBase if this user has a contact id for OnlineNIC, if not, then create it
//      some SQL query

//      if(this user has a contact id for OnlineNIC){
//          $params['ContactID'] = DATABASE_VALUE;
//      }else{

            $createContactReturn = $onlinenic->create_contact($params);

            // Check for create contact errors
            $errorMessage = $this->getError($loginReturn, 'Create Contact');
            if($errorMessage != '') return array(-1, array($errorMessage));

//          Store in the DataBase the contact id for OnlineNIC
//          some SQL query

            $params['ContactID'] = $createContactReturn['resData'][0]['#']['contact:creData'][0]['#']['contact:id'][0]['#'];
//      }

        $return = $onlinenic->register_domain($params);

        // Check for register domain errors
        $errorMessage = $this->getError($loginReturn, 'Register Domain');
        if($errorMessage != '') return array(-1, array($errorMessage));

        CE_Lib::log(4, "OnlineNIC Registration Response: ".$return['result'][0]['#']['msg'][0]['#']);
        $regId = $return['trID'][0]['#']['svTRID'][0]['#'];

        $logoutReturn = $onlinenic->logout();

        // Check for logout errors
        $this->getError($logoutReturn, 'Logout');

        return $regId;
    }

    // @access private
    function _plugin_onlinenic_validatePhone($phone, $country)
    {
        // strip all non numerical values
        $phone = preg_replace('/[^\d]/', '', $phone);

        $query = "SELECT phone_code FROM country WHERE iso=? AND phone_code != ''";
        $result = $this->db->query($query, $country);
        if (!$row = $result->fetch()) {
            return $phone;
        }

        // check if code is already there
        $code = $row['phone_code'];
        $phone = preg_replace("/^($code)(\\d+)/", '+\1.\2', $phone);
        if ($phone[0] == '+') {
            return $phone;
        }

        // if not, prepend it
        return "+$code.$phone";
    }

    function getContactInformation ($params)
    {
        throw new CE_Exception('Getting Contact Information is not supported in this plugin.', EXCEPTION_CODE_NO_EMAIL);
    }

    function setContactInformation ($params)
    {
        throw new CE_Exception('Method setContactInformation() has not been implemented yet.');
    }

    function getNameServers ($params)
    {
        throw new CE_Exception('Getting Name Server Records is not supported in this plugin.', EXCEPTION_CODE_NO_EMAIL);
    }

    function setNameServers ($params)
    {
        throw new CE_Exception('Method setNameServers() has not been implemented yet.');
    }

    function checkNSStatus ($params)
    {
        throw new CE_Exception('Method checkNSStatus() has not been implemented yet.');
    }

    function registerNS ($params)
    {
        throw new CE_Exception('Method registerNS() has not been implemented yet.');
    }

    function editNS ($params)
    {
        throw new CE_Exception('Method editNS() has not been implemented yet.', EXCEPTION_CODE_NO_EMAIL);
    }

    function deleteNS ($params)
    {
        throw new CE_Exception('Method deleteNS() has not been implemented yet.');
    }

    function getGeneralInfo ($params)
    {
        throw new CE_Exception('Getting Domain Information is not supported in this plugin.', EXCEPTION_CODE_NO_EMAIL);
    }

    function setAutorenew ($params)
    {
        throw new MethodNotImplemented('Method setAutorenew() has not been implemented yet.');
    }

    function getRegistrarLock ($params)
    {
        throw new CE_Exception('Method getRegistrarLock() has not been implemented yet.', EXCEPTION_CODE_NO_EMAIL);
    }

    function setRegistrarLock ($params)
    {
        throw new CE_Exception('Method setRegistrarLock() has not been implemented yet.');
    }

    function sendTransferKey ($params)
    {
        throw new CE_Exception('Method sendTransferKey() has not been implemented yet.');
    }
    function disablePrivateRegistration($parmas)
    {
        throw new MethodNotImplemented('Method disablePrivateRegistration has not been implemented yet.');
    }
    function getTransferStatus($params)
    {
        throw new MethodNotImplemented('Method getTransferStatus has not been implemented yet.', EXCEPTION_CODE_NO_EMAIL);
    }
}

?>
