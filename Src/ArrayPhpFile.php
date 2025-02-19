<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator;

use Closure;
use ReflectionFunction;
use Nette\Utils\Strings;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Printer;
use Nette\PhpGenerator\PhpNamespace;
use TheWebSolver\Codegarage\Generator\Traits\ArrayExport;
use TheWebSolver\Codegarage\Generator\Traits\ImportResolver;

class ArrayPhpFile {
	use ArrayExport, ImportResolver;

	/** @var mixed[] */
	private array $content = array();
	private string|int $parentKey;

	private static bool $subscribeImports = true;

	public function __construct(
		public readonly PhpFile $phpFile = new PhpFile(),
		private Printer $printer = new Printer(),
		private Dumper $dumper = new Dumper(),
		PhpNamespace $namespace = null
	) {
		$phpFile->setStrictTypes()->addNamespace( $this->setNamespace( $namespace )->getNamespace() );
	}

	public static function subscribeForImport( bool $addUseStatement = true ): Closure {
		$previousSubscription   = self::$subscribeImports;
		self::$subscribeImports = $addUseStatement;

		return static fn() => self::$subscribeImports = $previousSubscription;
	}

	public static function isSubscribedForImport(): bool {
		return self::$subscribeImports;
	}

	/** @return mixed[] */
	public function getContent(): array {
		return $this->content;
	}

	public function getAliasOf( string $import ): string {
		return match ( true ) {
			default => $import,
			! is_null( $classAlias = $this->getAliasBy( $import, type: PhpNamespace::NAME_NORMAL ) )
				=> $classAlias ? "{$classAlias}::class" : $import,
			! is_null( $funcAlias = $this->getAliasBy( $import, type: PhpNamespace::NAME_FUNCTION ) )
				=> $funcAlias ?: $import,
		};
	}

	public function print(): string {
		$content = $this->getContent();
		$print   = $this->printer->printFile( $this->phpFile )
			. static::CHARACTER_NEWLINE
			. Strings::normalize( $this->export( $content ) ) . ';';

		$this->flushArrayExport();

		return $print;
	}

	/** Sets the parent key for creating multi-dimensional array. */
	public function childOf( string|int $parentKey ): static {
		$this->parentKey = $parentKey;

		return $this;
	}

	public function addContent( string|int $key, mixed $value ): static {
		$this->set( $this->content, $this->maybeWithParent( $key ), $value );

		return $this;
	}

	/** @param string|array{0:string,1:string}|Closure $value Only static method's first-class callable is supported. */
	public function addCallable( string|int $key, string|array|Closure $value ): static {
		$callable = match ( true ) {
			is_string( $value )       => $this->normalizeStringCallable( $value ),
			$value instanceof Closure => $this->normalizeFirstClassCallable( $value ),
			default                   => $this->normalizeArrayCallable( $value ),
		};

		return $this->addContent( $key, $callable );
	}

	/**
	 * @param array<array-key,mixed> $array
	 * @param-out array<mixed,mixed> $array
	 */
	protected function set( array &$array, string $key, mixed $value ): void {
		$keys  = explode( '.', $key );
		$index = $key;

		foreach ( $keys as $i => $key ) {
			$this->maybeImport( $key );

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

	protected function export( mixed &$content ): string {
		return match ( true ) {
			is_array( $content )  => $this->exportArray( $content ),
			is_string( $content ) => $this->exportString( $content ),
			default               => $this->exportMixed( $content )
		};
	}

	final protected function exportMixed( mixed $content ): string {
		return $this->dumper->dump( $content );
	}

	protected function exportString( string $content ): string {
		$alias = self::isSubscribedForImport() ? $this->getAliasOf( $content ) : $content;

		return match ( true ) {
			$alias === $content            => $this->exportMixed( $alias ),
			$this->isClassString( $alias ) => $alias,
			default                        =>  "{$alias}(...)"
		};
	}

	private function maybeWithParent( string|int $key ): string {
		$parentKey         = ( $this->parentKey ?? null );
		$parentKey && $key = "{$parentKey}.{$key}";

		unset( $this->parentKey );

		return (string) $key;
	}

	private function isClassString( string $content ): bool {
		return str_contains( haystack: $content, needle: '::' );
	}

	/** @return string|string[] */
	private function normalizeStringCallable( string $value ): string|array {
		if ( ! $this->isClassString( $value ) ) {
			return $value;
		}

		[$fqcn] = $callable = explode( separator: '::', string: $value, limit: 2 );

		$this->maybeImport( $fqcn );

		return $callable;
	}

	/** @return string|string[] */
	private function normalizeFirstClassCallable( Closure $value ): string|array {
		$reflection       = new ReflectionFunction( $value );
		$funcOrMethodName = $reflection->getShortName();
		$lateBindingClass = $reflection->getClosureCalledClass();

		if ( $lateBindingClass ) {
			$this->maybeImport( $fqcn = $lateBindingClass->name );

			return array( $fqcn, $funcOrMethodName );
		}

		if ( $reflection->getNamespaceName() ) {
			$this->maybeImport( $funcOrMethodName = $reflection->name, type: PhpNamespace::NAME_FUNCTION );
		}

		return $funcOrMethodName;
	}

	/**
	 * @param array{0:string,1:string} $value
	 * @return array{0:string,1:string}
	 */
	private function normalizeArrayCallable( array $value ): array {
		$this->maybeImport( key: $value[0], type: PhpNamespace::NAME_NORMAL );

		return $value;
	}

	private function getAliasBy( string $import, string $type ): string|false|null {
		return in_array( $import, $imports = $this->getNamespace()->getUses( of: $type ), strict: true )
			? ( ( $alias = array_search( $import, $imports, strict: true ) ) ? (string) $alias : false )
			: null;
	}

	/** @param PhpNamespace::NAME* $type */
	private function maybeImport( string $key, string $type = null ): void {
		self::isSubscribedForImport()
			&& Helpers::isNamespaceIdentifier( $key )
			&& $this->addUseStatementOf( $key, $type ?? PhpNamespace::NAME_NORMAL );
	}
}
