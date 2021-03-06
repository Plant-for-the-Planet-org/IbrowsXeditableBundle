<?php

namespace Ibrows\XeditableBundle\Mapper;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Validator\ValidatorInterface;

class XeditableFormMapper extends AbstractFormXeditableMapper
{

    /**
     * @var EngineInterface
     */
    protected $engine;

    /**
     * @var ValidatorInterface
     */
    protected $validator;


    /**
     * @var array
     */
    protected $parameters = array();

    /**
     * @param FormInterface $form
     * @param EngineInterface $engine
     * @param string $url
     * @param null $validator
     * @param bool $validateExtra
     * @throws \Exception
     */
    public function __construct(FormInterface $form, EngineInterface $engine, $url = null, $validator = null, $validateExtra = false)
    {
        $this->form = $form;
        $this->engine = $engine;
        if ($validateExtra and !$validator) {
            throw new \Exception("Validator must not be set if validateExtra enabled");
        }
        if ($validateExtra) {
            $this->validator = $validator;
        }
        parent::__construct($url);
    }


    protected function validate(FormInterface $subform){
        $path = (string)$subform->getPropertyPath();
        $parentData = null;
        if ($subform->getParent()) {
            $parentData = $subform->getParent()->getData();
        } else {
            $parentData = $subform->getData();
        }
        $constraintViolationList = $this->validator->validateProperty($parentData, $path);
        if ($constraintViolationList->count() == 0) {
            return;
        } else {
            foreach ($constraintViolationList as $violation) {
                $subform->addError(
                    new FormError(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getMessageParameters(),
                        $violation->getMessagePluralization()
                    )
                );
            }

        }
    }

    /**
     * @param Request $request
     * @return Response|void
     */
    public function handleRequest(Request $request)
    {
        //remove not in path children
        $path = $request->request->get('path');
        $subform = $this->getFormByPath($path,null, true);

        $this->form->submit($request, false);

        if ($this->validator) {
            $this->validate($subform);
        } else {
            if ($this->form->isValid()) {
                return;
            }
        }

        $this->renderError($subform);
    }




    /**
     * @param string $path
     * @param array $attributes
     * @param array $options
     * @throws \Exception
     * @return string
     */
    public function render($path = null, array $attributes = array(), array $options = array())
    {
        if (!$form = $this->getFormByPath($path)) {
            throw new \Exception("Path $path invalid");
        }

        $value = isset($options['value']) ? $options['value'] : $form->getData();
        $template = $this->getRenderTemplate($options);

        $attributes = array_merge(
            array(
                'id' => 'xeditable_' . $this->form->getName() . '_' . $path
            ),
            $form->getConfig()->getOption('attr', array()),
            $attributes
        );

        if($this->getRenderFormPrototype($options)){
            $rendredFormXeditable = $this->renderXeditable($path);
            $attributes[ 'data-form'] = $rendredFormXeditable;
        }
        return $this->engine->render(
            $template,
            $this->getViewParameters(
                $path,
                $value,
                $attributes,
                $options
            )
        );
    }





    /**
     * @param string $path
     * @param array $attributes
     * @param array $options
     * @throws \Exception
     * @return string
     */
    public function renderXeditable($path = null, array $attributes = array(), array $options = array())
    {

        if (!$form = $this->getFormByPath($path,clone $this->form, true)) {
            throw new \Exception("Path $path invalid");
        }

        $template = $this->getFormTemplate($options);

        return $this->engine->render($template, $this->getEditParameters($form, $attributes, $options));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'ibrows_xeditable_form';
    }



}