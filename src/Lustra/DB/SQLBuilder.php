<?php

declare(strict_types=1);

namespace Lustra\DB;

use Exception;

class SQLBuilder {

	public static function build(array $query): string {

		$query = array_merge([
			'DISTINCT' => false,
			'COLUMNS'  => ['*'],
			'FROM'     => '<table>',
			'JOIN'     => [],
			'WHERE'    => [],
			'GROUP'    => [],
			'HAVING'   => [],
			'ORDER'    => [],
			'LIMIT'    => null,
		], $query);


		$sql = ['SELECT'];

		foreach ($query as $k => $v) {
			if (is_string($v) && !in_array($k, ['DISTINCT', 'FROM', 'LIMIT'])) {
				$v = [$v];
			}

			switch ($k) {
				case 'DISTINCT':
					if ($v) {
						$sql[] = $k;
					}
					break;

				case 'COLUMNS':
					$sql[] = implode(', ', $v);
					break;

				case 'FROM':
				case 'LIMIT':
					if ($v) {
						$sql[] = "{$k} {$v}";
					}
					break;

				case 'JOIN':
					if (count($v) > 0) {
						$sql[] = implode(' ', $v);
					}
					break;

				case 'WHERE':
				case 'HAVING':
					if (count($v) > 0) {
						$sql[] = "{$k} " . implode(' AND ', $v);
					}
					break;

				case 'GROUP':
				case 'ORDER':
					if (count($v) > 0) {
						$sql[] = "{$k} BY " . implode(', ', $v);
					}
					break;
			}
		}

		return rtrim(implode(' ', $sql));
	}


	public static function parseJoins(
		array $joins,
		array $relations = []
	): array {

		$parsed = [];

		$joins = array_unique($joins);

		foreach ($joins as $join) {
			$join = trim($join);

			if (empty($join)) {
				continue;
			}

			$regex = '/^((?<a>INNER|LEFT|RIGTH|FULL)(\s+JOIN)?\s+)?(?<b>\S+)(\s+ON\s+(?<c>.+))?$/i';

			if (!preg_match($regex, $join, $matches)) {
				throw new Exception("Malformed JOIN clause: '{$join}'");
			}

			$type      = $matches['a'] ?: 'INNER';
			$table     = $matches['b'];
			$condition = $matches['c'] ?? null;

			if (isset($relations[$table])) {
				if ($condition) {
					$condition = "({$relations[$table]}) AND ({$condition})";
				} else {
					$condition = $relations[$table];
				}
			}

			if (empty($condition)) {
				throw new Exception("Malformed JOIN clause: '{$join}'");
			}

			$parsed[] = "{$type} JOIN {$table} ON {$condition}";
		}

		return $parsed;
	}

}
