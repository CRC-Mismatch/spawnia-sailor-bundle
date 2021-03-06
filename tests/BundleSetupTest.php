<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Tests;

use Mismatch\SpawniaSailorBundle\Service\SailorPsr18Client;
use PHPUnit\Framework\TestCase;
use Spawnia\Sailor\Client;
use function is_int;

class BundleSetupTest extends TestCase
{
    public function testSetupBundle(): void
    {
        $kernel = new Kernel('test', [
            'suffix' => (string) time(),
        ]);
        $kernel->boot();

        $container = $kernel->getContainer();

        $services = [
            'sailor.client' => SailorPsr18Client::class,
            Client::class => SailorPsr18Client::class,
            SailorPsr18Client::class,
        ];

        foreach ($services as $id => $class) {
            if (is_int($id)) {
                $id = $class;
            }
            $this->assertTrue($container->has($id), "Service $id not found in container");
            $this->assertInstanceOf($class, $container->get($id), "Service $id isn't an instance of $class");
        }
    }
}
