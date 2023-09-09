<?php
/**
 * Doc Generator
 *
 * @package TheWebSolver
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generators;

use LogicException;
use ReflectionMethod;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode as Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode as TextNode;

class DocParser {
	protected ConstExprParser $const_parser;
	protected PhpDocParser $doc_parser;
	protected TypeParser $type_parser;
	protected Lexer $lexer;

	public function __construct() {
		$this->lexer        = new Lexer();
		$this->const_parser = new ConstExprParser();
		$this->type_parser  = new TypeParser( $this->const_parser );
		$this->doc_parser   = new PhpDocParser( $this->type_parser, $this->const_parser );
	}

	/** @throws LogicException When method doesn't have docBlock.  */
	public static function fromMethod( ReflectionMethod $method ): PhpDocNode {
		$parser = new self();

		if ( ! is_string( $doc = $method->getDocComment() ) ) {
			throw new LogicException(
				sprintf( 'Method "%s" does not have doc block.', $method )
			);
		}

		return $parser->doc_parser->parse(
			new TokenIterator( $parser->lexer->tokenize( $doc ) )
		);
	}

	/** @return TextNode[] */
	public static function getTextNodes( PhpDocNode $nodes ): array {
		return array_values(
			array_filter(
				$nodes->children,
				fn( Node $node ): bool => $node instanceof TextNode && '' !== "$node"
			)
		);
	}
}
