<?php

namespace Idk\LegoBundle\Annotation\Entity\Form;

use Idk\LegoBundle\Form\Type\JsonType as BaseType;


/**
 * @Annotation
 */
class JsonForm extends AbstractForm
{

    public function __construct($options){
        parent::__construct($options);
        $this->type = BaseType::class;
    }

}
