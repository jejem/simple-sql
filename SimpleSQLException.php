<?php
/**
 * SQL/SimpleSQLException.php
 *
 * @author Frédéric Le Barzic <fred@lebarzic.fr>
 * @copyright Jérémy 'Jejem' Desvages
 * @license The MIT License (MIT)
 * @version 1.0.0
 **/

namespace Phyrexia\SQL;

class SimpleSQLException extends \Exception
{
	protected $query;

	public function __construct($message = "", $code = 0, Exception $previous = null, $query = null)
	{
		parent::__construct($message, $code, $previous);
		$this->query = $query;
	}

	public function getQuery()
	{
		return $this->query;
	}

}