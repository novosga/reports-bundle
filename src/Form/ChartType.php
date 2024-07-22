<?php

declare(strict_types=1);

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
use Doctrine\ORM\EntityRepository;
use App\Entity\Usuario;
use Novosga\ReportsBundle\Helper\Grafico;
use Novosga\ReportsBundle\NovosgaReportsBundle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotNull;

class ChartType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $domain = NovosgaReportsBundle::getDomain();
        $today = new DateTime('today');
        $yesterday = new DateTime('yesterday');
        
        $chart1 = $this->translator->trans('chart.servicing_by_status', [], $domain);
        $chart2 = $this->translator->trans('chart.servicing_by_service', [], $domain);
        $chart3 = $this->translator->trans('chart.avg_servicing_time', [], $domain);
        
        $builder
            ->add('chart', ChoiceType::class, [
                'placeholder' => 'Selecione',
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
                'html5' => false,
                'constraints' => [
                    new NotNull(),
                ],
                'data' => $yesterday,
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'constraints' => [
                    new NotNull(),
                ],
                'data' => $today,
            ])
            ->add('usuario', EntityType::class, [
                'class'         => Usuario::class,
                'required'      => false,
                'placeholder'   => 'Todos',
                'query_builder' => function (EntityRepository $repo) {
                    return $repo
                        ->createQueryBuilder('e')
                        ->orderBy('e.nome', 'ASC');
                },
            ])
        ;
    }

    public function getBlockPrefix()
    {
        return '';
    }
}
