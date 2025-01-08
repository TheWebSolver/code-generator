<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Generator\Enum\Argument;

class ArgumentTest extends TestCase {
	#[Test]
	public function itReturnsTrueIfArgumentCaseIsIntractable(): void {
		foreach ( Argument::cases() as $case ) {
			if ( Argument::Name === $case || Argument::Position === $case ) {
				$this->assertFalse( $case->isIntractable() );
			} else {
				$this->assertTrue( $case->isIntractable() );
			}
		}
	}

	#[Test]
	#[DataProvider( 'provideArgumentCaseWithItsType' )]
	public function isReturnsArgumentCaseExpectedDataType( Argument $arg, string $expectedType ): void {
		$this->assertSame( $expectedType, $arg->type() );
		$this->assertContains( $arg->value, Argument::casesToString() );
	}

	public static function provideArgumentCaseWithItsType(): array {
		return array(
			array( Argument::Reference, 'bool' ),
			array( Argument::Nullable, 'bool' ),
			array( Argument::Variadic, 'bool' ),
			array( Argument::Promoted, 'bool' ),
			array( Argument::Position, 'int' ),
			array( Argument::Default, 'mixed' ),
			array( Argument::Type, '?string' ),
			array( Argument::Name, 'string' ),
		);
	}
}
