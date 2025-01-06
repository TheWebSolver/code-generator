<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Generator\Parameter;
use TheWebSolver\Codegarage\Generator\Data\ParamExtractionError;

class ParameterTest extends TestCase {
	public function testParameterExtractionFromString(): void {
		$this->assertTrue(
			'extractionError' === Parameter::extractFrom( string: 'something' )['error']->type(),
			'Given string must be enclosed between "[" and "]".'
		);

		$this->assertTrue(
			'invalidPair' === Parameter::extractFrom( '[name]' )['error']->type(),
			'Parameter arg must be a key value pair separated by "=" equal sign.'
		);

		$attributes = Parameter::extractFrom( '[name=valueContains=sign]' );

		$this->assertTrue(
			'invalidPair' === $attributes['error']->type(),
			'Parameter arg value must not contain "=" equal sign.'
		);

		$this->assertSame( 'name=valueContains=sign', $attributes['error']->value() );

		$this->assertTrue(
			'invalidCreationArg' === Parameter::extractFrom( '[invalidArg=value]' )['error']->type(),
			'Parameter arg name must be one of "Parameter::CREATION_ARGS" key.'
		);

		$attributes = Parameter::extractFrom(
			string: '[name=value]',
			validator: static fn() => ParamExtractionError::of( 'validator', 'name=value' )
		);

		$this->assertTrue(
			'validator' === $attributes['error']->type(),
			'It must catch ParamExtractionError from external validator.'
		);

		$attributes = Parameter::extractFrom( string: '[type=string]' );

		$this->assertSame( array( 'type' => 'string' ), $attributes['raw'] );
		$this->assertTrue(
			'noName' === $attributes['error']->type(),
			'Given string must contain the required "Parameter::CREATION_ARGS" key called "name".'
		);

		$attributes = Parameter::extractFrom( string: '[name=test,type=string]' );

		$this->assertNull( $attributes['error'], message: 'Valid string must not return error object.' );
		$this->assertSame(
			actual: $attributes['raw'],
			expected: array(
				'name' => 'test',
				'type' => 'string',
			),
		);

		$attributes = Parameter::extractFrom(
			string: '[name=test,type=string]',
			validator: static function ( string $arg, string $value ): string {
				return 'name' === $arg && 'test' === $value ? 'valueFromValidator' : $value;
			}
		);

		$this->assertTrue(
			'valueFromValidator' === $attributes['raw']['name'],
			'It must save arg value from external validator.'
		);
	}
}
