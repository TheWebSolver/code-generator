<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use Nette\PhpGenerator\PhpNamespace;

trait AliasResolver {
	/** @return PhpNamespace::NAME_* */
	abstract protected function forType(): string;
	abstract protected function forImport(): string;

	/** @param array<string,string> $imports */
	protected function findAliasAsIndexIn( array $imports ): ?string {
		return (string) array_search( $this->forImport(), $imports, strict: true ) ?: null;
	}

	protected function getCurrentTypeFormattedAlias( string $alias ): ?string {
		return match ( $this->forType() ) {
			default                     => null,
			PhpNamespace::NAME_NORMAL   => "{$alias}::class",
			PhpNamespace::NAME_FUNCTION,
			PhpNamespace::NAME_CONSTANT => $alias,
		};
	}
}
