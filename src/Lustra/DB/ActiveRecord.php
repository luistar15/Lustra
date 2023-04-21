<?php

namespace Lustra\DB;

abstract class ActiveRecord {

	protected DBAL $__db;  // phpcs:ignore

	protected string $__table    = '<table>';  // phpcs:ignore
	protected string $__pk       = 'id';       // phpcs:ignore
	protected array $__relations = [];         // phpcs:ignore
	protected array $__data      = [];         // phpcs:ignore


	public function __construct(DBAL $db) {
		$this->__db = $db;
	}


	public function __set(string $k, mixed $v): void {
		$this->__data[$k] = $v;
	}


	public function __get(string $k): mixed {
		return $this->__data[$k] ?? null;
	}


	public function setData(iterable $data): void {
		foreach ($data as $k => $v) {
			$this->__data[$k] = $v;
		}
	}


	public function getData(array $columns = []): array {
		if (count($columns) === 0) {
			return $this->__data;
		}

		$data = [];

		foreach ($columns as $k) {
			$data[$k] = $this->__data[$k];
		}

		return $data;
	}


	public function getPk(): ?string {
		return $this->__data[$this->__pk] ?? null;
	}


	public function setPk(?string $pk): void {
		$this->__data[$this->__pk] = $pk;
	}


	public function exists(): bool {
		return (bool) $this->getPk();
	}


	// -------------------------------------------------------------------------


	public function load(
		array $query,
		array $bindings = []
	): array {

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


	public function loadByColumn(
		string $column,
		string $value
	): array {

		return $this->load(
			['WHERE' => "`{$column}` = :_VAL_"],
			[':_VAL_' => $value]
		);
	}


	public function loadByPk(string $pk): array {
		return $this->loadByColumn($this->__pk, $pk);
	}


	// -------------------------------------------------------------------------


	public function save(array $columns = []): void {
		$data = $this->getData($columns);

		if ($this->exists()) {
			$this->__db->update($this->__table, $data);

		} else {
			$this->__db->insert($this->__table, $data);

			$insert_id = $this->__db->lastInsertId();

			if (is_string($insert_id)) {
				$this->setPk($insert_id);
			}
		}
	}


	public function delete(): void {
		$this->__db->delete(
			$this->__table,
			['WHERE' => sprintf("`%s` = :pk", $this->__pk)],
			[':pk' => $this->getPk()]
		);
	}


	// -------------------------------------------------------------------------


	public function find(
		array $query,
		array $bindings = []
	): array {

		$query = array_merge($query, ['FROM' => $this->__table]);

		if (isset($query['JOIN'])) {
			$query['JOIN'] = SQLBuilder::parseJoins((array) $query['JOIN'], $this->__relations);
		}

		$rows = $this->__db->getRows(SQLBuilder::build($query), $bindings);

		if (is_array($rows)) {
			return $rows;
		}
	}


	public function getDb(): DBAL {
		return $this->__db;
	}

}
