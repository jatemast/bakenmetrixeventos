<?php

namespace Database\Seeders;

use App\Models\PostalCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostalCodeSeeder extends Seeder
{
    public function run(): void
    {
        $file = base_path('col_cp_dump_v4.csv');
        if (!file_exists($file)) {
            $this->command->error("CSV file not found: $file");
            return;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle); // SKIP HEADER

        $this->command->info("Importing CPs...");
        
        DB::beginTransaction();
        try {
            $count = 0;
            while (($data = fgetcsv($handle)) !== FALSE) {
                // Mapping:
                // 0: CVE_ENT
                // 1: NOM_ENT (Estado)
                // 3: NOM_MUN (Municipio)
                // 7: NOM_COL (Colonia)
                // 9: CP (Postal Code)
                
                if (count($data) < 10) continue;

                PostalCode::create([
                    'entidad_id' => $data[0] ?? null,
                    'estado' => $data[1] ?? '',
                    'municipio_id' => $data[2] ?? null,
                    'municipio' => $data[3] ?? '',
                    'colonia' => $data[7] ?? '', // ID_COL is 6, NOM_COL is 7
                    'cp' => $data[9] ?? ''
                ]);

                $count++;
                if ($count % 500 == 0) {
                    $this->command->info("Imported $count records...");
                }
            }
            DB::commit();
            $this->command->info("Done! Imported $count total records.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Import error: " . $e->getMessage());
        }
        
        fclose($handle);
    }
}
