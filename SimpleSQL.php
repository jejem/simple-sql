<?php
/**
 * SQL/SimpleSQL.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @author Frédéric Le Barzic <fred@lebarzic.fr>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
 * @version 1.2.0
**/

namespace Phyrexia\SQL;

class SimpleSQL {
	private static $instance;

	private $link = false;
	private $result = false;

	private static $sqlBase;
	private static $sqlHost;
	private static $sqlPort;
	private static $sqlUser;
	private static $sqlPass;

	private $totalQueries = 0;

	private function __construct($base = NULL, $host = NULL, $port = NULL, $user = NULL, $pass = NULL) {
		self::$sqlBase = $base;
		self::$sqlHost = $host;
		self::$sqlPort = $port;
		self::$sqlUser = $user;
		self::$sqlPass = $pass;

		mysqli_report(MYSQLI_REPORT_STRICT);
	}

	public static function getInstance($base = NULL, $host = NULL, $port = NULL, $user = NULL, $pass = NULL) {
		if (is_null(self::$instance) || (! is_null($base) && $base != self::$sqlBase) || (! is_null($host) && $host != self::$sqlHost) || (! is_null($port) && $port != self::$sqlPort) || (! is_null($user) && $user != self::$sqlUser) || (! is_null($pass) && $pass != self::$sqlPass))
			self::$instance = new SimpleSQL($base, $host, $port, $user, $pass);

		return self::$instance;
	}

	private function checkLink() {
		if ($this->link)
			return;

		try {
			$this->link = mysqli_connect(self::$sqlHost, self::$sqlUser, self::$sqlPass, self::$sqlBase, self::$sqlPort);
			$this->doQuery('SET NAMES %1', 'utf8');

			return true;
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function doQuery() {
		$this->checkLink();

		$args = func_get_args();

		$query = $args[0];
		$query = preg_replace_callback('/@([0-9]+)/s', function($matches) use ($args) { return ((! array_key_exists($matches[1], $args))?'@'.$matches[1]:(is_null($args[$matches[1]])?NULL:'`'.@mysqli_real_escape_string($this->link, $args[$matches[1]]).'`')); }, $query);
		$query = preg_replace_callback('/%([0-9]+)/s', function($matches) use ($args) { return ((! array_key_exists($matches[1], $args))?'%'.$matches[1]:(is_null($args[$matches[1]])?NULL:'"'.@mysqli_real_escape_string($this->link, $args[$matches[1]]).'"')); }, $query);

		try {
			if (is_resource($this->result)) {
				mysqli_free_result($this->result);
				$this->result = false;
			}

			$this->result = mysqli_query($this->link, $query);
			if ($this->result === false)
				throw new SimpleSQLException(mysqli_error($this->link), mysqli_errno($this->link), NULL, $query);

			$this->totalQueries += 1;

			return $this->result;
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e, $query);
		}
	}

	public function fetchResult() {
		$this->checkLink();

		if (! $this->result)
			return false;

		try {
			return mysqli_fetch_assoc($this->result);
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function fetchAllResults() {
		$this->checkLink();

		if (! $this->result)
			return false;

		try {
			$ret = array();
			while ($buf = mysqli_fetch_assoc($this->result))
				$ret[] = $buf;

			return $ret;
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function numRows() {
		$this->checkLink();

		if (! $this->result)
			return false;

		try {
			return mysqli_num_rows($this->result);
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function insertID() {
		$this->checkLink();

		try {
			return mysqli_insert_id($this->link);
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function totalQueries() {
		return $this->totalQueries;
	}
}
