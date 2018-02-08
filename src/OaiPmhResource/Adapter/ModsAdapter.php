<?php
/**
 *
 *
 *
 */

namespace OaiPmhHarvester\OaiPmhResource\Adapter;

/**
 * Metadata format map for the MODS format
 */
class ModsAdapter extends AbstractAdapter {
    
    /*  XML schema and OAI prefix for the format represented by this class.
     These constants are required for all maps. */
    const METADATA_SCHEMA = 'http://www.loc.gov/standards/mods/v3/mods-3-7.xsd';
    const METADATA_PREFIX = '';
    
    /* Use this regex to indicate any related schemas that should be handled by this adapter, e.g. minor version differences */
    const METADATA_SCHEMA_REGEX = 'http://www.loc.gov/standards/mods/v3/mods-3-([\d]+).xsd';
    
    const MODS_NAMESPACE  = '';
    
    protected $_elements = array(
        "abstract",
        "accessCondition",
        "classification",
        "extension",
        "genre",
        "identifier",
        "language",
        "location>physicalLocation",
        "location>shelfLocator",
        "location>url",
        "location>holdingSimple",
        "location>holdingExternal",
        "name[role=creator|cre]",
        "name[other role]",
        "note",
        "originInfo>place",
        "originInfo>issuance",
        "originInfo>publisher",
        "originInfo>dateCreated",
        "originInfo>dateIssued",
        "originInfo>dateCaptured",
        "originInfo>dateValid",
        "originInfo>dateModified",
        "originInfo>dateOther",
        "originInfo>copyrightDate",
        "originInfo>edition",
        "originInfo>frequency",
        "part",
        "physicalDescription",
        "recordInfo>recordContentSource",
        "recordInfo>recordCreationDate",
        "recordInfo>recordChangeDate",
        "recordInfo>recordIdentifier",
        "recordInfo>languageOfCataloging",
        "recordInfo>recordOrigin",
        "recordInfo>descriptionStandard",
        "recordInfo>recordInfoNote",
        "relatedItem",
        "subject",
        "tableOfContents",
        "targetAudience",
        "titleInfo>title",
        "typeOfResource"
    );
    
    public function extractRecord( $record ) {
        
        
        
    }
    
}