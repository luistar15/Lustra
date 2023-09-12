<?php

declare(strict_types=1);


namespace Lustra\DB;


use Exception;


abstract class ActiveRecord {

	/**
	 * @var \Lustra\DB\ActiveRecordDefinition
	 */
	protected $definition;
	/**
	 * @var mixed[]
	 */
	protected $data = [];
	public function __construct( ActiveRecordDefinition $definition, array $data = [] ) {
		$this->definition = $definition;
		$this->data       = $data;
	}
	// -------------------------------------------------------------------------
	/**
	 * @param mixed $v
	 */
	public function set( string $k, $v ): void {
		$this->data[ $k ] = $v;
	}
	// -------------------------------------------------------------------------
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


	// -------------------------------------------------------------------------


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


	// -------------------------------------------------------------------------
	/**
	 * @return int|string|null
	 */
	public function getPk() {
		return $this->data[ $this->definition->getPkColumn() ] ?? null;
	}


	public function setPk( ?string $pk ): void {
		$this->data[ $this->definition->getPkColumn() ] = $pk;
	}


	public function exists() : bool {
		return boolval( $this->getPk() );
	}


	// -------------------------------------------------------------------------


	public function load( array $query, array $bindings = [] ): array {
		$query = array_merge(
			$query,
			[
				'FROM'  => $this->definition->getTableName(),
				'LIMIT' => '1',
			]
		);
		$rows  = $this->definition->getDb()->getRows(
			SQLBuilder::build( $query, $this->definition->getRelations() ),
			$bindings,
		);
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
		return $this->loadByColumn( $this->definition->getPkColumn(), $pk );
	}


	// -------------------------------------------------------------------------


	public function save( array $columns = [] ): void {
		$data = $this->getData( $columns );
		unset( $data[ $this->definition->getPkColumn() ] );
		if ( $this->exists() ) {
			$this->definition->getDb()->update(
				$this->definition->getTableName(),
				$data,
				[ sprintf( '%s = %d', $this->definition->getPkColumn(), $this->getPk() ) ]
			);

		} else {
			$this->definition->getDb()->insert( $this->definition->getTableName(), $data );

			$insert_id = $this->definition->getDb()->lastInsertId();

			if ( is_string( $insert_id ) ) {
				$this->setPk( $insert_id );
			}
		}
	}


	public function delete() : void {
		$this->definition->getDb()->delete(
			$this->definition->getTableName(),
			[ 'WHERE' => sprintf( '`%s` = :pk', $this->definition->getPkColumn() ) ],
			[ ':pk' => $this->getPk() ]
		);
	}


	// -------------------------------------------------------------------------


	public function getDefinition() : ActiveRecordDefinition {
		return $this->definition;
	}

}
