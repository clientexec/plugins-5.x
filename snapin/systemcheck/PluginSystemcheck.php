<?php

//TODO on upgrade reenable this plugin

require_once 'modules/admin/models/SnapinPlugin.php';
include_once "plugins/snapin/systemcheck/SystemCheckGateway.php";

class PluginSystemcheck extends SnapinPlugin
{

    function getVariables()
    {
        $variables = array(
            lang('Plugin Name')       => array(
                'type'        => 'hidden',
                'description' => '',
                'value'       => 'System Check',
            )
        );
        return $variables;
    }

    function init()
    {
    	$this->setEnabledByDefault(true);
        $this->setSystemPlugin(true);
        $this->setDescription("Feature ensures basic installations are complete");
        $this->addMappingHook("admin_global_top","systemcheck","System Check", "Show missing settings from Clientexec installation");
        $this->addMappingView("listsystemchecks","");
    }

    function callAction($callback=true)
    {

        $gw = new SystemCheckGateway($this->user);

        switch($_REQUEST['pluginaction']) {
            case "deletecustomfields":
                // $gw->deleteCustomFields();
                // $this->send(Array(), false, $this->user->lang("Custom fields deleted"));
                break;
        }
    }

    function systemcheck()
    {

        if (CE_Lib::getActionName() == "viewtickets" && isset($_GET['id'])) {
            $this->view->padding_top = "38px";
        } else if (CE_Lib::getActionName() == "dashboard"){
            $this->view->padding_top = "0";
        } else {
            $this->view->padding_top = "15px";
        }

    }

    function disableIfComplete()
    {
        $complete = false;
        $gw = new SystemCheckGateway($this->user);
        //let's build an array of items we want to list with urls to where to update and description
        $aItems = array();
        //let's get possible issues with company settings
        $aItems = array_merge($aItems,$gw->checkBasicNeeds());
        $notcomplete = false;
        foreach ($aItems as $item) {
            if ($item['completed'] == 0) {
                $notcomplete = true;
            }
        }
        if (!$notcomplete) {
            $gw->disable_snapin();
            $complete = true;
        }
        return $complete;
    }

    function listsystemchecks()
    {
    	$this->disableLayout = true;

    	$gw = new SystemCheckGateway($this->user);
    	//let's build an array of items we want to list with urls to where to update and description
    	$aItems = array();

    	//let's get possible issues with company settings
    	$aItems = array_merge($aItems,$gw->checkBasicNeeds());

        $notcomplete = false;
        foreach ($aItems as $item) {
            if ($item['completed'] == 0) {
                $notcomplete = true;
            }
        }

        if (!$notcomplete) {
            $gw->disable_snapin();
        }

    	$this->view->checkitems = $aItems;

    }

}