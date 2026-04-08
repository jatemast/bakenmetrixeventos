<?php

namespace Database\Seeders;

use App\Models\PostalCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JsonPostalCodeSeeder extends Seeder
{
    public function run(): void
    {
        $file = base_path('COL_CP_CRM (1).json');
        if (!file_exists($file)) {
            $this->command->error("JSON file not found: $file");
            return;
        }

        $json = json_decode(file_get_contents($file), true);
        if (!isset($json['COL_CP'])) {
            $this->command->error("Invalid JSON structure: 'COL_CP' key not found.");
            return;
        }

        $dataRecords = $json['COL_CP'];
        $total = count($dataRecords);
        $this->command->info("Importing $total CPs from JSON...");

        PostalCode::truncate();

        $chunks = array_chunk($dataRecords, 1000);
        $this->command->info("Starting bulk import in chunks of 1000...");

        try {
            foreach ($chunks as $index => $chunk) {
                $batch = [];
                foreach ($chunk as $item) {
                    $batch[] = [
                        'entidad_id' => $item['CVE_ENT'] ?? null,
                        'estado' => $item['NOM_ENT'] ?? '',
                        'municipio_id' => $item['CU_MUN'] ?? null,
                        'municipio' => $item['NOM_MUN'] ?? '',
                        'colonia' => $item['NOM_COL'] ?? '',
                        'cp' => (string)($item['C_P'] ?? ''),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('postal_codes')->insert($batch);
                $this->command->info("Imported chunk " . ($index + 1) . "/" . count($chunks));
            }
            $this->command->info("Done! Imported all records.");
        } catch (\Exception $e) {
            $this->command->error("Import error: " . $e->getMessage());
        }
    }
}
