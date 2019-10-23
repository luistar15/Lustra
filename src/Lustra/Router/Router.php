<?php

namespace Lustra\Router;


class Router {

	private $path_prefix;

	private $routes = [];
	private $routes_methods = [];


	public function __construct (string $path_prefix = '/') {
		$this->path_prefix = $path_prefix;
	}


	public function addRoute (
		string $route_id,
		array  $route,
		array  $allowed_methods = []

	) : void {

		$this->routes[$route_id] = $route;

		foreach ($allowed_methods as $method) {
			if (isset($this->routes_methods[$method])) {
				$this->routes_methods[$method][] = $route_id;
			} else {
				$this->routes_methods[$method] = [$route_id];
			}
		}
	}


	public function findMatch (
		string $path,
		string $method = null

	) : array {

		$route = null;

		$path = preg_replace("#^{$this->path_prefix}#", '', $path); // strip prefix

		$routes_ids = $method ? $this->routes_methods[$method] : array_keys($this->routes);

		foreach ($routes_ids as $route_id) {
			$temp = $this->routes[$route_id];

			if ($temp['path'] === $path) {
				$route = $temp;
				$route['parameters'] = [];

			} else if (isset($temp['path_regexp']) && preg_match($temp['path_regexp'], $path, $matches)) {
				$route = $temp;
				$route['parameters'] = array_filter(array_slice(array_filter($matches), 1), function ($k) { return !is_int($k); }, ARRAY_FILTER_USE_KEY);
			}

			if ($route) {
				$route['id'] = $route_id;
				return $route;
			}
		}

		throw new RouteNotFoundException("Route not found for '/{$path}'");
	}


	public function pathFor (
		string $route_id,
		array  $parameters = null

	) : string {

		$route = $this->routes[$route_id];
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
		string $route_id,
		array  $parameters = null,
		array  $getvars = null,
		bool   $include_prefix = true

	) : string {

		$url = $this->pathFor($route_id, $parameters);

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

}
