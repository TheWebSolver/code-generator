<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use ArrayObject as PhpGlobal;

/** @template TType */
trait GlobalImporter {
	use AliasResolver;

	/** @var array<string,string> */
	private array $globalImports;

	/** @return PhpGlobal<TType,array<string,string>> */
	abstract protected function inGlobal(): PhpGlobal;

	/** @return bool `true` if $name does not have a namespace, else `false`. */
	final protected function importGlobal(): bool {
		( $isImportable = $this->globalImportable() )
			&& $this->inGlobal()->offsetSet( $this->forType(), $this->withGlobalImport() );

		return $isImportable;
	}

	private function globalContains(): bool {
		return ( $this->globalImports = $this->inGlobal()[ $this->forType() ] ?? array() )
			&& in_array( $this->forImport(), $this->globalImports, strict: true );
	}

	private function globalImportable(): bool {
		return ! str_contains( $this->forImport(), needle: '\\' ) && ! $this->globalContains();
	}

	/** @return array<string,string> */
	private function withGlobalImport(): array {
		// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		return array( ...$this->globalImports(), $this->forImport() => $this->forImport() );
	}

	/** @return array<string,string> */
	private function globalImports(): array {
		return $this->globalImports ?? array();
	}
}
