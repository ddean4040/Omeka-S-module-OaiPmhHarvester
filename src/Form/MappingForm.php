<?php
namespace OaiPmhHarvester\Form;

use OaiPmhHarvester\Job\Import;
use Omeka\Form\Element\ItemSetSelect;
use Omeka\Form\Element\PropertySelect;
use Omeka\Form\Element\ResourceSelect;
use Omeka\Form\Element\ResourceClassSelect;
use Zend\Form\Form;

class MappingForm extends Form
{
    protected $serviceLocator;
    
    /**
     * @var Source OAI-PMH URL
     */
    protected $sourceURL;
    
    /**
     * @var Metadata format for OAI-PMH harvest
     */
    protected $metadataFormat;
    
    /**
     * @var Record sets selected for harvest
     */
    protected $sets;

    public function init()
    {
        $resourceType = $this->getOption('resource_type'); // TODO: this does not load the resource_type of 'items'
        $serviceLocator = $this->getServiceLocator();
        $userSettings = $serviceLocator->get('Omeka\Settings\User');
        $config = $serviceLocator->get('Config');
        $default = $config['harvester']['user_settings'];
        $acl = $serviceLocator->get('Omeka\Acl');

//        var_dump( $resourceType );
        $resourceType = 'items';
        
        $this->add([
            'name' => 'map_status',
            'type' => 'hidden',
            'attributes' => [
                'value' => 'ready',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'source',
            'type' => 'hidden',
            'attributes' => [
                'value' => $this->sourceURL,
                'required' => true,
            ],
        ]);
        
        $this->add([
            'name' => 'format',
            'type' => 'hidden',
            'attributes' => [
                'value' => $this->metadataFormat,
                'required' => true,
            ],
        ]);
        
        
        /*
        $this->add([
            'name' => 'delimiter',
            'type' => 'hidden',
            'attributes' => [
                'value' => $this->getOption('delimiter'),
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'enclosure',
            'type' => 'hidden',
            'attributes' => [
                'value' => $this->getOption('enclosure'),
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'automap_check_names_alone',
            'type' => 'hidden',
            'attributes' => [
                'value' => $this->getOption('automap_check_names_alone'),
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'automap_check_user_list',
            'type' => 'hidden',
            'attributes' => [
                'value' => $this->getOption('automap_check_user_list'),
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'automap_user_list',
            'type' => 'hidden',
            'attributes' => [
                'value' => $this->getOption('automap_user_list'),
                'required' => true,
            ],
        ]);
*/
        $this->add([
            'name' => 'comment',
            'type' => 'textarea',
            'options' => [
                'label' => 'Comment', // @translate
                'info' => 'A note about the purpose or source of this import', // @translate
            ],
            'attributes' => [
                'id' => 'comment',
                'class' => 'input-body',
            ],
        ]);

        $this->add([
            'name' => 'harvest-from',
            'type' => 'date',
            'options' => [
                'label' => 'Partial harvest - Start Date', // @translate
                'info' => 'Specify a start date to perform a partial harvest. Omit to include all records.', //@translate
            ],
            'attributes' => [
                'id' => 'harvest-from',
                'class' => 'datepicker',
            ],
        ]);
        
        $this->add([
            'name' => 'harvest-until',
            'type' => 'date',
            'options' => [
                'label' => 'Partial harvest - End Date', // @translate
                'info' => 'Specify a end date to perform a partial harvest. Omit to include all records.', //@translate
            ],
            'attributes' => [
                'id' => 'harvest-until',
                'class' => 'datepicker',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'harvest-from',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'harvest-until',
            'required' => false,
        ]);
        
        // TODO: None of this works -- take it out for now?
        
        /*
        if (in_array($resourceType, ['item_sets', 'items', 'media', 'resources'])) {
            $urlHelper = $serviceLocator->get('ViewHelperManager')->get('url');
            $this->add([
                'name' => 'o:resource_template[o:id]',
                'type' => ResourceSelect::class,
                'options' => [
                    'label' => 'Resource template', // @translate
                    'info' => 'A pre-defined template for resource creation', // @translate
                    'empty_option' => 'Select a template', // @translate
                    'resource_value_options' => [
                        'resource' => 'resource_templates',
                        'query' => [],
                        'option_text_callback' => function ($resourceTemplate) {
                            return $resourceTemplate->label();
                        },
                    ],
                ],
                'attributes' => [
                    'id' => 'resource-template-select',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a template', // @translate
                    'data-api-base-url' => $urlHelper('api/default', ['resource' => 'resource_templates']),
                ],
            ]);

            $this->add([
                'name' => 'o:resource_class[o:id]',
                'type' => ResourceClassSelect::class,
                'options' => [
                    'label' => 'Class', // @translate
                    'info' => 'A type for the resource. Different types have different default properties attached to them.', // @translate
                    'empty_option' => 'Select a class', // @translate
                ],
                'attributes' => [
                    'id' => 'resource-class-select',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a class', // @translate
                ],
            ]);

            if (($resourceType === 'item_sets' && $acl->userIsAllowed('Omeka\Entity\ItemSet', 'change-owner'))
                || ($resourceType === 'items' && $acl->userIsAllowed('Omeka\Entity\Item', 'change-owner'))
                || ($resourceType === 'media' && $acl->userIsAllowed('Omeka\Entity\Media', 'change-owner'))
                // No rule for resources, so use item.
                || ($resourceType === 'resources' && $acl->userIsAllowed('Omeka\Entity\Item', 'change-owner'))
            ) {
                $this->add([
                    'name' => 'o:owner[o:id]',
                    'type' => ResourceSelect::class,
                    'options' => [
                        'label' => 'Owner', // @translate
                        'info' => 'If not set, the default owner will be the current user for a creation.', // @translate
                        'resource_value_options' => [
                            'resource' => 'users',
                            'query' => [],
                            'option_text_callback' => function ($user) {
                                return sprintf('%s (%s)', $user->email(), $user->name());
                            },
                        ],
                        'empty_option' => 'Select a user', // @translate
                    ],
                    'attributes' => [
                        'id' => 'select-owner',
                        'value' => '',
                        'class' => 'chosen-select',
                        'data-placeholder' => 'Select a user', // @translate
                        'data-api-base-url' => $urlHelper('api/default', ['resource' => 'users']),
                    ],
                ]);
            }

            $this->add([
                'name' => 'o:is_public',
                'type' => 'radio',
                'options' => [
                    'label' => 'Visibility', // @translate
                    'info' => 'The default visibility is private if the cell contains "0", "false", "no", "off" or "private" (case insensitive), else it is public.', // @translate
                    'value_options' => [
                        '1' => 'Public', // @translate
                        '0' => 'Private', // @translate
                    ],
                ],
            ]);

            switch ($resourceType) {
                case 'item_sets':
                    $this->add([
                        'name' => 'o:is_open',
                        'type' => 'radio',
                        'options' => [
                            'label' => 'Open/closed to additions', // @translate
                            'info' => 'The default openess is closed if the cell contains "0", "false", "off", or "closed" (case insensitive), else it is open.', // @translate
                            'value_options' => [
                                '1' => 'Open', // @translate
                                '0' => 'Closed', // @translate
                            ],
                        ],
                    ]);
                    break;

                case 'items':
                    break;

                case 'resources':
                    $this->add([
                        'name' => 'o:is_open',
                        'type' => 'radio',
                        'options' => [
                            'label' => 'Item sets open/closed to additions', // @translate
                            'info' => 'The default openess is closed if the cell contains "0", "false", "off", or "closed" (case insensitive), else it is open.', // @translate
                            'value_options' => [
                                '1' => 'Open', // @translate
                                '0' => 'Closed', // @translate
                            ],
                        ],
                    ]);

                    $this->add([
                        'name' => 'o:item_set',
                        'type' => ItemSetSelect::class,
                        'attributes' => [
                            'id' => 'select-item-set',
                            'class' => 'chosen-select',
                            'multiple' => true,
                            'data-placeholder' => 'Select item sets', // @translate
                        ],
                        'options' => [
                            'label' => 'Item sets for items', // @translate
                            'resource_value_options' => [
                                'resource' => 'item_sets',
                                'query' => [],
                            ],
                        ],
                    ]);
                    break;
            }

            $valueOptions = [
                Import::ACTION_CREATE => 'Create a new resource', // @translate
                Import::ACTION_APPEND => 'Append data to the resource', // @translate
                Import::ACTION_REVISE => 'Revise data of the resource', // @translate
                Import::ACTION_UPDATE => 'Update data of the resource', // @translate
                Import::ACTION_REPLACE => 'Replace all data of the resource', // @translate
                Import::ACTION_DELETE => 'Delete the resource', // @translate
                Import::ACTION_SKIP => 'Skip row', // @translate
            ];
            $this->add([
                'name' => 'action',
                'type' => 'select',
                'options' => [
                    'label' => 'Action', // @translate
                    'info' => 'In addition to the default "Create" and to the common "Delete", to manage most of the common cases, four modes of update are provided:
- append: add new data to complete the resource;
- revise: replace existing data to the resource by the ones set in each cell, except if empty (don\'t modify data that are not provided, except for default values);
- update: replace existing data to the resource by the ones set in each cell, even empty (don\'t modify data that are not provided, except for default values);
- replace: remove all properties of the resource, and fill new ones from the data.', // @translate
                    'value_options' => $valueOptions,
                ],
                'attributes' => [
                    'id' => 'action',
                    'class' => 'advanced-settings',
                ],
            ]);

            $this->add([
                'name' => 'multivalue_separator',
                'type' => 'text',
                'options' => [
                    'label' => 'Multivalue separator', // @translate
                    'info' => 'The separator to use for columns with multiple values', // @translate
                ],
                'attributes' => [
                    'id' => 'multivalue_separator',
                    'class' => 'input-body advanced-settings',
                    'value' => $userSettings->get(
                        'harvest_multivalue_separator',
                        $default['harvest_multivalue_separator']),
                ],
            ]);
            
            $this->add([
                'name' => 'global_language',
                'type' => 'text',
                'options' => [
                    'label' => 'Language', // @translate
                    'info' => 'Language setting to apply to all imported literal data. Individual property mappings can override the setting here.', // @translate
                ],
                'attributes' => [
                    'id' => 'global_language',
                    'class' => 'input-body value-language advanced-settings',
                    'value' => $userSettings->get(
                        'harvest_global_language',
                        $default['harvest_global_language']),
                ],
            ]);
            
            $this->add([
                'name' => 'identifier_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Resource identifier property', // @translate
                    'info' => 'Use this property, generally "dcterms:identifier", to identify the existing resources, so it will be possible to update them. One column of the file must map the selected property. In all cases, it is strongly recommended to add one ore more unique identifiers to all your resources.', // @translate
                    'empty_option' => 'Select below', // @translate
                    'term_as_value' => false,
                    'prepend_value_options' => [
                        'internal_id' => 'Internal id', // @translate
                    ],
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'value' => $userSettings->get(
                        'harvest_identifier_property',
                        $default['harvest_identifier_property']),
                    'class' => 'advanced-settings chosen-select',
                    'data-placeholder' => 'Select a property', // @translate
                ],
            ]);

            $this->add([
                'name' => 'action_unidentified',
                'type' => 'radio',
                'options' => [
                    'label' => 'Action on unidentified resources', // @translate
                    'info' => 'This option determines what to do when a resource does not exist but the action applies to an existing resource ("Append", "Update", or "Replace"). This option is not used when the main action is "Create", "Delete" or "Skip".', // @translate
                    'value_options' => [
                        Import::ACTION_SKIP => 'Skip the row', // @translate
                        Import::ACTION_CREATE => 'Create a new resource', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'action_unidentified',
                    'class' => 'advanced-settings',
                    'value' => Import::ACTION_SKIP,
                ],
            ]);

            $inputFilter->add([
                'name' => 'o:resource_template[o:id]',
                'required' => false,
            ]);
            $inputFilter->add([
                'name' => 'o:resource_class[o:id]',
                'required' => false,
            ]);
            $inputFilter->add([
                'name' => 'o:owner[o:id]',
                'required' => false,
            ]);
            $inputFilter->add([
                'name' => 'o:is_public',
                'required' => false,
            ]);
            $inputFilter->add([
                'name' => 'o:is_open',
                'required' => false,
            ]);
            $inputFilter->add([
                'name' => 'action',
                'required' => false,
            ]);
            $inputFilter->add([
                'name' => 'identifier_property',
                'required' => false,
            ]);
            $inputFilter->add([
                'name' => 'action_unidentified',
                'required' => false,
            ]);
        }
        */
    }

    public function setServiceLocator($serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
    
    /**
     *
     */
    public function setHarvestParams( $sourceURL, $sets, $metadataFormat ) {
        $this->sourceURL = $sourceURL;
        $this->metadataFormat = $metadataFormat;
        
        if( ! is_array( $sets ) )
            $sets = unserialize( $sets );
        $this->sets = $sets;
    }
    
}
