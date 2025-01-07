<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Error;

use Exception;

class ParamExtractionException extends Exception {
	public const NOT_ENCLOSED_IN_BRACKETS = 1;
	public const INVALID_PAIR             = 2;
	public const INVALID_CREATION_ARG     = 3;
	public const FROM_VALIDATOR           = 4;
	public const NO_NAME_ARG              = 5;
}
