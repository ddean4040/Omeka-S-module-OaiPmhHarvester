<?php
namespace OaiPmhHarvester\Service\Form;

use OaiPmhHarvester\Form\MappingForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MappingFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new MappingForm(null, $options);
        $form->setServiceLocator($services);
        $form->setHarvestParams( $options['source'], $options['sets'], $options['format'] );
        return $form;
    }
}
