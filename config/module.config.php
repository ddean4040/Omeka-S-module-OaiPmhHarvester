<?php
namespace OaiPmhHarvester;

return [
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'csvPropertySelector' => View\Helper\PropertySelector::class,
        ],
        'factories' => [
            'mediaSourceSidebar' => Service\ViewHelper\MediaSourceSidebarFactory::class,
            'resourceSidebar' => Service\ViewHelper\ResourceSidebarFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            'OaiPmhHarvester\Form\ImportForm' => Service\Form\ImportFormFactory::class,
            'OaiPmhHarvester\Form\SetsForm' => Service\Form\SetsFormFactory::class,
            'OaiPmhHarvester\Form\MappingForm' => Service\Form\MappingFormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'OaiPmhHarvester\Controller\Index' => Service\Controller\IndexControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'automapHeadersToMetadata' => Service\ControllerPlugin\AutomapHeadersToMetadataFactory::class,
            'findResourcesFromIdentifiers' => Service\ControllerPlugin\FindResourcesFromIdentifiersFactory::class,
        ],
        'aliases' => [
            'findResourceFromIdentifier' => 'findResourcesFromIdentifiers',
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'harvester_entities' => Api\Adapter\EntityAdapter::class,
            'harvester_imports' => Api\Adapter\ImportAdapter::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'harvester' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/harvester',
                            'defaults' => [
                                '__NAMESPACE__' => 'OaiPmhHarvester\Controller',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'new' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/new',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'OaiPmhHarvester\Controller',
                                        'controller' => 'Index',
                                        'action' => 'new',
                                    ],
                                ],
                            ],
                            'sets' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/sets',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'OaiPmhHarvester\Controller',
                                        'controller' => 'Index',
                                        'action' => 'sets'
                                    ]
                                ]
                            ],
                            'map' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/map',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'OaiPmhHarvester\Controller',
                                        'controller' => 'Index',
                                        'action' => 'map',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'OAI-PMH Harvester',
                'route' => 'admin/harvester',
                'resource' => 'OaiPmhHarvester\Controller\Index',
                'pages' => [
                    [
                        'label' => 'Harvests', // @translate
                        'route' => 'admin/harvester',
                        'controller' => 'Index',
                        'action' => 'past-imports',
                        'resource' => 'OaiPmhHarvester\Controller\Index',
                    ],
                    [
                        'label' => 'New Harvest', // @translate
                        'route' => 'admin/harvester/new',
                        'controller' => 'Index',
                        'action' => 'new',
                        'resource' => 'OaiPmhHarvester\Controller\Index',
                    ],
                    [
                        'label' => 'Select Sets to Harvest', // @translate
                        'route' => 'admin/harvester/sets',
                        'resource' => 'OaiPmhHarvester\Controller\Index',
                        'visible' => false,
                    ],
                    [
                        'label' => 'Harvest Mapping', // @translate
                        'route' => 'admin/harvester/map',
                        'resource' => 'OaiPmhHarvester\Controller\Index',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        'Remove mapping', // @translate
        'Undo remove mapping', // @translate
        'Select an item type at the left before choosing a resource class.', // @translate
        'Select an element at the left before choosing a property.', // @translate
        'Please enter a valid language tag.', // @translate
        'Set multivalue separator for all columns', // @translate
        'Unset multivalue separator for all columns', // @translate
        'Advanced settings', // @translate
        'Identifier for', // @translate
    ],
    'harvester'  => [
        'sources' => [
            'application/vnd.oasis.opendocument.spreadsheet' => Source\OpenDocumentSpreadsheet::class,
            'text/csv' => Source\CsvFile::class,
            'text/tab-separated-values' => Source\TsvFile::class,
        ],
        'mappings' => [
            'item_sets' => [
                Mapping\ItemSetMapping::class,
                Mapping\PropertyMapping::class,
            ],
            'items' => [
                Mapping\ItemMapping::class,
                Mapping\PropertyMapping::class,
                Mapping\MediaSourceMapping::class,
            ],
            'media' => [
                Mapping\MediaMapping::class,
                Mapping\PropertyMapping::class,
                Mapping\MediaSourceMapping::class,
            ],
            'resources' => [
                Mapping\ResourceMapping::class,
                Mapping\PropertyMapping::class,
                Mapping\MediaSourceMapping::class,
            ],
            'users' => [
                Mapping\UserMapping::class,
            ],
        ],
        'media_ingester_adapter' => [
            'url' => MediaIngesterAdapter\UrlMediaIngesterAdapter::class,
            'html' => MediaIngesterAdapter\HtmlMediaIngesterAdapter::class,
            'iiif' => null,
            'oembed' => null,
            'youtube' => null,
        ],
        'automapping' => [
        ],
        'user_settings' => [
            'harvest_http_timeout_seconds' => 60,
            'harvest_automap_check_names_alone' => false,
            'harvest_automap_check_user_list' => false,
            'harvest_global_language' => '',
            'harvest_identifier_property' => '',
            'harvest_multivalue_separator' => ',',
            'harvest_automap_user_list' => [
                'owner' => 'owner_email',
                'owner email' => 'owner_email',
                'id' => 'internal_id',
                'internal id' => 'internal_id',
                'resource' => 'resource',
                'resources' => 'resource',
                'resource id' => 'resource',
                'resource identifier' => 'resource {dcterms:identifier}',
                'record' => 'resource',
                'records' => 'resource',
                'record id' => 'resource',
                'record identifier' => 'resource {dcterms:identifier}',
                'resource type' => 'resource_type',
                'record type' => 'resource_type',
                'resource template' => 'resource_template',
                'item type' => 'resource_class',
                'resource class' => 'resource_class',
                'visibility' => 'is_public',
                'public' => 'is_public',
                'item set' => 'item_set',
                'item sets' => 'item_set',
                'collection' => 'item_set',
                'collections' => 'item_set',
                'item set id' => 'item_set',
                'collection id' => 'item_set',
                'item set identifier' => 'item_set {dcterms:identifier}',
                'collection identifier' => 'item_set {dcterms:identifier}',
                'item set title' => 'item_set {dcterms:title}',
                'collection title' => 'item_set {dcterms:title}',
                'additions' => 'is_open',
                'open' => 'is_open',
                'item' => 'item',
                'items' => 'item',
                'item id' => 'item',
                'item identifier' => 'item {dcterms:identifier}',
                'media' => 'media',
                'media id' => 'media',
                'media identifier' => 'media {dcterms:identifier}',
                'media url' => 'media_source {url}',
                'media html' => 'media_source {html}',
                'html' => 'media_source {html}',
                'iiif' => 'media_source {iiif}',
                'iiif image' => 'media_source {iiif}',
                'oembed' => 'media_source {oembed}',
                'youtube' => 'media_source {youtube}',
                'user' => 'user_name',
                'name' => 'user_name',
                'display name' => 'user_name',
                'username' => 'user_name',
                'user name' => 'user_name',
                'email' => 'user_email',
                'user email' => 'user_email',
                'role' => 'user_role',
                'user role' => 'user_role',
                'active' => 'user_is_active',
                'is active' => 'user_is_active',
                // From module Mapping.
                'latitude' => 'mapping_latitude',
                'longitude' => 'mapping_longitude',
                'latitude/longitude' => 'mapping_latitude_longitude',
                'default latitude' => 'mapping_default_latitude',
                'default longitude' => 'mapping_default_longitude',
                'default zoom' => 'mapping_default_zoom',
            ],
        ]
    ],
];
