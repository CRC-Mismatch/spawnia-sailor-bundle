<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\DependencyInjection;

use Exception;
use Mismatch\SpawniaSailorBundle\Service\SailorPsr18Client;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Spawnia\Sailor\Client;
use Spawnia\Sailor\EndpointConfig;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Filesystem\Path;
use function str_replace;
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

        $generatedEndpoints = [];
        foreach ($config['endpoints'] as $name => $options) {
            $configClass = (new ClassType())
                ->setExtends(EndpointConfig::class);
            $configClass->addMethod('makeClient')
                ->setPublic()
                ->setReturnType(Client::class)
                ->addBody('return (new \\Mismatch\\SpawniaSailorBundle\\Service\\SailorPsr18Client())')
                ->addBody('->setUrl(?)', [$options['url']])
                ->addBody('->setPost(?);', [$options['post']]);
            $configClass->addMethod('namespace')
                ->setPublic()
                ->setReturnType('string')
                ->addBody('return ?;', [$options['namespace']]);
            $configClass->addMethod('targetPath')
                ->setPublic()
                ->setReturnType('string')
                ->addBody('return ?;', [$options['generation_path']]);
            $configClass->addMethod('searchPath')
                ->setPublic()
                ->setReturnType('string')
                ->addBody('return ?;', [$options['operations_path']]);
            $configClass->addMethod('schemaPath')
                ->setPublic()
                ->setReturnType('string')
                ->addBody('return ?;', [$options['schema_path']]);
            $generatedEndpoints[$name] = (string) $configClass;
        }
        $configArrStr = "[\n";
        foreach ($generatedEndpoints as $name => $code) {
            $configArrStr .= "  '$name' => new class() $code,\n";
        }
        $configArrStr .= '];';
        $config = new PhpFile();
        $config
            ->setStrictTypes()
            ->addComment('This file is auto-generated.')
            ->addUse(EndpointConfig::class);

        $container->setParameter('sailor.config', "$config\nreturn $configArrStr");
    }
}
