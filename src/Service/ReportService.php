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

namespace Novosga\ReportsBundle\Service;

use DateTimeInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\Repository\LotacaoRepositoryInterface;
use Novosga\Repository\PerfilRepositoryInterface;
use Novosga\Repository\ServicoRepositoryInterface;
use Novosga\Repository\ServicoUnidadeRepositoryInterface;
use Novosga\Repository\ViewAtendimentoCodificadoRepositoryInterface;
use Novosga\Repository\ViewAtendimentoRepositoryInterface;
use Novosga\Service\AtendimentoServiceInterface;
use Novosga\Service\ModuleServiceInterface;
use Novosga\Service\UsuarioServiceInterface;

/**
 * ReportService
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ReportService
{
    private const MAX_RESULTS = 1000;

    public function __construct(
        private readonly UsuarioServiceInterface $usuarioService,
        private readonly ModuleServiceInterface $moduleService,
        private readonly PerfilRepositoryInterface $perfilRepository,
        private readonly LotacaoRepositoryInterface $lotacaoRepository,
        private readonly ServicoRepositoryInterface $servicoRepository,
        private readonly ServicoUnidadeRepositoryInterface $servicoUnidadeRepository,
        private readonly ViewAtendimentoRepositoryInterface $viewAtendimentoRepository,
        private readonly ViewAtendimentoCodificadoRepositoryInterface $viewAtendimentoCodificadoRepository,
    ) {
    }

    /** @return array<string,mixed> */
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
     * @return array<string,mixed>
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

    /** @return array<string,mixed> */
    public function getServicosRealizados(
        DateTimeInterface $dataInicial,
        DateTimeInterface $dataFinal,
        UnidadeInterface $unidade,
        UsuarioInterface|int|null $usuario,
        int $page = 1,
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
            ->setParameter('dataInicial', $dataInicial->format('Y-m-d 00:00:00'))
            ->setParameter('dataFinal', $dataFinal->format('Y-m-d 23:59:59'))
            ->setParameter('unidade', $unidade)
            ->setMaxResults(self::MAX_RESULTS)
            ->setFirstResult(max(0, $page - 1) * self::MAX_RESULTS);

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
            'servicos' => $rs,
        ];

        return $dados;
    }

    /** @return array<string,mixed> */
    public function getAtendimentosConcluidos(
        DateTimeInterface $dataInicial,
        DateTimeInterface $dataFinal,
        UnidadeInterface $unidade,
        UsuarioInterface|int|null $usuario,
        int $page = 1,
    ): array {
        $qb = $this
            ->viewAtendimentoRepository
            ->createQueryBuilder('e')
            ->select([
                'e',
                's',
                'u',
            ])
            ->join('e.servico', 's')
            ->join('e.usuario', 'u')
            ->where('e.unidade = :unidade')
            ->andWhere('e.status = :status')
            ->andWhere('e.dataChegada >= :dataInicial')
            ->andWhere('e.dataChegada <= :dataFinal')
            ->orderBy('e.dataChegada', 'ASC')
            ->setParameter('status', AtendimentoServiceInterface::ATENDIMENTO_ENCERRADO)
            ->setParameter('dataInicial', $dataInicial->format('Y-m-d 00:00:00'))
            ->setParameter('dataFinal', $dataFinal->format('Y-m-d 23:59:59'))
            ->setParameter('unidade', $unidade)
            ->orderBy('e.id')
            ->setMaxResults(self::MAX_RESULTS)
            ->setFirstResult(max(0, $page - 1) * self::MAX_RESULTS);

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

    /** @return array<string,mixed> */
    public function getAtendimentosStatus(
        DateTimeInterface $dataInicial,
        DateTimeInterface $dataFinal,
        UnidadeInterface $unidade,
        UsuarioInterface|int|null $usuario,
        int $page = 1,
    ): array {
        $qb = $this
            ->viewAtendimentoRepository
            ->createQueryBuilder('e')
            ->select([
                'e',
                's',
                'u',
                'c',
            ])
            ->join('e.servico', 's')
            ->join('e.usuario', 'u')
            ->leftJoin('e.cliente', 'c')
            ->where('e.unidade = :unidade')
            ->andWhere('e.dataChegada >= :dataInicial')
            ->andWhere('e.dataChegada <= :dataFinal')
            ->orderBy('e.dataChegada', 'ASC')
            ->setParameter('dataInicial', $dataInicial->format('Y-m-d 00:00:00'))
            ->setParameter('dataFinal', $dataFinal->format('Y-m-d 23:59:59'))
            ->setParameter('unidade', $unidade)
            ->setMaxResults(self::MAX_RESULTS)
            ->orderBy('e.id')
            ->setFirstResult(max(0, $page - 1) * self::MAX_RESULTS);

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

    /** @return array<string,mixed> */
    public function getTempoMedioAtendentes(
        DateTimeInterface $dataInicial,
        DateTimeInterface $dataFinal,
        UnidadeInterface $unidade,
        int $page = 1,
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
            ->setParameter('dataInicial', $dataInicial->format('Y-m-d 00:00:00'))
            ->setParameter('dataFinal', $dataFinal->format('Y-m-d 23:59:59'))
            ->getQuery()
            ->setMaxResults(self::MAX_RESULTS)
            ->setFirstResult(max(0, $page - 1) * self::MAX_RESULTS)
            ->getResult();

        $dados = [
            'unidade' => $unidade->getNome(),
            'atendentes' => $rs,
        ];

        return $dados;
    }

    /**
     * Retorna todos os usuarios e perfis (lotação) por unidade.
     * @return array<string,mixed>
     */
    public function getLotacoes(UnidadeInterface $unidade, int $page = 1): array
    {
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
            ->setFirstResult(max(0, $page - 1) * self::MAX_RESULTS)
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
     * @return array<string,mixed>
     */
    public function getPerfis(): array
    {
        $dados = [];
        $perfis = $this->perfilRepository->findBy([], [ 'nome' => 'ASC' ]);
        $modulesMap = [];
        foreach ($this->moduleService->getInstalledModules() as $module) {
            $modulesMap[$module->key] = $module->displayName;
        }

        foreach ($perfis as $perfil) {
            $dados[$perfil->getId()] = [
                'perfil' => $perfil->getNome(),
                'permissoes' => array_map(
                    fn (string $modulo) => [ 'nome' => $modulesMap[$modulo] ?? $modulo, 'chave' => $modulo, ],
                    $perfil->getModulos(),
                ),
            ];
        }

        return $dados;
    }
}
