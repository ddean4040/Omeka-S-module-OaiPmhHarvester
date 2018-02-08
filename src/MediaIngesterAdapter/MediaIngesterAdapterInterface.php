<?php
namespace OaiPmhHarvester\MediaIngesterAdapter;

interface MediaIngesterAdapterInterface
{
    public function getJson($mediaDatum);
}
