<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\Campaign;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CsvSegmentationService
{
    /**
     * Process all segmentation files for a campaign
     * 
     * @param Campaign $campaign
     * @return array Statistics about the import
     */
    public function processAllSegmentationFiles(Campaign $campaign): array
    {
        $stats = [
            'total_processed' => 0,
            'total_created' => 0,
            'total_updated' => 0,
            'total_errors' => 0,
            'citizens' => ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0],
            'leaders' => ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0],
            'militants' => ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0],
            'errors' => []
        ];

        // Process citizen segmentation file (U1)
        if ($campaign->citizen_segmentation_file) {
            $result = $this->processCsvFile(
                $campaign->citizen_segmentation_file,
                'U1',
                false // Not leaders
            );
            $stats['citizens'] = $result;
            $stats['total_processed'] += $result['processed'];
            $stats['total_created'] += $result['created'];
            $stats['total_updated'] += $result['updated'];
            $stats['total_errors'] += $result['errors'];
        }

        // Process leader segmentation file (U3)
        if ($campaign->leader_segmentation_file) {
            $result = $this->processCsvFile(
                $campaign->leader_segmentation_file,
                'U3',
                true // Leaders
            );
            $stats['leaders'] = $result;
            $stats['total_processed'] += $result['processed'];
            $stats['total_created'] += $result['created'];
            $stats['total_updated'] += $result['updated'];
            $stats['total_errors'] += $result['errors'];
        }

        // Process militant segmentation file (U4)
        if ($campaign->militant_segmentation_file) {
            $result = $this->processCsvFile(
                $campaign->militant_segmentation_file,
                'U4',
                false // Not leaders (militants are different from leaders)
            );
            $stats['militants'] = $result;
            $stats['total_processed'] += $result['processed'];
            $stats['total_created'] += $result['created'];
            $stats['total_updated'] += $result['updated'];
            $stats['total_errors'] += $result['errors'];
        }

        return $stats;
    }

    /**
     * Process a single CSV file
     * 
     * @param string $filePath Path in storage
     * @param string $universeType U1, U2, U3, or U4
     * @param bool $isLeader Whether these personas are leaders
     * @return array Statistics
     */
    private function processCsvFile(string $filePath, string $universeType, bool $isLeader): array
    {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {
            $fullPath = Storage::disk('public')->path($filePath);
            
            if (!file_exists($fullPath)) {
                Log::error("CSV file not found: {$fullPath}");
                return $stats;
            }

            $file = fopen($fullPath, 'r');
            
            if ($file === false) {
                Log::error("Could not open CSV file: {$fullPath}");
                return $stats;
            }

            // Read header row
            $headers = fgetcsv($file);
            
            if ($headers === false) {
                Log::error("CSV file is empty: {$fullPath}");
                fclose($file);
                return $stats;
            }

            // Normalize headers (trim and lowercase)
            $headers = array_map(function($header) {
                return strtolower(trim($header));
            }, $headers);

            // Process each row
            $rowNumber = 1;
            while (($row = fgetcsv($file)) !== false) {
                $rowNumber++;
                
                try {
                    // Combine headers with row data
                    $data = array_combine($headers, $row);
                    
                    if ($data === false) {
                        $stats['errors']++;
                        $stats['error_details'][] = "Row {$rowNumber}: Column count mismatch";
                        continue;
                    }

                    // Process the persona
                    $result = $this->processPersonaRow($data, $universeType, $isLeader);
                    
                    $stats['processed']++;
                    
                    if ($result['created']) {
                        $stats['created']++;
                    } elseif ($result['updated']) {
                        $stats['updated']++;
                    }
                    
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $stats['error_details'][] = "Row {$rowNumber}: {$e->getMessage()}";
                    Log::error("Error processing CSV row {$rowNumber}: {$e->getMessage()}");
                }
            }

            fclose($file);

        } catch (\Exception $e) {
            Log::error("Error processing CSV file: {$e->getMessage()}");
            $stats['errors']++;
            $stats['error_details'][] = "File processing error: {$e->getMessage()}";
        }

        return $stats;
    }

    /**
     * Process a single persona row from CSV
     * 
     * @param array $data Row data with headers as keys
     * @param string $universeType
     * @param bool $isLeader
     * @return array ['created' => bool, 'updated' => bool]
     */
    private function processPersonaRow(array $data, string $universeType, bool $isLeader): array
    {
        // Extract and validate cedula (unique identifier)
        $cedula = $this->getFieldValue($data, ['cedula', 'id', 'curp', 'identification']);
        
        if (empty($cedula)) {
            throw new \Exception('Cedula/ID is required');
        }

        // Clean cedula
        $cedula = trim($cedula);

        // Check if persona exists
        $persona = Persona::where('cedula', $cedula)->first();
        
        $personaData = $this->mapCsvDataToPersona($data, $universeType, $isLeader);

        if ($persona) {
            // Update existing persona
            $persona->update($personaData);
            return ['created' => false, 'updated' => true];
        } else {
            // Create new persona
            $personaData['cedula'] = $cedula;
            
            // Generate referral code if leader
            if ($isLeader) {
                $personaData['referral_code'] = $this->generateReferralCode($personaData['nombre'], $cedula);
            }
            
            Persona::create($personaData);
            return ['created' => true, 'updated' => false];
        }
    }

    /**
     * Map CSV data to Persona model fields
     * 
     * @param array $data
     * @param string $universeType
     * @param bool $isLeader
     * @return array
     */
    private function mapCsvDataToPersona(array $data, string $universeType, bool $isLeader): array
    {
        return [
            'nombre' => $this->getFieldValue($data, ['nombre', 'name', 'first_name', 'primer_nombre']),
            'apellido_paterno' => $this->getFieldValue($data, ['apellido_paterno', 'apellido', 'last_name', 'surname']),
            'apellido_materno' => $this->getFieldValue($data, ['apellido_materno', 'second_last_name', 'apellido_2']),
            'edad' => (int) $this->getFieldValue($data, ['edad', 'age'], 0),
            'sexo' => strtoupper($this->getFieldValue($data, ['sexo', 'sex', 'gender', 'genero'], 'H')),
            'calle' => $this->getFieldValue($data, ['calle', 'street', 'direccion']),
            'numero_exterior' => $this->getFieldValue($data, ['numero_exterior', 'num_ext', 'exterior', 'numero']),
            'numero_interior' => $this->getFieldValue($data, ['numero_interior', 'num_int', 'interior', 'apt']),
            'colonia' => $this->getFieldValue($data, ['colonia', 'neighborhood', 'barrio']),
            'codigo_postal' => $this->getFieldValue($data, ['codigo_postal', 'cp', 'zip', 'postal_code']),
            'municipio' => $this->getFieldValue($data, ['municipio', 'municipality', 'city', 'ciudad']),
            'estado' => $this->getFieldValue($data, ['estado', 'state', 'province']),
            'region' => $this->getFieldValue($data, ['region', 'area', 'zone', 'zona']),
            'numero_celular' => $this->getFieldValue($data, ['numero_celular', 'celular', 'mobile', 'telefono', 'phone', 'whatsapp']),
            'numero_telefono' => $this->getFieldValue($data, ['numero_telefono', 'phone', 'landline', 'tel']),
            'universe_type' => $universeType,
            'is_leader' => $isLeader,
            'loyalty_balance' => 0, // Start with 0 points
        ];
    }

    /**
     * Get field value from data array using multiple possible keys
     * 
     * @param array $data
     * @param array $possibleKeys
     * @param mixed $default
     * @return mixed
     */
    private function getFieldValue(array $data, array $possibleKeys, $default = ''): mixed
    {
        foreach ($possibleKeys as $key) {
            $key = strtolower($key);
            if (isset($data[$key]) && !empty(trim($data[$key]))) {
                return trim($data[$key]);
            }
        }
        return $default;
    }

    /**
     * Generate a unique referral code for leaders
     * 
     * @param string $nombre
     * @param string $cedula
     * @return string
     */
    private function generateReferralCode(string $nombre, string $cedula): string
    {
        // Create a code from first 3 letters of name + last 4 of cedula + random
        $namePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nombre), 0, 3));
        $cedulaSuffix = substr($cedula, -4);
        $random = strtoupper(Str::random(3));
        
        $code = "{$namePrefix}{$cedulaSuffix}{$random}";
        
        // Ensure uniqueness
        $originalCode = $code;
        $counter = 1;
        while (Persona::where('referral_code', $code)->exists()) {
            $code = $originalCode . $counter;
            $counter++;
        }
        
        return $code;
    }

    /**
     * Validate CSV file structure
     * 
     * @param string $filePath
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateCsvFile(string $filePath): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'row_count' => 0,
            'headers' => []
        ];

        try {
            $fullPath = Storage::disk('public')->path($filePath);
            
            if (!file_exists($fullPath)) {
                $result['valid'] = false;
                $result['errors'][] = 'File not found';
                return $result;
            }

            $file = fopen($fullPath, 'r');
            
            if ($file === false) {
                $result['valid'] = false;
                $result['errors'][] = 'Could not open file';
                return $result;
            }

            // Read header
            $headers = fgetcsv($file);
            
            if ($headers === false) {
                $result['valid'] = false;
                $result['errors'][] = 'File is empty';
                fclose($file);
                return $result;
            }

            $result['headers'] = array_map('trim', $headers);

            // Check for required cedula column
            $hasIdColumn = false;
            foreach ($headers as $header) {
                $normalized = strtolower(trim($header));
                if (in_array($normalized, ['cedula', 'id', 'curp', 'identification'])) {
                    $hasIdColumn = true;
                    break;
                }
            }

            if (!$hasIdColumn) {
                $result['valid'] = false;
                $result['errors'][] = 'Missing required ID/Cedula column';
            }

            // Count rows
            while (fgetcsv($file) !== false) {
                $result['row_count']++;
            }

            if ($result['row_count'] === 0) {
                $result['warnings'][] = 'File has no data rows';
            }

            fclose($file);

        } catch (\Exception $e) {
            $result['valid'] = false;
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get sample data from CSV file (first 5 rows)
     * 
     * @param string $filePath
     * @param int $limit
     * @return array
     */
    public function getCsvPreview(string $filePath, int $limit = 5): array
    {
        $preview = [
            'headers' => [],
            'rows' => [],
            'total_rows' => 0
        ];

        try {
            $fullPath = Storage::disk('public')->path($filePath);
            
            if (!file_exists($fullPath)) {
                return $preview;
            }

            $file = fopen($fullPath, 'r');
            
            if ($file === false) {
                return $preview;
            }

            // Read header
            $headers = fgetcsv($file);
            if ($headers === false) {
                fclose($file);
                return $preview;
            }

            $preview['headers'] = array_map('trim', $headers);

            // Read sample rows
            $rowCount = 0;
            while (($row = fgetcsv($file)) !== false && $rowCount < $limit) {
                $preview['rows'][] = array_combine($preview['headers'], $row);
                $rowCount++;
            }

            // Count remaining rows
            while (fgetcsv($file) !== false) {
                $rowCount++;
            }

            $preview['total_rows'] = $rowCount;

            fclose($file);

        } catch (\Exception $e) {
            Log::error("Error getting CSV preview: {$e->getMessage()}");
        }

        return $preview;
    }
}
