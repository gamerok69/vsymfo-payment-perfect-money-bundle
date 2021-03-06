<?php

/*
 * This file is part of the vSymfo package.
 *
 * website: www.vision-web.pl
 * (c) Rafał Mikołajun <rafal@vision-web.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vSymfo\Payment\PerfectMoneyBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use vSymfo\Payment\PerfectMoneyBundle\DependencyInjection\vSymfoPaymentPerfectMoneyExtension;

/**
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPerfectMoneyBundle
 */
class vSymfoPaymentPerfectMoneyBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new vSymfoPaymentPerfectMoneyExtension();
        }

        return $this->extension;
    }
}
