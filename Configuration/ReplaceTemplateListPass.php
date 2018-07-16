<?php

namespace Coolshop\EasyAdminFilters\Configuration;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;

class ReplaceTemplateListPass implements ConfigPassInterface {
	
	use ContainerAwareTrait;
	
	public function process(array $backendConfig) {
		
		$backendConfig = $this->replaceTemplateList($backendConfig);
		
		return $backendConfig;
		
	}
	
	protected function replaceTemplateList(array $backendConfig){
		
		if(isset($backendConfig['entities'])){
			foreach($backendConfig['entities'] as &$entity){
				if(isset($entity['list']) && isset($entity['list']['filters'])){
					$entity['templates']['list'] = isset($entity['templates']['list'])? $entity['templates']['list'] : '@EasyAdminFilters/list.html.twig';
					$entity['templates']['search'] = $entity['templates']['list'];
				}
			}
		}
		
		return $backendConfig;
		
	}
	
}