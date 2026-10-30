<?php

namespace App\Support\BsData;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

/**
 * One BattleScribe file, read with XPath under the "b" namespace prefix: a .cat catalogue or .gst game
 * system in XML (10th edition), or the same content transcribed in .json (11th edition), converted on load.
 */
final class BsDataCatalogue
{
    public readonly DOMXPath $xpath;

    public readonly string $id;

    public readonly string $name;

    public readonly bool $isLibrary;

    public readonly bool $isGameSystem;

    /**
     * Elements matched by an XPath expression, relative to the context node when given (other node types are left out).
     *
     * @return list<DOMElement>
     */
    public function elements(string $expression, ?DOMNode $context = null): array
    {
        $elements = [];

        foreach ($this->xpath->query($expression, $context) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * Values of the attributes matched by an XPath expression (e.g. "//b:entryLink/@targetId").
     *
     * @return list<string>
     */
    public function attributeValues(string $expression, ?DOMNode $context = null): array
    {
        $values = [];

        foreach ($this->xpath->query($expression, $context) ?: [] as $node) {
            if ($node instanceof DOMAttr) {
                $values[] = $node->value;
            }
        }

        return $values;
    }

    public function __construct(public readonly string $path)
    {
        $document = str_ends_with(strtolower($path), '.json') ? self::documentFromJson($path) : new DOMDocument;

        if (! $document->documentElement && ! @$document->load($path, LIBXML_NONET | LIBXML_COMPACT)) {
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
     * Rebuild the BattleScribe XML tree from its JSON transcription: scalar properties become attributes,
     * lists become a container element holding one singular child per item ("costs" → <costs><cost/></costs>,
     * "sharedSelectionEntries" → <sharedSelectionEntries><selectionEntry/></sharedSelectionEntries>).
     */
    private static function documentFromJson(string $path): DOMDocument
    {
        $data = json_decode((string) @file_get_contents($path), true);
        $rootName = is_array($data) ? array_key_first($data) : null;

        if (! in_array($rootName, ['catalogue', 'gameSystem'], true) || ! is_array($data[$rootName])) {
            throw new RuntimeException("Fichier BSData illisible : {$path}");
        }

        $properties = $data[$rootName];
        $namespace = $properties['xmlns'] ?? "http://www.battlescribe.net/schema/{$rootName}Schema";
        unset($properties['xmlns']);

        $document = new DOMDocument;
        $root = $document->createElementNS($namespace, $rootName);
        $document->appendChild($root);
        self::appendJsonProperties($document, $root, $properties, $namespace);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function appendJsonProperties(DOMDocument $document, DOMElement $element, array $properties, string $namespace): void
    {
        foreach ($properties as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $container = $document->createElementNS($namespace, $key);
                $element->appendChild($container);
                $childName = self::singular($key);

                foreach ($value as $item) {
                    $child = $document->createElementNS($namespace, $childName);
                    $container->appendChild($child);
                    is_array($item) ? self::appendJsonProperties($document, $child, $item, $namespace) : $child->textContent = (string) $item;
                }
            } elseif (is_array($value)) {
                $child = $document->createElementNS($namespace, $key);
                $element->appendChild($child);
                self::appendJsonProperties($document, $child, $value, $namespace);
            } elseif ($key === '$text') {
                $element->appendChild($document->createTextNode((string) $value));
            } elseif ($value !== null && preg_match('/^[A-Za-z_][\w.-]*$/', (string) $key) === 1) {
                $element->setAttribute($key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }
        }
    }

    /**
     * Element name of a list item: "sharedSelectionEntries" → "selectionEntry", "costs" → "cost".
     */
    private static function singular(string $listName): string
    {
        $name = lcfirst((string) preg_replace('/^shared/', '', $listName));

        return match (true) {
            str_ends_with($name, 'ies') => substr($name, 0, -3).'y',
            str_ends_with($name, 's') => substr($name, 0, -1),
            default => $name,
        };
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

        foreach ($this->attributeValues('//b:entryLink/@targetId') as $targetId) {
            $ids[$targetId] = true;
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
     * @return list<DOMElement>
     */
    public function identifiedEntries(): array
    {
        return $this->elements('//b:selectionEntry[@id] | //b:selectionEntryGroup[@id]');
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
     * Enhancements listed directly in an "Enhancements" group, without per-detachment sub-group.
     *
     * @return list<DOMElement>
     */
    public function flatEnhancementEntries(): array
    {
        return iterator_to_array($this->xpath->query(
            '//b:selectionEntryGroup[@name="Enhancements"]/b:selectionEntries/b:selectionEntry[@type="upgrade"]',
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
