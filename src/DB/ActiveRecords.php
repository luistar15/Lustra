<?php

declare(strict_types=1);


namespace Lustra\DB;


abstract class ActiveRecords {

	public function __construct(
		protected ActiveRecordDefinition $definition,
		protected string $active_record_class,
	) {}


	// -------------------------------------------------------------------------


	public function find(
		array $query = [],
		array $bindings = [],
		bool $map_entities = false,
	) : array {

		$query = array_merge( $query, [ 'FROM' => $this->definition->getTableName() ] );

		$rows = $this->definition->getDb()->getRows(
			SQLBuilder::build( $query, $this->definition->getRelations() ),
			$bindings,
		);

		if ( $map_entities ) {
			$active_record_class = $this->active_record_class;

			$rows = array_map(
				fn ( $row ) => new $active_record_class( $this->definition, $row ),
				$rows,
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


	// -------------------------------------------------------------------------


	public function getDefinition() : ActiveRecordDefinition {
		return $this->definition;
	}

}
