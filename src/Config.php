<?php

declare(strict_types=1);


namespace Lustra;


use Exception;


class Config {

	protected array $data = [];


	public function loadIniFile(
		string $file,
	) : void {

		$data = parse_ini_file( $file, true, INI_SCANNER_TYPED );

		if ( $data === false ) {
			throw new Exception( "Error parsing ini file: {$file}" );
		}

		$this->data = array_replace_recursive( $this->data, $data );
	}


	public function loadIni(
		string $data,
	) : void {

		$data = parse_ini_string( $data, true, INI_SCANNER_TYPED );

		if ( $data === false ) {
			throw new Exception( 'Error parsing ini string' );
		}

		$this->data = array_replace_recursive( $this->data, $data );
	}


	public function loadEnv(
		array $map,
	) : void {

		foreach ( $map as $section => $vars ) {
			foreach ( $vars as $key => $var ) {
				$value = getenv( $var );

				if ( $value === false ) {
					continue;
				}

				$value_lower = strtolower( $value );

				if ( $value_lower === 'null' ) {
					$value = null;

				} else if ( in_array( $value_lower, [ 'true', 'on', 'yes' ], true ) ) {
					$value = true;

				} else if ( in_array( $value_lower, [ 'false', 'off', 'no', 'none' ], true ) ) {
					$value = false;
				}

				$this->set( $key, $value, $section );
			}
		}
	}


	public function exists(
		string $key,
		string $section = 'global',
	) : bool {

		return isset( $this->data[ $section ][ $key ] );
	}


	public function get(
		string $key,
		string $section = 'global',
		mixed $default = null,
		array $placeholders = [],
	) : mixed {

		if ( ! isset( $this->data[ $section ][ $key ] ) && is_null( $default ) ) {
			throw new Exception( self::class . " [$section][$key] was not found" );
		}

		$value = $this->data[ $section ][ $key ] ?? $default;

		if ( is_string( $value ) && count( $placeholders ) > 0 ) {
			$value = self::replacePlaceholdersInValue( $value, $placeholders );
		}

		return $value;
	}


	public function getInt(
		string $key,
		string $section = 'global',
	) : int {

		$value = $this->get( $key, $section );

		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return intval( $value );
		}

		throw new Exception( "Invalid int value for [{$key}]" );
	}


	public function getString(
		string $key,
		string $section = 'global',
	) : string {

		$value = $this->get( $key, $section );

		if ( is_string( $value ) || is_int( $value ) ) {
			return strval( $value );
		}

		throw new Exception( "Invalid string value for [{$key}]" );
	}


	public function getBool(
		string $key,
		string $section = 'global',
	) : bool {

		$value = $this->get( $key, $section );

		if ( is_bool( $value ) || is_int( $value ) || is_string( $value ) ) {
			return boolval( $value );
		}

		throw new Exception( "Invalid boolean value for [{$key}]" );
	}


	public function set(
		string $key,
		mixed $value,
		string $section = 'global',
	) : void {

		if ( ! isset( $this->data[ $section ] ) ) {
			$this->data[ $section ] = [];
		}

		$this->data[ $section ][ $key ] = $value;
	}


	public function replacePlaceholders(
		array $placeholders = [],
	) : void {

		if ( count( $placeholders ) === 0 ) {
			return;
		}

		foreach ( $this->data as $section => $values ) {
			foreach ( $values as $k => $v ) {
				if ( is_string( $v ) ) {
					$this->data[ $section ][ $k ] = self::replacePlaceholdersInValue( $v, $placeholders );
				}
			}
		}
	}


	private static function replacePlaceholdersInValue(
		string $value,
		array $placeholders,
	) : string {

		return str_replace(
			array_map( fn ( $k) => "{{$k}}", array_keys( $placeholders ) ),
			array_values( $placeholders ),
			$value
		);
	}


	public function getDebugInfo() : array {
		$info = [];

		foreach ( $this->data as $section => $values ) {
			foreach ( $values as $key => $value ) {
				$info[ "[{$section}] {$key}" ] = $value;
			}
		}

		return $info;
	}

}
