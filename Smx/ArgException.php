<?php
/**
 * Copyright (C) 2019 Stefano Moioli <smxdev4@gmail.com>
 */
namespace Smx;
class ArgException extends Exception {
	public function __construct(){
		$argv = func_get_args();
		$message = vsprintf(array_shift($argv), $argv);
		parent::__construct($message, 0, null);
	}

	public function __toString() {
		return $this->message;
	}
}