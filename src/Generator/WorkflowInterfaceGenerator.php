<?php

declare(strict_types=1);

namespace Spiral\TemporalBridge\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Spiral\TemporalBridge\Attribute\AssignWorker;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

final class WorkflowInterfaceGenerator implements FileGeneratorInterface
{
    public function generate(Context $context, PhpNamespace $namespace): PhpCodePrinter
    {
        $className = $context->getClass();

        $class = ClassType::interface($className);
        $class->addAttribute(WorkflowInterface::class);

        if (($queue = $context->getTaskQueue()) !== null) {
            $class->addAttribute(AssignWorker::class, ['name' => $queue]);
        }

        $class->addMember($method = $context->getHandlerMethod());
        $method->setBody(null)->addAttribute(WorkflowMethod::class);

        foreach ($context->getSignalMethods() as $method) {
            $class->addMember($method->setBody(null)->addAttribute(SignalMethod::class));
        }

        foreach ($context->getQueryMethods() as $method) {
            $class->addMember($method->setBody(null)->addAttribute(QueryMethod::class));
        }

        return new PhpCodePrinter(
            $namespace
                ->add($class)
                ->addUse(QueryMethod::class)
                ->addUse(SignalMethod::class)
                ->addUse(WorkflowInterface::class)
                ->addUse(WorkflowMethod::class),
            $context
        );
    }
}
