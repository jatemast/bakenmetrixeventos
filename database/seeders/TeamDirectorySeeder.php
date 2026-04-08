<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Models\Group;
use App\Models\Tenant;
use App\Models\Mascota;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class TeamDirectorySeeder extends Seeder
{
    public function run(): void
    {
        try {
            DB::transaction(function () {
                // 1. PURGA ABSOLUTA (Force Delete para evitar conflictos de Postgres)
                DB::table('group_members')->delete();
                Mascota::query()->forceDelete();
                Persona::query()->forceDelete();
                Group::query()->forceDelete();
                
                $tenant = Tenant::updateOrCreate(
                    ['slug' => 'metrix-demo'],
                    ['name' => 'Metrix Events Demo', 'domain' => 'metrix.test', 'is_active' => true]
                );

                $directory = [
                    ['name' => 'Enrique González', 'phone' => '524423935595', 'universe' => 'U3', 'team' => 'Equipo Azul', 'age' => 30, 'pet' => false],
                    ['name' => 'Regina Páramo', 'phone' => '524427500286', 'universe' => 'U1', 'team' => 'Equipo Azul', 'age' => 45, 'pet' => true],
                    ['name' => 'Miguel Palacios', 'phone' => '524421572835', 'universe' => 'U2', 'team' => 'Equipo Azul', 'age' => 32, 'pet' => false],

                    ['name' => 'Cecilia Robles', 'phone' => '524427731302', 'universe' => 'U1', 'team' => 'Equipo Verde', 'age' => 25, 'pet' => false],
                    ['name' => 'Juana Morales', 'phone' => '524421708231', 'universe' => 'U1', 'team' => 'Equipo Verde', 'age' => 30, 'pet' => false],
                    ['name' => 'Diego Piña', 'phone' => '524423690323', 'universe' => 'U1', 'team' => 'Equipo Verde', 'age' => 35, 'pet' => false],

                    ['name' => 'Raquel Pacheco', 'phone' => '524422265346', 'universe' => 'U3', 'team' => 'Equipo Rojo', 'age' => 38, 'pet' => false],
                    ['name' => 'Luis Gerardo Chavez', 'phone' => '524426128379', 'universe' => 'U2', 'team' => 'Equipo Rojo', 'age' => 45, 'pet' => false],
                    ['name' => 'Zenaida Girón', 'phone' => '52442319836', 'universe' => 'U2', 'team' => 'Equipo Rojo', 'age' => 50, 'pet' => false],

                    ['name' => 'Alejandro Gallegos', 'phone' => '524461474773', 'universe' => 'U1', 'team' => 'Equipo Amarillo', 'age' => 28, 'pet' => false],
                    ['name' => 'Mario Morales', 'phone' => '524426091698', 'universe' => 'U1', 'team' => 'Equipo Amarillo', 'age' => 50, 'pet' => true],
                    ['name' => 'Héctor Ramírez', 'phone' => '524423325259', 'universe' => 'U1', 'team' => 'Equipo Amarillo', 'age' => 48, 'pet' => true],

                    ['name' => 'Don Salvador 1', 'phone' => '525529528413', 'universe' => 'U4', 'team' => 'Equipo Negro', 'age' => 55, 'pet' => false],
                    ['name' => 'Don Salvador 2', 'phone' => '524428082030', 'universe' => 'U4', 'team' => 'Equipo Negro', 'age' => 50, 'pet' => false],

                    ['name' => 'Javier Teheran', 'phone' => '573022122724', 'universe' => 'U4', 'team' => 'Equipo Naranja', 'age' => 30, 'pet' => false],
                    ['name' => 'Osvaldo', 'phone' => '573042697017', 'universe' => 'U4', 'team' => 'Equipo Naranja', 'age' => 32, 'pet' => false],
                    ['name' => 'Gael', 'phone' => '529995885158', 'universe' => 'U4', 'team' => 'Equipo Naranja', 'age' => 22, 'pet' => false],

                    ['name' => 'Jhon Martelo', 'phone' => '573003435530', 'universe' => 'U4', 'team' => 'Equipo Morado', 'age' => 35, 'pet' => false],
                ];

                foreach ($directory as $m) {
                    $parts = explode(' ', $m['name']);
                    $firstName = $parts[0];
                    $lastName = $parts[1] ?? 'Apellido';

                    $group = Group::withTrashed()->updateOrCreate(
                        ['name' => $m['team']],
                        ['code' => 'GRP-' . strtoupper(substr(md5($m['team']), 0, 6)), 'type' => 'organization', 'is_active' => true, 'deleted_at' => null]
                    );

                    $persona = Persona::create([
                        'tenant_id' => $tenant->id,
                        'cedula' => 'REAL-' . $m['phone'],
                        'nombre' => $firstName,
                        'apellido_paterno' => $lastName,
                        'apellido_materno' => 'Metrix',
                        'edad' => $m['age'],
                        'sexo' => 'H',
                        'calle' => 'Direccion Prueba',
                        'numero_exterior' => '1',
                        'colonia' => ($m['universe'] === 'U1' && $m['age'] < 40) ? 'Amanecer' : 'Centro',
                        'codigo_postal' => '76000',
                        'municipio' => 'Corregidora',
                        'estado' => 'Querétaro',
                        'numero_celular' => $m['phone'],
                        'universe_type' => $m['universe'],
                        'group_id' => $group->id,
                        'is_leader' => ($m['universe'] === 'U3')
                    ]);

                    DB::table('group_members')->insert([
                        'group_id' => $group->id,
                        'persona_id' => $persona->id,
                        'role' => ($m['universe'] === 'U3') ? 'coordinator' : 'member',
                        'joined_at' => now(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    if ($m['pet']) {
                        Mascota::create(['persona_id' => $persona->id, 'nombre' => 'TesterDog', 'tipo' => 'Perro', 'raza' => 'PRUEBA', 'edad' => 2]);
                    }
                }
            });
            echo "LIMPIEZA TOTAL FINALIZADA: Solo tienes a tus 18 elegidos en la DB.\n";
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}
