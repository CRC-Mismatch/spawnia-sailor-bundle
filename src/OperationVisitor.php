<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle;

use ReflectionClass;
use ReflectionException;
use Spawnia\Sailor\Operation;
use Symfony\Component\ErrorHandler\Error\UndefinedMethodError;

class OperationVisitor
{
    private Operation $visited;
    private ReflectionClass $classToVisit;

    public function __construct(Operation $visited)
    {
        $this->visited = $visited;
        $this->classToVisit = new ReflectionClass($visited);
    }

    public function config(): string
    {
        return $this->visited::config();
    }

    public function endpoint(): string
    {
        return $this->visited::endpoint();
    }

    public function document(): string
    {
        return $this->visited::document();
    }

    public function converters(): array
    {
        try {
            $converters = $this->classToVisit->getMethod('converters');
            $converters->setAccessible(true);

            return $converters->invoke(null);
        } catch (ReflectionException $e) {
            throw new UndefinedMethodError("Operation class {$this->classToVisit->getShortName()} has no 'converters' method.", $e);
        }
    }
}
