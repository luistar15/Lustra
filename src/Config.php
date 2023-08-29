<?php

declare(strict_types=1);


namespace Lustra;


use Exception;


class Config {

	protected array $data = [];


	public function loadJson(
		string $json,
	) : void {

		if ( $json === '' ) {
			return;
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			throw new Exception( 'Error parsing config string' );
		}

		$this->data = array_replace_recursive( $this->data, $data );
	}


	public function loadJsonFile(
		string $file,
	) : void {

		$this->loadJson( $this->getConfigFileContent( $file ) );
	}


	public function loadIni(
		string $ini,
	) : void {

		$data = parse_ini_string( $ini, true, INI_SCANNER_TYPED );

		if ( $data === false ) {
			throw new Exception( 'Error parsing ini string' );
		}

		$this->data = array_replace_recursive( $this->data, $data );
	}


	public function loadIniFile(
		string $file,
	) : void {

		$this->loadIni( $this->getConfigFileContent( $file ) );
	}


	private function getConfigFileContent(
		string $file,
	) : string {

		if ( ! is_file( $file ) ) {
			throw new Exception( "Config file not found: {$file}" );
		}

		$content = file_get_contents( $file );

		if ( is_string( $content ) ) {
			return trim( $content );
		}

		throw new Exception( "Error loading config file: {$file}" );
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

				$this->set( $section, $key, $value );
			}
		}
	}


	public function exists(
		string $section,
		string $key,
	) : bool {

		return isset( $this->data[ $section ][ $key ] );
	}


	public function get(
		string $section,
		string $key,
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
		string $section,
		string $key,
	) : int {

		$value = $this->get( $section, $key );

		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return intval( $value );
		}

		throw new Exception( "Invalid int value for [{$key}]" );
	}


	public function getString(
		string $section,
		string $key,
	) : string {

		$value = $this->get( $section, $key );

		if ( is_string( $value ) || is_int( $value ) ) {
			return strval( $value );
		}

		throw new Exception( "Invalid string value for [{$key}]" );
	}


	public function getBool(
		string $section,
		string $key,
	) : bool {

		$value = $this->get( $section, $key );

		if ( is_bool( $value ) || is_int( $value ) || is_string( $value ) ) {
			return boolval( $value );
		}

		throw new Exception( "Invalid boolean value for [{$key}]" );
	}


	/** @return array<string|int, string|int|bool|null> */
	public function getSection(
		string $section,
	) : array {

		if ( ! isset( $this->data[ $section ] ) ) {
			throw new Exception( "Config section [{$section}] was not found" );
		}

		return $this->data[ $section ];
	}


	public function set(
		string $section,
		string $key,
		mixed $value,
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
