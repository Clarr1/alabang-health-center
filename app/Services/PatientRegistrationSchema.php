<?php

namespace App\Services;

class PatientRegistrationSchema
{
    public const YES_NO = ['Yes', 'No', 'Unknown'];

    public const VACCINES = ['BCG at Birth', 'Vitamin K at Birth', 'Hepa-B at Birth', 'Penta HIB', 'IPV', 'OPV', 'PCV', 'Measles Containing Vaccine', 'Vitamin A', 'Others'];

    public const SCHEDULES = ['At birth', '1½ Months', '2–3 Months', '3–4 Months', '9 Months', '12 Months', 'Other'];

    public const CONDITIONS = ['Diabetes', 'Hypertension', 'Asthma', 'COPD', 'Cancer'];

    /** @return array<string, array<string, array<string, mixed>>> */
    public static function sections(): array
    {
        return [
            'Personal Information' => [
                'civil_status' => self::field('Civil status', 'select', ['Single', 'Married', 'Widowed', 'Separated', 'Annulled', 'Other']),
                'birthplace' => self::field('Birthplace'),
                'education' => self::field('Educational attainment', 'select', ['No formal education', 'Elementary', 'High school', 'Senior high school', 'Vocational', 'College', 'Postgraduate', 'Other']),
                'education_other' => self::field('Other educational attainment', when: ['education', 'Other']),
                'religion' => self::field('Religion'), 'email' => self::field('Email', 'email'), 'landline' => self::field('Landline', 'tel'),
            ],
            'Family and Membership Information' => [
                'mother_name' => self::field("Mother's name"), 'philhealth_number' => self::field('PhilHealth number'),
                'four_ps' => self::field('4Ps member', 'select', self::YES_NO),
                'four_ps_number' => self::field('4Ps number', when: ['four_ps', 'Yes']),
                'pwd' => self::field('PWD', 'select', self::YES_NO),
                'pwd_number' => self::field('PWD ID number', when: ['pwd', 'Yes']), 'senior_number' => self::field('Senior citizen ID'),
            ],
            'Child Health Profile (0–9 years)' => [
                'vaccination_status' => self::field('Vaccination status'), 'nutrition_status' => self::field('Nutritional status'),
                'development_assessment' => self::field('Developmental assessment', 'textarea'),
            ],
            'Adolescent and Adult Health Profile' => [
                'smoking' => self::field('Smoking', 'select', self::YES_NO),
                'packs_per_year' => self::field('Number of packs per year', 'number', when: ['smoking', 'Yes']),
                'vaping' => self::field('Vaping', 'select', self::YES_NO),
                'vaping_duration' => self::field('Duration of vaping', when: ['vaping', 'Yes']),
                'alcohol' => self::field('Alcohol use', 'select', self::YES_NO),
                'bottles_per_year' => self::field('Number of bottles per year', 'number', when: ['alcohol', 'Yes']),
                'sexually_active' => self::field('Sexually active', 'select', self::YES_NO),
            ],
            'Women of Reproductive Age' => [
                'reproductive_applicable' => self::field('Clinically applicable', 'select', self::YES_NO),
                'pregnant' => self::field('Pregnant', 'select', self::YES_NO, ['reproductive_applicable', 'Yes']),
                'lmp' => self::field('Last menstrual period', 'date', when: ['pregnant', 'Yes']),
                'edc' => self::field('Expected date of confinement', 'date', when: ['pregnant', 'Yes']),
                'gravida' => self::field('OB score / Gravida', 'number', when: ['pregnant', 'Yes']),
                'para' => self::field('Para', 'number', when: ['pregnant', 'Yes']),
                'family_planning' => self::field('Family planning method', when: ['reproductive_applicable', 'Yes']),
                'cervical_screening' => self::field('Cervical screening', 'select', self::YES_NO, ['reproductive_applicable', 'Yes']),
                'cervical_date' => self::field('Cervical screening date', 'date', when: ['cervical_screening', 'Yes']),
                'hpv_vaccine' => self::field('HPV vaccine', 'select', self::YES_NO, ['reproductive_applicable', 'Yes']),
                'hpv_date' => self::field('HPV vaccination date', 'date', when: ['hpv_vaccine', 'Yes']),
                'tetanus_vaccine' => self::field('Tetanus vaccine', 'select', self::YES_NO, ['reproductive_applicable', 'Yes']),
                'tetanus_date' => self::field('Tetanus vaccination date', 'date', when: ['tetanus_vaccine', 'Yes']),
            ],
            'Adolescent HEADSS Assessment' => [
                'headss_home' => self::field('H – Home', 'textarea'), 'headss_education' => self::field('E – Education / Employment', 'textarea'),
                'headss_activities' => self::field('A – Activities', 'textarea'), 'headss_drugs' => self::field('D – Drugs', 'textarea'),
                'headss_sexuality' => self::field('S – Sexuality', 'textarea'), 'headss_depression' => self::field('S – Suicide / Depression', 'textarea'),
                'headss_safety' => self::field('S – Safety', 'textarea'), 'menarche' => self::field('Menarche'), 'headss_others' => self::field('Others', 'textarea'),
            ],
            'Medical History' => [
                'tuberculosis' => self::field('History of tuberculosis', 'select', self::YES_NO),
                'tb_treatment' => self::field('Status of TB treatment', when: ['tuberculosis', 'Yes']),
                'animal_bite' => self::field('History of animal bite / rabies', 'select', self::YES_NO),
                'rabies_vaccine' => self::field('Rabies vaccine given', when: ['animal_bite', 'Yes']),
                'dengue_vaccine' => self::field('History of receiving dengue vaccine', 'select', self::YES_NO),
                'pertinent_information' => self::field('Other pertinent information', 'textarea'), 'ncd_notes' => self::field('NCD notes', 'textarea'),
            ],
            'Immunization Child Information' => [
                'delivery_manner' => self::field('Manner of delivery', 'select', ['Normal', 'CS']),
                'delivery_location' => self::field('Delivery location', 'select', ['Lying-In', 'Hospital', 'House', 'Other']),
                'maternal_status' => self::field('Maternal status during pregnancy (GDM / GHPN / etc.)', 'textarea'),
            ],
            'Form Completion' => ['preparer_designation' => self::field('Preparer designation')],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function householdFields(): array
    {
        return [
            'label' => self::field('Family name / household label'), 'address' => self::field('Household address', 'textarea'),
            'family_count' => self::field('Number of families in household', 'number'),
            'residency_years' => self::field('Years of residency in Muntinlupa', 'number'), 'previous_address' => self::field('Previous address', 'textarea'),
            'home_ownership' => self::field('Status of home ownership', 'select', ['Rent', 'Own', 'Transient', 'Informal Settler']),
            'water_source' => self::field('Type of water source', 'select', ['Point Source', 'Communal Faucet', 'Individual Connection', 'Other']),
            'water_source_other' => self::field('Other water source', when: ['water_source', 'Other']),
            'drinking_water' => self::field('Source of drinking water', 'select', ['Maynilad', 'Deep Well', 'Refilling Station', 'Other']),
            'drinking_water_other' => self::field('Other drinking water source', when: ['drinking_water', 'Other']),
            'toilet_facility' => self::field('Type of toilet facility', 'select', ['Pour/Flush Toilet Connected to Septic Tank', 'Pour/Flush Toilet Connected to Septic Tank and Sewerage System', 'Ventilated Pit Latrines / Water Sealed Toilet', 'Overhang Latrine', 'Open Pit Latrine', 'Without Latrine (Walang Palikuran)']),
            'garbage_disposal' => self::field('Garbage disposal', 'select', ['Collected', 'Segregated', 'Garbage Pit', 'None']),
            'income_source' => self::field('Source of income', 'select', ['Work/Employed', 'Business', 'Contractual', 'Livelihood Project', 'N/A', 'Other']),
            'income_source_other' => self::field('Other source of income', when: ['income_source', 'Other']),
            'multi_income' => self::field('Multi-income family', 'select', self::YES_NO), 'average_income' => self::field('Average income (Pesos)', 'number'),
        ];
    }

    public static function sensitive(string $section): bool
    {
        return in_array($section, ['Women of Reproductive Age', 'Adolescent HEADSS Assessment'], true);
    }

    /** @param array<string, mixed> $values */
    public static function visible(string $key, array $fields, array $values): bool
    {
        $condition = $fields[$key]['when'] ?? null;

        return $condition === null || (($values[$condition[0]] ?? null) === $condition[1] && self::visible($condition[0], $fields, $values));
    }

    /** @return array{label: string, type: string, options: array<int, string>, when: ?array{0: string, 1: string}} */
    private static function field(string $label, string $type = 'text', array $options = [], ?array $when = null): array
    {
        return compact('label', 'type', 'options', 'when');
    }
}
