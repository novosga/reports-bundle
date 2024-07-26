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

namespace Novosga\ReportsBundle\Dto;

use DateTimeInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\ReportsBundle\Helper\Grafico;

/**
 * GenerateChartDto
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
final class GenerateChartDto
{
    public function __construct(
        public ?Grafico $chart = null,
        public ?DateTimeInterface $startDate = null,
        public ?DateTimeInterface $endDate = null,
        public ?UsuarioInterface $usuario = null,
    ) {
    }
}
