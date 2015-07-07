<?php
require_once 'modules/admin/models/SnapinPlugin.php';
require_once 'library/CE/XmlFunctions.php';

class PluginOpensrsform extends SnapinPlugin
{
    public $title = 'Create OpenSRS Account';

    public function init()
    {
        $this->settingsNotes = lang('When enabled this snapin allows your customers the ability to obtain their own OpenSRS accounts on a self-service basis.');
        $this->addMappingForPublicMain("view", "Get OpenSRS", 'Integrate OpenSRS Signup Form in Public Home', 'icon-pencil', 'margin: 5px;');
        $this->addMappingForTopMenu('public', '', 'view', 'Get OpenSRS', 'Integrate OpenSRS Signup Form in Public Top Menu');
    }

    function getVariables()
    {
        $variables = array(
            'Plugin Name' => array(
                'type'        => 'hidden',
                'description' => '',
                'value'       => 'Get OpenSRS',
            ),
            lang('Snap-in header seen by customers') => array(
                'type'        => 'textarea',
                'description' => lang('Enter here the header that will be shown on top of the snap-in, explaining rates and such.'),
                'value'       => ''
            ),
            lang('Success message') => array(
                'type'        => 'textarea',
                'description' => lang('This is the message the customer will see after a successful OpenSRS account creation.'),
                'value'       => 'Congratulations! Your OpenSRS account has been created successfully.  You will receive an email notification with your login information shortly.<br><br>Thank you,<br>-CLIENT EXEC'
            ),
            'Login' => array(
                'type'        => 'text',
                'description' => lang('Enter your username for your OpenSRS reseller account.'),
                'value'       => '',
            ),
            'Private Key' => array(
                'type'        => 'text',
                'description' => lang('Enter the private key for your OpenSRS reseller account.'),
                'value'       => '',
            ),
            lang('Notification Email') => array(
                'type'        => 'text',
                'description' => lang('Enter the email address to which sign up details should be emailed to.'),
                'value'       => '',
            ),
            'Public Description'       => array(
                'type'        => 'hidden',
                'description' => 'Description to be seen by public',
                'value'       => 'Sign up for your openSRS reseller account today.',
            )
        );

        return $variables;
    }


    function view()
    {

        $this->overrideTemplate = true;

        if ( isset($_POST['submit']) ) {
            $this->session->postData = $_POST;
            if ( isset($_POST['agree']))  {
                $return = $this->_processdata();
                if ( $return === true ) {
                    CE_Lib::redirectPage('index.php?fuse=admin&view=snapin&controller=snapins&plugin=opensrsform&success=1&v=view');
                } else {
                    $this->session->error = $return;
                    CE_Lib::redirectPage('index.php?fuse=admin&view=snapin&controller=snapins&plugin=opensrsform&v=view');
                }
            } else {
                $this->session->error = $this->user->lang('Please agree to the TUCOWS MASTER SERVICES AGREEMENT');
                CE_Lib::redirectPage('index.php?fuse=admin&view=snapin&controller=snapins&plugin=opensrsform&v=view');
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

    function _showform($response = false)
    {

        if ( isset($_GET['success']) && $_GET['success'] == '1' ) {
            $this->view->successMessage = $this->settings->get('plugin_opensrsform_Success message');
        }

        $user = $this->user;

        $this->view->snapinHeader = $this->settings->get('plugin_opensrsform_Snap-in header seen by customers');

        include_once 'modules/admin/models/Countries.php';
        $countries = new Countries($this->user);

        if($response === false){
            if($user->getCountry()){
                $country = $user->getCountry();
            }else{
                $country = 'US';
            }
            $this->view->countries  = $countries->getCodes($country, true);
            $this->view->firstName = $user->getFirstName();
            $this->view->lastName = $user->getLastName();
            $this->view->email = $user->getEmail();
            $this->view->organization = $user->getOrganization();
            $this->view->address = $user->getAddress();
            $this->view->city = $user->getCity();
            $this->view->state = $user->getState();
            $this->view->zipcode = $user->getZipCode();
            $this->view->phone = $user->getPhone();
            $this->view->id = '';
            $this->view->assign(array(
                'fax'          => '',
                'PW'           => '',
                'ERR'          => ''
            ));

        }else{
            if($this->session->postData['country']){
                $country = $this->session->postData['country'];
            }else{
                $country = 'US';
            }
            $this->view->countries  = $countries->getCodes($country, true);
            $this->view->assign(array(
                'organization' => $this->session->postData['org_name'],
                'firstName'        => $this->session->postData['first_name'],
                'lastName'        => $this->session->postData['last_name'],
                'email'        => $this->session->postData['email'],
                'address'     => $this->session->postData['address1'],
                'city'         => $this->session->postData['city'],
                'state'        => $this->session->postData['state'],
                'zipcode'   => $this->session->postData['postal_code'],
                'phone'        => $this->session->postData['phone'],
                'fax'          => $this->session->postData['fax'],
                'id'           => $this->session->postData['username'],
                'PW'           => $this->session->postData['password']
            ));

            $errorMessage = '';

            if( isset($this->session->postData) && !isset($this->session->postData['agree']) ){
                $errorMessage = 'Please agree to the TUCOWS MASTER SERVICES AGREEMENT';
            }elseif(is_a($response, 'CE_Error')){
                $errorMessage = $response->getMessage();
            }elseif(($this->_get_item_value($response, 'is_success') !== '1'
              || $this->_get_item_value($response, 'response_code') === '404')
              && $this->_get_item_value($response, 'response_text') !== false){
                $errorMessage = $this->_get_item_value($response, 'response_text');
            }

            $this->view->assign(array(
                'ERR'          => $errorMessage
            ));

            unset($this->session->postData);
        }

        return $this->view->render('view.phtml');

    }

    function _processdata()
    {
        require_once 'library/CE/NE_MailGateway.php';

        $arguments = array(
            // commands
            'action'      => 'create',
            'object'      => 'reseller',

            // settings
            'uid'         => $this->settings->get('plugin_opensrsform_Login'),
            'pw'          => $this->settings->get('plugin_opensrsform_Private Key'),

            // admin email
            'admin_email' => $_POST['email'],

            // contact set
            'org_name'    => $_POST['org_name'],
            'first_name'  => $_POST['first_name'],
            'last_name'   => $_POST['last_name'],
            'email'       => $_POST['email'],
            'address1'    => $_POST['address1'],
            'address2'    => "",
            'address3'    => "",
            'city'        => $_POST['city'],
            'state'       => $_POST['state'],
            'country'     => $_POST['country'],
            'postal_code' => $_POST['postal_code'],
            'phone'       => $_POST['phone'],
            'fax'         => $_POST['fax'],
            'fqdn1'       => 'ns1.systemdns.com', //$_POST['fqdn1'],
            'fqdn2'       => 'ns2.systemdns.com', //$_POST['fqdn2'],
            'fqdn3'       => 'ns3.systemdns.com', //$_POST['fqdn3'],

            // account fields
            'username'    => $_POST['username'],
            'password'    => $_POST['password']
        );

        $response = $this->_make_request($arguments);

        if(is_a($response, 'CE_Error')
          || !$response
          || $this->_get_item_value($response, 'is_success') !== '1'
          || $this->_get_item_value($response, 'response_code') === '404'){
            // Return to the form, show the error message and repopulate the custom fields
            return $response;
        }else{
            // email confirmation to admin
            $subject = "New OpenSRS Signup";
            $message = "A new OpenSRS account has been created with the username ".$_POST['username'].".  ";
            $message .= "\n\nAccount details:\n";
            $skip = array("action", "object", "uid", "pw");

            foreach($arguments as $key => $val){
                if(!in_array($key, $skip)){
                    $message .= "\n".$key.": ".$val;
                }
            }

            $mailGateway = new NE_MailGateway();
            $mailGateway->mailMessageEmail(
                $message,
                $this->settings->get('Support E-mail'),
                $this->settings->get('Company Name'),
                $this->settings->get('plugin_opensrsform_Notification Email'),
                '',
                $subject
            );
        }

        // go to the success page
        $_REQUEST['success'] = 1;

        echo $this->view();
    }

    function _XMLrequest($arguments){
        require_once 'library/CE/NE_Network.php';

        $url = "rr-n1-tor.opensrs.net";

        // Port - 55000 or 55443 (non-ssl or ssl)
        $connPort = "55443";

        $xml = "<?xml version='1.0' encoding='UTF-8' standalone='no' ?>\n"
              ."<!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>\n"
              ."<OPS_envelope>\n"
              ."    <header>\n"
              ."        <version>0.9</version>\n"
              ."    </header>\n"
              ."    <body>\n"
              ."        <data_block>\n"
              ."            <dt_assoc>\n"
              ."                <item key='protocol'>XCP</item>\n"
              ."                <item key='action'>".$arguments['action']."</item>\n"
              ."                <item key='object'>".$arguments['object']."</item>\n"
              ."                <item key='attributes'>\n"
              ."                    <dt_assoc>\n"
              ."                        <item key='admin_email'>".$arguments['admin_email']."</item>\n"
              ."                        <item key='contact_set'>\n"
              ."                            <dt_assoc>\n"
              ."                                <item key='org_name'>".$arguments['org_name']."</item>\n"
              ."                                <item key='first_name'>".$arguments['first_name']."</item>\n"
              ."                                <item key='last_name'>".$arguments['last_name']."</item>\n"
              ."                                <item key='email'>".$arguments['email']."</item>\n"
              ."                                <item key='address1'>".$arguments['address1']."</item>\n"
              ."                                <item key='address2'>".$arguments['address2']."</item>\n"
              ."                                <item key='address3'>".$arguments['address3']."</item>\n"
              ."                                <item key='city'>".$arguments['city']."</item>\n"
              ."                                <item key='state'>".$arguments['state']."</item>\n"
              ."                                <item key='country'>".$arguments['country']."</item>\n"
              ."                                <item key='postal_code'>".$arguments['postal_code']."</item>\n"
              ."                                <item key='phone'>".$arguments['phone']."</item>\n"
              ."                                <item key='fax'>".$arguments['fax']."</item>\n"
              ."                            </dt_assoc>\n"
              ."                        </item>\n"
              ."                        <item key='nameservers'>\n"
              ."                            <dt_assoc>\n"
              ."                                <item key='fqdn1'>".$arguments['fqdn1']."</item>\n"
              ."                                <item key='fqdn2'>".$arguments['fqdn2']."</item>\n"
              ."                                <item key='fqdn3'>".$arguments['fqdn3']."</item>\n"
              ."                            </dt_assoc>\n"
              ."                        </item>\n"
              ."                        <item key='username'>".$arguments['username']."</item>\n"
              ."                        <item key='password'>".$arguments['password']."</item>\n"
              ."                    </dt_assoc>\n"
              ."                </item>\n"
              ."            </dt_assoc>\n"
              ."        </data_block>\n"
              ."    </body>\n"
              ."</OPS_envelope>";

        // Generate the signature
        $signature = md5(md5($xml.$arguments['pw']).$arguments['pw']);

        // Contruct the headers
        $header = "POST ".$url." HTTP/1.0\r\n";
        $header .= "Content-Type: text/xml\r\n";
        $header .= "X-Username: ".$arguments['uid']."\r\n";
        $header .= "X-Signature: ".$signature."\r\n";
        $header .= "Content-Length: ".strlen($xml)."\r\n\r\n";

        // Open the connection
        $fp = @fsockopen("ssl://$url", $connPort, $errno, $errstr, 30);
        if(!$fp){
            CE_Lib::log(1, "Couldn't connect to OpenSRS: $errno, $errstr");
            throw new Exception("OpenSRS API Error: Unable to communicate with OpenSRS. Please check your settings.");
        }

        // Send the request
        fputs($fp, $header.$xml);
        $response = "";

        // Gather the reply
        while(!feof($fp)){
            $response .= fgets ($fp, 1024);
        }

        // Close the file
        fclose($fp);

        // Log the reply
        CE_Lib::log(4, "OpenSRS response: ".$response);

        // drop the headers so we can xmlize it
        $arrResponse = explode("\n", $response);
        $response = "";
        $flag = false;

        foreach($arrResponse as $line){
            if($flag){
                $response .= $line."\n";
            }elseif(trim($line) == ""){
                $flag = true;
            }
        }

        // don't want xmlize an empty array
        if($response){
            $response = XmlFunctions::xmlize($response);
        }

        return $response;
    }

    function _make_request($arguments)
    {
        $response = $this->_XMLrequest($arguments);

        if(!$response){
            return false;
        }

        return $response;
    }

    function _get_item_value($response, $key)
    {
        if(isset($response['OPS_envelope']['#']['body'][0]['#']['data_block'][0]['#']['dt_assoc'][0]['#']['item'])){
            $item = $response['OPS_envelope']['#']['body'][0]['#']['data_block'][0]['#']['dt_assoc'][0]['#']['item'];

            foreach($item as $itemValue){
                if($itemValue['@']['key'] == $key){
                    return $itemValue['#'];
                }
            }
        }

        return false;
    }
}