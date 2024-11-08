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
use Novosga\ReportsBundle\Dto\GenerateReportDto;
use Novosga\ReportsBundle\Helper\Relatorio;
use Novosga\ReportsBundle\NovosgaReportsBundle;
use Novosga\Repository\UsuarioRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotNull;

class ReportType extends AbstractType
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepository,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $domain = NovosgaReportsBundle::getDomain();
        $today = new DateTime('today');
        $yesterday = new DateTime('yesterday');

        $report1 = $this->translator->trans('report.services_available_global', [], $domain);
        $report2 = $this->translator->trans('report.services_available_unity', [], $domain);
        $report3 = $this->translator->trans('report.services_performed', [], $domain);
        $report4 = $this->translator->trans('report.finished_servicing', [], $domain);
        $report5 = $this->translator->trans('report.servicing_all_status', [], $domain);
        $report6 = $this->translator->trans('report.avg_time_servicing', [], $domain);
        $report7 = $this->translator->trans('report.lotations', [], $domain);
        $report8 = $this->translator->trans('report.roles', [], $domain);

        $builder
            ->add('report', ChoiceType::class, [
                'placeholder' => 'Selecione',
                'choices' => [
                    new Relatorio(1, $report1, 'servicos_disponiveis_global'),
                    new Relatorio(2, $report2, 'servicos_disponiveis_unidade'),
                    new Relatorio(3, $report3, 'servicos_realizados', 'date-range,user'),
                    new Relatorio(4, $report4, 'atendimentos_concluidos', 'date-range,user'),
                    new Relatorio(5, $report5, 'atendimentos_status', 'date-range,user'),
                    new Relatorio(6, $report6, 'tempo_medio_atendentes', 'date-range'),
                    new Relatorio(7, $report7, 'lotacoes', 'unidade'),
                    new Relatorio(8, $report8, 'perfis'),
                ],
                'choice_label' => fn (?Relatorio $item) => $item?->titulo,
                'choice_attr' => fn (?Relatorio $item) => [ 'data-opcoes' => $item?->opcoes ],
                'constraints' => [
                    new NotNull(),
                ]
            ])
            ->add('startDate', DateType::class, [
                'data' => $yesterday,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('endDate', DateType::class, [
                'data' => $today,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('usuario', ChoiceType::class, [
                'placeholder' => 'Todos',
                'choices' => $this->usuarioRepository->findAll(),
                'choice_label'  => fn (?UsuarioInterface $usuario) => $usuario?->getLogin(),
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'class' => GenerateReportDto::class,
                'method' => 'get',
                'action' => $this->router->generate('novosga_reports_report'),
                'attr' => [
                    'target' => '_blank'
                ],
                'csrf_protection' => false,
                'allow_extra_fields' => true,
            ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
