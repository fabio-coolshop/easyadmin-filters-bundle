<?php

namespace Coolshop\EasyAdminFilters\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;

class AdminFilterableController extends AdminController {
	
	protected function renderTemplate($actionName,$templatePath,array $parameters = array()){
		
		if(in_array($actionName,array('list','search'))){
			$templatePath = $this->getTemplateList();
		}
		
		$parameters['filters'] = $this->getFilters();
		
		return $this->render($templatePath,$parameters);
	}
	
	protected function searchAction(){
		
		$this->dispatch(EasyAdminEvents::PRE_SEARCH);
		
		$query = $this->request->query->get('query');
		
		if(is_string($query)){
			return parent::searchAction();
		}
		
		$fields = $this->entity['list']['fields'];
		
		$filters = $this->getFilters();
		
		$paginator = $this->findBy($this->entity['class'],$query,$filters,$this->request->query->get('page',1),$this->config['list']['max_results'],isset($this->entity['search']['sort']['field'])?$this->entity['search']['sort']['field']:$this->request->query->get('sortField'),isset($this->entity['search']['sort']['direction'])?$this->entity['search']['sort']['direction']:$this->request->query->get('sortDirection'),$this->entity['search']['dql_filter']);
		
		array_map(function($filter){
		
		
		
		},$filters);
		
		$this->dispatch(EasyAdminEvents::POST_SEARCH,array(
			'fields'    => $fields,
			'paginator' => $paginator,
		));
		
		return $this->render($this->getTemplateList(),array(
			'paginator'            => $paginator,
			'fields'               => $fields,
			'filters'              => $filters,
			'delete_form_template' => $this->createDeleteForm($this->entity['name'],'__id__')->createView(),
		));
		
	}
	
	public function ajaxsearchAction(){
		
		$query = $this->request->query->all();
		
		$qkey = ", e.{$query['key']}";
		$qlabel = $query['label'] ? ", e.{$query['key']}" : '' ;
		
		
		$queryBuilder = $this->container->get('doctrine')->getManager()->createQueryBuilder('e')->select("e$qkey$qlabel")->from($query['target'],'e');
		
		if($query['label']){
			$queryBuilder
				->where("e.{$query['label']} LIKE :value")
				->setParameter(':value', "%{$query['query']}%")
			;
		}
		
		$results = $queryBuilder->getQuery()->getResult();
		
		return new JsonResponse(array_values(array_filter(array_map(function($result)use($query){
			
			if(!$query['label']){
				
				$strEntity = (string) $result[0];
				
				if(strpos(strtolower($strEntity),strtolower($query['query'])) !== false){
					$result = array(
						"id" => $result[$query['key']],
						"text" => $strEntity,
					);
				}else{
					$result = false;
				}
				
			}else{
				
				$result = array(
					"id" => $result[$query['key']],
					"text" => $result[$query['label']],
				);
				
			}
			
			return $result;
			
		},$results))));
		
	}
	
	protected function createSearchQueryBuilder($entityClass,$searchQuery,array $searchableFields,$sortField = null,$sortDirection = null,$dqlFilter = null){
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var QueryBuilder $queryBuilder */
		$queryBuilder = $em->createQueryBuilder()->select('entity')->from($this->entity['class'],'entity');
		
		foreach($searchableFields as $field){
			
			$fieldName = $field['property'];
			
			if(isset($searchQuery[ $fieldName ]) && $searchQuery[ $fieldName ] !== ''){
				
				if($searchQuery[ $fieldName ] == 'null'){
					
					$queryBuilder->andWhere("entity.$fieldName is null");
					
				}else{
					
					switch($field['type']){
						case 'choice':
							$queryBuilder->andWhere("entity.$fieldName = :$fieldName");
							$queryBuilder->setParameter($fieldName,$searchQuery[ $fieldName ]);
							break;
						case 'datetime':
							if($searchQuery[ $fieldName ]['start'] != ''){
								$queryBuilder->andWhere("entity.$fieldName >= :Start$fieldName");
								$queryBuilder->setParameter("Start$fieldName",\DateTime::createFromFormat('d-m-Y H:i',$searchQuery[ $fieldName ]['start']));
							}
							if($searchQuery[ $fieldName ]['end'] != ''){
								$queryBuilder->andWhere("entity.$fieldName <= :End$fieldName");
								$queryBuilder->setParameter("End$fieldName",\DateTime::createFromFormat('d-m-Y H:i',$searchQuery[ $fieldName ]['end']));
							}
							break;
						default:
							$queryBuilder->andWhere("entity.$fieldName LIKE :$fieldName");
							$queryBuilder->setParameter($fieldName,"%$searchQuery[$fieldName]%");
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
	
	private function getFilters(){
		
		if(!isset($this->entity['list']['filters'])){
			$this->entity['list']['filters'] = array();
		}
		
		return array_values(array_filter(array_map(function ($filter){
			
			if(is_string($filter)){
				$filter = array('property' => $filter);
			}
			
			if(isset($this->entity['list']['fields'][ $filter['property'] ])){
				
				if(!isset($filter['type'])){
					$filter['type'] = $this->entity['list']['fields'][ $filter['property'] ]['dataType'];
				}
				
				$filter = array_merge_recursive(array(
					"type_options" => array(
					
					),
					"attr" => array(
					
					),
					"size" => 2,
				),$filter);
				
			}else{
				$filter = false;
			}
			
			return $filter;
			
		},$this->entity['list']['filters'])));
	}
	
	private function getTemplateList(){
		return '@EasyAdminFilters/list-filters.html.twig';
	}

}