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
 * Ligne de la liste de traductions : le nom français est enregistré et validé sans recharger la page,
 * et le badge de statut se met à jour avec la réponse du serveur.
 */
Alpine.data('translationRow', (initial) => ({
    status: initial.status,
    statusLabel: initial.statusLabel,
    fr: initial.fr,
    saving: false,
    error: '',

    async save(form) {
        this.saving = true;
        this.error = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json().catch(() => ({}));

            if (! response.ok) {
                this.error = data.message ?? 'Enregistrement impossible.';

                return;
            }

            this.status = data.status;
            this.statusLabel = data.statusLabel;
            this.fr = data.fr;
        } catch {
            this.error = 'Erreur réseau : réessaie.';
        } finally {
            this.saving = false;
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
/**
 * Choix des détachements d'une liste : cases à cocher de la faction choisie, total des points de
 * détachement (DP) comparé à la limite du format, et tags « Unique » en double signalés.
 */
Alpine.data('detachmentPicker', (config) => ({
    ...config,
    query: '',

    /**
     * Détachements de la faction choisie et de sa faction mère (un chapitre propose ceux des Space Marines).
     */
    get detachments() {
        const parentId = this.parentFactionIds[this.factionId];

        return [...(this.detachmentsByFaction[this.factionId] ?? []), ...(parentId ? this.detachmentsByFaction[parentId] ?? [] : [])]
            .sort((first, second) => first.name.localeCompare(second.name));
    },

    /**
     * Détachements correspondant à la recherche ; ceux déjà cochés restent affichés pour être envoyés.
     */
    get visibleDetachments() {
        const words = normalize(this.query.trim()).split(/\s+/).filter(Boolean);

        return this.detachments.filter((detachment) => this.selectedIds.includes(detachment.id)
            || words.every((word) => normalize(`${detachment.name} ${detachment.uniqueTag ?? ''}`).includes(word)));
    },

    get selected() {
        return this.detachments.filter((detachment) => this.selectedIds.includes(detachment.id));
    },

    get spentPoints() {
        return this.selected.reduce((total, detachment) => total + (detachment.points ?? 0), 0);
    },

    get limit() {
        return this.pointLimits[this.gameFormatId] ?? null;
    },

    get pointsExceeded() {
        if (! this.limit || this.spentPoints <= this.limit.max) {
            return false;
        }

        return ! (this.selected.length === 1 && this.spentPoints <= (this.limit.single_max ?? this.limit.max));
    },

    /**
     * Dispositions de force accordées par les détachements cochés (celle déjà choisie reste proposée).
     */
    get availableDispositions() {
        const granted = new Set(this.selected.flatMap((detachment) => detachment.forceDispositions ?? []));

        return this.forceDispositions.filter((disposition) => granted.has(disposition.value) || disposition.value === this.forceDisposition);
    },

    get tagConflicts() {
        const byTag = {};
        for (const detachment of this.selected.filter((detachment) => detachment.uniqueTag)) {
            (byTag[detachment.uniqueTag] ??= []).push(detachment.name);
        }

        return Object.entries(byTag).filter(([, names]) => names.length > 1).map(([tag, names]) => `${tag} (${names.join(', ')})`);
    },
}));

/**
 * Saisie d'une partie : la disposition de force de la liste choisie est reprise, et la mission primaire
 * déduite des deux dispositions remplit le champ Mission tant qu'il est vide ou qu'il contient une mission déduite.
 */
Alpine.data('gameDispositions', (config) => ({
    ...config,

    get deducedMission() {
        return this.primaryMissions[this.forceDisposition]?.[this.opponentForceDisposition] ?? '';
    },

    isDeducedMission(text) {
        return Object.values(this.primaryMissions).some((missions) => Object.values(missions).includes(text));
    },

    listChanged() {
        if (this.listDispositions[this.armyListId]) {
            this.forceDisposition = this.listDispositions[this.armyListId];
            this.dispositionsChanged();
        }
    },

    dispositionsChanged() {
        if (this.deducedMission && (this.mission.trim() === '' || this.isDeducedMission(this.mission))) {
            this.mission = this.deducedMission;
        }
    },
}));

/**
 * Recharge la page courante en arrière-plan et remplace chaque zone marquée data-live-stats (tuiles, pastilles,
 * totaux) par sa version à jour, repérée par son id. Les clics rapprochés ne déclenchent qu'un seul rechargement.
 */
const refreshLiveStats = (() => {
    let timer = null;

    return () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const response = await fetch(window.location.href, { headers: { Accept: 'text/html' } });
            if (! response.ok) {
                return;
            }
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            for (const fresh of page.querySelectorAll('[data-live-stats][id]')) {
                document.getElementById(fresh.id)?.replaceWith(fresh);
            }
        }, 400);
    };
})();

/**
 * Ligne d'un lot dans la Collection : les nombres par statut se modifient sur place (− / + / » vers le statut
 * suivant) et sont enregistrés en arrière-plan ; si le serveur refuse, l'état précédent revient avec son message.
 */
Alpine.data('lotStatusRow', (counts, statuses, url) => ({
    counts: { ...counts },
    statuses,
    saving: false,
    error: '',

    get quantity() {
        return Object.values(this.counts).reduce((total, count) => total + (count ?? 0), 0);
    },

    get painted() {
        return (this.counts.painted ?? 0) + (this.counts.finished ?? 0);
    },

    change(status, delta) {
        const previous = { ...this.counts };
        this.counts[status] = Math.max(0, (this.counts[status] ?? 0) + delta);
        this.save(previous);
    },

    advance(status) {
        const next = this.statuses[this.statuses.findIndex((candidate) => candidate.value === status) + 1];
        if (! next || ! this.counts[status]) {
            return;
        }
        const previous = { ...this.counts };
        this.counts[status] -= 1;
        this.counts[next.value] = (this.counts[next.value] ?? 0) + 1;
        this.save(previous);
    },

    async save(previous) {
        this.saving = true;
        this.error = '';
        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ statuses: this.counts }),
            });
            const data = await response.json().catch(() => ({}));
            if (! response.ok) {
                throw new Error(Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'Enregistrement impossible.');
            }
            this.counts = { ...data.counts };
            refreshLiveStats();
        } catch (exception) {
            this.counts = previous;
            this.error = exception.message;
        } finally {
            this.saving = false;
        }
    },
}));

/**
 * Contenu JSON d'un élément de la page (ex. <script type="application/json">), analysé une seule fois
 * puis partagé par tous les composants qui le demandent.
 */
const pageData = (() => {
    const cache = new Map();

    return (elementId) => {
        if (! cache.has(elementId)) {
            cache.set(elementId, JSON.parse(document.getElementById(elementId)?.textContent ?? '{}'));
        }

        return cache.get(elementId);
    };
})();

Alpine.data('armyListEntryForm', (optionsElementId, initial = {}) => ({
    options: pageData(optionsElementId),
    unitId: initial.unitId ?? '',
    modelCount: initial.modelCount ?? '',
    enhancementId: initial.enhancementId ?? '',
    lotId: initial.lotId ?? '',
    attachedToEntryId: initial.attachedToEntryId ?? '',
    wargear: initial.wargear ?? {},
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

    /**
     * Optimisations des détachements de la liste : un Personnage (hors Héros épique) les prend toutes,
     * les autres unités seulement les Upgrades.
     */
    get enhancements() {
        if (! this.unit) {
            return [];
        }

        return this.options.enhancements.filter((enhancement) => this.unit.canTakeEnhancement || (enhancement.isUpgrade && this.unit.canTakeUpgrade));
    },

    get wargearOptions() {
        return this.unit?.wargear ?? [];
    },

    /**
     * Unités de la liste que cette unité Leader / Support peut rejoindre (pas elle-même ni une unité déjà rattachée).
     */
    get bodyguards() {
        const ids = this.unit?.bodyguardUnitIds ?? [];

        return this.options.entries.filter((entry) => ids.includes(entry.unitId)
            && entry.id !== Number(initial.entryId)
            && (! entry.isAttached || entry.id === Number(this.attachedToEntryId)));
    },

    unitChanged() {
        if (! this.squadSizes.some((size) => size.modelCount === Number(this.modelCount))) {
            this.modelCount = this.squadSizes[0]?.modelCount ?? 1;
        }
        if (! this.lots.some((lot) => lot.id === Number(this.lotId))) {
            this.lotId = '';
        }
        if (! this.enhancements.some((enhancement) => enhancement.id === Number(this.enhancementId))) {
            this.enhancementId = '';
        }
        if (! this.bodyguards.some((entry) => entry.id === Number(this.attachedToEntryId))) {
            this.attachedToEntryId = '';
        }
        this.wargear = {};
    },
}));

window.Alpine = Alpine;

Alpine.start();
