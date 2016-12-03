<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\ReportsBundle\Helper;

/**
 * Relatorio.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class Relatorio implements \JsonSerializable
{
    protected $titulo;
    protected $dados;
    protected $arquivo;
    protected $opcoes;

    public function __construct($titulo, $arquivo, $opcoes = '')
    {
        $this->titulo = $titulo;
        $this->arquivo = $arquivo;
        $this->opcoes = $opcoes;
        $this->dados = [];
    }

    public function getTitulo()
    {
        return $this->titulo;
    }

    public function getArquivo()
    {
        return $this->arquivo;
    }

    public function getOpcoes()
    {
        return $this->opcoes;
    }

    public function getDados()
    {
        return $this->dados;
    }

    public function setDados($dados)
    {
        $this->dados = $dados;
    }

    public function jsonSerialize()
    {
        return [
            'titulo' => $this->titulo,
            'dados'  => $this->dados,
        ];
    }
}
