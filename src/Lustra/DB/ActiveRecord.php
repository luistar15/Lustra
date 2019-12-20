<?php

namespace Lustra\DB;


abstract class ActiveRecord {

	protected $__db;

	protected $__table     = '<table>';
	protected $__pk        = 'id';
	protected $__relations = [];
	protected $__data      = [];


	public function __construct (DBAL $db) {
		$this->__db = $db;
	}


	public function __set ($k, $v) {
		$this->__data[$k] = $v;
	}


	public function __get ($k) {
		return $this->__data[$k] ?? null;
	}


	public function setData (iterable $data) : void {
		foreach ($data as $k => $v) {
			$this->__data[$k] = $v;
		}
	}


	public function getData (array $columns = []) : array {
		if (count($columns) === 0) {
			return $this->__data;
		}

		$data = [];

		foreach ($columns as $k) {
			$data[$k] = $this->__data[$k];
		}

		return $data;
	}


	public function getPk () : ?string {
		return $this->__data[$this->__pk] ?? null;
	}


	public function setPk (?string $pk) : void {
		$this->__data[$this->__pk] = $pk;
	}


	public function exists () : bool {
		return (bool) $this->getPk();
	}


	// -------------------------------------------------------------------------


	public function load (
		array $query,
		array $bindings = []

	) : array {

		$query = array_merge($query, ['LIMIT' => '1']);

		$rows = $this->find($query, $bindings);

		if (count($rows) === 0) {
			throw new RecordNotFoundException(
				sprintf('%s record was not found', get_class($this))
			);
		}

		$this->__data = current($rows);

		return $this->__data;
	}


	public function loadBy (
		string $column,
		string $value

	) : array {

		return $this->load(
			['WHERE' => "`{$column}` = :_VAL_"],
			[':_VAL_' => $value]
		);
	}


	public function loadByPk (string $pk) : array {
		return $this->loadBy($this->__pk, $pk);
	}


	// -------------------------------------------------------------------------


	public function save (array $columns = []) {
		$data = $this->getData($columns);

		if ($this->exists()) {
			$this->__db->update($this->__table, $data);
		} else {
			$this->__db->insert($this->__table, $data);
			$this->setPk($this->__db->lastInsertId());
		}
	}


	public function delete () {
		$this->__db->delete(
			$this->__table,
			['WHERE' => sprintf("`%s` = :pk", $this->__pk)],
			[':pk' => $this->getPk()]
		);
	}


	// -------------------------------------------------------------------------


	public function find (
		array $query,
		array $bindings = []

	) : array {

		$query = array_merge($query, ['FROM' => $this->__table]);

		if (isset($query['JOIN'])) {
			$query['JOIN'] = SQLBuilder::parseJoins((array) $query['JOIN'], $this->__relations);
		}

		return $this->__db->getRows(SQLBuilder::build($query), $bindings);
	}


	public function getDb () : DBAL {
		return $this->__db;
	}

}
