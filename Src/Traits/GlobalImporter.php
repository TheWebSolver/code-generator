<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use ArrayObject as PhpGlobal;

/** @template TType */
trait GlobalImporter {
	use AliasResolver;

	/** @return PhpGlobal<TType,array<string,string>> */
	abstract protected function inGlobal(): PhpGlobal;

	/** @return bool `true` if $name does not have a namespace, else `false`. */
	final protected function importGlobal(): bool {
		( $isImportable = $this->globalImportable() )
			&& $this->inGlobal()->offsetSet( $this->forType(), $this->withGlobalImport() );

		return $isImportable;
	}

	protected function getGlobalAlias(): ?string {
		return $this->findAliasAsIndexIn( $this->globalTypeImports() );
	}

	/** @return array<string,string> */
	private function globalTypeImports(): array {
		return $this->inGlobal()[ $this->forType() ] ?? array();
	}

	private function globalContains(): bool {
		return ( $imports = $this->globalTypeImports() ) && in_array( $this->forImport(), $imports, strict: true );
	}

	private function globalImportable(): bool {
		return ! str_contains( $this->forImport(), needle: '\\' ) && ! $this->globalContains();
	}

	/** @return array<string,string> */
	private function withGlobalImport(): array {
		// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		return array( ...$this->globalTypeImports(), $this->forImport() => $this->forImport() );
	}
}
