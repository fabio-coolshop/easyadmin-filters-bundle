<?php

namespace Coolshop\EasyAdminFilters\Configuration;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;

class FiltersConfigPass implements ConfigPassInterface {
	
	use ContainerAwareTrait;
	
	public function process(array $backendConfig) {
		
		$backendConfig = $this->processFiltersConfig($backendConfig);
		
		return $backendConfig;
		
	}
	
	protected function processFiltersConfig($backendConfig) {
		
		if(isset($backendConfig['entities'])){
			foreach($backendConfig['entities'] as &$entity){
				
				if(isset($entity['list']['filters'])){
					
					$entity['list']['filters'] = array_values(array_filter(array_map(function ($filter) use ($entity) {
						
						if(is_string($filter)){
							$filter = array('property' => $filter);
						}
						
						if(isset($entity['list']['fields'][ $filter['property'] ])){
							
							if(!isset($filter['type'])){
								$filter['type'] = $entity['list']['fields'][ $filter['property'] ]['dataType'];
							}
							
							$filter = array_merge_recursive(array(
								"type_options" => array(),
								"attr" => array(),
								"size" => 2,
							), $filter);
							
						}else{
							$filter = false;
						}
						
						return $filter;
						
					}, $entity['list']['filters'])));
					
				}else{
					$entity['list']['filters'] = array();
				}
				
			}
		}
		dump($backendConfig);
		
		return $backendConfig;
		
	}
	
}