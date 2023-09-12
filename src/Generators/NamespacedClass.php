<?php
/**
 * Namespaced class generator.
 *
 * @package TheWebSolver
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generators;

use LogicException;
use ReflectionMethod;
use Nette\Utils\Type;
use ReflectionException;
use ReflectionParameter;
use ReflectionNamedType;
use InvalidArgumentException;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;

class NamespacedClass {
	private PhpNamespace $namespace;
	/** @var string[] */
	private array $implements = array();
	private string $extends   = '';
	private string $name;

	public function __construct( string $namespace ) {
		$this->namespace = new PhpNamespace( self::stripSlashesFrom( $namespace ) );
		$this->name      = self::makeFqcnFor( $this->namespace->getName() );
	}

	public function __toString() {
		return (string) $this->namespace;
	}

	public function namespace(): PhpNamespace {
		return $this->namespace;
	}

	public static function from( string $namespace ): self {
		return new self( $namespace );
	}

	public static function stripSlashesFrom( string $fqcn ): string {
		return trim( $fqcn, '\\' );
	}

	public static function makeFqcnFor( string $name ): string {
		return '\\' . self::stripSlashesFrom( $name );
	}

	/** @return ($classNameOnly is false ? string[] : string) */
	public static function resolveClassNameFrom( string $fqcn, bool $classNameOnly = true ) {
		$parts = array_filter( explode( '\\', $fqcn ), fn( string $part ): bool => '' !== $part );

		return $classNameOnly ? array_pop( $parts ) : $parts;
	}

	/** @param int|string $alias */
	public static function resolveImportFrom( string $name, $alias = 0 ): array {
		if ( ! is_string( $alias ) || '' === $alias ) {
			$alias = self::resolveClassNameFrom( self::makeFqcnFor( $name ) );
		}

		return array( $name, $alias );
	}

	public static function fromMethod( string $class, string $name ): ReflectionMethod {
		return new ReflectionMethod( $class, $name );
	}

	public function resolveImportedAliasFrom( string $fqcn ): string {
		$classname = self::resolveClassNameFrom( $fqcn );

		return ! array_key_exists( $classname, $this->namespace->getUses() )
			? self::makeFqcnFor( $fqcn )
			: $classname;
	}

	/** @throws InvalidArgumentException When namespace mismatch. */
	public function prepareClassNameFrom( string $fqcn ): string {
		$rawFqcn     = $fqcn;
		$fqcn        = self::makeFqcnFor( $fqcn );
		$isClassOnly = 1 === count( $parts = (array) self::resolveClassNameFrom( $fqcn, false ) );
		$className   = (string) array_pop( $parts );

		if ( $isClassOnly || strpos( $fqcn, "{$this->name}\\" ) !== false ) {
			return $className;
		}

		throw new InvalidArgumentException(
			sprintf(
				'Given Fully Qualified Class Name "%1$s" does not belong with the current namespace "%2$s". The given classname "%3$s" belongs to "%4$s" namespace.',
				$rawFqcn,
				$this->namespace->getName(),
				$className,
				implode( '\\', $parts )
			)
		);
	}

	/** @throws LogicException When this method is called before class is created. */
	public function getClass(): ClassType {
		static $class = null;

		if ( is_null( $class ) ) {
			$classes = array_filter(
				$this->namespace->getClasses(),
				fn( ClassType $class ): bool => $class->getType() === ClassType::TYPE_CLASS
			);

			$class = array_shift( $classes );

			if ( ! $class instanceof ClassType ) {
				throw new LogicException(
					sprintf(
						'"%s" method can only be called after class is created in the current namespace "%2$s". Use method "%3$s" to add class first.',
						__METHOD__,
						"\\{$this->namespace->getName()}",
						self::class . '::createClass()'
					)
				);
			}
		}

		return $class;
	}

	/** @throws LogicException When this method is called before class is created. */
	public function getMethod( string $name ): Method {
		return $this->getClass()->getMethod( $name );
	}

	/** @param string|Method $method */
	public function resolveMethod( $method ): Method {
		return is_string( $method ) ? $this->getMethod( $method ) : $method;
	}

	/**
	 * @param string|string[] $implements The interface names that the given class implements.
	 * @throws InvalidArgumentException When fully qualified classname given & namespace mismatch.
	 */
	public function createClass( string $name, string $extends = null, $implements = null ): self {
		$class = $this->namespace->addClass( $this->prepareClassNameFrom( $name ) );

		if ( $extends ) {
			$class->addExtend( $this->extends = $extends );
		}

		if ( $implements ) {
			$class->setImplements(
				$this->implements = is_array( $implements ) ? $implements : array( $implements )
			);
		}

		return $this;
	}

	/** @throws LogicException When this method is called before class is created. */
	public function addMethod( string $name ): Method {
		return $this->getClass()->addMethod( $name );
	}

	/** @throws LogicException When this method is called before class is created. */
	public function withMethod(
		string $fromClass,
		string $name,
		bool $title = true,
		bool $desc = false,
		bool $returnOnDoc = false
	): self {
		$fromMethod = self::fromMethod( self::makeFqcnFor( $fromClass ), $name );
		$toMethod   = $this->getClass()->addMethod( $name );
		$node       = DocParser::fromMethod( $fromMethod );
		$comments   = DocParser::getTextNodes( $node );
		$docTitle   = '';
		$docDesc    = '';

		if ( ! empty( $comments ) ) {
			$docTitle = (string) array_shift( $comments );

			if ( ! empty( $comments ) && $desc ) {
				$docDesc = implode( '', array_map( fn( $c ): string => "$c", $comments ) ) . PHP_EOL;
			}
		}

		if ( $docTitle && $title ) {
			$toMethod->addComment( $docTitle . PHP_EOL );
		}

		if ( $docDesc ) {
			$toMethod->addComment( $docDesc );
		}

		$this->attachParamsToMethod(
			$toMethod,
			$node->getParamTagValues(),
			$fromMethod->getParameters()
		);

		$this->attachReturnToMethod( $toMethod, $fromMethod, $node, $returnOnDoc, $title );

		foreach ( $node->getThrowsTagValues() as $throw ) {
			$toMethod->addComment( "@throws {$throw}" );
		}

		return $this;
	}

	/** @param string[] $names */
	public function withMethods(
		string $fromClass,
		array $names,
		bool $title = true,
		bool $desc = false,
		bool $returnOnDoc = false
	): self {
		foreach ( $names as $name ) {
			$this->withMethod( $fromClass, $name, $title, $desc, $returnOnDoc );
		}

		return $this;
	}

	/** @param array<int|string,string> $imports */
	public function using( array $imports ): self {
		$imports = array_merge( $imports, array( $this->extends ), $this->implements );

		foreach ( $imports as $key => $name ) {
			// Do not add import for "extends" and "implements" if class doesn't have any.
			if ( empty( $name ) ) {
				continue;
			}

			$this->namespace->addUse( ...self::resolveImportFrom( $name, $key ) );
		}

		return $this;
	}

	/**
	 * @param string|Method         $method The method.
	 * @param ParamTagValueNode[]   $nodes  The doc comment params.
	 * @param ReflectionParameter[] $args   The method arg params.
	 */
	public function attachParamsToMethod( $method, array $nodes, array $args ) {
		$method     = $this->resolveMethod( $method );
		$isVariadic = false;

		array_walk( $nodes, fn( ParamTagValueNode $node ) => $method->addComment( "@param {$node}" ) );
		array_walk(
			$args,
			function( ReflectionParameter $arg ) use ( $method, &$isVariadic ) {
				try {
					$param = $method->addParameter( $arg->getName(), $arg->getDefaultValue() );
				} catch ( ReflectionException $e ) {
					$param = $method->addParameter( $arg->getName() );
				}

				$param->setReference( $arg->isPassedByReference() );

				// Union type is not supported.
				if ( ( $type = $arg->getType() ) instanceof ReflectionNamedType ) {
					$param->setType( $type->getName() );
				}

				if ( ! $isVariadic && $arg->isVariadic() ) {
					$isVariadic = true;
				}
			}
		);

		$method->setVariadic( $isVariadic );
	}

	/** @param string|Method $method */
	public function attachReturnToMethod(
		$method, ReflectionMethod $reflection, PhpDocNode $node, bool $doc = false, bool $head = true
	): self {
		$tags     = $node->getReturnTagValues();
		$return   = array_shift( $tags );
		$desc     = $return ? $return->description : '';
		$type     = $return ? (string) $return->type : '';
		$fromType = $reflection->getReturnType();

		if ( $fromType instanceof ReflectionNamedType ) {
			$type = $fromType->getName();

			// We'll only add return type hint if it is declared on the reflection method.
			// We do not want to add unsupported return type on method name.
			if ( $head ) {
				$this->resolveMethod( $method )->setReturnType( $type );
			}
		}

		if ( $doc ) {
			if ( Type::fromString( $type )->isClass() ) {
				$type = $this->resolveImportedAliasFrom( $type );
			}

			$method->addComment( trim( "@return $type " . $desc ) );
		}

		return $this;
	}

	/** @param string|Method $method */
	public function attachBodyToMethod( $method, string $content ): self {
		$this->resolveMethod( $method )->addBody( $content );

		return $this;
	}
}
