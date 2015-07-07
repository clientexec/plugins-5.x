<?php

define('GROUP_RESELLER', 0);
define('GROUP_DISTRIBUTOR', 3);
define('GROUP_ADMIN', 1);
define('GROUP_SUPERADMIN', 2);

require_once 'library/CE/LicenseDefenderAdminAPI.php';
require_once 'modules/admin/models/SnapinPlugin.php';

class PluginLicensedefender extends SnapinPlugin
{
    // var $username;
    var $hideOnDemo = true;

    function getVariables()
    {
        $variables = array(
            /*T*/'Plugin Name'/*/T*/       => array(
                'type'        => 'hidden',
                'description' => /*T*/''/*/T*/,
                'value'       => 'Reseller Panel',
            ),
            /*T*/'API Username'/*/T*/  => array(
                'type'        => 'text',
                'description' => /*T*/''/*/T*/,
                'value'       => '',
            ),
            /*T*/'API Password'/*/T*/  => array(
                'type'        => 'text',
                'description' => /*T*/''/*/T*/,
                'value'       => '',
            )
        );
        return $variables;
    }

    function init()
    {
        $this->setSettingLocation("clients");
        $this->addMappingForTopMenu("admin","clients|License Manager","licenses","Licenses","Manage Clientexec Licenses");
        $this->addMappingForTopMenu("admin","clients|License Manager","resellers","Resellers","Manage Clientexec Resellers");
    }

    /**
     * view
     * @return [type] [description]
     */
    function licenses()
    {
        $this->view->username = $this->getVariable('API Username');
        $this->view->resellerNames = $this->getResellerNames();
    }

    function resellers()
    {
        $this->view->username = $this->getVariable('API Username');
        $this->view->resellerNames = $this->getResellerNames();
    }

    function setupPlugin()
    {
        $username = $this->getVariable('API Username');
        $password = $this->getVariable('API Password');

        if ( $username == '' || $password == '' ) {
            CE_Lib::addErrorMessage($this->user->lang("Your reseller panel is not configured"));
            CE_Lib::redirectPage("index.php?fuse=admin&controller=settings&view=snapinsettings&plugin=licensedefender&settings=plugins_snapins&type=Snapins");
            return false;
        }

        $lDefender = new LicenseDefenderAdminAPI($username, $password, 'CLIENTEXEC');
        return $lDefender;
    }

    function callAction($callback=true)
    {
        $lDefender = $this->setupPlugin();

        if (!isset($_REQUEST['pluginaction'])) $_REQUEST['pluginaction'] = 'listitems';
        switch($_REQUEST['pluginaction']) {

            case "addeditReseller":
                $checkip = ($_REQUEST['checkip']=="true") ? "1" : "0";
                $iprange = (isset($_REQUEST['iprange'])) ? $_REQUEST['iprange'] : "";
                $result = $lDefender->addReseller("clientexec","",$_REQUEST['username'],
                    $_REQUEST['password'],$_REQUEST['license'],$_REQUEST['group'],
                    $checkip,$iprange,$_REQUEST['resellerid']);
                if ($result === false) {
                    $error = true;
                    $message = $this->user->lang('Error: %s', $lDefender->getError());
                } else {
                    $error = false;
                    $message = $this->user->lang('Reseller updated successfully');
                }
                $this->sendJson(array(),0,$error,$message);
                break;
            case "getReseller":
                $resellerid = (isset($_REQUEST['resellerid'])) ? $_REQUEST['resellerid'] : "0";
                if ($resellerid == 0) {
                    $error = true;
                    $message = $this->user->lang('Error: Resellerid was not passed properly');
                    $this->sendJson(array(),0,$error,$message);
                }else {
                    $result = $lDefender->getReseller($resellerid);
                    if ($result === false) {
                        $error = true;
                        $message = $this->user->lang('Error: %s', $lDefender->getError());
                        $this->sendJson(array(),0,$error,$message);
                    } else {
                        if ($result['iprange']!=""){
                            $result['iprange'] = base64_decode($result['iprange']);
                        }
                        $this->sendJson($result,1);
                    }
                }
                break;
            case "getLicenseDetails":
                $loginas = (isset($_REQUEST['loginas'])) ? $_REQUEST['loginas'] : "";
                $resellerid = (isset($_REQUEST['resellerid'])) ? $_REQUEST['resellerid'] : "0";
                $result = $lDefender->getLicenseDetails($_REQUEST['domain'],$loginas,$resellerid);
                if ($result === false) {
                    $error = true;
                    $message = $this->user->lang('Error: %s', $lDefender->getError());
                    $this->sendJson(array(),0,$error,$message);
                } else {
                    $this->sendJson($result,1);
                }
                break;
            case "deleteReseller":
                $resellerid = (isset($_REQUEST['resellerid'])) ? $_REQUEST['resellerid'] : "0";

                //don't allow deleting if resellerid is 0 or undefined
                if($resellerid == 0) {
                    $error = true;
                    $message = $this->user->lang('Error: Resellerid was not passed properly');
                } else {
                    $result = $lDefender->deleteReseller($resellerid);
                    if ($result === false) {
                        $error = true;
                        $message = $this->user->lang('Error: %s', $lDefender->getError());
                    } else {
                        $error = false;
                        $message = $this->user->lang('Domain reseller successfully');
                    }
                }
                $this->sendJson(array(),0,$error,$message);
                break;
            case "deleteDomains":
                $loginas = (isset($_REQUEST['loginas'])) ? $_REQUEST['loginas'] : "";
                $resellerid = (isset($_REQUEST['resellerid'])) ? $_REQUEST['resellerid'] : "0";
                $result = $lDefender->deleteDomain($_REQUEST['domain'],$loginas,$resellerid);
                if ($result === false) {
                    $error = true;
                    $message = $this->user->lang('Error: %s', $lDefender->getError());
                } else {
                    $error = false;
                    $message = $this->user->lang('Domain deleted successfully');
                }
                $this->sendJson(array(),0,$error,$message);
                break;
            case "updateLicense":
                $loginas = (isset($_REQUEST['loginas'])) ? $_REQUEST['loginas'] : "";
                $newdomain = $_REQUEST['licenses'];
                $olddomain = $_REQUEST['olddomain'];
                $resellerid = (isset($_REQUEST['resellerid'])) ? $_REQUEST['resellerid'] : "0";
                $type = isset($_POST['type'])? $_POST['type'] : '';
                if ($type == 1) {
                    $userlimit = 25;
                } else {
                    $userlimit = 0;
                }
                $result = $lDefender->editDomain($olddomain, $newdomain, $loginas, $resellerid, @$_POST['is_owned'], $userlimit, @$_POST['attributes']);
                if ($result === false) {
                    $error = true;
                    $message = $this->user->lang('Error: %s', $lDefender->getError());
                } else {
                    $error = false;
                    $message = $this->user->lang('Domain updated successfully');
                }
                $this->sendJson(array(),0,$error,$message);
                break;
            case "addLicense":
                $loginas = (isset($_REQUEST['loginas'])) ? $_REQUEST['loginas'] : "";
                $domains = $_REQUEST['licenses'];
                $type = isset($_POST['type'])? $_POST['type'] : '';
                if ($type == 1) {
                    $userlimit = 25;
                } else {
                    $userlimit = 0;
                }
                $result = $lDefender->addDomains($domains, '', $loginas, '', @$_POST['is_owned'],$userlimit, @$_POST['attributes']);
                if ($result === false) {
                    $error = true;
                    $message = $this->user->lang('Error: %s', $lDefender->getError());
                } else {
                    $error = false;
                    $message = "";
                }
                $this->sendJson(array(),0,$error,$message);
                break;
            case "getResellerList":
                //need to get resellerCount
                $searchkey = (isset($_REQUEST['searchkey'])) ? $_REQUEST['searchkey'] : "";
                $records = $lDefender->GetResellerCount($searchkey);
                $numRecs = $records['totalresellers'];
                $limit = (int)$_REQUEST['limit'];
                $start = (int)$_REQUEST['start'];
                $dir = (isset($_REQUEST['dir'])) ? $_REQUEST['dir']: "DESC";
                $sort = (isset($_REQUEST['dir'])) ? $_REQUEST['sort']: "license";
                if($start>=$limit) {
                    $page = $start/$limit + 1;
                } else {
                    $page = 1;
                }
                $result = $lDefender->fetchResellerList($limit, $page, $sort, $dir, $searchkey);
                if ($result == null){
                    $this->sendJson(array(), 0);
                } else {
                    $result['resellers'] = $this->findCustomFieldMatch($result['resellers']);
                    $this->sendJson($result['resellers'],$numRecs, false, '', array(), $records);
                }
                break;
            case "getDomains":
                $loginas = (isset($_REQUEST['loginas'])) ? $_REQUEST['loginas'] : "";
                $searchkey = (isset($_REQUEST['searchkey'])) ? $_REQUEST['searchkey'] : "";
                $records = $lDefender->GetLicenseCount($loginas, $searchkey);
                $numRecs = $records['used'];
                $limit = (int)$_REQUEST['limit'];
                $start = (int)$_REQUEST['start'];
                $dir = (isset($_REQUEST['dir'])) ? $_REQUEST['dir']: "ASC";
                $sort = (isset($_REQUEST['dir'])) ? $_REQUEST['sort']: "domain";
                if($start>=$limit) {
                    $page = $start/$limit + 1;
                } else {
                    $page = 1;
                }
                $result = $lDefender->fetchDomains($limit, $page, $sort, $dir,$loginas, $searchkey);
                if ($result == null){
                    $this->sendJson(array(), 0);
                } else {
                    foreach ( $result['licenses'] as $key => $value ) {
                        $result['licenses'][$key]['id'] = $key;
                    }
                    $this->sendJson($result['licenses'], $numRecs, false, '', $result['permissions'], $records);
                }
                break;
            case "getResellerNames":
                $newResellers = $this->getResellerNames();
                $this->sendJson($newResellers);
                break;
            default:
                break;
        }
    }

    function findCustomFieldMatch($resellerList)
    {
        include_once "modules/clients/models/UserPackageGateway.php";
        $gateway = new UserPackageGateway($this->user);
        $newList = array();

        foreach ($resellerList as $reseller)
        {
            if (isset($reseller['username'])) {
                $productinfo = $gateway->getPackageIdWhereCustomFieldMatches(6,$reseller['username']);
            }
            if($productinfo) {
                $reseller['userurl'] = "frmClientID=".$productinfo['CustomerId']."&packageid=".$productinfo['objectid'];
            } else {
                $reseller['userurl'] = "";
            }
            $newList[] = $reseller;
        }

        return $newList;
    }

    function sendJson($arrData, $totalData = 0, $error=false, $message = "", $permissions = array(), $records=array())
    {
        if ($error) {
            $arr = array("success" => false, "error"=>true, "message"=>$message, "total"=>$totalData, "data"=>$arrData, 'permissions' => $permissions, 'licensetotals' => $records);
        } else {
            $arr = array("success" => true, "error"=>false, "message"=>"", "total"=>$totalData, "data"=>$arrData, 'permissions'=> $permissions, 'licensetotals' => $records);
        }
        echo CE_Lib::jsonencode($arr);
    }

    private function getResellerNames()
    {
        $lDefender = $this->setupPlugin();
        $result = $lDefender->fetchDomains(1, 1, 'domain', 'ASC');
        $newResellers = array();
        if ( isset($result['resellers']) && is_array($result['resellers']) ) {
            foreach ($result['resellers'] as $reseller) {
                $reseller = array("username"=>  strtolower($reseller));
                $newResellers[] = $reseller;
            }
        }
        sort($newResellers);
        return $newResellers;
    }
}
