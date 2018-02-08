<?php
namespace OaiPmhHarvester\Job;

use OaiPmhHarvester\Entity\OaiPmhHarvesterImport;
use OaiPmhHarvester\Mvc\Controller\Plugin\FindResourcesFromIdentifiers;
use OaiPmhHarvester\OaiPmhResource\Request;
use OaiPmhHarvester\Source\SourceInterface;
use finfo;
use Omeka\Api\Manager;
use Omeka\Api\Response;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;
use Zend\Log\Logger;

class Harvest extends AbstractJob
{
    const ACTION_CREATE = 'create'; // @translate
    const ACTION_APPEND = 'append'; // @translate
    const ACTION_REVISE = 'revise'; // @translate
    const ACTION_UPDATE = 'update'; // @translate
    const ACTION_REPLACE = 'replace'; // @translate
    const ACTION_DELETE = 'delete'; // @translate
    const ACTION_SKIP = 'skip'; // @translate

    /**
     * Number of rows to process by batch.
     *
     * @var int
     */
    protected $rowsByBatch = 20;

    /**
     * @var Manager
     */
    protected $api;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var FindResourcesFromIdentifiers
     */
    protected $findResourcesFromIdentifiers;

    /**
     * @var OaiPmhHarvesterImport
     */
    protected $importRecord;

    /**
     * @var SourceInterface
     */
    protected $source;

    /**
     * @var string
     */
    protected $resourceType;

    /**
     * @var bool
     */
    protected $hasErr = false;

    /**
     * @var array
     */
    protected $stats;

    /**
     * @var array
     */
    protected $args;

    /**
     * @var array
     */
    protected $mappings;

    /**
     * @var array
     */
    protected $identifiers;

    /**
     * @var string|int
     */
    protected $identifierPropertyId;

    /**
     * @var bool
     */
    protected $importResource;

    /**
     * @var int
     */
    protected $emptyLines;

    public function perform()
    {
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');
        $this->findResourcesFromIdentifiers = $services->get('ControllerPluginManager')
                                                       ->get('findResourcesFromIdentifiers');
        $config = $services->get('Config');

        $this->logger->info( 'Starting OAI-PMH Harvest job' );
        
        $this->args = $this->job->getArgs();
        $args = &$this->args;
        
        $baseURL        = $this->getArg( 'source' );
        $metadataFormat = $this->getArg( 'format' );
        $selectedSets   = $this->getArg( 'sets' );
        $elementMap     = $this->getArg( 'column-property' );

        $oaiRequest = new Request( $baseURL );
        $formats    = $oaiRequest->listMetadataFormats();
        $formatURL  = $formats[ $metadataFormat ];
        
        $this->resourceType = $this->getArg('resource_type', 'items');

        $this->mappings = [];
        $mappingClasses = $config['harvester']['mappings'][$this->resourceType];
        foreach ($mappingClasses as $mappingClass) {
            $mapping = new $mappingClass();
            $mapping->init($args, $services);
            $this->mappings[] = $mapping;
        }
        
        // Add a record to the imports table for this job
        $oaiPmhHarvestJson = [
            'o:job' => ['o:id' => $this->job->getId()],
            'comment' => 'Job started',
            'resource_type' => $this->resourceType,
            'has_err' => false,
            'stats' => [],
        ];
        $response = $this->api->create('harvester_imports', $oaiPmhHarvestJson);
        $this->importRecord = $response->getContent();

        $this->checkOptions();
        if ($this->hasErr) {
            $this->logger->err( 'Error with checkOptions or creating imports record' );
            return $this->endJob();
        }

        // Get field list from schema URL
        $formatClass = sprintf(
            '\OaiPmhHarvester\OaiPmhResource\Adapter\%sAdapter',
            str_replace( '_','', ucwords( $metadataFormat, '_' ) )
            );
        
        if( class_exists( $formatClass ) ) {
            $adapter = new $formatClass( $formatURL );
        } else {
            $this->hasErr = true;
            $this->logger->err( sprintf( 'Could not load adapter for format: %s', $metadataFormat ) ); // @translate
            return $this->endJob();
        }

        $this->logger->info( sprintf( 'Loaded adapter for format: %s', $metadataFormat ) );
        
        $fields = $adapter->getElements();
        
        // The main identifier property may be used as term or as id in some
        // places, so prepare it one time only.
        if (empty($args['identifier_property']) || $args['identifier_property'] === 'internal_id') {
            $this->identifierPropertyId = $args['identifier_property'];
        } elseif (is_numeric($args['identifier_property'])) {
            $this->identifierPropertyId = (int) $args['identifier_property'];
        } else {
            $result = $this->api
            ->search('properties', ['term' => $args['identifier_property']])->getContent();
            $this->identifierPropertyId = $result ? $result[0]->id() : null;
        }

        if (!empty($args['rows_by_batch'])) {
            $this->rowsByBatch = (int) $args['rows_by_batch'];
        }

        // The core allows batch processes only for creation and deletion.
        if (!in_array($args['action'], [self::ACTION_CREATE, self::ACTION_DELETE, self::ACTION_SKIP])
            // It allows to identify resources too, so to use a new resource
            // from a previous row.
            || ($args['action'] === self::ACTION_CREATE && $this->resourceType === 'resources')
        ) {
            $this->rowsByBatch = 1;
        }

        $this->emptyLines = 0;
        
        $oaiRequest = new Request( $baseURL );
        
        $this->logger->info( 'Preparing to harvest sets' );
        
        foreach( $selectedSets as $set ) {
            
            $oaiQuery = array(
                'set' => $set,
                'metadataPrefix' => $metadataFormat
            );
            
            // Support date-restricted queries
            if( !empty( $args['harvest-from'] ) ) {
                $oaiQuery['from']  = $args['harvest-from'];
            }
            if( ! empty( $args['harvest-until'] ) ) {
                $oaiQuery['until'] = $args['harvest-until'];
            }
            
            // Use an item set just for this harvest
            $setTitle = sprintf(
                '%s-%s', 
                parse_url( $baseURL, PHP_URL_HOST ),
                $set
            );

            $this->logger->info( sprintf('Looking for set: %s', $setTitle ) );
            
            // Create the set for this harvest if it does not exist
            $itemSet = $this->api->search(
                'item_sets',
                array(
                    'property' => array(
                        array(
                            'property' => 1, // dcterms:title
                            'type'     => 'eq',
                            'text'     => $setTitle
                        )
                    )
                ),
                array(
                    'responseContent' => 'reference'
                )
            )->getContent();
            if( ! empty( $itemSet ) ) {
                $setID = $itemSet[0]->id();
            }
                
            if( empty( $setID ) ) {
                
                // Create a new item set for the harvested records
                $props = [
                    'dcterms:title' => [[
                        'type'        => 'literal',
                        'property_id' => 1,
                        '@value'      => $setTitle
                    ]]
                ];
                
                $setID = $this->api->create(
                    'item_sets',
                    $props,
                    array(
                        'responseContent' => 'reference'
                    )
                )->getContent()->id();
                
                // TODO: some error checking
                
            }

            $this->logger->info( sprintf('Found or created set: %s as %d', $setTitle, $setID ) );

            // Harvest records from the repository
            while( $response = $oaiRequest->listRecords( $oaiQuery ) ) {

                if( isset( $response['error'] ) ) {
                    $this->logger->err( sprintf('Error listing records: %s', print_r( $response['error'], true ) ) );
                    break;
                }
                
                $data = array();
                $records = $response['records'];
                foreach( $records->record as $record ) {
                    $mappedRecord = $this->mapRecord( $adapter->extractRecord( $record ), $fields, $elementMap );
                    $mappedRecord[ 'o:item_set' ] = array( array( 
                        'o:id' => $setID
                    ) );
                    $data[] = $mappedRecord;
                }
                
                $this->logger->info( 'Processing batch of records' );
                
                $this->processBatchData( $data );
                
                if( ! empty( $response['resumptionToken'] ) ) {
                    $oaiQuery['resumptionToken'] = $response['resumptionToken'];
                } else {
                    // When we don't get a resumption token, we're done
                    $this->logger->info( sprintf( 'No resumption token from server -- done with set: %s', $set ) );
                    unset( $oaiQuery['resumptionToken'] );
                    break;
                }
                
            }
            
            unset( $setID );
            
        }

        $this->endJob();
    }

    /**
     * Map record structure from that harvested by OAI to the one expected by the create API
     */
    protected function mapRecord( array $record, array $oaiElements, array $elementMap ) {
        
        $oaiMap = array_flip( $oaiElements );
        
        if( empty( $record ) || empty( $record['elementTexts'] ) )
            return false;
        
        $newRecord = array();
        foreach( $record['elementTexts'] as $elementName => $elementValues ) {
            
            foreach( $elementValues as $value ) {
                
                // Only process elements selected for import
                if( ! array_key_exists( $oaiMap[ $elementName ], $elementMap ) )
                    continue;
                
                $newElementName = key( $elementMap[ $oaiMap[ $elementName ] ] );
                $newElementID   = current( $elementMap[ $oaiMap[ $elementName ] ] );
                
                if( array_key_exists( $newElementName, $newRecord ) ) {
                    $newRecord[$newElementName][] = [
                        'type'        => "literal",
                        'property_id' => $newElementID,
                        "@value"      => $value['text']
                    ];
                } else {
                    $newRecord[$newElementName] = [[
                        'type'        => "literal",
                        'property_id' => $newElementID,
                        "@value"      => $value['text']
                    ]];
                }
            }
        }
        
        return $newRecord;        
        
    }
    
    /**
     * Process the import on a set of mapped rows.mappings
     *
     * @param array $data
     */
    protected function processBatchData(array $data)
    {
        $args = &$this->args;

        if ($this->importResource) {
            $data = $this->checkResources($data);
            // Because the import of resources is done by row, the resource
            // type is known and can be set.
            if (isset($data[0])) {
                $this->resourceType = $data[0]['resource_type'];
            }
        }

        switch ($args['action']) {
            case self::ACTION_CREATE:
                $this->create($data);
                break;

            case self::ACTION_APPEND:
            case self::ACTION_REVISE:
            case self::ACTION_UPDATE:
            case self::ACTION_REPLACE:
                $findResourcesFromIdentifiers = $this->findResourcesFromIdentifiers;
                $identifiers = $this->extractIdentifiers($data);
                $ids = $findResourcesFromIdentifiers($identifiers, $this->identifierPropertyId, $this->resourceType);
                $ids = $this->assocIdentifierKeysAndIds($identifiers, $ids);
                $idsToProcess = array_filter($ids);
                $idsRemaining = array_diff_key($ids, $idsToProcess);
                $dataToProcess = array_intersect_key($data, $idsToProcess);
                // The creation occurs before the update in all cases.
                switch ($args['action_unidentified']) {
                    case self::ACTION_CREATE:
                        $dataToCreate = array_intersect_key($data, $idsRemaining);
                        $this->create($dataToCreate);
                        break;
                    case self::ACTION_SKIP:
                        if ($idsRemaining) {
                            $identifiersRemaining = array_intersect_key($identifiers, $idsRemaining);
                            $this->stats(self::ACTION_SKIP, $identifiersRemaining);
                            $this->logger->info(new Message('The following identifiers are not associated with a resource and were skipped: "%s".', // @translate
                                implode('", "', $identifiersRemaining)));
                        }
                        break;
                }
                // Manage the special case where an item is updated and a
                // media is provided: it should be identified too in order
                // to update the one that belongs to this specified item.
                // It cannot be done during mapping, because the id of the
                // item is not known from the media source. In particular,
                // it avoids false positives in case of multiple files with
                // the same name for different items.
                if ($this->resourceType === 'items') {
                    $dataToProcess = $this->identifyMedias($dataToProcess, $idsToProcess);
                }
                $this->update($dataToProcess, $idsToProcess, $args['action']);
                break;

            case self::ACTION_DELETE:
                $findResourcesFromIdentifiers = $this->findResourcesFromIdentifiers;
                $identifiers = $this->extractIdentifiers($data);
                $ids = $findResourcesFromIdentifiers($identifiers, $this->identifierPropertyId, $this->resourceType);
                $idsToProcess = array_filter($ids);
                $idsRemaining = array_diff_key($ids, $idsToProcess);
                if ($idsRemaining) {
                    $identifiersRemaining = array_intersect_key($identifiers, $idsRemaining);
                    $this->stats(self::ACTION_SKIP, $identifiersRemaining);
                    $this->logger->info(new Message('The following identifiers are not associated with a resource and were skipped: "%s".', // @translate
                        implode('", "', $identifiersRemaining)));
                }
                $this->delete($idsToProcess);
                break;

            case self::ACTION_SKIP:
                $this->stats(self::ACTION_SKIP, $data);
                break;
        }

        if ($this->importResource) {
            $this->resourceType = 'resources';
        }
    }

    /**
     * Batch create a list of entities.
     *
     * @param array $data
     */
    protected function create(array $data)
    {
        if (empty($data)) {
            return;
        }

        // May fix some issues when a module doesn't manage batch create.
        if (count($data) == 1) {
            try {
                $response = $this->api->create( $this->resourceType, reset( $data ) );
            } catch (\Exception $e) {
                $this->logger->err((string) $e);
                $response = null;
            }
            $contents = $response ? [$response->getContent()] : [];
        } else {
            $response = $this->api->batchCreate($this->resourceType, $data, [], ['continueOnError' => true]);
            $contents = $response->getContent();
        }

        $this->logger->info( 'Creating stats' );
        
        $this->stats(self::ACTION_CREATE, $data, $contents);

        $createImportEntitiesJson = [];
        foreach ($contents as $resourceReference) {
            $createImportEntitiesJson[] = $this->buildImportRecordJson($resourceReference);
        }
        $createImportRecordResponse = $this->api->batchCreate(
            'harvester_entities', $createImportEntitiesJson, [], ['continueOnError' => true]);
    }

    /**
     * Batch update a list of entities.
     *
     * @param array $data
     * @param array $ids All the ids must exists and the order must be the same
     * than data.
     * @param string $action
     */
    protected function update(array $data, array $ids, $action)
    {
        if (empty($ids)) {
            return;
        }

        $fileData = [];
        $options = [];
        switch ($this->resourceType) {
            case 'item_sets':
            case 'items':
            case 'media':
                // TODO Manage and update file data.
                switch ($action) {
                    case self::ACTION_APPEND:
                    case self::ACTION_REVISE:
                    case self::ACTION_UPDATE:
                        break;
                    case self::ACTION_REPLACE:
                        $options['isPartial'] = false;
                        break;
                }
                break;

            default:
                $this->hasErr = true;
                $this->logger->err(new Message('The update mode "%s" is unsupported for %s currently.', // @translate
                    $action, $this->resourceType));
                return;
        }

        // In the api manager, batchUpdate() allows to update a set of resources
        // with the same data. Here, data are specific to each row, so each
        // resource is updated separately.
        $updatedIds = [];
        foreach ($ids as $key => $id) {
            try {
                switch ($action) {
                    case self::ACTION_APPEND:
                        $response = $this->append($this->resourceType, $id, $data[$key]);
                        break;
                    case self::ACTION_REVISE:
                        $response = $this->updateRevise($this->resourceType, $id, $data[$key], self::ACTION_REVISE);
                        break;
                    case self::ACTION_UPDATE:
                        $response = $this->updateRevise($this->resourceType, $id, $data[$key], self::ACTION_UPDATE);
                        break;
                    case self::ACTION_REPLACE:
                        $response = $this->api->update($this->resourceType, $id, $data[$key], $fileData, $options);
                        break;
                }
                $updatedIds[$key] = $id;
            } catch (\Exception $e) {
                $this->logger->err((string) $e);
                continue;
            }
        }
        if ($updatedIds) {
            $idsForLog = $this->idsForLog($updatedIds);
            $this->stats($action, $data, $updatedIds);
            $this->logger->info(new Message('%d %s were updated (%s): %s.', // @translate
                count($updatedIds), $this->resourceType, $action, $idsForLog));
        } else {
            $this->logger->notice(new Message('None of the %d %s were updated (%s).', // @translate
                count($ids), $this->resourceType, $action));
        }
    }

    /**
     * Batch delete a list of entities.
     *
     * @param array $ids
     */
    protected function delete(array $ids)
    {
        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }
        // May fix some issues when a module doesn't manage batch delete.
        if (count($ids) == 1) {
            // TODO Implement on delete cascade in the entity OaiPmhHarvesterEntity.
            try {
                $response = $this->api->delete($this->resourceType, reset($ids));
            } catch (\Exception $e) {
                $this->logger->err((string) $e);
                $response = null;
            }
            $contents = $response ? [$response->getContent()] : [];
        } else {
            $response = $this->api->batchDelete($this->resourceType, $ids, [], ['continueOnError' => true]);
            $contents = $response->getContent();
        }
        // TODO Get better stats of removed ids in case of error.
        $idsForLog = $this->idsForLog($ids, true);
        $this->stats(self::ACTION_DELETE, $ids, $contents);
        $this->logger->info(new Message('%d %s were removed: %s.', // @translate
            count($contents), $this->resourceType, $idsForLog));
    }

    /**
     * Check if medias to create belong to an existing item.
     *
     * To be used when importing media, that must have an item id.
     *
     * @param array $data
     * @return array
     */
    protected function checkMedias(array $data)
    {
        foreach ($data as $key => $entityJson) {
            if (empty($entityJson['o:item'])) {
                unset($data[$key]);
                $this->hasErr = true;
                $this->logger->err(new Message('A media to create is not attached to an item (%s).', // @translate
                    empty($entityJson['o:source'])
                        ? (isset($entityJson['o:ingester']) ? $entityJson['o:ingester'] : 'unknown ingester') // @translate
                        : $entityJson['o:ingester'] . ': ' . $entityJson['o:source']));
            }
        }
        return $data;
    }

    /**
     * Identify media of a list of items.
     *
     * To be used with rows that contain a media source.
     * The identifiers of media themselves are found as standard resources.
     *
     * @param array $data
     * @param array $ids All the ids must exists and the order must be the same
     * than data.
     * @return array
     */
    protected function identifyMedias(array $data, array $ids)
    {
        $findResourceFromIdentifier = $this->findResourcesFromIdentifiers;
        foreach ($data as $key => &$entityJson) {
            if (empty($entityJson['o:media'])) {
                continue;
            }
            foreach ($entityJson['o:media'] as &$media) {
                if (!empty($media['o:id'])) {
                    continue;
                }
                if (empty($media['o:source']) || empty($media['o:ingester'])) {
                    continue;
                }
                $identifierProperties = [];
                $identifierProperties['o:ingester'] = $media['o:ingester'];
                $identifierProperties['o:item']['o:id'] = $ids[$key];
                $resourceId = $findResourceFromIdentifier(
                    $media['o:source'], $identifierProperties, 'media');
                if ($resourceId) {
                    $media['o:id'] = $resourceId;
                }
            }
        }
        return $data;
    }

    /**
     * Move created medias at the last position of items.
     *
     * @todo Move this process in the core.
     *
     * @param array $resources
     */
    protected function reorderMedias(array $resources)
    {
        // Note: the position is not available in representation.

        $mediaIds = [];
        foreach ($resources as $resource) {
            // "Batch Create" returns a reference and "Create" a representation.
            if ($resource->resourceName() === 'media') {
                $mediaIds[] = $resource->id();
            }
        }
        $mediaIds = array_map('intval', $mediaIds);
        if (empty($mediaIds)) {
            return;
        }

        $services = $this->getServiceLocator();
        $conn = $services->get('Omeka\Connection');

        // Get the item ids first to avoid a sort issue with the subquery below.
        $qb = $conn->createQueryBuilder();
        $qb
            ->select('media.item_id')
            ->from('media', 'media')
            ->where($qb->expr()->in('id', $mediaIds))
            ->groupBy('media.item_id')
            ->orderBy('media.item_id', 'ASC');
        $stmt = $conn->executeQuery($qb, $qb->getParameters());
        $itemIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Get the media rank by item in one query even when position is set.
        // Note: in the subquery, the variable item_id should be set after rank.
        // If the media ids are used, a sort issue appears:
        // WHERE item_id IN (SELECT item_id FROM `media` WHERE id IN (%s) GROUP BY item_id ORDER BY item_id ASC)
        $conn->exec('SET @item_id = 0; SET @rank = 1;');
        $query = <<<'SQL'
SELECT id, rank FROM (
    SELECT id, @rank := IF(@item_id = item_id, @rank + 1, 1) AS rank, @item_id := item_id AS item
    FROM media
    WHERE item_id IN (%s)
    ORDER BY item_id ASC, -position DESC, id ASC
) AS media_rank;
SQL;
        $stmt = $conn->query(sprintf($query, implode(',', $itemIds)));
        $mediaRanks = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Update positions of the updated media.
        $entityManager = $services->get('Omeka\EntityManager');
        $mediaRepository = $entityManager->getRepository(\Omeka\Entity\Media::class);
        $medias = $mediaRepository->findById(array_keys($mediaRanks));
        foreach ($medias as $media) {
            $rank = $mediaRanks[$media->getId()];
            $media->setPosition($rank);
        }
        $entityManager->flush();
    }

    /**
     * Check if resources have a resource type.
     *
     * To be used when importing resources.
     *
     * @param array $data
     * @return array
     */
    protected function checkResources(array $data)
    {
        foreach ($data as $key => $entityJson) {
            if (empty($entityJson['resource_type'])) {
                // TODO Try to find the resource type according to other values.
                // Warning: default values may be set.
                unset($data[$key]);
                $this->hasErr = true;
                $this->logger->err(new Message('A resource type is required to import a resource.')); // @translate
            }
            // Avoid MediaSourceMapping issue when the resource type is unknown.
            elseif ($entityJson['resource_type'] === 'media') {
                $data[$key] += reset($data[$key]['o:media']);
                unset($data[$key]['o:media']);
            }
        }
        return $data;
    }

    /**
     * Helper to find identifiers from a batch of rows.
     *
     * The list of associated identifiers are kept as a class property until it
     * is recalled with new identifiers.
     *
     * @param array $data
     * @return array Associative array mapping the data key as key and the found
     * ids or null as value. Order is kept.
     */
    protected function extractIdentifiers(array $data)
    {
        $identifiers = [];

        foreach ($data as $key => $entityJson) {
            $identifier = null;
            switch ($this->identifierPropertyId) {
                case 'internal_id':
                    if (!empty($entityJson['o:id'])) {
                        $identifier = $entityJson['o:id'];
                    }
                    break;

                default:
                    switch ($this->resourceType) {
                        case 'item_sets':
                        case 'items':
                        case 'media':
                            foreach ($entityJson as $index => $value) {
                                if (is_array($value) && !empty($value)) {
                                    $value = reset($value);
                                    if (isset($value['property_id'])
                                        && $value['property_id'] == $this->identifierPropertyId
                                        && isset($value['@value'])
                                        && strlen($value['@value'])
                                    ) {
                                        $identifier = $value['@value'];
                                        break;
                                    }
                                }
                            }
                            break;
                        case 'users':
                            break;
                    }
            }
            $identifiers[$key] = $identifier;
        }

        $this->identifiers = $identifiers;
        return $identifiers;
    }

    /**
     * Helper to map data keys and ids in order to keep duplicate identifiers.
     *
     * When a document uses multiple lines of data, consecutive or not, they
     * have the same identifiers, but they are lost during the database search,
     * that returns a simple associative array.
     *
     * @param array $identifiers Associative array of data ids and identifiers.
     * @param array $ids Associative array of unique identifiers and ids.
     * @return array Associative array with data id as key and resource id as
     * value.
     */
    protected function assocIdentifierKeysAndIds(array $identifiers, array $ids)
    {
        return array_map(function ($v) use ($ids) {
            return $v ? $ids[$v] : null;
        }, $identifiers);
    }

    /**
     * Keep only data with an existing identifier.
     *
     * @param array $data
     * @param array $identifiers Associative array of identifiers and data ids.
     * @return array
     */
    protected function filterDataWithIdentifier(array $data, array $identifiers)
    {
        $identifiers = array_filter($identifiers, function ($v) {
            return !empty($v);
        });
        return array_intersect_key($data, $identifiers);
    }

    /**
     * Keep only data without an existing identifier.
     *
     * @param array $data
     * @param array $identifiers Associative array of data ids and identifiers.
     * @return array
     */
    protected function filterDataWithoutIdentifier(array $data, array $identifiers)
    {
        $identifiers = array_filter($identifiers);
        return array_intersect_key($data, $identifiers);
    }

    /**
     * Helper to get cleaner log when identifiers are used.
     *
     * @param array $ids
     * @param bool $hasIdentifierKeys
     * @return string
     */
    protected function idsForLog($ids, $hasIdentifierKeys = false)
    {
        switch ($this->args['identifier_property']) {
            case 'internal_id':
                // Nothing to do.
                break;
            default:
                if ($hasIdentifierKeys) {
                    array_walk($ids, function (&$v, $k) {
                        $v = new Message('"%s" (%d)', $k, $v); // @translate
                    });
                } else {
                    array_walk($ids, function (&$v, $k) {
                        $v = new Message('"%s" (%d)', $this->identifiers[$k], $v); // @translate
                    });
                }
                break;
        }
        return implode(', ', $ids);
    }

    /**
     * Update a resource (append with a deduplication check).
     *
     * Currently, Omeka S has no method to deduplicate, so a first call is done
     * to get all the data and to update them here, with a deduplication for
     * values, then a full replacement (not partial).
     *
     * @todo What to do with other data, and external data?
     *
     * @param string $resourceType
     * @param int $id
     * @param array $data
     * @return Response
     */
    protected function append($resourceType, $id, $data)
    {
        $resource = $this->api->read($resourceType, $id)->getContent();

        // Use arrays to simplify process.
        $currentData = json_decode(json_encode($resource), true);
        $merged = $this->mergeMetadata($currentData, $data, true);
        $data = array_replace($data, $merged);
        $newData = array_replace($currentData, $data);

        $fileData = [];
        $options['isPartial'] = false;
        return $this->api->update($resourceType, $id, $newData, $fileData, $options);
    }

    /**
     * Helper to update or revise a resource.
     *
     * The difference between revise and update is that all data that are set
     * replace current ones with "update", but only the filled ones replace
     * current one with "revise".
     *
     * @todo What to do with other data, and external data?
     *
     * @param string $resourceType
     * @param int $id
     * @param array $data
     * @param string $action
     * @return Response
     */
    protected function updateRevise($resourceType, $id, $data, $action)
    {
        $resource = $this->api->read($resourceType, $id)->getContent();

        // Use arrays to simplify process.
        $currentData = json_decode(json_encode($resource), true);
        switch ($action) {
            case self::ACTION_REVISE:
                $data = $this->removeEmptyData($data);
                break;
        }
        $replaced = $this->replacePropertyValues($currentData, $data);
        $newData = array_replace($data, $replaced);

        $fileData = [];
        $options['isPartial'] = true;
        $options['collectionAction'] = 'replace';
        return $this->api->update($resourceType, $id, $newData, $fileData, $options);
    }

    /**
     * Remove empty values from passed data in order not to change current ones.
     *
     * @todo Use the mechanism of preprocessBatchUpdate() of the adapter.
     *
     * @param array $data
     * @return array
     */
    protected function removeEmptyData(array $data)
    {
        // Data are updated in place.
        foreach ($data as $name => &$metadata) {
            switch ($name) {
                case 'o:resource_template':
                case 'o:resource_class':
                case 'o:owner':
                case 'o:item':
                    if (empty($metadata) || empty($metadata['o:id'])) {
                        unset($data[$name]);
                    }
                    break;
                case 'o:media':
                case 'o:item-set':
                    if (empty($metadata)) {
                        unset($data[$name]);
                    } elseif (array_key_exists('o:id', $metadata) && empty($metadata['o:id'])) {
                        unset($data[$name]);
                    }
                    break;
                // These values are not updatable and are removed.
                case 'o:ingester':
                case 'o:source':
                case 'ingest_filename':
                    unset($data[$name]);
                    break;
                case 'o:is_public':
                case 'o:is_open':
                    if (!is_bool($metadata)) {
                        unset($data[$name]);
                    }
                    break;
                default:
                    if (is_array($metadata)) {
                        if (empty($metadata)) {
                            unset($data[$name]);
                        }
                    }
            }
        }
        return $data;
    }

    /**
     * Merge current and new property values from two full resource metadata.
     *
     * @param array $currentData
     * @param array $newData
     * @param bool $keepIfNull Specify what to do when a value is null.
     * @return array Merged values extracted from the current and new data.
     */
    protected function mergeMetadata(array $currentData, array $newData, $keepIfNull = false)
    {
        // Merge properties.
        // Current values are cleaned too, because they have the property label.
        // So they are deduplicated too.
        $currentValues = $this->extractPropertyValuesFromResource($currentData);
        $newValues = $this->extractPropertyValuesFromResource($newData);
        $mergedValues = array_merge_recursive($currentValues, $newValues);
        $merged = $this->deduplicatePropertyValues($mergedValues);

        // Merge lists of ids.
        $names = ['o:item_set', 'o:item', 'o:media'];
        foreach ($names as $name) {
            if (isset($currentData[$name])) {
                if (isset($newData[$name])) {
                    $mergedValues = array_merge_recursive($currentData[$name], $newData[$name]);
                    $merged[$name] = $this->deduplicateIds($mergedValues);
                } else {
                    $merged[$name] = $currentData[$name];
                }
            } elseif (isset($newData[$name])) {
                $merged[$name] = $newData[$name];
            }
        }

        // Merge unique and boolean values (manage "null" too).
        $names = [
            'unique' => [
                'o:resource_template',
                'o:resource_class',
            ],
            'boolean' => [
                'o:is_public',
                'o:is_open',
                'o:is_active',
            ],
        ];
        foreach ($names as $type => $typeNames) {
            foreach ($typeNames as $name) {
                if (array_key_exists($name, $currentData)) {
                    if (array_key_exists($name, $newData)) {
                        if (is_null($newData[$name])) {
                            $merged[$name] = $keepIfNull
                                ? $currentData[$name]
                                : ($type == 'boolean' ? false : null);
                        } else {
                            $merged[$name] = $newData[$name];
                        }
                    } else {
                        $merged[$name] = $currentData[$name];
                    }
                } elseif (array_key_exists($name, $newData)) {
                    $merged[$name] = $newData[$name];
                }
            }
        }

        // TODO Merge third parties data.

        return $merged;
    }

    /**
     * Replace current property values by new ones that are set.
     *
     * @param array $currentData
     * @param array $newData
     * @return array Merged values extracted from the current and new data.
     */
    protected function replacePropertyValues(array $currentData, array $newData)
    {
        $currentValues = $this->extractPropertyValuesFromResource($currentData);
        $newValues = $this->extractPropertyValuesFromResource($newData);
        $updatedValues = array_replace($currentValues, $newValues);
        return $updatedValues ;
    }

    /**
     * Extract property values from a full array of metadata of a resource json.
     *
     * @param array $resourceJson
     * @return array
     */
    protected function extractPropertyValuesFromResource($resourceJson)
    {
        static $listOfTerms;
        if (empty($listOfTerms)) {
            $response = $this->api->search('properties', []);
            foreach ($response->getContent() as $member) {
                $term = $member->term();
                $listOfTerms[$term] = $term;
            }
        }
        return array_intersect_key($resourceJson, $listOfTerms);
    }

    /**
     * Deduplicate data ids for collections of items set, items, media....
     *
     * @param array $data
     * @return array
     */
    protected function deduplicateIds($data)
    {
        $dataBase = $data;
        // Base to normalize data in order to deduplicate them in one pass.
        $base = [];
        $base['id'] = ['o:id' => 0];
        // Deduplicate data.
        $data = array_map('unserialize', array_unique(array_map('serialize',
            // Normalize data.
            array_map(function ($v) use ($base) {
                return isset($v['o:id']) ? ['o:id' => $v['o:id']] : $v;
            }, $data)
        )));
        // Keep first original data.
        $data = array_intersect_key($dataBase, $data);
        return $data;
    }

    /**
     * Deduplicate property values.
     *
     * @param array $values
     * @return array
     */
    protected function deduplicatePropertyValues($values)
    {
        // Base to normalize data in order to deduplicate them in one pass.
        $base = [];
        $base['literal'] = ['property_id' => 0, 'type' => 'literal', '@language' => '', '@value' => ''];
        $base['resource'] = ['property_id' => 0, 'type' => 'resource', 'value_resource_id' => 0];
        $base['url'] = ['property_id' => 0, 'type' => 'url', '@id' => 0, 'o:label' => ''];
        foreach ($values as $key => $value) {
            $values[$key] = array_values(
                // Deduplicate values.
                array_map('unserialize', array_unique(array_map('serialize',
                    // Normalize values.
                    array_map(function ($v) use ($base) {
                        return array_replace($base[$v['type']], array_intersect_key($v, $base[$v['type']]));
                    }, $value)
                )))
            );
        }
        return $values;
    }

    /**
     * Get the source class to manage the file, according to its media type.
     *
     * @param string $filepath
     * @param string $mediaType Needed for some types of file without extension.
     * @return SourceInterface|null
     */
    protected function getSource($filepath, $mediaType = null)
    {
        if (empty($mediaType)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mediaType = $finfo->file($filepath);

            // Manage an exception for a very common format, undetected by fileinfo.
            if ($mediaType === 'text/plain') {
                $extensions = [
                    'csv' => 'text/csv',
                    'tab' => 'text/tab-separated-values',
                    'tsv' => 'text/tab-separated-values',
                ];
                $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
                if (isset($extensions[$extension])) {
                    $mediaType = $extensions[$extension];
                }
            }
        }

        $sources = $this->getServiceLocator()->get('Config')['harvester']['sources'];
        if (!isset($sources[$mediaType])) {
            return;
        }

        $source = new $sources[$mediaType];
        return $source;
    }

    /**
     * Check options used to import.
     *
     * @todo Mix with check in Import and make it available for external query.
     */
    protected function checkOptions()
    {
        if (empty($this->resourceType)) {
            $this->hasErr = true;
            $this->logger->err('Resource type is empty.'); // @translate
        }

        if (!in_array($this->resourceType, ['items', 'item_sets', 'media', 'resources', 'users'])) {
            $this->hasErr = true;
            $this->logger->err(new Message('Resource type "%s" is not managed.', $this->resourceType)); // @translate
        }

        $args = &$this->args;

        $args['action'] = empty($args['action']) ? self::ACTION_CREATE : $args['action'];
        $args['identifier_property'] = empty($args['identifier_property']) ? null : $args['identifier_property'];
        $args['action_unidentified'] = empty($args['action_unidentified']) ? self::ACTION_SKIP : $args['action_unidentified'];

        $allowedActions = [
            self::ACTION_CREATE,
            self::ACTION_APPEND,
            self::ACTION_REVISE,
            self::ACTION_UPDATE,
            self::ACTION_REPLACE,
            self::ACTION_DELETE,
            self::ACTION_SKIP,
        ];
        if (!in_array($args['action'], $allowedActions)) {
            $this->hasErr = true;
            $this->logger->err(new Message('Unknown action "%s".', $args['action'])); // @translate
        }

        // Specific check when a identifier is required.
        elseif (!in_array($args['action'], [self::ACTION_CREATE, self::ACTION_SKIP])) {
            if (empty($args['identifier_property'])) {
                $this->hasErr = true;
                $this->logger->err(new Message('The action "%s" requires a resource identifier property.', // @translate
                    $args['action']));
            }
            if ($args['action'] !== self::ACTION_DELETE && !in_array($this->resourceType, ['item_sets', 'items', 'media', 'resources'])) {
                $this->hasErr = true;
                $this->logger->err(new Message('The action "%s" is not available for resource type "%s" currently.', // @translate
                    $args['action'], $this->resourceType));
            }
        }

        if (!in_array($args['action'], [self::ACTION_CREATE, self::ACTION_DELETE, self::ACTION_SKIP])) {
            if (!in_array($args['action_unidentified'], [self::ACTION_SKIP, self::ACTION_CREATE])) {
                $this->hasErr = true;
                $this->logger->err(new Message('The action "%s" for unidentified resources is not managed.', // @translate
                    $args['action_unidentified']));
            }
        }
    }

    /**
     * Set the total of added, updated or deleted resources and processed rows.
     *
     * @todo Identify skipped rows and errors.
     *
     * @param string $action
     * @param array $data
     * @param array $result
     */
    protected function stats($action, array $data = [], array $result = [])
    {
        $actions = [
            self::ACTION_CREATE => 'added',
            self::ACTION_APPEND => 'updated',
            self::ACTION_REVISE => 'updated',
            self::ACTION_UPDATE => 'updated',
            self::ACTION_REPLACE => 'updated',
            self::ACTION_DELETE => 'deleted',
            self::ACTION_SKIP => 'skipped',
        ];
        $process = $actions[$action];

        $total = empty($this->stats[$process][$this->resourceType])
            ? 0
            : $this->stats[$process][$this->resourceType];

        switch ($process) {
            case 'added':
            case 'updated':
            case 'deleted':
                $this->stats[$process][$this->resourceType] = $total + count($result);
                break;
            case 'skipped':
                $this->stats[$process][$this->resourceType] = $total + count($data);
                // $this->stats['rows']['skipped'] = [];
                // $this->stats['rows']['error'] = [];
                break;
        }

        // Manage an exception for items: count media too.
        if ($this->resourceType === 'items') {
            switch ($process) {
                case 'added':
                    $itemIds = array_map(function ($v) {
                        return $v->id();
                    }, $result);
                    if ($itemIds) {
                        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
                        $query = $entityManager->createQuery(
                            sprintf('SELECT COUNT(media.id) FROM Omeka\Entity\Media media WHERE media.item IN (%s)',
                                implode(',', $itemIds)));
                        $total = $query->getSingleScalarResult();
                        if ($total) {
                            $this->stats[$process]['media'] = isset($this->stats[$process]['media'])
                                ? $this->stats[$process]['media'] + $total
                                : $total;
                        }
                    }
                    break;
                case 'updated':
                case 'deleted':
                case 'skipped':
                    // TODO Count updated / deleted / skipped media when updating items.
                    break;
            }
        }
    }

    protected function buildImportRecordJson($resourceReference)
    {
        $recordJson = [
            'o:job' => ['o:id' => $this->job->getId()],
            'entity_id' => $resourceReference->id(),
            'resource_type' => $this->resourceType,
        ];
        return $recordJson;
    }

    protected function endJob()
    {
        $csvImportJson = [
            'comment' => $this->getArg('comment'),
            'has_err' => $this->hasErr,
            'stats' => $this->stats,
        ];
        $response = $this->api->update('harvester_imports', $this->importRecord->id(), $csvImportJson);
        if ($this->source) {
            $this->source->clean();
        }
    }
}
