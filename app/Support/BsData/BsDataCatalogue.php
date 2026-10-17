<?php

namespace App\Support\BsData;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * One BattleScribe file (.cat catalogue or .gst game system), read with XPath under the "b" namespace prefix.
 */
final class BsDataCatalogue
{
    public readonly DOMXPath $xpath;

    public readonly string $id;

    public readonly string $name;

    public readonly bool $isLibrary;

    public readonly bool $isGameSystem;

    public function __construct(public readonly string $path)
    {
        $document = new DOMDocument;

        if (! @$document->load($path, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException("Fichier BSData illisible : {$path}");
        }

        $this->xpath = new DOMXPath($document);
        $this->xpath->registerNamespace('b', $document->documentElement->namespaceURI);

        $root = $document->documentElement;
        $this->id = $root->getAttribute('id');
        $this->name = $root->getAttribute('name');
        $this->isLibrary = $root->getAttribute('library') === 'true';
        $this->isGameSystem = $root->localName === 'gameSystem';
    }

    /**
     * Faction display name: the last segment of the catalogue name, without "Library"
     * ("Imperium - Adeptus Astartes - Blood Angels" → "Blood Angels", "Library - Titans" → "Titans").
     */
    public function factionName(): string
    {
        $segments = explode(' - ', trim(str_ireplace(['- Library', 'Library -', 'Library'], '', $this->name), ' -'));

        return trim(end($segments));
    }

    /**
     * Catalogues linked with their root entries imported.
     *
     * @return list<string> Target catalogue ids.
     */
    public function importedCatalogueIds(): array
    {
        $ids = [];

        foreach ($this->xpath->query('/*/b:catalogueLinks/b:catalogueLink[@importRootEntries="true"]') as $link) {
            /** @var DOMElement $link */
            $ids[] = $link->getAttribute('targetId');
        }

        return $ids;
    }

    /**
     * Every linked catalogue, whether its root entries are imported or not.
     *
     * @return list<string> Target catalogue ids.
     */
    public function linkedCatalogueIds(): array
    {
        $ids = [];

        foreach ($this->xpath->query('/*/b:catalogueLinks/b:catalogueLink') as $link) {
            /** @var DOMElement $link */
            $ids[] = $link->getAttribute('targetId');
        }

        return $ids;
    }

    /**
     * Targets of every entry link of the catalogue, at any depth.
     *
     * @return array<string, true>
     */
    public function linkTargetIds(): array
    {
        $ids = [];

        foreach ($this->xpath->query('//b:entryLink/@targetId') as $attribute) {
            $ids[$attribute->value] = true;
        }

        return $ids;
    }

    /**
     * Root entries of the catalogue: entry links (to resolve) and inline selection entries.
     *
     * @return list<DOMElement>
     */
    public function rootEntries(): array
    {
        return iterator_to_array($this->xpath->query(
            '/*/b:entryLinks/b:entryLink[@type="selectionEntry"][not(@hidden="true")] | /*/b:selectionEntries/b:selectionEntry[not(@hidden="true")]',
        ), false);
    }

    /**
     * Whether the catalogue defines units or single-model units among its shared entries.
     */
    public function definesUnits(): bool
    {
        return $this->xpath->query('/*/b:sharedSelectionEntries/b:selectionEntry[@type="unit" or @type="model"]')->length > 0;
    }

    /**
     * Every element carrying an id, for cross-file link resolution.
     *
     * @return iterable<DOMElement>
     */
    public function identifiedEntries(): iterable
    {
        return $this->xpath->query('//b:selectionEntry[@id] | //b:selectionEntryGroup[@id]');
    }

    /**
     * Groups of detachment choices, wherever the catalogue nests them.
     *
     * @return list<DOMElement>
     */
    public function detachmentGroups(): array
    {
        return iterator_to_array($this->xpath->query('//b:selectionEntryGroup[@name="Detachment" or @name="Detachments"]'), false);
    }

    /**
     * Enhancement sub-groups, one per detachment ("Gladius Task Force Enhancements"), wherever they are nested.
     *
     * @return list<DOMElement>
     */
    public function enhancementGroups(): array
    {
        return iterator_to_array($this->xpath->query(
            '//b:selectionEntryGroup[@name="Enhancements"]/b:selectionEntryGroups/b:selectionEntryGroup',
        ), false);
    }

    /**
     * Identifier of the "pts" cost type (defined in the game system).
     */
    public function pointsCostTypeId(): ?string
    {
        $costType = $this->xpath->query('/*/b:costTypes/b:costType[@name="pts"]')->item(0);

        return $costType instanceof DOMElement ? $costType->getAttribute('id') : null;
    }
}
