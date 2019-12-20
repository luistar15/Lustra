<?php

namespace Lustra\Validator;


use Exception;


class ValidatorException extends Exception {

	const MISSING_TYPE  = 0;
	const MISSING_VALUE = 1;
	const INVALID_VALUE = 2;

	private $data = [];


	public static function build (
		int    $code = 0,
		string $message = '',
		array  $data = []

	) : self {

		$e = new self($message, $code);
		$e->setData($data);

		return $e;
	}


	public function setData (array $data) : void {
		$this->data = $data;
	}


	public function getData () : array {
		return $this->data;
	}


	public function isMissingType () : bool {
		return $this->getCode() === self::MISSING_TYPE;
	}


	public function isMissingValue () : bool {
		return $this->getCode() === self::MISSING_VALUE;
	}


	public function isInvalidValue () : bool {
		return $this->getCode() === self::INVALID_VALUE;
	}

}
