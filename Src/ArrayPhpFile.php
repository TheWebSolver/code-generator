<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator;

use Closure;
use ReflectionFunction;
use Nette\Utils\Strings;
use OutOfBoundsException;
use ArrayObject as PhpGlobal;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Printer;
use Nette\PhpGenerator\PhpNamespace;
use TheWebSolver\Codegarage\Generator\Traits\ArrayExport;
use TheWebSolver\Codegarage\Generator\Helper\ImportBuilder;

class ArrayPhpFile {
	use ArrayExport;

	/** @var non-empty-string */
	protected const PARENT_INDICES_SEPARATOR = '.';
	private const NON_IMPORTABLE_ITEM        = 'Impossible to find alias of non-import(able) item: "%s".';
	private const IMPORTABLE_ITEM_DEFAULTS   = array(
		'callable'   => false,
		'beingAdded' => false,
	);

	/** @var mixed[] */
	private array $content = array();
	private string|int $parentKey;
	private static bool $subscribeImports = true;
	/** @var array{callable:bool,beingAdded:bool} */
	private array $currentItem = self::IMPORTABLE_ITEM_DEFAULTS;

	/** @param PhpGlobal<PhpNamespace::NAME_*,array<string,string>> $globalImports */
	public function __construct(
		private PhpFile $phpFile = new PhpFile(),
		private Printer $printer = new Printer(),
		private Dumper $dumper = new Dumper(),
		private PhpNamespace $namespace = new PhpNamespace( '' ),
		private PhpGlobal $globalImports = new PhpGlobal( flags: PhpGlobal::STD_PROP_LIST ),
	) {
		$phpFile->setStrictTypes()->addNamespace( $namespace );
	}

	final public static function subscribeForImport( bool $addUseStatement = true ): Closure {
		$previousSubscription   = self::$subscribeImports;
		self::$subscribeImports = $addUseStatement;

		return static fn() => self::$subscribeImports = $previousSubscription;
	}

	final public static function isSubscribedForImport(): bool {
		return self::$subscribeImports;
	}

	/** @return mixed[] */
	public function getContent(): array {
		return $this->content;
	}

	/**
	 * @param string|array{0:string,1:string}|Closure $item One of the `ImportBuilder::IMPORTABLE_TYPES` item.
	 * @param PhpNamespace::NAME_*                    $type The item type.
	 * @throws OutOfBoundsException When provided $item is not import(able).
	 */
	public function getAliasOf( string|array|Closure $item, string $type = PhpNamespace::NAME_NORMAL ): string {
		$import = $this->normalizeCallable( $item, onlyImportable: true );
		$alias  = $this->using( $import )?->ofType( $type )->getAlias();

		return match ( true ) {
			default             => throw new OutOfBoundsException( sprintf( self::NON_IMPORTABLE_ITEM, $import ) ),
			! is_null( $alias ) => $alias,
			$import === $item   => $import,
		};
	}

	public function print(): string {
		$arrayContent        = $this->getContent();
		$globalImport        = '';
		$fileHeader          = $this->printer->printFile( $this->phpFile );
		[$phpTag, $nsImport] = explode( separator:";\n", string: $fileHeader, limit: 2 );
		$nsImport            = implode( static::CHARACTER_NEWLINE, explode( "\n", $nsImport ) );

		foreach ( $this->globalImports->getArrayCopy() as $type => $imports ) {
			$globalImport .= array_reduce( $imports, $this->toUseStatement( ... ), initial: $globalImport );
		}

		$fileContent  = static::CHARACTER_NEWLINE . static::CHARACTER_NEWLINE . $globalImport . $nsImport;
		$fileContent .= static::CHARACTER_NEWLINE . Strings::normalize( $this->export( $arrayContent ) );

		$this->flushArrayExport();

		return "{$phpTag};{$fileContent};";
	}

	/**
	 * Imports either global class or namespaced class, function, constant etc.
	 * It will not import names that is globally scoped except only classname.
	 *
	 * @param PhpNamespace::NAME_* $type The name type that is being imported. Defaults to classname.
	 */
	public function importFrom( string $name, string $type = PhpNamespace::NAME_NORMAL ): static {
		$this->using( $name )?->ofType( $type )->import();

		return $this;
	}

	/** Sets the parent key(s) to create multi-dimensional array for the content being added. */
	public function childOf( string|int $firstLevelIndex, string|int ...$subLevelIndices ): static {
		$this->parentKey                      = $firstLevelIndex;
		$subLevelIndices && $this->parentKey .= static::PARENT_INDICES_SEPARATOR
		. implode( separator: static::PARENT_INDICES_SEPARATOR, array: $subLevelIndices );

		return $this;
	}

	public function addContent( string|int $key, mixed $value ): static {
		$this->set( $this->content, $this->maybeWithParentIndices( $key ), $value );

		return $this;
	}

	/** @param string|array{0:string,1:string}|Closure $value Only static method's first-class callable is supported. */
	public function addImportableContent( string|int $key, string|array|Closure $value ): static {
		return $this->asImportableItem()
			->addContent( $key, value: $this->normalizeCallable( $value, onlyImportable: false ) )
			->resetImportableItem();
	}

	final protected function asImportableItem( bool $callable = true, bool $beingAdded = true ): static {
		return $this->withImportableItem( compact( 'callable', 'beingAdded' ) );
	}

	final protected function resetImportableItem(): static {
		return $this->withImportableItem( self::IMPORTABLE_ITEM_DEFAULTS );
	}

	/**
	 * @param array<array-key,mixed> $array
	 * @param-out array<mixed,mixed> $array
	 */
	protected function set( array &$array, string $key, mixed $value ): void {
		$keys  = explode( static::PARENT_INDICES_SEPARATOR, $key );
		$index = $key;

		foreach ( $keys as $i => $key ) {
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
		if ( ! self::isSubscribedForImport() ) {
			return $this->exportMixed( $content );
		}

		$alias = ImportBuilder::formattedAliasOf( $content, $this->globalImports, $this->namespace ) ?? $content;

		return match ( true ) {
			$alias === $content            => $this->exportMixed( $alias ),
			$this->isClassString( $alias ) => $alias,
			default                        =>  "{$alias}(...)"
		};
	}

	protected function maybeWithParentIndices( string|int $key ): string {
		$parentKey         = ( $this->parentKey ?? null );
		$parentKey && $key = $parentKey . static::PARENT_INDICES_SEPARATOR . $key;

		unset( $this->parentKey );

		return (string) $key;
	}

	protected function isClassString( string $content ): bool {
		return str_contains( haystack: $content, needle: '::' );
	}

	protected function using( string $item ): ?ImportBuilder {
		return self::isSubscribedForImport()
			? new ImportBuilder( $item, $this->namespace, $this->globalImports )
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
		$this->callableBeingAdded() && $this->importFrom( name: $value[0] );

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

		return $this->toLateBindingClassCallableFrom( $ref ) ?? $this->toFunctionCallableFrom( $ref );
	}

	/** @return ?array{0:string,1:string} */
	private function toLateBindingClassCallableFrom( ReflectionFunction $reflection ): ?array {
		return ( $staticClassName = $reflection->getClosureCalledClass()?->getName() )
			? $this->normalizeArrayCallable( array( $staticClassName, $reflection->getShortName() ) )
			: null;
	}

	private function toFunctionCallableFrom( ReflectionFunction $reflection ): string {
		$reflection->getNamespaceName()
			&& $this->callableBeingAdded()
			&& $this->importFrom( $name = $reflection->getName(), type: PhpNamespace::NAME_FUNCTION );

		return $name ?? $reflection->getName();
	}

	/** @param array{callable:bool,beingAdded:bool} $options */
	private function withImportableItem( array $options ): static {
		$this->currentItem = array( ...$this->currentItem, ...$options );

		return $this;
	}

	private function callableBeingAdded(): bool {
		return $this->currentItem['callable'] && $this->currentItem['beingAdded'];
	}

	private function toUseStatement( string $statement, string $import ): string {
		return $statement .= "use {$import};" . static::CHARACTER_NEWLINE;
	}
}
