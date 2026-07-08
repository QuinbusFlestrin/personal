<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state)))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('venue_id')
                            ->relationship('venue', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('canton_id')
                            ->relationship('canton', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options([
                                Event::STATUS_DRAFT => 'Draft',
                                Event::STATUS_PENDING_REVIEW => 'Pending review',
                                Event::STATUS_PUBLISHED => 'Published',
                                Event::STATUS_REJECTED => 'Rejected',
                                Event::STATUS_ARCHIVED => 'Archived',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->required(),
                        Forms\Components\DateTimePicker::make('ends_at'),
                        Forms\Components\Toggle::make('is_all_day'),
                        Forms\Components\TextInput::make('price_info'),
                        Forms\Components\TextInput::make('external_url')
                            ->url()
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('image')
                            ->image()
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Submission details')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('source_id')
                            ->relationship('source', 'name')
                            ->disabled(),
                        Forms\Components\TextInput::make('submitter_name')
                            ->disabled(),
                        Forms\Components\TextInput::make('submitter_email')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('reviewed_at')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Event::STATUS_PUBLISHED => 'success',
                        Event::STATUS_PENDING_REVIEW => 'warning',
                        Event::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('venue.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('canton.code')
                    ->label('Canton')
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('origin')
                    ->label('Origin')
                    ->state(fn (Event $record) => $record->source?->name ?? ($record->submitted_by_user_id || $record->submitter_email ? 'User submission' : 'Manual'))
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Event::STATUS_DRAFT => 'Draft',
                        Event::STATUS_PENDING_REVIEW => 'Pending review',
                        Event::STATUS_PUBLISHED => 'Published',
                        Event::STATUS_REJECTED => 'Rejected',
                        Event::STATUS_ARCHIVED => 'Archived',
                    ])
                    ->default(Event::STATUS_PENDING_REVIEW),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('canton')
                    ->relationship('canton', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Event $record) => $record->status !== Event::STATUS_PUBLISHED)
                    ->action(fn (Event $record) => $record->update([
                        'status' => Event::STATUS_PUBLISHED,
                        'reviewed_by_user_id' => auth()->id(),
                        'reviewed_at' => now(),
                    ])),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Event $record) => $record->status !== Event::STATUS_REJECTED)
                    ->action(fn (Event $record) => $record->update([
                        'status' => Event::STATUS_REJECTED,
                        'reviewed_by_user_id' => auth()->id(),
                        'reviewed_at' => now(),
                    ])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update([
                            'status' => Event::STATUS_PUBLISHED,
                            'reviewed_by_user_id' => auth()->id(),
                            'reviewed_at' => now(),
                        ])),
                    Tables\Actions\BulkAction::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update([
                            'status' => Event::STATUS_REJECTED,
                            'reviewed_by_user_id' => auth()->id(),
                            'reviewed_at' => now(),
                        ])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Event::pendingReview()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
