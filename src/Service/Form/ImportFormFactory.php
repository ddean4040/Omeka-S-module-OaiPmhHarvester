<?php
namespace OaiPmhHarvester\Service\Form;

use OaiPmhHarvester\Form\ImportForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ImportFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ImportForm(null, $options);
        $config = $services->get('Config');
        $userSettings = $services->get('Omeka\Settings\User');
        $form->setConfigCsvImport($config['harvester']);
        $form->setUserSettings($services->get('Omeka\Settings\User'));
        return $form;
    }
}
