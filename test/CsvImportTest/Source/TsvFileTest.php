<?php
namespace OaiPmhHarvesterTest\Source;

use OaiPmhHarvester\Source\TsvFile;

if (!class_exists('OaiPmhHarvesterTest\Source\AbstractSource')) {
    require __DIR__ . '/AbstractSource.php';
}

class TsvFileTest extends AbstractSource
{
    protected $sourceClass = TsvFile::class;

    public function sourceProvider()
    {
        return [
            // filepath, options, expected for each test.
            ['test_column_missing.tsv', [], [false, 4, ['Identifier', 'Title', 'Description']]],
            ['test_column_in_excess.tsv', [], [false, 5, ['Identifier', 'Title', 'Description']]],
        ];
    }
}
