<?php

return [
    'groups' => [
        'government_ids' => 'Government-Issued Identifiers',
        'personal_identity' => 'Personal Identity',
        'contact' => 'Contact Information',
        'location' => 'Location',
        'financial' => 'Financial Data',
        'medical' => 'Medical & Health',
        'biometric' => 'Biometric Data',
        'professional' => 'Professional & Employment',
        'digital_identity' => 'Digital Identity',
        'authentication' => 'Authentication & Secrets',
        'relationships' => 'Relationships & Emergency Contacts',
        'social_media' => 'Social Media',
        'vehicle' => 'Vehicle Information',
    ],

    'matchers' => [
        // Government-Issued Identifiers
        'national_id' => 'National ID / SSN',
        'passport_number' => 'Passport Number',
        'drivers_license' => "Driver's License",
        'tax_id' => 'Tax ID / VAT Number',
        'voter_id' => 'Voter ID / Electoral Registration Number',
        'immigration_document' => 'Visa / Immigration / Residence Permit Number',

        // Personal Identity
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'full_name' => 'Person Name',
        'date_of_birth' => 'Date of Birth',
        'gender' => 'Gender',
        'place_of_birth' => 'Place of Birth',
        'nationality' => 'Nationality / Citizenship',
        'race_ethnicity' => 'Race / Ethnicity',
        'religion' => 'Religion / Faith',
        'middle_name' => 'Middle Name',
        'maiden_name' => 'Maiden Name',
        'marital_status' => 'Marital Status',
        'age' => 'Age',

        // Contact Information
        'email_address' => 'Email Address',
        'phone_number' => 'Phone Number',
        'username' => 'Username / Login',
        'whatsapp_number' => 'WhatsApp Number',

        // Location
        'street_address' => 'Street Address',
        'city' => 'City',
        'postal_code' => 'Postal Code',
        'country' => 'Country',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'building_number' => 'Building / Apartment Number',
        'state_province' => 'State / Province / Region',
        'timezone' => 'Timezone',
        'shipping_billing_address' => 'Billing / Shipping / Delivery Address',

        // Financial Data
        'credit_card' => 'Credit Card Number',
        'iban' => 'IBAN / Bank Account',
        'bank_routing_number' => 'Bank Routing / Sort Code',
        'crypto_wallet' => 'Crypto Wallet Address',
        'salary' => 'Salary / Wage',
        'cvv_security_code' => 'CVV / Card Security Code',
        'card_expiry' => 'Card Expiration Date',
        'bank_account_number' => 'Bank Account Number',
        'credit_score' => 'Credit Score',

        // Medical & Health
        'medical_record_id' => 'Medical Record Number',
        'health_insurance_id' => 'Health Insurance ID',
        'patient_id' => 'Patient ID',
        'diagnosis' => 'Diagnosis / Medical Condition',
        'blood_type' => 'Blood Type',
        'allergies' => 'Allergies',
        'medication' => 'Medication / Prescription',
        'disability_status' => 'Disability Status',

        // Biometric Data
        'biometric_data' => 'Biometric Identifier',
        'dna_profile' => 'DNA / Genetic Data',

        // Professional & Employment
        'company_name' => 'Company Name',
        'job_title' => 'Job Title',
        'employee_id' => 'Employee ID',
        'manager_name' => 'Manager / Supervisor Name',

        // Digital Identity
        'ip_address' => 'IP Address',
        'device_id' => 'Device ID / UDID',
        'session_id' => 'Session ID',
        'mac_address' => 'MAC Address',
        'user_agent' => 'User Agent / Browser',
        'advertising_id' => 'Advertising ID (IDFA / GAID)',
        'cookie_id' => 'Cookie / Tracking ID',

        // Authentication & Secrets
        'password' => 'Password / Secret',
        'oauth_token' => 'OAuth / Access Token',
        'api_token' => 'API Token / Key',
        'private_key' => 'Private / Encryption Key',
        'pin_code' => 'PIN Code',
        'two_factor_secret' => 'Two-Factor / TOTP Secret',
        'security_answer' => 'Security Question Answer',

        // Relationships & Emergency Contacts
        'emergency_contact_name' => 'Emergency Contact Name',
        'emergency_contact_phone' => 'Emergency Contact Phone',
        'next_of_kin' => 'Next of Kin / Spouse Name',

        // Social Media
        'social_handle' => 'Social Media Handle',
        'social_profile_url' => 'Social Profile URL',

        // Vehicle Information
        'license_plate' => 'License Plate',
        'vin_number' => 'Vehicle Identification Number (VIN)',
    ],
];
