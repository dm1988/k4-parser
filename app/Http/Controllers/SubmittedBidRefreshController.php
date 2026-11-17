<?php

namespace App\Http\Controllers;

use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Models\User;
use App\Services\CrewBids\QueuedSubmittedBidRefresh;
use App\Services\CrewBids\StoreSubmittedBidSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use SensitiveParameter;
use Throwable;

class SubmittedBidRefreshController extends Controller
{
    public function __construct(private QueuedSubmittedBidRefresh $refreshes, private StoreSubmittedBidSnapshot $snapshots) {}

    public function store(#[SensitiveParameter] Request $request, BidPackage $package, string $seat): JsonResponse
    {
        $user = $this->user($request, $package, $seat);
        $validation = Validator::make($request->only(['employee', 'portal_password']), [
            'employee' => ['required', 'string', 'regex:/\A\d{1,20}\z/'],
            'portal_password' => ['required', 'string', 'max:256'],
        ]);
        if ($validation->fails()) {
            return response()->json(['message' => 'Enter your employee number and K4 password, or unlock your saved K4 login.'], 422);
        }
        try {
            $id = $this->refreshes->start($user, $package, $seat, $request->string('employee')->toString(), $request->string('portal_password')->toString());
        } catch (BidPortalException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable) {
            return response()->json(['message' => 'The K4 check could not be queued. My bid list is unchanged.'], 503);
        }

        return response()->json(['url' => route('crew-bids.submissions.check.status', ['package' => $package, 'seat' => $seat, 'refresh' => $id]),
            'state' => 'queued', 'message' => 'K4 check queued. My bid list is unchanged.'], 202);
    }

    public function status(Request $request, BidPackage $package, string $seat, string $refresh): JsonResponse
    {
        $state = $this->refreshes->owned($refresh, $this->user($request, $package, $seat), $package, $seat);
        abort_if($state === null, 404);

        return response()->json(['state' => $state['state'], 'message' => $state['message'], 'finished_at' => $state['finished_at']]);
    }

    private function user(Request $request, BidPackage $package, string $seat): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $this->snapshots->authorize($user, $package, $seat);

        return $user;
    }
}
