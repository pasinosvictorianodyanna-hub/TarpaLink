<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The users screen from the prototype.
 *
 * Role is the interesting column here, not the name: it decides which panel a
 * person can open, so the list shows it as a pill rather than buried in the
 * edit form.
 */
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.admin.resources.users.pages.list-users';

    public string $q = '';

    /** '', 'customer', 'shop_owner', 'staff', 'admin'. */
    public string $role = '';

    public function users(): LengthAwarePaginator
    {
        return $this->query()->paginate(25);
    }

    public function total(): int
    {
        return $this->query()->count();
    }

    private function query(): Builder
    {
        return User::query()
            ->withCount('jobs')
            ->with('shops')
            ->when($this->q !== '', function (Builder $query): void {
                $term = '%'.trim($this->q).'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when($this->role !== '', fn (Builder $query) => $query->where('role', $this->role))
            ->orderByDesc('created_at');
    }

    /** @return array<string, string> */
    public function roleOptions(): array
    {
        return [
            '' => 'All roles',
            'customer' => 'Customer',
            'shop_owner' => 'Shop owner',
            'staff' => 'Staff',
            'admin' => 'Admin',
        ];
    }

    public function pillFor(string $role): string
    {
        return match ($role) {
            'admin' => 'live',
            'shop_owner' => 'paid',
            'staff' => 'pending',
            default => 'pending',
        };
    }

    public function roleLabel(string $role): string
    {
        return $this->roleOptions()[$role] ?? ucfirst($role);
    }

    /** "JM" — the prototype's avatar is initials, so there is no image to store. */
    public function initials(User $user): string
    {
        return collect(explode(' ', trim((string) $user->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: '?';
    }

    public function clearFilters(): void
    {
        $this->q = '';
        $this->role = '';
    }

    public function hasFilters(): bool
    {
        return $this->q !== '' || $this->role !== '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportUsers()),

            CreateAction::make()->label('Invite'),
        ];
    }

    public function exportUsers(): StreamedResponse
    {
        $users = User::query()->withCount('jobs')->orderBy('name')->cursor();

        return response()->streamDownload(function () use ($users): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Name', 'Email', 'Role', 'Joined', 'Jobs']);

            foreach ($users as $user) {
                fputcsv($out, [
                    $user->name,
                    $user->email,
                    $user->role,
                    $user->created_at?->format('Y-m-d') ?? '',
                    $user->jobs_count,
                ]);
            }

            fclose($out);
        }, 'tarpalink-users-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
