<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use stdClass;
use ValueError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Generator\Enum\Type;

class TypeTest extends TestCase {
	#[Test]
	#[DataProvider( 'provideBoolValueAndType' )]
	public function itConvertsToBooleanStringIfEitherValueOrTypeIsValidElseNull(
		mixed $value,
		?string $type,
		?string $expected = null
	): void {
		$this->assertSame( $expected, actual: Type::castToBoolByValueOrType( $value, $type ) );
	}

	public static function provideBoolValueAndType(): array {
		return array(
			array( 'value', 'does-not-matter-if-type-is-not-a-boolean-type' ),
			array( 'empty-type-given-is-always-null', '' ),
			array( 'true', 'string' ),
			array( 'false', 'int' ),

			array( 'true', null, 'true' ),
			array( 'true', 'bool', 'true' ),
			array( 'true', 'boolean', 'true' ),
			array( 'on', 'bool', 'true' ),
			array( 'yes', 'bool', 'true' ),
			array( '1', 'bool', 'true' ),
			array( true, 'bool', 'true' ),
			array( 'true', 'true', 'true' ),
			array( true, 'true', 'true' ),

			// Here, type as "null" means cast boolean using value without type.
			array( 'everything-else-will-be-false', null, 'false' ),
			array( fn(): int => 1, 'true', 'false' ),
			array( 'false', 'false', 'false' ),
			array( 'false', 'bool', 'false' ),
			array( false, 'boolean', 'false' ),
			array( false, 'false', 'false' ),
			array( 'off', 'bool', 'false' ),
			array( 'no', 'bool', 'false' ),
			array( '0', 'bool', 'false' ),
			array( 'and-anything-other-than-possible-truthy', 'bool', 'false' ),
		);
	}

	#[Test]
	#[DataProvider( 'provideImplicitTypeCastToBool' )]
	public function itConvertsImplicitBooleanElseReturnsWhateverValueGiven(
		string $value,
		string $type,
		string $expected
	): void {
		$this->assertSame( $expected, actual: Type::castImplicitBool( $value, $type ) );
	}

	public static function provideImplicitTypeCastToBool(): array {
		return array(
			array( 'returns-original-value', 'if-type-is-not-a-boolean-type', 'returns-original-value' ),
			array( 'empty-type-is-always-null', '', 'empty-type-is-always-null' ),

			array( 'true', 'type-does-not-matter-if-value-itself-is-boolean', 'true' ),
			array( 'false', 'same-thing-for-false-value-also', 'false' ),
			array( 'true', 'string', 'true' ),
			array( 'false', 'int', 'false' ),

			array( 'true', 'bool', 'true' ),
			array( 'true', 'boolean', 'true' ),
			array( 'on', 'bool', 'true' ),
			array( 'yes', 'bool', 'true' ),
			array( '1', 'bool', 'true' ),
			array( 'true', 'true', 'true' ),

			array( 'everything-else-is-false', 'boolean', 'false' ),
			array( 'false', 'boolean', 'false' ),
			array( 'off', 'bool', 'false' ),
			array( 'no', 'bool', 'false' ),
			array( '0', 'bool', 'false' ),
			array( 'false', 'false', 'false' ),
			array( 'and-anything-other-than-possible-truthy', 'bool', 'false' ),
		);
	}

	#[Test]
	#[DataProvider( 'providePossibleBoolValues' )]
	public function itConvertsValueToFalseIfNotExplicitTrue( mixed $value, string $expected ): void {
		$this->assertSame( $expected, actual: Type::castToFalseIfNotTrue( $value ) );
	}

	public static function providePossibleBoolValues(): array {
		return array(
			array( 'true', 'true' ),
			array( '1', 'false' ), // Everything is false except "true" literal string.
			array( 'false', 'false' ),
			array( 'truthy', 'false' ),
			array( 123, 'false' ),
			array( fn(): int => 45, 'false' ),
		);
	}

	#[Test]
	#[DataProvider( 'provideDataToConvert' )]
	public function itConvertsValueToTheGiveType(
		mixed $value,
		string $type,
		mixed $expected,
		bool $throws = false
	): void {
		if ( $throws ) {
			$this->expectException( ValueError::class );
		}

		$this->assertSame( $expected, actual: Type::set( $value, $type ) );
	}

	public static function provideDataToConvert(): array {
		$fn        = fn(): int => 1;
		$cls       = new \stdClass();
		$cls->pre  = 'Web';
		$cls->post = 'Developer';

		return array(
			array( 'string2', 'integer', 0 ),
			array( '2string', 'int', 2 ),
			array( '', 'false', false ),
			array( 99, 'string', '99' ),
			array( 99.00, 'string', '99' ),
			array( 123, 'float', 123.00 ),
			array( 'some', 'false', false ),
			array( 'some', 'true', true ),
			array( 'some', 'bool', true ),
			array( 'anything', 'null', null ),
			array( 'if-given', 'invalid-type', 'will-throw-an-error', true ),
			array( true, 'type', 'must-match-one-of-the-case', true ),
			array( $fn, 'array', array( $fn ) ),
			array( new \stdClass(), 'array', array() ),
			array(
				$cls,
				'array',
				array(
					'pre'  => 'Web',
					'post' => 'Developer',
				),
				false,
			),
			array( $fn, 'string', 'no-can-do', true ),
			array( $fn, 'object', $fn ),
			array( $fn, 'int', 1 ),
			array( $cls, 'float', 1.00 ),
			array( $cls, 'object', $cls ),
			array( $fn, 'bool', true ),
			array( $cls, 'bool', true ),
		);
	}

	#[Test]
	public function itConvertsValuesToObjectType(): void {
		$this->assertInstanceOf( stdClass::class, Type::set( array(), 'object' ) );
		$this->assertSame( 'string-to-object', Type::set( 'string-to-object', 'object' )->scalar );

		$arrayToObject = Type::set(
			type: 'object',
			value: array(
				'language'   => 'PHP',
				'isDoing'    => 'testing',
				1            => 'int',
				'with space' => 'value',
			)
		);

		$this->assertSame( 'PHP', $arrayToObject->language );
		$this->assertSame( 'testing', $arrayToObject->isDoing );

		$_1        = 1;
		$withSpace = 'with space';

		// Properties that cannot be retrieved directly can be retrieved by storing in a variable.
		$this->assertSame( 'int', $arrayToObject->{$_1} );
		$this->assertSame( 'value', $arrayToObject->{$withSpace} );
	}

	#[Test]
	#[DataProvider( 'provideValueToBeMatched' )]
	public function itReturnsValueBasedOnValueType( mixed $value, mixed $expected ): void {
		$this->assertEquals( $expected, actual: Type::match( $value ) );
	}

	#[Test]
	#[DataProvider( 'provideValueToBeMatched' )]
	public function itConvertsToBooleanType( mixed $value, mixed $expected, ?bool $converted = null ): void {
		$this->assertSame( $converted ?? $expected, actual: Type::toBool( $value ) );
	}

	public static function provideValueToBeMatched(): array {
		return array(
			array( true, true ),
			array( false, false ),
			array( null, null, false ),
			array( array(), array(), false ),
			array( 25, 25, false ),
			array( 'true', true ),
			array( 'false', false ),
			array( 'array()', array(), false ),
			array( fn(): int => 1, fn(): int => 1, false ),
			array( 'array', 'array', false ),
			array( 'everything-else', 'everything-else', false ),
		);
	}
}
