<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;
use TheWebSolver\Codegarage\Generator\ArrayPhpFile;
use TheWebSolver\Codegarage\Generator\Enum\Argument;

class ArrayPhpFileTest extends TestCase {
	#[Test]
	public function itEnsuresImportSubscriptionWorks(): void {
		$this->assertTrue( ArrayPhpFile::isSubscribedForImport() );

		$unsubscribe = ArrayPhpFile::subscribeForImport();

		$this->assertTrue( ArrayPhpFile::isSubscribedForImport() );
		$unsubscribe();
		$this->assertTrue(
			ArrayPhpFile::isSubscribedForImport(),
			'Default is set to true, so reset (unsubscribe) has no effect.'
		);

		$unsubscribe = ArrayPhpFile::subscribeForImport( false );

		$this->assertFalse( ArrayPhpFile::isSubscribedForImport() );
		$unsubscribe();
		$this->assertTrue(
			ArrayPhpFile::isSubscribedForImport(),
			'Resets to previous state before subscription.'
		);
	}

	#[Test]
	public function itAddsCallableOfVariousTypes(): ArrayPhpFile {
		$file = new ArrayPhpFile();

		$file->addCallable( key: 'testFunc', value: 'assert' );

		$this->assertSame( 'assert', $file->getContent()['testFunc'] );

		$file->addCallable( key: 'testMethod', value: __METHOD__ );

		$this->assertSame( array( self::class, __FUNCTION__ ), $file->getContent()['testMethod'] );

		$file->addCallable( key: 'funcFirstClass', value: is_string( ... ) );

		$this->assertSame( 'is_string', $file->getContent()['funcFirstClass'] );

		$file->addCallable( key: 'methodFirstClass', value: TestCase::assertIsString( ... ) );

		$this->assertSame( array( TestCase::class, 'assertIsString' ), $file->getContent()['methodFirstClass'] );

		$file->addCallable( 'testNsFirstClass', value: testFirstClassCallable( ... ) );

		$namespacedFunc = __NAMESPACE__ . '\\testFirstClassCallable';

		$this->assertSame( $namespacedFunc, $file->getContent()['testNsFirstClass'] );

		return $file;
	}

	#[Test]
	#[Depends( 'itAddsCallableOfVariousTypes' )]
	public function itEnsuresAddedCallablesAreFormattedForPrint( ArrayPhpFile $file ): void {
		$print          = $file->print();
		$namespacedFunc = __NAMESPACE__ . '\\testFirstClassCallable';

		foreach ( array( TestCase::class, self::class ) as $import ) {
			$this->assertStringContainsString( "use {$import}", $print );
		}

		$this->assertStringContainsString( "use function {$namespacedFunc}", $print );

		foreach ( array( "'testFunc' => 'assert'", "'funcFirstClass' => 'is_string'" ) as $printedFunc ) {
			$this->assertStringContainsString( $printedFunc, $print );
		}

		$this->assertStringContainsString( "\t'testMethod' => array(\n", $print );
		$this->assertStringContainsString( "\t\t0 => ArrayPhpFileTest::class,\n", $print );
		$this->assertStringContainsString( "\t\t1 => 'itAddsCallableOfVariousTypes',\n", $print );

		$this->assertStringContainsString( "\t'testNsFirstClass' => testFirstClassCallable(...),\n", $print );
	}

	#[Test]
	public function classNameKeyAndCallableMethodAreImportedProperly(): void {
		$file = new ArrayPhpFile();

		$file->addCallable( Argument::class, array( Argument::class, 'casesToString' ) );

		$this->assertSame(
			array( Argument::class => array( Argument::class, 'casesToString' ) ),
			$file->getContent()
		);

		$file->childOf( 'callables.' . TestCase::class )
			->addCallable( 'callback', array( self::class, 'assertTrue' ) );

		$this->assertSame(
			array( 'callback' => array( self::class, 'assertTrue' ) ),
			$file->getContent()['callables'][ TestCase::class ]
		);

		$file->childOf( 'callables.' . TestCase::class )
			->addCallable( Test::class, array( Test::class, 'attribute' ) );

		$this->assertSame(
			array( Test::class, 'attribute' ),
			$file->getContent()['callables'][ TestCase::class ][ Test::class ]
		);

		$file->childOf( 'callables.' . TestCase::class )->addContent( 'simple', 'value' );

		$this->assertSame(
			'value',
			$file->getContent()['callables'][ TestCase::class ]['simple']
		);

		$this->assertCount( 3, $file->getContent()['callables'][ TestCase::class ] );
		$this->assertSame(
			array( 'callback', Test::class, 'simple' ),
			array_keys( $file->getContent()['callables'][ TestCase::class ] )
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

function testFirstClassCallable() {} // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
