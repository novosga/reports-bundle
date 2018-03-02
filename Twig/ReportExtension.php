<?php

namespace Novosga\ReportsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Description of ReportExtension
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ReportExtension extends AbstractExtension
{
    public function getFilters()
    {
        return array(
            new TwigFilter('secToDate', array($this, 'secToDateFilter')),
        );
    }

    public function secToDateFilter($seconds)
    {
        $s  = (int) $seconds;
        $dt = new \DateTime();
        $dt->setTime(0, 0, 0);
        $dt->add(new \DateInterval("PT{$s}S"));
        
        return $dt;
    }
}
