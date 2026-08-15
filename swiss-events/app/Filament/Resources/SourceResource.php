<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SourceResource\Pages;
use App\Filament\Resources\SourceResource\RelationManagers\ImportRunsRelationManager;
use App\Models\Source;
use App\Services\Import\ImportRunner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Automation';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        Source::TYPE_RSS => 'RSS feed',
                        Source::TYPE_ICAL => 'iCal feed',
                        Source::TYPE_JSON_API => 'JSON API',
                        Source::TYPE_SCRAPER => 'HTML scraper (CSS selectors)',
                        Source::TYPE_JSON_LD => 'Embedded JSON-LD (schema.org Event)',
                        Source::TYPE_MANUAL => 'Manual entries (no automated fetch)',
                    ])
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('trust_level')
                    ->options([
                        Source::TRUST_TRUSTED => 'Trusted (auto-publish)',
                        Source::TRUST_UNTRUSTED => 'Untrusted (queue for review)',
                    ])
                    ->required()
                    ->native(false),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
                Forms\Components\Textarea::make('config')
                    ->label('Config (JSON)')
                    ->helperText('Feed URL, field mappings, or CSS selectors — shape depends on the source type.')
                    ->rows(10)
                    ->columnSpanFull()
                    ->formatStateUsing(fn (?array $state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? json_decode($state, true) : null)
                    ->rule('json'),
                Forms\Components\DateTimePicker::make('last_run_at')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\TextInput::make('last_run_status')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('trust_level')
                    ->badge()
                    ->color(fn (string $state) => $state === Source::TRUST_TRUSTED ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('never'),
                Tables\Columns\TextColumn::make('last_run_status')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'success' => 'success',
                        'partial' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('trust_level')->options([
                    Source::TRUST_TRUSTED => 'Trusted',
                    Source::TRUST_UNTRUSTED => 'Untrusted',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('runNow')
                    ->label('Run now')
                    ->icon('heroicon-o-play')
                    ->visible(fn (Source $record) => $record->type !== Source::TYPE_MANUAL)
                    ->action(function (Source $record) {
                        $run = app(ImportRunner::class)->run($record);

                        Notification::make()
                            ->title("Import {$run->status}")
                            ->body("seen={$run->items_seen} created={$run->items_created} updated={$run->items_updated} duplicates={$run->items_skipped_duplicate} failed={$run->items_failed}")
                            ->color(match ($run->status) {
                                'success' => 'success',
                                'partial' => 'warning',
                                default => 'danger',
                            })
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ImportRunsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSources::route('/'),
            'create' => Pages\CreateSource::route('/create'),
            'edit' => Pages\EditSource::route('/{record}/edit'),
        ];
    }
}
