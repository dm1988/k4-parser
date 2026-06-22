<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use Illuminate\Database\Seeder;

class AircraftSeeder extends Seeder
{
    public function run(): void
    {
        $freighterTailNumbers = [
            'N772CK',
            'N773CK',
            'N774CK',
            'N775CK',
            'N776CK',
            'N793CK',
            'N794CK',
        ];
        $ersfTailNumbers = [
            'N769CK',
            'N770CK',
            'N771CK',
            'N778CK',
            'N779CK',
            'N780CK',
        ];

        $this->createFreighterAircraft($freighterTailNumbers);
        $this->createERSFAircraft($ersfTailNumbers);
    }
    private function createFreighterAircraft(array $tailNumbers): void
    {
        $this->createAircraft($tailNumbers, 'Kalitta Air, LLC', '777-F', 'Boeing 777-F');
    }
    private function createERSFAircraft(array $tailNumbers): void
    {
        $this->createAircraft($tailNumbers, 'Kalitta Air, LLC', '777-300ERSF', 'Boeing 777-300ERSF');
    }

    private function createAircraft(array $tailNumbers, string $airline, string $model, string $type): void
    {
        foreach ($tailNumbers as $tailNumber) {
            Aircraft::updateOrCreate(
                [
                    'tail_number' => $tailNumber,
                ],
                [
                    'manufacturer' => 'Boeing',
                    'model' => $model,
                    'type' => $type,
                    'airline' => $airline,
                    'is_active' => true,
                ]
            );
        }
    }
}