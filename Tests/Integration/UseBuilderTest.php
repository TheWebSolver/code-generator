<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Integration;

use PHPUnit\Framework\TestCase;
use Nette\PhpGenerator\PhpNamespace;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Generator\Helper\UseBuilder;

class UseBuilderTest extends TestCase {
	private PhpNamespace $namespace;

	protected function setUp(): void {
		$this->namespace = new PhpNamespace( '' );
	}

	protected function tearDown(): void {
		unset( $this->namespace );
	}

	#[Test]
	public function itEnsuresStatementsAreImported(): void {
		$this->assertTrue( ( new UseBuilder( TestCase::class, $this->namespace ) )->import() );
		$this->assertFalse(
			( new UseBuilder( TestCase::class, $this->namespace ) )->import(),
			'Must not import same statement again in same namespace.'
		);

		$classImporter = new UseBuilder( Test::class, $this->namespace );
		$funcImporter  = ( new UseBuilder( __NAMESPACE__ . '\\testNamespacedFunc', $this->namespace ) )
			->ofType( PhpNamespace::NAME_FUNCTION );

		$altClassImporter = ( new UseBuilder( 'DifferentNamespaced\\' . Test::class, $this->namespace ) )
			->ofType( PhpNamespace::NAME_NORMAL );

		$this->assertTrue( $classImporter->import() );
		$this->assertTrue( $funcImporter->import() );
		$this->assertTrue( $altClassImporter->import() );
		$this->assertSame(
			'Test',
			$classImporter->ofType( PhpNamespace::NAME_NORMAL )->getAlias()
		);
		$this->assertSame(
			'AttributesTest',
			$altClassImporter->ofType( PhpNamespace::NAME_NORMAL )->getAlias()
		);
		$this->assertSame(
			'testNamespacedFunc',
			$funcImporter->ofType( PhpNamespace::NAME_FUNCTION )->getAlias()
		);

		$this->assertSame( 'Test::class', $classImporter->getFormattedAlias() );
		$this->assertSame( 'testNamespacedFunc', $funcImporter->getFormattedAlias() );
	}
}

function testNamespacedFunc(): void {} // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
