<?php

/**
 * @copyright  Copyright (c) 2022 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);

namespace Mismatch\SpawniaSailorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sailor');
        if (method_exists(TreeBuilder::class, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            $rootNode = $treeBuilder->root('sailor');
        }

        $rootNode->children()
            ->booleanNode('default_post')
                ->defaultTrue()
                ->info("Should the default Sailor client use POST?")
                ->end()
            ->scalarNode('default_url')
                ->defaultValue('')
                ->info("Sets a URL for the default Sailor client")
                ->end()
            ->arrayNode('endpoints')
                ->requiresAtLeastOneElement()
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')
                            ->defaultValue('')
                            ->info('URL for this endpoint')
                            ->end()
                        ->booleanNode('post')->defaultTrue()->info('Should this endpoint use POST?')->end()
                        ->scalarNode('namespace')
                            ->defaultValue('App\\SailorApi')
                            ->info('Namespace for this endpoint\'s generated classes')
                            ->end()
                        ->scalarNode('generation_path')
                            ->defaultValue('%kernel.project_dir%/generated')
                            ->info('Target path for this endpoint\'s generated classes')
                            ->end()
                        ->scalarNode('operations_path')
                            ->defaultValue('%kernel.project_dir%/graphql/operations')
                            ->info('Target path for this endpoint\'s operations (folder containing *.graphql files)')
                            ->end()
                        ->scalarNode('schema_path')
                            ->defaultValue('%kernel.project_dir%/graphql/schemas/schema.graphql')
                            ->info('Target path for this endpoint\'s schema files (one *.graphql file)')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}