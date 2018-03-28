#!/usr/bin/php
<?php
echo rawurlencode('http://baidu.com/2010-10-10/a?b=c e');exit;
if (php_sapi_name() != 'cli') trigger_error('use in cli mode', E_USER_ERROR);

function usage() {
    global $argv;
    $usage = [
        $argv[0] . ":\r\n",
        "\t -h help message\r\n",
        "\t -n <id> id or account anme or short name\r\n",
        "\t --id=<id> report request id\r\n",
    ];
    print implode('', $usage);
    exit(0);
}

function pretty_xml($str) {
    $dom = new DOMDocument();
    $dom->loadXML($str);
    $dom->formatOutput = true;
    return  $dom->saveXML();
}

function get_var(SimpleXMLElement $obj, $var) {
    if (property_exists($obj, $var)) {
        return $obj->$var;
    }
    return null;
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

$opts = getopt('ht:n:c:', ['id::','ack::', 'afd::', 'atd::']);
foreach (array_keys($opts) as $opt) switch ($opt) {
    case 'h':
        usage();
        break;
    case 'n':
        $id = $opts['n'];
        break;
    case 'id':
        $reqids = $opts['id'];
        break;
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

$baseurl = rtrim($account['service_url'], '/') . '/';
$action = "GetReportRequestList";
       
$param = [
    'AWSAccessKeyId' => $account['aws_access_key_id'],
    'Action' => $action,
    'Merchant' => $account['merchant_id'],
    'MarketplaceId.Id.1' => $account['market_place_id'],
    'Version' => '2009-01-01',
    'SignatureVersion' => '2',
    'SignatureMethod' => 'HmacSHA256',
    'Timestamp' => gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time()),
    'SellerId' => $account['merchant_id'],
    'MaxCount' => '20',
];

if (isset($reqids) && $reqids) {
    foreach (explode(',', $reqids) as $key => $val) {
        $param['ReportRequestIdList.Id.'.($key+1)] = $val;
    }
}

$t = [];

foreach($param as $key => $val) {
   $key = str_replace("%7E", "~", rawurlencode($key));
   $val = str_replace("%7E", "~", rawurlencode($val));
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
    print 'Curl error: '. curl_error($ch);
    exit(1);
}

$info = curl_getinfo($ch);
if ($info['http_code'] != 200) {
    echo pretty_xml($response);
    exit(1);
}

curl_close($ch);

$sxe = simplexml_load_string($response);

$rs = get_var($sxe, 'GetReportRequestListResult');
if ($rs) {
    $info = get_var($rs, 'ReportRequestInfo');
    if ($info) {
        foreach ($info as $val) {
            fwrite(STDOUT, str_repeat('#', 20)."\r\n");
            foreach (get_object_vars($val) as $k => $v) {
                fwrite(STDOUT, sprintf("%22s : %s\r\n", $k, $v));
            }
       }
   }

   $hn = get_var($rs, 'HasNext');
   $nt = get_var($rs, 'NextToken');

   fwrite(STDOUT, str_repeat('#', 20)."\r\n");
   fwrite(STDOUT, sprintf("%22s : %s\r\n", 'HasNext', $hn));
   fwrite(STDOUT, sprintf("%22s : %s\r\n", 'NextToken', $nt));
}
exit(0);