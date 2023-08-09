<?php

declare(strict_types=1);


namespace Lustra;


use Psr\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use ReflectionFunction;
use Exception;


class Container implements ContainerInterface {

	private array $store    = [];
	private array $builders = [];


	public function has(
		string $k,
	) : bool {

		return array_key_exists( $k, $this->store ) ||
			   array_key_exists( $k, $this->builders );
	}


	public function add(
		string $k,
		mixed $v,
	) : void {

		if ( is_callable( $v ) ) {
			$this->builders[ $k ] = $v;
		} else {
			$this->store[ $k ] = $v;
		}
	}


	public function get(
		string $k,
	) : mixed {

		if ( array_key_exists( $k, $this->store ) ) {
			return $this->store[ $k ];
		}

		if ( isset( $this->builders[ $k ] ) ) {
			$this->store[ $k ] = $this->build( $k );
			return $this->store[ $k ];
		}

		throw new NotFoundException( "[{$k}] not found in container" );
	}


	public function build(
		string $k,
	) : mixed {

		return $this->builders[ $k ]( $this );
	}


	public function getDebugInfo() : array {
		$entries = [];

		foreach ( $this->store as $k => $v ) {
			if ( is_object( $v ) ) {
				$entries[ $k ] = 'Object: ' . get_class( $v );
			} else {
				$entries[ $k ] = $v;
			}
		}

		foreach ( $this->builders as $k => $v ) {
			if ( ! isset( $entries[ $k ] ) ) {
				$entries[ $k ] = strval( new ReflectionFunction( $this->builders[ $k ] ) );
			}
		}

		return $entries;
	}

}


class ContainerException extends Exception implements ContainerExceptionInterface {}


class NotFoundException extends Exception implements NotFoundExceptionInterface {}
