#!/usr/bin/php
<?php
if (php_sapi_name() != 'cli') trigger_error('use in cli mode', E_USER_ERROR);

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

if ($argc < 3) {
    fwrite(STDERR, sprintf("Usage: %s <id> <report_id>\r\n", $argv[0]));
    exit(1);
}

$id = $argv[1];
$reportid = $argv[2];
$filename = isset($argv[3]) ? $argv[3] : '';

$sql = 'SELECT * FROM account WHERE id=:id OR account_name=:id or short_name=:id LIMIT 1';

$sth = $pdo->prepare($sql);
$sth->bindParam(':id', $id, PDO::PARAM_STR);
$sth->execute();

$account = $sth->fetch(PDO::FETCH_ASSOC);

if ($account == false) {
    print 'Not found such account for id `'.$id.'`';
    exit(1);
}

$tmp = __DIR__.DS.'tmp';
if (!is_dir($tmp))
    mkdir($tmp, 0755);

$default_filename = $account['short_name'].'_report_'.date('Y_d_m_H', time()).'.xls';

if (!$filename)
    $filename = __DIR__.DS.TMP.DS.$default_filename;
else if (is_dir($filename))
    $filename = trim($filename, DS) . DS. $default_filename;
else if (!is_dir(dirname($filename))) {
    print 'Wrong saved path for `'.$argv[3].'`';
    exit(1);
}

$baseurl = rtrim($account['service_url'], '/') . '/';

$params = [
    'AWSAccessKeyId' => $account['aws_access_key_id'],
    'Action' => 'GetReport',
    'Merchant' => $account['merchant_id'],
    'MarketplaceId.Id.1' => $account['market_place_id'],
    'Version' => '2009-01-01',
    'SignatureVersion' => '2',
    'SignatureMethod' => 'HmacSHA256',
    'Timestamp' => gmdate('Y-m-d\TH:i:s.\\0\\0\\Z', time()),
    'SellerId' => $account['merchant_id'],
    'ReportId' => $reportid,
];

$t = [];

foreach ($params as $key => $val) {
    $key = str_replace('%7E', '~', rawurlencode($key));
    $val = str_replace('%7E', '~', rawurlencode($val));
    $t[] = "{$key}={$val}";
}

sort($t);
$qs = implode('&', $t);

$sign  = 'POST' . "\n";
$sign .= substr(trim($account['service_url'], '/'), 8). "\n";
$sign .= '/'."\n";
$sign .= $qs;       

$secret= $account['secret_key'];
$signature = hash_hmac("sha256", $sign, $secret, true);
$s64 = base64_encode($signature);
$signature = urlencode($s64);

$qs .= "&Signature=" . $signature;

$ch = curl_init($baseurl);

curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Accept:']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $qs);

$response = curl_exec($ch);

$errno = curl_errno($ch);
if ($errno > 0) {
    print 'Curl error: ' . curl_error($ch);
    exit(1);
}

$info = curl_getinfo($ch);
if ($info['http_code'] != 200) {
    $dom = new DOMDocument();
    $dom->loadXML($response);
    $dom->formatOutput = true;

    print $dom->saveXML();
    exit(1);
}

file_put_contents($filename, $response, LOCK_EX);
curl_close($ch);
exit(0);
