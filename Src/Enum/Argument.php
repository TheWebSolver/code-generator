<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Enum;

enum Argument: string {
	case Name      = 'name';
	case Type      = 'type';
	case Default   = 'defaultValue';
	case Position  = 'position';
	case Promoted  = 'isPromoted';
	case Variadic  = 'isVariadic';
	case Nullable  = 'isNullable';
	case Reference = 'isReference';

	/** @var array{type:self,defaultValue:self,isPromoted:self,isVariadic:self,isNullable:self,isReference:self} */
	public const INTRACTABLE = array(
		Argument::Type->value      => Argument::Type,
		Argument::Default->value   => Argument::Default,
		Argument::Promoted->value  => Argument::Promoted,
		Argument::Variadic->value  => Argument::Variadic,
		Argument::Nullable->value  => Argument::Nullable,
		Argument::Reference->value => Argument::Reference,
	);

	/** @var array{name:self,position:self} */
	public const NON_INTRACTABLE = array(
		self::Name->value     => self::Name,
		self::Position->value => self::Position,
	);

	public const TYPES = array(
		self::Reference->value => 'bool',
		self::Nullable->value  => 'bool',
		self::Variadic->value  => 'bool',
		self::Promoted->value  => 'bool',
		self::Position->value  => 'int',
		self::Default->value   => 'mixed',
		self::Type->value      => '?string',
		self::Name->value      => 'string',
	);

	public function isIntractable(): bool {
		return ! isset( self::NON_INTRACTABLE[ $this->value ] );
	}

	public function type(): string {
		return self::TYPES[ $this->value ];
	}

	/** @return string[] */
	public static function casesToString(): array {
		return array_column( self::cases(), column_key: 'value' );
	}
}
