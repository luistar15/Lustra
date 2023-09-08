<?php

declare(strict_types=1);


namespace Lustra\DB;


use Exception;


abstract class ActiveRecords {

	/**
	 * @var \Lustra\DB\DBAL
	 */
	protected $db;
	/**
	 * @var string|null
	 */
	protected $entity_class;
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


	public function __construct( DBAL $db, ?string $entity_class = null ) {
		$this->db           = $db;
		$this->entity_class = $entity_class;
	}


	public function find( array $query = [], array $bindings = [], bool $map_entities = false ): array {
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
				function ( $row ) use ( $entity_class ) {
					return new $entity_class( $this->db, $row );
				},
				$rows
			);
		}
		return $rows;
	}


	/** @return ActiveRecord[] */
	public function findRecords( array $query = [], array $bindings = [] ): array {
		return $this->find( $query, $bindings, true );
	}


	public function getTableName() : string {
		return $this->table;
	}


	public function getDb() : DBAL {
		return $this->db;
	}

}
