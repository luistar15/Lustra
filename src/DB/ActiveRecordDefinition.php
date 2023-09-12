<?php

declare(strict_types=1);


namespace Lustra\DB;


abstract class ActiveRecordDefinition {

	/**
	 * @var \Lustra\DB\DBAL
	 */
	private $db;
	/**
	 * @var string
	 */
	protected $table_name;

	/**
	 * @var string
	 */
	private $pk_column = 'id';


	/** @var string[] */
	protected $columns = [];


	/** @var array<string, string> */
	protected $relations = [];


	public function __construct( DBAL $db ) {
		$this->db = $db;
	}


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
