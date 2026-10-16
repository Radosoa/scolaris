<?php

namespace App\Http\Requests\Concerns;

/**
 * Les mises à jour sont des remplacements complets (PUT) : un champ facultatif
 * absent de la requête doit être vidé, pas conservé.
 *
 * La classe utilisatrice déclare `protected array $champsFacultatifs = [...]`.
 */
trait ChampsFacultatifsNuls
{
    protected function prepareForValidation(): void
    {
        $this->merge(array_fill_keys(
            array_filter($this->champsFacultatifs, fn (string $champ) => ! $this->exists($champ)),
            null,
        ));
    }
}
