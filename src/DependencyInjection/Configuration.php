<?php

namespace Meta\Facebook\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritdoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $this->treeBuilder = new TreeBuilder('facebook');

        $rootNode = $this->treeBuilder->getRootNode();
        $this->addGlobalOptionsSection($rootNode);

        return $this->treeBuilder;
    }

    private $treeBuilder;
    public function getTreeBuilder(): TreeBuilder
    {
        return $this->treeBuilder;
    }

    private function addGlobalOptionsSection(ArrayNodeDefinition $rootNode)
    {
        $dataPath = dirname(__DIR__, 5)."/data";

        $rootNode
            ->children()
                ->booleanNode('enable')
                    ->info('Enable feature')
                    ->defaultValue(true)
                    ->end()
                ->booleanNode('autoappend')
                    ->info('Auto-append required dependencies into HTML page')
                    ->defaultValue(true)
                    ->end()
                ->scalarNode('pixelId')
                    ->info('id to load (can be set later)')
                    ->defaultValue('')
                    ->end()
                ->scalarNode('domainVerificationKey')
                    ->info('Domain Verification is done periodically by META')
                    ->defaultValue('')
                    ->end()
            ->end()
        ;
    }
}
