<?php

declare(strict_types=1);

namespace Spiral\TemporalBridge\Bootloader;

use Spiral\Attributes\AttributeReader;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\Core\Container;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Bootloader\RoadRunnerBootloader;
use Spiral\TemporalBridge\Commands;
use Spiral\TemporalBridge\Config\TemporalConfig;
use Spiral\TemporalBridge\DeclarationLocatorInterface;
use Spiral\TemporalBridge\Dispatcher;
use Spiral\TemporalBridge\Generator\Preset\PresetRegistry;
use Spiral\TemporalBridge\Generator\Preset\PresetRegistryInterface;
use Spiral\TemporalBridge\Generator\Preset\SignalWorkflow;
use Spiral\TemporalBridge\Generator\Preset\WorkflowPresetLocator;
use Spiral\TemporalBridge\Generator\Preset\WorkflowPresetLocatorInterface;
use Spiral\TemporalBridge\Workflow\WorkflowManager;
use Spiral\TemporalBridge\WorkflowManagerInterface;
use Spiral\Tokenizer\ClassesInterface;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\WorkerFactoryInterface;

class TemporalBridgeBootloader extends Bootloader
{
    protected const SINGLETONS = [
        WorkflowPresetLocatorInterface::class => [self::class, 'initWorkflowPresetLocator'],
        WorkflowManagerInterface::class => WorkflowManager::class,
        WorkerFactoryInterface::class => [self::class, 'initWorkerFactory'],
        DeclarationLocatorInterface::class => [self::class, 'initDeclarationLocator'],
        WorkflowClientInterface::class => [self::class, 'initWorkflowClient'],
        PresetRegistryInterface::class => PresetRegistry::class,
    ];

    protected const DEPENDENCIES = [
        ConsoleBootloader::class,
        RoadRunnerBootloader::class,
    ];

    public function __construct(private ConfiguratorInterface $config)
    {
    }

    public function boot(
        AbstractKernel $kernel,
        EnvironmentInterface $env,
        ConsoleBootloader $console,
        Dispatcher $dispatcher
    ): void {
        $this->initConfig($env);

        $kernel->addDispatcher($dispatcher);

        $console->addCommand(Commands\MakeWorkflowCommand::class);
        $console->addCommand(Commands\MakePresetCommand::class);
        $console->addCommand(Commands\PresetListCommand::class);
    }

    public function start(PresetRegistryInterface $registry)
    {
        $registry->register('signal', new SignalWorkflow());
    }

    private function initWorkflowPresetLocator(
        FactoryInterface $factory,
        ClassesInterface $classes
    ): WorkflowPresetLocatorInterface {
        return new WorkflowPresetLocator(
            $factory,
            $classes,
            new AttributeReader()
        );
    }

    private function initConfig(EnvironmentInterface $env): void
    {
        $this->config->setDefaults(
            TemporalConfig::CONFIG,
            [
                'address' => $env->get('TEMPORAL_ADDRESS', '127.0.0.1:7233'),
                'namespace' => 'App\\Workflow',
            ]
        );
    }

    private function initWorkflowClient(TemporalConfig $config): WorkflowClientInterface
    {
        return WorkflowClient::create(
            ServiceClient::create($config->getAddress())
        );
    }

    private function initWorkerFactory(
        Container $container,
        FinalizerInterface $finalizer
    ): WorkerFactoryInterface {
        return new \Spiral\TemporalBridge\WorkerFactory(
            DataConverter::createDefault(),
            Goridge::create(),
            $container, $finalizer
        );
    }

    private function initDeclarationLocator(ClassesInterface $classes): DeclarationLocatorInterface
    {
        return new \Spiral\TemporalBridge\DeclarationLocator(
            $classes,
            new AttributeReader()
        );
    }
}
