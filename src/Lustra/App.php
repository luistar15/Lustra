<?php

namespace Lustra;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

use Lustra\Router\Router;


class App {

	protected $container;
	protected $router;

	private $template_dir = './';

	public $route;


	public function __construct (
		Container $container,
		Router    $router
	) {

		$this->container = $container;
		$this->router = $router;
	}


	public function run () : void {
		$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$method = $_SERVER['REQUEST_METHOD'];

		$this->route = $this->router->findMatch($path, $method);

		$this->loadController();
	}


	public function loadController () : void {
		[$class, $method] = explode('@', $this->route['controller']);

		$controller = $this->instantiateService($class);

		$method_reflection = new ReflectionMethod($class, $method);
		$method_arguments = $this->findServiceArguments($method_reflection, true);

		$controller->{$method}(...$method_arguments);
	}


	public function instantiateService (string $class) {
		$reflection = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();

		if ($constructor) {
			$arguments = $this->findServiceArguments($constructor);
			return new $class(...$arguments);
		} else {
			return new $class();
		}
	}


	public function findServiceArguments (
		ReflectionMethod $method,
		bool $include_route_parameters = false

	) : array {

		$args = [];

		$parameters = $method->getParameters();

		foreach ($parameters as $parameter) {
			$name  = $parameter->getName();
			$class = $parameter->getClass();

			$arg = null;
			$found = false;

			if ($class) {
				$class = $class->getName();

				if ($this instanceof $class) {
					$arg = $this;
					$found = true;

				} else if ($this->container->has($class)) {
					$arg = $this->container->get($class);
					$found = true;
				}

			} else if ($include_route_parameters && $parameter->getType()->getName() == 'string') {
				if (isset($this->route['parameters'][$name])) {
					$arg = $this->route['parameters'][$name];
					$found = true;
				}
			}

			if (!$found && $parameter->isDefaultValueAvailable()) {
				$arg = $parameter->getDefaultValue();
				$found = true;
			}

			if ($found) {
				$args[] = $arg;

			} else if ($parameter->isOptional()) {
				break;

			} else {
				throw new InvalidArgumentException(sprintf(
					"'%s' argument was not found for: %s->%s()",
					$name,
					$method->getDeclaringClass()->getName(),
					$method->getName()
				));
			}
		}

		return $args;
	}


	public function setTemplateDir (string $path) : void {
		$this->template_dir = $path;
	}


	public function template (string $path) : string {
		return $this->template_dir . "/{$path}.tpl";
	}


	public function render (
		string $path,
		array &$data = null

	) : void {

		if ($data) {
			extract($data, EXTR_REFS);
		}

		ob_start();
		require $this->template($path);
		ob_end_flush();
	}


	public static function redirect (
		string $url,
		int    $code = 302

	) : void {

		header("Location: {$url}", true, $code);
		exit;
	}

}
