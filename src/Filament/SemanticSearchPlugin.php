<?php

namespace ValerianMemsk\SemanticSearch\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use ValerianMemsk\SemanticSearch\Filament\Pages\SemanticSearchSettings;

class SemanticSearchPlugin implements Plugin
{
    protected ?string $navigationGroup = null;
    protected ?string $navigationLabel = null;
    protected ?string $navigationIcon = 'heroicon-o-magnifying-glass';
    protected ?int $navigationSort = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'semantic-search';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            SemanticSearchSettings::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function navigationIcon(?string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): ?string
    {
        return $this->navigationIcon;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }
}
