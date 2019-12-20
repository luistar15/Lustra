<?php

namespace Lustra;


use Exception;


class Container {

	private array $store = [];
	private array $builders = [];


	public function has (string $k) : bool {
		return array_key_exists($k, $this->store) ||
		       array_key_exists($k, $this->builders);
	}


	/** @param mixed $v */

	public function add (string $k, $v) : void {
		if (is_callable($v)) {
			$this->builders[$k] = $v;
		} else {
			$this->store[$k] = $v;
		}
	}


	/** @return mixed */

	public function get (string $k) {
		if (array_key_exists($k, $this->store)) {
			return $this->store[$k];
		}

		if (isset($this->builders[$k])) {
			$this->store[$k] = $this->build($k);
			return $this->store[$k];
		}

		throw new Exception("'{$k}' not found in container");
	}


	/** @return mixed */

	public function build (string $k) {
		return $this->builders[$k]($this);
	}

}
