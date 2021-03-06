<?php

/*
 * This file is part of the Symfony package.
*
* (c) Fabien Potencier <fabien@symfony.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Symfony\Tests\Bridge\Doctrine\DependencyInjection\Compiler;

use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterEventListenersAndSubscribersPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessEventListenersWithPriorities()
    {
        $container = $this->createBuilder();

        $container
            ->register('a', 'stdClass')
            ->addTag('doctrine.event_listener', array(
                'event' => 'foo',
                'priority' => -5,
            ))
            ->addTag('doctrine.event_listener', array(
                'event' => 'bar',
            ))
        ;
        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_listener', array(
                'event' => 'foo',
            ))
        ;

        $this->process($container);
        $this->assertEquals(array('b', 'a'), $this->getServiceOrder($container, 'addEventListener'));

        $calls = $container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls();
        $this->assertEquals(array('foo', 'bar'), $calls[1][1][0]);
    }

    public function testProcessEventSubscribersWithPriorities()
    {
        $container = $this->createBuilder();

        $container
            ->register('a', 'stdClass')
            ->addTag('doctrine.event_subscriber')
        ;
        $container
            ->register('b', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'priority' => 5,
            ))
        ;
        $container
            ->register('c', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'priority' => 10,
            ))
        ;
        $container
            ->register('d', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'priority' => 10,
            ))
        ;
        $container
            ->register('e', 'stdClass')
            ->addTag('doctrine.event_subscriber', array(
                'priority' => 10,
            ))
        ;

        $this->process($container);
        $this->assertEquals(array('c', 'd', 'e', 'b', 'a'), $this->getServiceOrder($container, 'addEventSubscriber'));
    }

    private function process(ContainerBuilder $container)
    {
        $pass = new RegisterEventListenersAndSubscribersPass('doctrine.connections', 'doctrine.dbal.%s_connection.event_manager', 'doctrine');
        $pass->process($container);
    }

    private function getServiceOrder(ContainerBuilder $container, $method)
    {
        $order = array();
        foreach ($container->getDefinition('doctrine.dbal.default_connection.event_manager')->getMethodCalls() as $call) {
            list($name, $arguments) = $call;
            if ($method !== $name) {
                continue;
            }

            if ('addEventListener' === $name) {
                $order[] = (string) $arguments[1];
                continue;
            }

            $order[] = (string) $arguments[0];
        }

        return $order;
    }

    private function createBuilder()
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.dbal.default_connection.event_manager', 'stdClass');
        $container->register('doctrine.dbal.default_connection', 'stdClass');
        $container->setParameter('doctrine.connections', array('default' => 'doctrine.dbal.default_connection'));

        return $container;
    }
}
