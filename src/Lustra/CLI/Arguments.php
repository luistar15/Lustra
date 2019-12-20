<?php

namespace Lustra\CLI;


use Lustra\Validator\Validator;
use Lustra\Validator\ValidatorException;


class Arguments {

	private $params;
	private $rules;
	private $types;


	/*
	 * new CliArguments([
	 *  'param_a'=> [
	 *    'key'         => '--option',
	 *    'type'        => 'TEXT',
	 *    'required'    => false,
	 *    'default'     => 'default-value',
	 *    'description' => 'Parameter description',
	 *  ],
	 *
	 *  'param_b'=> [
	 *    'key'         => ['--option', '-o'],
	 *    'type'        => 'FLAG',
	 *    'description' => 'Flag parameter'
	 *  ],
	 *
	 *  'param_c'=> [
	 *    'key'         => 0,
	 *    'description' => 'Positional parameter'
	 *  ],
	 * ]));
	 */

	public function __construct (
		array $params,
		array $types = []
	) {

		[$this->params, $this->rules] = $this->parseParams($params);

		$this->types = array_merge($types, [
			'FLAG'  => '/^[01]$/',
			'USAGE' => '/^[01]$/',
		]);
	}


	public function parse (
		array $args,
		bool  $show_usage_on_error = true

	) : array {

		$parsed     = [];
		$positional = [];

		$i = 0;
		$p = 0;
		$l = count($args);

		while ($i < $l) {
			[$current_key,] = self::parseArgument($args, $i);

			if ($current_key) {
				$param = $this->findParamByKey($current_key);

				if ($param) {
					$k = $param['id'];

					if ($param['type'] === 'USAGE') {
						$this->displayUsage();
					}

					if ($param['type'] === 'FLAG') {
						$parsed[$k] = '1';

					} else {
						[,$next_value]  = self::parseArgument($args, $i + 1);

						if (is_string($next_value)) {
							$parsed[$k] = $next_value;
							$i++;
						}
					}
				}

			} else {
				$positional[$p++] = $args[$i];
			}

			$i++;
		}

		foreach ($positional as $k => $v) {
			$param = $this->findParamByKey((string) $k);

			if ($param) {
				$parsed[$param['id']] = $v;
			}
		}

		try {
			$data = Validator::parse($parsed, $this->rules, $this->types);

			foreach ($this->params as $k => $param) {
				if ($param['type'] === 'FLAG') {
					$data[$k] = !!$data[$k];
				}
			}

			return $data;

		} catch (ValidatorException $e) {

			if (!$show_usage_on_error || $e->isMissingType()) {
				throw $e;
			}

			if ($e->isMissingValue()) {
				$error_message = sprintf(
					'Missing value for: <%s>',
					$e->getData()['var']
				);

			} else if ($e->isInvalidValue()) {
				$error_message = sprintf(
					'"%s" is not a valid %s for: <%s>',
					$e->getData()['value'],
					$e->getData()['type'],
					$e->getData()['var']
				);

			} else {
				$error_message = $e->getMessage();
			}

			$this->displayUsageError($error_message);
		}
	}


	public function displayUsage (string $header = '') : void {
		$named_params      = [];
		$positional_params = [];

		foreach ($this->params as $k => $param) {
			if (is_string($param['key']) && ctype_digit($param['key'])) {
				$positional_params[(int) $param['key']] = $k;
			} else {
				$named_params[] = $k;
			}
		}

		// -------------------

		$positional_options = [];

		foreach ($positional_params as $k) {
			$param = $this->params[$k];

			$param_name  = $k;
			$required    = $param['required'] ? '(required)  ' : '';
			$description = $param['description'] ?? '';

			$positional_options[] = [
				/* 1 */   "  $param_name  ",
				/* 2 */   $required,
				/* 3 */   " $description",
			];
		}

		// -------------------

		$named_options = [];

		foreach ($named_params as $k) {
			$param = $this->params[$k];

			$short_opt   = '   ';
			$long_opts   = '';
			$varname     = '';
			$required    = '';
			$description = $param['description'] ?? '';

			if (is_string($param['key'])) {
				$param['key'] = [$param['key']];
			}

			usort($param['key'], function ($a, $b) { return strlen($a) - strlen($b); });

			if (strlen($param['key'][0]) === 2) {
				$short_opt = array_shift($param['key']);
				$short_opt .= count($param['key']) ? ',' : '';
			}

			$long_opts = implode(', ', $param['key']);

			if (!in_array($param['type'], ['FLAG', 'USAGE'])) {
				$varname = $param['required'] ? "<{$k}>" : "[{$k}]";
			}

			if ($param['required']) {
				$required = '(required)  ';
			}

			$named_options[] = [
				/* 1 */   " $short_opt ",
				/* 2 */   "$long_opts  ",
				/* 3 */   "$varname  ",
				/* 4 */   $required,
				/* 5 */   $description,
			];
		}

		// -------------------

		$usage = 'php ' . ($GLOBALS['argv'][0] ?? 'script');

		if (count($named_params) > 0) {
			$usage .= ' [option]...';
		}

		foreach ($positional_params as $k) {
			$usage .= $this->params[$k]['required'] ? " <{$k}>" : " [{$k}]";
		}

		if ($header) {
			print("{$header}\n");
		}

		print("\nUSAGE:\n  {$usage}\n");

		self::printTable('PARAMETERS', $positional_options);
		self::printTable('OPTIONS', $named_options);

		exit;
	}


	public function displayUsageError (string $error_message) : void {
		$this->displayUsage("\e[41mERROR:\e[0m\n  \e[36m{$error_message}\e[0m");
	}


	// -------------------------------------------------------------------------


	private function findParamByKey (string $key) : ?array {
		foreach ($this->params as $param) {
			if (is_string($param['key'])) {
				if ($key === $param['key']) {
					return $param;
				}

			} else if (in_array($key, $param['key'])) {
				return $param;
			}
		}

		return null;
	}


	private static function parseParams (array $params) : array {
		$rules = [];

		foreach ($params as $k => $param) {
			$type     = $param['type']     ?? 'TEXT';
			$required = $param['required'] ?? false;
			$default  = $param['default']  ?? null;

			if (in_array($type, ['FLAG', 'USAGE'])) {
				$required = false;
			}

			if (is_int($param['key'])) {
				$param['key'] = (string) $param['key'];
			}

			$param['id']       = $k;
			$param['type']     = $type;
			$param['type']     = $type;
			$param['required'] = $required;
			$param['default']  = $default;

			$params[$k] = $param;
			$rules[$k]  = [$type, $required, $default];
		}

		return [$params, $rules];
	}


	private static function parseArgument (array $args, int $i) : array {
		if (isset($args[$i])) {
			$is_key = preg_match('/^(-\w|--[\w\-]+)$/i', $args[$i]);
			return $is_key ? [$args[$i], null] : [null, $args[$i]];
		}

		return [null, null];
	}


	private static function printTable (
		string $title,
		array  $rows

	) : void {

		if (count($rows) === 0) {
			return;
		}

		$col_widths = [];

		foreach ($rows[0] as $i => $col) {
			$col_widths[$i] = max(array_map(
				function ($s) { return strlen($s); },
				array_column($rows, $i)
			));
		}

		// -----------

		print("\n{$title}:\n");

		foreach ($rows as $cols) {
			foreach ($cols as $i => $col) {
				if ($i < count($cols)) {
					print(str_pad($col, $col_widths[$i], ' ', STR_PAD_RIGHT));
				} else {
					print(rtrim($col));
				}
			}

			print("\n");
		}
	}

}
