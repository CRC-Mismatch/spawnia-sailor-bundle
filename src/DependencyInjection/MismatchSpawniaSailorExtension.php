<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\DependencyInjection;

use Mismatch\SpawniaSailorBundle\Services\SailorPsr18Client;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use function Symfony\Component\String\u;

class MismatchSpawniaSailorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('sailor.endpoints', $config['endpoints']);
        $container->setParameter('sailor.default_url', $config['default_url']);
        $container->setParameter('sailor.default_post', $config['default_post']);
        foreach ($config['endpoints'] as $name => $endpoint) {
            $uname = u($name);
            $camelName = $uname->camel();
            $snakeName = $uname->snake();
            $id = SailorPsr18Client::class." $${camelName}Client";
            $container->register($id, SailorPsr18Client::class)
                ->setAutoconfigured(true)
                ->setAutowired(true)
                ->setMethodCalls([
                    ['setUrl', [$endpoint['url']], true],
                    ['setPost', [$endpoint['post']], true],
                ])
                ->setPublic(true);
            $container->setAlias("sailor.$snakeName.client", $id);
        }
    }
}
