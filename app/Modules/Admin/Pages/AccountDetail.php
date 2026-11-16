<?php

declare(strict_types=1);

namespace App\Modules\Admin\Pages;

use App\Modules\Admin\Support\Paginates;
use App\Modules\Identity\Application\OperatorDirectory;
use App\Modules\Workspace\Application\OperatorProjects;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Locked;

/**
 * One account and its projects with quotas and usage (decision 74).
 * Read-only; reached from Accounts, not from the navigation.
 */
final class AccountDetail extends Page implements HasTable
{
    use InteractsWithTable;

    #[Locked]
    public string $account = '';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/accounts/{account}';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(string $account): void
    {
        $this->account = $account;
        $this->summary();
    }

    /** @return array<string, mixed> */
    private function summary(): array
    {
        $this->loaded ??= app(OperatorDirectory::class)->account($this->account);
        abort_if($this->loaded === null, 404);

        return $this->loaded;
    }

    public function getTitle(): string
    {
        return (string) ($this->summary()['email'] ?? 'Account');
    }

    public function content(Schema $schema): Schema
    {
        $summary = $this->summary();

        return $schema->components([
            Section::make('Account')
                ->columns(4)
                ->schema([
                    TextEntry::make('id')->state($summary['id'] ?? null)->copyable(),
                    TextEntry::make('status')->state($summary['status'] ?? null)->badge(),
                    TextEntry::make('created_at')->label('Created')->state($summary['created_at'] ?? null)->dateTime(),
                    TextEntry::make('last_sign_in_at')->label('Last sign-in')->state($summary['last_sign_in_at'] ?? null)->dateTime()->placeholder('never'),
                    IconEntry::make('verified')->state($summary['verified'] ?? false)->boolean(),
                    IconEntry::make('totp')->label('Authenticator')->state($summary['totp'] ?? false)->boolean(),
                    TextEntry::make('keys')->label('Passkeys / keys')->state($summary['keys'] ?? 0),
                    TextEntry::make('providers')->label('OAuth')->state($summary['providers'] ?? null)->placeholder('none'),
                    TextEntry::make('claimed_guest_sessions')->label('Guest sessions claimed')->state($summary['claimed_guest_sessions'] ?? 0),
                    IconEntry::make('operator')->state($summary['operator'] ?? false)->boolean(),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Projects')
            ->records(function (): array {
                $userId = $this->summary()['user_id'] ?? null;

                return is_string($userId) ? collect(app(OperatorProjects::class)->forUser($userId))->keyBy('id')->all() : [];
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->description(fn (array $record): string => $record['id']),
                IconColumn::make('deleted')->boolean()->trueColor('danger')->falseColor('gray'),
                TextColumn::make('captures_today')->label('Captures today')
                    ->state(fn (array $record): string => $record['captures_today'].' / '.$record['captures_per_day']),
                TextColumn::make('stored_body_bytes')->label('Stored bodies')
                    ->state(fn (array $record): string => Paginates::bytes($record['stored_body_bytes']).' / '.Paginates::bytes($record['stored_body_bytes_quota'])),
                TextColumn::make('captures_total')->label('Captures ever')->numeric(),
                TextColumn::make('runs_total')->label('Runs ever')->numeric(),
                TextColumn::make('retention')->label('Retention (captures / runs)')
                    ->state(fn (array $record): string => $record['capture_retention_days'].' d / '.$record['run_retention_days'].' d'),
                TextColumn::make('visibility'),
                TextColumn::make('created_at')->label('Created')->dateTime(),
            ]);
    }
}
