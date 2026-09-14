<?php

namespace ValerianMemsk\SemanticSearch\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use ValerianMemsk\SemanticSearch\Models\SemanticDictionary;

class SemanticSearchSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.form-page';

    public ?array $data = [];

    public function mount(): void
    {
        $dictionaryModel = config('semantic-search.dictionary.model', SemanticDictionary::class);

        $ollamaUrl = config('semantic-search.drivers.ollama.url', 'http://localhost:11434');
        $ollamaModel = config('semantic-search.drivers.ollama.model', 'bge-m3');
        $threshold = (float) config('semantic-search.threshold', 0.49);

        if (class_exists('App\Facades\Config')) {
            $ollamaUrl = \App\Facades\Config::get('search.semantic.ollama.url') ?: $ollamaUrl;
            $ollamaModel = \App\Facades\Config::get('search.semantic.ollama.model') ?: $ollamaModel;
            $threshold = (float) (\App\Facades\Config::get('search.semantic.ollama.threshold') ?: $threshold);
        }

        $formData = [
            'search' => [
                'semantic' => [
                    'ollama' => [
                        'url' => $ollamaUrl,
                        'model' => $ollamaModel,
                        'threshold' => $threshold,
                    ],
                ],
            ],
            'dictionary' => class_exists($dictionaryModel)
                ? $dictionaryModel::all(['id', 'term', 'replacement'])->toArray()
                : [],
        ];

        $this->form->fill($formData);
    }

    public function getTitle(): string
    {
        return __('Semantic Search Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Semantic Search');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    protected function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->ollamaSection(),
                $this->dictionarySection(),
            ])
            ->statePath('data');
    }

    protected function ollamaSection(): Component
    {
        return Section::make(__('Ollama / Embedder Settings'))
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('search.semantic.ollama.url')
                        ->default('http://localhost:11434')
                        ->placeholder('http://localhost:11434')
                        ->label(__('Ollama API URL'))
                        ->required()
                        ->url(),

                    TextInput::make('search.semantic.ollama.model')
                        ->default('bge-m3')
                        ->placeholder('bge-m3')
                        ->label(__('Embedding Model'))
                        ->required(),

                    TextInput::make('search.semantic.ollama.threshold')
                        ->default(0.49)
                        ->placeholder(0.49)
                        ->label(__('Cosine Distance Threshold'))
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.01),
                ]),
            ]);
    }

    protected function dictionarySection(): Component
    {
        return Section::make(__('Semantic Dictionary (Synonyms / Acronyms)'))
            ->schema([
                Repeater::make('dictionary')
                    ->label(__('Synonyms & Acronyms'))
                    ->itemLabel(fn (array $state): ?string => $state['term'] ?? null)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('term')
                                ->label(__('Term / Acronym'))
                                ->placeholder('e.g. ФЛ or CEO')
                                ->required(),
                            TextInput::make('replacement')
                                ->label(__('Full Replacement'))
                                ->placeholder('e.g. Физическое лицо or Chief Executive Officer')
                                ->required(),
                        ]),
                    ])
                    ->default([])
                    ->addActionLabel(__('Add Synonym Mapping')),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save Changes'))
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // 1. Save dynamic config if custom Config facade exists
        $ollamaConfig = Arr::get($state, 'search.semantic.ollama', []);
        if (class_exists('App\Facades\Config')) {
            \App\Facades\Config::setArray(['search.semantic' => ['ollama' => $ollamaConfig]]);
            if (method_exists('App\Facades\Config', 'touchDaemons')) {
                \App\Facades\Config::touchDaemons();
            }
        }

        // 2. Sync Dictionary entries
        $dictionaryModel = config('semantic-search.dictionary.model', SemanticDictionary::class);
        $dictionaryState = Arr::get($state, 'dictionary', []);
        $submittedIds = [];

        if (class_exists($dictionaryModel)) {
            foreach ($dictionaryState as $item) {
                $id = Arr::get($item, 'id');
                $term = trim(Arr::get($item, 'term', ''));
                $replacement = trim(Arr::get($item, 'replacement', ''));

                if ($term === '') {
                    continue;
                }

                $dict = $dictionaryModel::updateOrCreate(
                    ['id' => $id],
                    ['term' => $term, 'replacement' => $replacement]
                );

                $submittedIds[] = $dict->id;
            }

            // Delete removed rows
            $dictionaryModel::query()
                ->whereNotIn('id', $submittedIds)
                ->delete();
        }

        // 3. Purge cache
        $cacheKey = config('semantic-search.dictionary.cache_key', 'semantic_dictionary');
        Cache::forget($cacheKey);

        $this->mount();

        Notification::make()->success()->title(__('Settings saved successfully.'))->send();
    }
}
