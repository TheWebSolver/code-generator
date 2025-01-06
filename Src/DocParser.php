<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator;

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
	protected ConstExprParser $constParser;
	protected PhpDocParser $docParser;
	protected TypeParser $typeParser;
	protected Lexer $lexer;

	public function __construct() {
		$this->lexer       = new Lexer();
		$this->constParser = new ConstExprParser();
		$this->typeParser  = new TypeParser( $this->constParser );
		$this->docParser   = new PhpDocParser( $this->typeParser, $this->constParser );
	}

	/** @throws LogicException When method doesn't have docBlock. */
	public static function fromMethod( ReflectionMethod $method ): PhpDocNode {
		$parser = new self();

		return ! is_string( $doc = $method->getDocComment() )
			? throw new LogicException( sprintf( 'Method "%s" does not have doc block.', $method ) )
			: $parser->docParser->parse( new TokenIterator( $parser->lexer->tokenize( $doc ) ) );
	}

	public static function fromDocBlock( string $content ): PhpDocNode {
		$parser = new self();

		return $parser->docParser->parse( new TokenIterator( $parser->lexer->tokenize( $content ) ) );
	}

	/** @return TextNode[] */
	public static function getTextNodes( PhpDocNode $nodes ): array {
		return array_values(
			array_filter(
				array: $nodes->children,
				callback: static fn( Node $node ): bool => $node instanceof TextNode && '' !== "$node"
			)
		);
	}
}
