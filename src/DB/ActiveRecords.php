<?php

declare(strict_types=1);


namespace Lustra\DB;


use Exception;


abstract class ActiveRecords {

	protected string $table = '';
	protected string $pk    = 'id';

	protected array $relations = [];


	public function __construct(
		protected DBAL $db,
		protected ?string $entity_class = null,
	) {}


	public function find(
		array $query = [],
		array $bindings = [],
		bool $map_entities = false,
	) : array {

		$query = array_merge( $query, [ 'FROM' => $this->table ] );

		if ( isset( $query['JOIN'] ) ) {
			$query['JOIN'] = SQLBuilder::parseJoins(
				$query['JOIN'],
				$this->relations
			);
		}

		$rows = $this->db->getRows( SQLBuilder::build( $query ), $bindings );

		if ( $map_entities ) {
			$entity_class = $this->entity_class ?? null;

			if ( ! isset( $entity_class ) || ! class_exists( $entity_class ) ) {
				throw new Exception( 'Can not find entity class: ' . $entity_class );
			}

			$rows = array_map(
				fn ( $row ) => new $entity_class( $this->db, $row ),
				$rows
			);
		}

		return $rows;
	}


	/** @return ActiveRecord[] */
	public function findRecords(
		array $query = [],
		array $bindings = [],
	) : array {

		return $this->find( $query, $bindings, true );
	}


	public function getTableName() : string {
		return $this->table;
	}


	public function getDb() : DBAL {
		return $this->db;
	}

}
