<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\ReportsBundle\Form;

use Novosga\ReportsBundle\Helper\Grafico;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChartType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $today     = new \DateTime('today');
        $yesterday = new \DateTime('yesterday');
        
        $builder
            ->add('chart', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'placeholder' => 'Selecione',
                'choices' => [
                    new Grafico(1, _('Atendimentos por status'), 'pie', 'date-range'),
                    new Grafico(2, _('Atendimentos por serviço'), 'pie', 'date-range'),
                    new Grafico(3, _('Tempo médio do atendimento'), 'bar', 'date-range'),
                ],
                'choice_label' => function (Grafico $item) {
                    return $item->getTitulo();
                },
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotNull(),
                ]
            ])
            ->add('startDate', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotNull(),
                ],
                'data' => $yesterday,
            ])
            ->add('endDate', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotNull(),
                ],
                'data' => $today,
            ])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
    }
    
    public function getBlockPrefix()
    {
        return null;    
    }
}
