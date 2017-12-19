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
use Novosga\Entity\Lotacao;
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
    public function indexAction(Request $request)
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
    public function chartAction(
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
                $grafico->setLegendas($situacoes);
                $grafico->setDados($this->totalAtendimentosStatus($situacoes, $dataInicial, $dataFinal, $unidade));
                break;
            case 2:
                $grafico->setDados($this->totalAtendimentosServico($dataInicial, $dataFinal, $unidade));
                break;
            case 3:
                $grafico->setDados($this->tempoMedioAtendimentos($translator, $dataInicial, $dataFinal, $unidade));
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
    public function reportAction(Request $request, UsuarioService $usuarioService)
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
        $form = $this->createForm(ChartType::class);
        
        return $form;
    }
    
    private function createReportForm()
    {
        $form = $this->createForm(ReportType::class, null, [
            'method' => 'get',
            'action' => $this->generateUrl('novosga_reports_report'),
            'attr' => [
                'target' => '_blank'
            ]
        ]);
        
        return $form;
    }

    private function totalAtendimentosStatus($situacoes, DateTime $dataInicial, DateTime $dataFinal, $unidade)
    {
        $dados = [];
        $query = $this
                ->getDoctrine()
                ->getManager()
                ->createQuery("
                    SELECT
                        COUNT(e) as total
                    FROM
                        Novosga\Entity\AtendimentoHistorico e
                    WHERE
                        e.dataChegada >= :inicio AND
                        e.dataChegada <= :fim AND
                        e.unidade = :unidade AND
                        e.status = :status
                ")
                ->setParameters([
                    'inicio'  => $dataInicial,
                    'fim'     => $dataFinal,
                    'unidade' => $unidade->getId()
                ]);
        
        foreach ($situacoes as $k => $v) {
            $query->setParameter('status', $k);
            $rs = $query->getSingleResult();
            $dados[$k] = (int) $rs['total'];
        }

        return $dados;
    }

    private function totalAtendimentosServico(DateTime $dataInicial, DateTime $dataFinal, $unidade)
    {
        $dados = [];
        $rs = $this
                ->getDoctrine()
                ->getManager()
                ->createQuery("
                    SELECT
                        s.nome as servico,
                        COUNT(a) as total
                    FROM
                        Novosga\Entity\AtendimentoHistorico a
                        JOIN a.unidade u
                        JOIN a.servico s
                    WHERE
                        a.status = :status AND
                        a.dataChegada >= :inicio AND
                        a.dataChegada <= :fim AND
                        a.unidade = :unidade
                    GROUP BY
                        s
                ")
                ->setParameters([
                    'status' => AtendimentoService::ATENDIMENTO_ENCERRADO,
                    'inicio'  => $dataInicial,
                    'fim'     => $dataFinal,
                    'unidade' => $unidade->getId()
                ])
                ->getResult();
        
        foreach ($rs as $r) {
            $dados[$r['servico']] = $r['total'];
        }

        return $dados;
    }

    private function tempoMedioAtendimentos(TranslatorInterface $translator, DateTime $dataInicial, DateTime $dataFinal, $unidade)
    {
        $dados = [];
        $tempos = [
            'espera'       => $translator->trans('label.wait_time', [], self::DOMAIN),
            'deslocamento' => $translator->trans('label.dislocation_time', [], self::DOMAIN),
            'atendimento'  => $translator->trans('label.servicing_time', [], self::DOMAIN),
            'total'        => $translator->trans('label.total_time', [], self::DOMAIN),
        ];
        $dql = "
            SELECT
                AVG(a.dataChamada - a.dataChegada) as espera,
                AVG(a.dataInicio - a.dataChamada) as deslocamento,
                AVG(a.dataFim - a.dataInicio) as atendimento,
                AVG(a.dataFim - a.dataChegada) as total
            FROM
                Novosga\Entity\AtendimentoHistorico a
                JOIN a.unidade u
            WHERE
                a.dataChegada >= :inicio AND
                a.dataChegada <= :fim AND
                a.unidade = :unidade
        ";
        $query = $this
            ->getDoctrine()
            ->getManager()
            ->createQuery($dql)
            ->setParameters([
                'inicio'  => $dataInicial,
                'fim'     => $dataFinal,
                'unidade' => $unidade->getId(),
            ]);
            
        $rs = $query->getResult();
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
            ->createQuery("
                SELECT
                    e
                FROM
                    Novosga\Entity\Servico e
                    LEFT JOIN e.subServicos sub
                WHERE
                    e.mestre IS NULL
                ORDER BY
                    e.nome
            ")
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
            ->createQuery("
                SELECT
                    e
                FROM
                    Novosga\Entity\ServicoUnidade e
                    JOIN e.servico s
                    LEFT JOIN s.subServicos sub
                WHERE
                    s.mestre IS NULL AND
                    e.ativo = TRUE AND
                    e.unidade = :unidade
                ORDER BY
                    s.nome
            ")
            ->setParameter('unidade', $unidade)
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
                    ->createQuery("
                SELECT
                    COUNT(s.id) as total,
                    s.nome
                FROM
                    Novosga\Entity\AtendimentoCodificadoHistorico c
                    JOIN c.servico s
                    JOIN c.atendimento e
                WHERE
                    e.unidade = :unidade AND
                    e.dataChegada >= :dataInicial AND
                    e.dataChegada <= :dataFinal
                GROUP BY
                    s
                ORDER BY
                    s.nome
            ")
            ->setParameters([
                'dataInicial' => $dataInicial,
                'dataFinal'   => $dataFinal,
                'unidade'     => $unidade,
            ])
            ->setMaxResults(self::MAX_RESULTS)
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
            ->createQuery("
                SELECT
                    e
                FROM
                    Novosga\Entity\AtendimentoHistorico e
                WHERE
                    e.unidade = :unidade AND
                    e.status = :status AND
                    e.dataChegada >= :dataInicial AND
                    e.dataChegada <= :dataFinal
                ORDER BY
                    e.dataChegada
            ")
            ->setParameters([
                'status'      => AtendimentoService::ATENDIMENTO_ENCERRADO,
                'dataInicial' => $dataInicial,
                'dataFinal'   => $dataFinal,
                'unidade'     => $unidade
            ])
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
            ->createQuery("
                SELECT
                    e
                FROM
                    Novosga\Entity\AtendimentoHistorico e
                WHERE
                    e.unidade = :unidade AND
                    e.dataChegada >= :dataInicial AND
                    e.dataChegada <= :dataFinal
                ORDER BY
                    e.dataChegada
            ")
            ->setParameters([
                'dataInicial' => $dataInicial,
                'dataFinal'   => $dataFinal,
                'unidade'     => $unidade
            ])
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
        $dados = [];
        $rs = $this
            ->getDoctrine()
            ->getManager()
            ->createQuery("
                SELECT
                    CONCAT(u.nome, CONCAT(' ', u.sobrenome)) as atendente,
                    COUNT(a) as total,
                    AVG(a.dataChamada - a.dataChegada) as espera,
                    AVG(a.dataInicio - a.dataChamada) as deslocamento,
                    AVG(a.dataFim - a.dataInicio) as atendimento,
                    AVG(a.dataFim - a.dataChegada) as tempoTotal
                FROM
                    Novosga\Entity\AtendimentoHistorico a
                    JOIN a.usuario u
                WHERE
                    a.unidade = :unidade AND
                    a.dataChegada >= :dataInicial AND
                    a.dataChegada <= :dataFinal AND
                    a.dataFim IS NOT NULL
                GROUP BY
                    u
                ORDER BY
                    u.nome
            ")
            ->setParameters([
                'unidade' => $unidade,
                'dataInicial' => $dataInicial,
                'dataFinal' => $dataFinal,
            ])
            ->setMaxResults(self::MAX_RESULTS)
            ->getResult();
        
        $dados = [
            'unidade' => $unidade->getNome(),
            'atendentes' => []
        ];
        
        foreach ($rs as $r) {
            $d = [
                'atendente' => $r['atendente'],
                'total'     => $r['total'],
            ];
            try {
                // se der erro tentando converter a data do banco para segundos, assume que ja esta em segundos
                // Isso é necessário para manter a compatibilidade entre os bancos
                $d['espera'] = DateUtil::timeToSec($r['espera']);
                $d['deslocamento'] = DateUtil::timeToSec($r['deslocamento']);
                $d['atendimento'] = DateUtil::timeToSec($r['atendimento']);
                $d['tempoTotal'] = DateUtil::timeToSec($r['tempoTotal']);
            } catch (\Exception $e) {
                $d['espera'] = $r['espera'];
                $d['deslocamento'] = $r['deslocamento'];
                $d['atendimento'] = $r['atendimento'];
                $d['tempoTotal'] = $r['tempoTotal'];
            }
            $dados['atendentes'][] = $d;
        }

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
                'e', 'usu', 'uni', 'c'
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
        $dados = [];
        $query = $this
            ->getDoctrine()
            ->getManager()
            ->createQuery("SELECT e FROM Novosga\Entity\Perfil e ORDER BY e.nome");
        $perfis = $query->getResult();
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
