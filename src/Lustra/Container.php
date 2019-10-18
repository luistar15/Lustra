<?php

namespace Lustra;


class Container {

	private $store = [];

	private $builders = [];


	public function has (string $k) : bool {
		return array_key_exists($k, $this->store) ||
		       array_key_exists($k, $this->builders);
	}


	public function add (string $k, $v) : void {
		if (is_callable($v)) {
			$this->builders[$k] = $v;
		} else {
			$this->store[$k] = $v;
		}
	}


	public function get (string $k) {
		if (array_key_exists($k, $this->store)) {
			return $this->store[$k];
		}

		if (isset($this->builders[$k])) {
			$this->store[$k] = $this->build($k);
			return $this->store[$k];
		}

		throw new ContainerException("'{$k}' not found in container");
	}


	public function build (string $k) {
		return $this->builders[$k]($this);
	}

}


class ContainerException extends \Exception {};
