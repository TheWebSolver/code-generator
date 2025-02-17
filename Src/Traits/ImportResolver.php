<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpNamespace;

trait ImportResolver {
	private PhpNamespace $namespace;

	public function setNamespace( PhpNamespace $namespace = null ): static {
		$this->namespace ??= $namespace ?? new PhpNamespace( '' );

		return $this;
	}

	public function getNamespace(): PhpNamespace {
		return $this->namespace;
	}

	/**
	 * @param string $item
	 * @return string The alias of the $item that is being imported. Mostly the classname only without the
	 *                namespace part. If already has alias, alias is prefixed with last part of namespace.
	 */
	public function addUseStatementOf( string $item ): string {
		$classnameOnly = $alias = Helpers::extractShortName( $item );

		if ( $this->hasAlreadyImported( $item ) ) {
			return $classnameOnly;
		}

		$lastPartOfNamespace = $this->hasAlreadyAliasedImportedItemAs( $classnameOnly )
			? Helpers::extractShortName( Helpers::extractNamespace( $item ) )
			: '';

		$this->getNamespace()->addUse( $item, $alias = $lastPartOfNamespace . $classnameOnly );

		return $alias;
	}

	private function hasAlreadyImported( string $item ): bool {
		return in_array( $item, haystack: $this->getNamespace()->getUses(), strict: true );
	}

	private function hasAlreadyAliasedImportedItemAs( string $classnameOnly ): bool {
		return ! empty( $uses = $this->getNamespace()->getUses() ) && array_filter(
			array: $uses,
			callback: static fn( string $alias ): bool => $alias === $classnameOnly,
			mode: ARRAY_FILTER_USE_KEY
		);
	}
}
