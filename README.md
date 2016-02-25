# simple-sql
PHP class to perform SQL queries easily

[![Latest Stable Version](https://poser.pugx.org/phyrexia/sql/v/stable)](https://packagist.org/packages/phyrexia/sql)
[![License](https://poser.pugx.org/phyrexia/sql/license)](https://packagist.org/packages/phyrexia/sql)

## Requirements

- PHP >= 5.3
- PHP extension mysqli

## Installation

Install directly via [Composer](https://getcomposer.org):
```bash
$ composer require phyrexia/sql
```

## Basic Usage

```php
<?php
require 'vendor/autoload.php';

use Phyrexia\SQL\SimpleSQL;

//First call: generate instance (next calls won't need parameters, Singleton <3)
$SQL = SimpleSQL::getInstance(DATABASE, HOST, PORT, USER, PASS);

//Do some SQL query
$SQL->doQuery('SELECT * FROM table');

//Count returned rows
$count = $SQL->numRows();

//Fetch results (associative array)
$rows = $SQL->fetchAllResults();

//Do another SQL query
$SQL->doQuery('SELECT * FROM table2 LIMIT 1');

//Fetch a single result
$row = $SQL->fetchResult();
```
