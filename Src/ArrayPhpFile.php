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

	public function print(): string {
		$content = $this->getContent();

		return $this->printer->printFile( $this->phpFile )
			. $this->whitespaceCharOf( self::CHARACTER_NEWLINE )
			. Strings::normalize( $this->export( $content ) ) . ';';
	}

	/** Sets the parent key for creating multi-dimensional array. */
	public function childOf( string|int $parentKey ): static {
		$this->parentKey = $parentKey;

		return $this;
	}

	public function addContent( string|int $key, mixed $value ): static {
		$parentKey = ( $this->parentKey ?? null );

		unset( $this->parentKey );

		if ( ! $parentKey ) {
			is_string( $key ) && $this->maybeImport( $key );

			$this->content[ $key ] = $value;

			return $this;
		}

		$this->set( $this->content, "{$parentKey}.{$key}", $value );

		return $this;
	}

	/** @param string|array{0:string,1:string}|Closure $value Only static method's first-class callable is supported. */
	public function addCallable( string|int $key, string|array|Closure $value ): static {
		$callable = match ( true ) {
			is_string( $value )       => $this->resolveStringCallable( $value ),
			$value instanceof Closure => $this->resolveFirstClassCallable( $value ),
			default                   => $value,
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

	protected function export( mixed &$content, int $level = 0, int $column = 0 ): string {
		return match ( true ) {
			is_array( $content )  => $this->exportArray( $content, $level, $column ),
			is_string( $content ) => $this->exportString( $content ),
			default               => $this->dumper->dump( $content )
		};
	}

	protected function exportString( string $content ): string {
		$alias = $this->resolveImports( $content );

		return match ( true ) {
			$alias === $content            => $this->dumper->dump( $alias ),
			$this->isClassString( $alias ) => $alias,
			default                        =>  "{$alias}(...)"
		};
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

		$this->maybeImport( $fqcn );

		return array( $fqcn, $methodName );
	}

	/** @return string|string[] */
	private function resolveFirstClassCallable( Closure $value ): string|array {
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

	protected function getAliasOf( string $import ): string {
		if ( in_array( $import, $classImports = $this->getNamespace()->getUses(), strict: true ) ) {
			return ( $alias = array_search( $import, $classImports, strict: true ) )
				? "{$alias}::class"
				: $import;
		}

		$funcImports = $this->getNamespace()->getUses( of: PhpNamespace::NAME_FUNCTION );

		if ( in_array( $import, $funcImports, strict: true ) ) {
			return ( $alias = array_search( $import, $funcImports, strict: true ) ) ? (string) $alias : $import;
		}

		return $import;
	}

	protected function resolveImports( string $content ): string {
		return self::isSubscribedForImport() ? $this->getAliasOf( $content ) : $content;
	}

	/** @param PhpNamespace::NAME* $type */
	private function maybeImport( string $key, string $type = null ): void {
		self::isSubscribedForImport()
			&& Helpers::isNamespaceIdentifier( $key )
			&& $this->addUseStatementOf( $key, $type ?? PhpNamespace::NAME_NORMAL );
	}
}
