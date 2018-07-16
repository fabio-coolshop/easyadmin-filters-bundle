<?php

namespace Coolshop\EasyAdminFilters\Controller\Traits;

use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use Symfony\Component\HttpFoundation\JsonResponse;

trait Filterable {
	
	protected function filterAction() {
		
		$query = $this->request->query->get('filters');
		
		$filters = $this->entity['list']['filters'];
		
		$paginator = $this->filterBy(
			$this->entity['class'],
			$query,
			$filters,
			$this->request->query->get('page', 1),
			$this->config['list']['max_results'],
			isset($this->entity['search']['sort']['field'])?$this->entity['search']['sort']['field']:$this->request->query->get('sortField'),
			isset($this->entity['search']['sort']['direction'])?$this->entity['search']['sort']['direction']:$this->request->query->get('sortDirection'),
			$this->entity['search']['dql_filter']
		);
		
		$parameters = array(
			'paginator' => $paginator,
			'fields' => $this->entity['list']['fields'],
			'delete_form_template' => $this->createDeleteForm($this->entity['name'], '__id__')->createView(),
		);
		
		return $this->executeDynamicMethod('render<EntityName>Template', array(
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
		$queryBuilder = $em->createQueryBuilder()->select('entity')->from($this->entity['class'], 'entity');
		
		foreach($searchableFields as $field){
			
			$fieldName = $field['property'];
			
			if(isset($searchQuery[ $fieldName ]) && $searchQuery[ $fieldName ] !== ''){
				
				if($searchQuery[ $fieldName ] == 'null'){
					
					$queryBuilder->andWhere("entity.$fieldName is null");
					
				}else{
					
					switch($field['type']){
						case 'choice':
							$queryBuilder->andWhere("entity.$fieldName = :$fieldName");
							$queryBuilder->setParameter($fieldName, $searchQuery[ $fieldName ]);
							break;
						case 'datetime':
							if($searchQuery[ $fieldName ]['start'] != ''){
								$queryBuilder->andWhere("entity.$fieldName >= :Start$fieldName");
								$queryBuilder->setParameter("Start$fieldName", \DateTime::createFromFormat('d-m-Y H:i', $searchQuery[ $fieldName ]['start']));
							}
							if($searchQuery[ $fieldName ]['end'] != ''){
								$queryBuilder->andWhere("entity.$fieldName <= :End$fieldName");
								$queryBuilder->setParameter("End$fieldName", \DateTime::createFromFormat('d-m-Y H:i', $searchQuery[ $fieldName ]['end']));
							}
							break;
						default:
							$queryBuilder->andWhere("entity.$fieldName LIKE :$fieldName");
							$queryBuilder->setParameter($fieldName, "%$searchQuery[$fieldName]%");
							break;
					}
					
				}
				
			}
			
		}
		
		if(!empty($dqlFilter)){
			$queryBuilder->andWhere($dqlFilter);
		}
		
		if(null !== $sortField && $sortField != 'id'){
			$queryBuilder->orderBy('entity.'.$sortField, $sortDirection?:'DESC');
		}
		
		$queryBuilder->addOrderBy('entity.id', 'DESC');
		
		return $queryBuilder;
	}
	
}