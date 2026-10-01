<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\ImagingTest;
use App\Models\LabTest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Starter clinical catalogues. Only runs on empty tables, so hospitals'
 * own edits are never overwritten. Everything is editable in the admin.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('icd10_codes')->doesntExist()) {
            Artisan::call('emr:import-icd10', ['file' => database_path('data/icd10_common.csv')]);
        }

        if (LabTest::query()->doesntExist()) {
            // [code, name, category, sample, turnaround hours]
            foreach ([
                ['FBC', 'Full Blood Count', 'Haematology', 'Blood (EDTA)', 4],
                ['PCV', 'Packed Cell Volume (PCV)', 'Haematology', 'Blood (EDTA)', 1],
                ['ESR', 'Erythrocyte Sedimentation Rate', 'Haematology', 'Blood (EDTA)', 2],
                ['BGRP', 'Blood Group & Rhesus', 'Haematology', 'Blood (EDTA)', 1],
                ['GENO', 'Haemoglobin Genotype', 'Haematology', 'Blood (EDTA)', 24],
                ['XMATCH', 'Cross-match', 'Haematology', 'Blood (EDTA)', 2],
                ['CLOT', 'Clotting Profile (PT/INR, APTT)', 'Haematology', 'Blood (Citrate)', 4],
                ['MPS', 'Malaria Parasite (Blood Film)', 'Parasitology', 'Blood (EDTA)', 1],
                ['MRDT', 'Malaria Rapid Diagnostic Test', 'Parasitology', 'Blood (finger prick)', 1],
                ['STOOLMC', 'Stool Microscopy (Ova & Parasites)', 'Parasitology', 'Stool', 4],
                ['RBG', 'Random Blood Glucose', 'Chemistry', 'Blood (Fluoride)', 1],
                ['FBG', 'Fasting Blood Glucose', 'Chemistry', 'Blood (Fluoride)', 1],
                ['HBA1C', 'HbA1c', 'Chemistry', 'Blood (EDTA)', 24],
                ['EUCR', 'Electrolytes, Urea & Creatinine', 'Chemistry', 'Blood (Plain)', 4],
                ['LFT', 'Liver Function Tests', 'Chemistry', 'Blood (Plain)', 6],
                ['LIPID', 'Lipid Profile', 'Chemistry', 'Blood (Plain)', 6],
                ['UA', 'Uric Acid', 'Chemistry', 'Blood (Plain)', 6],
                ['TFT', 'Thyroid Function Tests', 'Chemistry', 'Blood (Plain)', 48],
                ['PSA', 'Prostate Specific Antigen', 'Chemistry', 'Blood (Plain)', 24],
                ['TROP', 'Troponin', 'Chemistry', 'Blood (Plain)', 2],
                ['CRP', 'C-Reactive Protein', 'Chemistry', 'Blood (Plain)', 4],
                ['URINE', 'Urinalysis (Dipstick)', 'Urine', 'Urine', 1],
                ['URMCS', 'Urine Microscopy, Culture & Sensitivity', 'Microbiology', 'Urine (mid-stream)', 72],
                ['BLDCS', 'Blood Culture & Sensitivity', 'Microbiology', 'Blood (Culture bottle)', 96],
                ['HVSMCS', 'High Vaginal Swab MCS', 'Microbiology', 'Swab', 72],
                ['SPUTAFB', 'Sputum for AFB (Ziehl-Neelsen)', 'Microbiology', 'Sputum', 24],
                ['GXPERT', 'GeneXpert MTB/RIF', 'Microbiology', 'Sputum', 24],
                ['WIDAL', 'Widal Test', 'Serology', 'Blood (Plain)', 2],
                ['HIV', 'HIV 1 & 2 Screening', 'Serology', 'Blood (Plain)', 1],
                ['HBSAG', 'Hepatitis B Surface Antigen', 'Serology', 'Blood (Plain)', 1],
                ['HCV', 'Hepatitis C Antibody', 'Serology', 'Blood (Plain)', 1],
                ['VDRL', 'VDRL / Syphilis Screening', 'Serology', 'Blood (Plain)', 2],
                ['HPYL', 'H. pylori Antigen/Antibody', 'Serology', 'Stool / Blood', 2],
                ['PREG', 'Pregnancy Test (Urine hCG)', 'Serology', 'Urine', 1],
                ['CD4', 'CD4 Count', 'Immunology', 'Blood (EDTA)', 24],
                ['VL', 'HIV Viral Load', 'Immunology', 'Blood (EDTA)', 168],
            ] as [$code, $name, $category, $sample, $tat]) {
                LabTest::create(['code' => $code, 'name' => $name, 'category' => $category, 'sample_type' => $sample, 'turnaround_hours' => $tat]);
            }
        }

        $this->call(LabParameterSeeder::class);

        if (ImagingTest::query()->doesntExist()) {
            foreach ([
                ['XR-CHEST', 'Chest X-ray (PA)', 'X-ray'],
                ['XR-ABD', 'Abdominal X-ray', 'X-ray'],
                ['XR-SKULL', 'Skull X-ray', 'X-ray'],
                ['XR-CSPINE', 'Cervical Spine X-ray', 'X-ray'],
                ['XR-LSPINE', 'Lumbosacral Spine X-ray', 'X-ray'],
                ['XR-PELVIS', 'Pelvis X-ray', 'X-ray'],
                ['XR-LIMB', 'Limb X-ray (specify)', 'X-ray'],
                ['US-ABD', 'Abdominal Ultrasound', 'Ultrasound'],
                ['US-PELVIC', 'Pelvic Ultrasound', 'Ultrasound'],
                ['US-OBS', 'Obstetric Ultrasound', 'Ultrasound'],
                ['US-BREAST', 'Breast Ultrasound', 'Ultrasound'],
                ['US-PROST', 'Prostate Ultrasound', 'Ultrasound'],
                ['US-THYR', 'Thyroid Ultrasound', 'Ultrasound'],
                ['US-DOPP', 'Doppler Ultrasound (specify)', 'Ultrasound'],
                ['ECHO', 'Echocardiogram', 'Cardiac'],
                ['ECG', 'Electrocardiogram (ECG)', 'Cardiac'],
                ['CT-HEAD', 'CT Brain', 'CT'],
                ['CT-ABD', 'CT Abdomen & Pelvis', 'CT'],
                ['CT-CHEST', 'CT Chest', 'CT'],
                ['MRI-BRAIN', 'MRI Brain', 'MRI'],
                ['MRI-SPINE', 'MRI Spine', 'MRI'],
                ['MAMMO', 'Mammography', 'Mammography'],
            ] as [$code, $name, $modality]) {
                ImagingTest::create(['code' => $code, 'name' => $name, 'modality' => $modality]);
            }
        }

        $this->call([ImagingTemplateSeeder::class, BillingSeeder::class, VaccineSeeder::class, TheatreSeeder::class, SpecialtySeeder::class]);

        if (Drug::query()->doesntExist()) {
            // [name, strength, form, route]
            foreach ([
                ['Paracetamol', '500mg', 'Tablet', 'Oral'],
                ['Paracetamol', '120mg/5ml', 'Syrup', 'Oral'],
                ['Paracetamol', '1g/100ml', 'Infusion', 'IV'],
                ['Ibuprofen', '400mg', 'Tablet', 'Oral'],
                ['Ibuprofen', '100mg/5ml', 'Suspension', 'Oral'],
                ['Diclofenac', '50mg', 'Tablet', 'Oral'],
                ['Diclofenac', '75mg/3ml', 'Injection', 'IM'],
                ['Tramadol', '50mg', 'Capsule', 'Oral'],
                ['Artemether/Lumefantrine', '20/120mg', 'Tablet', 'Oral'],
                ['Artesunate', '60mg', 'Injection', 'IV'],
                ['Amoxicillin', '500mg', 'Capsule', 'Oral'],
                ['Amoxicillin', '250mg/5ml', 'Suspension', 'Oral'],
                ['Amoxicillin/Clavulanate', '625mg', 'Tablet', 'Oral'],
                ['Ampicillin/Cloxacillin', '500mg', 'Capsule', 'Oral'],
                ['Azithromycin', '500mg', 'Tablet', 'Oral'],
                ['Ciprofloxacin', '500mg', 'Tablet', 'Oral'],
                ['Levofloxacin', '500mg', 'Tablet', 'Oral'],
                ['Doxycycline', '100mg', 'Capsule', 'Oral'],
                ['Metronidazole', '400mg', 'Tablet', 'Oral'],
                ['Metronidazole', '500mg/100ml', 'Infusion', 'IV'],
                ['Ceftriaxone', '1g', 'Injection', 'IV'],
                ['Gentamicin', '80mg/2ml', 'Injection', 'IM'],
                ['Nitrofurantoin', '100mg', 'Capsule', 'Oral'],
                ['Cotrimoxazole', '960mg', 'Tablet', 'Oral'],
                ['Fluconazole', '150mg', 'Capsule', 'Oral'],
                ['Clotrimazole', '1%', 'Cream', 'Topical'],
                ['Albendazole', '400mg', 'Tablet', 'Oral'],
                ['Aciclovir', '400mg', 'Tablet', 'Oral'],
                ['Amlodipine', '5mg', 'Tablet', 'Oral'],
                ['Amlodipine', '10mg', 'Tablet', 'Oral'],
                ['Lisinopril', '10mg', 'Tablet', 'Oral'],
                ['Losartan', '50mg', 'Tablet', 'Oral'],
                ['Hydrochlorothiazide', '25mg', 'Tablet', 'Oral'],
                ['Nifedipine', '20mg', 'Tablet (SR)', 'Oral'],
                ['Methyldopa', '250mg', 'Tablet', 'Oral'],
                ['Atenolol', '50mg', 'Tablet', 'Oral'],
                ['Furosemide', '40mg', 'Tablet', 'Oral'],
                ['Furosemide', '20mg/2ml', 'Injection', 'IV'],
                ['Atorvastatin', '20mg', 'Tablet', 'Oral'],
                ['Aspirin', '75mg', 'Tablet', 'Oral'],
                ['Metformin', '500mg', 'Tablet', 'Oral'],
                ['Glibenclamide', '5mg', 'Tablet', 'Oral'],
                ['Insulin (Soluble)', '100IU/ml', 'Injection', 'SC'],
                ['Omeprazole', '20mg', 'Capsule', 'Oral'],
                ['Magnesium Trisilicate', '', 'Suspension', 'Oral'],
                ['Metoclopramide', '10mg', 'Tablet', 'Oral'],
                ['Metoclopramide', '10mg/2ml', 'Injection', 'IM'],
                ['Hyoscine Butylbromide', '10mg', 'Tablet', 'Oral'],
                ['Oral Rehydration Salts', '', 'Sachet', 'Oral'],
                ['Zinc Sulphate', '20mg', 'Tablet (dispersible)', 'Oral'],
                ['Loratadine', '10mg', 'Tablet', 'Oral'],
                ['Chlorpheniramine', '4mg', 'Tablet', 'Oral'],
                ['Salbutamol', '100mcg/dose', 'Inhaler', 'Inhaled'],
                ['Salbutamol', '4mg', 'Tablet', 'Oral'],
                ['Prednisolone', '5mg', 'Tablet', 'Oral'],
                ['Hydrocortisone', '100mg', 'Injection', 'IV'],
                ['Ferrous Sulphate', '200mg', 'Tablet', 'Oral'],
                ['Folic Acid', '5mg', 'Tablet', 'Oral'],
                ['Vitamin B Complex', '', 'Tablet', 'Oral'],
                ['Vitamin C', '100mg', 'Tablet', 'Oral'],
                ['Diazepam', '5mg', 'Tablet', 'Oral'],
                ['Diazepam', '10mg/2ml', 'Injection', 'IV'],
                ['Carbamazepine', '200mg', 'Tablet', 'Oral'],
                ['Phenobarbitone', '30mg', 'Tablet', 'Oral'],
                ['Amitriptyline', '25mg', 'Tablet', 'Oral'],
                ['Oxytocin', '10IU/ml', 'Injection', 'IM'],
                ['Misoprostol', '200mcg', 'Tablet', 'Oral'],
                ['Magnesium Sulphate', '50%', 'Injection', 'IM'],
                ['Normal Saline', '0.9%', 'Infusion', 'IV'],
                ['Ringer\'s Lactate', '', 'Infusion', 'IV'],
                ['Dextrose', '5%', 'Infusion', 'IV'],
                ['Chloramphenicol', '0.5%', 'Eye drops', 'Eye'],
                ['Tetracycline', '1%', 'Eye ointment', 'Eye'],
            ] as [$name, $strength, $form, $route]) {
                Drug::create(['name' => $name, 'strength' => $strength ?: null, 'form' => $form, 'route' => $route]);
            }
        }
    }
}
