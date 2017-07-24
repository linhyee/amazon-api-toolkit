#!/usr/bin/php
<?php
if (php_sapi_name() != 'cli') trigger_error('use in cli mode', E_USER_ERROR);

function usage() {
    global $argv; 
    $usage = [
        $argv[0] . ":\r\n",
        "\t -h help message\r\n",
        "\t -n <id> id or account name or short name\r\n",
        "\t -t <type> feed type\r\n",
        "\t --par=<true|false> PurgeAndReplace\r\n",
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

$opts = getopt('hn:t:',['par::', 'file::']);
foreach (array_keys($opts) as $opt) switch ($opt) {
    case 'h':
        usage();
        break;
    case 'n':
        $id = $opts['n'];
        break;
    case 't':
        $feedtype = $opts['t'];
        break;
    case 'par':
        $par = $opts['par'];
        break;
    case 'file':
        $file = $opts['file'];
        break;
}

if (!isset($feedtype) || !$feedtype) {
    usage();
}

if (!isset($file) || !$file) {
    usage();
} else if (!is_file($file)) {
    print 'File `'. $file . "`is not readable\n";
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

$baseurl = rtrim($account['service_url'], '/') . '/';
$action = "SubmitFeed";

$feedstr = file_get_contents($file);
if ($feedstr === false) {
    print 'Read file `'. $file ."` fail\r\n";
    exit(1);
}

$handle = @fopen('php://temp', 'rw+');
fwrite($handle, $feedstr);
rewind($handle);

$md5 = base64_encode(md5(stream_get_contents($handle), true));
rewind($handle);

$param = [
   'AWSAccessKeyId' => $account['aws_access_key_id'],
   'Action' => $action,
   'Merchant' => $account['merchant_id'],
   'MarketplaceId.Id.1' => $account['market_place_id'],
   'Version' => '2009-01-01',
   'ContentMD5Value' => $md5,
   'SignatureVersion' => "2",
   'SignatureMethod' => "HmacSHA256",
   'FeedType' => $feedtype,
   'Timestamp' => gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time()),
   'SellerId' => $account['merchant_id'],
   'PurgeAndReplace' => 'false',
];       

$t = [];
foreach($param as $key =>$val){
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
$link = $baseurl. '?' . $qs;

$ch = curl_init($link);

$header[] = 'Expect: ';
$header[] = 'Accept: ';
$header[] = 'Transfer-Encoding: chunked';
$header[] = 'Content-MD5: ' . $md5;
$header[] = 'Content-Type: text/xml;charset=UTF-8';

curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
curl_setopt($ch, CURLOPT_INFILE, $handle);
curl_setopt($ch, CURLOPT_UPLOAD, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_POST, true);

$response = curl_exec($ch);
$errno = curl_errno($ch);
if ($errno > 0) {
    print_r('Curl error: ' . curl_error($ch));
    exit(1);
}

$info = curl_getinfo($ch);
if ($info['http_code'] != 200) {
    echo pretty_xml($response);
    exit(1);
}

curl_close($ch);

$sxe = simplexml_load_string($response);

$rs = get_var($sxe, 'SubmitFeedResult');
if ($rs) {
    $info = get_var($rs, 'FeedSubmissionInfo');
    if ($info) {
        fwrite(STDOUT, str_repeat('#', 22)."\r\n");
        foreach (get_object_vars($info) as $key => $val) {
            fwrite(STDOUT, sprintf("%22s : %s\r\n", $key, $val));
        }
    }
}
exit(0);
