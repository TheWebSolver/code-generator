<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Enum;

use Throwable;
use ValueError;

enum Type: string {
	case PossibleTruthy = 'true,on,yes,1';
	case PossibleFalsy  = 'false,off,no,0';
	case EmptyArray     = 'array()';

	// Set types.
	case HintObject = 'object';
	case HintString = 'string';
	case HintArray  = 'array';
	case HintFloat  = 'float';
	case HintBool   = 'bool';
	case HintNull   = 'null';
	case HintInt    = 'int';

	// Get types.
	case Truthy = 'true';
	case Falsy  = 'false';
	case Float  = 'double';
	case Bool   = 'boolean';
	case Null   = 'NULL';
	case Int    = 'integer';

	public function resolve(): string {
		return match ( $this ) {
			// Currently doesn't support "unknown type". Defaults to an empty string.
			default                                                => '',
			self::HintBool, self::Truthy, self::Falsy, self::Bool => self::Bool->value,
			self::HintInt, self::Int                              => self::Int->value,
			self::HintNull, self::Null                            => self::Null->value,
			self::HintFloat, self::Float                          => self::Float->value,
			self::HintString                                      => self::HintString->value,
			self::HintArray                                       => self::HintArray->value,
			self::HintObject                                      => self::HintObject->value,
		};
	}

	public function isInferable(): bool {
		return in_array( $this, self::inferable(), strict: true );
	}

	/**
	 * @return static[]
	 * @link https://www.php.net/manual/en/function.gettype.php
	 */
	public static function inferable(): array {
		return array(
			self::HintString,
			self::Bool,
			self::HintBool,
			self::Int,
			self::HintInt,
			self::Float,
			self::HintFloat,
			self::Null,
			self::HintNull,
			self::HintObject,
		);
	}

	/** @return string[] */
	public static function hints(): array {
		return array_column( array_filter( self::cases(), self::canBeHinted( ... ) ), column_key: 'value' );
	}

	public static function castToFalseIfNotTrue( mixed $value ): string {
		return in_array( $value, array( self::Truthy->value, self::Falsy->value ), strict: true )
			? $value
			: self::Falsy->value;
	}

	/** @throws ValueError When value cannot be type casted. */
	public static function set( mixed $value, string $type ): mixed {
		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Setting type OK.
			@settype( $value, type: self::from( $type )->resolve() );

			return $value;
		} catch ( Throwable ) {
			throw new ValueError(
				sprintf( 'Impossible to set "%1$s" type to "%2$s".', get_debug_type( $value ), $type )
			);
		}
	}

	public static function match( mixed $value ): mixed {
		return match ( $value ) {
			self::castToFalseIfNotTrue( $value ) => filter_var( $value, FILTER_VALIDATE_BOOL, array( 'default' => false ) ),
			self::EmptyArray->value              => array(),
			default                              => $value,
		};
	}

	public static function toBoolByValueOrType( mixed $value, ?string $type = null ): ?string {
		return self::isBool( $type ) ? self::getBoolTypeFrom( $value ) : null;
	}

	public static function castImplicitBool( string $value, string $type ): string {
		return null === ( $bool = self::toBoolByValueOrType( $value, $type ) ) ? $value : $bool;
	}

	public static function toBool( mixed $value ): bool {
		return true === self::match( self::getBoolTypeFrom( $value ) );
	}

	/** @throws ValueError When value cannot be type casted. */
	public static function cast( mixed $value, string $type ): mixed {
		return match ( $type ) {
			self::HintString->value => self::set( $value, $type ),
			self::HintArray->value  => self::match( $value ),
			self::HintFloat->value  => self::set( $value, $type ),
			self::HintBool->value   => self::match( $value ),
			self::HintInt->value    => self::set( $value, $type ),
			default                 => $value,
		};
	}

	private static function canBeHinted( Type $type ): bool {
		return str_starts_with( $type->name, needle: 'Hint' );
	}

	private static function isBool( ?string $type ): bool {
		return ! ( null !== $type && self::Bool->value !== self::tryFrom( $type )?->resolve() );
	}

	private static function isTruthy( mixed $value ): bool {
		return in_array(
			needle: $value,
			haystack: array( true, ...explode( separator: ',', string: self::PossibleTruthy->value ) ),
			strict: true
		);
	}

	private static function getBoolTypeFrom( mixed $value ): string {
		return self::isTruthy( $value ) ? self::Truthy->value : self::Falsy->value;
	}
}
