<?php

declare(strict_types=1);

namespace App\Modules\Admin\Pages;

use App\Modules\Admin\Support\Paginates;
use App\Modules\Agent\Application\OperatorFleet;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

/** Paired agents: version, platform, state, last seen, workspace (decision 74). Read-only. */
final class Agents extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): Heroicon
    {
        return Heroicon::OutlinedCpuChip;
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public function getTitle(): string
    {
        return 'Agents';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (array $filters, int $page, int $recordsPerPage): LengthAwarePaginator {
                $revoked = (bool) ($filters['revoked']['isActive'] ?? false);

                return Paginates::page(app(OperatorFleet::class)->agents($page, $recordsPerPage, $revoked), $page, $recordsPerPage);
            })
            ->paginated([25, 50, 100])
            ->filters([Filter::make('revoked')->label('Include revoked agents')])
            ->columns([
                TextColumn::make('name')->description(fn (array $record): string => $record['id']),
                TextColumn::make('state')->badge()->color(fn (string $state): string => match ($state) {
                    'connected' => 'success', 'revoked' => 'danger', default => 'gray',
                }),
                TextColumn::make('version'),
                TextColumn::make('platform')->placeholder('unknown'),
                TextColumn::make('proto')->label('Protocol')->numeric(),
                TextColumn::make('workspace'),
                IconColumn::make('guest')->boolean(),
                TextColumn::make('last_seen_at')->label('Last seen')->since()->placeholder('never'),
                TextColumn::make('paired_at')->label('Paired')->dateTime(),
            ]);
    }
}
