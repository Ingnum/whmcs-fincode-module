<?php

# Fincode Payment Gateway Module

if (!defined("WHMCS")) die("This file cannot be accessed directly");

require __DIR__ . '/vendor/autoload.php';

function fincode_MetaData()
{
    return array(
        'DisplayName' => 'Fincode Payment Gateway Module',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function fincode_config()
{
  return array(
    'FriendlyName' => array(
      'Type' => 'System',
      'Value' => 'Fincode'
    ),
    'publicKey' => array(
      'FriendlyName' => 'パブリックキー',
      'Type' => 'text',
      'Size' => '200',
    ),
    'secretKey' => array(
      'FriendlyName' => 'シークレットキー',
      'Type' => 'password',
      'Size' => '200',
    ),
    'redirectUrlOnSuccess' => array(
      'FriendlyName' => 'リダイレクトURL（成功時）',
      'Type' => 'text',
      'Size' => '60',
    ),
    'redirectUrlOnCancel' => array(
      'FriendlyName' => 'リダイレクトURL（キャンセル時）',
      'Type' => 'text',
      'Size' => '60',
    ),
  );
}

function fincode_link($params)
{
  $session = curl_init();

  $apiDomain = "https://api.test.fincode.jp";
  $apiPath = "/v1/sessions";
  curl_setopt($session, CURLOPT_URL, $apiDomain.$apiPath);
  curl_setopt($session, CURLOPT_POST, true);

  $headers = array(
    "Authorization: Bearer {$params['secretKey']}",
    "Content-Type: application/json"
  );
  curl_setopt($session, CURLOPT_HTTPHEADER, $headers);

  $orderId = (string) rand(0, 99999999) . $params['invoiceid'];
  $requestParams = array(
    "success_url" => (string) $params['redirectUrlOnSuccess'] . "?orderId=" . $orderId,
    "cancel_url" => (string) $params['redirectUrlOnCancel'],
    "shop_service_name" => "<SERVICE-NAME>",
    "transaction" => [
      "amount" => (string) floatval($params['amount']),
      "pay_type" => ["Card", "Konbini", "Paypay"],
      "order_id" => $orderId,
    ],
    "konbini" => [
      "payment_term_day" => 3,
      "konbini_reception_mail_send_flag" => "0",
    ]
  );
  $requestParamsJson = json_encode($requestParams);
  curl_setopt($session, CURLOPT_POSTFIELDS, $requestParamsJson);
  curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($session);
  $data = json_decode($response);
  $url = $data->link_url;

  $htmlOutput = '<form method="get" action="' . $url . '" name="paymentfrm">';
  $htmlOutput .= '<button id="btnPayNow" class="btn btn-success btn-sm" type="submit" value="Submit">支払う</button>';
  $htmlOutput .= '</form>';

  curl_close($session);

  return $htmlOutput;
}