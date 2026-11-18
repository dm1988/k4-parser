<?php

namespace App\Http\Controllers;

use App\Actions\CrewBids\ImportSubmittedBidList;
use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SensitiveParameter;
use Throwable;

class CrewBidSyncController extends Controller
{
    public function __construct(private ImportSubmittedBidList $import) {}

    public function create(Request $request, BidPackage $package, string $seat): View
    {
        $workspace = $this->import->workspace($this->user($request), $package, $seat);

        return view('crew-bids.sync', compact('package', 'seat', 'workspace'));
    }

    public function store(#[SensitiveParameter] Request $request, BidPackage $package, string $seat): RedirectResponse
    {
        $user = $this->user($request);
        $this->import->workspace($user, $package, $seat);
        $destination = ['package' => $package, 'seat' => $seat];
        $validation = Validator::make($request->only(['employee', 'portal_password', 'revision', 'overwrite']), [
            'employee' => ['required', 'string', 'regex:/\A\d{1,20}\z/'],
            'portal_password' => ['required', 'string', 'max:256'],
            'revision' => ['required', 'integer', 'min:0'],
            'overwrite' => ['sometimes', 'boolean'],
        ]);
        if ($validation->fails()) {
            return to_route('crew-bids.submissions.sync.create', $destination)->with('crew_bid_import_error', 'Enter your employee number and portal password, and reopen Sync from K4 before retrying.');
        }
        try {
            $this->import->handle($user, $package, $seat, (int) $request->input('revision'), $request->boolean('overwrite'),
                $request->string('employee')->toString(), $request->string('portal_password')->toString());
        } catch (BidPortalException $exception) {
            return to_route('crew-bids.submissions.sync.create', $destination)->with('crew_bid_import_error', $exception->getMessage());
        } catch (ValidationException) {
            return to_route('crew-bids.submissions.sync.create', $destination)->with('crew_bid_import_error', 'Your bid list changed or could not be saved. Reopen Sync from K4 before retrying.');
        } catch (Throwable) {
            return to_route('crew-bids.submissions.sync.create', $destination)->with('crew_bid_import_error', 'K4 bids could not be synced. Your saved bid list is unchanged.');
        }

        return to_route('crew-bids.index')->with('crew_bid_import_context', ['package_id' => $package->id, 'seat' => $seat]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
