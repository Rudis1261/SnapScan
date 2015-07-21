<?php

/**
 * Author: Rudi Strydom (iam@thatguy.co.za)
 * Date: July 2015
 * Purpose: The purpose of this class is to be able to interact with SnapScan.
 * 1. To generate custom QRCode using an awesome class
 * 2. Check whether a payment with a specified reference has been completed
 *
 * References: https://developer.getsnapscan.com/#overview
 * SnapScan Site: http://www.snapscan.co.za/
 * Merchant Application Site: https://www.getsnapscan.com/merchant
 */
class SnapScan
{
    public $qr;
    public $qrEndPoint = "https://pos.snapscan.io/qr";
    public $apiEndPoint = "https://pos.snapscan.io/merchant/api/v1";
    private $apiToken = null;
    private $merchantId = null;
    private static $instance = null;

    public $allowedMethods = [];
    public $allowedActions = [];
    public $allowedFields = [];

    /**
     * Requires your MerchantID as you get it from SnapScan
     * @param string $merchantId eg: scanTest. You will need to have applied for a merchant
     * with SnapScan and gotten both a MerchantID from them as well as a API Token
     */
    function __construct($merchantId)
    {
        if (empty($merchantId)) {
            throw new Exception("No merchant id provided. Usage: SnapScan::init('merchantCode');", 1);
        }
        $this->merchantId = $merchantId;

        // Set the defaults
        $this->allowedFields = [
            'merchantReference',
            'startDate',
            'endDate',
            'status',
            'snapCode',
            'authCode',
            'snapCodeReference',
            'userReference',
            'statementReference'
        ];

        $this->allowedMethods = [
            'get',
            'post'
        ];

        $this->allowedActions = [
            'payments',
            'cash_ups'
        ];
    }

    static function Init($merchantId)
    {
        if (is_null(self::$instance)) {
            self::$instance = new SnapScan($merchantId);
        }
        return self::$instance;
    }

    static function setApiToken($token=false)
    {
        if (empty($token)) {
            throw new Exception("class.SnapScan.php |
                You can't use setApiToken($token) without providing a token", 1
            );
        }
        self::$instance->apiToken = $token;
    }

    static function checkPayment($reference=false, $field='merchantReference')
    {
        if (empty($reference)) {
            throw new Exception("class.SnapScan.php |
                We can't check a payment without a reference", 1
            );
        }

        if (empty($field) || !in_array($field, self::$instance->allowedFields)) {
            throw new Exception("class.SnapScan.php |
                checkPayment($method) Requires a valid field request, when provided.
                Options (".implode(', ', self::$instance->allowedFields).")", 1
            );
        }

        if (empty(self::$instance->apiToken)) {
            throw new Exception("class.SnapScan.php |
                checkPayment($reference) requires that you had used setApiToken($token) to set your API token", 1
            );
        }

        $params = [
            $field => $reference,
            'status' => 'completed'
        ];

        $getPayment = self::$instance->request('GET', "payments", $params);
        if (!empty($getPayment)) {
            return current($getPayment);
        }
        return false;
    }

    static function request($method="GET", $action="payments", $params=[])
    {
        $method = strtolower($method);
        $action = strtolower($action);

        if (empty($method) || !in_array($method, self::$instance->allowedMethods)) {
            throw new Exception("class.SnapScan.php |
                request($method) Requires a valid request method.
                Options(".implode(' ,', self::$instance->allowedMethods).")", 1
            );
        }

        // Ensure we have an API Token
        if (empty(self::$instance->apiToken)) {
            throw new Exception("class.SnapScan.php |
                request($method) requires that you had used setApiToken($token) to set your API token", 1
            );
        }

        // Ensure we have an API End Point
        if (empty(self::$instance->apiEndPoint) || filter_var(self::$instance->apiEndPoint, FILTER_VALIDATE_URL) === false) {
            throw new Exception("class.SnapScan.php |
                request($method) Failed to get the API EndPoint. This should be the same all the time really", 1
            );
        }

        // Ensure that the action is set
        if (empty($action) || !in_array($action, self::$instance->allowedActions)) {
            throw new Exception("class.SnapScan.php |
                request($method) Which action do you need to perform?
                Options (".implode(' ,', self::$instance->allowedActions).")", 1
            );
        }

        // Additional parameters
        $queryString = "";
        if ($method === 'get' && !empty($params)) {
            $queryString = "?".http_build_query($params);
        }

        // Assemble the final URL
        $finalUrl = self::$instance->apiEndPoint."/".$action.$queryString;

        // Actually do the query
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $finalUrl,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => self::$instance->apiToken . ":",
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT, 10
        ));
        $result = curl_exec($curl);
        $error = curl_error($curl);
        $curlInfo = curl_getinfo($curl);
        curl_close($curl);

        // Auth Issues
        if (!empty($curlInfo['http_code']) && $curlInfo['http_code'] == 401) {
            throw new Exception("class.SnapScan.php |
                Authorization Failed, Your API Token is most likely invalid", 1
            );
        }

        // Non-Success Response
        if (!empty($curlInfo['http_code']) && $curlInfo['http_code'] !== 200) {
            throw new Exception("class.SnapScan.php |
                Something went wrong with your request.
                Response Code {$curlInfo['http_code']}
                Response Text {$result}", 1
            );
        }

        // Other Curl issues
        if (!empty($error)) {
            throw new Exception("class.SnapScan.php |
                Call to SnapScan failed: error {$error}", 1
            );
        }

        if ($result) {
            $decode = json_decode($result, true);
            if (!empty($decode)) {
                return $decode;
            }
        }
        return false;
    }

    function getNominalValue($price)
    {
        if (!empty($price) && is_numeric($price)){
            $price = $price * 100;
            return (int)$price;
        }
        return $price;
    }

    static function QR($amount=false, $id=false, $strict=true, $size=300, $margin=0)
    {
        $query = '';
        $parts = [];

        // Ensure that we have data as well
        if (!empty($amount) && !is_numeric($amount)) {
            throw new Exception("class.SnapScan.php |
                QR Code :: expects $amount to be a number", 1
            );
            return '';
        } else {
            $amount = self::$instance->getNominalValue($amount);
            $parts['amount'] = $amount;
        }

        // Add the id to the url if we have it
        if (!empty($id)) {
            $parts['id'] = $id;
        }

        // Ensure we have a size
        if (!empty($size) && !is_numeric($size)) {
            throw new Exception("class.SnapScan.php |
                QR Code :: expects $size to be a number", 1
            );
            return '';
        }

        // Ensure we have a margin
        if (!empty($margin) && !is_numeric($margin)) {
            throw new Exception("class.SnapScan.php |
                QR Code :: expects $margin to be a number", 1
            );
            return '';
        }

        // Ensure we have extra components
        if (!empty($parts)) {
            $query = http_build_query($parts);
        }

        $data = urlencode(self::$instance->qrEndPoint.'/'.self::$instance->merchantId.'?'.$query);
        $QR = new QrCode($size, $data);
        $QR->setMargin($margin);

        $url = $QR->getUrl();
        if (empty($url)) {
            return '';
        }
        return $url;
    }
}