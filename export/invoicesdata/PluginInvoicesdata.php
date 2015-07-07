<?php
require_once 'modules/admin/models/ExportPlugin.php';

/**
* @package Plugins
*/
class PluginInvoicesdata extends ExportPlugin
{
    protected $_description = 'This export plugin exports invoice data to a CSV file.';
    protected $_title = 'Invoice Data CSV';

    function getForm()
    {
        $this->view->fields = array();
        $fields = array(    'Invoice ID',
                            'Taxable Amount',
                            'Tax percentage',
                            'Total Amount Before Taxes',
                            'Tax amount',
                            'Total Amount After Taxes',
                            'Balance Due',
                            'Customer ID',
                            'Customer Name',
                            'Customer Last Name',
                            'Organization',
                            'Description',
                            'Bill Date',
                            'Date Paid',
                            'Payment Reference',
        );
        for ($i = 0; $i < count($fields); $i++) {
            $this->view->fields[$i]['inputName'] = str_replace(array(' ', '_'), array('_', '__'), $fields[$i]);
            $this->view->fields[$i]['fieldName'] = $this->user->lang($fields[$i]);
            $this->view->fields[$i]['checked'] = 'checked';
        }

        return $this->view->render('PluginInvoicesdata.phtml');
    }

    function process($post)
    {
        $fields = array();
        foreach ($post as $fieldname => $value) {
            if (strpos($fieldname, 'invoices_field_') === 0) {
                $fields[] = str_replace(array('__', '_'), array('_', ' '), mb_substr($fieldname, 15));
            }
        }
        if (!$fields) {
            CE_Lib::redirectPage("index.php?fuse=reports&action=ViewExport");
        }
        $csv = $this->_getInvoicesCSV($fields, $_POST['invoice_status']);
        CE_Lib::download($csv, $this->user->lang("invoices").'.csv');
    }

    function _getInvoicesCSV($fields, $status)
    {
        include_once 'modules/billing/models/Currency.php';

        $currency = new Currency($this->user);
        $numFields = count($fields);
        $fieldsMap = array(
            'Invoice ID'                => 'id',
            'Tax percentage'            => 'tax',
            'Total Amount Before Taxes' => 'subtotal',
            'Total Amount After Taxes'  => 'amount',
            'Balance Due'               => 'balance_due',
            'Description'               => 'description',
            'Bill Date'                 => 'billdate',
            'Date Paid'                 => 'datepaid',
            'Payment Reference'         => 'checknum',
        );
        $dbFields = array();
        foreach ($fieldsMap as $human => $machine) {
            if (in_array($human, $fields)) {
                $dbFields[] = $machine;
            }
        }
        if (!in_array('id', $dbFields)){
            $dbFields[] = 'id';
        }
        if (in_array('Tax amount', $fields)) {
            if (!in_array('tax', $dbFields)){
                $dbFields[] = 'tax';
            }
            if (!in_array('subtotal', $dbFields)){
                $dbFields[] = 'subtotal';
            }
            if (!in_array('amount', $dbFields)){
                $dbFields[] = 'amount';
            }
        }
        $dbFields[] = 'customerid';
        $dbFields = implode(", ", $dbFields);
        $query = "SELECT $dbFields FROM invoice";
        if ($status == 'paid') {
            $query .= " WHERE status = 1";
        } elseif ($status == 'unpaid') {
            $query .= " WHERE (status = 0 OR status = 5)";
        }
        $result = $this->db->query($query);
        $fieldstranslated = "";
        $numOfTheField = 1;
        foreach ($fields as $field) {
            if ($numOfTheField == $numFields) {
                $fieldstranslated .= '"'.$this->user->lang($field).'"';
            } else {
        	$fieldstranslated .= '"'.$this->user->lang($field).'",';
            }
            $numOfTheField ++;
        }
        $csv = $fieldstranslated. "\n";
        while ($row = $result->fetch()) {
            for ($i = 0; $i < $numFields; $i++) {
                if ($fields[$i] == 'Customer Name') {
                    $query = "SELECT value "
                            ."FROM user_customuserfields uc "
                            .'LEFT JOIN customuserfields c ON uc.customid=c.id '
                            ."WHERE uc.userid = '{$row['customerid']}' AND c.type=?";
                    $result2 = $this->db->query($query, typeFIRSTNAME);
                    $row2 = $result2->fetch();
                    $csv .= "\"{$row2['value']}\"";
                } elseif ($fields[$i] == 'Customer Last Name') {
                    $query = "SELECT value "
                            ."FROM user_customuserfields uc "
                            .'LEFT JOIN customuserfields c ON uc.customid=c.id '
                            ."WHERE uc.userid = '{$row['customerid']}' AND c.type=?";
                    $result2 = $this->db->query($query, typeLASTNAME);
                    $row2 = $result2->fetch();
                    $csv .= "\"{$row2['value']}\"";
                } elseif ($fields[$i] == 'Customer ID') {
                    $csv .= "\"{$row['customerid']}\"";
                } elseif ($fields[$i] == 'Taxable Amount') {
                    $csv .= "\"".$currency->format($this->settings->get('Default Currency'), $this->_getTaxableAmount($row['id']))."\"";
                } elseif ($fields[$i] == 'Total Amount Before Taxes') {
                    $csv .= "\"".$currency->format($this->settings->get('Default Currency'), $row['subtotal'])."\"";
                } elseif ($fields[$i] == 'Tax amount') {
                    $taxAmount = $row['amount'] - $row['subtotal'];
                    $csv .= "\"".$currency->format($this->settings->get('Default Currency'), $taxAmount)."\"";
                } elseif ($fields[$i] == 'Total Amount After Taxes') {
                    $csv .= "\"".$currency->format($this->settings->get('Default Currency'), $row['amount'])."\"";
                } elseif ($fields[$i] == 'Balance Due') {
                    $csv .= "\"".$currency->format($this->settings->get('Default Currency'), $row['balance_due'])."\"";
                } elseif ($fields[$i] == 'Organization') {
                    $query = "SELECT value "
                            ."FROM user_customuserfields uc "
                            .'LEFT JOIN customuserfields c ON uc.customid=c.id '
                            ."WHERE uc.userid = ? AND c.type=?";
                    $result2 = $this->db->query($query, $row['customerid'], typeORGANIZATION);
                    $row2 = $result2->fetch();
                    $csv .= "\"{$row2['value']}\"";
                } else {
                    $csv .= '"' . $row[$fieldsMap[$fields[$i]]] . '"';
                }
                if ($i == ($numFields - 1)) {
                    $csv .= "\n";
                } else {
                    $csv .= ",";
                }
            }
        }
	$csv = str_replace('Invoice #', $this->user->lang('Invoice #'), $csv);
        return $csv;
    }

    function _getTaxableAmount($invoiceId)
    {
        $total = 0;
        $query = "SELECT price, taxable FROM invoiceentry WHERE invoiceid=?";
        $result = $this->db->query($query, $invoiceId);
        while ($row = $result->fetch()) {
            if ($row['taxable'] == '1') {
                $total += $row['price'];
            }
        }

        return $total;
    }
}

?>
