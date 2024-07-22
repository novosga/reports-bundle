<?php

declare(strict_types=1);

namespace Novosga\ReportsBundle\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\ReportsBundle\NovosgaReportsBundle;
use Novosga\Repository\LotacaoRepositoryInterface;
use Novosga\Repository\PerfilRepositoryInterface;
use Novosga\Repository\ServicoRepositoryInterface;
use Novosga\Repository\ServicoUnidadeRepositoryInterface;
use Novosga\Repository\ViewAtendimentoCodificadoRepositoryInterface;
use Novosga\Repository\ViewAtendimentoRepositoryInterface;
use Novosga\Service\AtendimentoServiceInterface;
use Novosga\Service\UsuarioServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportService
{
    private const MAX_RESULTS = 1000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly UsuarioServiceInterface $usuarioService,
        private readonly PerfilRepositoryInterface $perfilRepository,
        private readonly LotacaoRepositoryInterface $lotacaoRepository,
        private readonly ServicoRepositoryInterface $servicoRepository,
        private readonly ServicoUnidadeRepositoryInterface $servicoUnidadeRepository,
        private readonly ViewAtendimentoRepositoryInterface $viewAtendimentoRepository,
        private readonly ViewAtendimentoCodificadoRepositoryInterface $viewAtendimentoCodificadoRepository,
    ) {
    }

    /** @param string[] $situacoes */
    public function getTotalAtendimentosStatus(
        array $situacoes,
        DateTime $dataInicial,
        DateTime $dataFinal,
        UnidadeInterface $unidade,
        UsuarioInterface|int|null $usuario,
    ): array {
        $dados = [];
        $qb = $this
            ->viewAtendimentoRepository
            ->createQueryBuilder('e')
            ->select('COUNT(e)')
            ->where('e.dataChegada >= :inicio')
            ->andWhere('e.dataChegada <= :fim')
            ->andWhere('e.unidade = :unidade')
            ->andWhere('e.status = :status')
            ->setParameter('inicio', $dataInicial)
            ->setParameter('fim', $dataFinal)
            ->setParameter('unidade', $unidade->getId());

        if ($usuario) {
            $qb
                ->andWhere('e.usuario = :usuario')
                ->setParameter('usuario', $usuario);
        }

        $query = $qb->getQuery();
        
        foreach ($situacoes as $k => $v) {
            $query->setParameter('status', $k);
            $dados[$k] = (int) $query->getSingleScalarResult();
        }

        return $dados;
    }

    public function getTotalAtendimentosServico(
        DateTime $dataInicial,
        DateTime $dataFinal,
        UnidadeInterface $unidade,
        UsuarioInterface|int|null $usuario,
    ): array {
        $dados = [];
        $qb = $this
            ->viewAtendimentoRepository
            ->createQueryBuilder('a')
            ->select([
                's.nome as servico',
                'COUNT(a) as total',
            ])
            ->join('a.unidade', 'u')
            ->join('a.servico', 's')
            ->where('a.status = :status')
            ->andWhere('a.dataChegada >= :inicio')
            ->andWhere('a.dataChegada <= :fim')
            ->andWhere('a.unidade = :unidade')
            ->groupBy('s')
            ->setParameter('status', AtendimentoServiceInterface::ATENDIMENTO_ENCERRADO)
            ->setParameter('inicio', $dataInicial)
            ->setParameter('fim', $dataFinal)
            ->setParameter('unidade', $unidade->getId());

        if ($usuario) {
            $qb
                ->andWhere('a.usuario = :usuario')
                ->setParameter('usuario', $usuario);
        }

        $rs = $qb
            ->getQuery()
            ->getResult();
        
        foreach ($rs as $r) {
            $dados[$r['servico']] = $r['total'];
        }

        return $dados;
    }

    public function getTempoMedioAtendimentos(
        DateTime $dataInicial,
        DateTime $dataFinal,
        $unidade,
        $usuario
    ) {
        $domain = NovosgaReportsBundle::getDomain();
        $dados  = [];
        $tempos = [
            'espera'       => $this->translator->trans('label.wait_time', [], $domain),
            'deslocamento' => $this->translator->trans('label.dislocation_time', [], $domain),
            'atendimento'  => $this->translator->trans('label.servicing_time', [], $domain),
            'total'        => $this->translator->trans('label.total_time', [], $domain),
        ];
        
        $qb = $this
            ->viewAtendimentoRepository
            ->createQueryBuilder('a')
            ->select([
                'AVG(a.tempoEspera) as espera',
                'AVG(a.tempoDeslocamento) as deslocamento',
                'AVG(a.tempoAtendimento) as atendimento',
                'AVG(a.tempoPermanencia) as total',
            ])
            ->join('a.unidade', 'u')
            ->where('a.dataChegada >= :inicio')
            ->andWhere('a.dataChegada <= :fim')
            ->andWhere('a.unidade = :unidade')
            ->setParameter('inicio', $dataInicial)
            ->setParameter('fim', $dataFinal)
            ->setParameter('unidade', $unidade->getId());
           
        if ($usuario) {
            $qb
                ->andWhere('a.usuario = :usuario')
                ->setParameter('usuario', $usuario);
        }

        $rs = $qb
            ->getQuery()
            ->getResult();

        foreach ($rs as $r) {
            foreach ($tempos as $k => $v) {
                $dados[$v] = (int) $r[$k];
            }
        }

        return $dados;
    }

    public function getServicosDisponiveisGlobal(): array
    {
        $rs = $this
            ->servicoRepository
            ->createQueryBuilder('e')
            ->select([
                'e',
                'sub'
            ])
            ->leftJoin('e.subServicos', 'sub')
            ->where('e.mestre IS NULL')
            ->orderBy('e.nome', 'ASC')
            ->getQuery()
            ->getResult();

        return $rs;
    }

    /**
     * Retorna todos os servicos disponiveis para cada unidade.
     */
    public function getServicosDisponiveisUnidade(UnidadeInterface $unidade): array
    {
        $rs = $this
            ->servicoUnidadeRepository
            ->createQueryBuilder('e')
            ->select([
                'e',
                's',
                'sub'
            ])
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

    public function getServicosRealizados(
        DateTime $dataInicial,
        DateTime $dataFinal,
        UnidadeInterface $unidade,
        UsuarioInterface|int|null $usuario,
    ): array {
        $qb = $this
            ->viewAtendimentoCodificadoRepository
            ->createQueryBuilder('c')
            ->select([
                'COUNT(s.id) as total',
                's.nome',
            ])
            ->join('c.servico', 's')
            ->join('c.atendimento', 'e')
            ->where('e.unidade = :unidade')
            ->andWhere('e.dataChegada >= :dataInicial')
            ->andWhere('e.dataChegada <= :dataFinal')
            ->groupBy('s')
            ->orderBy('s.nome', 'ASC')
            ->setParameter('dataInicial', $dataInicial)
            ->setParameter('dataFinal', $dataFinal)
            ->setParameter('unidade', $unidade)
            ->setMaxResults(self::MAX_RESULTS);
           
        if ($usuario) {
            $qb
                ->andWhere('e.usuario = :usuario')
                ->setParameter('usuario', $usuario);
        }

        $rs = $qb
            ->getQuery()
            ->getResult();
        
        $dados = [
            'unidade'  => $unidade->getNome(),
            'usuario'  => $usuario,
            'servicos' => $rs,
        ];

        return $dados;
    }

    public function getAtendimentosConcluidos(
        DateTime $dataInicial,
        DateTime $dataFinal,
        UnidadeInterface $unidade,
        UsuarioInterface|int|null $usuario,
    ): array {
        $qb = $this
            ->viewAtendimentoRepository
            ->createQueryBuilder('e')
            ->where('e.unidade = :unidade')
            ->andWhere('e.status = :status')
            ->andWhere('e.dataChegada >= :dataInicial')
            ->andWhere('e.dataChegada <= :dataFinal')
            ->orderBy('e.dataChegada', 'ASC')
            ->setParameter('status', AtendimentoServiceInterface::ATENDIMENTO_ENCERRADO)
            ->setParameter('dataInicial', $dataInicial)
            ->setParameter('dataFinal', $dataFinal)
            ->setParameter('unidade', $unidade)
            ->setMaxResults(self::MAX_RESULTS);

        if ($usuario) {
            $qb
                ->andWhere('e.usuario = :usuario')
                ->setParameter('usuario', $usuario);
        }

        $rs = $qb
            ->getQuery()
            ->getResult();

        $dados = [
            'unidade' => $unidade->getNome(),
            'usuario' => $usuario,
            'atendimentos' => $rs,
        ];

        return $dados;
    }

    public function getAtendimentosStatus(
        DateTime $dataInicial,
        DateTime $dataFinal,
        UnidadeInterface $unidade,
        UsuarioInterface|int|null $usuario,
    ): array {
        $qb = $this
            ->viewAtendimentoRepository
            ->createQueryBuilder('e')
            ->where('e.unidade = :unidade')
            ->andWhere('e.dataChegada >= :dataInicial')
            ->andWhere('e.dataChegada <= :dataFinal')
            ->orderBy('e.dataChegada', 'ASC')
            ->setParameter('dataInicial', $dataInicial)
            ->setParameter('dataFinal', $dataFinal)
            ->setParameter('unidade', $unidade)
            ->setMaxResults(self::MAX_RESULTS);

        if ($usuario) {
            $qb
                ->andWhere('e.usuario = :usuario')
                ->setParameter('usuario', $usuario);
        }

        $rs = $qb
            ->getQuery()
            ->getResult();

        $dados = [
            'unidade' => $unidade->getNome(),
            'usuario' => $usuario,
            'atendimentos' => $rs,
        ];

        return $dados;
    }

    public function getTempoMedioAtendentes(
        DateTime $dataInicial,
        DateTime $dataFinal,
        UnidadeInterface $unidade,
    ): array {
        $rs = $this
            ->viewAtendimentoRepository
            ->createQueryBuilder('a')
            ->select([
                "CONCAT(u.nome, CONCAT(' ', u.sobrenome)) as atendente",
                'COUNT(a) as total',
                'AVG(a.tempoEspera) as espera',
                'AVG(a.tempoDeslocamento) as deslocamento',
                'AVG(a.tempoAtendimento) as atendimento',
                'AVG(a.tempoPermanencia) as tempoTotal',
            ])
            ->join('a.usuario', 'u')
            ->where('a.unidade = :unidade')
            ->andWhere('a.dataChegada >= :dataInicial')
            ->andWhere('a.dataChegada <= :dataFinal')
            ->andWhere('a.dataFim IS NOT NULL')
            ->groupBy('u')
            ->orderBy('u.nome', 'ASC')
            ->setParameter('unidade', $unidade)
            ->setParameter('dataInicial', $dataInicial)
            ->setParameter('dataFinal', $dataFinal)
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
     */
    public function getLotacoes(
        UnidadeInterface $unidade,
        string $nomeServico = ''
    ): array {
        $lotacoes = $this
            ->lotacaoRepository
            ->createQueryBuilder('e')
            ->select([
                'e',
                'usu',
                'uni',
                'c',
            ])
            ->join('e.usuario', 'usu')
            ->join('e.unidade', 'uni')
            ->join('e.perfil', 'c')
            ->where('uni = :unidade')
            ->orderBy('usu.nome', 'ASC')
            ->setParameter('unidade', $unidade)
            ->getQuery()
            ->setMaxResults(self::MAX_RESULTS)
            ->getResult();
        
        $servicos = [];
        foreach ($lotacoes as $lotacao) {
            $servicos[$lotacao->getUsuario()->getId()] = $this
                ->usuarioService
                ->getServicosUnidade($lotacao->getUsuario(), $unidade);
        }
        
        $dados = [
            'unidade' => $unidade->getNome(),
            'lotacoes' => $lotacoes,
            'servicos' => $servicos,
        ];

        return $dados;
    }

    /**
     * Retorna todos os perfis e suas permissões.
     */
    public function getPerfis(): array
    {
        $dados = [];
        $perfis = $this->perfilRepository->findBy([], [ 'nome' => 'ASC' ]);

        foreach ($perfis as $perfil) {
            $dados[$perfil->getId()] = [
                'perfil' => $perfil->getNome(),
                'permissoes' => $perfil->getModulos(),
            ];
        }

        return $dados;
    }
}
