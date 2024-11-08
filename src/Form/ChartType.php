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
use Novosga\Entity\UsuarioInterface;
use Novosga\ReportsBundle\Dto\GenerateChartDto;
use Novosga\ReportsBundle\Helper\Grafico;
use Novosga\ReportsBundle\NovosgaReportsBundle;
use Novosga\Repository\UsuarioRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotNull;

class ChartType extends AbstractType
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
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
                'choice_label' => fn (?Grafico $item) => $item?->titulo,
                'constraints' => [
                    new NotNull(),
                ],
            ])
            ->add('startDate', DateType::class, [
                'data' => $yesterday,
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [
                    new NotNull(),
                ],
            ])
            ->add('endDate', DateType::class, [
                'data' => $today,
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [
                    new NotNull(),
                ],
            ])
            ->add('usuario', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Todos',
                'choices' => $this->usuarioRepository->findAll(),
                'choice_label' => fn (?UsuarioInterface $item) => $item?->getLogin(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'class' => GenerateChartDto::class,
                'csrf_protection' => false,
            ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
