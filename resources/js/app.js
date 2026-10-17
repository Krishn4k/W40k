import Alpine from 'alpinejs';

/**
 * Formulaire d'une unité de liste d'armée : les tailles d'escouade, lots de la collection
 * et optimisations proposés dépendent de l'unité choisie.
 */
Alpine.data('armyListEntryForm', (options, initial = {}) => ({
    options,
    unitId: initial.unitId ?? '',
    modelCount: initial.modelCount ?? '',
    enhancementId: initial.enhancementId ?? '',
    lotId: initial.lotId ?? '',
    isWarlord: initial.isWarlord ?? false,

    get unit() {
        return this.options.units.find((unit) => unit.id === Number(this.unitId)) ?? null;
    },

    get squadSizes() {
        return this.unit?.squadSizes ?? [];
    },

    get lots() {
        return (this.unit?.lots ?? []).filter(
            (lot) => lot.id === Number(initial.lotId) || ! this.options.usedLotIds.includes(lot.id),
        );
    },

    get canTakeEnhancement() {
        return this.unit?.canTakeEnhancement ?? false;
    },

    unitChanged() {
        if (! this.squadSizes.some((size) => size.modelCount === Number(this.modelCount))) {
            this.modelCount = this.squadSizes[0]?.modelCount ?? 1;
        }
        if (! this.lots.some((lot) => lot.id === Number(this.lotId))) {
            this.lotId = '';
        }
        if (! this.canTakeEnhancement) {
            this.enhancementId = '';
        }
    },
}));

window.Alpine = Alpine;

Alpine.start();
