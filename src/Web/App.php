<?php

declare(strict_types=1);


namespace Lustra\Web;


use Lustra\Web\Router\Router;

use Psr\Container\ContainerInterface;

use Closure;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionNamedType;


class App {

	/**
	 * @var \Psr\Container\ContainerInterface
	 */
	public $container;

	/**
	 * @var \Lustra\Web\Router\Router
	 */
	public $router;

	/**
	 * @var mixed[]
	 */
	public $route;

	/**
	 * @var string
	 */
	private $template_dir = '.';


	public function __construct( ContainerInterface $container, Router $router ) {
		$this->container = $container;
		$this->router    = $router;
	}


	public function run() : void {
		$path   = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$method = $_SERVER['REQUEST_METHOD'];

		if ( is_string( $path ) ) {
			$this->route = $this->router->findMatch( $path, $method );
			$this->loadController();
		}
	}


	public function loadController() : void {
		$controller = $this->route['controller'];

		if ( $controller instanceof Closure ) {
			$arguments = $this->findServiceArguments(
				new ReflectionFunction( $controller ),
				true
			);

			$controller( ...$arguments );

		} else {
			[ $class, $method ] = explode( '@', $controller );

			$arguments = $this->findServiceArguments(
				new ReflectionMethod( $class, $method ),
				true
			);

			$controller = $this->instantiateService( $class );
			$controller->{$method}( ...$arguments );
		}
	}


	public function instantiateService( string $class ): object {
		if ( ! class_exists( $class ) ) {
			throw new InvalidArgumentException(
				"'{$class}' argument is not a valid ClassName",
			);
		}
		$reflection  = new ReflectionClass( $class );
		$constructor = $reflection->getConstructor();
		if ( $constructor ) {
			$arguments = $this->findServiceArguments( $constructor, false );
			return new $class( ...$arguments );
		} else {
			return new $class();
		}
	}


	public function findServiceArguments( ReflectionFunctionAbstract $function, bool $include_route_parameters = false ): array {
		$args       = [];
		$parameters = $function->getParameters();
		foreach ( $parameters as $parameter ) {
			$arg       = null;
			$arg_name  = $parameter->getName();
			$arg_type  = $parameter->getType();
			$arg_class = $this->getReflectionClass( $parameter );

			$found = false;

			if ( $arg_class ) {
				$arg_class = $arg_class->getName();

				if ( $this instanceof $arg_class ) {
					$arg   = $this;
					$found = true;

				} else if ( $this->container->has( $arg_class ) ) {
					$arg   = $this->container->get( $arg_class );
					$found = true;
				}

			} else if (
				$include_route_parameters &&
				$arg_type instanceof ReflectionNamedType &&
				$arg_type->getName() === 'string' &&
				isset( $this->route['parameters'][ $arg_name ] )
			) {
				$arg   = $this->route['parameters'][ $arg_name ];
				$found = true;
			}

			if ( ! $found && $parameter->isDefaultValueAvailable() ) {
				$arg   = $parameter->getDefaultValue();
				$found = true;
			}

			if ( $found ) {
				$args[] = $arg;

			} else if ( $parameter->isOptional() ) {
				break;

			} else if ( $function instanceof ReflectionMethod ) {
				throw new InvalidArgumentException(
					sprintf(
						"'{$arg_name}' argument was not found for: %s->%s()",
						$function->getDeclaringClass()->getName(),
						$function->getName()
					)
				);

			} else {
				throw new InvalidArgumentException(
					sprintf(
						"'{$arg_name}' argument was not found for: %s()",
						$function->getName()
					)
				);
			}
		}
		return $args;
	}


	private function getReflectionClass( ReflectionParameter $parameter ): ?ReflectionClass {
		$type = $parameter->getType();
		if ( is_null( $type ) ) {
			return null;
		}
		if ( ! ( $type instanceof ReflectionNamedType ) ) {
			return null;
		}
		if ( $type->isBuiltin() ) {
			return null;
		}
		if ( ! class_exists( $type->getName() ) ) {
			return null;
		}
		return new ReflectionClass( $type->getName() );
	}


	public function setTemplateDir( string $path ): void {
		$this->template_dir = $path;
	}


	/**
	 * @param string|mixed[] $filenames
	 */
	public function getTemplatePath( $filenames, string $ext = 'php' ): string {
		if ( is_string( $filenames ) ) {
			$filenames = [ $filenames ];
		}
		foreach ( $filenames as $filename ) {
			$file = "{$this->template_dir}/{$filename}.{$ext}";

			if ( is_file( $file ) ) {
				return $file;
			}
		}
		throw new Exception( 'Missing template for ' . json_encode( $filenames ) );
	}


	public function getDefaultTemplateFile( bool $split_dirs = false ): string {
		$path = $this->route['name'];
		if ( $split_dirs ) {
			$path = str_replace( '-', '/', $path );
		}
		return $this->getTemplatePath( $path );
	}


	/**
	 * @param string|mixed[] $path
	 */
	public function renderTemplate( $path, array &$data = null ): string {
		if ( is_array( $data ) ) {
			extract( $data, EXTR_REFS ); // phpcs:ignore
		}
		ob_start();
		require $this->getTemplatePath( $path );
		return ob_get_clean() ?: '';
	}


	public static function redirect( string $url, int $code = 302 ): void {
		header( "Location: {$url}", true, $code );
		exit;
	}

}
