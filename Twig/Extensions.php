<?php

namespace Coolshop\EasyAdminFilters\Twig;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Extensions {
	
	use ContainerAwareTrait;
	
	// FILTERS
	
	public function getFilters() {
		return array(
			new \Twig_SimpleFilter('strpad', array($this, 'strpadFilter')),
			new \Twig_SimpleFilter('mongo_column', array($this, 'mongoColumn')),
			new \Twig_SimpleFilter('humanize', array($this, 'humanize')),
			new \Twig_SimpleFilter('hyphenize', array($this, 'hyphenize')),
			new \Twig_SimpleFilter('underscorize', array($this, 'underscorize')),
			new \Twig_SimpleFilter('cast', array($this, 'cast')),
		);
	}
	
	public function strpadFilter($number, $pad_length = 2, $pad_string = '0') {
		return str_pad($number, $pad_length, $pad_string, STR_PAD_LEFT);
	}
	
	public function mongoColumn($name) {
		$name = preg_replace('/^_/', '', $name);
		$name = preg_replace('/Id$/', '', $name)?:$name;
		
		return $name;
	}
	
	public function humanize($name) {
		$name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
		$name = preg_replace('/([a-z])-([a-z])/', '$1 $2', $name);
		$name = strtolower($name);
		$name = ucfirst($name);
		
		return $name;
	}
	
	public function hyphenize($name) {
		$name = preg_replace('/([a-z])([A-Z])/', '$1-$2', $name);
		$name = preg_replace('/([a-z|A-Z]) ([a-z|A-Z])/', '$1-$2', $name);
		$name = strtolower($name);
		
		return $name;
	}
	
	public function underscorize($name) {
		$name = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name);
		$name = preg_replace('/([a-z|A-Z]) ([a-z|A-Z])/', '$1_$2', $name);
		$name = strtolower($name);
		
		return $name;
	}
	
	public function cast($value, $name) {
		
		settype($value, $name);
		
		if($name == 'array'){
			
			$return = array();
			
			foreach($value as $key => $val){
				$return[ preg_replace("#^.*\\x00.*\\x00(.+)$#", "$1", $key) ] = $val;
			}
			
		}else{
			
			$return = $value;
			
		}
		
		return $return;
	}
	
	// FUNCTIONS
	
	public function getFunctions() {
		return array(
			new \Twig_SimpleFunction('call', [$this, 'callFunction']),
			new \Twig_SimpleFunction('call_static', [$this, 'callStaticMethod']),
			new \Twig_SimpleFunction('get_static', [$this, 'getStaticProperty']),
			new \Twig_SimpleFunction('uniqid', [$this, 'uniqid']),
			new \Twig_SimpleFunction('typeof', [$this, 'typeof']),
			new \Twig_SimpleFunction('findAll', [$this, 'findAll']),
			new \Twig_SimpleFunction('flatAttributes', [$this, 'flatAttributes'], array('is_safe' => array('html'))),
			new \Twig_SimpleFunction('findOneBy', [$this, 'findOneBy']),
			new \Twig_SimpleFunction('findBy', [$this, 'findBy']),
		);
	}
	
	public function typeof($variable) {
		switch(gettype($variable)){
			case 'object':
				$class = explode('\\', get_class($variable));
				
				return array_pop($class);
			default:
				return gettype($variable);
		}
	}
	
	public function uniqid($prefix, $moreEntropy = false) {
		return uniqid($prefix, $moreEntropy);
	}
	
	public function callFunction($function) {
		return $function();
	}
	
	public function callStaticMethod($class, $method, array $args = []) {
		return $class::$method();
	}
	
	public function getStaticProperty($class, $property) {
		return $class::$property;
	}
	
	public function findAll($class, $key = 'id', $value = '') {
		
		$splitted = explode('::', $class);
		
		if(isset($splitted[1])){
			$value = $splitted[1];
		}
		
		$qkey = "e.$key";
		
		$qvalue = $value?", e.$value":'';
		
		$results = array();
		
		foreach($this->container->get('doctrine')->getManager()->createQueryBuilder()->select('e,'.$qkey.$qvalue)->distinct()->from($class, 'e')->getQuery()->getResult() as $result){
			
			$results[ $result[ $key ] ] = $value?$result[ $value ]:$result[0];
			
		}
		
		return $results;
		
	}
	
	public function findBy($class, $filters = array()) {
		
		$qb = $this->container->get('doctrine')->getManager()->createQueryBuilder();
		
		foreach($filters as $attribute => $value){
			if(is_array($value)){
				$qb->andWhere("e.$attribute in (:$attribute)")->setParameter("$attribute", array_map(function ($entity) {
					return $entity->getId();
				}, $value));
			}else{
				$qb->andWhere("e.$attribute = :$attribute")->setParameter("$attribute", $value);
			}
		}
		
		return $qb->select('e')->distinct()->from($class, 'e')->getQuery()->getResult();
		
	}
	
	public function findOneBy($class, $value, $key = 'id') {
		
		return $this->container->get('doctrine')->getManager()->createQueryBuilder()->select("e")->from($class, 'e')->where("e.$key = :value")->setParameter(":value", $value)->getQuery()->getSingleResult();
		
	}
	
	public function flatAttributes($attributes = array(), $prefix = '') {
		
		$flatted = "";
		
		foreach($attributes as $key => $attribute){
			
			if($prefix){
				$key = "$prefix-$key";
			}
			
			$key = $this->hyphenize($key);
			
			if(is_array($attribute)){
				$flatted .= $this->flatAttributes($attribute, $key);
			}else{
				$flatted .= "$key=\"$attribute\"";
			}
			
		}
		
		return $flatted;
	}
	
	// TESTS
	
	public function getTests() {
		return array(
			new \Twig_SimpleTest('string', [$this, 'isString']),
			new \Twig_SimpleTest('array', [$this, 'isArray']),
			new \Twig_SimpleTest('function', [$this, 'isFunction']),
			new \Twig_SimpleTest('class', [$this, 'isClass']),
			new \Twig_SimpleTest('property', [$this, 'isProperty']),
		);
	}
	
	public function isString($value) {
		return is_string($value);
	}
	
	public function isArray($value) {
		return is_array($value);
	}
	
	public function isFunction($value) {
		return is_callable($value);
	}
	
	public function isClass($value) {
		if(!is_string($value)){
			return false;
		}
		
		return class_exists($value);
	}
	
	public function isProperty($value) {
		if(!is_string($value)){
			return false;
		}
		$splitted = explode('::', $value);
		if(count($splitted) < 2){
			return false;
		}
		$class = $splitted[0];
		$property = $splitted[1];
		
		return $this->container->get('doctrine')->getManager()->getClassMetadata($class)->hasField($property);
	}
	
}