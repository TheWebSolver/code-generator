<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Integration;

use PHPUnit\Framework\TestCase;
use Nette\PhpGenerator\PhpNamespace;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Generator\Traits\ImportResolver;

class ImportResolverTest extends TestCase {
	#[Test]
	public function itEnsuresSetterGetterWorks(): void {
		$import = new class() {
			use ImportResolver;
		};

		$import->setNamespace( $namespace = new PhpNamespace( '' ) );

		$this->assertSame( $namespace, $import->getNamespace() );
	}

	#[Test]
	public function itEnsuresAliasesAreCreatedWhenImporting(): void {
		$import = new class() {
			use ImportResolver;
		};

		$import->setNamespace( new PhpNamespace( '' ) );

		$import->addUseStatementOf( ImportResolver::class );
		$import->addUseStatementOf( TestCase::class );

		$this->assertCount( 2, $imports = $import->getNamespace()->getUses() );

		foreach ( array( ImportResolver::class, TestCase::class ) as $item ) {
			$this->assertContains( $item, $imports );
		}

		$import->addUseStatementOf( ImportResolver::class );

		$this->assertCount( 2, $import->getNamespace()->getUses(), 'Must not add same import again.' );

		$import->addUseStatementOf( 'DifferentNamespaced\\' . ImportResolver::class );

		$this->assertCount( 3, $uses = $import->getNamespace()->getUses() );
		$this->assertContains( 'DifferentNamespaced\\' . ImportResolver::class, $uses );
		$this->assertArrayHasKey(
			'TraitsImportResolver',
			$uses,
			'Must alias if already imported same classname that exists in another namespace.'
		);
	}
}
