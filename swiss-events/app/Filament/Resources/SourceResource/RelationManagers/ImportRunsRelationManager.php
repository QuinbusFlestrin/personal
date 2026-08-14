<?php

namespace App\Filament\Resources\SourceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only audit trail so an admin can see why a source's last run
 * failed/skipped items without digging into application logs.
 */
class ImportRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'importRuns';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('started_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('started_at')->dateTime(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'success' => 'success',
                        'partial' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_seen')->label('Seen'),
                Tables\Columns\TextColumn::make('items_created')->label('Created'),
                Tables\Columns\TextColumn::make('items_updated')->label('Updated'),
                Tables\Columns\TextColumn::make('items_skipped_duplicate')->label('Duplicates'),
                Tables\Columns\TextColumn::make('items_failed')->label('Failed'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
