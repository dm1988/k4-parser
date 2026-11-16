<?php

namespace App\Http\Controllers;

use App\Actions\CrewBids\LinkCrewBidEmployee;
use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\CrewBidSnapshot;
use App\Models\User;
use App\Services\CrewBids\PredictCrewBidLines;
use App\Services\CrewBids\SyncCrewBidSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use SensitiveParameter;
use Throwable;

class CrewBidEmployeeController extends Controller
{
    public function __construct(private SyncCrewBidSnapshot $sync, private PredictCrewBidLines $predictions) {}

    public function index(Request $request, BidPackage $package): View
    {
        $user = $this->available($request, $package);
        $seat = $request->input('seat', $user->crew_bid_seat_preference);
        abort_unless(in_array($seat, ['CA', 'FO'], true), 404);
        $snapshot = CrewBidSnapshot::forPackage($package, $seat);
        $workspace = BidWorkspace::query()->where('user_id', $user->id)->where('bid_package_id', $package->id)->where('seat', $seat)->first();
        $prediction = $this->predictions->forUser($package, $snapshot, $seat, $user, $workspace->selections ?? []);
        $person = $user->crew_bid_employee_verified_at === null ? null : ($snapshot->bidders[$user->crew_bid_employee_number] ?? null);

        return view('crew-bids.employees', compact('package', 'snapshot', 'seat', 'prediction', 'person'));
    }

    public function read(#[SensitiveParameter] Request $request, BidPackage $package, LinkCrewBidEmployee $link): RedirectResponse|Response
    {
        $user = $this->available($request, $package);
        $seat = in_array($request->input('seat'), ['CA', 'FO'], true) ? $request->input('seat') : 'FO';
        $destination = ['package' => $package, 'seat' => $seat];
        $validation = Validator::make($request->only(['employee', 'portal_password', 'seat', 'format', 'operation']), [
            'employee' => ['required', 'string', 'regex:/\A\d{1,20}\z/'],
            'portal_password' => ['required', 'string', 'max:256'],
            'seat' => ['required', 'string', 'in:CA,FO'],
            'format' => ['nullable', 'string', 'in:PDF,Excel'],
            'operation' => ['nullable', 'string', 'in:link,refresh'],
        ]);
        if ($validation->fails()) {
            return to_route('crew-bids.employees.index', $destination)->with('crew_bid_sync_error', 'Enter your employee number and portal password, and choose CA or FO and a valid report format.');
        }
        try {
            if ($request->input('operation') === 'link') {
                $link->handle($user, $request->string('employee')->toString(), $request->string('portal_password')->toString());

                return to_route('crew-bids.employees.index', $destination)->with('crew_bid_sync_notice', 'Employee number verified and linked. Predictions now use your account automatically.');
            }
            $format = $request->input('format') ?: null;
            $result = $this->sync->handle($package, $request->string('employee')->toString(), $request->string('portal_password')->toString(), $seat, $format);
            if ($result['download'] !== null && $format !== null) {
                $extension = $format === 'PDF' ? 'pdf' : 'xls';

                return response($result['download'], 200, [
                    'Content-Type' => $format === 'PDF' ? 'application/pdf' : 'application/vnd.ms-excel',
                    'Content-Disposition' => 'attachment; filename="shared-bids-'.$package->period.'-'.$seat.'.'.$extension.'"',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        } catch (BidPortalException $exception) {
            return to_route('crew-bids.employees.index', $destination)->with('crew_bid_sync_error', $exception->getMessage());
        } catch (Throwable) {
            return to_route('crew-bids.employees.index', $destination)->with('crew_bid_sync_error', 'Shared bids could not be refreshed. The last successful snapshot is unchanged.');
        }

        return to_route('crew-bids.employees.index', $destination)->with('crew_bid_sync_notice', 'Shared bids updated for all K4 Bids users.');
    }

    private function available(Request $request, BidPackage $package): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless($package->published_at !== null && ($package->archived_at === null
            || $package->workspaces()->where('user_id', $user->id)->exists()), 404);

        return $user;
    }
}
