<?php

namespace App\Console\Commands;

use App\Actions\CrewBids\ImportPortalBidPackage;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('crew-bids:import-portal {--period= : Bid period as YYYY-MM; defaults to next UTC month} {--fleet= : 747 or 777; defaults to both} {--admin-id= : Accountable bid admin; defaults to configured ID}')]
#[Description('Download complete public bid packages and publish first releases')]
class ImportPortalBidPackageCommand extends Command
{
    public function handle(ImportPortalBidPackage $importer): int
    {
        $ownerOption = $this->option('admin-id');
        if ($ownerOption !== null && (! ctype_digit($ownerOption) || (int) $ownerOption < 1)) {
            $this->error('Use a positive numeric bid admin ID.');

            return self::FAILURE;
        }
        $ownerId = $ownerOption === null ? (int) config('crew_bids.portal.imported_by') : (int) $ownerOption;
        $owner = $ownerId > 0 ? User::query()->find($ownerId) : null;
        if ($owner === null || ! $owner->canUseCrewBids() || ! $owner->isAdmin()) {
            $this->error('Configure an active, verified bid admin as CREW_BIDS_PORTAL_IMPORT_ADMIN_ID.');

            return self::FAILURE;
        }

        $periodOption = $this->option('period');
        $period = is_string($periodOption) && $periodOption !== '' ? $periodOption : now('UTC')->addMonth()->format('Y-m');
        $fleetOption = $this->option('fleet');
        if (! preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $period)
            || ($fleetOption !== null && ! in_array($fleetOption, ['747', '777'], true))) {
            $this->error('Use a YYYY-MM period and a 747 or 777 fleet.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($fleetOption === null ? ['747', '777'] : [$fleetOption] as $fleet) {
            try {
                $status = $importer->handle($period, $fleet, $owner);
                $this->info("B{$fleet} {$period}: {$status}");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("B{$fleet} {$period}: import failed; existing packages were left unchanged.");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
