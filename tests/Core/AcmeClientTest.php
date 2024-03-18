<?php

/*
 * This file is part of the Acme PHP project.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\AcmePhp\Core;

use AcmePhp\Core\AcmeClient;
use AcmePhp\Core\Challenge\Http\SimpleHttpSolver;
use AcmePhp\Core\Exception\Protocol\CertificateRevocationException;
use AcmePhp\Core\Http\Base64SafeEncoder;
use AcmePhp\Core\Http\SecureHttpClient;
use AcmePhp\Core\Http\ServerErrorHandler;
use AcmePhp\Core\Protocol\AuthorizationChallenge;
use AcmePhp\Core\Protocol\ExternalAccount;
use AcmePhp\Core\Util\PrinterInterface;
use AcmePhp\Ssl\Certificate;
use AcmePhp\Ssl\CertificateRequest;
use AcmePhp\Ssl\CertificateResponse;
use AcmePhp\Ssl\DistinguishedName;
use AcmePhp\Ssl\Generator\EcKey\EcKeyOption;
use AcmePhp\Ssl\Generator\KeyOption;
use AcmePhp\Ssl\Generator\KeyPairGenerator;
use AcmePhp\Ssl\Generator\RsaKey\RsaKeyOption;
use AcmePhp\Ssl\Parser\KeyParser;
use AcmePhp\Ssl\Signer\DataSigner;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Prophecy\PhpUnit\ProphecyTrait;

class AcmeClientTest extends AbstractFunctionnalTest
{
    use ProphecyTrait;
    public function provideFullProcess()
    {
        yield 'rsa1024' => [new RsaKeyOption(1024), false];
        yield 'rsa1024-alternate' => [new RsaKeyOption(1024), true];
        yield 'rsa4098' => [new RsaKeyOption(4098), false];
        yield 'ecprime256v1' => [new EcKeyOption('prime256v1'), false];
        yield 'ecsecp384r1' => [new EcKeyOption('secp384r1'), false];
    }

    /**
     * @dataProvider provideFullProcess
     */
    public function testFullProcess(KeyOption $keyOption, bool $useAlternateCertificate)
    {
        $secureHttpClient = new SecureHttpClient(
            (new KeyPairGenerator())->generateKeyPair($keyOption),
            new Client(),
            new Base64SafeEncoder(),
            new KeyParser(),
            new DataSigner(),
            new ServerErrorHandler()
        );


        $client = new AcmeClient($secureHttpClient, 'https://pebble:14000/dir');
        /*
         * Register account
         */
        if ('eab' === getenv('PEBBLE_MODE')) {
            $data = $client->registerAccount('titouan.galopin@acmephp.com', new ExternalAccount('kid1', 'dGVzdGluZ3Rlc3Rpbmd0ZXN0aW5ndGVzdGluZ3Rlc3Rpbmd0ZXN0aW5ndGVzdGluZw=='));
        } else {
            $data = $client->registerAccount('titouan.galopin@acmephp.com');
        }

        $this->assertIsArray($data);
        $this->assertArrayHasKey('key', $data);

        $solver = new SimpleHttpSolver(null, $this->prophesize(PrinterInterface::class)->reveal());
        $fakeServer = new Client();
        $response = $fakeServer->post('http://challtestsrv:8055/set-default-ipv4', [RequestOptions::JSON => ['ip' => gethostbyname('challtestsrv')]]);
        $this->assertSame(200, $response->getStatusCode());
        /*
         * Ask for domain challenge
         */
        $order = $client->requestOrder(['acmephp.com']);

        $this->assertEquals('pending', $order->getStatus());
        $challenges = $order->getAuthorizationChallenges('acmephp.com');
        foreach ($challenges as $challenge) {
            if ('http-01' === $challenge->getType()) {
                break;
            }
        }

        $this->assertInstanceOf(AuthorizationChallenge::class, $challenge ?? null);
        $this->assertEquals('acmephp.com', $challenge->getDomain());
        $this->assertStringContainsString(':14000/chalZ/', $challenge->getUrl());

        $solver->solve($challenge);

        /*
         * Challenge check
         */
        $this->handleChallenge($challenge->getToken(), $challenge->getPayload());
        try {
            $check = $client->challengeAuthorization($challenge);
            $this->assertEquals('valid', $check['status']);
        } finally {
            $this->cleanChallenge($challenge->getToken());
        }

        /**
         * Reload order, check if challenge was completed.
         */
        $updatedOrder = $client->reloadOrder($order);
        $this->assertEquals('ready', $updatedOrder->getStatus());
        $this->assertCount(1, $updatedOrder->getAuthorizationChallenges('acmephp.com'));
        $validatedChallenge = $updatedOrder->getAuthorizationChallenges('acmephp.com')[0];
        $this->assertEquals('valid', $validatedChallenge->getStatus());

        /*
         * Request certificate
         */
        $csr = new CertificateRequest(new DistinguishedName('acmephp.com'), (new KeyPairGenerator())->generateKeyPair($keyOption));
        $response = $client->finalizeOrder($order, $csr, 180, $useAlternateCertificate);

        $this->assertInstanceOf(CertificateResponse::class, $response);
        $this->assertEquals($csr, $response->getCertificateRequest());
        $this->assertInstanceOf(Certificate::class, $response->getCertificate());

        /*
         * Revoke certificate
         *
         * ACME will not let you revoke the same cert twice so this test should pass both cases
         */
        try {
            $client->revokeCertificate($response->getCertificate());
        } catch (CertificateRevocationException $e) {
            $this->assertStringContainsString('Unable to find specified certificate', $e->getPrevious()->getPrevious()->getMessage());
        }
    }
}
