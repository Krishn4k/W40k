<?php

namespace App\Enums;

/**
 * Army composition rules checked by the list analysis. Stored as text in composition_rules.rule_type,
 * where an unknown type (data imported later) is simply ignored.
 */
enum CompositionRuleType: string
{
    case MaxPoints = 'max_points';
    case RequiresDetachment = 'requires_detachment';
    case MaxDetachmentPoints = 'max_detachment_points';
    case ForceDispositionFromDetachments = 'force_disposition_from_detachments';
    case UniqueDetachmentTags = 'unique_detachment_tags';
    case MaxUnitCopies = 'max_unit_copies';
    case MaxEnhancements = 'max_enhancements';
    case EnhancementFromDetachments = 'enhancement_from_detachments';
    case UniqueEnhancements = 'unique_enhancements';
    case MaxUpgradeCopies = 'max_upgrade_copies';
    case EnhancementRequiresCharacter = 'enhancement_requires_character';
    case OneEnhancementPerAttachedUnit = 'one_enhancement_per_attached_unit';
    case SupportMustBeAttached = 'support_must_be_attached';
    case ValidAttachment = 'valid_attachment';
    case RequiresWarlord = 'requires_warlord';

    /**
     * Not stored: always checked, for units whose squad size has no cost in the point set.
     */
    case MissingCost = 'missing_cost';
}
