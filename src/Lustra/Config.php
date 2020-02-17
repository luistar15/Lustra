<?php

namespace Lustra;


class Config {

	private array $config = [];


	public function load (string $file) : void {
		$this->config = array_replace_recursive($this->config,
			parse_ini_file($file, true, INI_SCANNER_TYPED),
		);
	}


	public function loadEnv (
		string $folder,
		string $env

	) : void {

		$this->load("{$folder}/config.base.ini");
		$this->load("{$folder}/config.{$env}.ini");
	}


	/**
	 * @param mixed $default
	 * @return mixed
	 */

	public function get (
		string $section,
		string $key,
		       $default = null
	) {

		return $this->config[$section][$key] ?? $default;
	}

}
