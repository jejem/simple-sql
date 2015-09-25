<?php
/**
 * SQL/SimpleSQL.php
 *
 * @author Jérémy 'Jejem' Desvages <jejem@phyrexia.org>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
 * @version 1.0.0
**/

namespace Phyrexia\SQL;

class SimpleSQL {
	public $alertEmails = array();

	private $link = false;
	private $db = NULL;
	private $result = false;
	private $useException = true;

	public $sqlHost;
	public $sqlUser;
	public $sqlPass;
	public $sqlBase;

	private $totalQueries = 0;

	public function __construct($host=NULL, $user=NULL, $pass=NULL, $base=NULL) {
		$this->sqlHost = $host;
		$this->sqlUser = $user;
		$this->sqlPass = $pass;
		$this->sqlBase = $base;

		mysqli_report(MYSQLI_REPORT_STRICT);
	}

	private function fatalError($msg) {
		if (is_array($this->alertEmails) && count($this->alertEmails) > 0) {
			foreach ($this->alertEmails as $alertEmail)
				mail($alertEmail, '[SimpleSQL] An error occured', 'An error occured:'."\n\n".$msg, 'X-Mailer: PHP/'.phpversion());
		}

		trigger_error($msg, E_USER_ERROR);
	}

	private function checkLink() {
		if ($this->link)
			return;

		try {
			$this->link = mysqli_connect($this->sqlHost, $this->sqlUser, $this->sqlPass);
			$this->selectDB($this->sqlBase);
			$this->doQuery('SET NAMES %1', 'utf8');
		} catch (\mysqli_sql_exception $e) {
			if ($this->useException) {
				throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
			}

			$this->fatalError('<strong>Error:</strong><br />Link to SQL server failed.<br />Please try again later.');
			return false;
		}  catch (SimpleSQLException $e) {
			if ($this->useException) {
				throw new $e;
			}

			$this->fatalError('<strong>Error:</strong><br />Could not select database.<br />Please try again later.');
		}
	}

	public function selectDB($db) {
		$this->checkLink();

		try {
			mysqli_select_db($this->link, $db);
			$this->db = $db;

			return true;
		} catch (\mysqli_sql_exception $e) {
			throw new SimpleSQLException('Could not select database, please try again later.');
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
			if ($this->result === false) {
				if ($this->useException) {
					throw new SimpleSQLException(mysqli_error($this->link), mysqli_errno($this->link), null, $query);
				}

				$this->fatalError('<strong>Error:</strong><br />('.mysqli_errno($this->link).') '.mysqli_error($this->link).'<br />Query: '.$query);
			}

			$this->totalQueries += 1;

			return $this->result;
		} catch (\mysqli_sql_exception $e) {
			if ($this->useException) {
				throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e, $query);
			}

			$this->fatalError('<strong>Error:</strong><br />(' . $e->getCode() . ') ' . $e->getMessage() . '<br />Query: ' . $query);
		}
	}

	public function fetchResult() {
		$this->checkLink();

		if (! $this->result)
			return false;

		try {
			return mysqli_fetch_assoc($this->result);
		} catch (\mysqli_sql_exception $e) {
			if ($this->useException) {
				throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
			}

			$this->fatalError('<strong>Error:</strong><br />(' . $e->getCode() . ') ' . $e->getMessage());
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
			if ($this->useException) {
				throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
			}

			$this->fatalError('<strong>Error:</strong><br />(' . $e->getCode() . ') ' . $e->getMessage());
		}
	}

	public function numRows() {
		$this->checkLink();

		if (! $this->result)
			return false;

		try {
			return mysqli_num_rows($this->result);
		} catch (\mysqli_sql_exception $e) {
			if ($this->useException) {
				throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
			}

			$this->fatalError('<strong>Error:</strong><br />(' . $e->getCode() . ') ' . $e->getMessage());
		}
	}

	public function insertID() {
		$this->checkLink();

		try {
			return mysqli_insert_id($this->link);
		} catch (\mysqli_sql_exception $e) {
			if ($this->useException) {
				throw new SimpleSQLException($e->getMessage(), $e->getCode(), $e);
			}

			$this->fatalError('<strong>Error:</strong><br />(' . $e->getCode() . ') ' . $e->getMessage());
		}
	}

	public function totalQueries() {
		return $this->totalQueries;
	}

	public function setUseException($value) {
		$this->useException = $value;
	}
}
