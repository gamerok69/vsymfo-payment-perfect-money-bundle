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

namespace vSymfo\Payment\PerfectMoneyBundle\Controller;

use JMS\Payment\CoreBundle\Entity\PaymentInstruction;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use vSymfo\Component\Payments\Utility\ViewUtility;

/**
 * EgoPay - przekierowanie do płatności
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPerfectMoneyBundle
 */
class RedirectController extends Controller
{
    /**
     * @param Request $request
     * @param PaymentInstruction $instruction
     * @return Response
     */
    public function redirectAction(Request $request, PaymentInstruction $instruction)
    {
        $sci = $this->get('payment.perfectmoney.client');
        $transaction = $instruction->getPendingTransaction();
        $extendedData = $transaction->getExtendedData();

        if (!$extendedData->has('payment_url')) {
            throw new \RuntimeException('You must configure a payment_url.');
        }

        if (!$extendedData->has('nopayment_url')) {
            throw new \RuntimeException('You must configure a nopayment_url.');
        }

        $html = $this->container->get('twig')->render('vSymfoPaymentPerfectMoneyBundle:Default:redirect.html.twig', array(
            "PAYEE_ACCOUNT" => $sci->getPayeeAccount(),
            "PAYMENT_AMOUNT" => $transaction->getRequestedAmount(),
            "PAYMENT_UNITS" => $instruction->getCurrency(),
            "PAYMENT_URL" => $extendedData->get('payment_url'),
            "NOPAYMENT_URL" => $extendedData->get('nopayment_url'),
            "PAYEE_NAME" => $this->container->getParameter('vsymfo_payment_perfectmoney.payee_name')
        ));

        return new Response(ViewUtility::redirectView($html));
    }
}
