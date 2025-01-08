<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
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
}
