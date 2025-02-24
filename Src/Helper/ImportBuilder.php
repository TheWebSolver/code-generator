<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Generator\Helper;

use Nette\PhpGenerator\PhpNamespace as Ns;
use TheWebSolver\Codegarage\Generator\Traits\ImportResolver;

final class ImportBuilder {
	use ImportResolver;

	public function __construct( string $item, Ns $namespace ) {
		$this->importStatementIn( $namespace, $item );
	}
}
