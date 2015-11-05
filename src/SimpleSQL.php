<?php
/**
 * SQL/SimpleSQL.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @author Frédéric Le Barzic <fred@lebarzic.fr>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
 * @version 1.3.0
**/

namespace Phyrexia\SQL;

class SimpleSQL {
	const LINK_TYPE_MASTER = 'master';
	const LINK_TYPE_SLAVE = 'slave';

	private static $instance;

	private $master = array();
	private $slaves = array();
	private $result = false;
	private $links = array();
	private $currentLinkType = null;
	private $forceMaster = false;

	private $totalQueries = 0;

	private function __construct($base = NULL, $host = NULL, $port = NULL, $user = NULL, $pass = NULL) {
		$this->addLink(self::LINK_TYPE_MASTER, $base, $host, $port, $user, $pass);

		mysqli_report(MYSQLI_REPORT_STRICT);
	}

	public static function getInstance($base = NULL, $host = NULL, $port = NULL, $user = NULL, $pass = NULL) {
		if (is_null(self::$instance))
			self::$instance = new SimpleSQL($base, $host, $port, $user, $pass);

		return self::$instance;
	}

	public function addLink($type, $base, $host, $port, $user, $pass) {
		$config = compact('base', 'host', 'port', 'user', 'pass');
		if ($type == self::LINK_TYPE_MASTER) {
			$this->master = $config;
		} elseif ($type == self::LINK_TYPE_SLAVE) {
			array_push($this->slaves, $config);
		} else {
			throw new SimpleSQLException('This type of link is incorrect');
		}
	}

	private function autoSelectLinkType($query = null) {
		$type = self::LINK_TYPE_MASTER;
		if ($this->forceMaster) {
			return self::LINK_TYPE_MASTER;
		} elseif (is_string($query) && $query != '' && preg_match('/^SELECT/im', $query)) {
			$type = self::LINK_TYPE_SLAVE;
			if (!is_array($this->slaves) || count($this->slaves) == 0) {
				$type = self::LINK_TYPE_MASTER;
			}
		} elseif (is_null($query) && !is_null($this->currentLinkType)) {
			$type = $this->currentLinkType;
		}

		return $type;
	}

	private function checkLink($query = null) {
		$this->currentLinkType = $this->autoSelectLinkType($query);
		if ($this->getLink()) {
			return true;
		}

		$config = $this->master;
		if ($this->currentLinkType == self::LINK_TYPE_SLAVE && is_array($this->slaves) && count($this->slaves) > 0) {
			$rand = array_rand($this->slaves);
			$config = $this->slaves[$rand];
		}

		try {
			$this->links[$this->currentLinkType] = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['base'], $config['port']);
			$this->links[$this->currentLinkType]->query('SET NAMES "utf8"');

			return true;
		} catch (\mysqli_sql_exception $e) {
			if ($this->currentLinkType == self::LINK_TYPE_SLAVE && is_array($this->slaves) && count($this->slaves) > 0) {
				unset($this->slaves[$rand]);
				unset($this->links[$this->currentLinkType]);

				return $this->checkLink($query);
			}

			throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function getLink() {
		if (array_key_exists($this->currentLinkType, $this->links) && is_a($this->links[$this->currentLinkType], 'mysqli')) {
			return $this->links[$this->currentLinkType];
		}

		return false;
	}

	public function doQuery() {
		$args = func_get_args();

		$query = $args[0];
		$query = preg_replace_callback('/@([0-9]+)/s', function($matches) use ($args) { return ((! array_key_exists($matches[1], $args))?'@'.$matches[1]:(is_null($args[$matches[1]])?NULL:'`'.@mysqli_real_escape_string($this->getLink(), $args[$matches[1]]).'`')); }, $query);
		$query = preg_replace_callback('/%([0-9]+)/s', function($matches) use ($args) { return ((! array_key_exists($matches[1], $args))?'%'.$matches[1]:(is_null($args[$matches[1]])?NULL:'"'.@mysqli_real_escape_string($this->getLink(), $args[$matches[1]]).'"')); }, $query);

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

	public function setForceMaster($force = false) {
		$this->forceMaster = (bool) $force;
	}

	public function getForceMaster() {
		return $this->forceMaster;
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
