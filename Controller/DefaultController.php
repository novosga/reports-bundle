<?php

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
use Novosga\Entity\AtendimentoCodificadoHistorico;
use Novosga\Entity\AtendimentoHistorico;
use Novosga\Entity\Lotacao;
use Novosga\Entity\Perfil;
use Novosga\Entity\Servico;
use Novosga\Entity\ServicoUnidade;
use Novosga\Entity\Unidade;
use Novosga\Http\Envelope;
use Novosga\ReportsBundle\Form\ChartType;
use Novosga\ReportsBundle\Form\ReportType;
use Novosga\Service\AtendimentoService;
use Novosga\Service\UsuarioService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

/**
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class DefaultController extends Controller
{
    const DOMAIN = 'NovosgaReportsBundle';
    const MAX_RESULTS = 1000;

    /**
     *
     * @param Request $request
     *
     * @Route("/", name="novosga_reports_index")
     */
    public function index(Request $request)
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

    /**
     *
     * @param Request $request
     *
     * @Route("/chart", name="novosga_reports_chart")
     */
    public function chart(
        Request $request,
        AtendimentoService $atendimentoService,
        TranslatorInterface $translator
    ) {
        $envelope = new Envelope();
        
        $form = $this->createChartForm();
        $form->handleRequest($request);
        
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new Exception('Formulário inválido');
        }
        
        $grafico     = $form->get('chart')->getData();
        $dataInicial = $form->get('startDate')->getData();
        $dataFinal   = $form->get('endDate')->getData();
        $unidade     = $this->getUnidade();
        
        $dataInicial->setTime(0, 0, 0);
        $dataFinal->setTime(23, 59, 59);

        switch ($grafico->getId()) {
            case 1:
                $situacoes = $atendimentoService->situacoes();
                $dados     = $this->totalAtendimentosStatus($situacoes, $dataInicial, $dataFinal, $unidade);
                $grafico->setLegendas($situacoes);
                $grafico->setDados($dados);
                break;
            case 2:
                $dados = $this->totalAtendimentosServico($dataInicial, $dataFinal, $unidade);
                $grafico->setDados($dados);
                break;
            case 3:
                $dados = $this->tempoMedioAtendimentos($translator, $dataInicial, $dataFinal, $unidade);
                $grafico->setDados($dados);
                break;
        }
        
        $data = $grafico->jsonSerialize();
        $envelope->setData($data);

        return $this->json($envelope);
    }

    /**
     *
     * @param Request $request
     *
     * @Route("/report", name="novosga_reports_report")
     */
    public function report(Request $request, UsuarioService $usuarioService)
    {
        $form = $this->createReportForm();
        $form->handleRequest($request);
        
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new Exception('Formulário inválido');
        }
        
        $relatorio   = $form->get('report')->getData();
        $dataInicial = $form->get('startDate')->getData();
        $dataFinal   = $form->get('endDate')->getData();
        $unidade     = $this->getUnidade();
        
        if (!$dataInicial) {
            $dataInicial = new DateTime();
        }
        
        if (!$dataFinal) {
            $dataFinal = new DateTime();
        }
        
        $dataInicial->setTime(0, 0, 0);
        $dataFinal->setTime(23, 59, 59);
        
        switch ($relatorio->getId()) {
            case 1:
                $relatorio->setDados($this->servicosDisponiveisGlobal());
                break;
            case 2:
                $relatorio->setDados($this->servicosDisponiveisUnidade($unidade));
                break;
            case 3:
                $relatorio->setDados($this->servicosRealizados($dataInicial, $dataFinal, $unidade));
                break;
            case 4:
                $relatorio->setDados($this->atendimentosConcluidos($dataInicial, $dataFinal, $unidade));
                break;
            case 5:
                $relatorio->setDados($this->atendimentosStatus($dataInicial, $dataFinal, $unidade));
                break;
            case 6:
                $relatorio->setDados($this->tempoMedioAtendentes($dataInicial, $dataFinal, $unidade));
                break;
            case 7:
                $relatorio->setDados($this->lotacoes($usuarioService, $unidade));
                break;
            case 8:
                $relatorio->setDados($this->perfis());
                break;
        }
        
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

    private function totalAtendimentosStatus($situacoes, DateTime $dataInicial, DateTime $dataFinal, $unidade)
    {
        $dados = [];
        $query = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select('COUNT(e)')
            ->from(AtendimentoHistorico::class, 'e')
            ->where('e.dataChegada >= :inicio')
            ->andWhere('e.dataChegada <= :fim')
            ->andWhere('e.unidade = :unidade')
            ->andWhere('e.status = :status')
            ->setParameters([
                'inicio'  => $dataInicial,
                'fim'     => $dataFinal,
                'unidade' => $unidade->getId()
            ])
            ->getQuery();
        
        foreach ($situacoes as $k => $v) {
            $query->setParameter('status', $k);
            $dados[$k] = (int) $query->getSingleScalarResult();
        }

        return $dados;
    }

    private function totalAtendimentosServico(DateTime $dataInicial, DateTime $dataFinal, $unidade)
    {
        $dados = [];
        $rs = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select([
                's.nome as servico',
                'COUNT(a) as total',
            ])
            ->from(AtendimentoHistorico::class, 'a')
            ->join('a.unidade', 'u')
            ->join('a.servico', 's')
            ->where('a.status = :status')
            ->andWhere('a.dataChegada >= :inicio')
            ->andWhere('a.dataChegada <= :fim')
            ->andWhere('a.unidade = :unidade')
            ->groupBy('s')
            ->setParameters([
                'status' => AtendimentoService::ATENDIMENTO_ENCERRADO,
                'inicio'  => $dataInicial,
                'fim'     => $dataFinal,
                'unidade' => $unidade->getId()
            ])
            ->getQuery()
            ->getResult();
        
        foreach ($rs as $r) {
            $dados[$r['servico']] = $r['total'];
        }

        return $dados;
    }

    private function tempoMedioAtendimentos(
        TranslatorInterface $translator,
        DateTime $dataInicial,
        DateTime $dataFinal,
        $unidade
    ) {
        $dados  = [];
        $tempos = [
            'espera'       => $translator->trans('label.wait_time', [], self::DOMAIN),
            'deslocamento' => $translator->trans('label.dislocation_time', [], self::DOMAIN),
            'atendimento'  => $translator->trans('label.servicing_time', [], self::DOMAIN),
            'total'        => $translator->trans('label.total_time', [], self::DOMAIN),
        ];
        
        $rs = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select([
                'AVG(a.tempoEspera) as espera',
                'AVG(a.tempoDeslocamento) as deslocamento',
                'AVG(a.tempoAtendimento) as atendimento',
                'AVG(a.tempoPermanencia) as total',
            ])
            ->from(AtendimentoHistorico::class, 'a')
            ->join('a.unidade', 'u')
            ->where('a.dataChegada >= :inicio')
            ->andWhere('a.dataChegada <= :fim')
            ->andWhere('a.unidade = :unidade')
            ->setParameters([
                'inicio'  => $dataInicial,
                'fim'     => $dataFinal,
                'unidade' => $unidade->getId(),
            ])
            ->getQuery()
            ->getResult();
            
        foreach ($rs as $r) {
            foreach ($tempos as $k => $v) {
                $dados[$v] = (int) $r[$k];
            }
        }

        return $dados;
    }

    private function servicosDisponiveisGlobal()
    {
        $rs = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select([
                'e',
                'sub'
            ])
            ->from(Servico::class, 'e')
            ->leftJoin('e.subServicos', 'sub')
            ->where('e.mestre IS NULL')
            ->orderBy('e.nome', 'ASC')
            ->getQuery()
            ->getResult();

        return $rs;
    }

    /**
     * Retorna todos os servicos disponiveis para cada unidade.
     *
     * @return array
     */
    private function servicosDisponiveisUnidade($unidade)
    {
        $rs = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select([
                'e',
                's',
                'sub'
            ])
            ->from(ServicoUnidade::class, 'e')
            ->join('e.servico', 's')
            ->leftJoin('s.subServicos', 'sub')
            ->where('s.mestre IS NULL')
            ->andWhere('e.ativo = TRUE')
            ->andWhere('e.unidade = :unidade')
            ->orderBy('s.nome', 'ASC')
            ->setParameter('unidade', $unidade)
            ->getQuery()
            ->getResult();
        
        $dados = [
            'unidade'  => $unidade->getNome(),
            'servicos' => $rs,
        ];

        return $dados;
    }

    private function servicosRealizados(DateTime $dataInicial, DateTime $dataFinal, $unidade)
    {
        $rs = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select([
                'COUNT(s.id) as total',
                's.nome',
            ])
            ->from(AtendimentoCodificadoHistorico::class, 'c')
            ->join('c.servico', 's')
            ->join('c.atendimento', 'e')
            ->where('e.unidade = :unidade')
            ->andWhere('e.dataChegada >= :dataInicial')
            ->andWhere('e.dataChegada <= :dataFinal')
            ->groupBy('s')
            ->orderBy('s.nome', 'ASC')
            ->setParameters([
                'dataInicial' => $dataInicial,
                'dataFinal'   => $dataFinal,
                'unidade'     => $unidade,
            ])
            ->setMaxResults(self::MAX_RESULTS)
            ->getQuery()
            ->getResult();
        
        $dados = [
            'unidade'  => $unidade->getNome(),
            'servicos' => $rs,
        ];

        return $dados;
    }

    private function atendimentosConcluidos(DateTime $dataInicial, DateTime $dataFinal, $unidade)
    {
        $rs = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select('e')
            ->from(AtendimentoHistorico::class, 'e')
            ->where('e.unidade = :unidade')
            ->andWhere('e.status = :status')
            ->andWhere('e.dataChegada >= :dataInicial')
            ->andWhere('e.dataChegada <= :dataFinal')
            ->orderBy('e.dataChegada', 'ASC')
            ->setParameters([
                'status'      => AtendimentoService::ATENDIMENTO_ENCERRADO,
                'dataInicial' => $dataInicial,
                'dataFinal'   => $dataFinal,
                'unidade'     => $unidade
            ])
            ->getQuery()
            ->setMaxResults(self::MAX_RESULTS)
            ->getResult();
        
        $dados = [
            'unidade'      => $unidade->getNome(),
            'atendimentos' => $rs,
        ];

        return $dados;
    }

    private function atendimentosStatus(DateTime $dataInicial, DateTime $dataFinal, $unidade)
    {
        $rs = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select('e')
            ->from(AtendimentoHistorico::class, 'e')
            ->where('e.unidade = :unidade')
            ->andWhere('e.dataChegada >= :dataInicial')
            ->andWhere('e.dataChegada <= :dataFinal')
            ->orderBy('e.dataChegada', 'ASC')
            ->setParameters([
                'dataInicial' => $dataInicial,
                'dataFinal'   => $dataFinal,
                'unidade'     => $unidade
            ])
            ->getQuery()
            ->setMaxResults(self::MAX_RESULTS)
            ->getResult();
        
        $dados = [
            'unidade'      => $unidade->getNome(),
            'atendimentos' => $rs,
        ];

        return $dados;
    }

    private function tempoMedioAtendentes(DateTime $dataInicial, DateTime $dataFinal, $unidade)
    {
        $rs = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select([
                "CONCAT(u.nome, CONCAT(' ', u.sobrenome)) as atendente",
                'COUNT(a) as total',
                'AVG(a.tempoEspera) as espera',
                'AVG(a.tempoDeslocamento) as deslocamento',
                'AVG(a.tempoAtendimento) as atendimento',
                'AVG(a.tempoPermanencia) as tempoTotal',
            ])
            ->from(AtendimentoHistorico::class, 'a')
            ->join('a.usuario', 'u')
            ->where('a.unidade = :unidade')
            ->andWhere('a.dataChegada >= :dataInicial')
            ->andWhere('a.dataChegada <= :dataFinal')
            ->andWhere('a.dataFim IS NOT NULL')
            ->groupBy('u')
            ->orderBy('u.nome', 'ASC')
            ->setParameters([
                'unidade'     => $unidade,
                'dataInicial' => $dataInicial,
                'dataFinal'   => $dataFinal,
            ])
            ->getQuery()
            ->setMaxResults(self::MAX_RESULTS)
            ->getResult();
        
        $dados = [
            'unidade' => $unidade->getNome(),
            'atendentes' => $rs,
        ];

        return $dados;
    }

    /**
     * Retorna todos os usuarios e perfis (lotação) por unidade.
     *
     * @return array
     */
    private function lotacoes(UsuarioService $usuarioService, $unidade, $nomeServico = '')
    {
        $lotacoes = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select([
                'e',
                'usu',
                'uni',
                'c',
            ])
            ->from(Lotacao::class, 'e')
            ->join('e.usuario', 'usu')
            ->join('e.unidade', 'uni')
            ->join('e.perfil', 'c')
            ->where('uni = :unidade')
            ->orderBy('usu.nome', 'ASC')
            ->setParameters([
                'unidade' => $unidade
            ])
            ->getQuery()
            ->setMaxResults(self::MAX_RESULTS)
            ->getResult();
        
        $servicos = [];
        foreach ($lotacoes as $lotacao) {
            $servicos[$lotacao->getUsuario()->getId()] = $usuarioService->servicos($lotacao->getUsuario(), $unidade);
        }
        
        $dados = [
            'unidade'  => $unidade->getNome(),
            'lotacoes' => $lotacoes,
            'servicos' => $servicos,
        ];

        return $dados;
    }

    /**
     * Retorna todos os perfis e suas permissões.
     *
     * @return array
     */
    private function perfis()
    {
        $dados  = [];
        $perfis = $this
            ->getDoctrine()
            ->getRepository(Perfil::class)
            ->findBy([], [ 'nome' => 'ASC' ]);
        
        foreach ($perfis as $perfil) {
            $dados[$perfil->getId()] = [
                'perfil'      => $perfil->getNome(),
                'permissoes' => $perfil->getModulos(),
            ];
        }

        return $dados;
    }
    
    /**
     * @return Unidade
     */
    private function getUnidade()
    {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        return $unidade;
    }
}
