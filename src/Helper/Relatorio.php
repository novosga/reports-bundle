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

/**
 * Relatorio.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class Relatorio implements \JsonSerializable
{
    protected $id;
    protected $titulo;
    protected $dados;
    protected $arquivo;
    protected $opcoes;

    public function __construct($id, $titulo, $arquivo, $opcoes = '')
    {
        $this->id = $id;
        $this->titulo = $titulo;
        $this->arquivo = $arquivo;
        $this->opcoes = $opcoes;
        $this->dados = [];
    }
    
    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
        
        return $this;
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
    
    public function __toString()
    {
        return $this->titulo;
    }

    public function jsonSerialize()
    {
        return [
            'id'     => $this->id,
            'titulo' => $this->titulo,
            'dados'  => $this->dados,
        ];
    }
}
