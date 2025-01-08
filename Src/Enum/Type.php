<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Enum;

use Throwable;
use ValueError;

enum Type: string {
	case Object = 'object';
	case String = 'string';
	case Float  = 'float';
	case Array  = 'array';
	case Bool   = 'bool';
	case Null   = 'null';
	case Int    = 'int';

	public const POSSIBLE_TRUTHY = 'true,on,yes,1';
	public const POSSIBLE_FALSY  = 'false,off,no,0';
	public const EMPTY_ARRAY     = 'array()';
	public const TRUTHY          = 'true';
	public const FALSY           = 'false';
	public const BOOL_ALT        = 'boolean';
	public const FLOAT_ALT       = 'double';
	public const INT_ALT         = 'integer';

	/** @throws ValueError When value cannot be type casted. */
	public static function set( mixed $value, string $type ): mixed {
		try {
			// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged -- Setting type OK.
			match ( $type ) {
				self::INT_ALT, self::FLOAT_ALT => @settype( $value, $type ),
				self::BOOL_ALT                 => @settype( $value, self::Bool->value ),
				self::TRUTHY                   => $value = true,
				self::FALSY                    => $value = false,
				default                        => @settype( $value, self::from( $type )->value )
			};
			// phpcs:enable

			return $value;
		} catch ( Throwable ) {
			throw new ValueError(
				sprintf( 'Impossible to set "%1$s" type to "%2$s".', get_debug_type( $value ), $type )
			);
		}
	}

	public static function castToFalseIfNotTrue( mixed $value ): string {
		return in_array( $value, array( self::TRUTHY, self::FALSY ), strict: true ) ? $value : self::FALSY;
	}

	public static function match( mixed $value ): mixed {
		return match ( $value ) {
			self::castToFalseIfNotTrue( $value ) => filter_var( $value, FILTER_VALIDATE_BOOL, array( 'default' => false ) ),
			self::EMPTY_ARRAY                    => array(),
			default                              => $value,
		};
	}

	public static function castToBoolByValueOrType( mixed $value, ?string $type = null ): ?string {
		return self::isBool( $type ) ? self::getBoolFrom( $value ) : null;
	}

	public static function castImplicitBool( string $value, string $type ): string {
		return null === ( $bool = self::castToBoolByValueOrType( $value, $type ) ) ? $value : $bool;
	}

	public static function toBool( mixed $value ): bool {
		return true === self::match( self::getBoolFrom( $value ) );
	}

	/** @throws ValueError When value cannot be type casted. */
	public static function cast( mixed $value, string $type ): mixed {
		return match ( $type ) {
			self::Object->value => self::set( $value, $type ),
			self::String->value => self::set( $value, $type ),
			self::Array->value  => self::match( $value ),
			self::Float->value  => self::set( $value, $type ),
			self::Bool->value   => self::match( $value ),
			self::Null->value   => self::set( $value, $type ),
			self::Int->value    => self::set( $value, $type ),
			default             => $value,
		};
	}

	private static function isBool( ?string $type ): bool {
		return ( self::TRUTHY === $type || self::FALSY === $type || self::BOOL_ALT === $type )
			? true
			: ! ( null !== $type && self::Bool->value !== self::tryFrom( $type )?->value );
	}

	private static function isTruthy( mixed $value ): bool {
		return in_array(
			needle: $value,
			haystack: array( true, ...explode( separator: ',', string: self::POSSIBLE_TRUTHY ) ),
			strict: true
		);
	}

	private static function getBoolFrom( mixed $value ): string {
		return self::isTruthy( $value ) ? self::TRUTHY : self::FALSY;
	}
}
