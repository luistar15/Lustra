<?php

namespace Lustra;


use Throwable;
use ErrorException;
use phpDocumentor\Reflection\Types\Callable_;

class ErrorHandler {

	private bool $debug = true;

	/** @var callable|null */
	private $handler = null;


	public function __construct (
		bool   $debug = true,
		string $error_log = ''
	) {

		$this->debug = $debug;
		$this->setup($error_log);
	}


	private function setup (string $error_log) : void {
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
			if (ob_get_length()) {
				ob_clean();
			}

			if (PHP_SAPI === 'cli') {
				self::dumpExceptionCli($exception);
			} else {
				self::dumpExceptionHtml($exception);
			}

			exit;

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


	private static function dumpExceptionHtml (Throwable $exception) : void {
		$html = sprintf(
			"\n\n<p><b>%s:</b> %s <br>\n<code>%s (%s)</code></p><hr>\n\n",
			htmlspecialchars(get_class($exception)),
			htmlspecialchars(trim($exception->getMessage())),
			htmlspecialchars($exception->getFile()),
			$exception->getLine()
		);

		foreach ($exception->getTrace() as $entry) {
			$caller   = '';
			$location = '';

			if (isset($entry['class'])) {
				$caller .= $entry['class'] . $entry['type'];
			}

			if (isset($entry['function'])) {
				$caller .= $entry['function'] . '()';
			}

			if (isset($entry['file'])) {
				$location = sprintf(" <br>\n<code>%s (%s)</code>", htmlspecialchars($entry['file']), $entry['line']);
			}

			$html .= sprintf("<div><b>%s</b>{$location}</div><br>\n\n", htmlspecialchars($caller));
		}

		// -----------------------------

		header('HTTP/1.1 500 Internal Server Error', true);
		header('Content-Type: text/html; charset=UTF-8', true);

		echo <<<HTML
		<!DOCTYPE html>
		<html>
		<head>
			<title>An error has occurred</title>
			<style>html { font-family: "Segoe UI", Verdana, sans-serif; }</style>
		</head>
		<body>{$html}</body>
		</html>
		HTML;
	}


	private static function dumpExceptionCli (Throwable $exception) : void {
		$color = function ($str, $code) { return "\e[{$code}m{$str}\e[0m"; };

		printf(
			"\n%s\n%s\n%s (%s)\n\n",
			$color(get_class($exception), 41),
			$color(trim($exception->getMessage()), 36),
			$exception->getFile(),
			$exception->getLine()
		);

		foreach ($exception->getTrace() as $entry) {
			$caller   = '';
			$location = '';

			if (isset($entry['class'])) {
				$caller .= $entry['class'] . $entry['type'];
			}

			if (isset($entry['function'])) {
				$caller .= $entry['function'] . '()';
			}

			if (isset($entry['file'])) {
				$location = sprintf("\n%s (%s)", $entry['file'], $entry['line']);
			}

			printf("%s{$location}\n\n", $color($caller, 31));
		}
	}

}
