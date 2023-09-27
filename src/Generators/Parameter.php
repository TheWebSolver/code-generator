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
/**
 * @phpstan-type ArgsAsArray array{name:string,position:int,type:?string,defaultValue:mixed,isReference:bool,isVariadic:bool,isNullable:bool,isPromoted:bool}
 */
final class Parameter {
	/**
	 * List of constructor arguments with their respective type.
	 *
	 * @var array<string,string>
	 */
	public const CREATION_ARGS = array(
		'defaultValue' => 'mixed',
		'isReference'  => 'bool',
		'isNullable'   => 'bool',
		'isVariadic'   => 'bool',
		'isPromoted'   => 'bool',
		'position'     => 'int',
		'type'         => '?string',
		'name'         => 'string',
	);

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
		$this->name        = (string) $this->validate( value: $name, type: 'name' );
		$this->type        = $this->validate( value: $type, type: 'typehint', isNullable: true );
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

	/**
	 * Extracts parameter properties from given string data.
	 *
	 * Examples: String with param constructor property in key/value pair separated by "=" sign.
	 * 1. `"[name=firstName,type=TheWebSolver\Codegarage\Generators\Parameter,isReference=false]"`
	 * 2. `"[name=last,isVariadic=true,type=string]"`
	 * 3. `"[name=middle,type=string,isNullable=true]"`
	 * 4. `"[name=typeasBool,type=bool,defaultValue=true,isPromoted=false]"`
	 * 5. `"[type=array,isPromoted=true,isNullable=false,isVariadic=true]"`
	 *
	 * The converted data will be, of example number 4 & 5, will be as follow:
	 * Keep in mind, each values are still in `string` and
	 * needs to be typcasted appropriately.
	 *
	 * ```
	 * $example_4_withoutError = array(
	 *   'raw'   => array(
	 *     'name'         => 'typeasBool',
	 *     'type'         => 'bool',
	 *     'isPromoted'   => 'false',
	 *     'defaultValue' => 'true',
	 *   ),
	 *   'error' => '',
	 * );
	 *
	 * $example_5_withError = array(
	 *   'raw'   => array(),
	 *   'error' => 'noName',
	 * );
	 * ```
	 *
	 * @return array<string,string|string[]>
	 * @phpstan-return array{error:string, raw:string[]}
	 */
	public static function extractFrom( string $string ): array {
		$params = str_replace( array( '[', ']' ), '', $string, $count );
		$raw    = array();
		$error  = '';

		if ( 2 !== $count ) {
			$error = 'extractionError';

			return compact( 'raw', 'error' );
		}

		foreach ( explode( ',', $params ) as $param ) {
			if ( 2 !== count( $pair = array_values( explode( '=', $param, 2 ) ) ) ) {
				$error = 'invalidPair';

				return compact( 'raw', 'error' );
			}

			list( $name, $data ) = $pair;
			$raw[ $name ]        = $data;
		}

		if ( ! array_key_exists( 'name', $raw ) ) {
			$error = 'noName';

			return compact( 'raw', 'error' );
		}

		return compact( 'raw', 'error' );
	}

	/**
	 * @throws InvalidArgumentException When invalid args passed.
	 * @phpstan-param ArgsAsArray $args
	 */
	public static function createFrom( array $args ): self {
		$instance = self::create( ... );

		if ( ( $parameter = call_user_func_array( $instance, $args ) ) instanceof self ) {
			return $parameter;
		};

		throw new InvalidArgumentException(
			'The given args does not map wilth creation args. To veiw all supported args, see "'
				. self::class
				. '::CREATION_ARGS" constant.'
		);
	}

	/** @phpstan-param array{position?:int,defaultValue?:mixed} $newValues */
	public function recreateWith( array $newValues ): self {
		return self::createFrom( array( ...$this->toArray(), ...$newValues ) );
	}

	/** @phpstan-return ArgsAsArray */
	public function toArray(): array {
		static $array = null;

		if ( null === $array ) {
			$array = array_combine(
				array_keys( self::CREATION_ARGS ),
				array(
					$this->getRawDefaultValue(),
					$this->isPassedByReference(),
					$this->allowsNull(),
					$this->isVariadic(),
					$this->isPromoted(),
					$this->getPosition(),
					$this->getRawType(),
					$this->getName(),
				)
			);
		}

		return $array;
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
