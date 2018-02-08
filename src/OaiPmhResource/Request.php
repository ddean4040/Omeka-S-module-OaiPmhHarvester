<?php
namespace OaiPmhHarvester\OaiPmhResource;

use Zend\Http\Client;

class Request
{
    /**
     * OAI-PMH error code for a repository with no set hierarchy
     */
    const OAI_ERR_NO_SET_HIERARCHY = 'noSetHierarchy';

    /**
     * @var string
     */
    private $_baseUrl;

    /**
     * @var Zend_Http_Client
     */
    private $_client;

    /**
     * Constructor.
     *
     * @param string $baseUrl
     */
    public function __construct($baseUrl = null) 
    {
        if ($baseUrl) {
            $this->setBaseUrl($baseUrl);
        }
    }

    public function setBaseUrl($baseUrl)
    {
        $this->_baseUrl = $baseUrl;
    }

    /**
     * List metadata response formats for the provider.
     *
     * @return array Keyed array, where key is the metadataPrefix and value
     * is the schema.
     */
    public function listMetadataFormats()
    {
        $xml = $this->_makeRequest(array(
            'verb' => 'ListMetadataFormats',
        ));

        $formats = array();
        $error = $this->_getError($xml);
        if ($error) {
            $formats['error'] = $error;
        }

        foreach ($xml->ListMetadataFormats->metadataFormat as $format) {
            $prefix = trim((string)$format->metadataPrefix);
            $schema = trim((string)$format->schema);
            $formats[$prefix] = $schema;
        }
        /**
         * It's important to consider that some repositories don't provide 
         * repository
         *  -wide metadata formats. Instead they only provide record level metadata 
         *  formats. Oai_dc is mandatory for all records, so if a
         *  repository doesn't provide metadata formats using ListMetadataFormats, 
         *  only expose the oai_dc prefix. For a data provider that doesn't offer 
         *  repository-wide metadata formats, see: 
         *  http://www.informatik.uni-stuttgart.de/cgi-bin/OAI/OAI.pl
         */
        if (empty($formats)) {
            $formats[OaipmhHarvester_Harvest_OaiDc::METADATA_PREFIX] =
                OaipmhHarvester_Harvest_OaiDc::METADATA_SCHEMA;
        }
        return $formats;
    }

    /**
     * List all records for a given request.
     *
     * @param array $query Args may include: metadataPrefix, set, 
     * resumptionToken, from.
     */
    public function listRecords(array $query = array())
    {
        $query['verb'] = 'ListRecords';
        $xml = $this->_makeRequest($query);
        $response = array(
            'records' => $xml->ListRecords->children(),
        );
        $error = $this->_getError($xml);
        if ($error) {
            $response['error'] = $error;
        }
        $token = $xml->ListRecords->resumptionToken;
        if ($token) {
            $response['resumptionToken'] = (string)$token;
        }
        return $response;
    }

    /**
     * List all available sets from the provider.
     *
     * Resumption token can be given for incomplete lists.
     * 
     * @param string|null $resumptionToken
     */
    public function listSets($resumptionToken = null)
    {
        $query = array(
            'verb' => 'ListSets',
        );
        if ($resumptionToken) {
            $query['resumptionToken'] = $resumptionToken;
        }

        $retVal = array();
        $sets = array();
        try {
            $xml = $this->_makeRequest($query);
        
            // Handle returned errors, such as "noSetHierarchy". For a data 
            // provider that has no set hierarchy, see: 
            // http://solarphysics.livingreviews.org/register/oai
            $error = $this->_getError($xml);
            if ($error) {
                $retVal['error'] = $error;
                if ($error['code'] ==
                        OaipmhHarvester_Request::OAI_ERR_NO_SET_HIERARCHY
                ) {
                    $sets = array();
                }
            } else {
                $sets = $xml->ListSets->children();
            }
            if (isset($xml->ListSets->resumptionToken)) {
                $retVal['resumptionToken'] = $xml->ListSets->resumptionToken;
            }
        } catch(Exception $e) {
            // If we're here, the provider didn't even respond with valid XML.
            // Try to continue with no sets.
            $sets = array();
        }

        $retVal['sets'] = $sets;
        return $retVal;
    }

    /**
     * Retrieve a single record from the provider.
     * 
     * @param string $identifier
     * @param string $metadataPrefix
     * 
     * @return array
     * 
     */
    public function getRecord( string $identifier, string $metadataPrefix ) {
        
        // TODO: maybe check supplied prefix against valid prefixes from repo? Or skip the HTTP request.
        
        $query = array(
            'verb'           => 'GetRecord',
            'metadataPrefix' => $metadataPrefix,
            'identifier'     => $identifier
        );
        
        try {
            
            $xml = $this->_makeRequest( $query );
            $error = $this->_getError($xml);
            if ($error) {
                $retVal['error'] = $error;
            }
            
            $response = array(
                'record' => $xml->GetRecord->children(),
            );
            
        } catch( Exception $e ) {
            return array(
                'error' => sprintf( 'Unable to retrieve record: %s - %s', $identifier, $e->getMessage() )
            );
        }
        
        return $response;
    }
    
    public function getClient()
    {
        if ($this->_client === null) {
            $this->setClient();
        }
        return $this->_client;
    }

    public function setClient(Client $client = null)
    {
        if ($client === null) {
            $client = new Client();
        }        
        $this->_client = $client;
    }

    private function _getError($xml)
    {
        $error = array();
        if ($xml->error) {
            $error['message'] = (string)$xml->error;   
            $error['code'] = $xml->error->attributes()->code;
        }
        return $error;
    }

    private function _makeRequest(array $query)
    {
        $client = $this->getClient();
        $curlAdapter = new Client\Adapter\Curl();
        $client->setAdapter($curlAdapter);
        
        $client->setUri($this->_baseUrl);
        $client->setOptions(
            array(
                'useragent' => $this->_getUserAgent(),
                'timeout'   => 60
            )
        );
        
        // Remove all other params when resumptionToken is present
        if( array_key_exists( 'resumptionToken', $query ) ) {
            $query = array(
                'verb'            => $query['verb'],
                'resumptionToken' => $query['resumptionToken']
            );
        }
        
        $client->setParameterGet($query);
        $response = $client->send();
        
        if ($response->isSuccess() && !$response->isRedirect()) {
            libxml_use_internal_errors(true);
            $iter = simplexml_load_string($response->getBody());
//            print_r( $iter );
            if ($iter !== false) {
                $ns = $iter->getNamespaces( true );
//                print_r( $ns );
                $ns_key = array_search("http://www.openarchives.org/OAI/2.0/", $ns);
                if ($ns_key !== false and $ns_key !== "") {
//                    echo 'Got key: ' . $ns_key;
                    $iter = simplexml_load_string($response->getBody(),
                            "SimpleXMLElement", 0, $ns_key, true);
//                    print_r( $iter );
                } else {
//                    echo 'No ns key loaded...' . "\n";
                }
            }
            if ($iter === false) {
                $errors = array();
                foreach(libxml_get_errors() as $error) {
                    $errors[] = trim($error->message) . ' on line ' 
                              . $error->line . ', column ' 
                              . $error->column;
                }
                libxml_clear_errors();
                libxml_use_internal_errors(false);
                _log(
                    "[OaipmhHarvester] Could not parse XML: " 
                    . $response->getBody()
                );
                $errStr = join("\n", $errors);
                _log("[OaipmhHarvester] XML errors in document: " . $errStr);
                throw new Client\Exception(
                    "Error in parsing response XML. XML document had the "
                    . "following errors: \n"
                    . $errStr
                );
            }
            return $iter;
        } else {
            libxml_clear_errors();
            libxml_use_internal_errors(false);
            throw new Client\Exception("Invalid URL ("
                . $response->getStatus() . " " . $response->getMessage()
                . ").");
        }
    }

    private function _getUserAgent()
    {
        return 'Omeka S OAI-PMH Harvester/0.1';
    }
}
