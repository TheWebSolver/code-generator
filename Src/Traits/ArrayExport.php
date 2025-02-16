<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Traits;

use LogicException;

trait ArrayExport {
	final public const ARRAY_CONSTRUCT = array( 'array(', ')' );

	final public const CHARACTER_INDENT_STYLE = 'tabOrSpace';
	final public const CHARACTER_NEWLINE      = 'newline';

	final public const LENGTH_INDENT = 'length';
	final public const LENGTH_DEPTH  = 'depth';
	final public const LENGTH_WRAP   = 'wrap';

	/** @var mixed[] */
	private array $arrayParents = array();
	private int $currentCount   = 0;

	abstract protected function export( mixed &$content, int $level = 0, int $column = 0 ): string;

	/** @param self::LENGTH* $type */
	protected function lengthValueOf( string $type ): int {
		return match ( $type ) {
			self::LENGTH_INDENT => 4,
			self::LENGTH_WRAP   => 120,
			self::LENGTH_DEPTH  => 50
		};
	}

	/** @param self::CHARACTER* $type */
	private function whitespaceCharOf( string $type ): string {
		return match ( $type ) {
			self::CHARACTER_INDENT_STYLE => "\t",
			self::CHARACTER_NEWLINE      => "\n"
		};
	}

	/** @return array{0:string,1:string} */
	protected function arrayConstruct(): array {
		return self::ARRAY_CONSTRUCT;
	}

	protected function arrayIndentForCurrenLevel( int $level ): string {
		return str_repeat( $this->whitespaceCharOf( self::CHARACTER_INDENT_STYLE ), times: $level );
	}

	/** @param mixed[] $content */
	protected function toStringifiedArrayKey( array $content, string|int $index ): string {
		$currentIndex = array_is_list( $content ) && $index === $this->currentCount
			? ''
			: "{$this->export( $index )} => ";

		return $currentIndex;
	}

	protected function withArrayLanguageConstruct( string $content ): string {
		[$arrayOpen, $arrayClose] = $this->arrayConstruct();

		return "{$arrayOpen} {$content} {$arrayClose}";
	}

	protected function updateCurrentCount( string|int $arrayIndex ): self {
		is_int( $arrayIndex ) && $this->currentCount = max( $arrayIndex + 1, $this->currentCount );

		return $this;
	}

	private function toSingleLineArray(
		string &$content,
		string $key,
		int $column,
		mixed &$value
	): self {
		$content .= ( '' === $content ? '' : ', ' ) . $key;
		$content .= $this->export( $value, level: 0, column: $column + strlen( $content ) );

		return $this;
	}

	protected function toMultilineArray(
		string &$content,
		string $key,
		int $level,
		mixed &$value,
		string $indent
	): self {
		$content .= $this->whitespaceCharOf( self::CHARACTER_INDENT_STYLE )
			. $key
			. $this->export( $value, level: $level + 1, column: strlen( $key ) )
			. ",{$this->whitespaceCharOf( self::CHARACTER_NEWLINE )}{$indent}";

		return $this;
	}

	protected function multipleArrayValuesInNewline( string $content, int $column, int $level ): bool {
		return strpos( $content, needle: $this->whitespaceCharOf( self::CHARACTER_NEWLINE ) ) !== false
			|| substr_count( $content, needle: '=>' ) > 1
			|| ( $level * $this->lengthValueOf( self::LENGTH_INDENT ) + $column + strlen( $content ) + 9 /* array(  ) */ ) > $this->lengthValueOf( self::LENGTH_WRAP );
	}

	/**
	 * @param mixed[] $content
	 * @throws LogicException When max-depth reached.
	 */
	protected function exportArray( array &$content, int $level, int $column ): string {
		if ( empty( $content ) ) {
			return 'array()';

		} elseif ( $level > $this->lengthValueOf( self::LENGTH_DEPTH ) || in_array( $content, $this->arrayParents, true ) ) {
			throw new LogicException( 'Nesting level too deep or recursive dependency.' );
		}

		$indent               = $this->arrayIndentForCurrenLevel( $level );
		$singleline           = '';
		$multiline            = "{$this->whitespaceCharOf( self::CHARACTER_NEWLINE )}{$indent}";
		$this->arrayParents[] = $content;

		foreach ( $content as $index => &$value ) {
			$key = $this->toStringifiedArrayKey( $content, $index );

			$this->updateCurrentCount( $index )
				->toSingleLineArray( $singleline, $key, $column, $value )
				->toMultilineArray( $multiline, $key, $level, $value, $indent );
		}

		array_pop( $this->arrayParents );

		$shouldWrap = $this->multipleArrayValuesInNewline( $singleline, $column, $level );

		return $this->withArrayLanguageConstruct( $shouldWrap ? $multiline : $singleline );
	}
}
