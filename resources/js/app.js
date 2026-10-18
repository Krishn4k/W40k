import Alpine from 'alpinejs';

/**
 * Texte en minuscules sans accents, pour une recherche tolérante (« pretre » trouve « Prêtre »).
 */
const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * Une option est visible si elle correspond à la recherche et n'est pas une Légende masquée ;
 * l'option déjà choisie reste toujours visible pour ne pas perdre la sélection.
 */
const isVisible = (option, query, showLegends, selected) => String(option.value) === String(selected)
    || ((showLegends || ! option.legends) && (query === '' || normalize(option.search).includes(normalize(query))));

/**
 * Liste déroulante d'unités filtrable, regroupée par faction.
 */
Alpine.data('filterableSelect', (options, selected = '') => ({
    options,
    selected,
    query: '',
    showLegends: false,

    get visibleOptions() {
        return this.options.filter((option) => isVisible(option, this.query.trim(), this.showLegends, this.selected));
    },

    get visibleCount() {
        return this.visibleOptions.length;
    },

    get groups() {
        const groups = new Map();
        for (const option of this.visibleOptions) {
            if (! groups.has(option.group)) {
                groups.set(option.group, []);
            }
            groups.get(option.group).push(option);
        }

        return [...groups].map(([name, groupOptions]) => ({ name, options: groupOptions }));
    },
}));

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

    unitQuery: '',
    showLegends: false,

    get unit() {
        return this.options.units.find((unit) => unit.id === Number(this.unitId)) ?? null;
    },

    /**
     * Unités disponibles filtrées par la recherche et les Légendes, regroupées par faction.
     */
    get unitGroups() {
        const groups = new Map();
        for (const unit of this.options.units) {
            if (! isVisible({ ...unit, value: unit.id }, this.unitQuery.trim(), this.showLegends, this.unitId)) {
                continue;
            }
            if (! groups.has(unit.group)) {
                groups.set(unit.group, []);
            }
            groups.get(unit.group).push(unit);
        }

        return [...groups].map(([name, units]) => ({ name, options: units }));
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
