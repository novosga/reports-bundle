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
use Novosga\ReportsBundle\NovosgaReportsBundle;
use Novosga\Repository\ViewAtendimentoRepositoryInterface;
use Novosga\Service\AtendimentoServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * ChartService
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ChartService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ViewAtendimentoRepositoryInterface $viewAtendimentoRepository,
    ) {
    }

    /**
     * @param string[] $situacoes
     * @return array<string,mixed>
     */
    public function getTotalAtendimentosStatus(
        array $situacoes,
        DateTimeInterface $dataInicial,
        DateTimeInterface $dataFinal,
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
            ->setParameter('inicio', $dataInicial->format('Y-m-d 00:00:00'))
            ->setParameter('fim', $dataFinal->format('Y-m-d 23:59:59'))
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

    /** @return array<string,mixed> */
    public function getTotalAtendimentosServico(
        DateTimeInterface $dataInicial,
        DateTimeInterface $dataFinal,
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
            ->setParameter('inicio', $dataInicial->format('Y-m-d 00:00:00'))
            ->setParameter('fim', $dataFinal->format('Y-m-d 23:59:59'))
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

    /** @return array<string,mixed> */
    public function getTempoMedioAtendimentos(
        DateTimeInterface $dataInicial,
        DateTimeInterface $dataFinal,
        UnidadeInterface $unidade,
        UsuarioInterface|int|null $usuario,
    ) {
        $domain = NovosgaReportsBundle::getDomain();
        $dados  = [];
        $tempos = [
            'espera' => $this->translator->trans('label.wait_time', [], $domain),
            'deslocamento' => $this->translator->trans('label.dislocation_time', [], $domain),
            'atendimento' => $this->translator->trans('label.servicing_time', [], $domain),
            'total' => $this->translator->trans('label.total_time', [], $domain),
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
            ->setParameter('inicio', $dataInicial->format('Y-m-d 00:00:00'))
            ->setParameter('fim', $dataFinal->format('Y-m-d 23:59:59'))
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
}
