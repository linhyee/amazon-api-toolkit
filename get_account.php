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

if ($argc < 2) {
    fwrite(STDERR, sprintf("Usage: %s <id or account_name or short_name>\r\n", $argv[0]));
    exit(1);
}

$id = $argv[1];
$name = $argv[1];
$sql = 'SELECT * FROM `account` WHERE id=:id OR account_name LIKE :name OR short_name LIKE :name';
$sth = $pdo->prepare($sql);
$sth->bindParam(':id', $id, PDO::PARAM_STR);
$sth->bindValue(':name', '%'.$name.'%', PDO::PARAM_STR);
$sth->execute();

$found = $sth->fetchAll(PDO::FETCH_ASSOC);
if ($found == false) {
	fwrite(STDERR, 'NOT FOUND!'."\r\n");
	exit(1);
}

foreach ($found as $item) {
	fwrite(STDOUT, sprintf("%20s\r\n", str_repeat('#', 20)));

	foreach ($item as $key => $val) {
		fwrite(STDOUT, sprintf("%20s : %s\r\n", $key, $val));
	}

}

exit(0);
