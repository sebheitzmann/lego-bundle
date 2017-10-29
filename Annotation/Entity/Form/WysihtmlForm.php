<?php

namespace Idk\LegoBundle\Annotation\Entity\Form;

use Idk\LegoBundle\Form\Type\WysihtmlType;


/**
 * @Annotation
 */
class WysihtmlForm extends AbstractForm
{

    public function __construct($options){
        parent::__construct($options);
        $this->type = WysihtmlType::class;
    }

}
