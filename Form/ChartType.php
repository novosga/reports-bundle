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

use DateTime;
use Novosga\ReportsBundle\Controller\DefaultController;
use Novosga\ReportsBundle\Helper\Grafico;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotNull;

class ChartType extends AbstractType
{
    /**
     * @var TranslatorInterface
     */
    private $translator;
    
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }
    
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $today     = new DateTime('today');
        $yesterday = new DateTime('yesterday');
        
        $chart1 = $this->translator->trans('chart.servicing_by_status', [], DefaultController::DOMAIN);
        $chart2 = $this->translator->trans('chart.servicing_by_service', [], DefaultController::DOMAIN);
        $chart3 = $this->translator->trans('chart.avg_servicing_time', [], DefaultController::DOMAIN);
        $placeholder = $this->translator->trans('report.select.placeholder', [], DefaultController::DOMAIN);
        
        $builder
            ->add('chart', ChoiceType::class, [
                'placeholder' => $placeholder,
                'choices' => [
                    new Grafico(1, $chart1, 'pie', 'date-range'),
                    new Grafico(2, $chart2, 'pie', 'date-range'),
                    new Grafico(3, $chart3, 'bar', 'date-range'),
                ],
                'choice_label' => function (Grafico $item) {
                    return $item->getTitulo();
                },
                'constraints' => [
                    new NotNull(),
                ]
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'constraints' => [
                    new NotNull(),
                ],
                'data' => $yesterday,
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'constraints' => [
                    new NotNull(),
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
