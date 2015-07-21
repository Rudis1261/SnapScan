<?php

// This file shows you sample usage of the SnapScan class I wrote
// SnapScan is a awesome service, and I would like to contribute to it's success
// Even if it just shows you possible usage

// Add the required files
require_once('class.qrcode.php');
require_once('class.snapscan.php');

// Initialize SnapScan. Used to be able to generate your Custom SnapScan
// QRCode
$snapMerchantId = "SnapSanID1232_test";
SnapScan::Init($snapMerchantId);

// Generating your custom QR Code. There are some options available
// Where amount is the only required parameter.
// $strict means that a client can overpay, but not overpay.
//
// QR($amount=false, $id=false, $strict=true, $size=300, $margin=0)
// Generate a R50 QRCode
$QRCode = SnapScan::QR(50);
?>

<img
    src="<?php echo $QRCode ?>"
    width="300"
    height="300"
    alt="ScanScan Barcode"
/>

<?php
// Generate the QR Code with a unique reference to keep track of the transaction
// Generate a R100 QRCode, with a Unique Reference
$uniqueId = "Super_Awesome_Unique_ID_101201";
$QRCode = SnapScan::QR(100, $uniqueId);
?>

<img
    src="<?php echo $QRCode ?>"
    width="300"
    height="300"
    alt="ScanScan Barcode"
/>

<?php

// To be able to do calls against the API, you will need to obtain a
// API Token from SnapScan, and specify it.
$snapToken = "0a63fca2-xxxx-xxxx-xxxx-xxxxxxxxxxxx";
SnapScan::setApiToken($snapToken);

// After you have set your API Token you can check that a transaction was successful
$uniqueId = "Super_Awesome_Unique_ID_101201";
$checkPayment = SnapScan::checkPayment($uniqueId);

// returns [] With payment details / False depending on whether the payment was successful
// Or fails miserably
if (!empty($checkPayment)) {
    echo "FOUND PAYMENT!!!" . PHP_EOL;
    echo print_r($checkPayment, true) . PHP_EOL;
} else {
    echo "PAYMENT NOT FOUND!" . PHP_EOL;
}
