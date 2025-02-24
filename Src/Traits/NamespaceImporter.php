<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpNamespace as Ns;

trait NamespaceImporter {
	private Ns $namespace;
	private string $import;

	/** @var Ns::NAME_* */
	private string $type = Ns::NAME_NORMAL;
	/** @var ?string[] */
	private ?array $currentTypeImports = null;

	/** @param Ns::NAME_* $type */
	public function ofType( string $type ): static {
		$this->type = $type;

		return $this;
	}

	public function import(): bool {
		if ( $this->itemExistsInCurrentImports() ) {
			return false;
		}

		$alias = $this->maybeCreateAltAliasFor( Helpers::extractShortName( $this->import ) );

		$this->namespace->addUse( $this->import, $alias, of: $this->type );

		return true;
	}

	public function getAlias(): ?string {
		return $this->itemExistsInCurrentImports() ? $this->findAliasInCurrentImports() : null;
	}

	final public function getFormattedAlias(): ?string {
		return match ( true ) {
			! is_null( $alias = $this->ofType( Ns::NAME_NORMAL )->getAlias() )   => "{$alias}::class",
			! is_null( $alias = $this->ofType( Ns::NAME_FUNCTION )->getAlias() ) => $alias,
			default                                                              => null,
		};
	}

	final protected function importStatementIn( Ns $namespace, string $item ): void {
		$this->namespace = $namespace;
		$this->import    = $item;
	}

	final protected function findAliasInCurrentImports(): ?string {
		return (string) array_search( $this->import, $this->getCurrentTypeImports(), strict: true ) ?: null;
	}

	final protected function importedItemAliasedAs( string $alias ): bool {
		return ! empty( $imports = $this->getCurrentTypeImports() ) && isset( $imports[ $alias ] );
	}

	protected function maybeCreateAltAliasFor( string $alias ): string {
		$namespaceLastPart = $this->importedItemAliasedAs( $alias )
			? Helpers::extractShortName( Helpers::extractNamespace( $this->import ) )
			: '';

		return $namespaceLastPart . $alias;
	}

	private function itemExistsInCurrentImports(): bool {
		$this->currentTypeImports = $this->namespace->getUses( $this->type );

		return in_array( $this->import, $this->currentTypeImports, strict: true );
	}

	/** @return string[] */
	private function getCurrentTypeImports(): array {
		return $this->currentTypeImports ?? $this->namespace->getUses( of: $this->type );
	}
}
