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

namespace vSymfo\Payment\PerfectMoneyBundle\Client;

use \DateTime;
use Symfony\Component\HttpFoundation\Request;
use vSymfo\Payment\PerfectMoneyBundle\Client\Exception\InvalidResponseException;
use vSymfo\Payment\PerfectMoneyBundle\Client\Exception\InvalidPayeeAccountException;
use vSymfo\Payment\PerfectMoneyBundle\Client\Exception\ParametersNotMatchException;
use vSymfo\Payment\PerfectMoneyBundle\Client\Exception\HashNotMatchException;

/**
 * Klient Perfect Money
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPerfectMoneyBundle
 */
class SciClient
{
    /**
     * Your Perfect Money member ID
     *
     * @var string
     */
    private $pmMemberId;

    /**
     * Password you use to login your account
     *
     * @var string
     */
    private $pmPassword;

    /**
     * Constant below contains md5-hashed alternate passhrase in upper case.
     * You can generate it like this:
     * strtoupper(md5('your_passphrase'));
     * Where `your_passphrase' is Alternate Passphrase you entered
     * in your Perfect Money account.
     *
     * @var string
     */
    private $alternatePhraseHash;

    /**
     * The merchant’s Perfect Money® account to which the payment is to be made.
     * For example U9007123.
     *
     * @var string
     */
    private $payeeAccount;

    /**
     * @param string $pmMemberId
     * @param string $pmPassword
     * @param string $alternatePhraseHash
     * @param string $payeeAccount
     * @throws \Exception
     */
    public function __construct($pmMemberId, $pmPassword, $alternatePhraseHash, $payeeAccount)
    {
        if (!function_exists('curl_version')) {
            throw new \Exception('curl not found');
        }

        if (!is_string($pmMemberId)) {
            throw new \InvalidArgumentException('pmMemberId is not string');
        }

        if (!is_string($pmPassword)) {
            throw new \InvalidArgumentException('pmPassword is not string');
        }

        if (!is_string($alternatePhraseHash)) {
            throw new \InvalidArgumentException('alternatePhraseHash is not string');
        }

        if (!is_string($payeeAccount)) {
            throw new \InvalidArgumentException('payeeAccount is not string');
        }

        $this->pmMemberId = $pmMemberId;
        $this->pmPassword = $pmPassword;
        $this->alternatePhraseHash = $alternatePhraseHash;
        $this->payeeAccount = $payeeAccount;
    }

    /**
     * @return string
     */
    public function getPmMemberId()
    {
        return $this->pmMemberId;
    }

    /**
     * @return string
     */
    public function getPmPassword()
    {
        return $this->pmPassword;
    }

    /**
     * @return string
     */
    public function getAlternatePhraseHash()
    {
        return $this->alternatePhraseHash;
    }

    /**
     * @return string
     */
    public function getPayeeAccount()
    {
        return $this->payeeAccount;
    }

    /**
     * Walidowanie danych płatności
     * @param Request $request
     * @param array $data
     * @throws ParametersNotMatchException
     */
    private function paymentValidate(Request $request, array $data)
    {
        $post = $request->request;

        if (!($data['Batch'] === $post->get('PAYMENT_BATCH_NUM')
            && $data['Payment_ID'] === $post->get('PAYMENT_ID')
            && $data['Type'] === 'Income'
            && $data['Payee_Account'] === $post->get('PAYEE_ACCOUNT')
            && $data['Amount'] === $post->get('PAYMENT_AMOUNT')
            && $data['Currency'] === $post->get('PAYMENT_UNITS')
            && $data['Payer_Account'] === $post->get('PAYER_ACCOUNT'))
        ) {
            throw new ParametersNotMatchException('The data did not pass validation', 110);
        }
    }

    /**
     * Generowanie hashu
     * @param Request $request
     * @return string
     */
    public function hashGenerate(Request $request)
    {
        $post = $request->request;
        return strtoupper(md5($post->get('PAYMENT_ID').':'.$post->get('PAYEE_ACCOUNT').':'.
            $post->get('PAYMENT_AMOUNT').':'.$post->get('PAYMENT_UNITS').':'.
            $post->get('PAYMENT_BATCH_NUM').':'.
            $post->get('PAYER_ACCOUNT').':'.$this->getAlternatePhraseHash().':'.
            $post->get('TIMESTAMPGMT')));
    }

    /**
     * Uzyskaj dane płatności na podstawie obiektu Request
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getPaymentResponse(Request $request)
    {
        $post = $request->request;

        if ($this->hashGenerate($request) !== $post->get('V2_HASH')) {
            throw new HashNotMatchException('Invalid hash', 100);
        }

        if ($post->get('PAYEE_ACCOUNT') !== $this->getPayeeAccount()) {
            throw new InvalidPayeeAccountException('Invalid payeeAccount', 101);
        }

        $date = new DateTime();
        $date->setTimestamp($post->get('TIMESTAMPGMT'));
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, 'https://perfectmoney.is/acct/historycsv.asp?'
            . 'AccountID=' . $this->getPmMemberId()
            . '&PassPhrase=' . $this->getPmPassword()
            . '&startmonth=' . $date->format('m')
            . '&startday=' . $date->format('d')
            . '&startyear=' . $date->format('Y')
            . '&endmonth=' . $date->format('m')
            . '&endday=' . $date->format('d')
            . '&endyear=' . $date->format('Y')
            . '&paymentsreceived=1&batchfilter=' . $post->get('PAYMENT_BATCH_NUM'));
        $lines = explode(PHP_EOL, curl_exec($curl));
        curl_close($curl);

        if (count($lines) !== 2) {
            throw new InvalidResponseException("Invalid number of rows", 1);
        }

        if ($lines[0] !== 'Time,Type,Batch,Currency,Amount,Fee,Payer Account,Payee Account,Payment ID,Memo') {
            throw new InvalidResponseException($lines[0], 2);
        }

        $row = str_getcsv($lines[1]);
        if (count($row) !== 10) {
            throw new InvalidResponseException($row[0], 3);
        }

        $data = array(
            'Time'          => $row[0],
            'Type'          => $row[1],
            'Batch'         => $row[2],
            'Currency'      => $row[3],
            'Amount'        => $row[4],
            'Fee'           => $row[5],
            'Payer_Account' => $row[6],
            'Payee_Account' => $row[7],
            'Payment_ID'    => $row[8],
            'Memo'          => $row[9],
        );

        // walidacja danych
        $this->paymentValidate($request, $data);

        return array($data);
    }
}
