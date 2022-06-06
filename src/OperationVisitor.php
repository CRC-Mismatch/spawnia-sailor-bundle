<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle;

use ReflectionClass;
use ReflectionException;
use Spawnia\Sailor\Operation;

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

    /**
     * @throws ReflectionException
     */
    public function converters(): array
    {
        $converters = $this->classToVisit->getMethod('converters');
        $converters->setAccessible(true);
        return $converters->invoke(null);
    }
}
