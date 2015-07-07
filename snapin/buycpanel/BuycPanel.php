<?php

    class BuycPanel {

        private $username;
        private $apiKey;
        private $testMode;
        private $url = 'http://www.buycpanel.com/api/';

        public function __construct($username, $apiKey, $testMode)
        {
            $this->username = $username;
            $this->apiKey = $apiKey;
            $this->testMode = $testMode;
        }

        public function exportUsage()
        {
            $usage = $this->doRequest('export.php');
            return $usage;
        }

        public function cancelLicense($ipaddress)
        {
            $args = array();
            $args['currentip'] = $ipaddress;
            $request = $this->doRequest('cancel.php', $args);
        }

        public function saveLicense($ipaddress, $domain, $licenseType)
        {
            $args = array();
            $args['serverip'] = $ipaddress;
            $args['domain'] = $domain;
            $args['ordertype'] = $licenseType;
            $request = $this->doRequest('order.php', $args);
        }

        public function changeLicense($currentIP, $ipaddress)
        {
            $args = array();
            $args['newip'] = $ipaddress;
            $args['currentip'] = $currentIP;
            $request = $this->doRequest('changeip.php', $args);
        }

        private function doRequest($cmd, $args=array())
        {
            $fullUrl = $this->url . $cmd;
            $args['login'] = $this->username;
            $args['key'] = $this->apiKey;
            $args['test'] = $this->testMode;

            CE_Lib::log(4, 'cURL Request to: ' . $fullUrl);
            CE_Lib::log(4, 'Args: ' . print_r($args, true));
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $fullUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/../../../library/cacert.pem');
            $data = curl_exec($ch);
            if ( $data === false ) {
                $error = "BuycPanel API Request / cURL Error: ".curl_error($ch);
                CE_Lib::log(4, $error);
                throw new CE_Exception($error);
            }
            curl_close($ch);
            $response = json_decode($data, true);


            CE_Lib::log(4, 'cURL Response: ' . print_r($response,true));
            if ( $response['success'] == 1 ) {
                return $response;
            } else {
                throw new CE_Exception($response['faultstring']);
            }
        }
    }