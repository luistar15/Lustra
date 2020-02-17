<?php

namespace Lustra;

use Exception;

class Config {

	private array $config = [];


	public function loadIniFile (string $file) : void {
		$this->config = array_replace_recursive($this->config,
			parse_ini_file($file, true, INI_SCANNER_TYPED),
		);
	}


	public function loadEnv (
		string $folder,
		string $env

	) : void {

		$this->loadIniFile("{$folder}/config.base.ini");
		$this->loadIniFile("{$folder}/config.{$env}.ini");
	}


	public function exists (
		string $section,
		string $key

	) : bool {

		return isset($this->config[$section][$key]);
	}


	/**
	 * @param mixed $default
	 * @return mixed
	 */

	public function get (
		string $section,
		string $key,
		       $default = null,
		 array $placeholders = []
	) {

		$value = $this->config[$section][$key] ?? $default;

		if (is_null($value)) {
			throw new Exception(self::class . " [$section][$key] was not found");
		}

		if (count($placeholders) > 0) {
			$value = self::replacePlaceholdersInValue($value, $placeholders);
		}

		return $value;
	}


	public function replacePlaceholders (array $placeholders = []) : void {
		if (count($placeholders) > 0) {
			foreach ($this->config as $section => $values) {
				foreach ($values as $k => $v) {
					if (is_string($v)) {
						$this->config[$section][$k] = self::replacePlaceholdersInValue($v, $placeholders);
					}
				}
			}
		}
	}


	private static function replacePlaceholdersInValue (
		string $value,
		array $placeholders

	) : string {

		$value = str_replace(
			array_map(fn ($k) => "{$k}", array_keys($placeholders)),
			array_values($placeholders),
			$value
		);

		return $value;
	}

}
