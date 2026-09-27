<?php

return [
    'groups' => [
        'government_ids' => 'Identifiants gouvernementaux',
        'personal_identity' => 'Identité personnelle',
        'contact' => 'Coordonnées de contact',
        'location' => 'Localisation',
        'financial' => 'Données financières',
        'medical' => 'Santé et données médicales',
        'biometric' => 'Données biométriques',
        'professional' => 'Informations professionnelles et emploi',
        'digital_identity' => 'Identité numérique',
        'authentication' => 'Authentification et secrets',
        'relationships' => 'Relations et contacts d\'urgence',
        'social_media' => 'Réseaux sociaux',
        'vehicle' => 'Informations sur le véhicule',
    ],

    'matchers' => [
        // Government-Issued Identifiers
        'national_id' => 'Numéro d\'identification nationale / Sécurité sociale',
        'passport_number' => 'Numéro de passeport',
        'drivers_license' => 'Permis de conduire',
        'tax_id' => 'Numéro d\'identification fiscale / TVA',
        'voter_id' => 'Carte d\'électeur / Numéro d\'enregistrement électoral',
        'immigration_document' => 'Numéro de visa / d\'immigration / de titre de séjour',

        // Personal Identity
        'first_name' => 'Prénom',
        'last_name' => 'Nom de famille',
        'full_name' => 'Nom complet',
        'date_of_birth' => 'Date de naissance',
        'gender' => 'Genre',
        'place_of_birth' => 'Lieu de naissance',
        'nationality' => 'Nationalité / Citoyenneté',
        'race_ethnicity' => 'Origine ethnique / Race',
        'religion' => 'Religion / Conviction',
        'middle_name' => 'Deuxième prénom',
        'maiden_name' => 'Nom de jeune fille',
        'marital_status' => 'État civil',
        'age' => 'Âge',

        // Contact Information
        'email_address' => 'Adresse email',
        'phone_number' => 'Numéro de téléphone',
        'username' => 'Nom d\'utilisateur / Identifiant',
        'whatsapp_number' => 'Numéro WhatsApp',

        // Location
        'street_address' => 'Adresse postale',
        'city' => 'Ville',
        'postal_code' => 'Code postal',
        'country' => 'Pays',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'building_number' => 'Numéro de bâtiment / d\'appartement',
        'state_province' => 'État / Province / Région',
        'timezone' => 'Fuseau horaire',
        'shipping_billing_address' => 'Adresse de facturation / de livraison',

        // Financial Data
        'credit_card' => 'Numéro de carte de crédit',
        'iban' => 'IBAN / Compte bancaire',
        'bank_routing_number' => 'Code guichet / Code banque (Routing / Sort code)',
        'crypto_wallet' => 'Adresse de portefeuille crypto',
        'salary' => 'Salaire / Rémunération',
        'cvv_security_code' => 'Code CVV / Cryptogramme visuel',
        'card_expiry' => 'Date d\'expiration de la carte',
        'bank_account_number' => 'Numéro de compte bancaire',
        'credit_score' => 'Score de crédit',

        // Medical & Health
        'medical_record_id' => 'Numéro de dossier médical',
        'health_insurance_id' => 'Identifiant d\'assurance maladie',
        'patient_id' => 'Identifiant patient',
        'diagnosis' => 'Diagnostic / Condition médicale',
        'blood_type' => 'Groupe sanguin',
        'allergies' => 'Allergies',
        'medication' => 'Médicament / Ordonnance',
        'disability_status' => 'Statut d\'invalidité / Handicap',

        // Biometric Data
        'biometric_data' => 'Identifiant biométrique',
        'dna_profile' => 'Profil ADN / Données génétiques',

        // Professional & Employment
        'company_name' => 'Nom de l\'entreprise',
        'job_title' => 'Intitulé de poste',
        'employee_id' => 'Identifiant employé',
        'manager_name' => 'Nom du responsable / superviseur',

        // Digital Identity
        'ip_address' => 'Adresse IP',
        'device_id' => 'Identifiant d\'appareil / UDID',
        'session_id' => 'Identifiant de session',
        'mac_address' => 'Adresse MAC',
        'user_agent' => 'Agent utilisateur (User Agent) / Navigateur',
        'advertising_id' => 'Identifiant publicitaire (IDFA / GAID)',
        'cookie_id' => 'Cookie / Identifiant de suivi',

        // Authentication & Secrets
        'password' => 'Mot de passe / Secret',
        'oauth_token' => 'Jeton OAuth / Jeton d\'accès',
        'api_token' => 'Jeton / Clé d\'API',
        'private_key' => 'Clé privée / Clé de chiffrement',
        'pin_code' => 'Code PIN',
        'two_factor_secret' => 'Secret double facteur / TOTP',
        'security_answer' => 'Réponse à la question de sécurité',

        // Relationships & Emergency Contacts
        'emergency_contact_name' => 'Nom du contact d\'urgence',
        'emergency_contact_phone' => 'Téléphone du contact d\'urgence',
        'next_of_kin' => 'Nom du proche / du conjoint',

        // Social Media
        'social_handle' => 'Identifiant sur les réseaux sociaux',
        'social_profile_url' => 'URL du profil sur les réseaux sociaux',

        // Vehicle Information
        'license_plate' => 'Plaque d\'immatriculation',
        'vin_number' => 'Numéro d\'identification du véhicule (NIV / VIN)',
    ],
];
