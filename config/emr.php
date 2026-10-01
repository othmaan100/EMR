<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installation lock
    |--------------------------------------------------------------------------
    | Once the setup wizard completes, this file is written. Its presence means
    | the application is installed and the wizard is no longer reachable.
    | EMR_INSTALLED_FILE (optional) is relative to the project root.
    */
    'installed_file' => env('EMR_INSTALLED_FILE')
        ? base_path(env('EMR_INSTALLED_FILE'))
        : storage_path('app/installed.lock'),

    'version' => '1.2.0',

    /*
    |--------------------------------------------------------------------------
    | Security & backups
    |--------------------------------------------------------------------------
    */
    'security' => [
        'max_failed_logins' => 5,      // consecutive failures before the account locks
        'lockout_minutes' => 15,
    ],

    'backup' => [
        'path' => storage_path('app/backups'),
        // mysqldump binary; auto-detected on XAMPP when not set.
        'mysqldump' => env('EMR_MYSQLDUMP'),
        // Folders copied into every backup (patient photos, imaging files, uploads).
        'folders' => [storage_path('app/private'), public_path('uploads')],
    ],

    /*
    | Uploaded branding (logo) is stored on this disk so it works on hosts
    | where `storage:link` symlinks are unavailable (e.g. Windows/XAMPP).
    */
    'upload_disk' => 'uploads',

    'hospital_types' => [
        'General Hospital',
        'Specialist Hospital',
        'Teaching Hospital',
        'Clinic',
        'Primary Health Centre',
        'Maternity Home',
        'Diagnostic Centre',
        'University Health Service',
        'Other',
    ],

    'currencies' => [
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
        'GHS' => ['name' => 'Ghanaian Cedi', 'symbol' => 'GH₵'],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
        'UGX' => ['name' => 'Ugandan Shilling', 'symbol' => 'USh'],
        'TZS' => ['name' => 'Tanzanian Shilling', 'symbol' => 'TSh'],
        'RWF' => ['name' => 'Rwandan Franc', 'symbol' => 'FRw'],
        'XOF' => ['name' => 'West African CFA Franc', 'symbol' => 'CFA'],
        'XAF' => ['name' => 'Central African CFA Franc', 'symbol' => 'FCFA'],
        'EGP' => ['name' => 'Egyptian Pound', 'symbol' => 'E£'],
        'MAD' => ['name' => 'Moroccan Dirham', 'symbol' => 'DH'],
        'ETB' => ['name' => 'Ethiopian Birr', 'symbol' => 'Br'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
        'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => 'Rs'],
        'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SAR'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'CA$'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱'],
    ],

    'date_formats' => [
        'd/m/Y' => '29/09/2026',
        'm/d/Y' => '09/29/2026',
        'Y-m-d' => '2026-09-29',
        'd M Y' => '29 Sep 2026',
        'M d, Y' => 'Sep 29, 2026',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default settings
    |--------------------------------------------------------------------------
    | Fallback values used before the wizard has saved anything.
    */
    'defaults' => [
        'hospital_name' => 'Hospital EMR',
        'currency_code' => 'NGN',
        'currency_symbol' => '₦',
        'timezone' => 'Africa/Lagos',
        'date_format' => 'd/m/Y',
        'patient_number_prefix' => 'PT',
        'patient_number_padding' => 6,
        'primary_color' => '#0d6efd',
        'session_idle_minutes' => 30,
        'backup_retention_days' => 14,
        'sms_provider' => 'none',
        'sms_country_code' => '234',
        'payment_gateway' => 'none',
        'nin_provider' => 'none',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles & permissions
    |--------------------------------------------------------------------------
    | Seeded on install. "Super Admin" bypasses all permission checks. Later
    | modules append their own permissions here.
    */
    'super_admin_role' => 'Super Admin',

    // Grouped for display in the role permission matrix: group => [name => label].
    'permissions' => [
        'Administration' => [
            'settings.manage' => 'Manage hospital settings',
            'users.view' => 'View staff list & profiles',
            'users.manage' => 'Create, edit & deactivate staff',
            'departments.manage' => 'Manage departments',
            'roles.manage' => 'Manage roles & permissions',
            'audit.view' => 'View audit log',
            'insurance.manage' => 'Manage insurance / HMO providers',
            'data.import' => 'Import data from spreadsheets (legacy system records)',
            'integrations.manage' => 'Set up integrations (lab analysers, PACS, payments, e-claims, NIN) & view their logs',
        ],
        'Patients' => [
            'patients.view' => 'Search & view patient records',
            'patients.create' => 'Register new patients',
            'patients.update' => 'Edit patient details',
            'patients.delete' => 'Archive (delete) patient records',
            'portal.manage' => 'Give patients portal access (activation codes)',
        ],
        'Appointments & Queue' => [
            'appointments.view' => 'View appointments',
            'appointments.manage' => 'Book, reschedule & cancel appointments',
            'visits.checkin' => 'Check patients in (start a visit)',
            'queue.view' => 'View clinic queues',
            'queue.manage' => 'Move patients through the queue',
            'clinics.manage' => 'Manage clinics',
        ],
        'Nursing' => [
            'vitals.view' => 'View vital signs & nursing notes',
            'vitals.record' => 'Record vital signs / triage',
            'vitals.void' => "Void other staff's vital sign entries",
            'nursing_notes.create' => 'Write nursing notes',
        ],
        'Consultation & Orders' => [
            'consultations.view' => 'View consultation notes & diagnoses',
            'consultations.create' => 'Conduct consultations (write, sign)',
            'lab.request' => 'Request laboratory tests',
            'imaging.request' => 'Request imaging',
            'prescriptions.create' => 'Prescribe medication',
            'catalog.manage' => 'Manage lab, imaging & drug catalogues',
        ],
        'Laboratory' => [
            'lab.process' => 'Work the lab: collect samples & enter results',
            'lab.verify' => 'Verify & release lab results',
            'lab.results.view' => 'View released lab results',
        ],
        'Radiology' => [
            'radiology.process' => 'Work radiology: schedule, perform exams, upload images',
            'radiology.report' => 'Write & sign imaging reports',
            'imaging.results.view' => 'View released imaging reports',
        ],
        'Pharmacy & Inventory' => [
            'pharmacy.dispense' => 'Dispense prescriptions',
            'inventory.view' => 'View drug stock levels',
            'inventory.manage' => 'Receive stock, adjust stock & manage suppliers',
        ],
        'Billing' => [
            'billing.view' => 'View patient bills & payments',
            'billing.collect' => 'Take payments & print receipts (cashier)',
            'billing.discount' => 'Give discounts / waivers & void charges',
            'billing.reverse' => 'Reverse payments',
            'billing.prices' => 'Manage the price list',
            'billing.claims' => 'Manage insurance / HMO claims',
            'claims.preauth' => 'Request & record HMO pre-authorisation codes',
        ],
        'Inpatients' => [
            'admissions.view' => 'View inpatients & bed board',
            'admissions.manage' => 'Admit patients & transfer beds',
            'admissions.discharge' => 'Discharge patients',
            'admissions.notes' => 'Write ward-round / progress notes',
            'medication.administer' => 'Record medication given (drug chart)',
            'wards.manage' => 'Manage wards & beds',
        ],
        'Reports' => [
            'reports.operational' => 'Patient-flow & pharmacy reports',
            'reports.clinical' => 'Clinical reports (diagnoses, investigations, admissions)',
            'reports.financial' => 'Financial reports (revenue, collections, debts, claims)',
            'reports.staff' => 'Staff activity report',
        ],
        'System' => [
            'system.manage' => 'System health & backups',
            'sms.manage' => 'View SMS log & send test messages',
        ],
        'Maternity & child health' => [
            'maternity.view' => 'View pregnancies, deliveries & postnatal records',
            'maternity.record' => 'Record ANC, labour, delivery & postnatal care',
            'immunization.record' => 'Record immunizations',
        ],
        'Theatre' => [
            'theatre.view' => 'View the theatre list & operation records',
            'theatre.book' => 'Book, reschedule & cancel operations',
            'theatre.manage' => 'Pre-op assessment, WHO checklist & anaesthesia record',
            'theatre.operate' => 'Write & sign operation notes (surgeon)',
        ],
        'Specialty clinics' => [
            'specialty.view' => 'View dental, eye & physiotherapy records',
            'dental.record' => 'Record dental charts & treatment',
            'eye.record' => 'Record eye examinations & spectacle prescriptions',
            'physio.record' => 'Record physiotherapy assessments & sessions',
        ],
        'Stores & procurement' => [
            'requisitions.create' => 'Request items from the general store for my department',
            'stores.view' => 'View general store stock & requisitions',
            'stores.manage' => 'Issue, receive & adjust store stock; manage store items',
            'purchasing.manage' => 'Raise purchase orders & receive deliveries; manage suppliers',
            'purchasing.approve' => 'Approve purchase orders',
            'payables.manage' => 'Record & pay supplier invoices',
        ],
    ],

    // Default roles. These cannot be renamed or deleted (their permissions
    // can still be edited). Hospitals may add their own custom roles.
    'roles' => [
        'Super Admin' => [],
        'Administrator' => ['settings.manage', 'users.view', 'users.manage', 'departments.manage', 'roles.manage', 'audit.view', 'insurance.manage', 'data.import', 'integrations.manage',
            'patients.view', 'patients.create', 'patients.update', 'patients.delete', 'portal.manage',
            'appointments.view', 'appointments.manage', 'visits.checkin', 'queue.view', 'queue.manage', 'clinics.manage',
            'vitals.view', 'vitals.record', 'vitals.void', 'nursing_notes.create', 'consultations.view', 'catalog.manage', 'lab.process', 'lab.verify', 'lab.results.view',
            'radiology.process', 'radiology.report', 'imaging.results.view',
            'pharmacy.dispense', 'inventory.view', 'inventory.manage',
            'billing.view', 'billing.collect', 'billing.discount', 'billing.reverse', 'billing.prices', 'billing.claims', 'claims.preauth',
            'admissions.view', 'admissions.manage', 'admissions.discharge', 'admissions.notes', 'medication.administer', 'wards.manage',
            'reports.operational', 'reports.clinical', 'reports.financial', 'reports.staff', 'system.manage', 'sms.manage',
            'maternity.view', 'maternity.record', 'immunization.record',
            'theatre.view', 'theatre.book', 'theatre.manage', 'theatre.operate',
            'specialty.view',
            'requisitions.create', 'stores.view', 'stores.manage', 'purchasing.manage', 'purchasing.approve', 'payables.manage'],
        'Doctor' => ['users.view', 'patients.view', 'appointments.view', 'appointments.manage', 'queue.view', 'queue.manage',
            'vitals.view', 'vitals.record', 'nursing_notes.create',
            'consultations.view', 'consultations.create', 'lab.request', 'imaging.request', 'prescriptions.create', 'lab.results.view', 'imaging.results.view', 'inventory.view',
            'admissions.view', 'admissions.manage', 'admissions.discharge', 'admissions.notes', 'reports.clinical',
            'maternity.view', 'maternity.record',
            'theatre.view', 'theatre.book', 'theatre.manage', 'theatre.operate',
            'specialty.view', 'dental.record', 'eye.record', 'physio.record'],
        'Nurse' => ['users.view', 'patients.view', 'patients.update', 'appointments.view', 'visits.checkin', 'queue.view', 'queue.manage',
            'vitals.view', 'vitals.record', 'nursing_notes.create', 'consultations.view', 'lab.results.view', 'imaging.results.view',
            'admissions.view', 'admissions.manage', 'admissions.notes', 'medication.administer', 'reports.clinical',
            'maternity.view', 'immunization.record', 'theatre.view', 'theatre.manage', 'specialty.view', 'requisitions.create'],
        'Midwife' => ['users.view', 'patients.view', 'patients.update', 'appointments.view', 'appointments.manage', 'visits.checkin', 'queue.view', 'queue.manage',
            'vitals.view', 'vitals.record', 'nursing_notes.create', 'consultations.view', 'lab.results.view', 'imaging.results.view',
            'admissions.view', 'admissions.manage', 'admissions.notes', 'medication.administer',
            'maternity.view', 'maternity.record', 'immunization.record', 'theatre.view', 'requisitions.create'],
        'Anaesthetist' => ['users.view', 'patients.view', 'vitals.view', 'vitals.record', 'consultations.view', 'lab.results.view', 'imaging.results.view',
            'admissions.view', 'admissions.notes', 'prescriptions.create', 'theatre.view', 'theatre.manage'],
        'Pharmacist' => ['patients.view', 'consultations.view', 'admissions.view', 'pharmacy.dispense', 'inventory.view', 'inventory.manage', 'reports.operational',
            'requisitions.create', 'purchasing.manage'],
        'Lab Scientist' => ['patients.view', 'lab.process', 'lab.verify', 'lab.results.view', 'requisitions.create'],
        'Radiologist' => ['patients.view', 'radiology.process', 'radiology.report', 'imaging.results.view'],
        'Radiographer' => ['patients.view', 'radiology.process'],
        'Dental Therapist' => ['patients.view', 'appointments.view', 'queue.view', 'queue.manage', 'vitals.view', 'lab.results.view', 'imaging.results.view',
            'specialty.view', 'dental.record'],
        'Optometrist' => ['patients.view', 'appointments.view', 'queue.view', 'queue.manage', 'vitals.view', 'specialty.view', 'eye.record'],
        'Physiotherapist' => ['patients.view', 'appointments.view', 'queue.view', 'queue.manage', 'vitals.view', 'imaging.results.view',
            'specialty.view', 'physio.record'],
        'Records Officer' => ['patients.view', 'patients.create', 'patients.update', 'billing.view', 'claims.preauth', 'portal.manage',
            'appointments.view', 'appointments.manage', 'visits.checkin', 'queue.view', 'reports.operational'],
        'Cashier' => ['patients.view', 'billing.view', 'billing.collect', 'admissions.view', 'claims.preauth'],
        'Accountant' => ['patients.view', 'billing.view', 'billing.discount', 'billing.reverse', 'billing.prices', 'billing.claims', 'claims.preauth', 'reports.financial',
            'stores.view', 'purchasing.approve', 'payables.manage'],
        'Storekeeper' => ['stores.view', 'stores.manage', 'requisitions.create', 'purchasing.manage', 'inventory.view', 'reports.operational'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Patient registration lists
    |--------------------------------------------------------------------------
    */
    'patient' => [
        'titles' => ['Mr', 'Mrs', 'Miss', 'Ms', 'Master', 'Baby', 'Dr', 'Prof', 'Rev'],
        'genders' => ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'],
        'marital_statuses' => ['Single', 'Married', 'Divorced', 'Separated', 'Widowed'],
        'blood_groups' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'genotypes' => ['AA', 'AS', 'AC', 'SS', 'SC', 'CC'],
        'relationships' => ['Spouse', 'Parent', 'Child', 'Sibling', 'Guardian', 'Relative', 'Friend', 'Other'],
        'religions' => ['Christianity', 'Islam', 'Traditional', 'Hinduism', 'Buddhism', 'Other', 'None'],
    ],

    'department_types' => [
        'Clinical',
        'Nursing',
        'Diagnostic',
        'Pharmacy',
        'Administrative',
        'Finance',
        'Support Services',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maternity
    |--------------------------------------------------------------------------
    */
    'specialty' => [
        'clinic_types' => ['general' => 'General', 'dental' => 'Dental', 'eye' => 'Eye / optometry', 'physio' => 'Physiotherapy'],

        // FDI tooth numbering, drawn as the patient faces you (their right on the left).
        'teeth' => [
            'permanent' => [[18, 17, 16, 15, 14, 13, 12, 11], [21, 22, 23, 24, 25, 26, 27, 28], [48, 47, 46, 45, 44, 43, 42, 41], [31, 32, 33, 34, 35, 36, 37, 38]],
            'primary' => [[55, 54, 53, 52, 51], [61, 62, 63, 64, 65], [85, 84, 83, 82, 81], [71, 72, 73, 74, 75]],
        ],
        'surfaces' => ['M' => 'Mesial', 'O' => 'Occlusal / incisal', 'D' => 'Distal', 'B' => 'Buccal / labial', 'L' => 'Lingual / palatal'],
        // condition => [label, colour for the chart]
        'dental_conditions' => [
            'sound' => ['Sound / healthy', '#ffffff'],
            'caries' => ['Caries', '#dc3545'],
            'filled' => ['Filled', '#0d6efd'],
            'missing' => ['Missing', '#adb5bd'],
            'extracted' => ['Extracted', '#6c757d'],
            'crown' => ['Crown', '#ffc107'],
            'root_canal' => ['Root canal treated', '#6f42c1'],
            'fractured' => ['Fractured', '#fd7e14'],
            'abscess' => ['Abscess / periapical lesion', '#b02a37'],
            'mobile' => ['Mobile', '#20c997'],
            'impacted' => ['Impacted / unerupted', '#495057'],
            'implant' => ['Implant', '#0dcaf0'],
            'bridge' => ['Bridge / pontic', '#198754'],
        ],
        'snellen' => ['6/5', '6/6', '6/9', '6/12', '6/18', '6/24', '6/36', '6/60', '3/60', '1/60', 'CF', 'HM', 'PL', 'NPL'],
        'physio_treatments' => [
            'exercise' => 'Exercise therapy', 'manual' => 'Manual therapy / mobilisation', 'massage' => 'Soft-tissue massage',
            'tens' => 'TENS', 'ultrasound' => 'Therapeutic ultrasound', 'heat' => 'Heat therapy', 'ice' => 'Cryotherapy / ice',
            'traction' => 'Traction', 'gait' => 'Gait training', 'chest' => 'Chest physiotherapy', 'education' => 'Education & home programme',
        ],
        'physio_outcomes' => ['goals_met' => 'Goals met', 'improved' => 'Improved', 'unchanged' => 'No change', 'worse' => 'Worse', 'dropped_out' => 'Stopped attending', 'referred' => 'Referred on'],
    ],

    'maternity' => [
        'risk_factors' => [
            'age_extreme' => 'Age under 18 or over 35',
            'grand_multipara' => 'Grand multipara (5+ births)',
            'previous_cs' => 'Previous caesarean section',
            'previous_stillbirth' => 'Previous stillbirth / neonatal death',
            'previous_pph' => 'Previous postpartum haemorrhage',
            'hypertension' => 'Hypertension / pre-eclampsia',
            'diabetes' => 'Diabetes',
            'sickle_cell' => 'Sickle cell disease',
            'hiv' => 'HIV positive',
            'anaemia' => 'Severe anaemia',
            'multiple' => 'Multiple pregnancy',
            'malpresentation' => 'Malpresentation',
            'short_stature' => 'Short stature (< 150 cm)',
        ],
        'interventions' => [
            'iptp' => 'IPTp (SP) given',
            'td' => 'Tetanus-diphtheria (Td)',
            'iron_folate' => 'Iron / folic acid',
            'llin' => 'Insecticide-treated net',
            'deworming' => 'Deworming',
            'hiv_test' => 'HIV test / counselling',
        ],
        'presentations' => ['Cephalic', 'Breech', 'Transverse', 'Not determined'],
        'urine_protein' => ['Nil', 'Trace', '+', '++', '+++'],
        'oedema' => ['None', '+', '++', '+++'],
        'delivery_modes' => [
            'svd' => 'Spontaneous vaginal delivery',
            'assisted' => 'Assisted vaginal (vacuum / forceps)',
            'breech' => 'Vaginal breech',
            'cs_elective' => 'Caesarean section (elective)',
            'cs_emergency' => 'Caesarean section (emergency)',
        ],
        'perineum' => ['intact' => 'Intact', 'episiotomy' => 'Episiotomy', 'tear1' => '1st-degree tear', 'tear2' => '2nd-degree tear',
            'tear3' => '3rd-degree tear', 'tear4' => '4th-degree tear'],
        'complications' => [
            'pph' => 'Postpartum haemorrhage', 'eclampsia' => 'Eclampsia', 'obstructed' => 'Obstructed labour',
            'retained_placenta' => 'Retained placenta', 'ruptured_uterus' => 'Ruptured uterus', 'sepsis' => 'Sepsis', 'cord_prolapse' => 'Cord prolapse',
        ],
        'baby_outcomes' => [
            'live_birth' => 'Live birth', 'fresh_stillbirth' => 'Fresh stillbirth',
            'macerated_stillbirth' => 'Macerated stillbirth', 'neonatal_death' => 'Early neonatal death',
        ],
        'liquor' => ['I' => 'Intact membranes', 'C' => 'Clear', 'M' => 'Meconium-stained', 'B' => 'Blood-stained', 'A' => 'Absent'],
        'moulding' => ['0', '+', '++', '+++'],
    ],
];
