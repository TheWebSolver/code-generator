<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use DateTime;
use SplFixedArray;
use OutOfBoundsException;
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

		$file->addImportableContent( key: 'testFunc', value: 'assert' );

		$this->assertSame( 'assert', $file->getContent()['testFunc'] );

		$file->addImportableContent( key: 'testMethod', value: __METHOD__ );

		$this->assertSame( array( self::class, __FUNCTION__ ), $file->getContent()['testMethod'] );

		$file->addImportableContent( key: 'funcFirstClass', value: is_string( ... ) );

		$this->assertSame( 'is_string', $file->getContent()['funcFirstClass'] );

		$file->addImportableContent( key: 'methodFirstClass', value: TestCase::assertIsString( ... ) );

		$this->assertSame( array( TestCase::class, 'assertIsString' ), $file->getContent()['methodFirstClass'] );

		$file->addImportableContent( 'testNsFirstClass', value: testFirstClassCallable( ... ) );

		$namespacedFunc = __NAMESPACE__ . '\\testFirstClassCallable';

		$this->assertSame( $namespacedFunc, $file->getContent()['testNsFirstClass'] );

		$file->addImportableContent( 'datetime', DateTime::createFromFormat( ... ) );

		$this->assertSame( array( DateTime::class, 'createFromFormat' ), $file->getContent()['datetime'] );

		return $file;
	}

	#[Test]
	#[Depends( 'itAddsCallableOfVariousTypes' )]
	public function itEnsuresAddedCallablesAreFormattedForPrint( ArrayPhpFile $file ): void {
		$print          = $file->print();
		$namespacedFunc = __NAMESPACE__ . '\\testFirstClassCallable';

		foreach ( array( TestCase::class, self::class ) as $import ) {
			$this->assertPhpFilePrints( "use {$import};\n", $print );
		}

		$this->assertPhpFilePrints( "use function {$namespacedFunc}", $print );

		foreach ( array( "'testFunc' => 'assert'", "'funcFirstClass' => 'is_string'" ) as $printedFunc ) {
			$this->assertPhpFilePrints( $printedFunc, $print );
		}

		$this->assertPhpFilePrints(
			"\t'testMethod' => array( ArrayPhpFileTest::class, 'itAddsCallableOfVariousTypes' ),\n",
			$print
		);
		$this->assertPhpFilePrints( "\t'testNsFirstClass' => testFirstClassCallable(...),\n", $print );
	}

	#[Test]
	#[Depends( 'itAddsCallableOfVariousTypes' )]
	public function itEnsuresImportedItemAliasCanBeRetrieved( ArrayPhpFile $file ): void {
		$this->assertSame( 'TestCase::class', $file->getAliasOf( TestCase::class ) );
		$this->assertSame( 'ArrayPhpFileTest::class', $file->getAliasOf( $this->itAddsCallableOfVariousTypes( ... ) ) );
		$this->assertSame(
			'ArrayPhpFileTest::class',
			$file->getAliasOf( array( self::class, __FUNCTION__ ) ),
			'Does not matter with method name if classname is same'
		);
		$this->assertSame( 'TestCase::class', $file->getAliasOf( TestCase::assertIsString( ... ) ) );
		$this->assertSame( 'testFirstClassCallable', $file->getAliasOf( testFirstClassCallable( ... ) ) );

		$this->assertSame( 'DateTime::class', $file->getAliasOf( \DateTime::createFromFormat( ... ) ) );

		$this->expectException( OutOfBoundsException::class );
		$file->getAliasOf( is_string( ... ), 'Global function is not imported. So, no alias.' );
	}

	#[Test]
	public function itOnlyImportItemUsingImportableMethod(): void {
		$file     = new ArrayPhpFile();
		$callable = array( Argument::class, 'cases' );

		$file->addImportableContent( 'imported', $callable );

		$useArgument = 'use ' . Argument::class;

		$this->assertPhpFilePrints( $useArgument, $file->print() );

		$file = ( new ArrayPhpFile() )->addContent( 'notImported', $callable );

		$this->assertStringNotContainsString( $useArgument, $file->print() );
	}

	#[Test]
	public function classNameKeyAndCallableMethodAreImportedProperly(): ArrayPhpFile {
		$file = new ArrayPhpFile();

		$file->addImportableContent( Argument::class, array( Argument::class, 'casesToString' ) );

		$this->assertSame(
			array( Argument::class => array( Argument::class, 'casesToString' ) ),
			$file->getContent()
		);

		// No need to import key manually as value contains same key and it'll be imported automatically.
		// See `self::itEnsuresUseStatementsAreProperlyImported()` method for import assertion test.
		$file->addImportableContent( SplFixedArray::class, array( SplFixedArray::class, 'fromArray' ) );

		$this->assertSame( array( SplFixedArray::class, 'fromArray' ), $file->getContent()[ SplFixedArray::class ] );

		// Key must be imported manually if its not present in any of the value that is added as file content.
		$file->importFrom( TestCase::class )
			->childOf( 'callables', TestCase::class )
			->addImportableContent( 'callback', array( self::class, 'assertTrue' ) );

		$this->assertSame(
			array( 'callback' => array( self::class, 'assertTrue' ) ),
			$file->getContent()['callables'][ TestCase::class ]
		);

		$file->childOf( 'callables', TestCase::class )
			->addImportableContent( Test::class, array( Test::class, 'attribute' ) );

		$this->assertSame(
			array( Test::class, 'attribute' ),
			$file->getContent()['callables'][ TestCase::class ][ Test::class ]
		);

		$file->childOf( 'callables', TestCase::class )->addContent( 'simple', 'value' );

		$this->assertSame(
			'value',
			$file->getContent()['callables'][ TestCase::class ]['simple']
		);

		$this->assertCount( 3, $file->getContent()['callables'][ TestCase::class ] );
		$this->assertSame(
			array( 'callback', Test::class, 'simple' ),
			array_keys( $file->getContent()['callables'][ TestCase::class ] )
		);

		$file->childOf( 'firstDepth', 'secondDepth', 'thirdDepth' )->addContent( 'some', 'thing' );

		$this->assertSame(
			'thing',
			$file->getContent()['firstDepth']['secondDepth']['thirdDepth']['some']
		);

		$file->childOf( 'firstDepth', 'secondDepth' )->addContent( 'atSecond', 'insertIt' );

		$this->assertSame(
			array( 'thirdDepth', 'atSecond' ),
			array_keys( $file->getContent()['firstDepth']['secondDepth'] )
		);

		$file->childOf( 'firstDepth' )->addImportableContent( 'globalScope', '\DateTime::createFromFormat' );

		$print = $file->print();

		$this->assertSame( $print, $file->print(), 'Each print must flush its own artefact.' );

		return $file;
	}

	#[Test]
	#[Depends( 'classNameKeyAndCallableMethodAreImportedProperly' )]
	public function itEnsuresUseStatementsAreProperlyImported( ArrayPhpFile $file ): string {
		// Preceding namespace separator does not matter either importing or adding content.
		// Also, no need to use `::addImportableContent` method as value does not have anything importable.
		$print   = $file->importFrom( 'BackedEnum' )->addContent( '\BackedEnum', 'cases' )->print();
		$imports = array( self::class, Argument::class, TestCase::class, Test::class, DateTime::class, SplFixedArray::class, 'BackedEnum' );

		foreach ( $imports as $classname ) {
			$this->assertPhpFilePrints( "use {$classname};\n", $print );
		}

		return $print;
	}

	#[Test]
	#[Depends( 'itEnsuresUseStatementsAreProperlyImported' )]
	public function itEnsuresArrayValuesAreProperlyExported( string $print ): void {
		$this->assertPhpFilePrints( "\t'callables' => array(\n", $print );
		$this->assertPhpFilePrints( "\tBackedEnum::class => 'cases',\n", $print );
		$this->assertPhpFilePrints( "\tSplFixedArray::class => array( SplFixedArray::class, 'fromArray' ),\n", $print );
		$this->assertPhpFilePrints( "\t\tTestCase::class => array(\n", $print );
		$this->assertPhpFilePrints( "\t\t\t'callback' => array( ArrayPhpFileTest::class, 'assertTrue' ),\n", $print );
		$this->assertPhpFilePrints( ",\n\t\t\tTest::class => array( Test::class, 'attribute' ),\n", $print );
		$this->assertPhpFilePrints( "\t'firstDepth' => array(\n", $print );
		$this->assertPhpFilePrints( "\t\t'globalScope' => array( DateTime::class, 'createFromFormat' ),\n", $print );
		$this->assertPhpFilePrints( "\t\t'secondDepth' => array(\n", $print );
		$this->assertPhpFilePrints( "\n\t\t\t'thirdDepth' => array( 'some' => 'thing' ),\n", $print );
	}

	private function assertPhpFilePrints( string $stringPart, string $fullPrint ): void {
		$this->assertStringContainsString( $stringPart, $fullPrint );
	}
}

function testFirstClassCallable() {} // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
