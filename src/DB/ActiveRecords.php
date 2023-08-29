<?php

declare(strict_types=1);


namespace Lustra\DB;


abstract class ActiveRecords {

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
	protected $relations = [];


	public function __construct( DBAL $db ) {
		$this->db = $db;
	}


	public function find( array $query = [], array $bindings = [], ?string $class_entity = null ): array {
		$query = array_merge( $query, [ 'FROM' => $this->table ] );
		if ( isset( $query['JOIN'] ) ) {
			$query['JOIN'] = SQLBuilder::parseJoins(
				$query['JOIN'],
				$this->relations
			);
		}
		$rows = $this->db->getRows( SQLBuilder::build( $query ), $bindings );
		if ( $class_entity && class_exists( $class_entity ) ) {
			$rows = array_map(
				function ( $row ) use ( $class_entity ) {
					return new $class_entity( null, $row );
				},
				$rows
			);
		}
		if ( is_array( $rows ) ) {
			return $rows;
		}
	}


	public function findRecords( string $class_entity, array $query = [], array $bindings = [] ): array {
		return $this->find( $query, $bindings, $class_entity );
	}


	public function getDb() : DBAL {
		return $this->db;
	}

}
