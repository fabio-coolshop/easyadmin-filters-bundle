<?php

namespace Coolshop\EasyAdminFilters\Controller\Traits;

use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use Symfony\Component\HttpFoundation\JsonResponse;

trait Filterable {
	
	protected function filterAction() {
		
		$query = $this->request->query->get('filters');
		
		$filters = $this->entity['list']['filters'];
		
		$paginator = $this->filterBy($this->entity['class'], $query, $filters, $this->request->query->get('page', 1), $this->config['list']['max_results'], isset($this->entity['search']['sort']['field'])?$this->entity['search']['sort']['field']:$this->request->query->get('sortField'), isset($this->entity['search']['sort']['direction'])?$this->entity['search']['sort']['direction']:$this->request->query->get('sortDirection'), $this->entity['search']['dql_filter']);
		
		$parameters = array(
			'paginator' => $paginator,
			'fields' => $this->entity['list']['fields'],
			'delete_form_template' => $this->createDeleteForm($this->entity['name'], '__id__')->createView(),
		);
		
		return $this->executeDynamicMethod('render<EntityName>Template<ViewName>', array(
			'filter',
			$this->entity['templates']['list'],
			$parameters,
		));
		
	}
	
	protected function filterBy($entityClass, $searchQuery, array $searchableFields, $page = 1, $maxPerPage = 15, $sortField = null, $sortDirection = null, $dqlFilter = null) {
		
		$queryBuilder = $this->executeDynamicMethod('create<EntityName>FilterQueryBuilder', array(
			$entityClass,
			$searchQuery,
			$searchableFields,
			$sortField,
			$sortDirection,
			$dqlFilter,
		));
		
		$this->dispatch(EasyAdminEvents::POST_SEARCH_QUERY_BUILDER, array(
			'query_builder' => $queryBuilder,
			'search_query' => $searchQuery,
			'searchable_fields' => $searchableFields,
		));
		
		return $this->get('easyadmin.paginator')->createOrmPaginator($queryBuilder, $page, $maxPerPage);
	}
	
	public function ajaxFilterAction() {
		
		$query = $this->request->query->all();
		
		$queryKey = ",e.{$query['key']}";
		$queryLabel = $query['label']?",e.{$query['label']}":'';
		
		$queryBuilder = $this->getDoctrine()->getManager()->createQueryBuilder('e')->select("e $queryKey $queryLabel")->from($query['target'], 'e');
		
		if($query['label']){
			$queryBuilder->where("e.{$query['label']} LIKE :value")->setParameter(':value', "%{$query['value']}%");
		}
		
		$results = $queryBuilder->getQuery()->getResult();
		
		return new JsonResponse(array_values(array_filter(array_map(function ($result) use ($query) {
			
			if(!$query['label']){
				
				$label = (string) $result[0];
				
				if(strpos(strtolower($label), strtolower($query['value'])) !== false){
					$result = array(
						"id" => $result[ $query['key'] ],
						"text" => $label,
					);
				}else{
					$result = false;
				}
				
			}else{
				
				$result = array(
					"id" => $result[ $query['key'] ],
					"text" => $result[ $query['label'] ],
				);
				
			}
			
			return $result;
			
		}, $results))));
		
	}
	
	protected function createFilterQueryBuilder($entityClass, $searchQuery, array $searchableFields, $sortField = null, $sortDirection = null, $dqlFilter = null) {
		
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var QueryBuilder $queryBuilder */
		$queryBuilder = $em->createQueryBuilder()->select('entity')->from($this->entity['class'],'entity');
		
		$joined = array();
		
		foreach($searchableFields as $field){
			
			$propertyParts = explode('.',$field['property']);
			
			$value = isset($searchQuery[$field['property']])? $searchQuery[$field['property']] : $this->getDefaultValue($field['type']);
			
			$fieldName = $propertyParts[0];
			
			if($value !== $this->getDefaultValue($field['type'])){
				
				if($value == 'null'){
					
					$queryBuilder->andWhere("entity.$fieldName is null");
					
				}else{
					
					$fieldName = array_pop($propertyParts);
					
					$joinBase = "entity";
					
					if($field['subField']){
						
						$entityConfig = $this->entity;
						
						foreach($propertyParts as $subProperty){
							
							$entityConfig = $this->getEntityConfiguration($entityConfig['properties'][$subProperty]['targetEntity']);
							
							if(!in_array("$joinBase$subProperty",$joined)){
								$joined[] = "$joinBase$subProperty";
								$queryBuilder->join("$joinBase.$subProperty","$joinBase$subProperty");
							}
							
							$joinBase .= "$subProperty";
							
						}
						
					}
					
					if($entityConfig['properties'][$fieldName]['type'] == 'association'){
						$queryBuilder->join("$joinBase.$fieldName","$joinBase$fieldName");
						$joinBase .= $fieldName;
						$fieldName = 'id';
					}
					
					switch($field['type']){
						case 'choice':
							if(is_array($value)){
								$queryBuilder->andWhere("$joinBase.$fieldName IN (:$joinBase$fieldName)");
								$queryBuilder->setParameter("$joinBase$fieldName",$value);
							}else{
								$queryBuilder->andWhere("$joinBase.$fieldName = :$joinBase$fieldName");
								$queryBuilder->setParameter("$joinBase$fieldName",$value);
							}
							break;
						case 'datetime':
							if($value['start'] != ''){
								$queryBuilder->andWhere("$joinBase.$fieldName >= :Start$joinBase$fieldName");
								$queryBuilder->setParameter("Start$joinBase$fieldName",\DateTime::createFromFormat('d-m-Y H:i',$value['start']));
							}
							if($value['end'] != ''){
								$queryBuilder->andWhere("$joinBase.$fieldName <= :End$joinBase$fieldName");
								$queryBuilder->setParameter("End$joinBase$fieldName",\DateTime::createFromFormat('d-m-Y H:i',$value['end']));
							}
							break;
						default:
							if(is_array($value)){
								$queryBuilder->andWhere("$joinBase.$fieldName IN (:$joinBase$fieldName)");
								$queryBuilder->setParameter("$joinBase$fieldName",$value);
							}else{
								$queryBuilder->andWhere("$joinBase.$fieldName LIKE :$joinBase$fieldName");
								$queryBuilder->setParameter("$joinBase$fieldName","%$value%");
							}
							break;
					}
					
				}
				
			}
			
		}
		
		if(!empty($dqlFilter)){
			$queryBuilder->andWhere($dqlFilter);
		}
		
		if(null !== $sortField && $sortField != 'id'){
			$queryBuilder->orderBy('entity.'.$sortField,$sortDirection?:'DESC');
		}
		
		$queryBuilder->addOrderBy('entity.id','DESC');

		return $queryBuilder;
	}
	
	private function getFilter($filter, $entity = null) {
		
		$query = $this->request->query->get('query',array());
		
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
			
			$filter = $this->calculateFilter($filter,$entityConfig);

			/** @var EntityManager $em */
			$em = $this->getDoctrine()->getManager();
			
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
	
	private function getDefaultTypeValue($type){
		switch($type){
			case 'datetime':
				return array('start'=>'','end'=>'');
			default:
				return '';
		}
	}
	
	public function dynamicVariables() {
		return array(
			"<EntityName>" => $this->entity['name'],
			"<ViewName>" => ucwords($this->request->get('action')),
		);
	}
	
	protected function executeDynamicMethod($methodNamePattern,array $arguments = array()) {
		
		$dynamicVariables = $this->dynamicVariables();
		
		foreach($dynamicVariables as $variableName => $variable){
			$methodName = str_replace($variableName,$variable,$methodNamePattern);
			$methodName = str_replace(array_keys($dynamicVariables),'',$methodName);
			if(is_callable(array($this,$methodName))){
				return call_user_func_array(array($this,$methodName),$arguments);
			}
		}
		
		$methodName = str_replace(array_keys($dynamicVariables),$dynamicVariables,$methodNamePattern);
		
		if(!is_callable(array($this,$methodName))){
			$methodName = str_replace(array_keys($dynamicVariables),'',$methodNamePattern);
		}
		
		return call_user_func_array(array($this,$methodName),$arguments);
		
	}
	
	public function getEntityConfiguration($class){
		return array_values($this->config['entities'])[array_search($class, array_column($this->config['entities'], 'class'))];
	}
	
}