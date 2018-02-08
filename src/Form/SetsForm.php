<?php
namespace OaiPmhHarvester\Form;

use OaiPmhHarvester\OaiPmhResource\Request;
use Omeka\Settings\UserSettings;
use Zend\Form\Form;

class SetsForm extends Form
{
    /**
     * @var array
     */
    protected $configCsvImport;

    /**
     * @var UserSettings
     */
    protected $userSettings;
    
    /**
     * 
     */
    protected $oaiRequest;
    
    /**
     * @var Source OAI-PMH URL
     */
    protected $sourceURL;
    
    /**
     * @var OAI format options
     */
    protected $oaiFormats;

    public function init()
    {
        $this->setAttribute('action', 'map');

        $defaults = $this->configCsvImport['user_settings'];

        // TODO: This does not store the URL in the hidden field -- fix and remove from sets.phtml
        //        echo 'Setting hidden field to : ' . $this->sourceURL . "<br>\n";
        /*
        $this->add([
            'name' => 'source',
            'type' => 'hidden',
            'options' => [
                'value' => $this->sourceURL,
            ],
        ]);
        */
        
        $this->add([
            'name' => 'format',
            'type' => 'select',
            'options' => [
                'label' => 'Metadata format', // @translate
                'info' => 'Metadata formats available for harvest via OAI-PMH from this source.', // @translate
                'value_options' => $this->getOAIFormats(),
            ],
        ]);
        
        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'format',
            'required' => true,
        ]);
    }

    /**
     * 
     */
    public function setImportSource( $sourceURL ) {
        $this->sourceURL = $sourceURL;
    }
    
    /**
     * Retrieve the metadata formats supplied by the repository and mark which ones have adapters available
     */
    public function getOAIFormats() {
        
        $formats = $this->oaiRequest->listMetadataFormats();
        
        foreach( $formats as $formatCode => $formatURL ) {
            
            $formatClass = sprintf(
                '\OaiPmhHarvester\OaiPmhResource\Adapter\%sAdapter',
                str_replace( '_','', ucwords( $formatCode, '_' ) )
            );
            
            if( class_exists( $formatClass ) ) {
                try {
                    $adapter = new $formatClass( $formatURL );
                    $formats[$formatCode] .= ' -- (supported)';
                } catch( \Exception $e ) {
                    // Leave as unsupported
                }
            }
        }
        
        return $formats;
    }
    
    /**
     * 
     */
    public function setOAIRequest( $request ) {
        $this->oaiRequest = $request;
    }
    
    /**
     * Convert a user list text into an array.
     *
     * @param string $text
     * @return array
     */
    public function convertUserListTextToArray($text)
    {
        $result = [];
        $text = str_replace('  ', ' ', $text);
        $list = array_filter(array_map('trim', explode(PHP_EOL, $text)));
        foreach ($list as $line) {
            $map = array_filter(array_map('trim', explode('=', $line)));
            if (count($map) === 2) {
                $result[$map[0]] = $map[1];
            } else {
                $result[$line] = '';
            }
        }
        return $result;
    }

    /**
     * Convert a user list array into a text.
     *
     * @param array $list
     * @return string
     */
    public function convertUserListArrayToText($list)
    {
        $result = '';
        foreach ($list as $name => $mapped) {
            $result .= $name . ' = ' . $mapped . PHP_EOL;
        }
        return $result;
    }

    public function setUserSettings(UserSettings $userSettings)
    {
        $this->userSettings = $userSettings;
    }

}
