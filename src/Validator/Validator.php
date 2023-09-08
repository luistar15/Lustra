<?php

declare(strict_types=1);


namespace Lustra\Validator;


class Validator {

	/*
	 * $params = Validator::validate(
	 *   $array_source,
	 *   [
	 * 	   'array_key' => [ $data_type, $required_flag, $default_value ],
	 *   ]
	 * );
	 */
	public static function validate( array $source, array $rules, array $types = [] ): bool {
		$valid = true;
		try {
			self::parse( $source, $rules, $types );
		} catch ( ValidatorException $e ) {
			$valid = false;
		}
		return $valid;
	}


	public static function parse( array $source, array $rules, array $types = [] ): array {
		$parsed = [];
		foreach ( $rules as $k => $rule ) {
			$value   = $source[ $k ] ?? '';
			$missing = $value === '';

			$type     = $rule[0];
			$required = $rule[1] ?? false;
			$default  = $rule[2] ?? null;

			if ( ! array_key_exists( 2, $rule ) && isset( $source[ $k ] ) ) {
				$default = '';
			}

			$error = null;

			if ( $required && $missing ) {
				$error = ValidatorException::build(
					ValidatorException::MISSING_VALUE,
					"Missing value for: {$k}"
				);

			} else if ( ! $missing && ! self::validateValue( $value, $type, $types ) ) {
				$error = ValidatorException::build(
					ValidatorException::INVALID_VALUE,
					"'{$value}' is not a valid {$type} value for: {$k}"
				);
			}

			if ( $error ) {
				$error->setData(
					[
						'var'      => $k,
						'type'     => $type,
						'value'    => $value,
						'required' => $required,
					]
				);

				throw $error;
			}

			$parsed[ $k ] = $missing ? $default : $value;
		}
		return $parsed;
	}


	public static function validateValue( string $value, string $type, array $types = [] ): bool {
		if ( isset( self::$types[ $type ] ) ) {
			return preg_match( self::$types[ $type ], $value ) === 1;
		}
		if ( isset( $types[ $type ] ) ) {
			return preg_match( $types[ $type ], $value ) === 1;
		}
		$filters = [
			'EMAIL'  => FILTER_VALIDATE_EMAIL,
			'IP'     => FILTER_VALIDATE_IP,
			'URL'    => FILTER_VALIDATE_URL,
			'DOMAIN' => FILTER_VALIDATE_DOMAIN,
			'MAC'    => FILTER_VALIDATE_MAC,
		];
		if ( isset( $filters[ $type ] ) ) {
			return filter_var( $value, $filters[ $type ] ) !== false;
		}
		throw ValidatorException::build(
			ValidatorException::MISSING_TYPE,
			"Missing validator type: {$type}",
			[
				'type'  => $type,
				'value' => $value,
			]
		);
	}


	/**
	 * @var mixed[]
	 */
	private static $types = [
		'BOOL'     => '/^[01]$/',
		'DATE'     => '/^\d{4}-\d{2}-\d{2}$/',
		'DATETIME' => '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/',
		'TIME'     => '/^\d{2,3}:\d{2}:\d{2}$/',
		'DIGIT'    => '/^\d+$/',
		'ALPHA'    => '/^[a-z]+$/i',
		'ALNUM'    => '/^[\da-z]+$/i',
		'TEXT'     => '/\S/i',
	];

}
