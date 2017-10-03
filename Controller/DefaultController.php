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
use Novosga\Http\Envelope;
use Novosga\Service\AtendimentoService;
use Novosga\Util\DateUtil;
use Novosga\ReportsBundle\Helper\Grafico;
use Novosga\ReportsBundle\Helper\Relatorio;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class DefaultController extends Controller
{
    const MAX_RESULTS = 1000;

    private $graficos;
    private $relatorios;

    public function __construct()
    {
        $this->graficos = [
            1 => new Grafico(_('Atendimentos por status'), 'pie', 'date-range'),
            2 => new Grafico(_('Atendimentos por serviço'), 'pie', 'date-range'),
            3 => new Grafico(_('Tempo médio do atendimento'), 'bar', 'date-range'),
        ];
        $this->relatorios = [
            1 => new Relatorio(_('Serviços Disponíveis - Global'), 'servicos_disponiveis_global'),
            2 => new Relatorio(_('Serviços Disponíveis - Unidade'), 'servicos_disponiveis_unidade'),
            3 => new Relatorio(_('Serviços codificados'), 'servicos_codificados', 'date-range'),
            4 => new Relatorio(_('Atendimentos concluídos'), 'atendimentos_concluidos', 'date-range'),
            5 => new Relatorio(_('Atendimentos em todos os status'), 'atendimentos_status', 'date-range'),
            6 => new Relatorio(_('Tempos médios por Atendente'), 'tempo_medio_atendentes', 'date-range'),
            7 => new Relatorio(_('Lotações'), 'lotacoes', 'unidade'),
            8 => new Relatorio(_('Perfis'), 'perfis'),
        ];
    }

    /**
     *
     * @param Request $request
     *
     * @Route("/", name="novosga_reports_index")
     */
    public function indexAction(Request $request)
    {
        $unidade = $this->getUnidade();
        $date = new DateTime();
        
        $endDate = $date->format(_('d/m/Y'));
        $date->sub(new \DateInterval('P1D'));
        $startDate = $date->format(_('d/m/Y'));
        
        return $this->render('@NovosgaReports/default/index.html.twig', [
            'unidade' => $unidade,
            'relatorios' => $this->relatorios,
            'graficos' => $this->graficos,
            'statusAtendimento' => AtendimentoService::situacoes(),
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     *
     * @param Request $request
     *
     * @Route("/chart/{id}", name="novosga_reports_chart")
     */
    public function chartAction(Request $request, $id)
    {
        $envelope = new Envelope();
        try {
            $dataInicial = $request->get('inicial');
            $dataFinal = $request->get('final').' 23:59:59';
            $unidade = $this->getUnidade();
            
            if (!isset($this->graficos[$id])) {
                throw new Exception(_('Gráfico inválido'));
            }
            
            $grafico = $this->graficos[$id];
            
            switch ($id) {
                case 1:
                    $grafico->setLegendas(AtendimentoService::situacoes());
                    $grafico->setDados($this->totalAtendimentosStatus($dataInicial, $dataFinal, $unidade));
                    break;
                case 2:
                    $grafico->setDados($this->totalAtendimentosServico($dataInicial, $dataFinal, $unidade));
                    break;
                case 3:
                    $grafico->setDados($this->tempoMedioAtendimentos($dataInicial, $dataFinal, $unidade));
                    break;
            }
            $data = $grafico->jsonSerialize();
            $envelope->setData($data);
        } catch (\Exception $e) {
            $envelope->exception($e);
        }

        return $this->json($envelope);
    }

    /**
     *
     * @param Request $request
     *
     * @Route("/report", name="novosga_reports_report")
     */
    public function reportAction(Request $request)
    {
        $id = (int) $request->get('relatorio');
        $dataInicial = $request->get('inicial');
        $dataFinal = $request->get('final');
        $unidade = $this->getUnidade();
        $params = [];
        
        if (isset($this->relatorios[$id])) {
            $relatorio = $this->relatorios[$id];
            $params['dataInicial'] = DateUtil::format($dataInicial, _('d/m/Y'));
            $params['dataFinal'] = DateUtil::format($dataFinal, _('d/m/Y'));
            
            $dataFinal = $dataFinal.' 23:59:59';
            switch ($id) {
                case 1:
                    $relatorio->setDados($this->servicosDisponiveisGlobal());
                    break;
                case 2:
                    $relatorio->setDados($this->servicosDisponiveisUnidade($unidade));
                    break;
                case 3:
                    $relatorio->setDados($this->servicosCodificados($dataInicial, $dataFinal, $unidade));
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
                    $relatorio->setDados($this->lotacoes($unidade));
                    break;
                case 8:
                    $relatorio->setDados($this->perfis());
                    break;
            }
            $params['relatorio'] = $relatorio;
            $params['page'] = "NovosgaReportsBundle:relatorios:{$relatorio->getArquivo()}.html.twig";
        }
        
        return $this->render("NovosgaReports/default/relatorio.html.twig", $params);
    }

    private function totalAtendimentosStatus($dataInicial, $dataFinal, $unidade)
    {
        $dados = [];
        $status = AtendimentoService::situacoes();
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
                    'inicio' => $dataInicial
                ]);
                $query->setParameter('fim', $dataFinal);
                $query->setParameter('unidade', $unidade->getId());
        
        foreach ($status as $k => $v) {
            $query->setParameter('status', $k);
            $rs = $query->getSingleResult();
            $dados[$k] = (int) $rs['total'];
        }

        return $dados;
    }

    private function totalAtendimentosServico($dataInicial, $dataFinal, $unidade)
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
                    'inicio' => $dataInicial,
                    'fim' => $dataFinal,
                    'unidade' => $unidade->getId()
                ])
                ->getResult();
        
        foreach ($rs as $r) {
            $dados[$r['servico']] = $r['total'];
        }

        return $dados;
    }

    private function tempoMedioAtendimentos($dataInicial, $dataFinal, $unidade)
    {
        $dados = [];
        $tempos = [
            'espera'       => _('Tempo de Espera'),
            'deslocamento' => _('Tempo de Deslocamento'),
            'atendimento'  => _('Tempo de Atendimento'),
            'total'        => _('Tempo Total'),
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
                ->createQuery($dql);
        $query->setParameter('inicio', $dataInicial);
        $query->setParameter('fim', $dataFinal);
        $query->setParameter('unidade', $unidade->getId());
            
        $rs = $query->getResult();
        foreach ($rs as $r) {
            try {
                // se der erro tentando converter a data do banco para segundos, assume que ja esta em segundos
                // Isso é necessário para manter a compatibilidade entre os bancos
                foreach ($tempos as $k => $v) {
                    $dados[$v] = DateUtil::timeToSec($r[$k]);
                }
            } catch (\Exception $e) {
                foreach ($tempos as $k => $v) {
                    $dados[$v] = (int) $r[$k];
                }
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

    private function servicosCodificados($dataInicial, $dataFinal, $unidade)
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
                    'dataFinal' => $dataFinal,
                    'unidade' => $unidade,
                ])
                ->setMaxResults(self::MAX_RESULTS)
                ->getResult();
        
        $dados = [
            'unidade'  => $unidade->getNome(),
            'servicos' => $rs,
        ];

        return $dados;
    }

    private function atendimentosConcluidos($dataInicial, $dataFinal, $unidade)
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
                    'status' => AtendimentoService::ATENDIMENTO_ENCERRADO,
                    'dataInicial' => $dataInicial,
                    'dataFinal' => $dataFinal,
                    'unidade' => $unidade
                ])
                ->setMaxResults(self::MAX_RESULTS)
                ->getResult();
        
        $dados = [
            'unidade'      => $unidade->getNome(),
            'atendimentos' => $rs,
        ];

        return $dados;
    }

    private function atendimentosStatus($dataInicial, $dataFinal, $unidade)
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
                    'dataFinal' => $dataFinal,
                    'unidade' => $unidade
                ])
                ->setMaxResults(self::MAX_RESULTS)
                ->getResult();
        
        $dados = [
            'unidade'      => $unidade->getNome(),
            'atendimentos' => $rs,
        ];

        return $dados;
    }

    private function tempoMedioAtendentes($dataInicial, $dataFinal, $unidade)
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
    private function lotacoes($unidade, $nomeServico = '')
    {
        /* @var $usuarioService \Novosga\Service\UsuarioService */
        $usuarioService = $this->get('Novosga\Service\UsuarioService');

        $lotacoes = $this
                ->getDoctrine()
                ->getManager()
                ->createQueryBuilder()
                ->select([
                    'e', 'usu', 'uni', 'c'
                ])
                ->from(\Novosga\Entity\Lotacao::class, 'e')
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
     * @return \Novosga\Entity\Unidade
     */
    private function getUnidade()
    {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        return $unidade;
    }
}
