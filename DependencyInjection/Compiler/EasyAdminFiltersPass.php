<?php

namespace Coolshop\EasyAdminFilters\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class EasyAdminFiltersPass implements CompilerPassInterface {
	
	public function process(ContainerBuilder $container) {
	
		$config = $container->getDefinition('easyadmin');
	
		dump($config);
		
	}
	
}