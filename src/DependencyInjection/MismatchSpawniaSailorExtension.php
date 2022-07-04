<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\DependencyInjection;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Psr18ClientDiscovery;
use Mismatch\SpawniaSailorBundle\Service\AbstractSailorClient;
use Mismatch\SpawniaSailorBundle\Service\GenericSailorEndpointConfig;
use Mismatch\SpawniaSailorBundle\Service\SailorClientInterface;
use Mismatch\SpawniaSailorBundle\Service\SailorPsr18Client;
use Mismatch\SpawniaSailorBundle\Service\SailorSymfonyHttpClient;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\PsrPrinter;
use Spawnia\Sailor\Client;
use Spawnia\Sailor\EndpointConfig;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpKernel\KernelInterface;

use function Symfony\Component\String\u;

class MismatchSpawniaSailorExtension extends Extension
{
    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        if ('psr18' === $config['default_client']
            || (
                'auto' === $config['default_client']
                && (
                   class_exists(Psr18Client::class)
                || class_exists(GuzzleClient::class)
                || class_exists(Psr18ClientDiscovery::class)
                )
            )
        ) {
            $clientClass = SailorPsr18Client::class;
        } else {
            $clientClass = SailorSymfonyHttpClient::class;
        }
        $definition = (new ChildDefinition(AbstractSailorClient::class))
            ->setClass($clientClass)
            ->setAutowired(true)
            ->setAutoconfigured(true);
        $container->setDefinition($clientClass, $definition);
        $container->setAlias(SailorClientInterface::class, $clientClass);

        $projectDir = $container->getParameter('kernel.project_dir');
        foreach ($config['endpoints'] as &$endpoint) {
            $endpoint['operations_path'] = Path::makeAbsolute($endpoint['operations_path'], $projectDir);
            $endpoint['generation_path'] = Path::makeAbsolute($endpoint['generation_path'], $projectDir);
            $endpoint['schema_path'] = Path::makeAbsolute($endpoint['schema_path'], $projectDir);
        }
        unset($endpoint);
        $container->setParameter('sailor.endpoints', $config['endpoints']);
        $container->setParameter('sailor.config_path', Path::makeAbsolute($config['config_path'], $projectDir));
        $container->setParameter('sailor.default_url', $config['default_url']);
        $container->setParameter('sailor.default_post', $config['default_post']);
        foreach ($config['endpoints'] as $name => $endpoint) {
            $uname = u($name);
            $camelName = $uname->camel();
            $snakeName = $uname->snake();
            $id = "sailor.$snakeName.client";
            $container->setDefinition($id, (new ChildDefinition(AbstractSailorClient::class))
                ->setClass($clientClass)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setMethodCalls([
                    ['setSerializer', [new Reference('serializer')]],
                    ['withUrl', [$endpoint['url']], true],
                    ['withPost', [$endpoint['post']], true],
                ])
                ->setPublic(true));
            $container->registerAliasForArgument($id, SailorClientInterface::class, "{$camelName}Client");
            $container->register("sailor.$snakeName.endpoint_config", GenericSailorEndpointConfig::class)
                ->setArguments([
                    '$client' => new Reference("sailor.$snakeName.client"),
                    '$endpoints' => new Parameter('sailor.endpoints'),
                    '$configPath' => new Parameter('sailor.config_path'),
                    '$endpointName' => $name,
                ])
                ->setPublic(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getXsdValidationBasePath(): string
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace(): string
    {
        return 'http://mismatch.org/schema/dic/spawnia_sailor_bundle';
    }
}
