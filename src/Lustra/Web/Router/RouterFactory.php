<?php

namespace Lustra\Web\Router;


final class RouterFactory {

	public static function build (
		string $path_prefix,
		string $source_file,
		string $cache_file = null,
		bool   $use_cache = true,
		string $controller_namespace = null,
		string $controller_suffix = null

	) : Router {

		$router = new Router($path_prefix);

		$cache_file_exists = $cache_file && is_file($cache_file);

		if ($use_cache && $cache_file_exists) {
			$router->import(require $cache_file);
			return $router;
		}

		// ------------------------------------------

		$source = require $source_file;

		$routes_requirements = $source['requirements'] ?? [];
		$routes_constraints  = $source['constraints']  ?? [];

		$routes = self::flattenRoutesTree(self::fixRoutesTree($source['routes']));

		foreach ($routes as $route_name => $route) {
			$controller_class  = $route['controller_class'];
			$controller_method = $route['controller_method'];

			if ($controller_namespace) {
				$controller_class = "{$controller_namespace}\\{$controller_class}";
			}

			if ($controller_suffix) {
				$controller_class .= $controller_suffix;
			}

			$route_controller = "{$controller_class}@{$controller_method}";

			$router->addRoute($route['path'], $route_controller, [
				'name'         => $route_name,
				'methods'      => $route['methods'],
				'requirements' => $routes_requirements,
				'constraints'  => $routes_constraints,
			]);
		}

		// ------------------------------------------

		if ($cache_file && !$cache_file_exists) {
			$data = var_export($router->export(), true);

			// unnecesary dummy format
			$data = str_replace('  ', "\t", $data);                              // spaces to tabs
			$data = preg_replace("/=>\s*\n\t*(array\s*\()/m", '=> ${1}', $data); // same line '=> array'
			$data = preg_replace("/(\t)\d+ => /", '${1}', $data);                // remove numeric index
			$data = str_replace('array (', '[', $data);                          // array() to []
			$data = preg_replace('/(\n\t*)\)(,|$)/', '${1}]${2}', $data);

			file_put_contents($cache_file, "<?php return {$data};\n");
		}

		return $router;
	}


	public static function fixRoutesTree (array $tree) : array {

		$CamelCase = function ($str) {
			return strtr(ucwords(preg_replace('/[^a-z0-9]+/i', ' ', strtolower($str))), [' ' => '']);
		};

		$lowerCamelCase = function ($str) use (&$CamelCase) {
			$str = $CamelCase($str);
			return strtolower(substr($str, 0, 1)) . substr($str, 1);
		};

		$walker = function (array $nodes, bool $has_parent) use (&$walker, &$CamelCase, &$lowerCamelCase) {

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
					$controller_class = [$CamelCase($node_id)];

				} else {
					$controller_class = [];
				}

				if (isset($node['controller_method'])) {
					$controller_method = $node['controller_method'];

				} else if (!$is_group && $has_parent && !isset($node['controller_class'])) {
					$controller_method = $lowerCamelCase($node_id);

				} else {
					$controller_method = '__invoke';
				}

				// methods -----------------------------------------------------

				$methods = [];

				if (isset($node['methods'])) {
					$methods = $node['methods'];
				} else if (!$is_group) {
					$methods = ['GET'];
				}

				// ensamble ----------------------------------------------------

				$node = [
					'path'             => $path,
					'controller_class' => $controller_class,
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

		foreach ($flattener($tree) as $route_name => $route) {
			$route['path']             = implode('/', $route['path']);
			$route['controller_class'] = implode('\\', $route['controller_class']);

			$flatten[$route_name] = $route;
		}

		return $flatten;
	}

}
