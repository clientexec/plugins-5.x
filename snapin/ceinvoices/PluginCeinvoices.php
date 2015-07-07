<?php

require_once 'modules/admin/models/SnapinPlugin.php';

class PluginCeinvoices extends SnapinPlugin
{

    function getVariables()
    {
        $variables = array(
            lang('Plugin Name')       => array(
                'type'        => 'hidden',
                'description' => '',
                'value'       => 'Invoices',
            )
        );
        return $variables;
    }

    function init()
    {
        $this->setDescription("This feature adds an invoice grid to a users package");
        $this->setPermissionLocation("billing");
        $this->addMappingHook("admin_profileproducttab","profileinvoice","Invoices", "Adds an invoice grid to each users package", -1);
    }

    function profileinvoice()
    {
    }
}