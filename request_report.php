#!/usr/bin/php
<?php
if (php_sapi_name() != 'cli') trigger_error('use in cli mode', E_USER_ERROR);

function usage() {
    global $argv;
    $usage = [
        $argv[0] . ":\r\n",
        "\t -h help message\r\n",
        "\t -n <id> id or account anme or short name\r\n",
        "\t -t <type> report type\r\n",
        "\t --start_date=<date> start date\r\n",
        "\t --end_date=<date> end date\r\n",
        "\t --options=<options> report options\r\n",
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

function switch_time($time, $stz, $tdz, $fmt = "Y-m-d\\TH:i:s\\Z") {
    $datetime = new DateTime($time, new DateTimeZone($stz));
    $datetime->setTimezone(new DateTimeZone($tdz));

    return $datetime->format($fmt);
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

$opts = getopt('ht:n:', ['start_date::','end_date::', 'options::']);
foreach (array_keys($opts) as $opt) switch ($opt) {
    case 'h':
        usage();
        break;
    case 'n':
        $id = $opts['n'];
        break;
    case 't':
        $reprot_type = $opts['t'];
        break;    
    case 'start_date':
        $start_date = $opts['start_date'];
        break;
    case 'end_date':
        $end_date = $opts['end_date'];
        break;
    case 'options':
        $options = $options;
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

if (!isset($reprot_type) || !$reprot_type) usage();

$baseurl = rtrim($account['service_url'], '/') . '/';
$action = "RequestReport";
       
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
    'ReportType' => $reprot_type,
];

if (isset($start_date) && $start_date) {
    $param['StartDate'] = switch_time($start_date, 'PRC', 'UTC');
}
if (isset($end_date) && $end_date) {
    $param['EndDate'] = switch_time($end_date, 'PRC', 'UTC');
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

$rs = get_var($sxe, 'RequestReportResult');
if ($rs) {
    $info = get_var($rs, 'ReportRequestInfo');
    if ($info) {
        fwrite(STDOUT, sprintf("%24s\r\n", str_repeat('#', 20)));

        foreach (get_object_vars($info) as $key => $val) {
            fwrite(STDOUT, sprintf("%24s : %s\r\n", $key, $val));
        }
    }
}
exit(0);
