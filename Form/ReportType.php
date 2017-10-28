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

use Novosga\ReportsBundle\Helper\Relatorio;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReportType extends AbstractType
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
            ->add('report', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'placeholder' => 'Selecione',
                'choices' => [
                    new Relatorio(1, _('Serviços Disponíveis - Global'), 'servicos_disponiveis_global'),
                    new Relatorio(2, _('Serviços Disponíveis - Unidade'), 'servicos_disponiveis_unidade'),
                    new Relatorio(3, _('Serviços codificados'), 'servicos_codificados', 'date-range'),
                    new Relatorio(4, _('Atendimentos concluídos'), 'atendimentos_concluidos', 'date-range'),
                    new Relatorio(5, _('Atendimentos em todos os status'), 'atendimentos_status', 'date-range'),
                    new Relatorio(6, _('Tempos médios por Atendente'), 'tempo_medio_atendentes', 'date-range'),
                    new Relatorio(7, _('Lotações'), 'lotacoes', 'unidade'),
                    new Relatorio(8, _('Perfis'), 'perfis'),
                ],
                'choice_label' => function (Relatorio $item) {
                    return $item->getTitulo();
                },
                'choice_attr' => function (Relatorio $item) {
                    return [
                        'data-opcoes' => $item->getOpcoes(),
                    ];
                },
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotNull(),
                ]
            ])
            ->add('startDate', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'data' => $yesterday,
            ])
            ->add('endDate', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
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
