<?php

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '1.7.8.0');
}
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

abstract class Module
{
    public static $nextInstallResult = true;
    public static $forceHookFailure = false;
    public static $hookLog = array();

    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $displayName;
    public $description;
    public $ps_versions_compliancy = array();

    public function __construct()
    {
    }

    public function install()
    {
        if (!Module::$nextInstallResult) {
            return false;
        }

        return true;
    }

  public function registerHook($name)
    {
        if (Module::$forceHookFailure) {
            return false;
        }
        Module::$hookLog[] = array('action' => 'register', 'hook' => $name);

        return true;
    }

    public function unregisterHook($name)
    {
        Module::$hookLog[] = array('action' => 'unregister', 'hook' => $name);

        return true;
    }

    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if ($domain === null || $domain === '') {
            throw new RuntimeException('Translator domain must not be empty.');
        }

        $message = (string) $id;
        if (!empty($parameters) && preg_match('/%(?:\d+\$)?[+-]?(?:[ 0]|\'.)?-?\d*(?:\.\d+)?[bcdeufFosxX]/', $message)) {
            return vsprintf($message, $parameters);
        }

        return $message;
    }
}

abstract class ModuleGrid extends Module
{
    protected $_values = array();
    protected $_totalCount = 0;
    protected $_start = 0;
    protected $_limit = 40;
    protected $_sort = null;
    protected $_direction = null;
    protected $_employee;
    protected $_id_lang;
    protected $_csv;

    protected $_dateFrom = null;
    protected $_dateTo = null;

    public function setStatsDateRange($from, $to)
    {
        $this->_dateFrom = $from;
        $this->_dateTo = $to;
    }

    public function getDate()
    {
        if ($this->_dateFrom === null || $this->_dateTo === null) {
            throw new RuntimeException('Stats date range was not configured for the test harness.');
        }

        return ' \'' . $this->_dateFrom . ' 00:00:00\' AND \'' . $this->_dateTo . ' 23:59:59\' ';
    }

    public function engine($params)
    {
        return 'GRID_ENGINE_OUTPUT';
    }

    protected function csvExport($datas)
    {
        $this->_sort = $datas['defaultSortColumn'];
        $this->getData();

        if (count($datas['columns'])) {
            foreach ($datas['columns'] as $column) {
                $this->_csv .= $column['header'] . ';';
            }
            $this->_csv = rtrim($this->_csv, ';') . "\n";

            foreach ($this->_values as $value) {
                foreach ($datas['columns'] as $column) {
                    $this->_csv .= $value[$column['dataIndex']] . ';';
                }
                $this->_csv = rtrim($this->_csv, ';') . "\n";
            }
        }

        echo $this->_csv;
        exit(0);
    }
}

class Shop
{
    const SHARE_ORDER = 'share_order';
    const SHARE_CUSTOMER = 'share_customer';
    const SHARE_STOCK = 'share_stock';

    public static function addSqlRestriction($share = false, $alias = null)
    {
        if ($share !== self::SHARE_ORDER) {
            throw new RuntimeException('Harness Shop::addSqlRestriction only supports SHARE_ORDER.');
        }
        if ($alias !== 'o') {
            throw new RuntimeException('Harness Shop::addSqlRestriction expects alias o.');
        }

        return ' AND o.id_shop IN (1) ';
    }
}

class Validate
{
    public static function isName($name)
    {
        if (!is_string($name)) {
            return false;
        }

        return (bool) preg_match('/^[^0-9!<>,;?=+()@#"°{}_$%:¤|]*$/u', $name);
    }

    public static function isSortDirection($value)
    {
        return $value !== null && ($value === 'ASC' || $value === 'DESC');
    }

    public static function isUnsignedInt($value)
    {
        return (is_numeric($value) || is_string($value))
            && (string) (int) $value === (string) $value
            && $value < 4294967296
            && $value >= 0;
    }

}

class Tools
{
    public static function getValue($key, $default_value = false)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default_value;
    }

    public static function safeOutput($string, $html = false)
    {
        if (!$html) {
            $string = strip_tags($string);
        }

        return htmlentities((string) $string, ENT_QUOTES, 'UTF-8');
    }
}

function pSQL($string, $htmlOK = false)
{
    return (string) $string;
}

function bqSQL($string)
{
    return str_replace('`', '\\`', pSQL($string));
}

class Db
{
    /** @var Db */
    private static $instance;
    /** @var PDO */
    private $pdo;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function bootstrapFromEnv()
    {
        $host = getenv('STATS_DB_HOST');
        $port = getenv('STATS_DB_PORT');
        $name = getenv('STATS_DB_NAME');
        $user = getenv('STATS_DB_USER');
        $pass = getenv('STATS_DB_PASSWORD');

        if (!$host || !$port || !$name || !$user) {
            throw new RuntimeException('Database environment is not configured.');
        }

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $user, $pass ? $pass : '', array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => true,
        ));
        $pdo->exec("SET SESSION sql_mode = ''");
        $pdo->exec('SET NAMES utf8mb4');
        $mode = $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        if ($mode !== '' && $mode !== null) {
            throw new RuntimeException('Expected empty SESSION sql_mode, got: ' . $mode);
        }

        self::$instance = new self($pdo);
    }

    public static function getInstance($slave = false)
    {
        if (!self::$instance) {
            self::bootstrapFromEnv();
        }

        return self::$instance;
    }

    public function executeS($sql)
    {
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            throw new RuntimeException('SQL failed: ' . $sql);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            throw new RuntimeException('SQL fetch failed: ' . $sql);
        }

        return $rows;
    }

    public function getValue($sql)
    {
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            throw new RuntimeException('SQL failed: ' . $sql);
        }
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row === false) {
            return false;
        }

        return $row[0];
    }

    public function exec($sql)
    {
        return $this->pdo->exec($sql);
    }

    public function truncateSalesFixture()
    {
        $this->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (array('order_detail', 'orders', 'product', 'manufacturer', 'currency') as $table) {
            $this->exec('TRUNCATE TABLE `' . _DB_PREFIX_ . $table . '`');
        }
        $this->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    public function seedSalesFixture()
    {
        $this->exec("INSERT INTO `" . _DB_PREFIX_ . "currency` (id_currency, conversion_rate) VALUES (1, 2.000000)");
        $this->exec("INSERT INTO `" . _DB_PREFIX_ . "manufacturer` (id_manufacturer, name) VALUES
            (1, 'Alpha'), (2, 'Beta'), (3, 'Gamma'), (4, 'Delta'), (5, 'Epsilon')");
        $this->exec("INSERT INTO `" . _DB_PREFIX_ . "product` (id_product, id_manufacturer) VALUES
            (10, 1), (20, 2), (30, 3), (40, 4), (50, 5), (60, NULL), (70, 99)");
        $orders = array(
            array(1, 1, 1, '2016-03-01 00:00:00', 1, 10, 1, 10.000000),
            array(2, 1, 1, '2016-03-31 23:59:59', 1, 10, 1, 10.000000),
            array(3, 1, 1, '2016-03-15 12:00:00', 1, 10, 2, 10.000000),
            array(4, 1, 1, '2016-03-10 08:00:00', 1, 20, 10, 3.000000),
            array(5, 2, 1, '2016-03-12 10:00:00', 1, 30, 5, 5.000000),
            array(6, 1, 0, '2016-03-11 10:00:00', 1, 40, 1, 1.000000),
            array(7, 1, 1, '2016-02-28 23:59:59', 1, 50, 1, 1.000000),
            array(9, 1, 1, '2016-02-28 23:59:59', 1, 20, 100, 3.000000),
            array(10, 1, 1, '2016-04-01 00:00:00', 1, 20, 100, 3.000000),
            array(11, 1, 0, '2016-03-16 08:00:00', 1, 10, 100, 100.000000),
            array(12, 1, 1, '2016-03-18 08:00:00', 1, 60, 1, 1.000000),
            array(13, 1, 1, '2016-03-19 08:00:00', 1, 70, 1, 1.000000),
        );
        foreach ($orders as $o) {
            $this->exec(sprintf(
                "INSERT INTO `%sorders` (id_order, id_shop, valid, invoice_date, id_currency, conversion_rate)
                VALUES (%d, %d, %d, '%s', %d, 2.000000)",
                _DB_PREFIX_,
                $o[0],
                $o[1],
                $o[2],
                $o[3],
                $o[4]
            ));
            $this->exec(sprintf(
                "INSERT INTO `%sorder_detail` (id_order, product_id, product_quantity, unit_price_tax_excl)
                VALUES (%d, %d, %d, %.6f)",
                _DB_PREFIX_,
                $o[0],
                $o[5],
                $o[6],
                $o[7]
            ));
        }
    }

    public static function ensureSchema()
    {
        $db = self::getInstance();
        $db->exec('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'currency` (
            `id_currency` INT UNSIGNED NOT NULL,
            `conversion_rate` DECIMAL(20,6) NOT NULL,
            PRIMARY KEY (`id_currency`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'manufacturer` (
            `id_manufacturer` INT UNSIGNED NOT NULL,
            `name` VARCHAR(64) NOT NULL,
            PRIMARY KEY (`id_manufacturer`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'product` (
            `id_product` INT UNSIGNED NOT NULL,
            `id_manufacturer` INT UNSIGNED NULL,
            PRIMARY KEY (`id_product`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'orders` (
            `id_order` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL,
            `valid` TINYINT(1) NOT NULL,
            `invoice_date` DATETIME NOT NULL,
            `id_currency` INT UNSIGNED NOT NULL,
            `conversion_rate` DECIMAL(20,6) NOT NULL,
            PRIMARY KEY (`id_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'order_detail` (
            `id_order` INT UNSIGNED NOT NULL,
            `product_id` INT UNSIGNED NOT NULL,
            `product_quantity` INT NOT NULL,
            `unit_price_tax_excl` DECIMAL(20,6) NOT NULL,
            KEY (`id_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
