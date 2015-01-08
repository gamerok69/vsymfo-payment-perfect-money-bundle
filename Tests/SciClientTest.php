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

use Symfony\Component\HttpFoundation\Request;
use vSymfo\Payment\PerfectMoneyBundle\Client\SciClient;
use vSymfo\Payment\PerfectMoneyBundle\Client\Exception\InvalidResponseException;
use vSymfo\Payment\PerfectMoneyBundle\Client\Exception\InvalidPayeeAccountException;
use vSymfo\Payment\PerfectMoneyBundle\Client\Exception\HashNotMatchException;

class SciClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * testowanie wyjątków
     */
    public function testException()
    {
        $sci = new SciClient('InvalidId', 'InvalidPass', 'InvalidHash', 'InvalidPayee');
        $request = new Request();
        $request->request->set('TIMESTAMPGMT', time());
        $request->request->set('PAYMENT_BATCH_NUM', '1');

        try { // nie podałem hasha, więc rzuci wyjątek HashNotMatchException
            $sci->getPaymentResponse($request);
        } catch (HashNotMatchException $e) {
            $this->assertTrue(true);
        } catch (\Exception $e) {
            throw $e;
        }

        // wygenerowałem hash, powinno przejść walidację
        $request->request->set('V2_HASH', $sci->hashGenerate($request));

        try { // teraz rzuci, że PayeeAccount się nie zgadza
            $sci->getPaymentResponse($request);
        } catch (InvalidPayeeAccountException $e) {
            $this->assertTrue(true);
        } catch (\Exception $e) {
            throw $e;
        }

        // ustawiłem payeeAccount takie samo jak w konstruktorze
        $request->request->set('PAYEE_ACCOUNT', 'InvalidPayee');
        // payeeAccount ma wpływ na hash, dlatego trzeba zaktualizować
        $request->request->set('V2_HASH', $sci->hashGenerate($request));

        try { // teraz wyjątek ma związek z błędnymi danymi autoryzacyjnymi
            $sci->getPaymentResponse($request);
        } catch (InvalidResponseException $e) {
            $this->assertEquals($e->getCode(), 2);
        } catch (\Exception $e) {
            throw $e;
        }

        // tutaj utwórz nowy obiekt SciClient z prawidłowmi danymi w konstruktorze
        // i przetestuj pozostałe wyjątki
        // $test = new SciClient('...', '...', '...');
        // ...
        // Wynik testów będzie zależał od tego jakie parametry POST zdefiniujesz
        // $request = new Request();
        // $request->request->set('TIMESTAMPGMT', '..');
        // $request->request->set('PAYMENT_BATCH_NUM', '..');
    }
}
