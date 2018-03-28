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
    public $attributes = [
        'id',
        'account_name',
        'short_name',
        'merchant_id',
        'market_place_id',
        'aws_access_key_id',
        'secret_key',
        'service_url',
        'status',
        'site',
        'sort',
    ];

    public $pk = 'id';
    public $new = true;
    public $table = 'account';

    public function __get($key) {
        if (property_exists(get_class($this), $key))
            return $this->$key;

        return null;
    }

    public function save() {
        global $pdo;

        $pkpos = array_search($this->pk, $this->attributes);

        foreach ($this->attributes as $index => $field) {
            if ($this->new) {
                $vars[] = '`' . $field . '`';
                $vals[] = ':ycp' . $index;
            } else {
                $vars[] = '`'.$field.'`='.':ycp'.$index;
            }
        }

        $sql = $this->new ? 'INSERT INTO `'.$this->table.'` ('.implode(',', $vars).') VALUES ('.implode(',', $vals).')' :
            'UPDATE `'.$this->table.'` SET '.implode(',', $vars).' WHERE `'.$this->pk.'`=:ycp'.$pkpos;
        $sth = $pdo->prepare($sql);

        if (!$sth) return false;

        foreach ($attributes as $key => $field)  {
            $sth->bindParam(':ycp'.$index, $this->$field, PDO::PARAM_STR);
        }

        return $sth->execute();
    }

    public static function findAll($condition = null) {
        global $pdo;
        $class = get_called_class();
        $obj = new $class();
        $sql = 'SELECT * FROM `'.$obj->table.'`';

        unset($obj);
    }

    public static function find($condition) {
        global $pdo;

        $class = get_called_class();
        $obj = new $class; //unreasonable, waste memory

        $sql = 'SELECT * FROM `'.$obj->table.'` WHERE `id`=:ycp OR `account_name`=:ycp OR `short_name`=:ycp LIMIT 1';

        $sth = $pdo->prepare($sql);
        if (!$sth) return null;

        $sth->bindParam(':ycp', $condition, PDO::PARAM_STR);
        $sth->execute();

        $row = $sth->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $obj->new = false;

        foreach ($obj->attributes as $field) $obj->$field = $row[$field];

        return $obj;
    }

    public static function delete($id)
    {
        global $pdo;
        $class = get_called_class();
        $obj = new $class();

        $sql = 'SELECT * FROM `'.$obj->table.'` WHERE `id`=:id';
        $sth = $pdo->prepare($sql);

        unset($obj);

        return $sth->execute();
    }
}

class Act
{
    public $account = null;

    public function __construct()
    {
        $this->account = new Account;
    }

    public function actionList($offset = 1, $limit = 2)
    {
        global $pdo;
        $sql = 'SELECT COUNT(*) AS `c` FROM `'.$this->account->table.'`';
        $sth = $pdo->query($sql);

        $total = $sth->fetch(PDO::FETCH_ASSOC)['c'];
        $pages = ceil($total / $limit);

        $offset = $offset > $pages ? $pages : $offset;
        $offset = $offset < 1 ? 1 : $offset;
        $offset = ($offset -1) * $limit;

        $sql = 'SELECT * FROM `'.$this->account->table.'` LIMIT '.$offset.', '.$limit;
        $sth = $pdo->prepare($sql);
        $sth->execute();
        $rs = $sth->fetchAll(PDO::FETCH_ASSOC);

        if ($rs) {
            foreach ($rs as $idx => $val) {
                fwrite(STDOUT, sprintf("%20s\r\n", str_repeat('#', 20)));
                foreach ($val as $k => $v)
                    fwrite(STDOUT, sprintf("%20s : %s\r\n", $k, $v));
            }
        }

        return $rs ? TRUE:FALSE;
    }

    public function actionInsert()
    {
    }

    public function actionUpdate()
    {
    }

    public function actionSearch($keyword)
    {
        $found = Account::find($keyword);

        fwrite(STDOUT, sprintf("%20s\r\n", str_repeat('#', 20)));
        foreach ($found->attributes as $value) {
            fwrite(STDOUT, sprintf("%20s : %s\r\n", $value, $found->$value));
        }

        return $found ? TRUE:FALSE;
    }
}

class UI
{
    public $currPage = 1;   

    public $act = null;

    public function __construct ()
    {
        $this->act = new Act;
    }

    public function listing() {
        if (!$this->act->actionList()) return;

        for (;;) {
            echo "************************\r\n";
            echo "* 1.Prev 2.Next q.Quit *\r\n";
            echo "************************\r\n";
            echo "Input:";

            switch (trim(fgets(STDIN))) {
                case '1':
                    $this->act->actionList(--$this->currPage);
                    break;
                case '2':
                    $this->act->actionList(++$this->currPage);
                    break;
                case 'q':
                    goto quit;
                    break;
                default:
                    echo "************************\r\n";
                    echo "* WRONG INPUT!         *\r\n";
                    echo "************************\r\n";
                    break;
            }
        }
        quit:
        $this->currPage = 1;
    }

    public function enter() {
        echo "******************\r\n";
        echo "Enter:";
    }

    public function find() {
        echo "*****************\r\n";
        echo "Search:";

        $keyword = trim(fgets(STDIN));
        $this->act->actionSearch($keyword);
    }

    public function delete() {
        echo "*****************\r\n";
        echo "ID:";
        $id = trim(fgets(STDIN));
        if ($id != '-1') {
            $n = Account::delete($id);
            echo "Delete $n Successfully!\r\n";
            return;
        }
        echo 'Abort!';
    }

    public function modify() {
    }

    public function menu() {
        for (;;) {
            echo "******************\r\n";
            echo "*  1.List        *\r\n";
            echo "*  2.Enter       *\r\n";
            echo "*  3.Find        *\r\n";
            echo "*  4.Delete      *\r\n";
            echo "*  5.Modify      *\r\n";
            echo "******************\r\n";
            echo "Input:";

            $ch = trim(fgets(STDIN));
            switch ($ch) {
                case '1':
                    $this->listing();
                    break;
                case '2':
                    $this->enter();
                    break;
                case '3':
                    $this->find();
                    break;
                case '4':
                    $this->delete();
                    break;
                case '5':
                    $this->modify();
                    break;
                default:
                    echo "******************\r\n";
                    echo "* WRONG INPUT!   *\r\n";
                    echo "******************\r\n";
                    break;
            }
        }
    }

    public static function init()
    {
        static $ui = null;
        if ($ui == null) $ui = new self();

        $ui->menu();
    }
}

class App
{
    public function run() {
        UI::init();
    }
}

(new App)->run();