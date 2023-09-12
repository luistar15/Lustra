<?php

declare(strict_types=1);


namespace Lustra\DB;


abstract class ActiveRecordDefinition {

	protected string $table_name;

	private string $pk_column = 'id';


	/** @var string[] */
	protected array $columns = [];


	/** @var array<string, string> */
	protected array $relations = [];


	public function __construct(
		private DBAL $db,
	) {}


	public function getDb() : DBAL {
		return $this->db;
	}


	public function getTableName() : string {
		return $this->table_name;
	}


	public function getPkColumn() : string {
		return $this->pk_column;
	}


	public function getRelations() : array {
		return $this->relations;
	}


	public function getColumns() : array {
		return $this->columns;
	}

}
