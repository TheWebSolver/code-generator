<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use Closure;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Generator\Parameter;
use TheWebSolver\Codegarage\Generator\Error\ParamExtractionException as ExtractionError;

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
			array( 'not inside bracket', ExtractionError::NOT_ENCLOSED_IN_BRACKETS ),
			array( '[name]', ExtractionError::INVALID_PAIR ),
			array( '[name=valueContains=sign]', ExtractionError::INVALID_PAIR ),
			array( '[invalidArg=value]', ExtractionError::INVALID_CREATION_ARG ),
			array( '[type=string]', ExtractionError::NO_NAME_ARG ),
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
				'validator'     => static function ( array $processed, string $raw ): ?array {
					if ( ! isset( $processed['name'] ) ) {
						return $processed;
					}

					if ( 'test' === $processed['name'] && str_contains( $raw, 'name=test' ) ) {
						$processed['name'] = 'valueFromValidator';
					}

					return $processed;
				},
			),
			array(
				'string'        => '[name=value]',
				'errorCode'     => ExtractionError::FROM_VALIDATOR,
				'expectedValue' => array(),
				'validator'     => static fn() => null, /* or empty array */
			),
		);
	}
}
