<?php
/**
 * SQL/SimpleSQLException.php
 *
 * @author Frédéric Le Barzic <fred@lebarzic.fr>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
 * @version 1.5.0
**/

namespace Phyrexia\SQL;

class SimpleSQLException extends \Exception {
	protected $query;

	public function __construct($message = '', $code = 0, \mysqli_sql_exception $e = NULL, $query = NULL) {
		parent::__construct($message, $code, $e);
		$this->query = $query;
	}

	public function getQuery() {
		return $this->query;
	}
}
