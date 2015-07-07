<?php
require_once 'modules/admin/models/ExportPlugin.php';
require_once 'modules/admin/models/StatusAliasGateway.php' ;
/* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT *//*
require_once 'library/encrypted/Clientexec.php';
*//* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT */

/**
* @package Plugins
*/
class PluginClientdata extends ExportPlugin
{
    protected $_description = 'This export plugin exports customer profile data to a CSV file.';
    protected $_title = 'Customer Data CSV';

    function getForm()
    {
        $this->view->fields = array();
        $fields = $this->_getCustomersFields();
        for ($i = 0; $i < count($fields); $i++) {
            $this->view->fields[$i]['inputName'] = str_replace(array(' ', '_'), array('_', '__'), $fields[$i]['name']);
            $this->view->fields[$i]['fieldName'] = $this->user->lang($fields[$i]['name']);
            if ($fields[$i]['isRequired']) {
                $this->view->fields[$i]['checked'] = 'checked';
            } else {
                $this->view->fields[$i]['checked'] = '';
            }

            /* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT *//*
            if ($fields[$i] == 'Credit Card Number') {
                $this->tpl->setkey('onaction', 'onClick="rerquestPassphrase()"');
            }else{
                $this->tpl->setkey('onaction', '');
            }
            *//* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT */
        }

        return $this->view->render('PluginClientdata.phtml');
    }

    function _getCustomersFields()
    {
        $query = "SELECT name, isRequired FROM customuserfields WHERE (inSignup = 1 OR inSettings = 1) ORDER BY myOrder";
        $result = $this->db->query($query);
        $arrReturn = array(
            array(
                'name'       => 'id',
                'isRequired' => 1
            ),
            array(
                'name'       => 'Status',
                'isRequired' => 1
            ),
            array(
                'name'       => 'Date Created',
                'isRequired' => 1
            )
        );
        while ($row = $result->fetch()) {
            if ( $row['name'] == 'Full Name' || $row['name'] == 'Full Address') {
                continue;
            }


            $arrReturn[] = array(
                'name'       => $row['name'],
                'isRequired' => $row['isRequired']
            );
        }
        /* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT *//*
        $arrReturn = array_merge($arrReturn, array('Credit Card Number', 'Expiration Month', 'Expiration Year'));
        *//* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT */
        return $arrReturn;
    }

    function process($post)
    {
        $fields = array();
        foreach ($post as $fieldname => $value) {
            if (strpos($fieldname, 'clients_field_') === 0) {
                $fields[] = str_replace(array('__', '_'), array('_', ' '), mb_substr($fieldname, 14));
            }
        }
        if (!$fields) {
            CE_Lib::redirectPage("index.php?fuse=reports&view=ViewExport");
        }
        $csv = $this->_getCustomersCSV($fields, $post['passphrase']);
        CE_Lib::download($csv, $this->user->lang("customers").'.csv');
    }

    function _getCustomersCSV($fields, $passphrase)
    {
        $numFields = count($fields);
        $fieldsFiltered = array();
        $fieldstranslated = "";
        $numOfTheField = 1;
        foreach ($fields as $field) {
            $fieldsFiltered[] = $this->db->escape_string($field);
            if ($numOfTheField == $numFields) {
                $fieldstranslated .= '"'.$this->user->lang($field).'"';
            } else {
        	$fieldstranslated .= '"'.$this->user->lang($field).'",';
            }
            $numOfTheField ++;
        }
        $fields_str = implode("', '", $fieldsFiltered);
        $csv = $fieldstranslated. "\n";
        $users = array();
        $query = "SELECT u.id, u.status, u.dateActivated, cu.name, u_cu.value FROM users u, user_customuserfields u_cu, customuserfields cu "
                ."WHERE u.groupid = 1 AND u.id = u_cu.userid AND u_cu.customid = cu.id AND cu.name IN('$fields_str')";
        $result = $this->db->query($query);

        // special cases
        $showId = in_array('id', $fields);
        $showStatus = in_array('Status', $fields);
        $showDateCreated = in_array('Date Created', $fields);
        /* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT *//*
        $showCreditCardNumber = in_array('Credit Card Number', $fields);
        $showExpirationMonth = in_array('Expiration Month', $fields);
        $showExpirationYear = in_array('Expiration Year', $fields);
        *//* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT */

        while ($row = $result->fetch()) {
            if ($showId) {
                $users[$row['id']]['id'] = $row['id'];
            }
            if ($showStatus) {
                $status = $this->user->lang(StatusAliasGateway::getInstance($this->user)->getUserStatus($row['status'])->name);
                $users[$row['id']]['Status'] = $status;
            }
            if ($showDateCreated) {
                $users[$row['id']]['Date Created'] = CE_Lib::db_to_form($row['dateActivated'], $this->settings->get('Date Format'), '/');
            }
            /* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT *//*
            if ($showCreditCardNumber || $showExpirationMonth || $showExpirationYear) {
                $tempUser = new user($row['id']);

                if ($showCreditCardNumber) {
                    if(isset($passphrase)){
                        //Check Passphrase
                        if (Clientexec::getPassPhraseHash($this->settings) == md5($passphrase)) {
                            $users[$row['id']]['Credit Card Number'] = $tempUser->getCreditCardInfo(@$_REQUEST['passphrase']);
                        } else {
                            $users[$row['id']]['Credit Card Number'] = 'Invalid Passphrase';
                        }
                    } else {
                        $users[$row['id']]['Credit Card Number'] = '';
                    }
                }
                if ($showExpirationMonth) {
                    $users[$row['id']]['Expiration Month'] = $tempUser->getCCMonth();
                }
                if ($showExpirationYear) {
                    $users[$row['id']]['Expiration Year'] = $tempUser->getCCYear();
                }
            }
            *//* THIS CODE IS TO EXPORT CREDIT CARDS, BUT WE WILL DECIDE LATER HOW TO DO IT */

            $users[$row['id']][$row['name']] = $row['value'];
        }

        foreach ($users as $userItem) {
            for ($i = 0; $i < $numFields; $i++) {
                // I tried more elegant ways, but this one assures field order :-P
                $csv .= "\"";
                if(isset($fields[$i])) {
                    if(isset($userItem[$fields[$i]])) {
                    	$csv .= $userItem[$fields[$i]];
                    }
                }
                $csv .= "\"";
                if ($i == ($numFields - 1)) {
                    $csv .= "\n";
                } else {
                    $csv .= ",";
                }
            }
        }

        return $csv;
    }
}

?>
