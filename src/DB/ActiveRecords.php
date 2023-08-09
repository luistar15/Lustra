<?php

declare(strict_types=1);


namespace Lustra\DB;


abstract class ActiveRecords {

	protected DBAL $db;

	protected string $table = '';
	protected string $pk    = 'id';

	protected array $relations = [];


	public function __construct(
		DBAL $db,
	) {

		$this->db = $db;
	}


	public function find(
		array $query = [],
		array $bindings = [],
		?string $class_entity = null,
	) : array {

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
				fn ( $row ) => new $class_entity( null, $row ),
				$rows
			);
		}

		if ( is_array( $rows ) ) {
			return $rows;
		}
	}


	public function findRecords(
		string $class_entity,
		array $query = [],
		array $bindings = [],
	) : array {

		return $this->find( $query, $bindings, $class_entity );
	}


	public function getDb() : DBAL {
		return $this->db;
	}

}
