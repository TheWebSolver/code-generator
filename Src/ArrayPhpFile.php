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
		$alias = $this->resolveImports( $content );

		return match ( true ) {
			$alias === $content            => $this->dumper->dump( $content ),
			$this->isClassString( $alias ) => $alias,
			default                        =>  "{$alias}(...)"
		};
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
			is_string( $key ) && $this->maybeAddUseOf( $key );

			$this->content[ $key ] = $value;

			return $this;
		}

		$this->set( $array, "{$parentKey}.{$key}", $value );

		$this->content = $array;

		return $this;
	}

	/** @param string|array{0:string,1:string}|Closure $value Only static method's first-class callable is supported. */
	public function addCallable( string|int $key, string|array|Closure $value ): static {
		$value = match ( true ) {
			is_string( $value )       => $this->resolveStringCallable( $value ),
			$value instanceof Closure => $this->resolveFirstClassCallable( $value ),
			default                   => $value,
		};

		return $this->addContent( $key, $value );
	}

	private function isClassString( string $content ): bool {
		return str_contains( haystack: $content, needle: '::' );
	}

	/** @return string|string[] */
	private function resolveStringCallable( string $value ): string|array {
		if ( ! $this->isClassString( $value ) ) {
			return $value;
		}

		[$fqcn, $methodName] = explode( separator: '::', string: $value, limit: 2 );

		$this->maybeAddUseOf( $fqcn );

		return array( $fqcn, $methodName );
	}

	/** @return string|string[] */
	private function resolveFirstClassCallable( Closure $value ): string|array {
		$reflection       = new ReflectionFunction( $value );
		$funcOrMethodName = $reflection->getShortName();
		$lateBindingClass = $reflection->getClosureCalledClass();

		if ( $lateBindingClass ) {
			$this->maybeAddUseOf( $fqcn = $lateBindingClass->name );

			return array( $fqcn, $funcOrMethodName );
		}

		if ( $reflection->getNamespaceName() ) {
			$this->maybeAddUseOf( $funcOrMethodName = $reflection->name, type: PhpNamespace::NAME_FUNCTION );
		}

		return $funcOrMethodName;
	}

	/** @return mixed[] */
	public function getContent(): array {
		return $this->content;
	}

	protected function getAliasOf( string $import ): string {

		if ( in_array( $import, $classImports = $this->getNamespace()->getUses(), strict: true ) ) {
			return ( $alias = array_search( $import, $classImports, strict: true ) )
				? "{$alias}::class"
				: $import;
		}

		$funcImports = $this->getNamespace()->getUses( PhpNamespace::NAME_FUNCTION );

		if ( in_array( $import, $funcImports, strict: true ) ) {
			return ( $alias = array_search( $import, $funcImports, strict: true ) ) ? (string) $alias : $import;
		}

		return $import;
	}

	protected function resolveImports( string $content ): string {
		return self::isSubscribedForImport() ? $this->getAliasOf( $content ) : $content;
	}

	/** @param PhpNamespace::NAME* $type */
	private function maybeAddUseOf( string $key, string $type = PhpNamespace::NAME_NORMAL ): void {
		self::isSubscribedForImport()
			&& Helpers::isNamespaceIdentifier( $key )
			&& $this->addUseStatementOf( $key, $type );
	}
}
