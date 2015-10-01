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

	private $base;
	private $host;
	private $port;
	private $user;
	private $pass;

	private $totalQueries = 0;

	private function __construct($base = NULL, $host = NULL, $port = NULL, $user = NULL, $pass = NULL) {
		$this->base = $base;
		$this->host = $host;
		$this->port = $port;
		$this->user = $user;
		$this->pass = $pass;

		mysqli_report(MYSQLI_REPORT_STRICT);
	}

	public static function getInstance($base = NULL, $host = NULL, $port = NULL, $user = NULL, $pass = NULL) {
		if (is_null(self::$instance) || (! is_null($base) && $base != $this->base) || (! is_null($host) && $host != $this->host) || (! is_null($port) && $port != $this->port) || (! is_null($user) && $user != $this->user) || (! is_null($pass) && $pass != $this->pass))
			self::$instance = new SimpleSQL($base, $host, $port, $user, $pass);

		return self::$instance;
	}

	private function checkLink() {
		if ($this->link)
			return;

		try {
			$this->link = mysqli_connect($this->host, $this->user, $this->pass, $this->base, $this->port);
			$this->doQuery('SET NAMES %1', 'utf8');

			return true;
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function getLink() {
		$this->checkLink();

		return $this->link;
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
