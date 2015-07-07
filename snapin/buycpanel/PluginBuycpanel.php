<?php

require_once 'modules/admin/models/SnapinPlugin.php';
require_once 'plugins/snapin/buycpanel/BuycPanel.php';

class PluginBuycpanel extends SnapinPlugin
{
    private $api;

    function getVariables()
    {
        $variables = array(
            'Plugin Name'       => array(
                'type'        => 'hidden',
                'description' => '',
                'value'       => 'BuycPanel Manager (Beta)',
            ),
            lang('Username')  => array(
                'type'        => 'text',
                'description' => lang('Enter your BuycPanel Username'),
                'value'       => '',
            ),
            lang('API Key')  => array(
                'type'        => 'text',
                'description' => lang('Enter your BuycPanel API Key'),
                'value'       => '',
            ),
            lang('Test Mode')  => array(
                'type'        => 'yesno',
                'description' => lang('Select yes if you wish to use BuycPanel\'s test environment.'),
                'value'       => '',
            )
        );
        return $variables;
    }

    function init()
    {
        $this->setSettingLocation("clients");
        $this->addMappingForTopMenu("admin","clients","licenses","BuycPanel Manager (Beta)","Manage BuycPanel Licenses");
        $this->addMappingView('license', 'Manage License');
    }

    private function setupPlugin()
    {
        $username = $this->getVariable('Username');
        $apiKey = $this->getVariable('API Key');
        $testMode = $this->getVariable('Test Mode');

        return new BuycPanel($username, $apiKey, $testMode);
    }

    function licenses()
    {

    }

    function license()
    {
        $this->disableLayout = true;
        $this->view->ip = '';
        $this->view->domain = '';
        $this->view->type = '';
        $this->view->editing = false;

        $id = $this->getParam('id', FILTER_SANITIZE_NUMBER_INT, 0);
        if ( $id > 0 ) {
            $this->view->editing = true;
            $actualLicense = null;
            $buycPanel = $this->setupPlugin();
            try {
                $licenses = $buycPanel->exportUsage();
            } catch ( CE_Exception $e ) {
                $this->sendJson(array(), 0, true, $e->getMessage());
            }

            foreach ( $licenses['result'] as $license ) {
                if ( $id == $license['package_id'] ) {
                    $actualLicense = $license;
                    break;
                }
            }
            $this->view->ip = $actualLicense['ip'];

        }
    }

    function callAction($callback=true)
    {
        $buycPanel = $this->setupPlugin();

        if (!isset($_REQUEST['pluginaction'])) $_REQUEST['pluginaction'] = 'list';
        switch($_REQUEST['pluginaction']) {

            case 'cancelLicense':
                $ip = $this->getParam('ip', FILTER_VALIDATE_IP);
                try {
                    $buycPanel->cancelLicense($ip);
                } catch ( CE_Exception $e ) {
                    $this->sendJson(array(), 0, true, $e->getMessage());
                    return;
                }

                $this->sendJson(array(), 0, false, $this->user->lang('Successfully saved license'));
                break;

            case 'savelicense':
                $ipaddress = $this->getParam('ipaddress', FILTER_VALIDATE_IP);
                $domain = $this->getParam('domain', FILTER_SANITIZE_STRING, '');
                $licenseType = $this->getParam('licensetype', FILTER_SANITIZE_NUMBER_INT, '');
                $id = $this->getParam('id', FILTER_SANITIZE_NUMBER_INT, 0);

                try {
                    if ( $id == 0 ) {
                        $buycPanel->saveLicense($ipaddress, $domain, $licenseType);
                    } else {
                        $currentIP = $this->getParam('currentip', FILTER_VALIDATE_IP);
                        $buycPanel->changeLicense($currentIP, $ipaddress);
                    }
                } catch ( CE_Exception $e ) {
                    $this->sendJson(array(), 0, true, $e->getMessage());
                    return;
                }

                $this->sendJson(array(), 0, false, $this->user->lang('Successfully saved license'));
                break;

            case 'list':
                // BuycPanel does not allow for limits, etc, so we just return all the licenses.
                $output = array();
                $filter = $_REQUEST['filter'];
                try {
                    $licenses = $buycPanel->exportUsage();
                } catch ( CE_Exception $e ) {
                    $this->sendJson(array(), 0, true, $e->getMessage());
                }

                foreach ( $licenses['result'] as $license ) {

                    if ( $filter == 'all' || $filter == strtolower($license['status']) ) {
                        $tempLicense = array();
                        $tempLicense['id'] = $license['package_id'];
                        $tempLicense['ip'] = $license['ip'];
                        $tempLicense['package'] = $license['package'];
                        $tempLicense['status'] = $license['status'];
                        $tempLicense['next_renewal'] = date($this->settings->get('Date Format'), strtotime($license['next_renewal']));
                        $output[] = $tempLicense;
                    }
                }

                $this->sendJson($output);
                break;

            default:
                break;
        }
    }


    function sendJson($arrData, $totalData = 0, $error=false, $message = "")
    {
        if ($error) {
            $arr = array("success" => false, "error"=>true, "message"=>$message, "data"=>$arrData);
        } else {
            $arr = array("success" => true, "error"=>false, "message"=>"", "data"=>$arrData);
        }
        echo CE_Lib::jsonencode($arr);
    }
}