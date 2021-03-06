<?php

namespace Idk\LegoBundle\Component;


use Idk\LegoBundle\Annotation\Entity\Field;
use Idk\LegoBundle\Lib\Actions\EntityAction;
use Idk\LegoBundle\Lib\Breaker;
use Symfony\Component\HttpFoundation\Request;
use Idk\LegoBundle\Lib\Actions\BulkAction;
use Doctrine\ORM\QueryBuilder;
use Idk\LegoBundle\Lib\QueryHelper;
use Idk\LegoBundle\Service\MetaEntityManager;

class ListItems extends Component{

    const ENTITY_ACTION_DELETE = 'entity_action_delete';
    const ENTITY_ACTION_EDIT = 'entity_action.edit';
    const ENTITY_ACTION_SHOW = 'entity_action.show';
    const BULK_ACTION_DELETE = 'bulk_action_delete';

    private $fields = [];
    private $entityActions = [];
    private $bulkActions = [];
    private $page = 1;
    private $nbEntityPerPage = null;
    private $breakers = [];
    private $sorters = [];
    private $mem;

    public function __construct(MetaEntityManager $mem){
        $this->mem = $mem;
    }

    protected function init(){
        $this->sorters = $this->getOption('sorters', []);
        return;
    }

    public function getAllQueryParams()
    {
        return ['page', 'breaker', 'nbepp'];
    }

    public function getListenParamsForReload()
    {
        if($this->getConfigurator()->getParent()) {
            return ['id'];
        }else{
            return [];
        }
    }

    public function xhrBindRequest(Request $request)
    {
        if($request->query->has('nbepp')){
            $this->setComponentSessionStorage('nbepp', $request->query->get('nbepp'));
        }
        if($request->query->has('breaker')){
            $this->setComponentSessionStorage('breaker', $request->query->get('breaker'));
        }
        if($request->query->has('page')){
            $this->setComponentSessionStorage('page', $request->query->get('page'));
        }
        $this->bindRequest($request);
    }

    public function bindRequest(Request $request)
    {
        parent::bindRequest($request);
        $this->initBreakers();
        $this->nbEntityPerPage = $this->getComponentSessionStorage('nbepp', $this->getOption('entity_per_page'));
        $this->page = $this->getComponentSessionStorage('page',1);
        foreach($this->getOption('entity_actions', []) as $action){
            if($action instanceOf EntityAction){
                $this->entityActions[] = $action;
            }else{
                $this->addPredefinedEntityAction($action);
            }
        }
        foreach($this->getOption('bulk_actions', []) as $action){
            if($action instanceOf BulkAction){
                $action->setCid($this->getId());
                $this->bulkActions[] = $action;
            }else{
                $this->addPredefinedBulkAction($action);
            }
        }
    }

    public function getPager(){
        return  $this->getConfigurator()->getPager($this->page,$this->nbEntityPerPage, $this->getOption('page_unlimited', false));
    }

    protected function requiredOptions(){
        return [];
    }

    public function add($name, $options){
        $field = new Field($options);
        $field->setName($name);
        $this->fields[$name] = $field;
        return $this;
    }

    public function addEntityAction($label, $options){
        $this->entityActions[] = new EntityAction($label, $options);
        return $this;
    }

    public function addBulkAction($label,$options)
    {
        $bulkAction = new BulkAction($label,$options);
        $bulkAction->setCid($this->getId());
        $this->bulkActions[] =  $bulkAction;
        return $this;
    }

    public function addPredefinedEntityAction($action){
        if($action == self::ENTITY_ACTION_DELETE){
            $this->entityActions[] = new EntityAction('lego.action.delete', ['icon'=>'remove', 'css_class' => 'btn-danger' ,'modal' => $this->getPartial('modal_delete')]);
        }else if($action == self::ENTITY_ACTION_EDIT){
            $this->entityActions[] = new EntityAction('lego.action.edit', ['icon'=>'pencil' ,'css_class' => 'btn-primary' ,'route' => $this->getConfigurator()->getPathRoute('edit'), 'params'=>$this->getConfigurator()->getPathParameters()]);
        }else if($action == self::ENTITY_ACTION_SHOW){
            $this->entityActions[] = new EntityAction('lego.action.show', ['icon'=>'eye' ,'css_class' => 'btn-success','route' => $this->getConfigurator()->getPathRoute('show'), 'params'=>$this->getConfigurator()->getPathParameters()]);
        }
    }

    public function addPredefinedBulkAction($action){
        if($action == self::BULK_ACTION_DELETE){
            $this->addBulkAction('lego.action.bulk_delete', ['route'=>$this->getConfigurator()->getPathRoute('bulk'), 'params'=>$this->getConfigurator()->getPathParameters(['type'=>'delete'])]);
        }
    }

    public function getFields(){
        return array_merge($this->mem->generateFields($this->getConfigurator()->getEntityName(), $this->getOption('fields')), $this->fields);
    }

    public function getEntityActions(){
        return $this->entityActions;
    }

    public function getBulkActions(){
        return $this->bulkActions;
    }

    public function getBulkAction($id){
        foreach($this->bulkActions as $action){
            if($action->getId() == $id){
                return $action;
            }
        }
        return false;
    }

    public function getTemplate($name = 'index'){
        return 'IdkLegoBundle:Component\\ListItemsComponent:'.$name.'.html.twig';
    }

    public function getTemplateParameters(){
        return ['component' => $this];
    }

    public function hasBulkActions(){
        return (count($this->bulkActions));
    }

    public function hideEntityActions(){
        return $this->getOption('hide_entity_actions', false);
    }

    public function addSorter($name, $type = 'ASC'){
        $this->sorters[] = [$name,$type];
    }

    public function getSorters(){
        return $this->sorters;
    }

    public function catchQueryBuilder(QueryBuilder $queryBuilder)
    {

        $queryHelper = new QueryHelper();
        foreach($this->getAllBreakers() as $breaker){
            if($breaker->isEnable()) {
                $path = $queryHelper->getPath($queryBuilder, 'b', $breaker->getFieldName());
                $queryBuilder->addOrderBy($path['alias'] . $path['column'], $breaker->getOrder());
            }
        }

        foreach($this->getSorters() as $sorter){
            $path = $queryHelper->getPath($queryBuilder, 'b', $sorter[0]);
            $typeSorter = (isset($sorter[1]) and strtoupper($sorter[1]) == 'DESC')? 'DESC':'ASC';
            $queryBuilder->addOrderBy($path['alias'] . $path['column'], $typeSorter);
        }

        if($this->request->get('id') and $this->getConfigurator()->getParent()) {
            $fieldAssociation = $this->getFieldAssociationOfParent();
            if($fieldAssociation) {

                if(isset($fieldAssociation['join'])){
                    $alias = $fieldAssociation['join'] . '_';
                    $queryBuilder->leftJoin('b.'.$fieldAssociation['join'], $fieldAssociation['alias'])->andWhere($fieldAssociation['name'] . ' = :' . $alias);
                }else {
                    $alias = $fieldAssociation['name'] . '_';
                    $queryBuilder->andWhere('b.' . $fieldAssociation['name'] . ' = :' . $alias);
                }
                $queryBuilder->setParameter($alias, $this->request->get('id'));
            }
        }
    }

    private function getFieldAssociationOfParent(){
        $fieldAssociation = null;
        $className = $this->getConfigurator()->getParent()->getClass();
        if($this->getOption('field_association', null)){
            return $this->getOption('field_association', null);
        }else {
            $c = $this->getConfigurator()->getClassMetaData();
            foreach($c->getAssociationMappings( ) as $association){
                if(isset($association['joinColumns']) and $association['targetEntity'] == $className) {
                    $fieldAssociation = ['name'=>$association['fieldName']];
                }elseif($association['targetEntity'] == $className){
                    $a = '_'.$association['fieldName'];
                    $fieldAssociation = ['name'=>$a.'.id', 'join' => $association['fieldName'], 'alias' => $a];
                }
            }
            return $fieldAssociation;
        }
    }

    public function canModifyNbEntityPerPage(){
        return $this->getOption('can_modify_nb_entity_per_page', false);
    }

    public function addBreaker($label, array $options = []){
        $breaker = new Breaker($label, $options);
        $this->breakers[$breaker->getId()] = $breaker;
        return $breaker;
    }

    public function getBreakers(){
        return $this->breakers;
    }

    public function hasBreakers(){
        return count($this->breakers);
    }

    public function getCurrentBreaker(){
        foreach($this->getBreakers() as $breaker){
            if($breaker->isEnable()) return $breaker;
        }
    }

    public function getCurrentBreakerCollection($entities){
        return $this->getCurrentBreaker()->calculateBreakerCollection($this->getConfigurator(), $entities);
    }

    private function initBreakers(){
        foreach($this->getAllBreakers() as $breaker){
            if($breaker->isEnable()){
                $breaker->enable();
            }
        }
        if($this->getComponentSessionStorage('breaker')){
            foreach($this->getBreakers() as $breaker){
                $breaker->disable();
            }
            if($this->getComponentSessionStorage('breaker') !== $this->getId()){
                foreach($this->getAllBreakers() as $breaker){
                    if($this->getComponentSessionStorage('breaker') == $breaker->getId()){
                        $breaker->enable();
                    }
                }
            }
        }
    }

    private function getBreakersChildren($breakers, &$array = []){
        foreach($breakers as $breaker){
            $array[] = $breaker;
            $this->getBreakersChildren($breaker->getBreakers(), $array);
        }
        return $array;
    }

    private function getAllBreakers(){
        return $this->getBreakersChildren($this->getBreakers());
    }
}
