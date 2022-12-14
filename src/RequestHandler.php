<?php
/**
 * This file is part of niqingyang/workerman-bundle.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    niqingyang<niqy@qq.com>
 * @copyright niqingyang<niqy@qq.com>
 * @link      https://github.com/niqingyang/workerman-bundle
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace WellKit\WorkermanBundle;

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Psr7\ServerRequest;
use function Workerman\Psr7\response_to_string;

class RequestHandler
{
    public int $maxRequest = 2000;

    private PsrHttpFactory $psrHttpFactory;
    private HttpFoundationFactoryInterface $httpFoundationFactory;
    private Psr17Factory $psr17Factory;
    private FinfoMimeTypeDetector $detector;

    public function __construct(private KernelInterface $kernel)
    {
        $this->psr17Factory = new Psr17Factory();
        $this->httpFoundationFactory = new HttpFoundationFactory();
        $this->psrHttpFactory = new PsrHttpFactory($this->psr17Factory, $this->psr17Factory, $this->psr17Factory, $this->psr17Factory);

        $this->detector = new FinfoMimeTypeDetector();

        Http::requestClass(ServerRequest::class);
    }

    /**
     * @throws \Exception
     */
    public function onMessage(TcpConnection $connection, ServerRequest $psrRequest): void
    {
        $checkFile = "{$this->kernel->getProjectDir()}/public{$psrRequest->getUri()->getPath()}";
        $checkFile = str_replace('..', '/', $checkFile);

        if (is_file($checkFile)) {
            $code = file_get_contents($checkFile);
            $psrResponse = new Response(200, [
                'Content-Type' => $this->detector->detectMimeType($checkFile, $code),
                'Last-Modified' => gmdate('D, d M Y H:i:s', filemtime($checkFile)) . ' GMT',
            ], $code);
            $connection->send(response_to_string($psrResponse), true);
            return;
        }

        $this->kernel->boot();

        // ???PSR???????????????????????????Symfony???????????????????????????????????????PSR??????????????????
        $symfonyRequest = $this->httpFoundationFactory->createRequest($psrRequest);
        $symfonyResponse = $this->kernel->handle($symfonyRequest);
        $psrResponse = $this->psrHttpFactory->createResponse($symfonyResponse);

        // ????????????????????????????????????????????????HTTP???????????????????????????
        $connection->send(response_to_string($psrResponse), true);

        // ?????????????????????????????????????????????
        $this->kernel->terminate($symfonyRequest, $symfonyResponse);

        // ??????????????????????????????????????????????????????????????????????????????OOM
        static $maxRequest;
        if (++$maxRequest > $this->maxRequest) {
            // $output->writeln("max request {$maxRequest} reached and reload");
            // send SIGUSR1 signal to master process for reload
            posix_kill(posix_getppid(), SIGUSR1);
        }
    }
}
