<?php
require_once 'modules/admin/models/ImportPlugin.php';

class PluginWhmcs extends ImportPlugin
{
    protected $_description;
    protected $_name = 'whmcs';
    protected $_title = 'WHMCS 5.x';
    protected $_tplPath = 'PluginWhmcs.phtml';

    function __construct($user, $typeOfFetch = 1)
    {
        $this->_description = lang("This plugin imports customers, packages, invoices, servers from a WHMCS 5.x installation.");
        parent::__construct($user, $typeOfFetch);
    }
}
