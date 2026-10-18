import Alpine from 'alpinejs';

/**
 * Texte en minuscules sans accents, pour une recherche tolérante (« pretre » trouve « Prêtre »).
 */
const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * Une option est visible si chaque mot recherché figure dans son texte de recherche (noms, mots-clés,
 * rôle, faction) et si ce n'est pas une Légende masquée ; l'option déjà choisie reste toujours visible.
 */
const isVisible = (option, query, showLegends, selected) => {
    if (String(option.value) === String(selected)) {
        return true;
    }
    if (! showLegends && option.legends) {
        return false;
    }
    const text = normalize(option.search);

    return normalize(query).split(/\s+/).filter(Boolean).every((word) => text.includes(word));
};

/**
 * Formulaire de filtres (GET) appliqué automatiquement, sans recharger la page : après une pause dans
 * la saisie, ou dès qu'une liste déroulante ou une case change, la page filtrée est récupérée en arrière-plan
 * et seule la zone de résultats (sélecteur dans l'attribut data-results du formulaire) est remplacée.
 * Le champ de saisie garde ainsi le focus et tout ce qui est tapé pendant la requête.
 */
Alpine.data('autoSubmitFilters', (delay = 300) => ({
    timer: null,
    controller: null,

    init() {
        this.$el.addEventListener('input', (event) => {
            if (event.target.matches('input[type=search], input[type=text]')) {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.refresh(), delay);
            }
        });

        this.$el.addEventListener('change', (event) => {
            if (event.target.matches('select, input[type=checkbox]')) {
                this.refresh();
            }
        });

        this.$el.addEventListener('submit', (event) => {
            event.preventDefault();
            this.refresh();
        });
    },

    async refresh() {
        const selector = this.$el.dataset.results;
        const target = selector ? document.querySelector(selector) : null;
        const url = new URL(this.$el.action);
        url.search = new URLSearchParams(new FormData(this.$el)).toString();

        if (! target) {
            window.location.assign(url);

            return;
        }

        // Une nouvelle frappe annule la requête précédente : seule la dernière recherche s'affiche.
        this.controller?.abort();
        this.controller = new AbortController();
        target.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, { signal: this.controller.signal, headers: { Accept: 'text/html' } });
            const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector(selector);

            if (fresh) {
                target.innerHTML = fresh.innerHTML;
                history.replaceState(null, '', url);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.location.assign(url);
            }
        } finally {
            target.removeAttribute('aria-busy');
        }
    },
}));

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
