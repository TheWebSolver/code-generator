<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator;

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
	private ClassType $class;

	public function __construct( string $currentNamespace ) {
		$this->namespace = new PhpNamespace( self::stripSlashesFrom( $currentNamespace ) );
	}

	public function __toString() {
		return (string) $this->namespace;
	}

	public function namespace(): PhpNamespace {
		return $this->namespace;
	}

	public static function from( string $currentNamespace ): self {
		return new self( $currentNamespace );
	}

	public static function stripSlashesFrom( string $fqcn ): string {
		return trim( $fqcn, '\\' );
	}

	public static function makeFqcnFor( string $className ): string {
		return '\\' . self::stripSlashesFrom( $className );
	}

	/** @return ($classNameOnly is false ? array<int,string> : string) */
	public static function resolveClassNameFrom( string $fqcn, bool $classNameOnly = true ) {
		$parts = array_filter( explode( '\\', $fqcn ), static fn( string $part ): bool => '' !== $part );

		return ! $classNameOnly ? $parts : ( array_pop( $parts ) ?? '' );
	}

	/** @return array{0:string,1:string} Fully qualified classname and its alias. */
	public static function resolveImportFrom( string $fqcn, string|int $alias = 0 ): array {
		if ( ! is_string( $alias ) || '' === $alias ) {
			$alias = self::resolveClassNameFrom( self::makeFqcnFor( $fqcn ) );
		}

		return array( $fqcn, $alias );
	}

	public static function fromMethod( string $className, string $methodName ): ReflectionMethod {
		return new ReflectionMethod( $className, $methodName );
	}

	public function resolveImportedAliasFrom( string $fqcn ): string {
		$className = self::resolveClassNameFrom( $fqcn );

		return ! array_key_exists( $className, $this->namespace->getUses() )
			? self::makeFqcnFor( $fqcn )
			: $className;
	}

	/** @throws InvalidArgumentException When namespace mismatch. */
	public function prepareClassNameFrom( string $fqcn ): string {
		$rawFqcn     = $fqcn;
		$fqcn        = self::makeFqcnFor( $fqcn );
		$isClassOnly = 1 === count( $parts = self::resolveClassNameFrom( $fqcn, false ) );
		$className   = (string) array_pop( $parts );
		$namespace   = $this->namespace->getName();

		if ( $isClassOnly || strpos( $fqcn, self::makeFqcnFor( $namespace ) . '\\' ) !== false ) {
			return $className;
		}

		throw new InvalidArgumentException(
			sprintf(
				'Given Fully Qualified Class Name "%1$s" does not belong with the current namespace "%2$s". The given classname "%3$s" belongs to "%4$s" namespace.',
				$rawFqcn,
				$namespace,
				$className,
				implode( '\\', $parts )
			)
		);
	}

	/** @throws LogicException When this method is called before class is created. */
	public function getClass(): ClassType {
		if ( $class = ( $this->class ?? null ) ) {
			return $class;
		}

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

		return $this->class = $class;
	}

	/** @throws LogicException When this method is called before class is created. */
	public function getMethod( string $methodName ): Method {
		return $this->getClass()->getMethod( $methodName );
	}

	public function resolveMethod( string|Method $method ): Method {
		return $method instanceof Method ? $method : $this->getMethod( $method );
	}

	/**
	 * @param string               $className
	 * @param string               $extendsName
	 * @param string|string[]|null $implementsName The interface names that the given class implements.
	 * @throws InvalidArgumentException When fully qualified classname given & namespace mismatch.
	 */
	public function createClass(
		string $className,
		string $extendsName = null,
		string|array|null $implementsName = null
	): self {
		$class = $this->namespace->addClass( $this->prepareClassNameFrom( $className ) );

		if ( $extendsName ) {
			$class->addExtend( $this->extends = $extendsName );
		}

		if ( $implementsName ) {
			$class->setImplements(
				$this->implements = is_array( $implementsName ) ? $implementsName : array( $implementsName )
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

	/**
	 * @param string   $fromClassName
	 * @param string[] $methodNames
	 */
	public function withMethods(
		string $fromClassName,
		array $methodNames,
		bool $title = true,
		bool $desc = false,
		bool $returnOnDoc = false
	): self {
		foreach ( $methodNames as $name ) {
			$this->withMethod( $fromClassName, $name, $title, $desc, $returnOnDoc );
		}

		return $this;
	}

	/** @param array<int|string,string> $imports */
	public function using( array $imports ): self {
		$imports = array_merge( $imports, array( $this->extends ), $this->implements );

		foreach ( $imports as $alias => $className ) {
			if ( ! empty( $className ) ) {
				$this->namespace->addUse( ...self::resolveImportFrom( $className, $alias ) );
			}
		}

		return $this;
	}

	/**
	 * @param string|Method         $method The method.
	 * @param ParamTagValueNode[]   $nodes  The doc comment params.
	 * @param ReflectionParameter[] $args   The method arg params.
	 */
	public function attachParamsToMethod( string|Method $method, array $nodes, array $args ): void {
		$method     = $this->resolveMethod( $method );
		$isVariadic = false;

		array_walk( $nodes, fn( ParamTagValueNode $node ) => $method->addComment( "@param {$node}" ) );
		array_walk(
			$args,
			function ( ReflectionParameter $arg ) use ( $method, &$isVariadic ) {
				try {
					$param = $method->addParameter( $arg->getName(), $arg->getDefaultValue() );
				} catch ( ReflectionException ) {
					$param = $method->addParameter( $arg->getName() );
				}

				$param->setReference( $arg->isPassedByReference() );

				// Only NamedType is supported.
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

	public function attachReturnToMethod(
		string|Method $method,
		ReflectionMethod $reflection,
		PhpDocNode $node,
		bool $doc = false,
		bool $head = true
	): self {
		$tags     = $node->getReturnTagValues();
		$return   = array_shift( $tags );
		$desc     = $return ? $return->description : '';
		$type     = $return ? (string) $return->type : '';
		$fromType = $reflection->getReturnType();
		$method   = $this->resolveMethod( $method );

		if ( $fromType instanceof ReflectionNamedType ) {
			$type = $fromType->getName();

			// We'll only add return type hint if it is declared on the reflection method.
			// We do not want to add unsupported return type on method name.
			if ( $head ) {
				$method->setReturnType( $type );
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

	public function attachBodyToMethod( string|Method $method, string $content ): self {
		$this->resolveMethod( $method )->addBody( $content );

		return $this;
	}
}
