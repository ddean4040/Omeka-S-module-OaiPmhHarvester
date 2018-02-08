<?php
namespace OaiPmhHarvester\Service\Controller;

use OaiPmhHarvester\Controller\IndexController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $mediaIngesterManager = $serviceLocator->get('Omeka\Media\Ingester\Manager');
        $config = $serviceLocator->get('Config');
        $userSettings = $serviceLocator->get('Omeka\Settings\User');
        $api = $serviceLocator->get('Omeka\ApiManager');
        $indexController = new IndexController($config, $mediaIngesterManager, $userSettings, $api);
        return $indexController;
    }
}
