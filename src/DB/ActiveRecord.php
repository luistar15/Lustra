<?php

declare(strict_types=1);


namespace Lustra\DB;


use Exception;


abstract class ActiveRecord {

	/**
	 * @var \Lustra\DB\DBAL
	 */
	protected $db;

	/**
	 * @var string
	 */
	protected $table = '';
	/**
	 * @var string
	 */
	protected $pk = 'id';

	/**
	 * @var mixed[]
	 */
	protected $data = [];


	public function __construct( ?DBAL $db = null, array $data = [] ) {
		if ( $db ) {
			$this->db = $db;
		}
		$this->setData( $data );
	}


	/**
	 * @param mixed $v
	 */
	public function set( string $k, $v ): void {
		$this->data[ $k ] = $v;
	}


	/**
	 * @return mixed
	 */
	public function get( string $k ) {
		return $this->data[ $k ] ?? null;
	}


	public function getInt(
		string $k
	) : int {

		$value = $this->get( $k );

		if ( is_null( $value ) ) {
			throw new Exception( "Missing value for [{$k}]" );
		}

		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return intval( $value );
		}

		throw new Exception( "Invalid int value for [{$k}]" );
	}


	public function getString(
		string $k
	) : string {

		$value = $this->get( $k );

		if ( is_null( $value ) ) {
			throw new Exception( "Missing value for [{$k}]" );
		}

		if ( is_string( $value ) || is_int( $value ) ) {
			return strval( $value );
		}

		throw new Exception( "Invalid string value for [{$k}]" );
	}


	public function getBool(
		string $k
	) : bool {

		$value = $this->get( $k );

		if ( is_null( $value ) ) {
			throw new Exception( "Missing value for [{$k}]" );
		}

		if ( is_bool( $value ) || is_int( $value ) ) {
			return boolval( $value );
		}

		throw new Exception( "Invalid string value for [{$k}]" );
	}


	public function setData( iterable $data ): void {
		foreach ( $data as $k => $v ) {
			$this->data[ $k ] = $v;
		}
	}


	public function getData( array $columns = [] ): array {
		if ( count( $columns ) === 0 ) {
			return $this->data;
		}
		$data = [];
		foreach ( $columns as $k ) {
			$data[ $k ] = $this->data[ $k ];
		}
		return $data;
	}


	/**
	 * @return int|string|null
	 */
	public function getPk() {
		return $this->data[ $this->pk ] ?? null;
	}


	public function setPk( ?string $pk ): void {
		$this->data[ $this->pk ] = $pk;
	}


	public function exists() : bool {
		return boolval( $this->getPk() );
	}


	// -------------------------------------------------------------------------


	public function load( array $query, array $bindings = [] ): array {
		$query = array_merge(
			$query,
			[
				'FROM'  => $this->table,
				'LIMIT' => '1',
			]
		);
		$rows  = $this->db->getRows( SQLBuilder::build( $query ), $bindings );
		if ( count( $rows ) === 0 ) {
			throw new RecordNotFoundException(
				sprintf( '%s record was not found', get_class( $this ) )
			);
		}
		$this->data = current( $rows );
		return $this->data;
	}


	/**
	 * @param int|string $value
	 */
	public function loadByColumn( string $column, $value ): array {
		return $this->load(
			[ 'WHERE' => "`{$column}` = :_VAL_" ],
			[ ':_VAL_' => $value ]
		);
	}


	/**
	 * @param int|string $pk
	 */
	public function loadByPk( $pk ): array {
		return $this->loadByColumn( $this->pk, $pk );
	}


	// -------------------------------------------------------------------------


	public function save( array $columns = [] ): void {
		$data = $this->getData( $columns );
		unset( $data[ $this->pk ] );
		if ( $this->exists() ) {
			$this->db->update(
				$this->table,
				$data,
				[ sprintf( '%s = %d', $this->pk, $this->getPk() ) ]
			);

		} else {
			$this->db->insert( $this->table, $data );

			$insert_id = $this->db->lastInsertId();

			if ( is_string( $insert_id ) ) {
				$this->setPk( $insert_id );
			}
		}
	}


	public function delete() : void {
		$this->db->delete(
			$this->table,
			[ 'WHERE' => sprintf( '`%s` = :pk', $this->pk ) ],
			[ ':pk' => $this->getPk() ]
		);
	}


	// -------------------------------------------------------------------------


	public function setDb( DBAL $db ): void {
		$this->db = $db;
	}


	public function getDb() : DBAL {
		return $this->db;
	}

}
