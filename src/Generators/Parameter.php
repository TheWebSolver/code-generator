<?php
/**
 * @package TheWebSolver\CodeGenerator\Parameter
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generators;

use Nette\Utils\Type;
use InvalidArgumentException;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PromotedParameter;
use Nette\PhpGenerator\Parameter as NetteParameter;

final class Parameter {
	private bool $isDefaultValueAvailable = false;
	private bool $isDefaultValueConstant  = false;
	private ?string $invalidValueKey      = null;
	private mixed $defaultValue           = null;
	private bool $isReference             = false;
	private bool $isVariadic              = false;
	private bool $allowsNull              = false;
	private bool $promoted                = false;
	private ?string $type                 = null;
	private int $position                 = 0;
	private string $name                  = '';

	private NetteParameter $param;

	/** @throws InvalidArgumentException When empty string given for `name|type`. */
	private function __construct(
		string $name,
		int $position,
		?string $type = null,
		mixed $defaultValue = null,
		bool $isReference = false,
		bool $isVariadic = false,
		bool $isNullable = false,
		bool $isPromoted = false,
	) {
		$this->setDefault( $defaultValue );

		$this->isReference = $isReference;
		$this->isVariadic  = $isVariadic;
		$this->allowsNull  = $isNullable;
		$this->promoted    = $isPromoted;
		$this->position    = $position;
		$this->name        = (string) $this->validate( $name );
		$this->type        = $this->validate( $type, true );
		$this->param       = $this->make();
	}

	/** @throws InvalidArgumentException When empty string given for `name|type`. */
	public static function create(
		string $name,
		int $position,
		?string $type = null,
		mixed $defaultValue = null,
		bool $isReference = false,
		bool $isVariadic = false,
		bool $isNullable = false,
		bool $isPromoted = false
	) {
		return new self( ...func_get_args() );
	}

	/** @phpstan-param array{position?:int,defaultValue?:mixed} */
	public function recreateWith( array $newValues ) {
		return new self(
			$this->getName(),
			$newValues['position'] ?? $this->getPosition(),
			$this->getRawType(),
			$newValues['defaultValue'] ?? $this->getRawDefaultValue(),
			$this->isPassedByReference(),
			$this->isVariadic(),
			$this->allowsNull(),
			$this->isPromoted()
		);
	}

	public function getNetteParameter(): NetteParameter {
		return $this->param;
	}

	public function allowsNull(): bool {
		return $this->param->isNullable();
	}

	public function canBePassedByValue(): bool {
		return ! $this->isReference;
	}

	/** @return mixed */
	public function getDefaultValue() {
		return $this->param->getDefaultValue();
	}

	public function getDefaultValueConstantName(): ?string {
		if ( ! $this->isDefaultValueConstant() ) {
			return null;
		}

		return $this->prepareDefaultValueConstantName();
	}

	public function getName(): string {
		return $this->param->getName();
	}

	public function getPosition(): int {
		return $this->position;
	}

	/** @return null|string|Type */
	public function getType() {
		return $this->param->getType( true );
	}

	public function hasType(): bool {
		return null !== $this->getType();
	}

	public function isDefaultValueAvailable(): bool {
		return $this->param->hasDefaultValue();
	}

	public function isDefaultValueConstant(): bool {
		return $this->isDefaultValueConstant;
	}

	public function isOptional(): bool {
		return ! $this->isDefaultValueAvailable();
	}

	public function isPassedByReference(): bool {
		return $this->param->isReference();
	}

	public function isVariadic(): bool {
		return $this->isVariadic;
	}

	public function getInvalidValueKey(): ?string {
		return $this->invalidValueKey;
	}

	public function isPromoted(): bool {
		return $this->promoted;
	}

	private function getRawDefaultValue(): mixed {
		return $this->defaultValue;
	}

	public function getRawType(): ?string {
		return $this->type;
	}

	private function make(): NetteParameter {
		$param = PHP_VERSION_ID >= 80000 && $this->promoted
			? new PromotedParameter( $this->name )
			: new NetteParameter( $this->name );

		$param->setReference( $this->isReference );

		if ( null !== $this->type ) {
			$param->setType( $this->type )
				// Maybe limit nullable only to namedType (no union or intersection).
				->setNullable( $this->allowsNull );
		}

		if ( $this->isDefaultValueAvailable ) {
			$this->parseDefaultValue( $param );
		}

		return $param;
	}

	/**
	 * @throws InvalidArgumentException When given value is empty.
	 * @phpstan-return ($isNullable is true ? string|null : string )
	 */
	private function validate( ?string $value, bool $isNullable = false ): ?string {
		if ( null === $value && $isNullable ) {
			return $value;
		}

		if ( ! empty( $value ) ) {
			return $value;
		}

		throw new InvalidArgumentException( sprintf( 'The given value: "%s" cannot be empty.', $value ) );
	}

	private function setDefault( mixed $value ): void {
		if ( null === $value ) {
			return;
		}

		if ( is_string( $value ) ) {
			if ( '' !== $value ) {
				$this->isDefaultValueAvailable = true;
				$this->defaultValue            = $value;
			}

			if ( defined( $value ) ) {
				$this->isDefaultValueConstant = true;
			}
		} else {
			$this->isDefaultValueAvailable = true;
			$this->defaultValue            = $value;
		}
	}

	private function prepareDefaultValueConstantName(): string {
		static $constant = null;

		if ( null === $constant ) {
			if ( count( $parts = explode( '::', $this->defaultValue ) ) > 1 ) {
				$parts[0] = Helpers::tagName( $parts[0] );
			}

			$constant = implode( '::', $parts );
		}

		return $constant;
	}

	/** @see \Nette\PhpGenerator\Factory::fromParameterReflection() */
	private function parseDefaultValue( NetteParameter $param ): void {
		$default = $this->defaultValue;

		if ( $this->isDefaultValueConstant ) {
			$default = new Literal( $this->prepareDefaultValueConstantName() );
		} elseif ( is_string( $this->defaultValue ) && strpos( $this->defaultValue, '$', 0 ) === 0 ) {
			$variable = str_replace( '$', '', $this->defaultValue );

			if ( ! $setVariable = $$variable ?? null ) {
				$this->invalidValueKey = 'default';
			}

			$default = is_object( $setVariable ) ? new Literal( $default ) : $setVariable;
		}

		$param->setDefaultValue( $default )
			->setNullable( $param->isNullable() && null !== $param->getDefaultValue() );
	}
}
