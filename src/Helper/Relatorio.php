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

namespace Novosga\ReportsBundle\Helper;

use JsonSerializable;

/**
 * Relatorio.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
final class Relatorio implements JsonSerializable
{
    /** @param array<string,mixed> $dados */
    public function __construct(
        public readonly int $id,
        public readonly string $titulo,
        public readonly string $arquivo,
        public readonly string $opcoes = '',
        public array $dados = [],
    ) {
    }

    public function __toString()
    {
        return $this->titulo;
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'dados' => $this->dados,
        ];
    }
}
