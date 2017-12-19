<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\ReportsBundle;

use Novosga\Module\BaseModule;

class NovosgaReportsBundle extends BaseModule
{
    public function getIconName()
    {
        return 'bar-chart';
    }

    public function getDisplayName()
    {
        return 'module.name';
    }

    public function getHomeRoute()
    {
        return 'novosga_reports_index';
    }
}
