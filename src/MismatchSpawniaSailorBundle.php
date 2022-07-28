<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use function dirname;

class MismatchSpawniaSailorBundle extends Bundle
{
    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
