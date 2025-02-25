<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Helper;

use ArrayObject as Gbl;
use Nette\PhpGenerator\PhpNamespace as Ns;
use TheWebSolver\Codegarage\Generator\Traits\GlobalImporter;
use TheWebSolver\Codegarage\Generator\Traits\NamespaceImporter;

final class ImportBuilder {
	/** @use GlobalImporter<value-of<self::IMPORTABLE_TYPES>> */
	use GlobalImporter, NamespaceImporter;

	public const IMPORTABLE_TYPES = array( Ns::NAME_NORMAL, Ns::NAME_FUNCTION, Ns::NAME_CONSTANT );

	/** @var value-of<self::IMPORTABLE_TYPES> */
	private string $type = Ns::NAME_NORMAL;

	/** @param Gbl<value-of<self::IMPORTABLE_TYPES>,array<string,string>> $globals */
	public function __construct( private string $item, private Ns $namespace, private Gbl $globals ) {
		$this->item = $this->withoutPrecedingNamespaceSeparator();
	}

	/** @param Gbl<value-of<self::IMPORTABLE_TYPES>,array<string,string>> $global */
	public static function formattedAliasOf( string $item, Gbl $global, Ns $namespace ): ?string {
		$self = new self( $item, $namespace, $global );

		return array_reduce( self::IMPORTABLE_TYPES, callback: $self->discoverFormattedAlias( ... ) );
	}

	/** @param value-of<self::IMPORTABLE_TYPES> $name */
	public function ofType( string $name ): self {
		$this->type = $name;

		return $this;
	}

	public function import(): bool {
		return $this->importGlobal() ?: $this->importNamespace();
	}

	public function getAlias(): ?string {
		return match ( true ) {
			$this->globalContains()    => $this->getGlobalAlias(),
			$this->namespaceContains() => $this->getNamespaceAlias(),
			default                    => null,
		};
	}

	public function getFormattedAlias(): ?string {
		return ( $alias = $this->getAlias() ) ? $this->getCurrentTypeFormattedAlias( $alias ) : null;
	}

	/** @return value-of<self::IMPORTABLE_TYPES> */
	protected function forType(): string {
		return $this->type;
	}

	protected function forImport(): string {
		return $this->item;
	}

	/** @return Gbl<value-of<self::IMPORTABLE_TYPES>,array<string,string>> */
	protected function inGlobal(): Gbl {
		return $this->globals;
	}

	protected function inNamespace(): Ns {
		return $this->namespace;
	}

	/** @param value-of<self::IMPORTABLE_TYPES> $type */
	private function discoverFormattedAlias( ?string $found, string $type ): ?string {
		( $formattedAlias = $this->ofType( $type )->getFormattedAlias() ) && ( $found = $formattedAlias );

		return $found;
	}

	private function withoutPrecedingNamespaceSeparator(): string {
		return ltrim( string: $this->item, characters: '\\' );
	}
}
