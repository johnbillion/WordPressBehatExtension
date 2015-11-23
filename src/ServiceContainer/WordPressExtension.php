<?php

namespace Johnbillion\WordPressExtension\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension,
    Behat\Testwork\ServiceContainer\Extension as ExtensionInterface,
    Behat\Testwork\ServiceContainer\ExtensionManager,
    Behat\Testwork\EventDispatcher\ServiceContainer\EventDispatcherExtension;

use Symfony\Component\Config\FileLocator,
    Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition,
    Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Loader\XmlFileLoader,
    Symfony\Component\DependencyInjection\Definition;

/**
 * Class WordPressExtension
 *
 * @package Johnbillion\WordPressExtension\ServiceContainer
 */
class WordPressExtension implements ExtensionInterface
{

    /**
     * {@inheritDoc}
     */
    public function getConfigKey()
    {
        return 'wordpress';
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {

    }

  /**
   * {@inheritdoc}
   */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('path')
                    ->defaultValue(__DIR__ . 'vendor')
                ->end()
                ->arrayNode('symlink')
                    ->children()
                        ->scalarNode('from')
                            ->defaultValue('.')
                        ->end()
                        ->scalarNode('to')
                            ->isRequired()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('flush_database')
                    ->defaultValue(true)
                ->end()
                ->arrayNode('connection')
                    ->children()
                        ->scalarNode('db')
                            ->defaultValue('wordpress')
                        ->end()
                        ->scalarNode('username')
                            ->defaultValue('root')
                        ->end()
                        ->scalarNode('password')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('host')
                            ->defaultValue('localhost')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $this->loadContextInitializer($container);
        $container->setParameter('wordpress.parameters', $config);
    }

    /**
     * Register a Context Initializer service for the behat
     *
     * @param ContainerBuilder $container the service will check for Johnbillion\WordPressExtension\Context\WordPressContext contexts
     */
    private function loadContextInitializer(ContainerBuilder $container)
    {
        $definition = new Definition('Johnbillion\WordPressExtension\Context\Initializer\WordPressContextInitializer', array(
            '%wordpress.parameters%',
            '%mink.parameters%',
            '%paths.base%',
        ));
        $definition->addTag(ContextExtension::INITIALIZER_TAG, array('priority' => 0));
        $container->setDefinition('behat.wordpress.service.wordpress_context_initializer', $definition);
    }
}
