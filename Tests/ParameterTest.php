<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use Closure;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Generator\Parameter;
use TheWebSolver\Codegarage\Generator\Data\ParamExtractionError;
use TheWebSolver\Codegarage\Generator\Error\ParamExtractionException as Error;

class ParameterTest extends TestCase {
	#[Test]
	#[DataProvider( 'provideExtractionString' )]
	public function parameterExtractionFromString(
		string $string,
		?int $errorCode,
		array $expectedValue = array(),
		?Closure $validator = null
	): void {
		if ( $errorCode ) {
			$this->expectExceptionCode( $errorCode );
		}

		$this->assertSame( $expectedValue, Parameter::extractFrom( $string, $validator ) );
	}

	public static function provideExtractionString(): array {
		return array(
			array( 'not inside bracket', Error::NOT_ENCLOSED_IN_BRACKETS ),
			array( '[name]', Error::INVALID_PAIR ),
			array( '[name=valueContains=sign]', Error::INVALID_PAIR ),
			array( '[invalidArg=value]', Error::INVALID_CREATION_ARG ),
			array( '[type=string]', Error::NO_NAME_ARG ),
			array(
				'string'        => '[name=test,type=string]',
				'errorCode'     => null,
				'expectedValue' => array(
					'name' => 'test',
					'type' => 'string',
				),
			),
			array(
				'string'        => '[name=test,type=string]',
				'errorCode'     => null,
				'expectedValue' => array(
					'name' => 'valueFromValidator',
					'type' => 'string',
				),
				'validator'     => static function ( string $arg, string $value, string $param ): string {
					return 'name' === $arg && 'test' === $value && str_contains( $param, 'name=test' )
						? 'valueFromValidator'
						: $value;
				},
			),
			array(
				'string'        => '[name=value]',
				'errorCode'     => Error::FROM_VALIDATOR,
				'expectedValue' => array(),
				'validator'     => static fn() => ParamExtractionError::of( 'validator', 'name=value' ),
			),
		);
	}
}
