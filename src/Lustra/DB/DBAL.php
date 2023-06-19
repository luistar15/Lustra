<?php

declare(strict_types=1);


namespace Lustra\DB;


use PDO;
use PDOStatement;
use Iterator;
use Exception;


class DBAL extends PDO {

	private array $connection_parameters;

	private bool $is_connected = false;


	public function __construct(
		string $dsn,
		string $username = '',
		string $passwd = '',
		array $options = []
	) {

		$options[ PDO::ATTR_ERRMODE ] = PDO::ERRMODE_EXCEPTION;

		$this->connection_parameters = [ $dsn, $username, $passwd, $options ];
	}


	public function connect() : void {
		if ( $this->is_connected ) {
			return;
		}

		[ $dsn, $username, $passwd, $options ] = $this->connection_parameters;

		parent::__construct( $dsn, $username, $passwd, $options );

		$this->is_connected = true;
	}


	public function execute(
		string $sql,
		array $bindings = []
	) : PDOStatement {

		$this->connect();

		$sth = $this->prepare( $sql );

		self::bindValues( $sth, $bindings );

		$sth->execute();

		return $sth;
	}



	// SELECT shortcuts
	// -------------------------------------------------------------------------


	public function getRows(
		string $sql,
		array $bindings = [],
		int $fetch_type = PDO::FETCH_ASSOC
	) : array {

		$sth  = $this->execute( $sql, $bindings );
		$rows = $sth->fetchAll( $fetch_type );
		$sth->closeCursor();

		return $rows;
	}


	public function getRow(
		string $sql,
		array $bindings = [],
		int $fetch_type = PDO::FETCH_ASSOC
	) : array {

		$sth = $this->execute( $sql, $bindings );
		$row = $sth->fetch( $fetch_type );
		$sth->closeCursor();

		if ( $row === false ) {
			throw new Exception( 'PDOStatement::fetch() has failed' );
		}

		return $row;
	}


	public function getColumn(
		string $sql,
		array $bindings = [],
		int $column_number = 0
	) : array {

		$sth    = $this->execute( $sql, $bindings );
		$column = $sth->fetchAll( PDO::FETCH_COLUMN, $column_number );
		$sth->closeCursor();

		return $column;
	}


	public function getCell(
		string $sql,
		array $bindings = [],
		int $column_number = 0
	) : ?string {

		$sth = $this->execute( $sql, $bindings );
		$val = $sth->fetchColumn( $column_number );
		$sth->closeCursor();

		if ( $val === false ) {
			throw new Exception( 'PDOStatement::fetchColumn() has failed' );
		}

		return strval( $val );
	}


	// generators


	public function generateRows(
		string $sql,
		array $bindings = [],
		int $fetch_type = PDO::FETCH_ASSOC
	) : Iterator {

		$sth = $this->execute( $sql, $bindings );

		while ( $row = $sth->fetch( $fetch_type ) ) { // phpcs:ignore
			yield $row;
		}

		$sth->closeCursor();
	}


	public function generateColumn(
		string $sql,
		array $bindings = [],
		int $column_number = 0
	) : Iterator {

		$sth = $this->execute( $sql, $bindings );

		while ( $cell = $sth->fetchColumn( $column_number ) ) { // phpcs:ignore
			yield $cell;
		}

		$sth->closeCursor();
	}



	// INSERT (single row)
	// -------------------------------------------------------------------------


	public function insert(
		string $table,
		array $data
	) : int {

		$columns     = [];
		$placehoders = [];
		$bindings    = [];

		foreach ( $data as $k => $v ) {
			$columns[]     = $k;
			$placehoders[] = '?';
			$bindings[]    = $v;
		}

		$sql = sprintf(
			"INSERT INTO {$table} (%s) VALUES (%s)",
			implode( ', ', $columns ),
			implode( ', ', $placehoders )
		);

		$sth = $this->execute( $sql, $bindings );

		return $sth->rowCount();
	}



	// UPDATE
	// -------------------------------------------------------------------------


	public function update(
		string $table,
		array $data,
		array $conditions = [],
		array $bindings = []
	) : int {

		$columns = [];

		foreach ( $data as $k => $v ) {
			$columns[] = "{$k} = :{$k}";

			$bindings[ ":{$k}" ] = $v;
		}

		$sql = "UPDATE {$table} SET " . implode( ', ', $columns );

		if ( count( $conditions ) ) {
			$sql .= ' WHERE ';
			$sql .= implode(
				' AND ',
				array_map( fn ( $c) => "({$c})", $conditions )
			);
		}

		$sth = $this->execute( $sql, $bindings );

		return $sth->rowCount();
	}



	// DELETE
	// -------------------------------------------------------------------------


	public function delete(
		string $table,
		array $conditions = [],
		array $bindings = []
	) : int {

		$sql = "DELETE FROM {$table}";

		if ( count( $conditions ) ) {
			$sql .= ' WHERE ';
			$sql .= implode(
				' AND ',
				array_map( fn ( $c) => "({$c})", $conditions )
			);
		}

		$sth = $this->execute( $sql, $bindings );

		return $sth->rowCount();
	}



	// HELPERS
	// -------------------------------------------------------------------------


	public static function getStatementColumns(
		PDOStatement $sth
	) : array {

		$columns = [];

		$count = $sth->columnCount();

		for ( $i = 0; $i < $count; $i++ ) {
			$columns[] = $sth->getColumnMeta( $i );
		}

		return $columns;
	}


	public static function bindValues(
		PDOStatement $sth,
		array $bindings = []
	) : void {

		$question_mark_position = 0;

		foreach ( $bindings as $k => $v ) {
			$parameter = is_int( $k ) ? ++$question_mark_position : $k;

			$value     = $v;
			$data_type = null;

			if ( is_array( $v ) ) {
				$value     = $v[0];
				$data_type = $v[1] ?? null;
			}

			if ( is_null( $data_type ) ) {
				$data_type = self::inferDataType( gettype( $value ) );
			}

			$sth->bindValue( $parameter, $value, $data_type );
		}
	}


	private static function inferDataType(
		string $type
	) : int {

		// phpcs:disable
		switch ( $type ) {
			case 'string'  : return PDO::PARAM_STR;
			case 'integer' : return PDO::PARAM_INT;
			case 'boolean' : return PDO::PARAM_BOOL;
			case 'double'  : return PDO::PARAM_STR;
			case 'NULL'    : return PDO::PARAM_NULL;

			default:
				throw new Exception( "Invalid PDO data type: {$type}" );
		}
	}

}
