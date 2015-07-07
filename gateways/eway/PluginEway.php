<?php
require_once 'modules/admin/models/GatewayPlugin.php';

/**
* @package Plugins
*/
class PluginEway extends GatewayPlugin
{

    function getVariables()
    {
        /* Specification
               itemkey     - used to identify variable in your other functions
               type        - text,textarea,yesno,password
               description - description of the variable, displayed in ClientExec
        */

        $variables = array (
                    lang("Plugin Name") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("How CE sees this plugin (not to be confused with the Signup Name)"),
                                        "value"         =>lang("eWay")
                                       ),
                    lang("eWay Sandbox") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES if you want to set eWay into Test mode for testing. Even for testing you will need an eWay ID, that you can find at eWay's website."),
                                        "value"         =>"0"
                                       ),
                    lang("eWay ID") => array (
                                        "type"          =>"text",
                                        "description"   =>lang("Please enter your eWay Customer ID here"),
                                        "value"         =>""
                                       ),
                    lang("Accept CC Number") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("Selecting YES allows the entering of CC numbers when using this plugin type. No will prevent entering of cc information"),
                                        "value"         =>"1"
                                       ),
                   lang("Visa") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES to allow Visa card acceptance with this plugin.  No will prevent this card type."),
                                        "value"         =>"1"
                                       ),
                   lang("MasterCard") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES to allow MasterCard acceptance with this plugin. No will prevent this card type."),
                                        "value"         =>"1"
                                       ),
                   lang("AmericanExpress") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES to allow American Express card acceptance with this plugin. No will prevent this card type."),
                                        "value"         =>"1"
                                       ),
                   lang("Discover") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES to allow Discover card acceptance with this plugin. No will prevent this card type."),
                                        "value"         =>"0"
                                       ),
                   lang("Invoice After Signup") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES if you want an invoice sent to the customer after signup is complete."),
                                        "value"         =>"1"
                                       ),
                   lang("Signup Name") => array (
                                        "type"          =>"text",
                                        "description"   =>lang("Select the name to display in the signup process for this payment type. Example: eWay or Credit Card."),
                                        "value"         =>"Credit Card"
                                       ),
                   lang("Dummy Plugin") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("1 = Only used to specify a billing type for a customer. 0 = full fledged plugin requiring complete functions"),
                                        "value"         =>"0"
                                       ),
                   lang("Auto Payment") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("No description"),
                                        "value"         =>"1"
                                       ),
                   lang("30 Day Billing") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("Select YES if you want ClientExec to treat monthly billing by 30 day intervals.  If you select NO then the same day will be used to determine intervals."),
                                        "value"         =>"0"
                                       ),
                   lang("Check CVV2") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("Select YES if you want to accept CVV2 for this plugin."),
                                        "value"         =>"1"
                                       )
        );
        return $variables;
    }

    function singlepayment($params)
    {
        require_once 'library/CE/NE_Network.php';

        $tInvoice = new Invoice($params["invoiceNumber"]);
        $ewayId = trim($params["plugin_eway_eWay ID"]);

        //Transaction Information

        if($params["plugin_eway_eWay Sandbox"] == '1'){
            //  REPLACED WITH THE LINE BELOW  //
            //$priceWithoutCents = explode(".", $tInvoice->getPrice());
            //  REPLACED WITH THE LINE BELOW  //

            //  ADDED FOR: VARIABLE_PAYMENTS - Balance Due  //
            $priceWithoutCents = explode(".", $tInvoice->getBalanceDue());
            //  ADDED FOR: VARIABLE_PAYMENTS - Balance Due  //

            $totalAmount = $priceWithoutCents[0] * 100;
        }else{
            $totalAmount = sprintf("%01.2f", round($params["invoiceTotal"], 2)) * 100;
        }

        $cardHoldersname = $params["userFirstName"]." ".$params["userLastName"];
        $ccMonth = mb_substr($params["userCCExp"],0,2);
        $ccYear = mb_substr($params["userCCExp"],strpos($params["userCCExp"],"/")+3);
        $invoiceDescription = $tInvoice->getDescription();

        $currency = $params['currencytype'];
        if(!in_array($currency, array("AUD", "USD"))){
            $currency = "AUD";
        }

        $xmlCart = "<ewaygateway>";
        $xmlCart .= $this->CreateNode("ewayCustomerID", $ewayId);
        $xmlCart .= $this->CreateNode("ewayTotalAmount", $totalAmount);
        $xmlCart .= $this->CreateNode("ewayCardHoldersName", $cardHoldersname);
        $xmlCart .= $this->CreateNode("ewayCardNumber", $params["userCCNumber"]);
        $xmlCart .= $this->CreateNode("ewayCardExpiryMonth", $ccMonth);
        $xmlCart .= $this->CreateNode("ewayCardExpiryYear", $ccYear);
        $xmlCart .= $this->CreateNode("ewayTrxnNumber", "");
        $xmlCart .= $this->CreateNode("ewayCustomerInvoiceDescription", $invoiceDescription);
        $xmlCart .= $this->CreateNode("ewayCustomerFirstName", $params["userFirstName"]);
        $xmlCart .= $this->CreateNode("ewayCustomerLastName", $params["userLastName"]);
        $xmlCart .= $this->CreateNode("ewayCustomerEmail", $params["userEmail"]);
        $xmlCart .= $this->CreateNode("ewayCustomerAddress", $params["userAddress"]);
        $xmlCart .= $this->CreateNode("ewayCustomerPostcode", $params["userZipcode"]);
        $xmlCart .= $this->CreateNode("ewayCustomerInvoiceRef", $params["invoiceNumber"]);
        $xmlCart .= $this->CreateNode("ewayOption1", "");
        $xmlCart .= $this->CreateNode("ewayOption2", "");
        $xmlCart .= $this->CreateNode("ewayOption3", "");
        if($params["plugin_eway_Check CVV2"]){
            $xmlCart .= $this->CreateNode("ewayCVN", $params["userCCCVV2"]);
        }
        $xmlCart .= "</ewaygateway>";

        if($params["plugin_eway_eWay Sandbox"] == '1'){
            $requestUrl = "https://www.eway.com.au/gateway/xmltest/testpage.asp";
        }else{
            $requestUrl = "https://www.eway.com.au/gateway/xmlpayment.asp";
        }

        $transmit_response = NE_Network::curlRequest($this->settings, $requestUrl, $xmlCart, false, false, false);

        require_once 'library/CE/XmlFunctions.php';

        $xmlresponse = XmlFunctions::xmlize($transmit_response);

        require_once 'modules/billing/models/class.gateway.plugin.php';
        $cPlugin = new Plugin($params["invoiceNumber"], "eway", $this->user);
        $cPlugin->setAmount($params["invoiceTotal"]);
        $cPlugin->setAction('charge');

        require_once 'modules/billing/models/BillingGateway.php';
        $billingGateway = new BillingGateway($this->user);

        if(isset($xmlresponse['ewayResponse']['#']['ewayTrxnStatus'][0]['#'])){
            $cPlugin->m_TransactionID = $xmlresponse['ewayResponse']['#']['ewayTrxnNumber'][0]['#'];

            if($xmlresponse['ewayResponse']['#']['ewayTrxnStatus'][0]['#'] == "True"){
                $cPlugin->PaymentAccepted($params["invoiceTotal"], '('.$xmlresponse['ewayResponse']['#']['ewayTrxnError'][0]['#'].').', $xmlresponse['ewayResponse']['#']['ewayTrxnNumber'][0]['#']);

            }else{
                $cPlugin->PaymentRejected($xmlresponse['ewayResponse']['#']['ewayTrxnError'][0]['#']);
                return 'Payment rejected by credit card gateway provider';
            }
        }else{
            $cPlugin->PaymentRejected($this->user->lang("There was not response from eWay. Please double check your information"));
            return 'Payment rejected by credit card gateway provider';
        }
    }

    function CreateNode($NodeName, $NodeValue)
    {
        $node = "<" . $NodeName . ">" . $NodeValue . "</" . $NodeName . ">";
        return $node;
    }

    // Not supported?
    function credit($params) {
        return "";
    }
}
?>
