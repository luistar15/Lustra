<?php

namespace Lustra;

use Throwable;
use ErrorException;


class ErrorHandler {

	private $debug = true;

	private $handler = null; // custom user handler


	public function __construct (
		bool   $debug = true,
		string $error_log = ''
	) {

		$this->debug = $debug;
		$this->setup($error_log);
	}


	private function setup (string $error_log) {
		ini_set('display_errors', $this->debug ? '1' : '0');
		ini_set('log_errors', $this->debug ? '0' : '1');
		ini_set('error_log', $error_log);

		error_reporting(E_ALL);
	}


	public function register () : void {
		set_error_handler([$this, 'handleError']);
		set_exception_handler([$this, 'handleException']);
		register_shutdown_function([$this, 'handleShutdown']);
	}


	public function setHandler (callable $handler) : void {
		$this->handler = $handler;
	}


	public function handleError (
		int    $level,
		string $message,
		string $file,
		int    $line

	) : bool {

		$this->handleException(
			new ErrorException($message, $level, $level, $file, $line)
		);

		return true;
	}


	public function handleException (Throwable $exception) : void {
		if ($this->debug) {
			self::dumpException($exception);

		} else if ($this->handler) {
			call_user_func($this->handler, $exception);

		} else {
			header('HTTP/1.1 500 Internal Server Error', true);
		}
	}


	public function handleShutdown () : void {
		$error = error_get_last();

		if ($error) {
			$this->handleError(
				E_ERROR,
				$error['message'],
				$error['file'],
				$error['line']
			);
		}
	}


	private static function dumpException (Throwable $exception) : void {
		if (ob_get_length()) {
			ob_clean();
		}

		header('HTTP/1.1 500 Internal Server Error', true);
		header('Content-Type: text/plain; charset=UTF-8', true);

		printf("* %s\n", $exception->getMessage());
		printf("  %s (%s)\n\n", $exception->getFile(), $exception->getLine());

		foreach ($exception->getTrace() as $entry) {
			$caller = '';
			$location = null;

			if (isset($entry['file'])) {
				$location = sprintf('%s (%s)', $entry['file'], $entry['line']);
			}

			if (isset($entry['class'])) {
				$caller .= $entry['class'] . $entry['type'];
			}

			if (isset($entry['function'])) {
				$caller .= $entry['function'];
			}

			$caller .= '()';

			if ($location) {
				printf("- %s\n  %s\n\n", $caller, $location);
			} else {
				printf("- %s\n\n", $caller);
			}
		}

		exit;
	}

}
