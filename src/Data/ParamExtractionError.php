<?php
/**
 * @package TheWebSolver\CodeGenerator\Parameter
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Data;

final class ParamExtractionError {
	private string $value;
	private string $type;

	public function __construct( string $type, string $value ) {
		$this->value = $value;
		$this->type  = $type;
	}

	public static function of( string $type, string $value ): self {
		return new self( $type, $value );
	}

	public function type(): string {
		return $this->type;
	}

	public function value(): string {
		return $this->value;
	}
}
