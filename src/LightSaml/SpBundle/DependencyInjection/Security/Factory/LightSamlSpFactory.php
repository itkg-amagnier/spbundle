<?php

/*
 * This file is part of the LightSAML SP-Bundle package.
 *
 * (c) Milos Tomic <tmilos@lightsaml.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace LightSaml\SpBundle\DependencyInjection\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;

class LightSamlSpFactory extends AbstractFactory
{
    public function addConfiguration(NodeDefinition $node)
    {
        parent::addConfiguration($node);
        $node
            ->children()
                ->booleanNode('force')->defaultTrue()->end()
                ->scalarNode('username_mapper')->defaultValue('lightsaml_sp.username_mapper.simple')->end()
                ->scalarNode('user_creator')->defaultNull()->end()
                ->scalarNode('attribute_mapper')->defaultValue('lightsaml_sp.attribute_mapper.simple')->end()
                ->scalarNode('token_factory')->defaultValue('lightsaml_sp.token_factory')->end()
            ->end()
        ->end();
    }

    /**
     * Subclasses must return the id of a service which implements the
     * AuthenticationProviderInterface.
     *
     * @param ContainerBuilder $container
     * @param string           $id             The unique id of the firewall
     * @param array            $config         The options array for this listener
     * @param string           $userProviderId The id of the user provider
     *
     * @return string never null, the id of the authentication provider
     */
    protected function createAuthProvider(ContainerBuilder $container, $id, $config, $userProviderId)
    {
        if (class_exists('Symfony\Component\DependencyInjection\ChildDefinition')) {
            // Symfony >= 3.3
            $definition = new ChildDefinition('security.authentication.provider.lightsaml_sp');
        } else {
            // Symfony < 3.3
            $definition = new DefinitionDecorator('security.authentication.provider.lightsaml_sp');
        }

        $providerId = 'security.authentication.provider.lightsaml_sp.'.$id;
        $provider = $container
            ->setDefinition($providerId, $definition)
            ->replaceArgument(0, $id)
            ->replaceArgument(2, $config['force'])
        ;
        if (isset($config['provider'])) {
            $provider->replaceArgument(1, new Reference($userProviderId));
        }
        if (isset($config['username_mapper'])) {
            $provider->replaceArgument(4, new Reference($config['username_mapper']));
        }
        if (isset($config['user_creator'])) {
            $provider->replaceArgument(5, new Reference($config['user_creator']));
        }
        if (isset($config['attribute_mapper'])) {
            $provider->replaceArgument(6, new Reference($config['attribute_mapper']));
        }
        if (isset($config['token_factory'])) {
            $provider->replaceArgument(7, new Reference($config['token_factory']));
        }

        return $providerId;
    }

    /**
     * Subclasses must return the id of the listener template.
     *
     * Listener definitions should inherit from the AbstractAuthenticationListener
     * like this:
     *
     *    <service id="my.listener.id"
     *             class="My\Concrete\Classname"
     *             parent="security.authentication.listener.abstract"
     *             abstract="true" />
     *
     * In the above case, this method would return "my.listener.id".
     *
     * @return string
     */
    protected function getListenerId()
    {
        return 'security.authentication.listener.lightsaml_sp';
    }

    /**
     * Defines the position at which the provider is called.
     * Possible values: pre_auth, form, http, and remember_me.
     *
     * @return string
     */
    public function getPosition()
    {
        return 'form';
    }

    public function getKey(): string
    {
        return 'light_saml_sp';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        if (isset($config['csrf_token_generator'])) {
            throw new InvalidConfigurationException('The "csrf_token_generator" on "form_login" does not exist, use "enable_csrf" instead.');
        }

        $authenticatorId = 'security.authenticator.form_login.'.$firewallName;
        $options = array_intersect_key($config, $this->options);
        $authenticator = $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.form_login'))
            ->replaceArgument(1, new Reference($userProviderId))
            ->replaceArgument(2, new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)))
            ->replaceArgument(3, new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)))
            ->replaceArgument(4, $options);

        if ($options['use_forward'] ?? false) {
            $authenticator->addMethodCall('setHttpKernel', [new Reference('http_kernel')]);
        }

        return $authenticatorId;
    }
    
    public function getPriority(): int
    {
        return 0;
    }
}
