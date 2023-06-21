<?php

declare(strict_types=1);


namespace Lustra;


use Exception;


class Config {

	private array $data = [];


	public function loadIni(
		string $file
	) : void {

		$data = parse_ini_file( $file, true, INI_SCANNER_TYPED );

		if ( $data === false ) {
			throw new Exception( "Error parsing ini file: {$file}" );
		}

		$this->data = array_replace_recursive( $this->data, $data );
	}


	public function loadEnv(
		array $map
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
		string $section = 'global'
	) : bool {

		return isset( $this->data[ $section ][ $key ] );
	}


	public function get(
		string $key,
		string $section = 'global',
		mixed $default = null,
		array $placeholders = []
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


	public function set(
		string $key,
		mixed $value,
		string $section = 'global'
	) : void {

		if ( ! isset( $this->data[ $section ] ) ) {
			$this->data[ $section ] = [];
		}

		$this->data[ $section ][ $key ] = $value;
	}


	public function replacePlaceholders(
		array $placeholders = []
	) : void {

		if ( count( $placeholders ) > 0 ) {
			foreach ( $this->data as $section => $values ) {
				foreach ( $values as $k => $v ) {
					if ( is_string( $v ) ) {
						$this->data[ $section ][ $k ] = self::replacePlaceholdersInValue( $v, $placeholders );
					}
				}
			}
		}
	}


	private static function replacePlaceholdersInValue(
		string $value,
		array $placeholders
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
