<?php

namespace Pieldefoca\Translatavel;

use Illuminate\Support\Str;

trait HasTranslations
{
    protected string | null $translationLocale = null;

    public static function usingLocale($locale): self
    {
        return (new self())->setLocale($locale);
    }

    public function getAttributeValue($key)
    {
        if (!$this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->getTranslation($key, $this->getLocale());
    }

    public function setAttribute($key, $value)
    {
        if(is_null($value)) return $this->attributes[$key] = null;

        if ($this->isTranslatableAttribute($key) && is_array($value)) {
            return $this->setTranslations($key, $value);
        }

        if (!$this->isTranslatableAttribute($key) || is_array($value)) {
            return parent::setAttribute($key, $value);
        }

        return $this->setTranslation($key, $this->getLocale(), $value);
    }

    public function translate($key, $locale = null, $useFallbackLocale = true)
    {
        return $this->getTranslation($key, $locale, $useFallbackLocale);
    }

    public function getTranslation($key, $locale, $useFallbackLocale = true)
    {
        $locale = $this->normalizeLocale($key, $locale, $useFallbackLocale);

        $translations = $this->getTranslations($key);

        $translation = $translations[$locale] ?? null;

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $translation);
        }

        return $translation;
    }

    public function getTranslationWithFallback($key, $locale)
    {
        return $this->getTranslation($key, $locale, true);
    }

    public function getTranslationWithoutFallback($key, $locale)
    {
        return $this->getTranslation($key, $locale, false);
    }

    public function getTranslations($key = null): array
    {
        if ($key !== null) {
            return array_filter(
                json_decode($this->getAttributes()[$key] ?? '' ?: '{}', true) ?: [],
                fn ($value) => $value !== null && $value !== ''
            );
        }

        return array_reduce($this->getTranslatableAttributes(), function ($result, $item) {
            $result[$item] = $this->getTranslations($item);

            return $result;
        });
    }

    public function setTranslation($key, $locale, $value): self
    {
        $translations = $this->getTranslations($key);

        $oldValue = $translations[$locale] ?? '';

        if ($this->hasSetMutator($key)) {
            $method = 'set' . Str::studly($key) . 'Attribute';

            $this->{$method}($value, $locale);

            $value = $this->attributes[$key];
        }

        $translations[$locale] = $value;

        $this->attributes[$key] = $this->asJson($translations);

        return $this;
    }

    public function setTranslations($key, array $translations): self
    {
        foreach ($translations as $locale => $translation) {
            $this->setTranslation($key, $locale, $translation);
        }

        return $this;
    }

    public function forgetTranslation($key, $locale): self
    {
        $translations = $this->getTranslations($key);

        unset(
            $translations[$locale],
            $this->$key
        );

        $this->setTranslations($key, $translations);

        return $this;
    }

    public function forgetAllTranslations($locale): self
    {
        collect($this->getTranslatableAttributes())->each(function ($attribute) use ($locale) {
            $this->forgetTranslation($attribute, $locale);
        });

        return $this;
    }

    public function getTranslatedLocales($key): array
    {
        return array_keys($this->getTranslations($key));
    }

    public function isTranslatableAttribute($key): bool
    {
        return in_array($key, $this->getTranslatableAttributes());
    }

    public function hasTranslation($key, $locale = null): bool
    {
        $locale = $locale ?: $this->getLocale();

        return isset($this->getTranslations($key)[$locale]);
    }

    public function replaceTranslations($key, array $translations): self
    {
        foreach ($this->getTranslatedLocales($key) as $locale) {
            $this->forgetTranslation($key, $locale);
        }

        $this->setTranslations($key, $translations);

        return $this;
    }

    protected function normalizeLocale($key, $locale, $useFallbackLocale)
    {
        if (in_array($locale, $this->getTranslatedLocales($key))) {
            return $locale;
        }

        if (!$useFallbackLocale) {
            return $locale;
        }

        if (!is_null($fallbackLocale = config('translatable.fallback_locale'))) {
            return $fallbackLocale;
        }

        if (!is_null($fallbackLocale = config('app.fallback_locale'))) {
            return $fallbackLocale;
        }

        return $locale;
    }

    public function setLocale($locale): self
    {
        $this->translationLocale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->translationLocale ?: config('app.locale');
    }

    public function getTranslatableAttributes(): array
    {
        return $this->translatableFields;
    }

    public function getTranslationsAttribute(): array
    {
        return collect($this->getTranslatableAttributes())
            ->mapWithKeys(function ($key) {
                return [$key => $this->getTranslations($key)];
            })
            ->toArray();
    }

    public function getCasts(): array
    {
        return array_merge(
            parent::getCasts(),
            array_fill_keys($this->getTranslatableAttributes(), 'array')
        );
    }
}
