<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpNamespace;

trait NamespaceImporter {
	use AliasResolver;

	/** @var array<string,string> */
	private array $namespaceImports;

	abstract protected function inNamespace(): PhpNamespace;

	protected function importNamespace(): bool {
		( $isImportable = ! $this->namespaceContains() )
			&& $this->inNamespace()->addUse( $this->forImport(), $this->withNamespaceAlias(), $this->forType() );

		return $isImportable;
	}

	private function namespaceContains(): bool {
		return ( $this->namespaceImports = $this->inNamespace()->getUses( $this->forType() ) )
			&& in_array( $this->forImport(), $this->namespaceImports, strict: true );
	}

	private function withNamespaceAlias(): string {
		$alias = Helpers::extractShortName( $this->forImport() );

		return ( isset( $this->namespaceImports()[ $alias ] )
			? Helpers::extractShortName( Helpers::extractNamespace( $this->forImport() ) )
			: '' ) . $alias;
	}

	/** @return array<string,string> */
	private function namespaceImports(): array {
		return $this->namespaceImports ?? array();
	}
}
