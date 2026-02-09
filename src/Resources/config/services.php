<?php

declare(strict_types=1);

/*
 * Service definitions for Symfony 8.0+ (where XmlFileLoader was removed).
 * For Symfony 4.4-7.x, services.xml is used instead.
 */

use Sentry\ClientInterface;
use Sentry\Integration\RequestFetcherInterface;
use Sentry\SentryBundle\Command\SentryTestCommand;
use Sentry\SentryBundle\EventListener\ConsoleCommandListener;
use Sentry\SentryBundle\EventListener\ConsoleListener;
use Sentry\SentryBundle\EventListener\ErrorListener;
use Sentry\SentryBundle\EventListener\LoginListener;
use Sentry\SentryBundle\EventListener\MessengerListener;
use Sentry\SentryBundle\EventListener\RequestListener;
use Sentry\SentryBundle\EventListener\SubRequestListener;
use Sentry\SentryBundle\EventListener\TracingConsoleListener;
use Sentry\SentryBundle\EventListener\TracingRequestListener;
use Sentry\SentryBundle\EventListener\TracingSubRequestListener;
use Sentry\SentryBundle\Integration\IntegrationConfigurator;
use Sentry\SentryBundle\Integration\RequestFetcher;
use Sentry\SentryBundle\Tracing\Cache\TraceableCacheAdapter;
use Sentry\SentryBundle\Tracing\Cache\TraceableTagAwareCacheAdapter;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\ConnectionConfigurator;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriverConnectionFactory;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriverConnectionFactoryInterface;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriverMiddleware;
use Sentry\SentryBundle\Tracing\Twig\TwigTracingExtension;
use Sentry\SentryBundle\Transport\TransportFactory;
use Sentry\SentryBundle\Twig\SentryExtension;
use Sentry\State\HubAdapter;
use Sentry\State\HubInterface;
use Sentry\Transport\TransportFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/** @var ContainerBuilder $container */

$container->setAlias(ClientInterface::class, 'sentry.client')->setPublic(false);

$container->register(TransportFactoryInterface::class, TransportFactory::class)
    ->setPublic(false)
    ->setArgument(0, new Reference('Psr\Http\Message\UriFactoryInterface', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
    ->setArgument(1, new Reference('Psr\Http\Message\RequestFactoryInterface', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
    ->setArgument(2, new Reference('Psr\Http\Message\ResponseFactoryInterface', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
    ->setArgument(3, new Reference('Psr\Http\Message\StreamFactoryInterface', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
    ->setArgument(4, null)
    ->setArgument(5, null);

$container->register(HubInterface::class)
    ->setPublic(false)
    ->setFactory([HubAdapter::class, 'getInstance'])
    ->addMethodCall('bindClient', [new Reference(ClientInterface::class)]);

$container->setAlias(ConsoleCommandListener::class, ConsoleListener::class)->setPublic(false);

$container->register(ConsoleListener::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->addTag('kernel.event_listener', ['event' => 'console.command', 'method' => 'handleConsoleCommandEvent', 'priority' => 128])
    ->addTag('kernel.event_listener', ['event' => 'console.terminate', 'method' => 'handleConsoleTerminateEvent', 'priority' => -64])
    ->addTag('kernel.event_listener', ['event' => 'console.error', 'method' => 'handleConsoleErrorEvent', 'priority' => -64]);

$container->register(ErrorListener::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->addTag('kernel.event_listener', ['event' => 'kernel.exception', 'method' => 'handleExceptionEvent', 'priority' => 128]);

$container->register(RequestListener::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'handleKernelRequestEvent', 'priority' => 5])
    ->addTag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'handleKernelControllerEvent', 'priority' => 10]);

$container->register(SubRequestListener::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'handleKernelRequestEvent', 'priority' => 3])
    ->addTag('kernel.event_listener', ['event' => 'kernel.finish_request', 'method' => 'handleKernelFinishRequestEvent', 'priority' => 5]);

$container->register(TracingRequestListener::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'handleKernelRequestEvent', 'priority' => 4])
    ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'handleKernelResponseEvent', 'priority' => 15])
    ->addTag('kernel.event_listener', ['event' => 'kernel.terminate', 'method' => 'handleKernelTerminateEvent', 'priority' => 5]);

$container->register(TracingSubRequestListener::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'handleKernelRequestEvent', 'priority' => 2])
    ->addTag('kernel.event_listener', ['event' => 'kernel.finish_request', 'method' => 'handleKernelFinishRequestEvent', 'priority' => 10])
    ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'handleKernelResponseEvent', 'priority' => 15]);

$container->register(TracingConsoleListener::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->setArgument(1, null)
    ->addTag('kernel.event_listener', ['event' => 'console.command', 'method' => 'handleConsoleCommandEvent', 'priority' => 118])
    ->addTag('kernel.event_listener', ['event' => 'console.terminate', 'method' => 'handleConsoleTerminateEvent', 'priority' => -54]);

$container->register(MessengerListener::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->addTag('kernel.event_listener', ['event' => 'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent', 'method' => 'handleWorkerMessageFailedEvent', 'priority' => 50])
    ->addTag('kernel.event_listener', ['event' => 'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent', 'method' => 'handleWorkerMessageHandledEvent', 'priority' => 50]);

$container->register(LoginListener::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->setArgument(1, new Reference('security.token_storage', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
    ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'handleKernelRequestEvent'])
    ->addTag('kernel.event_listener', ['event' => 'Symfony\Component\Security\Http\Event\LoginSuccessEvent', 'method' => 'handleLoginSuccessEvent']);

$container->register(SentryTestCommand::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->addTag('console.command', ['command' => 'sentry:test']);

$container->setAlias(TracingDriverConnectionFactoryInterface::class, TracingDriverConnectionFactory::class)->setPublic(false);

$container->register(TracingDriverConnectionFactory::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class));

$container->register(TracingDriverMiddleware::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(TracingDriverConnectionFactoryInterface::class));

$container->register(ConnectionConfigurator::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(TracingDriverMiddleware::class));

$container->register(TwigTracingExtension::class)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->addTag('twig.extension');

$container->register('sentry.tracing.traceable_cache_adapter', TraceableCacheAdapter::class)
    ->setAbstract(true)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->setArgument(1, null);

$container->register('sentry.tracing.traceable_tag_aware_cache_adapter', TraceableTagAwareCacheAdapter::class)
    ->setAbstract(true)
    ->setPublic(false)
    ->setArgument(0, new Reference(HubInterface::class))
    ->setArgument(1, null);

$container->register(IntegrationConfigurator::class)
    ->setPublic(false)
    ->setArgument(0, [])
    ->setArgument(1, null);

$container->register(RequestFetcherInterface::class, RequestFetcher::class)
    ->setPublic(false)
    ->setArgument(0, new Reference('Symfony\Component\HttpFoundation\RequestStack'))
    ->setArgument(1, new Reference('Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface', ContainerBuilder::NULL_ON_INVALID_REFERENCE));

$container->register(SentryExtension::class)
    ->setPublic(false)
    ->addTag('twig.extension');
