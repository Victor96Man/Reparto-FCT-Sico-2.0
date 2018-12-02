<?php

namespace AppBundle\Form;


use AppBundle\Entity\RequestStudent;
use AppBundle\Services\RequestStudentsHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RequestStudentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('group', ChoiceType::class, array(
                'label' => "Grupo",
                'choices' => $options["groups"],
                'data' => $options["group_selected"]
                ))
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => RequestStudent::class,
            'groups' => null,
            'group_selected' => null,
            'convocatories' => null,
            'convocatory_selected' => null
        ]);

    }
}