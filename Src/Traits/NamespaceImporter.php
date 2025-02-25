<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpNamespace;

trait NamespaceImporter {
	use AliasResolver;

	private string $aliasOfImport;
	/** @var array<string,string> */
	private array $currentTypeImports = array();

	abstract protected function inNamespace(): PhpNamespace;

	protected function importNamespace(): bool {
		( $isImportable = ! $this->namespaceContains() )
			&& $this->inNamespace()->addUse( $this->forImport(), $this->withNamespaceAlias(), $this->forType() );

		return $isImportable;
	}

	/**
	 * Gets the alias of a namespace item, if it has been imported to the current namespace.
	 * Always check with `NamespaceImporter::namespaceContains()` before using this method.
	 */
	protected function getNamespaceAlias(): ?string {
		return $this->findAliasAsIndexIn( imports: $this->namespaceTypeImports() );
	}

	private function namespaceContains(): bool {
		return ( $this->currentTypeImports = $this->inNamespace()->getUses( $this->forType() ) )
			&& in_array( $this->forImport(), $this->currentTypeImports, strict: true );
	}

	private function withNamespaceAlias(): string {
		$alias             = Helpers::extractShortName( $this->forImport() );
		$namespaceLastPart = isset( $this->namespaceTypeImports()[ $alias ] )
			? Helpers::extractShortName( Helpers::extractNamespace( $this->forImport() ) )
			: '';

		return $namespaceLastPart . $alias;
	}

	/** @return array<string,string> */
	private function namespaceTypeImports(): array {
		return $this->currentTypeImports ?? array();
	}
}
