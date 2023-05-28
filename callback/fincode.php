<?php
require __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../../../includes/clientfunctions.php';

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

$redirectUrl = "<REDIRECT-URL>";
function redirectAfterSeconds($seconds, $redirectUrl) {
  header("refresh: $seconds; url=$redirectUrl");
  echo "<p>" . $seconds . "秒後にリダイレクトします。\nもしリダイレクトされない場合は、<a href='$redirectUrl'>こちら</a>をクリックしてください。<p/>";
}

if (!(isset($_GET['orderId'])))
{
  echo "<h2><span style='color: red;'>決済が確認できませんでした。</span></h2>";
  redirectAfterSeconds(3, $redirectUrl);
  return;
}

$orderId = $_GET['orderId'];
$invoiceId = str_replace(substr($orderId, 0, 8), "", $orderId);

$apiDomain = "https://api.test.fincode.jp";

$session = curl_init();
$headers = array(
  "Authorization: Bearer {$gatewayParams['secretKey']}",
  "Content-Type: application/json"
);

$code = 400;
$response = null;
$payTypes = [
  0 => "Card",
  1 => "Paypay",
  2 => "Konbini"
];
$payType = "";

for ($i = 0; $i <= count($payTypes) - 1; $i++)
{
  $payType = $payTypes[$i];
  curl_setopt($session, CURLOPT_URL, $apiDomain."/v1/payments/".$orderId."?pay_type=".$payTypes[$i]);
  curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($session);

  if (curl_getinfo($session, CURLINFO_RESPONSE_CODE) === 200)
  {
    if ($payTypes[$i] === "Konbini")
    {
      echo "<h2><span style='color: green;'>決済が完了しました。</span></h2>";
      redirectAfterSeconds(3, $redirectUrl);
      return;
    }

    $code = 200;
    break;
  }

  curl_close($session);
}

if ($code !== 200)
{
  echo "<h2><span style='color: red;'>決済の確認に失敗しました。</span></h2>";
  redirectAfterSeconds(3, $redirectUrl);
  return;
}

$data = json_decode($response);

$status = $data->status;
if ($status !== "AUTHORIZED")
{
  $message = [
    "CAPTURED" => "この決済は既に完了しています。",
    "CANCELED" => "この決済はキャンセルされています。",
    "CHECKED" => "有効性の確認中です。",
    "AWAITING_CUSTOMER_PAYMENT" => "支払いを行ってください。",
    "UNPROCESSED" => "支払いを行ってください。"
  ];

  echo "<h2><span style='color: red;'>" . $message[$status] . "</span></h2>";
  redirectAfterSeconds(3, $redirectUrl);
  return;
}

$accessId = $data->access_id;

$session = curl_init();

$requestParams = array(
  "pay_type" => $payType,
  "access_id" => $accessId
);
$requestParamsJson = json_encode($requestParams);

curl_setopt($session, CURLOPT_URL, $apiDomain."/v1/payments/".$orderId."/capture");
curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
curl_setopt($session, CURLOPT_POSTFIELDS, $requestParamsJson);

$response = curl_exec($session);
$data = json_decode($response);

addInvoicePayment((int) $invoiceId, (string) $data->id, (float) $data->amount, (float) 0.00, (string) $gatewayParams['paymentmethod']);

echo "<h2><span style='color: green;'>決済が完了しました。</span></h2>";
redirectAfterSeconds(3, $redirectUrl);

curl_close($session);