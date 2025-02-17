<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator;

use Closure;
use Nette\Utils\Strings;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Printer;
use TheWebSolver\Codegarage\Generator\Traits\ArrayExport;
use TheWebSolver\Codegarage\Generator\Traits\ImportResolver;

class ArrayPhpFile {
	use ArrayExport, ImportResolver;


	/** @var mixed[] */
	private array $content;
	private string|int $parentKey;

	private static bool $subscribeImports = true;

	public function __construct(
		public readonly PhpFile $phpFile = new PhpFile(),
		private Printer $printer = new Printer(),
		private Dumper $dumper = new Dumper()
	) {
		$phpFile->setStrictTypes()->addNamespace( $this->setNamespace()->getNamespace() );
	}

	public static function subscribeForImport( bool $addUseStatement = true ): Closure {
		$previousSubscription   = self::$subscribeImports;
		self::$subscribeImports = $addUseStatement;

		return static fn() => self::$subscribeImports = $previousSubscription;
	}

	public static function isSubscribedForImport(): bool {
		return self::$subscribeImports;
	}

	/**
	 * @param array<array-key,mixed> $array
	 * @param-out array<mixed,mixed> $array
	 */
	public function set( array &$array, string $key, mixed $value ): void {
		$keys  = explode( '.', $key );
		$index = $key;

		foreach ( $keys as $i => $key ) {
			$this->maybeAddUseOf( $key );

			if ( count( $keys ) === 1 ) {
				$index = $key;

				break;
			}

			unset( $keys[ $i ] );

			if ( is_array( $array ) ) {
				is_array( $array[ $key ] ?? null ) || $array[ $key ] = array();

				$array = &$array[ $key ];
			}
		}

		$array[ $index ] = $value;
	}

	public function print(): string {
		$headers = $this->printer->printFile( $this->phpFile );
		$content = $this->getContent();
		$export  = $this->export( $content );

		return "{$headers}\n" . Strings::normalize( "\n{$export};" );
	}

	protected function export( mixed &$content, int $level = 0, int $column = 0 ): string {
		return match ( true ) {
			is_array( $content )  => $this->exportArray( $content, $level, $column ),
			is_string( $content ) => $this->exportString( $content ),
			default               => $this->dumper->dump( $content )
		};
	}

	public function exportString( string $content ): string {
		return ( ( $alias = $this->resolveImports( $content ) ) !== $content )
			? (string) new Literal( $alias )
			: $this->dumper->dump( $content );
	}

	/** Sets previously added array key as parent for nested array key/value pair. */
	public function childOf( string|int $parentKey ): static {
		$this->parentKey = $parentKey;

		return $this;
	}

	public function addContent( string|int $key, mixed $value ): static {
		$parentKey = ( $this->parentKey ?? null );
		$array     = $this->content ?? array();

		unset( $this->parentKey );

		if ( ! $parentKey ) {
			$this->content[ $key ] = $value;

			return $this;
		}

		$this->set( $array, "{$parentKey}.{$key}", $value );

		$this->content = $array;

		return $this;
	}

	/** @param string|array{0:string,1:string} $value */
	public function addCallable( string|int $key, string|array $value ): static {
		[$fqcn, $methodName] = match ( true ) {
			is_string( $value ) => explode( separator: '::', string: $value, limit: 2 ),
			default             => $value,
		};

		$this->maybeAddUseOf( $fqcn );

		return $this->addContent( $key, array( $fqcn, $methodName ) );
	}

	/** @return mixed[] */
	public function getContent(): array {
		return $this->content;
	}

	protected function getAliasOf( string $import ): string {
		if ( ! in_array( $import, $uses = $this->getNamespace()->getUses(), strict: true ) ) {
			return $import;
		}

		return ( $alias = array_search( $import, $uses, strict: true ) ) ? "{$alias}::class" : $import;
	}

	protected function resolveImports( string $content ): string {
		return self::isSubscribedForImport() ? $this->getAliasOf( $content ) : $content;
	}

	private function maybeAddUseOf( string $key ): void {
		self::isSubscribedForImport()
			&& Helpers::isNamespaceIdentifier( $key )
			&& $this->addUseStatementOf( $key );
	}
}
