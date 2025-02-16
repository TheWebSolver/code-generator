<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Generator\ArrayPhpFile;
use TheWebSolver\Codegarage\Generator\Enum\Argument;

class ArrayPhpFileTest extends TestCase {
	#[Test]
	public function classNameKeyAndCallableMethodAreImportedProperly(): void {
		$this->assertTrue( true );
		$file = new ArrayPhpFile();

		$file->addCallable( Argument::class, array( Argument::class, 'casesToString' ) );

		$this->assertSame(
			array( 'Argument::class' => array( 'Argument::class', 'casesToString' ) ),
			$file->getContent()
		);

		$file->childOf( 'callables.' . TestCase::class )
			->addCallable( 'callback', array( self::class, 'assertTrue' ) );

		$this->assertSame(
			array( 'callback' => array( 'ArrayPhpFileTest::class', 'assertTrue' ) ),
			$file->getContent()['callables']['TestCase::class']
		);

		$file->childOf( 'callables.' . TestCase::class )
			->addCallable( Test::class, array( Test::class, 'attribute' ) );

		$this->assertSame(
			array( 'Test::class', 'attribute' ),
			$file->getContent()['callables']['TestCase::class']['Test::class']
		);

		$file->childOf( 'callables.' . TestCase::class )->addContent( 'simple', 'value' );

		$this->assertSame(
			'value',
			$file->getContent()['callables']['TestCase::class']['simple']
		);

		$this->assertCount( 3, $file->getContent()['callables']['TestCase::class'] );
		$this->assertSame(
			array( 'callback', 'Test::class', 'simple' ),
			array_keys( $file->getContent()['callables']['TestCase::class'] )
		);

		$file->childOf( 'firstDepth.secondDepth.thirdDepth' )->addContent( 'some', 'thing' );

		$this->assertSame(
			'thing',
			$file->getContent()['firstDepth']['secondDepth']['thirdDepth']['some']
		);

		$file->childOf( 'firstDepth.secondDepth' )->addContent( 'atSecond', 'insertIt' );

		$this->assertSame(
			array( 'thirdDepth', 'atSecond' ),
			array_keys( $file->getContent()['firstDepth']['secondDepth'] )
		);
	}
}
