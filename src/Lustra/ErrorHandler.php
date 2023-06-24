<?php

declare(strict_types=1);


namespace Lustra;


use Throwable;
use ErrorException;


class ErrorHandler {

	private bool $debug = true;

	/** @var ?callable $handler */
	private $handler = null;


	public function __construct() {
		$this->register();
	}


	public function setup(
		bool $debug,
		string $error_log = ''
	) : void {

		$this->debug = $debug;

		ini_set( 'display_errors', $debug );
		ini_set( 'log_errors', ! $debug );
		ini_set( 'error_log', $error_log );

		error_reporting( E_ALL );
	}


	private function register() : void {
		set_error_handler( [ $this, 'handleError' ] );
		set_exception_handler( [ $this, 'handleException' ] );
		register_shutdown_function( [ $this, 'handleShutdown' ] );
	}


	public function setHandler(
		callable $handler
	) : void {

		$this->handler = $handler;
	}


	public function handleError(
		int $level,
		string $message,
		string $file,
		int $line
	) : bool {

		$this->handleException(
			new ErrorException( $message, $level, $level, $file, $line )
		);

		return true;
	}


	public function handleException(
		Throwable $exception
	) : void {

		if ( ob_get_length() ) {
			ob_end_clean();
		}

		if ( is_callable( $this->handler ) ) {
			call_user_func( $this->handler, $exception );

		} else if ( $this->debug ) {
			$this->dumpException( $exception );

		} else {
			header( 'HTTP/1.1 500 Internal Server Error', true );
		}
	}


	public function handleShutdown() : void {
		$error = error_get_last();

		if ( $error ) {
			$this->handleError(
				E_ERROR,
				$error['message'],
				$error['file'],
				$error['line']
			);
		}
	}


	public function dumpException(
		Throwable $exception
	) : void {

		if ( PHP_SAPI === 'cli' ) {
			self::dumpExceptionCli( $exception );
		} else {
			self::dumpExceptionHtml( $exception );
		}

		exit;
	}


	public static function formatExceptionHtml(
		Throwable $exception
	) : string {

		$message      = trim( $exception->getMessage() );
		$message_json = json_decode( $message );

		if ( json_last_error() === 0 ) {
			$message_json = json_encode(
				$message_json,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
		}

		if ( is_string( $message_json ) ) {
			$html = sprintf(
				"\n\n<p><b>%s:</b><br><pre>%s</pre><br>\n<code>%s (%s)</code></p><hr>\n\n",
				htmlspecialchars( get_class( $exception ) ),
				htmlspecialchars( $message_json ),
				htmlspecialchars( $exception->getFile() ),
				$exception->getLine()
			);

		} else {
			$html = sprintf(
				"\n\n<p><b>%s:</b> %s <br>\n<code>%s (%s)</code></p><hr>\n\n",
				htmlspecialchars( get_class( $exception ) ),
				htmlspecialchars( $message ),
				htmlspecialchars( $exception->getFile() ),
				$exception->getLine()
			);
		}

		foreach ( $exception->getTrace() as $entry ) {
			$caller   = '';
			$location = '';

			if ( isset( $entry['class'] ) ) {
				$caller .= $entry['class'] . ( $entry['type'] ?? '' );
			}

			if ( $entry['function'] ) {
				$caller .= $entry['function'] . '()';
			}

			if ( isset( $entry['file'], $entry['line'] ) ) {
				$location = sprintf( " <br>\n<code>%s (%s)</code>", htmlspecialchars( $entry['file'] ), $entry['line'] );
			}

			$html .= sprintf( "<div><b>%s</b>{$location}</div><br>\n\n", htmlspecialchars( $caller ) );
		}

		return $html;
	}


	private static function dumpExceptionHtml(
		Throwable $exception
	) : void {

		header( 'HTTP/1.1 500 Internal Server Error', true );
		header( 'Content-Type: text/html; charset=UTF-8', true );

		$html = self::formatExceptionHtml( $exception );

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


	private static function dumpExceptionCli(
		Throwable $exception
	) : void {

		$color = function ( $str, $code ) {
			return "\e[{$code}m{$str}\e[0m";
		};

		printf(
			"\n%s\n%s\n%s (%s)\n\n",
			$color( get_class( $exception ), 41 ),
			$color( trim( $exception->getMessage() ), 36 ),
			$exception->getFile(),
			$exception->getLine()
		);

		foreach ( $exception->getTrace() as $entry ) {
			$caller   = '';
			$location = '';

			if ( isset( $entry['class'] ) ) {
				$caller .= $entry['class'] . ( $entry['type'] ?? '' );
			}

			if ( $entry['function'] ) {
				$caller .= $entry['function'] . '()';
			}

			if ( isset( $entry['file'], $entry['line'] ) ) {
				$location = sprintf( "\n%s (%s)", $entry['file'], $entry['line'] );
			}

			printf( "%s{$location}\n\n", $color( $caller, 31 ) );
		}
	}

}
