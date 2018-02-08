<?php
namespace OaiPmhHarvester\Service\ControllerPlugin;

use OaiPmhHarvester\Mvc\Controller\Plugin\AutomapHeadersToMetadata;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AutomapHeadersToMetadataFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $config = $serviceLocator->get('Config');
        $plugin = new AutomapHeadersToMetadata();
        $plugin->setConfigCsvImport($config['harvester']);
        $plugin->setApiManager($serviceLocator->get('Omeka\ApiManager'));
        $plugin->setTranslator($serviceLocator->get('MvcTranslator'));
        return $plugin;
    }
}
