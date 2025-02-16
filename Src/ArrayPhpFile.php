<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator;

use Nette\Utils\Strings;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Printer;
use TheWebSolver\Codegarage\Generator\Traits\ArrayExport;

class ArrayPhpFile {
	use ArrayExport;

	/** @var mixed[] */
	private array $content;
	private string|int $parentKey;

	public function __construct(
		public readonly PhpFile $phpFile = new PhpFile(),
		private Printer $printer = new Printer(),
		private Dumper $dumper = new Dumper()
	) {
		$phpFile->setStrictTypes();
	}

	/**
	 * @param array<array-key,mixed> $array
	 * @param-out array<mixed,mixed> $array
	 */
	public function set( array &$array, string $key, mixed $value ): void {
		$keys  = explode( '.', $key );
		$index = $key;

		foreach ( $keys as $i => $key ) {
			$key = $this->aliasIfIsClassname( $key );

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

	private function aliasIfIsClassname( string $value ): string {
		if ( ctype_digit( $value ) ) {
			return $value;
		}

		[$fqcn, $alias] = NamespacedClass::resolveImportFrom( $value );

		$alias && $this->phpFile->addUse( $fqcn, $alias );

		return $fqcn !== $alias ? "{$alias}::class" : $value;
	}

	protected function export( mixed &$content, int $level = 0, int $column = 0 ): string {
		return match ( true ) {
			is_array( $content )  => $this->exportArray( $content, $level, $column ),
			is_string( $content ) => $this->exportString( $content ),
			default               => $this->dumper->dump( $content )
		};
	}

	public function exportString( string $content ): string {
		return str_ends_with( $content, needle: '::class' )
			? (string) ( new Literal( $content ) )
			: $this->dumper->dump( $content );
	}

	/** Sets previously added array key as parent for nested array key/value pair. */
	public function childOf( string|int $parentKey ): static {
		$this->parentKey = $parentKey;

		return $this;
	}

	public function addContent( string|int $key, mixed $value, string|int $index = null ): static {
		$parentKey = ( $this->parentKey ?? null );
		$array     = $this->content ?? array();

		unset( $this->parentKey );

		if ( ! $parentKey ) {
			$this->content[ $index ?? $key ] = $value;

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

		$classname = $this->aliasIfIsClassname( $fqcn );
		$value     = array( $classname, $methodName );

		return $this->addContent( $key, $value, index: $this->aliasIfIsClassname( (string) $key ) );
	}

	/** @return mixed[] */
	public function getContent(): array {
		return $this->content;
	}
}
