<?php

use Illuminate\Support\Arr;

/**
 * The keys of every translation file of a language, file by file, like « tables.toasts.copied ». The names of the
 * fields in validation.attributes are left out: a language without them writes the name of the field itself.
 *
 * @return list<string>
 */
function translationKeys(string $locale): array
{
    return collect(glob(lang_path("{$locale}/*.php")))
        ->flatMap(fn (string $path): array => array_map(
            fn (string $key): string => basename($path, '.php').'.'.$key,
            array_keys(Arr::dot(require $path)),
        ))
        ->reject(fn (string $key): bool => str_starts_with($key, 'validation.attributes'))
        ->sort()
        ->values()
        ->all();
}

test('every language of the interface translates exactly the same keys', function () {
    $keysByLocale = collect(config('app.supported_locales'))->keys()->mapWithKeys(fn (string $locale): array => [$locale => translationKeys($locale)]);

    expect($keysByLocale->except('en')->all())->each->toBe($keysByLocale->get('en'));
});
