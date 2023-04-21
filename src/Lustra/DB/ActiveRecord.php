<?php

namespace Lustra\DB;

abstract class ActiveRecord {

	protected DBAL $db;

	protected string $table = '';
	protected string $pk    = 'id';

	protected array $relations = [];
	protected array $data      = [];


	public function __construct(DBAL $db) {
		$this->db = $db;
	}


	public function set(string $k, mixed $v): void {
		$this->data[$k] = $v;
	}


	public function get(string $k): mixed {
		return $this->data[$k] ?? null;
	}


	public function setData(iterable $data): void {
		foreach ($data as $k => $v) {
			$this->data[$k] = $v;
		}
	}


	public function getData(array $columns = []): array {
		if (count($columns) === 0) {
			return $this->data;
		}

		$data = [];

		foreach ($columns as $k) {
			$data[$k] = $this->data[$k];
		}

		return $data;
	}


	public function getPk(): ?string {
		return $this->data[$this->pk] ?? null;
	}


	public function setPk(?string $pk): void {
		$this->data[$this->pk] = $pk;
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

		$this->data = current($rows);

		return $this->data;
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
		return $this->loadByColumn($this->pk, $pk);
	}


	// -------------------------------------------------------------------------


	public function save(array $columns = []): void {
		$data = $this->getData($columns);

		if ($this->exists()) {
			$this->db->update($this->table, $data);

		} else {
			$this->db->insert($this->table, $data);

			$insert_id = $this->db->lastInsertId();

			if (is_string($insert_id)) {
				$this->setPk($insert_id);
			}
		}
	}


	public function delete(): void {
		$this->db->delete(
			$this->table,
			['WHERE' => sprintf("`%s` = :pk", $this->pk)],
			[':pk' => $this->getPk()]
		);
	}


	// -------------------------------------------------------------------------


	public function find(
		array $query,
		array $bindings = []
	): array {

		$query = array_merge($query, ['FROM' => $this->table]);

		if (isset($query['JOIN'])) {
			$query['JOIN'] = SQLBuilder::parseJoins((array) $query['JOIN'], $this->relations);
		}

		$rows = $this->db->getRows(SQLBuilder::build($query), $bindings);

		if (is_array($rows)) {
			return $rows;
		}
	}


	public function getDb(): DBAL {
		return $this->db;
	}

}
