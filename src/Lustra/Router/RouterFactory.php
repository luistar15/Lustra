<?php

namespace Lustra\Router;


final class RouterFactory {

	const CONSTRAINTS = [
		'digit' => '\d+',
		'alpha' => '[a-z]+',
		'alnum' => '\w+',
		'text'  => '[^/]+',
		'date'  => '\d{4}-\d{2}-\d{2}',
		'any'   => '.+',
	];


	public static function build (
		string $path_prefix,
		string $source_file,
		string $cache_file = null,
		string $controller_namespace = null,
		string $controller_suffix = null

	) : Router {

		$router = new Router($path_prefix);

		if ($cache_file && is_file($cache_file)) {
			$router->import(require $cache_file);
			return $router;
		}

		// --------------

		$source = require $source_file;

		$routes = self::parseRoutes(
			$source['routes'],
			$source['constraints'] ?? [],
			$source['rules'] ?? []
		);

		foreach ($routes as $route_id => $route) {
			if ($controller_namespace) {
				$route['controller_class'] = $controller_namespace . '\\' . $route['controller_class'];
			}

			if ($controller_suffix) {
				$route['controller_class'] .= $controller_suffix;
			}

			$route_methods = $route['methods'] ?? null;
			unset($route['methods']);

			$router->addRoute($route_id, $route, $route_methods);
		}

		// --------------

		if ($cache_file) {
			file_put_contents(
				$cache_file,
				sprintf("<?php return %s;\n", var_export($router->export(), true))
			);
		}

		return $router;
	}


	public static function parseRoutes (
		array $routes,
		array $constraints,
		array $rules

	) : array {

		$routes = self::flattenRoutesTree(self::fixRoutesTree($routes));

		foreach ($routes as $route_id => $route) {
			$routes[$route_id] = array_merge($route, self::parsePath($route['path'], $constraints, $rules));
		}

		return $routes;
	}


	public static function parsePath (
		string $path,
		array &$constraints,
		array &$rules

	) : array {

		$constraints = array_merge(self::CONSTRAINTS, $constraints);

		$parameters_placeholders = [];
		$template_placeholders   = [];

		// extract parameters
		$path_regexp = preg_replace_callback('/{(?<name>\w+)(:(?<constraint>[^}]+))?}/', function (
			$matches
		) use (
			&$parameters_placeholders,
			&$template_placeholders,
			&$constraints,
			&$rules
		) {

			$k = sprintf("`%s`", count($parameters_placeholders));
			$n = $matches['name'];
			$c = $matches['constraint'] ?? null;

			if ($c) {
				$regex = $constraints[$c] ?? $c;
			} else if (isset($rules[$n])) {
				$regex = $constraints[$rules[$n]] ?? $rules[$n];
			} else {
				$regex = $constraints['text'];
			}

			$parameters_placeholders[$k] = sprintf('(?<%s>%s)', $n, $regex);
			$template_placeholders[$k]   = sprintf('{%s}', $n);

			return $k;
		}, $path);


		if (count($parameters_placeholders) > 0) {
			$regexp_delimiter = '#';

			// build path template
			$path_tpl = strtr($path_regexp, $template_placeholders);


			// change optional blocks format
			$path_regexp = preg_replace_callback('/\[([^\]]+)\]/', function ($matches) {
				return sprintf('~%s~', $matches[1]);
			}, $path_regexp);


			// escape regex chars
			$path_regexp = preg_quote($path_regexp, $regexp_delimiter);


			// restore parsed parameters
			$path_regexp = strtr($path_regexp, $parameters_placeholders);


			// fix optional blocks
			$path_regexp = preg_replace_callback("/~([^~]+)~/", function ($matches) {
				return sprintf('(?|%s)?', $matches[1]);
			}, $path_regexp);


			// build regex
			$path_regexp = sprintf('%s^%s$%s', $regexp_delimiter, $path_regexp, $regexp_delimiter);
		}

		return isset($path_tpl) ? compact('path_regexp', 'path_tpl') : [];
	}


	public static function fixRoutesTree (array $tree) : array {
		$walker = function (array $nodes, bool $has_parent) use (&$walker) {
			$fixed = [];

			foreach ($nodes as $node_id => $node) {
				// path --------------------------------------------------------

				if (is_string($node)) {
					$path = $node;
					$node = [];
				} else {
					$path = $node['path'] ?? $node_id;
				}

				$path = $path === '' ? [] : [$path];

				// indetify group ----------------------------------------------

				$childs   = $node[0] ?? $node['items'] ?? [];
				$is_group = count($childs) > 0;

				unset($node[0], $node['items']);

				// controller --------------------------------------------------

				if (isset($node['controller_class'])) {
					$controller_class = [$node['controller_class']];

				} else if ($is_group || !$has_parent) {
					$controller_class = [str_replace('_', '', ucwords($node_id, '_'))];

				} else {
					$controller_class = [];
				}

				if (isset($node['controller_method'])) {
					$controller_method = $node['controller_method'];

				} else if (!$is_group && $has_parent && !isset($node['controller_class'])) {
					$controller_method = $node_id;

				} else {
					$controller_method = '__invoke';
				}

				// methods --------------------------------------------------

				if (isset($node['methods'])) {
					$methods = $node['methods'];
				} else if (!$is_group) {
					$methods = ['GET'];
				}

				// ensamble ----------------------------------------------------

				$node = [
					'path'              => $path,
					'controller_class'  => $controller_class,
				];

				if ($is_group) {
					$node['childs'] = $walker($childs, true);
				} else {
					$node['controller_method'] = $controller_method;
					$node['methods'] = $methods;
				}

				$fixed[$node_id] = $node;
			}

			return $fixed;
		};

		return $walker($tree, false);
	}


	public static function flattenRoutesTree (array $tree) : array {
		$flatten = [];

		$flattener = function (array $nodes) use (&$flattener) : iterable {
			foreach ($nodes as $node_id => $node) {
				if (isset($node['childs'])) {
					foreach ($flattener($node['childs']) as $child_id => $child) {
						$child['path']             = array_merge($node['path'], $child['path']);
						$child['controller_class'] = array_merge($node['controller_class'], $child['controller_class']);

						yield "{$node_id}-{$child_id}" => $child;
					}

				} else {
					yield $node_id => $node;
				}
			}
		};

		foreach ($flattener($tree) as $route_id => $route) {
			$route['path']             = implode('/', $route['path']);
			$route['controller_class'] = implode('\\', $route['controller_class']);

			$flatten[$route_id] = $route;
		}

		return $flatten;
	}

}
