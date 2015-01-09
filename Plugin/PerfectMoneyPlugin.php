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
        $extendedData = $transaction->getExtendedData();

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
        /*if (!$data->has('sStatus') || !$data->has('sId') || !$data->has('fAmount')) {
            throw new BlockedException("Awaiting extended data from EgoPay");
        }*/
    }

    /**
     * {@inheritdoc}
     */
    public function approve(FinancialTransactionInterface $transaction, $retry)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function deposit(FinancialTransactionInterface $transaction, $retry)
    {

    }
}
