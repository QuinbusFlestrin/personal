<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VenueResource\Pages;
use App\Models\Venue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Venues & Places';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state)))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Select::make('venue_type')
                    ->options([
                        Venue::TYPE_MUSEUM => 'Museum',
                        Venue::TYPE_PARK => 'Park',
                        Venue::TYPE_HISTORICAL_BUILDING => 'Historical building',
                        Venue::TYPE_THEATRE => 'Theatre / concert hall',
                        Venue::TYPE_GENERIC => 'Generic venue',
                    ])
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published'])
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('address')
                    ->columnSpanFull(),
                Forms\Components\Select::make('city_id')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('canton_id')
                    ->relationship('canton', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('lat')
                    ->numeric(),
                Forms\Components\TextInput::make('lng')
                    ->numeric(),
                Forms\Components\TextInput::make('website')
                    ->url()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('image')
                    ->image()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('venue_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('city.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('canton.code')
                    ->label('Canton')
                    ->sortable(),
                Tables\Columns\IconColumn::make('status')
                    ->boolean(fn (string $state) => $state === 'published')
                    ->label('Published'),
                Tables\Columns\TextColumn::make('events_count')
                    ->counts('events')
                    ->label('Events'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('venue_type')
                    ->options([
                        Venue::TYPE_MUSEUM => 'Museum',
                        Venue::TYPE_PARK => 'Park',
                        Venue::TYPE_HISTORICAL_BUILDING => 'Historical building',
                        Venue::TYPE_THEATRE => 'Theatre / concert hall',
                        Venue::TYPE_GENERIC => 'Generic venue',
                    ]),
                Tables\Filters\SelectFilter::make('canton')
                    ->relationship('canton', 'name'),
            ])
            ->actions([
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVenues::route('/'),
            'create' => Pages\CreateVenue::route('/create'),
            'edit' => Pages\EditVenue::route('/{record}/edit'),
        ];
    }
}
