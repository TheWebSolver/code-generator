<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use LogicException;

/** Souped-up version of Nette Framework: `Dumper::dumpArray()` to make array export portable. */
trait ArrayExport {
	/** @var array{0:string,1:string} Language construct as either full **array()** or shorthand **[]**. */
	public const ARRAY_LANGUAGE_CONSTRUCT = array( 'array(', ')' );

	/** @var int Length of full ***array()*** --> **7** + **2** <-- space between opening & closing parenthesis. */
	public const ARRAY_LANGUAGE_CONSTRUCT_LENGTH = 9;
	/** @var int The indentation size for the indentation style. */
	public const ARRAY_INDENT_LENGTH = 4;
	/** @var int The maximum depth a multi-dimensional array can be. */
	public const ARRAY_DEPTH_LENGTH = 50;
	/** @var int The maximum stringified length of an array after which it should be wrapped to a newline. */
	public const ARRAY_WRAP_LENGTH = 120;

	/** @var string The indentation style character as either **tab** or **space**. */
	public const CHARACTER_INDENT_STYLE = "\t";
	/** @var string The newline character. */
	public const CHARACTER_NEWLINE = "\n";

	private const DEFAULT_ARRAY_INFO = array(
		'level'   => 0,
		'column'  => 0,
		'count'   => 0,
		'parents' => array(),
	);

	/** @var array{level:int,column:int,count:int,parents:array<mixed[]>} */
	private array $currentArrayInfo = self::DEFAULT_ARRAY_INFO;

	/** Allows exporting content other than ***array*** type. */
	abstract protected function export( mixed &$content ): string;

	/**
	 * Flushes the artefact that was created during the export process.
	 * It must be called after exhibit finishes the export process.
	 *
	 * @example Usage
	 * ```php
	 * class ContentExporter {
	 *  use ArrayExport;
	 *
	 *   protected function export(mixed &$content): string {
	 *    if (is_array($content)) {
	 *     return $this->exportArray($content);
	 *    }
	 *    // Export other $content type.
	 *   }
	 *
	 *  public function exportContent(mixed $content): string {
	 *   $string = $this->export($content);
	 *   $this->flushArrayExport();
	 *   return $string;
	 *  }
	 * }
	 *
	 * $stringifiedContent = (new ContentExporter)->exportContent($contentToBeStringified);
	 * ```
	 */
	final protected function flushArrayExport(): void {
		$this->currentArrayInfo = self::DEFAULT_ARRAY_INFO;
	}

	protected function withArrayLanguageConstruct( string $content ): string {
		[$arrayOpen, $arrayClose] = self::ARRAY_LANGUAGE_CONSTRUCT;

		return "{$arrayOpen} {$content} {$arrayClose}";
	}

	protected function toSinglelineArray( string &$current, string $key, mixed &$content ): self {
		['level' => $prevLevel, 'column' => $prevColumn] = $this->currentArrayInfo;

		$column   = $prevColumn + strlen( $current );
		$current .= ( '' === $current ? '' : ', ' ) . $key
			. $this->withCurrentArrayInfo( level: 0, column: $column )->export( $content );

		return $this->withCurrentArrayInfo( $prevLevel, $prevColumn );
	}

	protected function toMultilineArray( string &$current, string $key, mixed &$content, string $indent ): self {
		['level' => $prevLevel, 'column' => $prevColumn] = $this->currentArrayInfo;

		$current .= self::CHARACTER_INDENT_STYLE . $key
			. $this->withCurrentArrayInfo( level: $prevLevel + 1, column: strlen( $key ) )->export( $content )
			. ',' . self::CHARACTER_NEWLINE . $indent;

		return $this->withCurrentArrayInfo( $prevLevel, $prevColumn );
	}

	/**
	 * @param mixed[] $content
	 * @throws LogicException When max-depth reached.
	 */
	protected function exportArray( array &$content ): string {
		if ( empty( $content ) ) {
			return 'array()';
		} elseif ( $this->reachedMaxDepthForCurrentArray( $content ) ) {
			throw new LogicException( 'Nesting level too deep or recursive dependency.' );
		}

		[$indent, $singleline, $multiline] = $this->prepareArrayLinesAndIndentFrom( $content );

		foreach ( $content as $index => &$value ) {
			$key = $this->getCurrentArrayKeyWithSeparatorBetween( $index, $content );

			$this->withUpdatedCountOfCurrentArrayIndex( $index )
				->toSinglelineArray( $singleline, $key, $value )
				->toMultilineArray( $multiline, $key, $value, $indent );
		}

		array_pop( $this->currentArrayInfo['parents'] );

		return $this->withArrayLanguageConstruct(
			$this->shouldWrapEachArrayItemOnANewline( $singleline ) ? $multiline : $singleline
		);
	}

	/**
	 * @param mixed[] $content
	 * @return array{0:string,1:string,2:string}
	 */
	private function prepareArrayLinesAndIndentFrom( array $content ): array {
		$this->currentArrayInfo['parents'][] = $content;

		return array(
			$indent     = $this->getCurrentLevelArrayIndent(),
			$singleline = '',
			$multiline  = self::CHARACTER_NEWLINE . $indent,
		);
	}

	private function getCurrentLevelArrayIndent(): string {
		return str_repeat( self::CHARACTER_INDENT_STYLE, times: $this->currentArrayInfo['level'] );
	}

	/** @param mixed[] $value */
	private function getCurrentArrayKeyWithSeparatorBetween( string|int $key, array $value ): string {
		return $this->omitArraySeparatorBetween( $key, $value ) ? '' : $this->export( $key ) . ' => ';
	}

	private function withCurrentArrayInfo( int $level, int $column ): static {
		$this->currentArrayInfo['level']  = $level;
		$this->currentArrayInfo['column'] = $column;

		return $this;
	}

	private function withUpdatedCountOfCurrentArrayIndex( string|int $key ): static {
		$prevCount = $this->currentArrayInfo['count'];

		is_int( $key ) && $this->currentArrayInfo['count'] = max( $key + 1, $prevCount );

		return $this;
	}

	/** @param mixed[] $value */
	private function omitArraySeparatorBetween( string|int $key, array $value ): bool {
		return ( array_is_list( $value ) && $key === $this->currentArrayInfo['count'] ) || is_int( $key );
	}

	private function shouldWrapEachArrayItemOnANewline( string $content ): bool {
		return str_contains( haystack: $content, needle: self::CHARACTER_NEWLINE )
			|| substr_count( $content, needle: '=>' ) > 1
			|| $this->reachedMaxWidthForCurrentArray( $content );
	}

	private function reachedMaxDepthForCurrentArray( mixed $content ): bool {
		['level' => $level, 'parents' => $parents] = $this->currentArrayInfo;

		return $level > self::ARRAY_DEPTH_LENGTH || in_array( $content, $parents, true );
	}

	private function reachedMaxWidthForCurrentArray( string $content ): bool {
		['level' => $level, 'column' => $column] = $this->currentArrayInfo;

		$indentLength = ( $level * self::ARRAY_INDENT_LENGTH ) + self::ARRAY_LANGUAGE_CONSTRUCT_LENGTH;

		return ( $column + strlen( $content ) + $indentLength ) > self::ARRAY_WRAP_LENGTH;
	}
}
