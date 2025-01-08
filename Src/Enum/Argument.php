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

	public function isIntractable(): bool {
		return ! isset( self::NON_INTRACTABLE[ $this->value ] );
	}
}
