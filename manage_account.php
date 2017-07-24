#!/usr/bin/php
<?php
if (php_sapi_name() != 'cli') trigger_error('use in cli mode', E_USER_ERROR);

define('DS', DIRECTORY_SEPARATOR);
define('TMP', 'tmp');
date_default_timezone_set('PRC');

try {
	$pdo = new PDO('sqlite:'.__DIR__.DS.'udata.db');
} catch (PDOException $e) {
	print $e->getMessage() . "\n";
	exit(1);
}


class Account
{
	public static function add(array $attributes) {
		global $pdo;

		foreach (array_keys($attributes) as $index => $field) {
			$vars[] = '`' . $field . '`';
			$vals[] = ':ycp' . $index;
		}

		$sql = sprintf('INSERT INTO `account` (%s) VALUES (%s)', implode(',', $vars), implode(',', $vals));
		$sth = $pdo->prepare($sql);

		if ($sth) return false;

		foreach (array_values($attributes) as $index => $value) {
			$sth->bindParam(':ycp', $value);
		}

		return $sth->execute();
	}

	public static function update(array $attributes, $pk) {
		global $pdo;

		foreach (array_keys($attributes) as $index => $field) {
			$update[] = '`'.$field.'`='.':ycp'.$index;
		}

		$sql = sprintf('UPDATE `account` SET %s WHERE `id`=:ycp OR `account_name`=:ycp OR `short_name`=:ycp', implode(',', $update));
		$sth = $pdo->prepare($sql);

		if ($sth) return false;

		foreach (array_values($attributes) as $index => $value)  {
			$sth->bindParam(':ycp' . $index, $value, PDO::PARAM_STR);
		}

		$sth->bindParam(':ycp', $pk, PDO::PARAM_STR);

		return $sth->execute();
	}

	public static function find($condition)
	{
		global $pdo;
		$sql = 'SELECT * FROM `%s` WHERE `id`=:ycp OR `account_name`=:ycp OR `short_name` LIMIT 1';
		$sth = $pdo->prepare($sql);
		$sth->bindParam(':ycp', $condition, PDO::PARAM_STR);
		$sth->execute();

		return $sth->fetch(PDO::FETCH_ASSOC);
	}
}

function display() {
}

Account::add(['id' => 2]);