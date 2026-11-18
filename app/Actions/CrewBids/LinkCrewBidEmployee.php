<?php

namespace App\Actions\CrewBids;

use App\Exceptions\BidPortalException;
use App\Models\PilotSeniorityList;
use App\Models\User;
use App\Services\CrewBids\BidPortalSession;
use App\Services\CrewBids\ReadBidPortalPage;
use Illuminate\Support\Facades\Gate;
use SensitiveParameter;

class LinkCrewBidEmployee
{
    public function __construct(private BidPortalSession $session, private ReadBidPortalPage $pages) {}

    public function handle(User $user, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password): void
    {
        Gate::forUser($user)->authorize('use-crew-bids');
        $list = PilotSeniorityList::current();
        if ($list === null || ! isset($list->pilots[$employee])) {
            throw new BidPortalException('Your employee number is absent from the imported seniority list. Ask an admin to update it.');
        }
        $this->session->viewEmployees($employee, $password, function (string $html) use ($employee): array {
            if (! $this->pages->context($html, $employee)['account_matches_input']) {
                throw new BidPortalException('The authenticated portal account does not match the supplied employee number.');
            }

            return [];
        });
        $current = User::query()->findOrFail($user->id);
        Gate::forUser($current)->authorize('use-crew-bids');
        $current->forceFill(['crew_bid_employee_number' => $employee, 'crew_bid_employee_verified_at' => now()])->save();
    }
}
