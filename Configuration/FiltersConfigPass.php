<?php

namespace Coolshop\EasyAdminFilters\Configuration;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;

class FiltersConfigPass implements ConfigPassInterface {
	
	use ContainerAwareTrait;
	
	private $entities;
	
	public function process(array $backendConfig) {
		
		$backendConfig = $this->processFiltersConfig($backendConfig);
		
		return $backendConfig;
		
	}
	
	protected function processFiltersConfig($backendConfig) {
		
		if(isset($backendConfig['entities'])){
			
			$this->entities = &$backendConfig['entities'];
			
			foreach($this->entities as &$entity){
				
				if(isset($entity['list']['filters'])){
					
					$entity['list']['filters'] = array_values(array_filter(array_map(function ($filter) use ($entity) {
						
						return $this->getFilter($filter,$entity);
						
					}, $entity['list']['filters'])));
					
				}else{
					$entity['list']['filters'] = array();
				}
				
			}
		}
		
		return $backendConfig;
		
	}
	
	private function getFilter($filter, $entity = null) {
		
		$query = $this->container->get('request_stack')->getCurrentRequest()->query->get('query',array());
		
		if(!$entity){
			$entity = $this->entity;
		}
		
		if(is_string($filter)){
			$filter = array('property' => $filter);
		}
		
		$nameParts = explode('.',$filter['property']);
		
		if(!isset($entity['properties'][ $filter['property'] ]) && isset($entity['properties'][ $nameParts[0] ])){
			
			$entityConfig = $this->getEntityConfiguration($entity['properties'][ $nameParts[0] ]['targetEntity']);
			
			$propertyBaseFieldName = array_shift($nameParts);
			
			$filter['property'] = implode('.',$nameParts);
			
			$filter = $this->getFilter($filter,$entityConfig);
			
			/** @var EntityManager $em */
			$em = $this->container->get('doctrine')->getManager();
			
			$filter['property'] = $propertyBaseFieldName.".".implode(".",$nameParts);
			$filter['fieldName'] = "query[".$filter['property']."]";
			$filter['value'] = isset($query[$filter['property']])? $query[$filter['property']] : $this->getDefaultTypeValue($filter['type']);
			
			try{
				$filter['subField'] = $entityConfig['properties'][array_pop($nameParts)];
			}catch(\Exception $e){}
			
		}elseif(isset($entity['properties'][ $filter['property'] ])){
			
			$filter['subField'] = false;
			
			if(!isset($filter['type'])){
				$filter['type'] = $entity['properties'][ $filter['property'] ]['dataType'];
			}
			
			$filter = array_replace_recursive(array(
				"type_options" => array(),
				"attr" => array(),
				"size" => 2,
			),$filter);
			
			switch($filter['type']){
				case 'association':
					$filter = array_replace_recursive(array( // add if not exists
						"choices" => $entity['properties'][ $filter['property'] ]['targetEntity'],
					),$filter,array( // override
						"type" => "choice",
						"nullable" => true,
					));
					break;
				default:
					$filter = array_replace_recursive($filter,array( // override
						"nullable" => $entity['properties'][$filter['property']]['nullable'],
					));
					break;
			}
			
			$filter['fieldName'] = "query[".$filter['property']."]";
			$filter['value'] = isset($query[$filter['property']])? $query[$filter['property']] : $this->getDefaultTypeValue($filter['type']);
			
		}else{
			
			$filter = array_replace_recursive(array(
				"type_options" => array(),
				"attr" => array(),
				"size" => 2,
				"nullable" => false,
			),$filter);
			
		}
		
		return $filter;
		
	}
	
	public function getEntityConfiguration($class){
		return array_values($this->entities)[array_search($class, array_column($this->entities, 'class'))];
	}
	
	private function getDefaultTypeValue($type){
		switch($type){
			case 'datetime':
				return array('start'=>'','end'=>'');
			default:
				return '';
		}
	}
	
}