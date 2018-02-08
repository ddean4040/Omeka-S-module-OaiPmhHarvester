<?php
namespace OaiPmhHarvester\Service\Form;

use OaiPmhHarvester\Form\SetsForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SetsFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new SetsForm(null, $options);
        $form->setImportSource( $options['source'] );
        $form->setOAIRequest( $options['oai_request'] );
        $config = $services->get('Config');
        $userSettings = $services->get('Omeka\Settings\User');
        $form->setUserSettings($services->get('Omeka\Settings\User'));
        return $form;
    }
}
