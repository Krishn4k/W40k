{{-- Champs de filtre communs aux listes d'unités : recherche par nom (FR ou EN) et affichage des Légendes. --}}
<div>
    <label for="q" class="form-label">Nom contient</label>
    <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Ex. Intercessor, Garde" class="form-input">
</div>
<label class="flex items-center gap-2 pb-2 text-sm">
    <input type="checkbox" name="legendes" value="1" @checked($withLegends) class="rounded border-zinc-300 dark:border-zinc-700">
    Afficher les Légendes
</label>
