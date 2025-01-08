<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator;

use Nette\Utils\Type;
use InvalidArgumentException;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PromotedParameter;
use TheWebSolver\Codegarage\Generator\Enum\Argument;
use TheWebSolver\Codegarage\Generator\Error\ParamExtractionException as ExtractionError;

/**
 * @phpstan-type ArgsAsArray array{
 *  name:string,
 *  position:int,
 *  type:?string,
 *  defaultValue:mixed,
 *  isReference:bool,
 *  isVariadic:bool,
 *  isNullable:bool,
 *  isPromoted:bool
 * }
 */
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

	private PromotedParameter $param;
	/** @var ArgsAsArray */
	private array $asArray;

	/** @throws InvalidArgumentException When empty string given for `name|type`. */
	private function __construct(
		string $name,
		int $position,
		?string $type = null,
		mixed $defaultValue = null,
		bool $isReference = false,
		bool $isVariadic = false,
		bool $isNullable = false,
		bool $isPromoted = false
	) {
		$this->setDefault( $defaultValue );

		$this->isReference = $isReference;
		$this->isVariadic  = $isVariadic;
		$this->allowsNull  = $isNullable;
		$this->promoted    = $isPromoted;
		$this->position    = $position;
		$this->name        = (string) $this->validate( value: $name, type: 'name' );
		$this->type        = $this->validate( value: $type, type: 'type-hint', isNullable: true );
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
	): self {
		return new self( ...func_get_args() );
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/**
	 * Extracts parameter properties from given string data.
	 *
	 * @param null|(callable(array<string,string> $processed, string $raw): (array<string,string>|null)) $validator
	 *                                                                          External validator to validate the extracted argument & its value.
	 *                                                                          It must return the updated processed value. In case validation
	 *                                                                          fails, then it must return `null`.
	 * @return array<string,string>
	 * @throws ExtractionError When extraction fails. Error code will be value of one of its constant.
	 *
	 * Examples: String with param constructor property in key/value pair separated by "=" sign.
	 * 1. `"[name=firstName,type=TheWebSolver\Codegarage\Generator\Parameter,isReference=false]"`
	 * 2. `"[name=last,isVariadic=true,type=string]"`
	 * 3. `"[name=middle,type=string,isNullable=true]"`
	 * 4. `"[name=typeAsBool,type=bool,defaultValue=true,isPromoted=false]"`
	 * 5. `"[type=array,isPromoted=true,isNullable=false,isVariadic=true]"`
	 *
	 * The converted data of example number 4 & 5 will be as follow:
	 * Keep in mind, each values are still in `string` and
	 * needs to be type-casted appropriately.
	 *
	 * ```
	 * $example_4_withoutError = array(
	 *  'name'         => 'typeAsBool',
	 *  'type'         => 'bool',
	 *  'isPromoted'   => 'false',
	 *  'defaultValue' => 'true',
	 * );
	 *
	 * $example_5_withError = Throws Error with one of its constant as error code.
	 * ```
	 */
	// phpcs:enable
	public static function extractFrom( string $string, ?callable $validator = null ): array {
		$parts = str_replace( array( '[', ']' ), '', $string, $count );

		if ( 2 !== $count ) {
			self::throwExtractionError( ExtractionError::NOT_ENCLOSED_IN_BRACKETS );
		}

		$args = array();

		foreach ( explode( separator: ',', string: $parts ) as $part ) {
			$pair = explode( separator: '=', string: $part, limit: 2 );

			if ( empty( $pair[1] ) || str_contains( $pair[1], needle: '=' ) ) {
				self::throwExtractionError( ExtractionError::INVALID_PAIR );
			}

			[ $name, $value ] = $pair;
			$args[ $name ]    = ! Argument::tryFrom( $name )
				? self::throwExtractionError( ExtractionError::INVALID_CREATION_ARG )
				: $value;
		}

		if ( $validator && ( ! $args = $validator( $args, $string ) ) ) {
			self::throwExtractionError( ExtractionError::FROM_VALIDATOR );
		}

		return ! isset( $args[ Argument::Name->value ] )
		? self::throwExtractionError( ExtractionError::NO_NAME_ARG )
		: $args;
	}

	/**
	 * @throws InvalidArgumentException When invalid args passed.
	 * @phpstan-param ArgsAsArray $args
	 */
	public static function createFrom( array $args ): self {
		return ( $parameter = call_user_func_array( self::create( ... ), $args ) ) instanceof self
			? $parameter
			: throw new InvalidArgumentException(
				sprintf(
					'The given args does not map with creation args. To view all supported args, see "%s".',
					self::class . '::CREATION_ARGS'
				)
			);
	}

	/** @param array{position?:int,defaultValue?:mixed} $newValues */
	public function recreateWith( array $newValues ): self {
		return self::createFrom( array( ...$this->toArray(), ...$newValues ) );
	}

	/**
	 * @return array{
	 *  name:string,
	 *  position:int,
	 *  type:?string,
	 *  defaultValue:mixed,
	 *  isReference:bool,
	 *  isVariadic:bool,
	 *  isNullable:bool,
	 *  isPromoted:bool
	 * }
	 */
	public function toArray(): array {
		return $this->asArray ??= array(
			Argument::Reference->value => $this->isPassedByReference(),
			Argument::Nullable->value  => $this->allowsNull(),
			Argument::Variadic->value  => $this->isVariadic(),
			Argument::Promoted->value  => $this->isPromoted(),
			Argument::Position->value  => $this->getPosition(),
			Argument::Default->value   => $this->getRawDefaultValue(),
			Argument::Type->value      => $this->getRawType(),
			Argument::Name->value      => $this->getName(),
		);
	}

	public function getNetteParameter(): PromotedParameter {
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

	private function make(): PromotedParameter {
		$param = new PromotedParameter( $this->name );

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
	 * @return ($isNullable is true ? string|null : string)
	 */
	private function validate( ?string $value, string $type, bool $isNullable = false ): ?string {
		if ( null === $value && $isNullable ) {
			return $value;
		}

		if ( ! empty( $value ) ) {
			return $value;
		}

		throw new InvalidArgumentException(
			sprintf( 'The parameter "%1$s" cannot be "%2$s".', $type, $value )
		);
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
		if ( count( $parts = explode( '::', $this->defaultValue, limit: 2 ) ) > 1 ) {
			$parts[0] = Helpers::tagName( $parts[0] );
		}

		return implode( '::', $parts );
	}

	/** @see \Nette\PhpGenerator\Factory::fromParameterReflection() */
	private function parseDefaultValue( PromotedParameter $param ): void {
		$default = $this->defaultValue;

		if ( $this->isDefaultValueConstant ) {
			$default = new Literal( $this->prepareDefaultValueConstantName() );
		} elseif ( is_string( $this->defaultValue ) && strpos( $this->defaultValue, '$', 0 ) === 0 ) {
			$variable = str_replace( '$', '', $this->defaultValue );

			if ( ! $setVariable = ( $$variable ?? null ) ) {
				$this->invalidValueKey = 'default';
			}

			$default = is_object( $setVariable ) ? new Literal( $default ) : $setVariable;
		}

		$param->setDefaultValue( $default )
			->setNullable( $param->isNullable() && null !== $param->getDefaultValue() );
	}

	private static function throwExtractionError( int $code ): never {
		throw new ExtractionError( 'Extraction Error', $code );
	}
}
