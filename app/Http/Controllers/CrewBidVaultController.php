<?php

namespace App\Http\Controllers;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\CrewBidOperationGuard;
use App\Services\CrewBids\CrewBidVaultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SensitiveParameter;
use Throwable;

class CrewBidVaultController extends Controller
{
    public function __construct(private CrewBidVaultService $vault) {}

    public function index(Request $request): View
    {
        $connection = $this->vault->status($request->user(), $request->session());
        abort_unless(config('crew_bids.vault.enabled') || $connection['connected'], 404);

        return view('crew-bids.connection', compact('connection'));
    }

    public function store(#[SensitiveParameter] Request $request): RedirectResponse
    {
        abort_unless(config('crew_bids.vault.enabled'), 404);
        $data = $request->validate([
            'app_password' => ['required', 'string', 'max:1024', 'current_password'],
            'employee' => ['required', 'string', 'regex:/\A\d{1,20}\z/'],
            'portal_password' => ['required', 'string', 'max:256'],
            'confirmed' => ['accepted'],
        ]);
        try {
            $this->vault->connect($request->user(), $request->session(), $data['app_password'], $data['employee'], $data['portal_password']);

            return to_route('crew-bids.connection.index')->with('crew_bid_sync_notice', 'K4 connection saved and unlocked for this session.');
        } catch (BidPortalException $exception) {
            return to_route('crew-bids.connection.index')->with('crew_bid_sync_error', $exception->getMessage());
        } catch (Throwable) {
            return to_route('crew-bids.connection.index')->with('crew_bid_sync_error', 'The connection could not be saved. Check its status and try again.');
        } finally {
            unset($data);
            app(CrewBidOperationGuard::class)->clear();
        }
    }

    public function unlock(#[SensitiveParameter] Request $request): RedirectResponse
    {
        abort_unless(config('crew_bids.vault.enabled'), 404);
        $data = $request->validate(['app_password' => ['required', 'string', 'max:1024', 'current_password']]);
        try {
            $this->vault->unlock($request->user(), $request->session(), $data['app_password']);

            return to_route('crew-bids.connection.index')->with('crew_bid_sync_notice', 'K4 connection unlocked for this session.');
        } catch (BidPortalException $exception) {
            return to_route('crew-bids.connection.index')->with('crew_bid_sync_error', $exception->getMessage());
        } catch (Throwable) {
            return to_route('crew-bids.connection.index')->with('crew_bid_sync_error', 'The connection could not be unlocked. Try again.');
        } finally {
            unset($data);
        }
    }

    public function lock(Request $request): RedirectResponse
    {
        $this->vault->lock($request->session());

        return to_route('crew-bids.connection.index')->with('crew_bid_sync_notice', 'K4 connection locked for this session.');
    }

    public function destroy(#[SensitiveParameter] Request $request): RedirectResponse
    {
        $request->validate(['app_password' => ['required', 'string', 'max:1024', 'current_password']]);
        $this->vault->forget($request->user(), $request->session());

        return to_route('crew-bids.connection.index')->with('crew_bid_sync_notice', 'K4 connection forgotten. Other sessions and queued logins are revoked.');
    }
}
