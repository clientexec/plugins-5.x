<?php
require_once 'modules/admin/models/GatewayPlugin.php';
require_once 'modules/billing/models/Invoice.php';

/**
* @package Plugins
*/
class Plugin2checkout extends GatewayPlugin
{
    function getVariables()
    {
        $variables = array (
                   lang("Plugin Name") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("How CE sees this plugin (not to be confused with the Signup Name)"),
                                        "value"         =>lang("2Checkout")
                                       ),
                   lang("Seller ID") => array (
                                        "type"          =>"text",
                                        "description"   =>lang("ID used to identify you to 2checkout.com.<br>NOTE: This ID is required if you have selected 2checkout as a payment gateway for any of your clients."),
                                        "value"         =>""
                                       ),
                   lang("Secret Word") => array (
                                        "type"          =>"text",
                                        "description"   =>lang("'Secret Word' used to calculate the MD5 hash. <br>NOTE: Please take in count, you will also need to set the 'Secret Word' on the 2Checkout Site Management page, and it is to avoid frauds."),
                                        "value"         =>""
                                       ),
                   lang("Purchase Routine")=> array(
                                        "type"          => "options",
                                        "description"   => lang("This setting allows you to determine which purchase routine will be better suited for your site."),
                                        "options"       => array(0 => lang( "Standard Purchase Routine" ),
                                                                 1 => lang( "Single Page Checkout" )),
                                        "value"         => 0
                                       ),
                    lang("Demo Mode") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES if you want to set 2checkout into Demo Mode for testing. (<b>NOTE:</b> You must set to NO before accepting actual payments through this processor.)"),
                                        "value"         =>"0"
                                       ),
                   lang("Accept CC Number") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("Selecting YES allows the entering of CC numbers when using this plugin type. No will prevent entering of cc information"),
                                        "value"         =>"0"
                                       ),
                   lang("Visa") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES to allow Visa card acceptance with this plugin.  No will prevent this card type."),
                                        "value"         =>"0"
                                       ),
                   lang("MasterCard") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES to allow MasterCard acceptance with this plugin. No will prevent this card type."),
                                        "value"         =>"0"
                                       ),
                   lang("AmericanExpress") => array (
                                        "type"          =>"yesno",
                                        "description"   =>lang("Select YES to allow American Express card acceptance with this plugin. No will prevent this card type."),
                                        "value"         =>"0"
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
                                        "description"   =>lang("Select the name to display in the signup process for this payment type. Example: eCheck or Credit Card."),
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
                                        "value"         =>"0"
                                       ),
                   lang("30 Day Billing") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("Select YES if you want ClientExec to treat monthly billing by 30 day intervals.  If you select NO then the same day will be used to determine intervals."),
                                        "value"         =>"0"
                                       ),
                   lang("Check CVV2") => array (
                                        "type"          =>"hidden",
                                        "description"   =>lang("Select YES if you want to accept CVV2 for this plugin."),
                                        "value"         =>"0"
                                       )
        );
        return $variables;
    }

    function credit($params)
    { }

    function singlepayment($params)
    {
        //Function needs to build the url to the payment processor, then redirect
        //Plugin variables can be accesses via $params["plugin_[pluginname]_[variable]"] (ex. $params["plugin_2checkout_SellerID"])
        $tempInvoice = new Invoice($params['invoiceNumber']);

        $return_url = mb_substr($params['clientExecURL'],-1,1) == "//" ? $params['clientExecURL']."plugins/gateways/2checkout/callback.php" : $params['clientExecURL']."/plugins/gateways/2checkout/callback.php";

        if ($params["userCountry"]=="US") $params["userCountry"]="USA";

        $tPrice = $params["invoiceTotal"] - $params["invoiceSetup"];

        // Start building the URL that will be used to send customers to 2CO for payment.
        //OLD URL
        //$strURL = "https://www2.2checkout.com/2co/buyer/purchase";

        // NEW URLs
        if(isset($params["plugin_2checkout_Purchase Routine"]) && $params["plugin_2checkout_Purchase Routine"] == 1){
            $strURL = "https://www.2checkout.com/checkout/spurchase";
        }else{
            $strURL = "https://www.2checkout.com/checkout/purchase";
        }

        include_once 'modules/billing/models/Currency.php';
        $currency = new Currency($this->user);
        // Basic parameters
        $strURL .= "?x_login=".$params["plugin_2checkout_Seller ID"];
        $strURL .= "&x_invoice_num=".$params["invoiceNumber"];

        $currency->_loadCurrency($this->settings->get('Default Currency'));
        $formatedCurrency = sprintf("%01.".$currency->cache[$this->settings->get('Default Currency')]['precision']."f", round($params["invoiceTotal"], $currency->cache[$this->settings->get('Default Currency')]['precision']));
        $strURL .= "&x_amount=".$formatedCurrency;

        $strURL .= "&id_type=1";


        // Supported Currency Code:
        // ARS, AUD, BRL, GBP, CAD, DKK, EUR, HKD, INR, ILS, JPY, MYR, MXN, NZD, NOK, PHP, RON, RUB, SGD, ZAR, SEK, CHF, TRY, AED, USD
        // https://www.2checkout.com/documentation/checkout/parameters
        $strUrl .= "&currency_code=" . $this->user->getCurrency();

        // Product Creation code (so CE can send 2CO on-the-fly orders,
        // as it does not store 2CO information yet.)
        /*
        // IT SEEMS THIS PARAMS ARE NO LONGER NEEDED.
        $strURL .= "&c_prod=ce_".$params["invoiceNumber"];
        $strURL .= "&c_name=".$params["companyName"]." - Subscription";
        $strURL .= "&c_description=".$params["companyName"]." - Subscription";
        $strURL .= "&c_price=".$currency->format($this->settings->get('Default Currency'), $tPrice);
        $strURL .= "&c_tangible=N";
        */

        // ADDING DESCRIPTION TO THE PAYMENT
        //default description
        $strDescription = "Invoice #".$params['invoiceNumber'];
        $invoiceEntries = $tempInvoice->getInvoiceEntries();

        require_once 'modules/billing/models/InvoiceEntriesGateway.php';
        $InvoiceEntriesGateway = new InvoiceEntriesGateway($this->user);

        //let's build a better description
        foreach ($invoiceEntries as $entry) {
            //I really only want the main entry not the coupon so let's filter on type
            if ( (count($invoiceEntries) == 2) && ($entry->getBillingTypeID() == BILLINGTYPE_COUPON_DISCOUNT) ) {
                continue;
            } else if ( (count($invoiceEntries) > 2) && in_array($entry->getBillingTypeID(), array(BILLINGTYPE_COUPON_DISCOUNT,BILLINGTYPE_PACKAGE_ADDON))) {
                continue;
            }

            if ($strDescription == "Invoice #".$params['invoiceNumber'] || $entry->getBillingTypeID() == BILLINGTYPE_PACKAGE) {
                $strDescription = $InvoiceEntriesGateway->getFullEntryDescription($entry->getId());
            }

            if($entry->getBillingTypeID() == BILLINGTYPE_PACKAGE){
                break 1;
            }
        }

        if ($strDescription == "") {
            $strDescription = $tempInvoice->getDescription();
        }

        //(128 characters max)
        if(strlen($strDescription) > 128){
            $strDescription = substr($strDescription, 0, 125)."...";
        }
       // $strURL .= "&c_name=".$strDescription;
        // ADDING DESCRIPTION TO THE PAYMENT


        // If Demo Mode is set, pass appropriate parameter.
        if ($params["plugin_2checkout_Demo Mode"]==1)
            $strURL .= "&demo=Y";

        $strURL .= "&acc_can=Y&acc_int=Y&diff_ship=N&can_handling=0.00&int_handling=0.00&fixed=Y";

        // Billing Information so the 2checkout form is pre-filled.
        //$strURL .= "&card_holder_name=".$params["userFirstName"]." ".$params["userLastName"];
        $strURL .= "&x_First_Name=".$params["userFirstName"];
        $strURL .= "&x_Last_Name=".$params["userLastName"];
        $strURL .= "&x_Email=".$params["userEmail"];
        $strURL .= "&x_Address=".$params["userAddress"];
        $strURL .= "&x_City=".$params["userCity"];
        $strURL .= "&x_State=".$params["userState"];
        $strURL .= "&x_Zip=".$params["userZipcode"];
        $strURL .= "&x_Phone=".$params["userPhone"];
        $strURL .= "&x_Country=".$params["userCountry"];

        //$strURL .= "&credit_card_processed=";
        $strURL .= "&x_receipt_link_url=".$return_url;

        // Custom Parameters Passed thru 2CO back to CE
        if ($params['isSignup']==1)
            $strURL .= "&signup=1";
        else
            $strURL .= "&signup=0";
        $strURL .= "&ce_invoice_num=".$params['invoiceNumber'];

        $tInvoiceHash = $tempInvoice->generateInvoiceHash($params['invoiceNumber']);
        if(!is_a($tInvoiceHash, 'CE_Error')){
            $strURL .= "&ce_invoice_hash=".$tInvoiceHash;
        }else{
            $strURL .= "&ce_invoice_hash="."WRONGHASH";
        }

        // Send to 2CO for payment
        header("Location: $strURL");
        exit;
     }
}

?>
