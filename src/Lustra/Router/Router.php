<?php

namespace Lustra\Router;


class Router {

	private $path_prefix;

	private $routes = [];
	private $routes_methods = [];

	const REQUIREMENTS = [
		'digit' => '\d+',
		'alpha' => '[a-z]+',
		'alnum' => '\w+',
		'text'  => '[^/]+',
		'date'  => '\d{4}-\d{2}-\d{2}',
		'any'   => '.+',
	];


	public function __construct (string $path_prefix = '/') {
		$this->path_prefix = $path_prefix;
	}


	public function addRoute (
		string $path,
		       $controller,
		array  $options = []

	) : void {

		$name         = $options['name']         ?? 'route-' . count($this->routes);
		$requirements = $options['requirements'] ?? [];
		$constraints  = $options['constraints']  ?? [];
		$methods      = $options['methods']      ?? ['GET'];

		$route = self::parsePath($path, $requirements, $constraints);

		if (is_string($controller) && strpos($controller, '@') === false) {
			$controller .= '@__invoke';
		}

		$route['controller'] = $controller;

		// --------------

		$this->routes[$name] = $route;

		foreach ($methods as $method) {
			if (isset($this->routes_methods[$method])) {
				$this->routes_methods[$method][] = $name;
			} else {
				$this->routes_methods[$method] = [$name];
			}
		}
	}


	public function findMatch (
		string $path,
		string $method = null

	) : array {

		$route = null;

		$path = preg_replace("#^{$this->path_prefix}#", '', $path); // strip prefix

		$routes_names = $method ? $this->routes_methods[$method] : array_keys($this->routes);

		foreach ($routes_names as $route_name) {
			$temp = $this->routes[$route_name];

			if ($temp['path'] === $path) {
				$route = $temp;
				$route['parameters'] = [];

			} else if (isset($temp['path_regexp']) && preg_match($temp['path_regexp'], $path, $matches)) {
				$route = $temp;
				$route['parameters'] = array_filter(
					array_slice(array_filter($matches), 1),
					function ($k) { return !is_int($k); },
					ARRAY_FILTER_USE_KEY
				);
			}

			if ($route) {
				$route['name'] = $route_name;
				return $route;
			}
		}

		throw new RouteNotFoundException("Route not found for '/{$path}'");
	}


	public function pathFor (
		string $route_name,
		array  $parameters = null

	) : string {

		$route = $this->routes[$route_name];
		$path  = $route['path'];

		if (isset($route['path_regexp'])) {
			// replace parameters values
			if ($parameters && count($parameters) > 0) {
				$placeholders = array_map(function ($k) { return "{{$k}}"; }, array_keys($parameters));
				$path = str_replace($placeholders, array_values($parameters), $route['path']);
			}

			// clear optional parameters
			$path = preg_replace_callback("/\[([^\]]+)\]/", function ($matches) {
				return preg_match('/{.*}/', $matches[1]) ? '' : $matches[1];
			}, $path);
		}

		return $path;
	}


	public function urlFor (
		string $route_name,
		array  $parameters = null,
		array  $getvars = null,
		bool   $include_prefix = true

	) : string {

		$url = $this->pathFor($route_name, $parameters);

		if ($include_prefix) {
			$url = $this->path_prefix . $url;
		}

		if ($getvars && count($getvars) > 0) {
			$url .= '?' . http_build_query($getvars);
		}

		return $url;
	}


	public function import (array $data) : void {
		[$this->routes, $this->routes_methods] = $data;
	}


	public function export () : array {
		return [$this->routes, $this->routes_methods];
	}


	// -------------------------------------------------------------------------


	public static function parsePath (
		string $path,
		array  $requirements,
		array  $constraints

	) : array {

		$requirements = array_merge(self::REQUIREMENTS, $requirements);

		$parameters = [];
		$paramnames = [];

		// extract parameters
		$path_regexp = preg_replace_callback('/{(?<name>\w+)(:(?<requirement>[^}]+))?}/', function (
			$matches
		) use (
			&$parameters,
			&$paramnames,
			&$requirements,
			&$constraints
		) {

			$placeholder = sprintf("`%s`", count($parameters));
			$param_name  = $matches['name'];
			$param_req   = $matches['requirement'] ?? null;

			if ($param_req) {
				$regex = $requirements[$param_req] ?? $param_req;
			} else if (isset($constraints[$param_name])) {
				$regex = $requirements[$constraints[$param_name]] ?? $constraints[$param_name];
			} else {
				$regex = $requirements['text'];
			}

			$parameters[$placeholder] = sprintf('(?<%s>%s)', $param_name, $regex);
			$paramnames[$placeholder] = sprintf('{%s}', $param_name);

			return $placeholder;
		}, $path);


		if (count($parameters) === 0) {
			return compact('path');
		}


		$regexp_delimiter = '#';

		// build path template
		$path = strtr($path_regexp, $paramnames);


		// change optional blocks format
		$path_regexp = preg_replace_callback('/\[([^\]]+)\]/', function ($matches) {
			return sprintf('~%s~', $matches[1]);
		}, $path_regexp);


		// escape regex chars
		$path_regexp = preg_quote($path_regexp, $regexp_delimiter);


		// restore parsed parameters
		$path_regexp = strtr($path_regexp, $parameters);


		// fix optional blocks
		$path_regexp = preg_replace_callback("/~([^~]+)~/", function ($matches) {
			return sprintf('(?|%s)?', $matches[1]);
		}, $path_regexp);


		// build regex
		$path_regexp = sprintf('%s^%s$%s', $regexp_delimiter, $path_regexp, $regexp_delimiter);


		// --
		return compact('path', 'path_regexp');
	}

}
