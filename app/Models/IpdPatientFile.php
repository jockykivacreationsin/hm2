<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class IpdPatientFile extends Model
{
    protected $table = 'ipd_patient_files';
    public $timestamps = false;

    protected $attributes = [
        'relative_name'                     => '',
        'relationship'                      => '',
        'witness_name'                      => '',
        'admission_type'                    => '',
        'patient_condition'                 => '',
        'source_of_history'                 => '',
        'general_condition'                 => '',
        'chief_complaints'                  => '',
        'history_of_present_illness'        => '',
        'past_medical_history'              => '',
        'pain_score'                        => '',
        'pain_location'                     => '',
        'intervention'                      => '',
        'family_ho'                         => '',
        'current_medication'                => '',
        'allergies'                         => '',
        'ho_blood_transfusion'              => '',
        'substance_abuse'                   => '',
        'nutritional_status'                => '',
        'general_examination'               => '',
        'systemic_examination'              => '',
        'cns_system_mental_status'          => '',
        'cns_system_sensory_examination'    => '',
        'cns_system_motor_examination_tone' => '',
        'cns_system_reflexes'               => '',
        'cns_system_power'                  => '',
        'provisional_diagnosis'             => '',
        'diet_plan'                         => '',
        'progress_sheet'                    => '',
        'rmo_notes'                         => '',
    ];

    protected $fillable = [
        'ipd_id',
        // Admission details
        'relative_name', 'relationship', 'witness_name',
        'admission_type', 'patient_condition', 'general_condition',
        // History
        'source_of_history', 'chief_complaints',
        'history_of_present_illness', 'past_medical_history',
        // Pain
        'pain_score', 'pain_location', 'intervention',
        // Background
        'family_ho', 'current_medication', 'allergies', 'ho_blood_transfusion',
        // Multi-select (stored comma-separated)
        'substance_abuse', 'nutritional_status', 'general_examination',
        // Systemic / CNS
        'systemic_examination',
        'cns_system_mental_status', 'cns_system_sensory_examination',
        'cns_system_motor_examination_tone', 'cns_system_reflexes', 'cns_system_power',
        // Plan
        'provisional_diagnosis', 'diet_plan',
        // Progress (used by progress-sheet tab)
        'progress_sheet', 'rmo_notes',
    ];

    public function ipd() { return $this->belongsTo(Ipd::class); }
}
