<?php

namespace Lustra\Validator;


class Validator {

	/*
	 * $params = Validator::parse($array_source, [
	 * 	'array_key' => [$data_type, $required_flag, $default_value],
	 * ]);
	 */

	 public static function validate (
		array $source,
		array $rules,
		array $types = [],
		bool  $return_parsed = false
	) {

		$parsed = [];

		foreach ($rules as $k => $rule) {
			$value   = $source[$k] ?? '';
			$missing = $value === '';

			$type     = $rule[0];
			$required = $rule[1] ?? false;
			$default  = $rule[2] ?? null;

			$error = null;

			if ($required && $missing) {
				$error = ValidatorException::build(
					ValidatorException::MISSING_VALUE,
					"Missing value for: {$k}"
				);

			} else if (!$missing && !self::validateValue($value, $type, $types)) {
				$error = ValidatorException::build(
					ValidatorException::INVALID_VALUE,
					"'{$value}' is not a valid {$type} value for: {$k}"
				);
			}

			if ($error) {
				$error->setData([
					'var'      => $k,
					'type'     => $type,
					'value'    => $value,
					'required' => $required
				]);

				if ($return_parsed) {
					throw $error;
				} else {
					return $error;
				}

			} else if ($return_parsed) {
				$parsed[$k] = $missing ? $default : $value;
			}
		}

		return $return_parsed ? $parsed : true;
	}


	public static function parse (
		array $source,
		array $rules,
		array $types = []

	) : array {

		return self::validate($source, $rules, $types, true);
	}


	public static function validateValue (
		string $value,
		string $type,
		array  $types = []

	) : bool {

		if (isset(self::$types[$type])) {
			return preg_match(self::$types[$type], $value);
		}

		if (isset($types[$type])) {
			return preg_match($types[$type], $value);
		}

		if (in_array($type, ['DOMAIN', 'EMAIL', 'IP', 'MAC', 'URL'])) {
			return filter_var($value, constant("FILTER_VALIDATE_{$type}"));
		}

		throw ValidatorException::build(
			ValidatorException::MISSING_TYPE,
			"Missing validator type: {$type}",
			['type' => $type, 'value' => $value]
		);
	}


	private static $types = [
		'BOOL'     => '/^[01]$/',
		'DATE'     => '/^\d{4}-\d{2}-\d{2}$/',
		'DATETIME' => '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/',
		'DIGIT'    => '/^\d+$/',
		'ALPHA'    => '/^[a-z]+$/i',
		'ALNUM'    => '/^[\da-z]+$/i',
		'TEXT'     => '/\S/i',
	];

}
