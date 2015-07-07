<?php
require_once 'modules/admin/models/SnapinPlugin.php';
require_once 'library/CE/XmlFunctions.php';

class PluginEnomform extends SnapinPlugin
{
    public $title = 'Create eNom Account';

    function getVariables()
    {
        $variables = array(
            'Plugin Name'       => array(
                'type'        => 'hidden',
                'description' => '',
                'value'       => 'eNom Form',
            ),
            lang('Snap-in header seen by customers') => array(
                'type'        => 'textarea',
                'description' => lang('Enter here the header that will be shown on top of the snap-in, explaining rates and such.'),
                'value'       => 'Fill your Enom account with $100 minimum and each domain registration charge is deducted from your balance.<br/>Can download the API, setup Registry Rocket links, create a PDQ and create sub-accounts.<br/>Average domain registration fee is $9.45 per domain.<br/>Works with the ClientExec eNom plugin.<br/>'
            ),
            lang('Success message') => array(
                'type'        => 'textarea',
                'description' => lang('This is the message the customer will see after a successful eNom account creation.'),
                'value'       => 'Congratulations! Your eNom account has been created successfully.  You will receive an email notification with your login information shortly.<br><br><b>Note:</b> If a reseller account was requested it has been created as a retail account and will be upgraded to a reseller account within the next 24-48 hours.<br><br>Thank you,<br>-ClientExec Team'
            ),
            lang('Use ClientExec Account') => array(
                'type'        => 'yesno',
                'description' => lang('Select YES if you don\'t have an eNom reseller account and wish to provide accounts to your customers through ClientExec\'s account. Select NO if you have obtained your reseller account, for which you\'ll have to enter the Login and Password below. You can obtain an eNom reseller account by login into your ClientExec member account.'),
                'value'       => true,
            ),
            lang('Use testing server') => array(
                'type'          => 'yesno',
                'description'   => lang('Select Yes if you wish to use eNom\'s testing environment, so that transactions are not actually made. For this to work, you must first register you server\'s ip in eNom\'s testing environment, and your server\'s name servers must be registered there as well.'),
                'value'         => 0,
            ),
            'Login' => array(
                'type'          => 'text',
                'description'   => lang('Enter your username for your eNom reseller account. If you are using ClientExec\'s account, you can leave this field empty.'),
                'value'         => '',
            ),
            'Password'  => array(
                'type'          => 'password',
                'description'   => lang('Enter the password for your eNom reseller account. If you are using ClientExec\'s account, you can leave this field empty.'),
                'value'         => '',
            ),
            lang('Notification Email') => array(
                'type'          => 'text',
                'description'   => lang('If not using ClientExec\'s eNom account, enter the email address to which sign up details should be emailed to.'),
                'value'         => '',
            ),
            'Public Description'       => array(
                'type'        => 'text',
                'description' => 'Description to be seen by public',
                'value'       => 'Sign up for your eNom reseller account today.',
            )
        );

        return $variables;
    }

    public function init()
    {
        $this->settingsNotes = lang('When enabled this snapin gives your customers the ability to obtain their own eNom accounts on a self-service basis.');
        $this->addMappingForPublicMain("view", "eNom Reseller", 'Integrate eNom Signup Form in Public Home', 'icon-pencil', 'margin: 5px;');
        $this->addMappingForTopMenu('public', '', 'view', 'eNom Reseller', 'Integrate eNom Signup Form in Public Top Menu');
    }

    function view()
    {

        $this->overrideTemplate = true;

        if (isset($_POST['submit'])) {
            $return = $this->_processdata();
            if ( $return === true ) {
                CE_Lib::redirectPage('index.php?fuse=admin&view=snapin&controller=snapins&plugin=enomform&success=1&v=view');
            } else {
                $this->session->error = $return;
                CE_Lib::redirectPage('index.php?fuse=admin&view=snapin&controller=snapins&plugin=enomform&v=view');
            }
        } else {
            $error = null;
            if ( isset($this->session->error) ) {
                $error = $this->session->error;
                unset($this->session->error);
            }
            return $this->_showform($error);
        }
    }

    function _showform($response = false) {

        $user = $this->user;

        if (isset($_REQUEST['success']) && $_REQUEST['success']) {
            $this->view->successMessage = $this->settings->get('plugin_enomform_Success message');
        }

        $this->view->snapinHeader = $this->settings->get('plugin_enomform_Snap-in header seen by customers');

        include_once 'modules/admin/models/Countries.php';
        $countries = new Countries($this->user);

        if (is_a($response, 'CE_Error') || $response === false || !isset($response['interface-response']['#']['NewAccount'][0]['#']['Fname'][0]['#'])) {

            if($user->getCountry()){
                $country = $user->getCountry();
            }else{
                $country = 'US';
            }
            $this->view->countries = $countries->getCodes($country, true);

            $this->view->firstName = $user->getFirstName();
            $this->view->lastName = $user->getLastName();
            $this->view->email = $user->getEmail();
            $this->view->organization = $user->getOrganization();
            $this->view->title = '';
            $this->view->address = $user->getAddress();
            $this->view->city = $user->getCity();
            $this->view->state = $user->getState();
            $this->view->zipcode = $user->getZipCode();
            $this->view->phone = $user->getPhone();
            $this->view->retail = '';
            $this->view->id = '';
            $this->view->pw = '';
            $this->view->pw2 = '';
            $this->view->cob = '';
            $this->view->l4d = '';
            $this->view->hs = '';
            $this->view->ans = '';
            $this->view->err = is_a($response, 'CE_Error')? $response->getMessage() : @$response['interface-response']['#']['errors'][0]['#']['Err1'][0]['#'];
        } else {

            if($_POST['country']){
                $country = $_POST['country'];
            }else{
                $country = 'US';
            }
            $this->view->countries  = $countries->getCodes($country, true);

            if (!isset($response['interface-response']['#']['NewAccount'][0]['#']['GetAccountInfo'][0]['#']['Reseller'][0]['#'])) {
                $retail = "selected";
            } else {
                $retail = '';
            }
            $cob = "";
            $l4d = "";
            $hs = "";
            if (isset($response['interface-response']['#']['NewAccount'][0]['#']['GetAccountInfo'][0]['#']['AuthQuestionType'][0]['#'])) {
                switch($response['interface-response']['#']['NewAccount'][0]['#']['GetAccountInfo'][0]['#']['AuthQuestionType'][0]['#']) {
                    case 'sbirth':
                        $cob = " selected ";
                        break;
                    case 'ssocial':
                        $l4d = " selected ";
                        break;
                    case 'shigh':
                        $hs = " selected ";
                        break;
                }
            }
            $this->view->firstName = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['Fname'][0]['#']);
            $this->view->lastName = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['Lname'][0]['#']);
            $this->view->email = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['EmailAddress'][0]['#']);
            $this->view->organization = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['OrganizationName'][0]['#']);
            $this->view->title = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['JobTitle'][0]['#']);
            $this->view->address = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['Address1'][0]['#']);
            $this->view->city = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['City'][0]['#']);
            $this->view->state = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['StateProvince'][0]['#']);
            $this->view->country = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['Country'][0]['#']);
            $this->view->zipcode = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['PostalCode'][0]['#']);
            $this->view->phone = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['Phone'][0]['#']);
            $this->view->retail = htmlspecialchars($retail);
            $this->view->id = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['GetAccountInfo'][0]['#']['NewUID'][0]['#']);
            $this->view->pw = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['GetAccountInfo'][0]['#']['NewPW'][0]['#']);
            $this->view->pw2 = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['GetAccountInfo'][0]['#']['ConfirmPW'][0]['#']);
            $this->view->cob = htmlspecialchars($cob);
            $this->view->l4d = htmlspecialchars($l4d);
            $this->view->hs = htmlspecialchars($hs);
            $this->view->ans = htmlspecialchars($response['interface-response']['#']['NewAccount'][0]['#']['GetAccountInfo'][0]['#']['AuthQuestionAnswer'][0]['#']);
            $this->view->err = htmlspecialchars($response['interface-response']['#']['errors'][0]['#']['Err1'][0]['#']);
        }

        return $this->view->render('view.phtml');

    }

    function _processdata() {
        require_once 'library/CE/NE_MailGateway.php';

        if($_POST['accounttype'] == "0"){
           $EmailInfo = "checked";
        }else{
           $EmailInfo = "";
        }

        $arguments = array(
            'command'                         => 'CreateAccount',
            'uid'                             => $this->settings->get('plugin_enomform_Login'),
            'pw'                              => $this->settings->get('plugin_enomform_Password'),
            'NewUID'                          => $_POST['username'],
            'NewPW'                           => $_POST['password'],
            'ConfirmPW'                       => $_POST['password'],
            'AuthQuestionType'                => $_POST['secrettype'],
            'AuthQuestionAnswer'              => $_POST['sword'],
            'RegistrantAddress1'              => $_POST['address'],
            'RegistrantCity'                  => $_POST['city'],
            'RegistrantCountry'               => $_POST['country'],
            'RegistrantEmailAddress'          => $_POST['email'],
            'RegistrantEmailAddress_Contact'  => $_POST['email'],
            'RegistrantFirstName'             => $_POST['fname'],
            'RegistrantLastName'              => $_POST['lname'],
            'RegistrantJobTitle'              => $_POST['title'],
            'RegistrantOrganizationName'      => $_POST['org'],
            'RegistrantPhone'                 => $_POST['phone'],
            'RegistrantPostalCode'            => $_POST['zip'],
            'RegistrantStateProvince'         => $_POST['state'],
            'EmailInfo'                       => $EmailInfo,
            'AccountType'                     => $_POST['accounttype'],
            'Reseller'                        => $_POST['accounttype']
        );

        $params = array();
        $params['secure'] = true;

        if ($this->settings->get('plugin_enomform_Use ClientExec Account')) {
            $response = $this->_makeRequestThroughNE($arguments);
        } else {
            $response = $this->_make_request($params, $arguments);
        }

        if (is_a($response, 'CE_Error') || !$response || $response['interface-response']['#']['ErrCount'][0]['#'] > 0) {
            return $response;
        } elseif (!$this->settings->get('plugin_enomform_Use testing server') && !$this->settings->get('plugin_enomform_Use ClientExec Account')) {
            // email confirmation to admin
            $type = "Retail";
            if ($_POST['accounttype']) $type = "Reseller";
            $subject = "New eNom Signup - ".$type;
            $message = "A new ".$type." eNom account has been created with the username ".$_POST['username'].".  ";
            if ($_POST['accounttype']) {
                $subject = "*ACTION REQUIRED* ".$subject;
                $message .= "This account has been created as a retail account however a reseller account has been requested.  Please upgrade this account as soon as possible.  ";
            }
            $message .= "\n\nAccount details:\n";
            $skip = array("EmailInfo", "command", "uid", "pw");
            foreach ($arguments as $key=>$val) {
                if (!in_array($key, $skip)) $message .= "\n".$key.": ".$val;
            }
            $mailGateway = new NE_MailGateway();
            $mailGateway->mailMessageEmail( $message,
                                            $this->settings->get('Support E-mail'),
                                            $this->settings->get('Company Name'),
                                            $this->settings->get('plugin_enomform_Notification Email'),
                                            '',
                                            $subject);
        }
        return true;
    }

    function _make_request($params, $arguments)
    {
        require_once 'library/CE/NE_Network.php';

        if ($params['secure']) $request = 'https://';
        else $request= 'http://';

        if (@$this->settings->get('plugin_enomform_Use testing server')) $request .= 'resellertest.enom.com/interface.asp';
        else $request .= 'reseller.enom.com/interface.asp';

        $arguments['responsetype'] = 'XML';

        $i = 0;
        foreach ($arguments as $name => $value) {
            $value = urlencode($value);
            if (!$i) $request .= "?$name=$value";
            else $request .= "&$name=$value";
            $i++;
        }

        $response = NE_Network::curlRequest($this->settings, $request, false, false, true);
        if (!$response) return false;   // don't want xmlize an empty array

        $response = XmlFunctions::xmlize($response);

        return $response;
    }

    function _makeRequestThroughNE($arguments)
    {
        require_once 'library/CE/nusoap/nusoap.php';
        $parameters = array(    'domain'                        => $_SERVER['HTTP_HOST'],
                                'instance'                      => 'CLIENTEXEC',
                                'test'                          => $this->settings->get('plugin_enomform_Use testing server'),
                                'NewUID'                        => $arguments['NewUID'],
                                'NewPW'                         => $arguments['NewPW'],
                                'AuthQuestionType'              => $arguments['AuthQuestionType'],
                                'AuthQuestionAnswer'            => $arguments['AuthQuestionAnswer'],
                                'RegistrantAddress1'            => $arguments['RegistrantAddress1'],
                                'RegistrantCity'                => $arguments['RegistrantCity'],
                                'RegistrantCountry'             => $arguments['RegistrantCountry'],
                                'RegistrantEmailAddress'        => $arguments['RegistrantEmailAddress'],
                                'RegistrantEmailAddress_Contact'=> $arguments['RegistrantEmailAddress_Contact'],
                                'RegistrantFirstName'           => $arguments['RegistrantFirstName'],
                                'RegistrantLastName'            => $arguments['RegistrantLastName'],
                                'RegistrantJobTitle'            => $arguments['RegistrantJobTitle'],
                                'RegistrantOrganizationName'    => $arguments['RegistrantOrganizationName'],
                                'RegistrantPhone'               => $arguments['RegistrantPhone'],
                                'RegistrantPostalCode'          => $arguments['RegistrantPostalCode'],
                                'RegistrantStateProvince'       => $arguments['RegistrantStateProvince'],
                                'AccountType'                   => $arguments['AccountType']
                      );

        $soapclient = new nusoapclient('https://www.clientexec.com/members/library/enomapi.php');
        $tResult = $soapclient->call('createEnomAccount',$parameters);

        if ($tResult == '-1') {
            return false;
        }

        $response = XmlFunctions::xmlize($tResult);
        return $response;
    }
}
