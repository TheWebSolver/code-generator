<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator;

use Closure;
use ReflectionFunction;
use Nette\Utils\Strings;
use OutOfBoundsException;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Printer;
use Nette\PhpGenerator\PhpNamespace;
use TheWebSolver\Codegarage\Generator\Helper\UseBuilder;
use TheWebSolver\Codegarage\Generator\Traits\ArrayExport;

class ArrayPhpFile {
	use ArrayExport;

	private const NON_IMPORTABLE_ITEM      = 'Impossible to find alias of non-imported item.';
	private const IMPORTABLE_ITEM_DEFAULTS = array(
		'callable'   => false,
		'beingAdded' => false,
	);

	/** @var mixed[] */
	private array $content = array();
	private string|int $parentKey;
	private static bool $subscribeImports = true;
	/** @var array<string,string> */
	private array $nonNamespacedClasses = array();
	/** @var array{callable:bool,beingAdded:bool} */
	private array $currentItem = self::IMPORTABLE_ITEM_DEFAULTS;

	public function __construct(
		private PhpFile $phpFile = new PhpFile(),
		private Printer $printer = new Printer(),
		private Dumper $dumper = new Dumper(),
		private PhpNamespace $namespace = new PhpNamespace( '' )
	) {
		$phpFile->setStrictTypes()->addNamespace( $namespace );
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

	/**
	 * @param string|array{0:string,1:string}|Closure $item Other content or callable item.
	 * @throws OutOfBoundsException When provided $item is not imported.
	 */
	public function getAliasOf( string|array|Closure $item ): string {
		$import = $this->normalizeCallable( $item, onlyImportable: true );

		if ( $alias = $this->using( $import )?->getFormattedAlias() ) {
			return $alias;
		}

		if ( $alias = ( $this->nonNamespacedClasses[ $import ] ?? null ) ) {
			return "{$alias}::class";
		}

		return $import === $item ? $import : throw new OutOfBoundsException( self::NON_IMPORTABLE_ITEM );
	}

	public function print(): string {
		$content = $this->getContent();
		$print   = $this->printer->printFile( $this->phpFile ) . PHP_EOL;

		foreach ( $this->nonNamespacedClasses as $classname ) {
			$print .= "use {$classname};" . PHP_EOL;
		}

		$print .= PHP_EOL . Strings::normalize( $this->export( $content ) ) . ';';

		$this->flushArrayExport();

		return $print;
	}

	/** Sets the parent key for creating multi-dimensional array. */
	public function childOf( string|int $parentKey, string|int ...$keys ): static {
		$this->parentKey           = $parentKey;
		$keys && $this->parentKey .= '.' . implode( separator: '.', array: $keys );

		return $this;
	}

	public function addContent( string|int $key, mixed $value ): static {
		$this->set( $this->content, $this->maybeWithParent( $key ), $value );

		return $this;
	}

	/** @param string|array{0:string,1:string}|Closure $value Only static method's first-class callable is supported. */
	public function addImportableContent( string|int $key, string|array|Closure $value ): static {
		return $this->asImportableItem()
			->addContent( $key, value: $this->normalizeCallable( $value, onlyImportable: false ) )
			->resetImportableItem();
	}

	final protected function asImportableItem( bool $callable = true, bool $beingAdded = true ): static {
		$this->currentItem = compact( 'callable', 'beingAdded' );

		return $this;
	}

	final protected function resetImportableItem(): static {
		$this->currentItem = self::IMPORTABLE_ITEM_DEFAULTS;

		return $this;
	}

	/**
	 * @param array<array-key,mixed> $array
	 * @param-out array<mixed,mixed> $array
	 */
	protected function set( array &$array, string $key, mixed $value ): void {
		$keys  = explode( '.', $key );
		$index = $key;

		foreach ( $keys as $i => $key ) {
			$this->currentItem['callable'] && $this->using( $key )?->import();

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

	protected function maybeWithParent( string|int $key ): string {
		$parentKey         = ( $this->parentKey ?? null );
		$parentKey && $key = "{$parentKey}.{$key}";

		unset( $this->parentKey );

		return (string) $key;
	}

	protected function isClassString( string $content ): bool {
		return str_contains( haystack: $content, needle: '::' );
	}

	protected function using( string $item ): ?UseBuilder {
		return self::isSubscribedForImport()
			? ( Helpers::isNamespaceIdentifier( $item ) ? ( new UseBuilder( $item, $this->namespace ) ) : null )
			: null;
	}

	/**
	 * @param string|array{0:string,1:string}|Closure $value
	 * @return ($onlyImportable is true ? string : string|array{0:string,1:string})
	 */
	protected function normalizeCallable( string|array|Closure $value, bool $onlyImportable ): string|array {
		$callable = match ( true ) {
			is_string( $value )       => $this->normalizeStringCallable( $value ),
			$value instanceof Closure => $this->normalizeFirstClassCallable( $value ),
			default                   => $this->normalizeArrayCallable( $value ),
		};

		return is_string( $callable ) ? $callable : ( $onlyImportable ? $callable[0] : $callable );
	}

	/**
	 * @param array{0:string,1:string} $value
	 * @return array{0:string,1:string}
	 */
	private function normalizeArrayCallable( array $value ): array {
		if ( ! $this->currentItem['beingAdded'] ) {
			return $value;
		}

		$this->using( $classname = $value[0] )?->import();

		if ( $this->shouldRegisterAsGlobalScopeClass( $classname ) ) {
			$this->nonNamespacedClasses[ $classname ] = $classname;
		}

		return $value;
	}

	/** @return string|array{0:string,1:string} */
	private function normalizeStringCallable( string $value ): string|array {
		if ( ! $this->isClassString( $value ) ) {
			return $value;
		}

		[$fqcn, $methodName] = explode( separator: '::', string: $value, limit: 2 );

		return $this->normalizeArrayCallable( array( $fqcn, $methodName ) );
	}

	/** @return string|array{0:string,1:string} */
	private function normalizeFirstClassCallable( Closure $value ): string|array {
		$ref = new ReflectionFunction( $value );

		if ( $lateBindingClass = $ref->getClosureCalledClass() ) {
			$name = $lateBindingClass->name;

			if ( $this->currentItem['beingAdded'] ) {
				$this->using( $name )?->import();

				if ( $this->shouldRegisterAsGlobalScopeClass( $name ) ) {
					$this->nonNamespacedClasses[ $name ] = $name;
				}
			}

			return array( $name, $ref->getShortName() );
		}

		if ( $ref->getNamespaceName() ) {
			$funcOrMethodName = $ref->name;

			$this->currentItem['beingAdded']
				&& $this->using( $funcOrMethodName )?->ofType( PhpNamespace::NAME_FUNCTION )->import();
		}

		return $funcOrMethodName ?? $ref->name;
	}

	private function shouldRegisterAsGlobalScopeClass( string $name ): bool {
		return ! str_contains( $name, needle: '\\' )
			&& ! in_array( $name, $this->nonNamespacedClasses, strict: true );
	}
}
