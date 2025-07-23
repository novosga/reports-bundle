<?php

namespace Novosga\ReportsBundle\Twig;

use DateTime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Description of ReportExtension
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ReportExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('secToDate', array($this, 'secToDateFilter')),
        ];
    }

    public function secToDateFilter(int|float|string $seconds): DateTime
    {
        $dt = new DateTime();
        $dt->setTime(0, 0, 0);
        $dt->add(new \DateInterval("PT" . (int)round((float)$seconds) . "S"));

        return $dt;
    }
}
