<?php
namespace OaiPmhHarvester\Controller;

use OaiPmhHarvester\Form\ImportForm;
use OaiPmhHarvester\Form\SetsForm;
use OaiPmhHarvester\Form\MappingForm;
use OaiPmhHarvester\Source\SourceInterface;
use OaiPmhHarvester\OaiPmhResource\Request;
use OaiPmhHarvester\Job\Harvest;
use finfo;
use Omeka\Media\Ingester\Manager;
use Omeka\Service\Exception\ConfigException;
use Omeka\Settings\UserSettings;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * @var string
     */
    protected $tempPath;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Manager
     */
    protected $mediaIngesterManager;

    /**
     * @var UserSettings
     */
    protected $userSettings;

    protected $api;
    
    /**
     * @param array $config
     * @param Manager $mediaIngesterManager
     * @param UserSettings $userSettings
     */
    public function __construct(array $config, Manager $mediaIngesterManager, UserSettings $userSettings, \Omeka\Api\Manager $api)
    {
        $this->config = $config;
        $this->api = $api;
        $this->mediaIngesterManager = $mediaIngesterManager;
        $this->userSettings = $userSettings;
    }

    public function indexAction()
    {

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $undoJobIds = [];
            foreach ($data['jobs'] as $jobId) {
                $undoJob = $this->undoJob($jobId);
                $undoJobIds[] = $undoJob->getId();
            }
            $message = new Message(
                'Undo in progress in the following jobs: %s', // @translate
                implode(', ', $undoJobIds));
            $this->messenger()->addSuccess($message);
        }
        
        $view = new ViewModel;
        
        $page = $this->params()->fromQuery('page', 1);
        $query = $this->params()->fromQuery() + [
            'page' => $page,
            'sort_by' => $this->params()->fromQuery('sort_by', 'id'),
            'sort_order' => $this->params()->fromQuery('sort_order', 'desc'),
        ];
        $response = $this->api()->search('harvester_imports', $query);
        $this->paginator($response->getTotalResults(), $page);
        $view->setVariable('imports', $response->getContent());
        return $view;
        
    }
    
    public function setsAction() {
        
        $view = new ViewModel;
        
        $request = $this->getRequest();
        
        if (!$request->isPost()) {
            return $this->redirect()->toRoute('admin/harvester/new');
        }
        
        $post = $this->params()->fromPost();
        
        // List sets from OAI-PMH and load into var
        // Get metadata formats and load into var
        $oaiRequest = new Request( $post['source'] );
        $sets = $oaiRequest->listSets();
//        print_r( $sets );

        $setsOptions = array_intersect_key($post, array_flip([
            'source',
        ]));
        $setsOptions['oai_request'] = $oaiRequest;
        
        $form = $this->getForm(SetsForm::class, $setsOptions);
        $view->form = $form;
        
        $view->setVariable( 'base_url', $post['source'] );
        $view->setVariable( 'oai_sets', $sets );
        
        return $view;
    }

    public function mapAction()
    {
        $view = new ViewModel;
        $request = $this->getRequest();

        if (!$request->isPost()) {
            return $this->redirect()->toRoute('admin/harvester/new');
        }

        $post = $this->params()->fromPost();
        
        $oaiRequest = new Request( $post['source'] );
        $formats    = $oaiRequest->listMetadataFormats();
        $formatURL  = $formats[ $post['format'] ];

        
        /**
        $itemSet = $this->api->search(
            'item_sets',
            array(
                'property' => array(
                    array(
                    'joiner'   => 'and',
                    'property' => 1,
                    'type'     => 'eq',
                    'text'     => 'NOTHING in common'
                    )
                )
            ),
            array(
                'responseContent' => 'reference'
            )
        )->getContent();
        
        if( ! empty( $itemSet ) ) {
            print_r($itemSet[0]->id());
        }
            
        
        $itemSet = $this->api->search(
            'item_sets',
            array(
                'property' => array(
                    array(
                    'property' => 1,
                    'type'     => 'eq',
                    'text'     => 'Test Set'
                    )
                )
            ),
            array(
                'responseContent' => 'reference'
            )
        )->getContent->id();
        if( ! empty( $itemSet ) ) {
            print_r($itemSet[0]->id() );
        }

        $set_title = 'TESTset-' . time();
        
        $props = [
            'dcterms:title' => [[
                'type'        => 'literal',
                'property_id' => 1,
                '@value'      => $set_title
            ]]
        ];
        
        $setID = $this->api->create(
            'item_sets',
            $props,
            array(
                'responseContent' => 'reference'
            )
            )->getContent()->id();
        print_r( $setID );
        */
        
        // Get field list from schema URL 
        $formatClass = sprintf(
            '\OaiPmhHarvester\OaiPmhResource\Adapter\%sAdapter',
            str_replace( '_','', ucwords( $post['format'], '_' ) ) 
        );
        
        if( class_exists( $formatClass ) ) {
            $adapter = new $formatClass( $formatURL );
        } else {
            $this->messenger()->addError( sprintf( 'Could not load adapter for format: %s', $post['format'] ) );
            //$this->messenger()->addWarning( sprintf( 'Could not load adapter for format: %s', $post['format'] ) );
            // TODO: Use generic class that reads XSD
        }

        // "subject" is term ID #3, and OAI field ID #12
          
        /**
         $elementMap = array(
            "0" => array(
                "dcterms:contributor" => 6
                ),
            "6" => array(
                "dcterms:identifier" => 10
                ),
            "11" => array(
                "dcterms:source" => 11
                ),
            "12" => array(
                "dcterms:subject" => 3
                ),
            "13" => array(
                "dcterms:title" => 1
                ),
            "14" => array(
                "dcterms:type" => 8
                )
        );
        
        */
        
        /*
        $record = $oaiRequest->getRecord( 'oai:lcoa1.loc.gov:loc.gmd/g3791p.rr002300', $post['format'] );
        
        echo 'Received the following record:<br>';
        print_r( $record );
        
        $extractedRecord = $adapter->extractRecord( $record['record']->record );
        
        echo 'Extracted the following record:<br>';
        print_r( $extractedRecord );
        
        */
        /**
        
        $mappedRecord = \OaiPmhHarvester\Job\Harvest::mapRecord( $extractedRecord, $adapter->getElements(), $elementMap );
        $mappedRecord['o:item_set'] = array(array(
            'o:id' => 2
        ));
        print_r( $mappedRecord );

        $create_response = $this->api->create( 'items', $mappedRecord );
        print_r( $create_response->getContent()->id() );
        */
        
        $fields = $adapter->getElements();
        
//        print_r( $fields );
        
        $mappingOptions = array_intersect_key($post, array_flip([
            'source',
            'format',
            'sets'
        ]));
        $mappingOptions['metadataFields'] = $fields;
        
        $form = $this->getForm(MappingForm::class, $mappingOptions);
        
        $form->setData($post);
        
        // Run the harvest on post back once mapping is completed
        if ( ! empty( $post['map_status'] ) && 'ready' == $post['map_status'] ) {
            
            if( $form->isValid() ) {
                $args = $this->cleanArgs($post);
                $this->saveUserSettings($args);
                unset($args['multivalue_by_default']);
                if (empty($args['automap_check_user_list'])) {
                    unset($args['automap_user_list']);
                }
                $dispatcher = $this->jobDispatcher();
                $job = $dispatcher->dispatch('OaiPmhHarvester\Job\Harvest', $args);
                // The CsvImport record is created in the job, so it doesn't
                // happen until the job is done.
                $message = new Message(
                    'Harvesting in background (%sjob #%d%s)', // @translate
                    sprintf('<a href="%s">',
                        htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                    ),
                    $job->getId(),
                   '</a>'
                );
                $message->setEscapeHtml(false);
                $this->messenger()->addSuccess($message);
                
                return $this->redirect()->toRoute('admin/harvester', ['action' => 'browse'], true);
                
            } else {
        
                // TODO Keep user variables when the form is invalid.
                $this->messenger()->addError('Invalid settings.'); // @translate
                //return $this->redirect()->toRoute('admin/csvimport');
            }
        }

        $view->setVariable('form', $form);
        $view->setVariable('source', $post['source']);
        $view->setVariable('format', $post['format']);
        $view->setVariable('sets', $post['sets']);
        $view->setVariable('elements', $fields );
        $view->setVariable('mappings', $this->getMappingsForResource('items'));
        return $view;
    }

    public function newAction()
    {
        $view = new ViewModel;
        $form = $this->getForm(ImportForm::class);
        $view->form = $form;
        return $view;
        
    }

    /**
     * Get the source class to manage the file, according to its media type.
     *
     * @todo Use the class TempFile before.
     *
     * @param array $fileData File data from a post ($_FILES).
     * @return SourceInterface|null
     */
    protected function getSource(array $fileData)
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mediaType = $finfo->file($fileData['tmp_name']);

        // Manage an exception for a very common format, undetected by fileinfo.
        if ($mediaType === 'text/plain') {
            $extensions = [
                'csv' => 'text/csv',
                'tab' => 'text/tab-separated-values',
                'tsv' => 'text/tab-separated-values',
            ];
            $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
            if (isset($extensions[$extension])) {
                $mediaType = $extensions[$extension];
            }
        }

        $sources = $this->config['harvester']['sources'];
        if (!isset($sources[$mediaType])) {
            return;
        }

        $source = new $sources[$mediaType];
        return $source;
    }

    /**
     * Helper to return ordered mappings of the selected resource type.
     *
     * @param string $resourceType
     * @return array
     */
    protected function getMappingsForResource($resourceType)
    {
        // First reorder mappings: for ergonomic reasons, it's cleaner to keep
        // the buttons of modules after the default ones. This is only needed in
        // the mapping form. The default order is set in this module config too,
        // before Zend merge.
        $config = include dirname(dirname(__DIR__)) . '/config/module.config.php';
        $defaultOrder = $config['harvester']['mappings'];
        $mappings = $this->config['harvester']['mappings'];
        
        if (isset($defaultOrder[$resourceType])) {
            $mappingClasses = array_values(array_unique(array_merge(
                $defaultOrder[$resourceType], $mappings[$resourceType]
            )));
        } else {
            $mappingClasses = $mappings[$resourceType];
        }
        
        $mappings = [];
        foreach ($mappingClasses as $mappingClass) {
            $mappings[] = new $mappingClass();
        }
        return $mappings;
    }

    /**
     * Helper to clean posted args to get more readable logs.
     *
     * @todo Mix with check in Import and make it available for external query.
     *
     * @param array $post
     * @return array
     */
    protected function cleanArgs(array $post)
    {
        $args = $post;

        // Set the default action if not set, for example for users.
        $args['action'] = empty($args['action'])
            ? \OaiPmhHarvester\Job\Import::ACTION_CREATE
            : $args['action'];

        // Set values as integer.
        foreach (['o:resource_template', 'o:resource_class', 'o:owner', 'o:item'] as $meta) {
            if (!empty($args[$meta]['o:id'])) {
                $args[$meta] = ['o:id' => (int) $args[$meta]['o:id']];
            }
        }
        foreach (['o:is_public', 'o:is_open', 'o:is_active'] as $meta) {
            if (isset($args[$meta]) && strlen($args[$meta])) {
                $args[$meta] = (int) $args[$meta];
            }
        }

        // Set arguments as integer.
        if (!empty($args['rows_by_batch'])) {
            $args['rows_by_batch'] = (int) $args['rows_by_batch'];
        }

        // Set arguments as boolean.
        foreach (['automap_check_names_alone', 'automap_check_user_list'] as $meta) {
            if (array_key_exists($meta, $args)) {
                $args[$meta] = (bool) $args[$meta];
            }
        }

        // Name of properties must be known to merge data and to process update.
        $api = $this->api();
        if (array_key_exists('column-property', $args)) {
            foreach ($args['column-property'] as $column => $ids) {
                $properties = [];
                foreach ($ids as $id) {
                    $term = $api->read('properties', $id)->getContent()->term();
                    $properties[$term] = (int) $id;
                }
                $args['column-property'][$column] = $properties;
            }
        }

        // Check the identifier property.
        if (array_key_exists('identifier_property', $args)) {
            $identifierProperty = $args['identifier_property'];
            if (empty($identifierProperty) && $identifierProperty !== 'internal_id') {
                $properties = $api->search('properties', ['term' => $identifierProperty])->getContent();
                if (empty($properties)) {
                    $args['identifier_property'] = null;
                }
            }
        }

        if (!array_key_exists('column-multivalue', $post)) {
            $args['column-multivalue'] = [];
        }

        // TODO: remove
        // Set default multivalue separator if not set, for example for users.
        if (!array_key_exists('multivalue_separator', $args)) {
            $args['multivalue_separator'] = $this->userSettings()
                ->get(
                    'harvest_multivalue_separator',
                    $this->config['harvester']['user_settings']['harvest_multivalue_separator']
                );
        }

        // TODO Move to the source class.
        unset($args['delimiter']);
        unset($args['enclosure']);

        // Reset the user list.
        if (!empty($args['automap_check_user_list']) && empty($args['automap_user_list'])) {
            $args['automap_user_list'] = $this->config['harvester']['user_settings']['harvest_automap_user_list'];
        }
        // Convert the user text into an array.
        elseif (array_key_exists('automap_user_list', $args)) {
            $args['automap_user_list'] = $this->getForm(ImportForm::class)
                ->convertUserListTextToArray($args['automap_user_list']);
        }

        // Set a default owner for a creation.
        if (empty($args['o:owner']['o:id']) && (empty($args['action']) || $args['action'] === Harvest::ACTION_CREATE)) {
            $args['o:owner'] = ['o:id' => $this->identity()->getId()];
        }

        // Clean up fomr/until params TODO: check date format?
        if( empty($args['harvest-from']) ) {
            unset( $args['harvest-from'] );
        }

        if( empty($args['harvest-until']) ) {
            unset( $args['harvest-until'] );
        }
        
        
        // Remove useless input fields from sidebars.
        unset($args['value-language']);
        unset($args['column-resource_property']);
        unset($args['column-item_set_property']);
        unset($args['column-item_property']);
        unset($args['column-media_property']);

        return $args;
    }

    /**
     * Save user settings.
     *
     * @param array $settings
     */
    protected function saveUserSettings(array $settings)
    {
        foreach ($this->config['harvester']['user_settings'] as $key => $value) {
            $name = substr($key, strlen('harvest_'));
            if (isset($settings[$name])) {
                $this->userSettings()->set($key, $settings[$name]);
            }
        }
    }

    protected function getMediaForms()
    {
        $mediaIngester = $this->mediaIngesterManager;

        $forms = [];
        foreach ($mediaIngester->getRegisteredNames() as $ingester) {
            $forms[$ingester] = [
                'label' => $mediaIngester->get($ingester)->getLabel(),
            ];
        }
        ksort($forms);
        return $forms;
    }

    /**
     * Move a file to the temp path.
     *
     * @param string $systemTempPath
     */
    protected function moveToTemp($systemTempPath)
    {
        move_uploaded_file($systemTempPath, $this->getTempPath());
    }

    /**
     * Get the path to the temporary file.
     *
     * @param null|string $tempDir
     * @return string
     */
    protected function getTempPath($tempDir = null)
    {
        if (isset($this->tempPath)) {
            return $this->tempPath;
        }
        if (!isset($tempDir)) {
            $config = $this->config;
            if (!isset($config['temp_dir'])) {
                throw new ConfigException('Missing temporary directory configuration');
            }
            $tempDir = $config['temp_dir'];
        }
        $this->tempPath = tempnam($tempDir, 'omeka');
        return $this->tempPath;
    }

    protected function undoJob($jobId)
    {
        $response = $this->api()->search('harvester_imports', ['job_id' => $jobId]);
        $csvImport = $response->getContent()[0];
        $dispatcher = $this->jobDispatcher();
        $job = $dispatcher->dispatch('OaiPmhHarvester\Job\Undo', ['jobId' => $jobId]);
        $response = $this->api()->update('harvester_imports',
            $csvImport->id(),
            [
                'o:undo_job' => ['o:id' => $job->getId() ],
            ]
        );
        return $job;
    }
}
