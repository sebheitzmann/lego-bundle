<?php

namespace Idk\LegoBundle\Configurator;

use Doctrine\ORM\PersistentCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Idk\LegoBundle\Annotation\Entity\Field;
use Idk\LegoBundle\Component\Component;
use Idk\LegoBundle\Service\Tag\ComponentChain;
use Symfony\Component\Asset\Package;
use Symfony\Component\HttpFoundation\Request;
use Idk\LegoBundle\Lib\Path;



abstract class AbstractConfigurator
{

    const ROUTE_SUFFIX_EDIT_IN_PLACE = 'editinplace';
    const ROUTE_SUFFIX_ORDER_COMPONENTS = 'ordercomponents';
    const ROUTE_SUFFIX_ORDER_COMPONENTS_RESET = 'ordercomponentsreset';
    const ROUTE_SUFFIX_INDEX = 'index';
    const ROUTE_SUFFIX_ADD = 'add';
    const ROUTE_SUFFIX_EDIT = 'edit';
    const ROUTE_SUFFIX_SHOW = 'show';

    const ENTITY_CLASS_NAME = 'entity_class_name_empty';

    const TITLE = 'lego.default.title';

    protected $em;

    private $showTemplate = 'IdkLegoBundle:Default:show.html.twig';

    private $logTemplate = 'IdkLegoBundle:Default:log.html.twig';

    private $logsTemplate = 'IdkLegoBundle:Default:logs.html.twig';

    private $addTemplate = 'IdkLegoBundle:Default:add.html.twig';

    private $editTemplate = 'IdkLegoBundle:Default:edit.html.twig';

    private $indexTemplate = 'IdkLegoBundle:Default:index.html.twig';

    private $title;

    protected $page = 1;

    protected $orderBy = '';

    protected $orderDirection = '';

    protected $container;

    protected $entityClassName;

    private $isBuild = false;

    protected $request;

    protected $currentComponentSuffixRoute;

    protected $children = [];

    private $components = [];

    private $parent = null;

    private $pathParameters = [];

    public function __construct($container, AbstractConfigurator $parent = null, $entityClassName = null, $pathParameters = [])
    {
        if($parent) $this->setParent($parent);
        $this->container = $container;
        $this->entityClassName = $entityClassName;
        $this->pathParameters = $pathParameters;
        if($this->getControllerPath() !== 'lego'){
           unset($this->pathParameters['entity']); //TODO rename in lego_entity
        }
        if($container) { //another methode for no build TODO (need a empty configurateur and a executable configurator ??)
            $this->build();
        }
    }

    public function setPathParameters(array $pathParameters){
        $this->pathParameters = $pathParameters;
    }

    public function getId(){
        return md5(get_class($this));
    }

    abstract function getPager();
    abstract public function getType($item,$columnName);

    public function getEntityName(){
        return $this->entityClassName ?? $this::ENTITY_CLASS_NAME;
    }

    public function setEntityClassName($entityClassName){
        $this->entityClassName = $entityClassName;
    }


    public function buildIndex(){
        return;
    }

    public function buildShow(){
        return;
    }

    public function buildEdit(){
        return;
    }

    public function buildAdd(){
        return;
    }

    public function buildAll(){
        return;
    }

    public function build(){
        if($this->isBuild == false and !$this->getParent()){
            $this->isBuild = true;
            $this->buildAll();
            $this->buildIndex();
            $this->buildEdit();
            $this->buildAdd();
            $this->buildShow();
        }
    }

    public function get($id)
    {
        return $this->container->get($id);
    }

    public function getTitle() {
        return $this->title ?? $this::TITLE;
    }

    public function getSubTitle() {
        return "";
    }

    /**
     * Return default repository name.
     *
     * @return string
     */
    public function getRepositoryName()
    {
        return $this->getEntityName();
    }


    public function getPathByField($item,Field $field)
    {
        $path = $field->getPath();
        if(!$path) return null;
        $params = [];
        if($path and isset($path['route'])) {
            $route = $path['route'];
            if (isset($path['params']) and $path['params']) {
                foreach ($path['params'] as $key => $fieldName) {
                    $params[$key] = $this->getValue($item, $fieldName);
                }
            }
        }else{
            if ($path == self::ROUTE_SUFFIX_SHOW) {
                $route = $this->getPathRoute(self::ROUTE_SUFFIX_SHOW);
                $params['id'] = $item->getId();
                $params = $this->getPathParameters($params);
            }
        }
        return ['route' => $route, 'params' => $params];
    }

    public function getEditInPlacePath()
    {
        return new Path($this->getPathByConvention(self::ROUTE_SUFFIX_EDIT_IN_PLACE), $this->getPathParameters());
    }





    /**
     * @return int
     */
    public function getLimit()
    {
        return 25;
    }


    public function getLogTemplate()
    {
        return $this->logTemplate;
    }
    public function getLogsTemplate()
    {
        return $this->logsTemplate;
    }
    public function getShowTemplate()
    {
        return $this->showTemplate;
    }



    function from_camel_case($str) {
          $str[0] = strtolower($str[0]);
            return preg_replace_callback('/([A-Z])/', function($c){ return "_" . strtolower($c[1]);}, $str);
    }


    function to_camel_case($str, $capitalise_first_char = false) {
          if($capitalise_first_char) {
                  $str[0] = strtoupper($str[0]);
                    }
            return preg_replace_callback('/_([a-z])/', function($c){ return "_" . strtolower($c[1]);}, $str);
    }

    public function getValue($item, $columnName)
    {
        if (is_array($item)) {
            if (isset($item[$columnName])) {
                return $item[$columnName];
            } else {
                return '';
            }
        }
        $methodName = $columnName;
        if (method_exists($item, $methodName)) {
            $result = $item->$methodName();
        } else {
            $methodName = 'get' . ucfirst($this->to_camel_case($columnName));
            if (method_exists($item, $methodName)) {
                $result = $item->$methodName();
            } else {
                $methodName = 'is' . $columnName;
                if (method_exists($item, $methodName)) {
                    $result = $item->$methodName();
                } else {
                    $methodName = 'has' . $columnName;
                    if (method_exists($item, $methodName)) {
                        $result = $item->$methodName();
                    } else {
                        $methods = explode('.',$columnName);
                        $subItem = $item;
                        foreach($methods as $m){
                            $methodName = 'get' . ucfirst($m);
                            if(!$subItem) return null;

                            if (method_exists($subItem, $methodName)){
                                try{
                                    $subItem = $subItem->$methodName();
                                }catch(\Exception $e){
                                    if(is_object($subItem)) return $this->em->getClassMetadata(get_class($subItem))->getName().':'.$subItem->getId() .' introuvable';
                                    else return '<strong>'.$columnName.'</strong> not found';
                                }
                            }else{
                                if(method_exists($subItem,'__get')){
                                    return $subItem->$methodName();
                                }else{
                                    if(is_object($subItem)) return sprintf(get_class($subItem).'() find but undefined function '.$methodName.'() not found');
                                    else return '<strong>'.$columnName.'</strong> not found';
                                }
                            }
                        }
                        $result = $subItem;
                    }
                }
            }
        }

        return $result;
    }



    public function editInplaceInputType($item,$columnName,$class = null){
        $methodName = 'get' . $this->to_camel_case($columnName);
        if (is_array($item)) {
            $item = $item[0];
        }
        $type = $this->getType($item,$columnName);
        $retour = $item->$methodName();
        if ($type == 'boolean') {
            return $type;
        }else if($type == 'datetime'){
            return $type;
        }else if($type == 'date') {
            return $type;
        }else if($type == 'time') {
            return $type;
        } else if($retour instanceof PersistentCollection) {
            return 'collection';
        } elseif(is_array($retour)) {
            return 'array';
        } elseif($type != null) {
            return 'text';
        } else {
            return 'object';
        }
    }

    public function getStringValue($item, $columnName)
    {
        $type = null;
        $subColumn = substr($columnName,0,strrpos($columnName,'.'));
        if($subColumn){
            $value = $this->getValue($item, $subColumn);
            if($value){
                $type = $this->getType($value,substr($columnName,strrpos($columnName,'.')+1));
            }
        }else{
            $type = $this->getType($item,$columnName);
        }
        $result = $this->getValue($item, $columnName);
        if($type == 'json_array'){
            return 'Json';
        }
        if(is_array($result)){
            return nl2br(implode("\n",$result));
        }
        if ($type == 'boolean') {
            if($result == null) return '';
            return ($result)? 'oui' : 'non';
        }elseif($type == 'text'){
            return ($result)? nl2br($result):$result;
        }

        if ($result instanceof \DateTime) {
            if($type == 'datetime'){
                return ($result->format('Y') > 0)? $result->format('d/m/Y H:i:s'):null;
            }elseif($type == 'date'){
                return ($result->format('Y') > 0)? $result->format('d/m/Y'):null;
            }elseif($type == 'time'){
                return $result->format('H:i');
            }
        } else {
            if ($result instanceof PersistentCollection) {
                $results = "";
                /* @var Object $entry */
                foreach ($result as $entry) {
                    $results[] = $entry->__toString();
                }
                if (empty($results)) {
                    return "";
                }

                return implode(', ', $results);
            } elseif ($result instanceof ArrayCollection) {
                $results = "";
                /* @var Object $entry */
                foreach ($result as $entry) {
                    $results[] = (string)$entry;
                }
                if (empty($results)) {
                    return "";
                }


                return implode(', ', $results);
            } else {
                if (is_array($result)) {
                    foreach($result as $r){
                        if(is_array($r)) return json_encode($result);
                    }
                    return implode(', ', $result);
                }elseif(is_object($result)){
                    return (string)$result;
                } else {
                    return $result;
                }
            }
        }
    }


    public function getAddTemplate()
    {
        return $this->addTemplate;
    }

    public function getEditTemplate()
    {
        return $this->editTemplate;
    }

    public function bindRequest(Request $request, $routeSuffix = null)
    {
        $this->request = $request;
        if(!$routeSuffix) {
            $lastUnserscore = strrchr($request->get('_route'), '_');

            $index = substr($lastUnserscore, 1);
            $index = ($index == 'export') ? 'index' : $index;
        }else{
            $index = $routeSuffix;
        }
        $this->currentComponentSuffixRoute = $index;
        return $this->bindRequestCurrentComponents($request);
    }


    public function bindRequestCurrentComponents(Request $request, Component $excepted = null){
        $componentResponse = [];
        foreach($this->getChildren($this->currentComponentSuffixRoute) as $configurator){
            $configurator->bindRequest($request, $this->currentComponentSuffixRoute);
        }
        foreach($this->components[$this->currentComponentSuffixRoute] as $component){
            if($component->getConfigurator()->getId() == $this->getId() and ($excepted == null or $component->getId() != $excepted->getId())) {
                $componentResponse[] = $component->bindRequest($request);
            }
        }
        return $componentResponse;
    }

    public function getChildren($routeSuffix){
        if(isset($this->children[$routeSuffix])){
            return $this->children[$routeSuffix];
        }
        return [];
    }

    public function getCurrentComponents(){
        return $this->getComponents($this->currentComponentSuffixRoute);
    }

    public function getCurrentComponentSuffixRoute(){
        return $this->currentComponentSuffixRoute;
    }


    public function getPage()
    {
        return $this->page;
    }


    public function getOrderBy()
    {
        return $this->orderBy;
    }


    public function getOrderDirection()
    {
        return $this->orderDirection;
    }

    public function getPathByConvention($suffix = null)
    {
        return $this->getControllerPath().'_'.$suffix;
    }

    public function label($word){
        return $word;
    }

    public function getUser(){
        return $this->get('security.context')->getToken()->getUser();
    }




    public function setParent(AbstractConfigurator $configurator){
        $this->parent = $configurator;
        return $this;
    }

    public function getParent(){
        return $this->parent;
    }

    public function getIndexComponents(){
        return $this->getComponents(self::ROUTE_SUFFIX_INDEX);
    }

    public function getAddComponents(){
        return $this->getComponents(self::ROUTE_SUFFIX_ADD);
    }

    public function getEditComponents(){
        return $this->getComponents(self::ROUTE_SUFFIX_EDIT);
    }

    public function getShowComponents(){
        return $this->getComponents(self::ROUTE_SUFFIX_SHOW);
    }

    public function getComponent($routeSuffix, $id){
        $this->currentComponentSuffixRoute = $routeSuffix;
        foreach($this->components[$routeSuffix] as $component){
            if($component->getId() == $id) {
                return $component;
            }
        }
        return null;
    }

    public function getComponents($routeSuffix)
    {
        if (isset($this->components[$routeSuffix])) {

            $components = $this->components[$routeSuffix];
            $order = $this->getConfiguratorSessionStorage('order');
            if ($order != null and isset($order[$routeSuffix])) {
                return $this->sortComponents($components, $order[$routeSuffix]);
            } else {
                return $components;
            }
        }
        return [];
    }

    private function sortComponents($components, $order){
        $return = [];
        foreach($order as $id){
            foreach($components as $cid => $component){
                if($cid == $id){
                    $return[$id] = $component;
                    break;
                }
            }
        }
        //check if new component which not in the order array
        foreach($components as $cid => $component){
            if(!in_array($cid, array_keys($return))){
                $return[$cid] = $component;
            }
        }
        return $return;
    }

    public function getIndexTemplate()
    {
        return $this->indexTemplate;
    }

    public function setIndexTemplate($template)
    {
        $this->indexTemplate = $template;
        return $this;
    }

    public function addIndexComponent($className, array $options, $configuratorClassName = null)
    {
        return $this->addComponent($className, $options, self::ROUTE_SUFFIX_INDEX, $configuratorClassName);
    }

    public function addAddComponent($className, array $options, $configuratorClassName = null)
    {
        return $this->addComponent($className, $options, self::ROUTE_SUFFIX_ADD, $configuratorClassName);
    }

    public function addEditComponent($className, array $options,  $configuratorClassName = null)
    {
        return $this->addComponent($className, $options, self::ROUTE_SUFFIX_EDIT, $configuratorClassName);
    }

    public function addShowComponent($className, array $options, $configuratorClassName = null)
    {
        return $this->addComponent($className, $options, self::ROUTE_SUFFIX_SHOW, $configuratorClassName);
    }

    public function addComponent($className, array $options, $routeSuffix, $configuratorClassName = null){
        if(!isset($this->components[$routeSuffix])){
            $this->components[$routeSuffix] = [];
        }
        $component = $this->generateComponent($className, $options, $routeSuffix, $configuratorClassName);
        $this->components[$routeSuffix][$component->getId()] = $component;
        return $component;
    }

    private function generateComponent($className, array $options, $routeSuffix, $configuratorClassName = null)
    {
        $reflectionClass =  new \ReflectionClass($className);
        if($configuratorClassName){
            $configurator = $this->generateConfigurator($configuratorClassName);
            $component = $configurator->addComponent($className,$options, $routeSuffix);
            $this->addChild($routeSuffix, $configurator);
            return $component;
        }else{
            $this->componentsChain = $this->get(ComponentChain::class);
            return $this->componentsChain->build($className, $options, $this, $routeSuffix);
            return $this->get($className)->build($options, $this, $routeSuffix);//$reflectionClass->newInstance()->build($options, $this, $routeSuffix);
        }

    }

    private function addChild($routeSuffix, AbstractConfigurator $configurator){
        //2* le mem configurateur ?? TODO dedoublon
        if(!isset($this->children[$routeSuffix])){
            $this->children[$routeSuffix] = [];
        }
        $this->children[$routeSuffix][] = $configurator;
    }

    private function generateConfigurator($className = null){
        $reflectionClass =  new \ReflectionClass($className);
        return $reflectionClass->newInstance($this->container, $this);
    }



    public function getPathRoute($sufix = 'index'){
        return $this->getControllerPath().'_'.$sufix;
    }

    public function getPath($suffix = 'index', $params = []){
        return new Path( $this->getPathRoute($suffix), $this->getPathParameters($params));
    }

    public function getUrl($suffix, array $params = []){
        $path = $this->getPath($suffix, $params);
        return $this->get('router')->generate($path->getRoute(), $path->getParams());
    }

    public function getPathParams($item){
            return $this->getPathParameters(['id' => $item->getId()]);
    }

    public function getPathParameters(array $params = []){
        return array_merge($params, $this->pathParameters);
    }

    public function getConfiguratorSessionStorage($key, $default = null){
        if($this->get('session')->has($this->getId())){
            $componentSessionStorage = $this->get('session')->get($this->getId());
            return (isset($componentSessionStorage[$key]))? $componentSessionStorage[$key]:$default;
        }else{
            return $default;
        }
    }

    public function setConfiguratorSessionStorage($key, $value){
        if(!$this->get('session')->has($this->getId())){
            $this->get('session')->set($this->getId(), []);
        }
        $componentSessionStorage = $this->get('session')->get($this->getId());
        $componentSessionStorage[$key] = $value;
        $this->get('session')->set($this->getId(), $componentSessionStorage);
        return $this;
    }

    public function setTitle($title){
        $this->title = $title;
    }

    public function getControllerPath(){
        return 'lego';
    }




}
