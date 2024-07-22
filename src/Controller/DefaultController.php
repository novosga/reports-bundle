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

use DateTime;
use Exception;
use Novosga\Entity\UnidadeInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\Http\Envelope;
use Novosga\ReportsBundle\Form\ChartType;
use Novosga\ReportsBundle\Form\ReportType;
use Novosga\ReportsBundle\Service\ReportService;
use Novosga\Service\AtendimentoServiceInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
        $unidade    = $this->getUnidade();
        $chartForm  = $this->createChartForm();
        $reportForm = $this->createReportForm();
        
        return $this->render('@NovosgaReports/default/index.html.twig', [
            'unidade'    => $unidade,
            'chartForm'  => $chartForm->createView(),
            'reportForm' => $reportForm->createView(),
        ]);
    }

    #[Route("chart", name: "chart", methods: ['POST'])]
    public function chart(
        Request $request,
        AtendimentoServiceInterface $atendimentoService,
        ReportService $reportService,
    ): Response {
        $envelope = new Envelope();
        
        $form = $this
            ->createChartForm()
            ->handleRequest($request);
        
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new Exception('Formulário inválido');
        }
        
        $grafico = $form->get('chart')->getData();
        $dataInicial = $form->get('startDate')->getData();
        $dataFinal = $form->get('endDate')->getData();
        $usuario = $form->get('usuario')->getData();
        $unidade = $this->getUnidade();
        
        $dataInicial->setTime(0, 0, 0);
        $dataFinal->setTime(23, 59, 59);

        switch ($grafico->getId()) {
            case 1:
                $situacoes = $atendimentoService->getSituacoes();
                $dados = $reportService->getTotalAtendimentosStatus($situacoes, $dataInicial, $dataFinal, $unidade, $usuario);
                $grafico->setLegendas($situacoes);
                $grafico->setDados($dados);
                break;
            case 2:
                $dados = $reportService->getTotalAtendimentosServico($dataInicial, $dataFinal, $unidade, $usuario);
                $grafico->setDados($dados);
                break;
            case 3:
                $dados = $reportService->getTempoMedioAtendimentos($dataInicial, $dataFinal, $unidade, $usuario);
                $grafico->setDados($dados);
                break;
        }
        
        $data = $grafico->jsonSerialize();
        $envelope->setData($data);

        return $this->json($envelope);
    }

    #[Route("/report", name: "report", methods: ['GET'])]
    public function report(
        Request $request,
        ReportService $reportService,
    ): Response {
        $form = $this
            ->createReportForm()
            ->handleRequest($request);
        
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new Exception('Formulário inválido');
        }
        
        $relatorio   = $form->get('report')->getData();
        $dataInicial = $form->get('startDate')->getData();
        $dataFinal   = $form->get('endDate')->getData();
        $usuario     = $form->get('usuario')->getData();
        $unidade     = $this->getUnidade();
        
        if (!$dataInicial) {
            $dataInicial = new DateTime();
        }
        
        if (!$dataFinal) {
            $dataFinal = new DateTime();
        }
        
        $dataInicial->setTime(0, 0, 0);
        $dataFinal->setTime(23, 59, 59);
        
        $dados = match ($relatorio->getId()) {
            1 => $reportService->getServicosDisponiveisGlobal(),
            2 => $reportService->getServicosDisponiveisUnidade($unidade),
            3 => $reportService->getServicosRealizados($dataInicial, $dataFinal, $unidade, $usuario),
            4 => $reportService->getAtendimentosConcluidos($dataInicial, $dataFinal, $unidade, $usuario),
            5 => $reportService->getAtendimentosStatus($dataInicial, $dataFinal, $unidade, $usuario),
            6 => $reportService->getTempoMedioAtendentes($dataInicial, $dataFinal, $unidade),
            7 => $reportService->getLotacoes($unidade),
            8 => $reportService->getPerfis(),
            default => []
        };

        $relatorio->setDados($dados);
        
        return $this->render("@NovosgaReports/default/relatorio.html.twig", [
            'dataInicial' => $dataInicial->format('d/m/Y'),
            'dataFinal'   => $dataFinal->format('d/m/Y'),
            'relatorio'   => $relatorio,
            'page'        => "@NovosgaReports/relatorios/{$relatorio->getArquivo()}.html.twig",
        ]);
    }
    
    private function createChartForm()
    {
        $form = $this->createForm(ChartType::class, null, [
            'csrf_protection' => false,
        ]);
        
        return $form;
    }
    
    private function createReportForm()
    {
        $form = $this->createForm(ReportType::class, null, [
            'method' => 'get',
            'action' => $this->generateUrl('novosga_reports_report'),
            'attr' => [
                'target' => '_blank'
            ],
            'csrf_protection' => false,
        ]);
        
        return $form;
    }

    private function getUnidade(): UnidadeInterface
    {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        return $unidade;
    }
}
