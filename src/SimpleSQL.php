<?php
/**
 * SQL/SimpleSQL.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @author Frédéric Le Barzic <fred@lebarzic.fr>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
**/

namespace Phyrexia\SQL;

class SimpleSQL {
	const LINK_TYPE_MASTER = 'master';
	const LINK_TYPE_SLAVE = 'slave';

	private static $instance;

	private $master = array();
	private $slaves = array();
	private $links = array();

	private $forceMaster = false;
	private $currentLinkType;

	private $result = false;

	private $totalQueries = 0;

	private function __construct($base = NULL, $host = NULL, $port = NULL, $user = NULL, $pass = NULL, $charset = 'utf8', $collate = 'utf8_unicode_ci') {
		$this->addLink(self::LINK_TYPE_MASTER, $base, $host, $port, $user, $pass, $charset, $collate);

		mysqli_report(MYSQLI_REPORT_STRICT);
	}

	public static function getInstance($base = NULL, $host = NULL, $port = NULL, $user = NULL, $pass = NULL, $charset = 'utf8', $collate = 'utf8_unicode_ci') {
		if (is_null(self::$instance) || ! is_null($base) || ! is_null($host) || ! is_null($port) || ! is_null($user) || ! is_null($pass))
			self::$instance = self::newInstance($base, $host, $port, $user, $pass, $charset, $collate);

		return self::$instance;
	}

	public static function newInstance($base = NULL, $host = NULL, $port = NULL, $user = NULL, $pass = NULL, $charset = 'utf8', $collate = 'utf8_unicode_ci') {
		return new SimpleSQL($base, $host, $port, $user, $pass, $charset, $collate);
	}

	public function close() {
		foreach (array(self::LINK_TYPE_SLAVE, self::LINK_TYPE_MASTER) as $type) {
			if (array_key_exists($type, $this->links) && $this->links[$type] instanceof \mysqli) {
				$tid = mysqli_thread_id($this->links[$type]);
				if ($tid !== false)
					mysqli_kill($this->links[$type], $tid);

				mysqli_close($this->links[$type]);

				unset($this->links[$type]);
			}
		}

		$this->links = array();
	}

	public function getForceMaster() {
		return $this->forceMaster;
	}

	public function setForceMaster($force) {
		$this->forceMaster = (bool)$force;
	}

	public function addLink($type, $base, $host, $port, $user, $pass, $charset, $collate) {
		if (! in_array($type, array(self::LINK_TYPE_MASTER, self::LINK_TYPE_SLAVE)))
			throw new SimpleSQLException('Invalid or unsupported link type '.$type);

		switch ($type) {
			case self::LINK_TYPE_MASTER:
				$this->master = compact('base', 'host', 'port', 'user', 'pass', 'charset', 'collate');
				break;
			case self::LINK_TYPE_SLAVE:
				$this->slaves[] = compact('base', 'host', 'port', 'user', 'pass', 'charset', 'collate');
				break;
		}

		return true;
	}

	private function autoSelectLinkType($query = NULL) {
		if ($this->forceMaster)
			return self::LINK_TYPE_MASTER;

		if (is_null($query) && ! is_null($this->currentLinkType))
			return $this->currentLinkType;

		$type = self::LINK_TYPE_MASTER;

		if (is_string($query) && preg_match('/^SELECT/im', $query)) {
			$type = self::LINK_TYPE_SLAVE;
			if (! is_array($this->slaves) || count($this->slaves) == 0)
				$type = self::LINK_TYPE_MASTER;
		}

		return $type;
	}

	private function checkLink($query = NULL) {
		$this->currentLinkType = $this->autoSelectLinkType($query);

		if ($this->getLink() instanceof \mysqli)
			return true;

		$config = $this->master;
		if ($this->currentLinkType == self::LINK_TYPE_SLAVE && is_array($this->slaves) && count($this->slaves) > 0) {
			$rand = array_rand($this->slaves);
			$config = $this->slaves[$rand];
		}

		try {
			$this->links[$this->currentLinkType] = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['base'], $config['port']);
			$this->links[$this->currentLinkType]->query('SET NAMES "' . $config['charset'] . '"');

			return true;
		} catch (\mysqli_sql_exception $e) {
			if ($this->currentLinkType == self::LINK_TYPE_SLAVE && is_array($this->slaves) && count($this->slaves) > 0) {
				unset($this->slaves[$rand]);
				unset($this->links[$this->currentLinkType]);

				return $this->checkLink($query);
			}

			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e, $query);
		}
	}

	public function getLink() {
		if (array_key_exists($this->currentLinkType, $this->links) && $this->links[$this->currentLinkType] instanceof \mysqli)
			return $this->links[$this->currentLinkType];

		return false;
	}

	public function prepareQuery() {
		$this->checkLink(NULL);

		$args = func_get_args();

		if (count($args) == 2 && is_string($args[0]) && is_array($args[1])) {
			$buf = array_merge(array($args[0]), $args[1]);
			$args = $buf;
		}

		$query = $args[0];
		$query = preg_replace_callback('/@([0-9]+)/s', function($matches) use ($args) { return (($matches[1] == 0 || ! array_key_exists($matches[1], $args))?'@'.$matches[1]:(is_null($args[$matches[1]])?'NULL':'`'.@mysqli_real_escape_string($this->getLink(), $args[$matches[1]]).'`')); }, $query);
		$query = preg_replace_callback('/%([0-9]+)/s', function($matches) use ($args) { return (($matches[1] == 0 || ! array_key_exists($matches[1], $args))?'%'.$matches[1]:(is_null($args[$matches[1]])?'NULL':'"'.@mysqli_real_escape_string($this->getLink(), $args[$matches[1]]).'"')); }, $query);

		return $query;
	}

	public function doQuery() {
		$query = call_user_func_array(array($this, 'prepareQuery'), func_get_args());

		$this->checkLink($query);

		try {
			if (is_resource($this->result)) {
				mysqli_free_result($this->result);
				$this->result = false;
			}

			$this->result = mysqli_query($this->getLink(), $query);
			if ($this->result === false)
				throw new SimpleSQLException(mysqli_error($this->getLink()), mysqli_errno($this->getLink()), NULL, $query);

			$this->totalQueries += 1;

			return $this->result;
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e, $query);
		}
	}

	public function doQueryOnMaster() {
		$state = $this->getForceMaster();
		$this->setForceMaster(true);
		$buf = call_user_func_array(array($this, 'doQuery'), func_get_args());
		$this->setForceMaster($state);

		return $buf;
	}

	public function fetchResult($result = NULL) {
		$this->checkLink();

		$r = $this->result;
		if (! is_null($result) && is_resource($result))
			$r = $result;

		if (! $r)
			return false;

		try {
			return mysqli_fetch_assoc($r);
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function fetchAllResults($result = NULL) {
		$this->checkLink();

		$r = $this->result;
		if (! is_null($result) && is_resource($result))
			$r = $result;

		if (! $r)
			return false;

		try {
			$ret = array();
			while ($buf = mysqli_fetch_assoc($r))
				$ret[] = $buf;

			return $ret;
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function numRows($result = NULL) {
		$this->checkLink();

		$r = $this->result;
		if (! is_null($result) && is_resource($result))
			$r = $result;

		if (! $r)
			return false;

		try {
			return mysqli_num_rows($r);
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function affectedRows() {
		$this->checkLink();

		try {
			return mysqli_affected_rows($this->getLink());
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function insertID() {
		$this->checkLink();

		try {
			return mysqli_insert_id($this->getLink());
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function totalQueries() {
		return $this->totalQueries;
	}
}
