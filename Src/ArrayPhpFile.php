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
use TheWebSolver\Codegarage\Generator\Traits\ArrayExport;
use TheWebSolver\Codegarage\Generator\Helper\ImportBuilder;

class ArrayPhpFile {
	use ArrayExport;

	/** @var non-empty-string */
	protected const PARENT_INDICES_SEPARATOR = '.';
	private const NON_IMPORTABLE_ITEM        = 'Impossible to find alias of non-import(able) item.';
	private const IMPORTABLE_ITEM_DEFAULTS   = array(
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
	 * @param string|array{0:string,1:string}|Closure $item Other content or callable item.
	 * @throws OutOfBoundsException When provided $item is not import(able).
	 */
	public function getAliasOf( string|array|Closure $item ): string {
		$import = $this->normalizeCallable( $item, onlyImportable: true );

		return match ( true ) {
			default =>  throw new OutOfBoundsException( self::NON_IMPORTABLE_ITEM ),
			! is_null( $alias = $this->getGlobalScope( $import ) )              => "{$alias}::class",
			! is_null( $alias = $this->using( $import )?->getFormattedAlias() ) => $alias,
			$import === $item                                                   => $import,
		};
	}

	public function print(): string {
		$content = $this->getContent();
		$print   = $this->printer->printFile( $this->phpFile ) . static::CHARACTER_NEWLINE;

		foreach ( $this->nonNamespacedClasses as $classname ) {
			$print .= "use {$classname};\n" . static::CHARACTER_NEWLINE;
		}

		$print .= static::CHARACTER_NEWLINE . Strings::normalize( $this->export( $content ) ) . ';';

		$this->flushArrayExport();

		return $print;
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
			$this->callableBeingAdded() && $this->using( $key )?->import();

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
		return self::isSubscribedForImport() && Helpers::isNamespaceIdentifier( $item )
			? new ImportBuilder( $item, $this->namespace )
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

	final protected function setGlobalScope( string $classname ): bool {
		return $this->entitledForGlobalScope( $name = ltrim( $classname, characters: '\\' ) )
			&& $this->nonNamespacedClasses[ $name ] = $name;
	}

	final protected function getGlobalScope( string $classname ): ?string {
		return $this->nonNamespacedClasses[ ltrim( $classname, characters: '\\' ) ] ?? null;
	}

	/**
	 * @param array{0:string,1:string} $value
	 * @return array{0:string,1:string}
	 */
	private function normalizeArrayCallable( array $value ): array {
		if ( ! $this->callableBeingAdded() ) {
			return $value;
		}

		$this->setGlobalScope( $value[0] ) || $this->using( $value[0] )?->import();

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
		$reflection = new ReflectionFunction( $value );

		return $this->toLateBindingClassCallableFrom( $reflection )
			?? $this->toFunctionCallableFrom( $reflection );
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
			&& $this->using( $reflection->getName() )?->ofType( PhpNamespace::NAME_FUNCTION )->import();

		return $reflection->getName();
	}

	private function entitledForGlobalScope( string $name ): bool {
		return ! str_contains( $name, needle: '\\' )
			&& ! in_array( $name, $this->nonNamespacedClasses, strict: true );
	}

	/** @param array{callable:bool,beingAdded:bool} $options */
	private function withImportableItem( array $options ): static {
		$this->currentItem = array( ...$this->currentItem, ...$options );

		return $this;
	}

	private function callableBeingAdded(): bool {
		return $this->currentItem['callable'] && $this->currentItem['beingAdded'];
	}
}
