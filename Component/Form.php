<?php

namespace Idk\LegoBundle\Component;

use Idk\LegoBundle\AdminList\FilterBuilder;
use Idk\LegoBundle\ComponentResponse\ErrorComponentResponse;
use Idk\LegoBundle\ComponentResponse\SuccessComponentResponse;
use Idk\LegoBundle\FilterType\ORM\AbstractORMFilterType;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\QueryBuilder;
use Idk\LegoBundle\AdminList\Filter as Fi;


class Form extends Component{

    private $form;

    protected function init(){

    }

    protected function requiredOptions(){
        return ['form'];
    }

    public function getTemplate($name = 'index'){
        return 'IdkLegoBundle:Component\\FormComponent:'.$name.'.html.twig';
    }

    public function getTemplateParameters(){
        return ['form' => $this->form->createView(), 'theme' => $this->getOption('theme','IdkLegoBundle:Form:lle_base_fields.html.twig')];
    }

    public function bindRequest(Request $request){
        $entity = $this->getConfigurator()->newInstance();
        $this->form = $this->get('form.factory')->create($this->getOption('form'), $entity);
        $this->form->handleRequest($request);
        if ('POST' == $request->getMethod() and $this->form->isSubmitted()) {
            if ($this->form->isValid()) {
                $em = $this->getConfigurator()->getEntityManager();
                $em->persist($entity);
                $this->uploader(null,null);
                $em->flush();
                $this->resetForm();
                return new SuccessComponentResponse('lego.form.success');
            } else {
                return new ErrorComponentResponse('lego.form.error');
            }
        }
    }

    private function resetForm(){
        $this->form = $this->get('form.factory')->create($this->getOption('form'), $this->getConfigurator()->newInstance());
    }


    /* todo */
    protected function uploader($configurator,$obj){
        /*if($configurator->getUploadFileGetter()) {
            if(is_array($configurator->getUploadFileGetter())){
                foreach($configurator->getUploadFileGetter() as $method){
                    $uploadFile = call_user_func(array($obj, $method));
                    $this->upload($obj, $uploadFile);
                }
            }else{
                $uploadFile = call_user_func(array($obj, $configurator->getUploadFileGetter()));
                $this->upload($obj, $uploadFile);
            }
        }*/
    }

    /*protected function upload($obj, $uploadFile){
        if($uploadFile instanceof UploadedFile){
            $uploadableManager = $this->get('stof_doctrine_extensions.uploadable.manager');
            $uploadableManager->markEntityToUpload($obj, $uploadFile);
        }
    }*/





}
