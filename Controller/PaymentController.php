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
 * Kontroler płatności
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPerfectMoneyBundle
 */
class PaymentController extends Controller
{
    /**
     * Przekierowanie do systemu płatności
     * @param PaymentInstruction $instruction
     * @return Response
     */
    public function redirectAction(PaymentInstruction $instruction)
    {
        $sci = $this->get('payment.perfectmoney.client');

        if (null === $transaction = $instruction->getPendingTransaction()) {
            throw new \RuntimeException('No pending transaction found for the payment instruction');
        }

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
            "PAYEE_NAME" => $this->container->getParameter('vsymfo_payment_perfectmoney.payee_name'),
            "STATUS_URL" => $this->generateUrl('vsymfo_payment_perfectmoney_callback', array(
                "id" => $instruction->getId()
            ), true),
        ));

        return new Response(ViewUtility::redirectView($html));
    }

    /**
     * Zatwierdzanie płatności
     * @param Request $request
     * @param PaymentInstruction $instruction
     * @return Response
     * @throws \EgoPayException
     */
    public function callbackAction(Request $request, PaymentInstruction $instruction)
    {
        $date = new \DateTime();
        file_put_contents('payment-log.txt', file_get_contents('payment-log.txt') . $date->format('Y-m-d H:i:s') . ": " . json_encode($request->request->all()) . "\n\n");

        $client = $this->get('payment.perfectmoney.client');
        $response = $client->getPaymentResponse($request);

        if (null === $transaction = $instruction->getPendingTransaction()) {
            throw new \RuntimeException('No pending transaction found for the payment instruction');
        }

        $em = $this->getDoctrine()->getManager();
        $extendedData = $transaction->getExtendedData();
        $extendedData->set('Time', $response['Time']);
        $extendedData->set('Type', $response['Type']);
        $extendedData->set('Batch', $response['Batch']);
        $extendedData->set('Currency', $response['Currency']);
        $extendedData->set('Amount', $response['Amount']);
        $extendedData->set('Fee', $response['Fee']);
        $extendedData->set('Payer_Account', $response['Payer_Account']);
        $extendedData->set('Payee_Account', $response['Payee_Account']);
        $extendedData->set('Payment_ID', $response['Payment_ID']);
        $extendedData->set('Memo', $response['Memo']);
        $em->persist($transaction);

        $payment = $transaction->getPayment();
        $result = $this->get('payment.plugin_controller')->approveAndDeposit($payment->getId(), (float)$response['Payment_ID']);
        if (is_object($ex = $result->getPluginException())) {
            throw $ex;
        }

        $em->flush();

        return new Response('OK');
    }
}
