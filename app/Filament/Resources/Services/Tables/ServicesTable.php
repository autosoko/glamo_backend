<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('serviceCategory')
                ->with('media')
                ->withCount('providers'))
            ->columns([
                ImageColumn::make('primary_image_url')
                    ->label('Picha')
                    ->getStateUsing(fn ($record): string => (string) $record->primary_image_url)
                    ->defaultImageUrl(asset('images/placeholder.svg'))
                    ->square()
                    ->imageSize(56),

                TextColumn::make('name')
                    ->label('Huduma')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('serviceCategory.name')
                    ->label('Category')
                    ->sortable(),

                TextColumn::make('base_price')
                    ->label('Base (TZS)')
                    ->money('TZS')
                    ->sortable(),

                TextColumn::make('materials_price')
                    ->label('Materials (TZS)')
                    ->money('TZS')
                    ->sortable(),

                TextColumn::make('usage_percent')
                    ->label('Usage %')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('duration_minutes')
                    ->label('Dakika')
                    ->suffix(' min')
                    ->sortable(),

                TextColumn::make('providers_count')
                    ->label('Watoa huduma')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('serviceCategory', 'name'),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}
