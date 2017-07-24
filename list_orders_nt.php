#!/usr/bin/php
<?php
if (php_sapi_name() != 'cli') trigger_error('use in cli mode', E_USER_ERROR);

function usage() {
    global $argv;
    exit(1);
}

define('DS', DIRECTORY_SEPARATOR);
define('TMP', 'tmp');
date_default_timezone_set('PRC');

try {
    $pdo = new PDO('sqlite:'.__DIR__.DS.'udata.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    print $e->getMessage()."\n";
    exit(1);
}

$opts = getopt('hn:', ['token::']);
foreach (array_keys($opts) as $opt) switch ($opt) {
    case 'h':
        usage();
        break;
    case 'n':
        $id = $opts['n'];
        break;
    case 'token':
        $nexttoken = $opts['token'];
        break;
}

if (!isset($id) || !$id) {
    print 'Needed id or account name or short name'."\r\n";
    exit(1);
}
if (!isset($nexttoken) || !$nexttoken) {
    print "Needed next token\r\n";
    exit(1);
}

$sql = 'SELECT * FROM account WHERE id=:id OR account_name=:id or short_name=:id LIMIT 1';
$sth = $pdo->prepare($sql);
$sth->bindParam(':id', $id, PDO::PARAM_STR);
$sth->execute();

$account = $sth->fetch(PDO::FETCH_ASSOC);
if ($account == false) {
    print 'Not found such account for id`'.$id.'`';
    exit(1);
}       
       
$baseurl= rtrim($account['service_url'], '/') . '/'. '/Orders/2013-09-01';
$action = "ListOrdersByNextToken";

$param = array(
   'AWSAccessKeyId' => $account['aws_access_key_id'],
   'Action' => $action,
   'MarketplaceId.Id.1' => $account['market_place_id'],
   'Version' =>'2013-09-01',
   'SignatureVersion' => '2',
   'SignatureMethod' => 'HmacSHA256',
   'Timestamp' => gmdate('Y-m-d\TH:i:s.\\0\\0\\0\\Z', time()),
   'SellerId' =>$account['merchant_id'],
   'NextToken' =>$nexttoken
);

$t = [];
foreach($param as $key =>$val){
   $key = str_replace("%7E", "~", rawurlencode($key));
   $val = str_replace("%7E", "~", rawurlencode($val));
   $t[] = "{$key}={$val}";
}
sort($t);

$qs = implode('&', $t);

$sign  = 'POST' . "\n";
$sign .= substr(trim($account['service_url'], '/'), 8) . "\n";
$sign .= '/Orders/2013-09-01' . "\n";
$sign .= $qs;

$secret= $account['secret_key'];
$signature = hash_hmac("sha256", $sign, $secret, true);

$s64 = base64_encode($signature);
$signature = urlencode($s64);

$link  = $baseurl;
$qs .= "&Signature=" . $signature;

$ch = curl_init($link);

curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Accept:']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $qs);

$response = curl_exec($ch);
$info = curl_getinfo($ch);

curl_close($ch);
