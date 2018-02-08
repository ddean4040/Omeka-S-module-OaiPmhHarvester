<?php
namespace OaiPmhHarvester\Form;

use Omeka\Settings\UserSettings;
use Zend\Form\Form;

class ImportForm extends Form
{
    /**
     * @var array
     */
    protected $configCsvImport;

    /**
     * @var UserSettings
     */
    protected $userSettings;

    public function init()
    {
        $this->setAttribute('action', 'sets');

        $defaults = $this->configCsvImport['user_settings'];

        $this->add([
                'name' => 'source',
                'type' => 'url',
                'options' => [
                    'label' => 'Base URL', // @translate
                    'info' => 'The base URL of the OAI-PMH Repository', //@translate
                ],
                'attributes' => [
                    'id' => 'source',
                    'required' => 'true',
                ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'source',
            'required' => true,
        ]);
    }

    /**
     * Extract values that can't be passed via a select form element in Zend.
     *
     * The values is extracted from a string between "__>" and "<__".
     *
     * @param string $value
     * @return string
     */
    public function extractParameter($value)
    {
        if (strpos($value, '__>') === 0
            && ($pos = strpos($value, '<__')) == (strlen($value) - 3)
        ) {
            $result = substr($value, 3, $pos - 3);
            return $result === '\r' ? "\r" : $result;
        }
        return $value;
    }

    /**
     * Integrate values that can't be passed via a select form element in Zend.
     *
     * The values are integrated with a string between "__>" and "<__".
     *
     * @param string $value
     * @return string
     */
    public function integrateParameter($value)
    {
        $specialValues = ["\r", "\t", ' '];
        return in_array($value, $specialValues, true)
            ? sprintf('__>%s<__', $value)
            : $value;
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

    public function setConfigCsvImport(array $configCsvImport)
    {
        $this->configCsvImport = $configCsvImport;
    }

    public function setUserSettings(UserSettings $userSettings)
    {
        $this->userSettings = $userSettings;
    }

    /**
     * Return a list of standard delimiters.
     *
     * @return array
     */
    public function getDelimiterList()
    {
        return $this->delimiterList;
    }

    /**
     * Return a list of standard enclosures.
     *
     * @return array
     */
    public function getEnclosureList()
    {
        return $this->enclosureList;
    }

}
