<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpNamespace;

trait ImportResolver {
	private PhpNamespace $namespace;

	/** @var array{0:string,1:string,2:string} */
	private array $currentlyImportingItem;

	public function setNamespace( PhpNamespace $namespace = null ): static {
		$this->namespace ??= $namespace ?? new PhpNamespace( '' );

		return $this;
	}

	public function getNamespace(): PhpNamespace {
		return $this->namespace;
	}

	/**
	 * @param PhpNamespace::NAME* $type
	 * @return string The alias of the $item that is being imported. Mostly the classname only without the
	 *                namespace part. If already has alias, alias is prefixed with last part of namespace.
	 */
	public function addUseStatementOf( string $item, string $type = PhpNamespace::NAME_NORMAL ): string {
		$nameOnly                     = Helpers::extractShortName( $item );
		$this->currentlyImportingItem = array( $item, $type, $nameOnly );

		if ( $this->hasAlreadyImportedCurrentItem() ) {
			return $this->currentItemResolvedAs( $nameOnly );
		}

		$namePrefixedByLastPartOfNamespace = $this->prefixedWithLastPartOfCurrentItemNamespace();

		$this->getNamespace()->addUse( $item, $namePrefixedByLastPartOfNamespace, of: $type );

		return $this->currentItemResolvedAs( $namePrefixedByLastPartOfNamespace );
	}

	private function hasAlreadyImportedCurrentItem(): bool {
		[$item] = $this->currentlyImportingItem;

		return in_array( $item, haystack: $this->getNamespace()->getUses(), strict: true );
	}

	private function hasAlreadyAliasedCurrentItem(): bool {
		[, $type, $nameOnly] = $this->currentlyImportingItem;

		return ! empty( $uses = $this->getNamespace()->getUses( of: $type ) ) && array_filter(
			array: $uses,
			callback: static fn( string $alias ): bool => $alias === $nameOnly,
			mode: ARRAY_FILTER_USE_KEY
		);
	}

	private function prefixedWithLastPartOfCurrentItemNamespace(): string {
		[$fqcn,, $nameOnly] = $this->currentlyImportingItem;

		$namespaceLastPart = $this->hasAlreadyAliasedCurrentItem()
			? Helpers::extractShortName( Helpers::extractNamespace( $fqcn ) )
			: '';

		return $namespaceLastPart . $nameOnly;
	}

	private function currentItemResolvedAs( string $item ): string {
		unset( $this->currentlyImportingItem );

		return $item;
	}
}
