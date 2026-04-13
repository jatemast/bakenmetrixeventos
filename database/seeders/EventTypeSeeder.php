<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    /**
     * Seed contextual event types with rich default_form_schema templates.
     * Each type acts as a "blueprint" that pre-fills the event's registration form.
     */
    public function run(): void
    {
        $tenantId = \App\Models\Tenant::first()?->id;

        $types = [
            // ── Salud ────────────────────────────────────────────────────────
            [
                'name' => 'Vacunación de Mascotas',
                'slug' => 'vacunacion-mascotas',
                'icon' => 'pi pi-heart',
                'requires_appointment' => true,
                'has_beneficiaries' => true,
                'beneficiary_label' => 'Mascota',
                'success_message' => '¡Vacunación registrada exitosamente! Tu mascota está protegida. 🐾',
                'default_slot_config' => [
                    'interval_minutes' => 15,
                    'capacity_per_slot' => 6,
                ],
                'default_points_config' => [
                    'attendee' => 5,
                    'leader' => 3,
                    'referral' => 2,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Dueño',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'colonia', 'label' => 'Colonia', 'type' => 'text', 'required' => true],
                                ['name' => 'codigo_postal', 'label' => 'Código Postal', 'type' => 'text', 'required' => false],
                            ],
                        ],
                        [
                            'title' => 'Datos de la Mascota',
                            'icon' => 'pi pi-heart',
                            'fields' => [
                                ['name' => 'nombre_mascota', 'label' => 'Nombre de la Mascota', 'type' => 'text', 'required' => true],
                                ['name' => 'tipo_mascota', 'label' => 'Tipo de Mascota', 'type' => 'select', 'required' => true, 'options' => ['Perro', 'Gato', 'Ave', 'Conejo', 'Otro']],
                                ['name' => 'raza', 'label' => 'Raza', 'type' => 'text', 'required' => false],
                                ['name' => 'sexo_mascota', 'label' => 'Sexo', 'type' => 'select', 'required' => true, 'options' => ['Macho', 'Hembra']],
                                ['name' => 'edad_mascota', 'label' => 'Edad (años)', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 30],
                                ['name' => 'peso_mascota', 'label' => 'Peso Aproximado (kg)', 'type' => 'number', 'required' => false],
                                ['name' => 'esterilizado', 'label' => '¿Está esterilizado(a)?', 'type' => 'select', 'required' => true, 'options' => ['Sí', 'No']],
                            ],
                        ],
                        [
                            'title' => 'Vacunación',
                            'icon' => 'pi pi-shield',
                            'fields' => [
                                ['name' => 'vacuna_aplicada', 'label' => 'Vacuna a Aplicar', 'type' => 'select', 'required' => true, 'options' => ['Antirrábica', 'Polivalente Canina', 'Triple Felina', 'Desparasitación', 'Otra']],
                                ['name' => 'mesa_vacunacion', 'label' => 'Mesa de Vacunación', 'type' => 'select', 'required' => true, 'options' => ['Mesa 1', 'Mesa 2', 'Mesa 3', 'Mesa 4', 'Mesa 5']],
                                ['name' => 'dosis_numero', 'label' => 'Número de Dosis', 'type' => 'select', 'required' => true, 'options' => ['1ra Dosis', '2da Dosis', '3ra Dosis', 'Refuerzo']],
                                ['name' => 'lote_vacuna', 'label' => 'Lote de Vacuna', 'type' => 'text', 'required' => false, 'placeholder' => 'Ej: LOT-2026-ABC'],
                                ['name' => 'observaciones_vet', 'label' => 'Observaciones del Veterinario', 'type' => 'textarea', 'required' => false],
                            ],
                        ],
                    ],
                ],
            ],

            [
                'name' => 'Vacunación Humana',
                'slug' => 'vacunacion-humana',
                'icon' => 'pi pi-plus-circle',
                'requires_appointment' => true,
                'has_beneficiaries' => false,
                'beneficiary_label' => null,
                'success_message' => '¡Vacunación registrada! Cuida tu salud. 💉',
                'default_slot_config' => [
                    'interval_minutes' => 10,
                    'capacity_per_slot' => 8,
                ],
                'default_points_config' => [
                    'attendee' => 5,
                    'leader' => 3,
                    'referral' => 2,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Ciudadano',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true, 'maxLength' => 18],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'edad', 'label' => 'Edad', 'type' => 'number', 'required' => true],
                                ['name' => 'sexo', 'label' => 'Sexo', 'type' => 'select', 'required' => true, 'options' => ['Masculino', 'Femenino']],
                                ['name' => 'colonia', 'label' => 'Colonia', 'type' => 'text', 'required' => true],
                            ],
                        ],
                        [
                            'title' => 'Registro de Vacunación',
                            'icon' => 'pi pi-shield',
                            'fields' => [
                                ['name' => 'tipo_vacuna', 'label' => 'Tipo de Vacuna', 'type' => 'select', 'required' => true, 'options' => ['Influenza', 'COVID-19', 'Hepatitis B', 'Tétanos', 'Sarampión', 'Otra']],
                                ['name' => 'dosis', 'label' => 'Dosis', 'type' => 'select', 'required' => true, 'options' => ['1ra Dosis', '2da Dosis', 'Refuerzo', 'Dosis Única']],
                                ['name' => 'modulo_vacunacion', 'label' => 'Módulo de Vacunación', 'type' => 'select', 'required' => true, 'options' => ['Módulo A', 'Módulo B', 'Módulo C', 'Módulo D']],
                                ['name' => 'lote', 'label' => 'Lote de Vacuna', 'type' => 'text', 'required' => false],
                                ['name' => 'reacciones_previas', 'label' => '¿Ha tenido reacciones a vacunas anteriores?', 'type' => 'select', 'required' => true, 'options' => ['No', 'Sí, leves', 'Sí, severas']],
                                ['name' => 'alergias', 'label' => 'Alergias conocidas', 'type' => 'textarea', 'required' => false],
                                ['name' => 'observaciones_medicas', 'label' => 'Observaciones Médicas', 'type' => 'textarea', 'required' => false],
                            ],
                        ],
                    ],
                ],
            ],

            [
                'name' => 'Operativo de Salud',
                'slug' => 'operativo-salud',
                'icon' => 'pi pi-heart-fill',
                'requires_appointment' => true,
                'has_beneficiaries' => false,
                'beneficiary_label' => null,
                'success_message' => '¡Tu cita médica ha sido registrada! 🏥 Te esperamos.',
                'default_slot_config' => [
                    'interval_minutes' => 20,
                    'capacity_per_slot' => 4,
                ],
                'default_points_config' => [
                    'attendee' => 8,
                    'leader' => 5,
                    'referral' => 3,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Paciente',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => false],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'edad', 'label' => 'Edad', 'type' => 'number', 'required' => true],
                                ['name' => 'sexo', 'label' => 'Sexo', 'type' => 'select', 'required' => true, 'options' => ['Masculino', 'Femenino']],
                                ['name' => 'tipo_sangre', 'label' => 'Tipo de Sangre', 'type' => 'select', 'required' => false, 'options' => ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'No sé']],
                            ],
                        ],
                        [
                            'title' => 'Servicio Médico',
                            'icon' => 'pi pi-briefcase',
                            'fields' => [
                                ['name' => 'servicio_solicitado', 'label' => 'Servicio Solicitado', 'type' => 'select', 'required' => true, 'options' => ['Consulta General', 'Dental', 'Optometría', 'Nutrición', 'Psicología', 'Laboratorio', 'Papanicolau', 'Otro']],
                                ['name' => 'consultorio', 'label' => 'Consultorio / Módulo', 'type' => 'select', 'required' => true, 'options' => ['Consultorio 1', 'Consultorio 2', 'Consultorio 3', 'Área de Laboratorio', 'Área Dental']],
                                ['name' => 'temperatura', 'label' => 'Temperatura (°C)', 'type' => 'number', 'required' => false, 'step' => 0.1],
                                ['name' => 'presion_arterial', 'label' => 'Presión Arterial', 'type' => 'text', 'required' => false, 'placeholder' => 'Ej: 120/80'],
                                ['name' => 'peso', 'label' => 'Peso (kg)', 'type' => 'number', 'required' => false],
                                ['name' => 'talla', 'label' => 'Talla (cm)', 'type' => 'number', 'required' => false],
                                ['name' => 'padecimiento_actual', 'label' => 'Padecimiento / Motivo de Consulta', 'type' => 'textarea', 'required' => true],
                                ['name' => 'medicamentos_actuales', 'label' => 'Medicamentos Actuales', 'type' => 'textarea', 'required' => false],
                            ],
                        ],
                    ],
                ],
            ],

            // ── Comunidad ────────────────────────────────────────────────────
            [
                'name' => 'Jornada de Limpieza',
                'slug' => 'jornada-limpieza',
                'icon' => 'pi pi-sun',
                'requires_appointment' => false,
                'has_beneficiaries' => false,
                'beneficiary_label' => null,
                'success_message' => '¡Gracias por sumarte a la jornada de limpieza! 🌿 Juntos hacemos la diferencia.',
                'default_slot_config' => null,
                'default_points_config' => [
                    'attendee' => 10,
                    'leader' => 8,
                    'referral' => 5,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Voluntario',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'colonia', 'label' => 'Colonia', 'type' => 'text', 'required' => true],
                            ],
                        ],
                        [
                            'title' => 'Asignación de Trabajo',
                            'icon' => 'pi pi-map',
                            'fields' => [
                                ['name' => 'zona_asignada', 'label' => 'Zona Asignada', 'type' => 'select', 'required' => true, 'options' => ['Zona Norte', 'Zona Sur', 'Zona Centro', 'Zona Este', 'Zona Oeste']],
                                ['name' => 'equipo', 'label' => 'Equipo', 'type' => 'select', 'required' => true, 'options' => ['Equipo Recolección', 'Equipo Barrido', 'Equipo Poda', 'Equipo Pintura', 'Equipo Logística']],
                                ['name' => 'tarea_especifica', 'label' => 'Tarea Específica', 'type' => 'select', 'required' => false, 'options' => ['Recoger basura', 'Barrer calles', 'Podar árboles', 'Pintar bardas', 'Destapar coladeras', 'Otra']],
                                ['name' => 'herramienta_propia', 'label' => '¿Trae herramienta propia?', 'type' => 'select', 'required' => false, 'options' => ['Sí', 'No']],
                            ],
                        ],
                    ],
                ],
            ],

            // ── Deportes ─────────────────────────────────────────────────────
            [
                'name' => 'Evento Deportivo',
                'slug' => 'evento-deportivo',
                'icon' => 'pi pi-star',
                'requires_appointment' => false,
                'has_beneficiaries' => false,
                'beneficiary_label' => null,
                'success_message' => '¡Inscripción deportiva registrada! 🏆 ¡Mucho éxito!',
                'default_slot_config' => null,
                'default_points_config' => [
                    'attendee' => 5,
                    'leader' => 3,
                    'referral' => 2,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Deportista',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'edad', 'label' => 'Edad', 'type' => 'number', 'required' => true],
                                ['name' => 'sexo', 'label' => 'Sexo', 'type' => 'select', 'required' => true, 'options' => ['Masculino', 'Femenino']],
                            ],
                        ],
                        [
                            'title' => 'Disciplina y Categoría',
                            'icon' => 'pi pi-star',
                            'fields' => [
                                ['name' => 'disciplina', 'label' => 'Disciplina', 'type' => 'select', 'required' => true, 'options' => ['Fútbol', 'Básquetbol', 'Voleibol', 'Atletismo', 'Béisbol', 'Box', 'Lucha', 'Natación', 'Ciclismo', 'Otro']],
                                ['name' => 'categoria', 'label' => 'Categoría', 'type' => 'select', 'required' => true, 'options' => ['Infantil (6-12)', 'Juvenil (13-17)', 'Libre (18-35)', 'Veteranos (36+)']],
                                ['name' => 'equipo_nombre', 'label' => 'Nombre del Equipo', 'type' => 'text', 'required' => false],
                                ['name' => 'talla_playera', 'label' => 'Talla de Playera', 'type' => 'select', 'required' => false, 'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
                                ['name' => 'condicion_medica', 'label' => '¿Alguna condición médica?', 'type' => 'textarea', 'required' => false],
                            ],
                        ],
                    ],
                ],
            ],

            // ── Empleo ───────────────────────────────────────────────────────
            [
                'name' => 'Feria de Empleo',
                'slug' => 'feria-empleo',
                'icon' => 'pi pi-briefcase',
                'requires_appointment' => false,
                'has_beneficiaries' => false,
                'beneficiary_label' => null,
                'success_message' => '¡Te has registrado en la Feria de Empleo! 💼 Prepara tu CV.',
                'default_slot_config' => null,
                'default_points_config' => [
                    'attendee' => 5,
                    'leader' => 3,
                    'referral' => 2,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Candidato',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'email', 'label' => 'Correo Electrónico', 'type' => 'email', 'required' => true],
                                ['name' => 'edad', 'label' => 'Edad', 'type' => 'number', 'required' => true],
                                ['name' => 'colonia', 'label' => 'Colonia', 'type' => 'text', 'required' => true],
                            ],
                        ],
                        [
                            'title' => 'Perfil Laboral',
                            'icon' => 'pi pi-briefcase',
                            'fields' => [
                                ['name' => 'area_interes', 'label' => 'Área de Interés', 'type' => 'select', 'required' => true, 'options' => ['Administración', 'Ventas', 'Producción', 'Tecnología', 'Salud', 'Educación', 'Construcción', 'Comercio', 'Servicios', 'Otro']],
                                ['name' => 'escolaridad', 'label' => 'Escolaridad', 'type' => 'select', 'required' => true, 'options' => ['Primaria', 'Secundaria', 'Preparatoria', 'Universidad', 'Posgrado', 'Técnico']],
                                ['name' => 'experiencia_anos', 'label' => 'Años de Experiencia', 'type' => 'select', 'required' => true, 'options' => ['Sin experiencia', '1-2 años', '3-5 años', '5-10 años', 'Más de 10 años']],
                                ['name' => 'disponibilidad', 'label' => 'Disponibilidad', 'type' => 'select', 'required' => true, 'options' => ['Inmediata', '15 días', '1 mes', 'A convenir']],
                                ['name' => 'empresa_visitada', 'label' => 'Empresa que Desea Visitar', 'type' => 'text', 'required' => false],
                                ['name' => 'tiene_cv', 'label' => '¿Trae CV impreso?', 'type' => 'select', 'required' => false, 'options' => ['Sí', 'No']],
                            ],
                        ],
                    ],
                ],
            ],

            // ── Electoral ────────────────────────────────────────────────────
            [
                'name' => 'Registro Electoral',
                'slug' => 'registro-electoral',
                'icon' => 'pi pi-id-card',
                'requires_appointment' => false,
                'has_beneficiaries' => false,
                'beneficiary_label' => null,
                'success_message' => '¡Registro electoral capturado exitosamente! 🗳️',
                'default_slot_config' => null,
                'default_points_config' => [
                    'attendee' => 10,
                    'leader' => 8,
                    'referral' => 5,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Ciudadano',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true],
                                ['name' => 'clave_elector', 'label' => 'Clave de Elector', 'type' => 'text', 'required' => true],
                                ['name' => 'colonia', 'label' => 'Colonia', 'type' => 'text', 'required' => true],
                            ],
                        ],
                        [
                            'title' => 'Datos Electorales',
                            'icon' => 'pi pi-id-card',
                            'fields' => [
                                ['name' => 'seccion_electoral', 'label' => 'Sección Electoral', 'type' => 'text', 'required' => true],
                                ['name' => 'distrito', 'label' => 'Distrito', 'type' => 'text', 'required' => true],
                                ['name' => 'municipio', 'label' => 'Municipio', 'type' => 'text', 'required' => true],
                                ['name' => 'vigencia_ine', 'label' => 'Vigencia INE', 'type' => 'text', 'required' => false],
                                ['name' => 'promotor', 'label' => 'Promotor que lo Registra', 'type' => 'text', 'required' => false],
                            ],
                        ],
                    ],
                ],
            ],

            // ── Entrega de Apoyos (ya existe, enriquecer) ────────────────────
            [
                'name' => 'Entrega de Apoyos',
                'slug' => 'entrega-apoyos',
                'icon' => 'pi pi-gift',
                'requires_appointment' => true,
                'has_beneficiaries' => true,
                'beneficiary_label' => 'Beneficiario',
                'success_message' => '¡Tu apoyo ha sido registrado! 🎁 Gracias por asistir.',
                'default_slot_config' => [
                    'interval_minutes' => 15,
                    'capacity_per_slot' => 10,
                ],
                'default_points_config' => [
                    'attendee' => 5,
                    'leader' => 5,
                    'referral' => 3,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Beneficiario',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'colonia', 'label' => 'Colonia', 'type' => 'text', 'required' => true],
                                ['name' => 'calle', 'label' => 'Calle y Número', 'type' => 'text', 'required' => true],
                            ],
                        ],
                        [
                            'title' => 'Entrega',
                            'icon' => 'pi pi-gift',
                            'fields' => [
                                ['name' => 'tipo_apoyo', 'label' => 'Tipo de Apoyo', 'type' => 'select', 'required' => true, 'options' => ['Despensa', 'Material Escolar', 'Cobija', 'Medicamento', 'Material de Construcción', 'Apoyo Económico', 'Otro']],
                                ['name' => 'mesa_entrega', 'label' => 'Mesa de Entrega', 'type' => 'select', 'required' => true, 'options' => ['Mesa 1', 'Mesa 2', 'Mesa 3', 'Mesa 4', 'Mesa 5']],
                                ['name' => 'cantidad', 'label' => 'Cantidad Entregada', 'type' => 'number', 'required' => true, 'min' => 1],
                                ['name' => 'folio_entrega', 'label' => 'Folio de Entrega', 'type' => 'text', 'required' => false],
                                ['name' => 'firma_recibido', 'label' => '¿Firmó de recibido?', 'type' => 'select', 'required' => true, 'options' => ['Sí', 'No']],
                                ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea', 'required' => false],
                            ],
                        ],
                    ],
                ],
            ],

            // ── Censo de Salud (ya existe, enriquecer) ───────────────────────
            [
                'name' => 'Censo de Salud',
                'slug' => 'censo-salud',
                'icon' => 'pi pi-chart-bar',
                'requires_appointment' => true,
                'has_beneficiaries' => true,
                'beneficiary_label' => 'Dependiente',
                'success_message' => '¡Censo de salud registrado exitosamente! 📋',
                'default_slot_config' => [
                    'interval_minutes' => 20,
                    'capacity_per_slot' => 4,
                ],
                'default_points_config' => [
                    'attendee' => 8,
                    'leader' => 5,
                    'referral' => 3,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Ciudadano',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => false],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'edad', 'label' => 'Edad', 'type' => 'number', 'required' => true],
                                ['name' => 'sexo', 'label' => 'Sexo', 'type' => 'select', 'required' => true, 'options' => ['Masculino', 'Femenino']],
                            ],
                        ],
                        [
                            'title' => 'Información de Salud',
                            'icon' => 'pi pi-heart',
                            'fields' => [
                                ['name' => 'tipo_sangre', 'label' => 'Tipo de Sangre', 'type' => 'select', 'required' => false, 'options' => ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'No sé']],
                                ['name' => 'seguro_medico', 'label' => '¿Tiene Seguro Médico?', 'type' => 'select', 'required' => true, 'options' => ['IMSS', 'ISSSTE', 'INSABI', 'Particular', 'Ninguno']],
                                ['name' => 'enfermedades_cronicas', 'label' => 'Enfermedades Crónicas', 'type' => 'select', 'required' => true, 'options' => ['Ninguna', 'Diabetes', 'Hipertensión', 'Asma', 'Cáncer', 'Otra']],
                                ['name' => 'discapacidad', 'label' => '¿Vive con alguna discapacidad?', 'type' => 'select', 'required' => true, 'options' => ['No', 'Motriz', 'Visual', 'Auditiva', 'Intelectual', 'Otra']],
                                ['name' => 'medicamentos_regulares', 'label' => 'Medicamentos Regulares', 'type' => 'textarea', 'required' => false],
                            ],
                        ],
                    ],
                ],
            ],

            // ── Los que ya existen sin plantilla ─────────────────────────────
            [
                'name' => 'Evento Social',
                'slug' => 'evento-social',
                'icon' => 'pi pi-users',
                'requires_appointment' => false,
                'has_beneficiaries' => false,
                'beneficiary_label' => null,
                'success_message' => '¡Te esperamos en el evento! 🎉',
                'default_slot_config' => null,
                'default_points_config' => [
                    'attendee' => 5,
                    'leader' => 3,
                    'referral' => 2,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Asistente',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'colonia', 'label' => 'Colonia', 'type' => 'text', 'required' => true],
                                ['name' => 'num_acompanantes', 'label' => 'Número de Acompañantes', 'type' => 'number', 'required' => false, 'min' => 0, 'max' => 10],
                            ],
                        ],
                    ],
                ],
            ],

            [
                'name' => 'Campaña Política',
                'slug' => 'campana-politica',
                'icon' => 'pi pi-megaphone',
                'requires_appointment' => false,
                'has_beneficiaries' => false,
                'beneficiary_label' => null,
                'success_message' => '¡Gracias por tu apoyo y asistencia! 🗳️',
                'default_slot_config' => null,
                'default_points_config' => [
                    'attendee' => 10,
                    'leader' => 8,
                    'referral' => 5,
                ],
                'default_form_schema' => [
                    'sections' => [
                        [
                            'title' => 'Datos del Ciudadano',
                            'icon' => 'pi pi-user',
                            'fields' => [
                                ['name' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                                ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                                ['name' => 'clave_elector', 'label' => 'Clave de Elector', 'type' => 'text', 'required' => false],
                                ['name' => 'seccion', 'label' => 'Sección Electoral', 'type' => 'text', 'required' => false],
                                ['name' => 'colonia', 'label' => 'Colonia', 'type' => 'text', 'required' => true],
                                ['name' => 'municipio', 'label' => 'Municipio', 'type' => 'text', 'required' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($types as $typeData) {
            $typeData['tenant_id'] = $tenantId;

            EventType::withoutGlobalScopes()->updateOrCreate(
                ['slug' => $typeData['slug']],
                $typeData
            );
        }

        $this->command->info("✅ Seeded " . count($types) . " contextual event types with form templates.");
    }
}
