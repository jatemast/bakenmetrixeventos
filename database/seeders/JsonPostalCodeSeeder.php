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

        // Start fresh
        PostalCode::truncate();

        DB::beginTransaction();
        try {
            $count = 0;
            foreach ($dataRecords as $item) {
                PostalCode::create([
                    'entidad_id' => $item['CVE_ENT'] ?? null,
                    'estado' => $item['NOM_ENT'] ?? '',
                    'municipio_id' => $item['CU_MUN'] ?? null,
                    'municipio' => $item['NOM_MUN'] ?? '',
                    'colonia' => $item['NOM_COL'] ?? '',
                    'cp' => (string)($item['C_P'] ?? '')
                ]);

                $count++;
                if ($count % 500 == 0) {
                    $this->command->info("Imported $count / $total records...");
                }
            }
            DB::commit();
            $this->command->info("Done! Imported $count total records.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Import error: " . $e->getMessage());
        }
    }
}
