<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

trait GlobalImporter {
	/** @var array<string,string> */
	private array $nonNamespacedImports = array();

	/** @return array<string,string> */
	final protected function getGlobalImports(): array {
		return $this->nonNamespacedImports;
	}

	/** @return bool `true` if $name does not have a namespace, else `false`. */
	final protected function importGlobal( string $name ): bool {
		return $this->entitledForGlobalImport( $name = $this->withoutPrecedingNamespaceSeparator( $name ) )
			&& $this->nonNamespacedImports[ $name ] = $name;
	}

	/** @return ?string `null` if $name has namespace or not yet imported. */
	final protected function globallyImported( string $name ): ?string {
		return $this->nonNamespacedImports[ $this->withoutPrecedingNamespaceSeparator( $name ) ] ?? null;
	}

	private function entitledForGlobalImport( string $name ): bool {
		return ! str_contains( $name, needle: '\\' )
			&& ! in_array( $name, $this->nonNamespacedImports, strict: true );
	}

	private function withoutPrecedingNamespaceSeparator( string $name ): string {
		return ltrim( string: $name, characters: '\\' );
	}
}
