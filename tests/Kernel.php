<?php

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\Tests;

use Mismatch\SpawniaSailorBundle\MismatchSpawniaSailorBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollection;

class Kernel extends BaseKernel
{
    private string $suffix;
    private array $endpoints;

    public function __construct(string $environment, array $options)
    {
        parent::__construct($environment, true);
        $this->suffix = $options['suffix'] ?? '';
        $this->endpoints = $options['endpoints'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new MismatchSpawniaSailorBundle(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function buildContainer(): ContainerBuilder
    {
        $container = parent::buildContainer();
        $container->addCompilerPass(new class($this->endpoints) implements CompilerPassInterface {
            private array $endpoints;

            public function __construct(array $endpoints)
            {
                $this->endpoints = $endpoints;
            }

            public function process(ContainerBuilder $container)
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if ('parameter_bag' === $id) {
                        $definition->setPublic(true);
                    }
                    if (false !== stripos($id, 'Mismatch') || false !== stripos($id, 'sailor')) {
                        $definition->setPublic(true);
                    }
                }
                foreach ($container->getAliases() as $id => $definition) {
                    if (false !== stripos($id, 'Mismatch') || false !== stripos($id, 'sailor')) {
                        $definition->setPublic(true);
                    }
                }
                $container->setParameter('sailor.endpoints', $this->endpoints);
            }
        });

        return $container;
    }

    /**
     * {@inheritDoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test',
                'router' => [
                    'resource' => 'kernel:loadRoutes',
                    'type' => 'service',
                    'utf8' => true,
                ],
                'http_method_override' => false,
            ]);
            $container->addObjectResource($this);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/'.$this->suffix;
    }

    /**
     * {@inheritDoc}
     */
    public function getLogDir(): string
    {
        return parent::getLogDir().'/'.$this->suffix;
    }

    /**
     * {@inheritDoc}
     */
    public function loadRoutes(LoaderInterface $loader): RouteCollection
    {
        return new RouteCollection();
    }
}
