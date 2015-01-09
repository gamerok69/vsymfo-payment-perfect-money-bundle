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

namespace vSymfo\Payment\PerfectMoneyBundle\Plugin;

use EgoPaySci;
use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\Util\Number;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Router;
use vSymfo\Component\Payments\EventDispatcher\PaymentEvent;
use vSymfo\Payment\PerfectMoneyBundle\Client\SciClient;

/**
 * Plugin płatności Perfect Money
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPerfectMoneyBundle
 */
class PerfectMoneyPlugin extends AbstractPlugin
{
    /**
     * @var SciClient
     */
    private $sci;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @param Router $router The router
     * @param SciClient $sciClient
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(Router $router, SciClient $sciClient, EventDispatcherInterface $dispatcher)
    {
        $this->sci = $sciClient;
        $this->router = $router;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Nazwa płatności
     * @return string
     */
    public function getName()
    {
        return 'perfect_money_sci_payment';
    }

    /**
     * {@inheritdoc}
     */
    public function processes($name)
    {
        return $this->getName() === $name;
    }

    /**
     * {@inheritdoc}
     */
    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        if ($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            throw $this->createPerfectMoneyRedirect($transaction);
        }

        $this->approve($transaction, $retry);
        $this->deposit($transaction, $retry);
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return ActionRequiredException
     */
    public function createPerfectMoneyRedirect(FinancialTransactionInterface $transaction)
    {
        $actionRequest = new ActionRequiredException('Redirecting to PerfectMoney.');
        $actionRequest->setFinancialTransaction($transaction);
        $instruction = $transaction->getPayment()->getPaymentInstruction();

        $actionRequest->setAction(new VisitUrl($this->router->generate('vsymfo_payment_perfectmoney_redirect', array(
            "id" => $instruction->getId()
        ))));

        return $actionRequest;
    }

    /**
     * Check that the extended data contains the needed values
     * before approving and depositing the transation
     *
     * @param ExtendedDataInterface $data
     * @throws BlockedException
     */
    protected function checkExtendedDataBeforeApproveAndDeposit(ExtendedDataInterface $data)
    {
        if (!$data->has('Payment_ID') || !$data->has('Amount') || !$data->has('Currency') || !$data->has('Type')) {
            throw new BlockedException("Awaiting extended data from PerfectMoney");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function approve(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();
        $this->checkExtendedDataBeforeApproveAndDeposit($data);

        /* Jakaś walidacja tutaj musi być
        if ($data->get('sStatus') == self::STATUS_COMPLETED
            || ($data->get('sId') == self::TEST_ID && $data->get('sStatus') == self::STATUS_TEST_SUCCESS)
        ) {*/
            $transaction->setReferenceNumber($data->get('Payment_ID'));
            $transaction->setProcessedAmount($data->get('Amount'));
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
        /*} else {
            $e = new FinancialException('Payment status unknow: ' . $data->get('sStatus'));
            $e->setFinancialTransaction($transaction);
            $transaction->setResponseCode('Unknown');
            $transaction->setReasonCode($data->get('sStatus'));
            throw $e;
        }*/
    }

    /**
     * {@inheritdoc}
     */
    public function deposit(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();

        if ($transaction->getResponseCode() !== PluginInterface::RESPONSE_CODE_SUCCESS
            || $transaction->getReasonCode() !== PluginInterface::REASON_CODE_SUCCESS
        ) {
            $e = new FinancialException('Peyment is not completed');
            $e->setFinancialTransaction($transaction);
            throw $e;
        }

        // różnica kwoty zatwierdzonej i kwoty wymaganej musi być równa zero
        // && nazwa waluty musi się zgadzać
        if (Number::compare($transaction->getProcessedAmount(), $transaction->getRequestedAmount()) === 0
            && $transaction->getPayment()->getPaymentInstruction()->getCurrency() == $data->get('Currency')
        ) {
            // wszystko ok
            // można zakakceptować zamówienie
            $event = new PaymentEvent($this->getName(), $transaction, $transaction->getPayment()->getPaymentInstruction());
            $this->dispatcher->dispatch('deposit', $event);
        } else {
            // coś się nie zgadza, nie można tego zakaceptować
            $e = new FinancialException('The deposit has not passed validation');
            $e->setFinancialTransaction($transaction);
            $transaction->setResponseCode('Unknown');
            $transaction->setReasonCode('UNKNOWN');
            throw $e;
        }
    }
}
