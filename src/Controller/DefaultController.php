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

namespace Novosga\ReportsBundle\Controller;

use Exception;
use Novosga\Entity\UnidadeInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\Http\Envelope;
use Novosga\ReportsBundle\Dto\GenerateChartDto;
use Novosga\ReportsBundle\Dto\GenerateReportDto;
use Novosga\ReportsBundle\Form\ChartType;
use Novosga\ReportsBundle\Form\ReportType;
use Novosga\ReportsBundle\Service\ChartService;
use Novosga\ReportsBundle\Service\ReportService;
use Novosga\Service\AtendimentoServiceInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

/**
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[Route("/", name: "novosga_reports_")]
class DefaultController extends AbstractController
{
    #[Route("/", name: "index", methods: ['GET'])]
    public function index(): Response
    {
        $unidade = $this->getUnidade();
        $chartForm = $this->createForm(ChartType::class);
        $reportForm = $this->createForm(ReportType::class);

        return $this->render('@NovosgaReports/default/index.html.twig', [
            'unidade' => $unidade,
            'chartForm' => $chartForm,
            'reportForm' => $reportForm,
        ]);
    }

    #[Route("chart", name: "chart", methods: ['POST'])]
    public function chart(
        Request $request,
        AtendimentoServiceInterface $atendimentoService,
        ChartService $chartService,
    ): Response {
        $envelope = new Envelope();

        $data = new GenerateChartDto();
        $form = $this
            ->createForm(ChartType::class, $data)
            ->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new Exception('Formulário inválido');
        }

        $unidade = $this->getUnidade();

        switch ($data->chart->id) {
            case 1:
                $data->chart->legendas = $atendimentoService->getSituacoes();
                $data->chart->dados = $chartService->getTotalAtendimentosStatus(
                    $data->chart->legendas,
                    $data->startDate,
                    $data->endDate,
                    $unidade,
                    $data->usuario
                );
                break;
            case 2:
                $data->chart->dados = $chartService->getTotalAtendimentosServico(
                    $data->startDate,
                    $data->endDate,
                    $unidade,
                    $data->usuario
                );
                break;
            case 3:
                $data->chart->dados = $chartService->getTempoMedioAtendimentos(
                    $data->startDate,
                    $data->endDate,
                    $unidade,
                    $data->usuario
                );
                break;
        }

        $data = $data->chart->jsonSerialize();
        $envelope->setData($data);

        return $this->json($envelope);
    }

    #[Route("/report", name: "report", methods: ['GET'])]
    public function report(
        Request $request,
        ReportService $reportService,
        #[MapQueryParameter] int $page = 1,
    ): Response {
        $data = new GenerateReportDto();
        $form = $this
            ->createForm(ReportType::class, $data)
            ->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new Exception('Formulário inválido');
        }

        $unidade = $this->getUnidade();

        $data->report->dados = match ($data->report->id) {
            1 => $reportService->getServicosDisponiveisGlobal(),
            2 => $reportService->getServicosDisponiveisUnidade($unidade),
            3 => $reportService->getServicosRealizados(
                $data->startDate,
                $data->endDate,
                $unidade,
                $data->usuario,
                $page
            ),
            4 => $reportService->getAtendimentosConcluidos(
                $data->startDate,
                $data->endDate,
                $unidade,
                $data->usuario,
                $page
            ),
            5 => $reportService->getAtendimentosStatus(
                $data->startDate,
                $data->endDate,
                $unidade,
                $data->usuario,
                $page
            ),
            6 => $reportService->getTempoMedioAtendentes($data->startDate, $data->endDate, $unidade, $page),
            7 => $reportService->getLotacoes($unidade, $page),
            8 => $reportService->getPerfis(),
            default => []
        };

        return $this->render("@NovosgaReports/default/relatorio.html.twig", [
            'dataInicial' => $data->startDate,
            'dataFinal' => $data->endDate,
            'relatorio' => $data->report,
            'page' => $page,
            'template' => "@NovosgaReports/relatorios/{$data->report->arquivo}.html.twig",
        ]);
    }

    private function getUnidade(): UnidadeInterface
    {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        return $unidade;
    }
}
