<?php

use App\Services\Cloning\Faker\CloningFakerCatalog;
use App\Services\Cloning\Pii\PiiMatcherCatalog;
use Faker\Factory;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function piiMatchersFile(): array
{
    return Yaml::parseFile(resource_path('app/clonio.pii-matchers.yaml'));
}

/**
 * @return array<string, array<string, mixed>>
 */
function piiMatchers(): array
{
    $matchers = [];
    foreach (piiMatchersFile()['groups'] as $group) {
        foreach ($group['matchers'] as $key => $matcher) {
            $matchers[$key] = $matcher;
        }
    }

    return $matchers;
}

function piiMatcherOwner(string $column): ?string
{
    static $catalog = null;

    $catalog ??= PiiMatcherCatalog::fromRaw(piiMatchersFile());

    return $catalog->match($column)?->matcher->key;
}

it('declares every matcher with a valid shape', function () {
    $matchers = piiMatchers();

    expect($matchers)->toHaveCount(89);

    foreach ($matchers as $key => $matcher) {
        expect($matcher)->toHaveKeys(['name', 'sensitivity', 'enabled', 'patterns', 'transformation'], "Matcher [{$key}]")
            ->and($matcher['sensitivity'])->toBeIn(['low', 'medium', 'high', 'critical'], "Sensitivity of [{$key}]")
            ->and($matcher['enabled'])->toBeBool("Enabled flag of [{$key}]")
            ->and($matcher['patterns'])->toBeArray()->not->toBeEmpty("Patterns of [{$key}]");
    }
});

it('only uses regular expressions that compile', function () {
    foreach (piiMatchers() as $key => $matcher) {
        foreach ($matcher['patterns'] as $pattern) {
            if (preg_match('#^/(.*)/([a-zA-Z]*)$#s', (string) $pattern, $parts) !== 1) {
                continue;
            }

            expect(@preg_match('/'.$parts[1].'/'.$parts[2], ''))->not->toBeFalse("Pattern [{$pattern}] of [{$key}] does not compile")
                ->and($parts[1])->toStartWith('^', "Pattern of [{$key}] must be anchored")->toEndWith('$', "Pattern of [{$key}] must be anchored");
        }
    }
});

it('only uses strategies, faker methods and hash algorithms accepted by clonio', function () {
    foreach (piiMatchers() as $key => $matcher) {
        $transformation = $matcher['transformation'];

        expect($transformation['strategy'])->toBeIn(['keep', 'fake', 'hash', 'mask', '"null"', 'static', 'template', 'remapping'], "Strategy of [{$key}]");

        if ($transformation['strategy'] === 'fake') {
            expect(CloningFakerCatalog::supports($transformation['faker_method']))->toBeTrue("Faker method of [{$key}]");
        }

        if ($transformation['strategy'] === 'hash') {
            expect($transformation['algorithm'])->toBeIn(['sha256', 'sha512', 'md5', 'sha1'], "Hash algorithm of [{$key}]");
        }

        if ($transformation['strategy'] === 'static') {
            expect($transformation['value'])->toBeString()->not->toBeEmpty("Static value of [{$key}]");
        }
    }
});

it('generates a scalar value for every fake transformation in every locale', function (string $locale) {
    $faker = Factory::create($locale);

    foreach (piiMatchers() as $key => $matcher) {
        $transformation = $matcher['transformation'];

        if ($transformation['strategy'] !== 'fake') {
            continue;
        }

        $value = $faker->{$transformation['faker_method']}(...array_values($transformation['faker_arguments'] ?? []));

        expect(is_string($value) || is_int($value) || is_float($value))->toBeTrue("Value generated for [{$key}] in [{$locale}]")
            ->and((string) $value)->not->toBe('', "Value generated for [{$key}] in [{$locale}]");
    }
})->with(['fr_FR', 'en_US', 'de_DE', 'es_ES', 'en_GB', 'ja_JP']);

it('keeps generated values within the length of the columns they target', function (string $matcher, int $maxLength) {
    $transformation = piiMatchers()[$matcher]['transformation'];
    $longest = 0;

    foreach (['fr_FR', 'en_US'] as $locale) {
        $faker = Factory::create($locale);

        for ($i = 0; $i < 300; $i++) {
            $value = (string) $faker->{$transformation['faker_method']}(...array_values($transformation['faker_arguments'] ?? []));
            $longest = max($longest, mb_strlen($value));
        }
    }

    expect($longest)->toBeLessThanOrEqual($maxLength);
})->with([
    'cvv fits 3 characters' => ['cvv_security_code', 3],
    'pin fits 4 characters' => ['pin_code', 4],
    'sort code fits 6 characters' => ['sort_code', 6],
    'bic fits 11 characters' => ['swift_bic', 11],
    'state abbreviation fits 2 characters' => ['state_abbreviation', 2],
    'imei fits 15 characters' => ['imei', 15],
    'siren fits 9 characters' => ['siren_number', 9],
    'siret fits 14 characters' => ['siret_number', 14],
    'phone fits 16 characters' => ['phone_number', 16],
    'emergency phone fits 16 characters' => ['emergency_contact_phone', 16],
    'whatsapp fits 16 characters' => ['whatsapp_number', 16],
    'license plate fits 8 characters' => ['license_plate', 8],
    'passport fits 9 characters' => ['passport_number', 9],
    'vin fits 17 characters' => ['vin_number', 17],
    'ethereum wallet fits 42 characters' => ['crypto_wallet', 42],
    'patient id fits 6 characters' => ['patient_id', 6],
    'api token fits 32 characters' => ['api_token', 32],
    'oauth token fits 32 characters' => ['oauth_token', 32],
    'username fits 12 characters' => ['username', 12],
    'email fits 24 characters' => ['email_address', 24],
    'street address fits 40 characters' => ['street_address', 40],
    'mac address fits 17 characters' => ['mac_address', 17],
]);

it('generates single line street addresses', function () {
    $transformation = piiMatchers()['street_address']['transformation'];

    foreach (['fr_FR', 'en_US'] as $locale) {
        $faker = Factory::create($locale);

        for ($i = 0; $i < 300; $i++) {
            expect($faker->{$transformation['faker_method']}())->not->toContain("\n");
        }
    }
});

it('generates values that stay unique on large tables', function (string $matcher) {
    $transformation = piiMatchers()[$matcher]['transformation'];
    $faker = Factory::create('fr_FR');
    $values = [];

    for ($i = 0; $i < 30000; $i++) {
        $values[$faker->{$transformation['faker_method']}(...array_values($transformation['faker_arguments'] ?? []))] = true;
    }

    expect($values)->toHaveCount(30000);
})->with(['email_address', 'username', 'oauth_token', 'api_token', 'device_id', 'cookie_id', 'advertising_id']);

it('generates uppercase identifiers', function (string $matcher, string $format) {
    $transformation = piiMatchers()[$matcher]['transformation'];
    $faker = Factory::create('fr_FR');

    for ($i = 0; $i < 100; $i++) {
        expect($faker->{$transformation['faker_method']}(...$transformation['faker_arguments']))->toMatch($format);
    }
})->with([
    'passport' => ['passport_number', '/^[A-Z][0-9]{8}$/'],
    'license plate' => ['license_plate', '/^[A-Z]{3}-[0-9]{4}$/'],
    'vin' => ['vin_number', '/^[A-HJ-NPR-Z0-9]{17}$/'],
    'state abbreviation' => ['state_abbreviation', '/^[A-Z]{2}$/'],
]);

it('only generates adults for a date of birth', function () {
    $transformation = piiMatchers()['date_of_birth']['transformation'];
    $faker = Factory::create('fr_FR');
    $limit = date('Y-m-d', strtotime('-18 years'));

    for ($i = 0; $i < 2000; $i++) {
        expect($faker->date(...$transformation['faker_arguments']))->toBeLessThanOrEqual($limit);
    }
});

it('replaces passwords with a valid hash of a known password', function () {
    $transformation = piiMatchers()['password']['transformation'];

    expect($transformation['strategy'])->toBe('static')
        ->and(password_verify('password', $transformation['value']))->toBeTrue()
        ->and(password_get_info($transformation['value'])['algoName'])->toBe('bcrypt');
});

it('routes a column name to the expected matcher', function (string $column, ?string $expected) {
    expect(piiMatcherOwner($column))->toBe($expected);
})->with([
    // Government-issued identifiers
    ['ssn', 'national_id'], ['social_security', 'national_id'], ['social_security_number', 'national_id'], ['national_id', 'national_id'],
    ['national_id_number', 'national_id'], ['personal_id', 'national_id'], ['nir', 'national_id'], ['numero_secu', 'national_id'],
    ['passport', 'passport_number'], ['passport_number', 'passport_number'], ['passport_no', 'passport_number'], ['passport_num', 'passport_number'],
    ['driver_license', 'drivers_license'], ['drivers_license', 'drivers_license'], ['drivers_license_number', 'drivers_license'],
    ['driving_licence', 'drivers_license'], ['driving_licence_no', 'drivers_license'], ['dl_number', 'drivers_license'], ['license_number', null],
    ['tax_id', 'tax_id'], ['tax_id_number', 'tax_id'], ['tax_number', 'tax_id'], ['tin', 'tax_id'], ['vat_number', 'tax_id'], ['fiscal_id', 'tax_id'],
    ['voter_id', 'voter_id'], ['visa_number', 'immigration_document'], ['residence_permit', 'immigration_document'],

    // Personal identity
    ['first_name', 'first_name'], ['firstname', 'first_name'], ['fname', 'first_name'], ['forename', 'first_name'], ['given_name', 'first_name'],
    ['given_names', 'first_name'], ['prenom', 'first_name'],
    ['last_name', 'last_name'], ['lastname', 'last_name'], ['lname', 'last_name'], ['surname', 'last_name'], ['family_name', 'last_name'],
    ['nom', 'last_name'], ['nom_de_famille', 'last_name'], ['nom_famille', 'last_name'],
    ['full_name', 'full_name'], ['fullname', 'full_name'], ['display_name', 'full_name'], ['name', 'full_name'], ['nickname', 'full_name'],
    ['birth_date', 'date_of_birth'], ['birthdate', 'date_of_birth'], ['birthday', 'date_of_birth'], ['birth_day', 'date_of_birth'],
    ['date_of_birth', 'date_of_birth'], ['dob', 'date_of_birth'], ['date_naissance', 'date_of_birth'], ['date_de_naissance', 'date_of_birth'],
    ['year_of_birth', null],
    ['gender', 'gender'], ['sex', 'gender'], ['sexe', 'gender'], ['civilite', 'gender'],
    ['birthplace', 'place_of_birth'], ['birth_place', 'place_of_birth'], ['place_of_birth', 'place_of_birth'], ['birth_city', 'place_of_birth'],
    ['lieu_naissance', 'place_of_birth'], ['lieu_de_naissance', 'place_of_birth'],
    ['nationality', 'nationality'], ['nationalite', 'nationality'], ['citizenship', 'nationality'],
    ['race', 'race_ethnicity'], ['ethnicity', 'race_ethnicity'], ['religion', 'religion'],
    ['middle_name', 'middle_name'], ['maiden_name', 'maiden_name'], ['birth_name', 'maiden_name'], ['marital_status', 'marital_status'], ['age', 'age'],
    ['sexual_orientation', 'sexual_orientation'], ['political_affiliation', 'political_opinion'], ['political_party', 'political_opinion'],
    ['union_membership', 'union_membership'], ['trade_union', 'union_membership'],
    ['avatar', 'profile_photo'], ['avatar_url', 'profile_photo'], ['profile_photo_path', 'profile_photo'], ['profile_picture', 'profile_photo'],
    ['photo', null], ['picture', null], ['image', null],
    ['signature', null], ['signature_image', null],
    ['bio', null], ['notes', null], ['note', null], ['comment', null], ['about_me', null], ['description', null],

    // Contact
    ['email', 'email_address'], ['e_mail', 'email_address'], ['mail', 'email_address'], ['courriel', 'email_address'], ['email2', 'email_address'],
    ['email_address', 'email_address'], ['user_email', 'email_address'], ['reply_to', 'email_address'], ['secondary_email', 'email_address'],
    ['emergency_contact_email', 'email_address'], ['email_verified_at', null], ['mailing_address', 'shipping_billing_address'],
    ['phone', 'phone_number'], ['phone_number', 'phone_number'], ['phone_no', 'phone_number'], ['tel', 'phone_number'], ['telephone', 'phone_number'],
    ['mobile', 'phone_number'], ['mobile_number', 'phone_number'], ['mobile_phone', 'phone_number'], ['home_phone', 'phone_number'],
    ['work_phone', 'phone_number'], ['cell', 'phone_number'], ['fax', 'phone_number'], ['fax_number', 'phone_number'], ['portable', 'phone_number'],
    ['msisdn', 'phone_number'], ['contact_phone', 'phone_number'], ['phone_verified_at', null],
    ['username', 'username'], ['user_name', 'username'], ['login', 'username'], ['handle', 'username'], ['last_login', null],
    ['whatsapp', 'whatsapp_number'], ['whatsapp_number', 'whatsapp_number'],

    // Location
    ['address', 'street_address'], ['adresse', 'street_address'], ['street', 'street_address'], ['rue', 'street_address'],
    ['street_address', 'street_address'], ['street_name', 'street_address'], ['address1', 'street_address'], ['address_1', 'street_address'],
    ['address_line_1', 'street_address'], ['address_line1', 'street_address'], ['addr_line_2', 'street_address'],
    ['city', 'city'], ['town', 'city'], ['ville', 'city'], ['commune', 'city'], ['locality', 'city'],
    ['zip', 'postal_code'], ['zipcode', 'postal_code'], ['zip_code', 'postal_code'], ['postcode', 'postal_code'], ['post_code', 'postal_code'],
    ['postal_code', 'postal_code'], ['code_postal', 'postal_code'], ['plz', 'postal_code'],
    ['country', 'country'], ['country_code', 'country'], ['pays', 'country'], ['country_id', null],
    ['lat', 'latitude'], ['latitude', 'latitude'], ['lng', 'longitude'], ['lon', 'longitude'], ['longitude', 'longitude'],
    ['building_number', 'building_number'], ['house_number', 'building_number'],
    ['province', 'state_province'], ['bundesland', 'state_province'], ['state_name', 'state_province'], ['billing_state', 'state_province'],
    ['shipping_region', 'state_province'], ['state', null], ['region', null], ['status', null],
    ['state_abbr', 'state_abbreviation'], ['state_abbreviation', 'state_abbreviation'], ['state_code', 'state_abbreviation'],
    ['province_code', 'state_abbreviation'],
    ['timezone', null], ['time_zone', null], ['tz', null],
    ['billing_address', 'shipping_billing_address'], ['shipping_address', 'shipping_billing_address'], ['delivery_address', 'shipping_billing_address'],
    ['invoice_address', 'shipping_billing_address'], ['home_address', 'shipping_billing_address'], ['postal_address', 'shipping_billing_address'],

    // Financial
    ['credit_card', 'credit_card'], ['credit_card_number', 'credit_card'], ['card_number', 'credit_card'], ['card_no', 'credit_card'],
    ['cc_number', 'credit_card'], ['cc_num', 'credit_card'], ['payment_card', 'credit_card'], ['pan', 'credit_card'],
    ['card_holder', 'card_holder_name'], ['cardholder_name', 'card_holder_name'], ['card_holder_name', 'card_holder_name'],
    ['name_on_card', 'card_holder_name'],
    ['iban', 'iban'], ['bank_account', 'iban'],
    ['routing_number', 'bank_routing_number'], ['aba_number', 'bank_routing_number'], ['bank_code', 'bank_routing_number'],
    ['sort_code', 'sort_code'], ['sortcode', 'sort_code'],
    ['bic', 'swift_bic'], ['swift', 'swift_bic'], ['swift_code', 'swift_bic'], ['bic_code', 'swift_bic'], ['swift_bic', 'swift_bic'],
    ['wallet_address', 'crypto_wallet'], ['eth_address', 'crypto_wallet'],
    ['salary', 'salary'], ['salaire', 'salary'], ['income', 'salary'], ['annual_pay', 'salary'],
    ['hourly_rate', null], ['hourly_wage', null],
    ['cvv', 'cvv_security_code'], ['cvc', 'cvv_security_code'], ['card_cvv', 'cvv_security_code'], ['security_code', 'cvv_security_code'],
    ['card_expiry', 'card_expiry'], ['card_expiration', 'card_expiry'], ['card_exp', 'card_expiry'], ['card_expiry_date', 'card_expiry'],
    ['card_expiration_date', 'card_expiry'], ['cc_exp', 'card_expiry'], ['cc_exp_date', 'card_expiry'],
    ['expiration_date', null], ['expiry_date', null], ['exp_date', null], ['expires_at', null],
    ['exp_month', 'card_expiry_month'], ['card_exp_month', 'card_expiry_month'], ['expiry_month', 'card_expiry_month'],
    ['exp_year', 'card_expiry_year'], ['card_exp_year', 'card_expiry_year'], ['cc_expiration_year', 'card_expiry_year'],
    ['account_number', 'bank_account_number'], ['bank_account_number', 'bank_account_number'], ['acct_no', 'bank_account_number'],
    ['credit_score', 'credit_score'],
    ['stripe_id', 'payment_provider_id'], ['stripe_customer_id', 'payment_provider_id'], ['paypal_payer_id', 'payment_provider_id'],

    // Medical and biometric
    ['mrn', 'medical_record_id'], ['medical_record_number', 'medical_record_id'],
    ['insurance_id', 'health_insurance_id'], ['insurance_number', 'health_insurance_id'], ['policy_number', 'health_insurance_id'],
    ['member_id', null], ['health_plan', null],
    ['patient_id', 'patient_id'], ['diagnosis', 'diagnosis'], ['medical_condition', 'diagnosis'], ['condition', null],
    ['blood_type', 'blood_type'], ['allergies', 'allergies'], ['medication', 'medication'], ['disability', 'disability_status'],
    ['fingerprint', 'biometric_data'], ['face_id', 'biometric_data'], ['biometric', 'biometric_data'], ['dna', 'dna_profile'],

    // Professional
    ['company', 'company_name'], ['company_name', 'company_name'], ['employer', 'company_name'], ['organization', 'company_name'],
    ['societe', 'company_name'], ['entreprise', 'company_name'],
    ['job_title', 'job_title'], ['job_position', 'job_title'], ['occupation', 'job_title'], ['profession', 'job_title'], ['poste', 'job_title'],
    ['fonction', 'job_title'], ['title', null], ['position', null], ['role', null],
    ['employee_id', null], ['staff_id', null], ['manager_name', 'manager_name'],
    ['siren', 'siren_number'], ['numero_siren', 'siren_number'], ['siret', 'siret_number'], ['numero_siret', 'siret_number'],

    // Digital identity
    ['ip', 'ip_address'], ['ip_address', 'ip_address'], ['client_ip', 'ip_address'], ['last_login_ip', 'ip_address'], ['registration_ip', 'ip_address'],
    ['last_ip_address', 'ip_address'], ['zip_code', 'postal_code'], ['is_vip', null], ['tooltip', null], ['membership', null],
    ['device_id', 'device_id'], ['udid', 'device_id'], ['device_token', 'device_id'],
    ['imei', 'imei'], ['imei_number', 'imei'],
    ['session_id', 'session_id'], ['session_token', 'session_id'],
    ['mac_address', 'mac_address'], ['hardware_address', 'mac_address'], ['physical_address', null],
    ['user_agent', 'user_agent'], ['browser', null], ['http_user_agent', 'user_agent'], ['client_info', null],
    ['advertising_id', 'advertising_id'], ['idfa', 'advertising_id'], ['ad_id', null],
    ['cookie_id', 'cookie_id'], ['tracking_id', null], ['visitor_id', null],

    // Authentication
    ['password', 'password'], ['passwd', 'password'], ['pwd', 'password'], ['password_hash', 'password'], ['hashed_password', 'password'],
    ['encrypted_password', 'password'], ['password_digest', 'password'], ['mot_de_passe', 'password'], ['mdp', 'password'],
    ['password_reset_at', null], ['password_changed_at', null],
    ['access_token', 'oauth_token'], ['refresh_token', 'oauth_token'], ['oauth_token', 'oauth_token'], ['auth_token', 'oauth_token'],
    ['api_key', 'api_token'], ['api_token', 'api_token'], ['api_secret', 'api_token'], ['client_secret', 'api_token'],
    ['remember_token', 'api_token'], ['personal_access_token', 'api_token'], ['token', null],
    ['private_key', 'private_key'], ['secret_key', 'private_key'], ['encryption_key', 'private_key'], ['secret', 'private_key'],
    ['pin', 'pin_code'], ['pin_code', 'pin_code'],
    ['two_factor_secret', 'two_factor_secret'], ['totp_secret', 'two_factor_secret'], ['otp_secret', 'two_factor_secret'],
    ['two_factor_recovery_codes', 'two_factor_secret'], ['recovery_codes', 'two_factor_secret'], ['backup_codes', 'two_factor_secret'],
    ['two_factor_confirmed_at', null],
    ['security_answer', 'security_answer'], ['secret_answer', 'security_answer'],

    // Relationships, social media, vehicle
    ['emergency_contact', 'emergency_contact_name'], ['emergency_contact_name', 'emergency_contact_name'],
    ['emergency_contact_phone', 'emergency_contact_phone'], ['emergency_phone', 'emergency_contact_phone'],
    ['next_of_kin', 'next_of_kin'], ['spouse_name', 'next_of_kin'],
    ['twitter', 'social_handle'], ['twitter_handle', 'social_handle'], ['twitter_username', 'social_handle'], ['instagram', 'social_handle'],
    ['github_username', 'social_handle'], ['linkedin', 'social_handle'], ['skype_id', 'social_handle'], ['discord_tag', 'social_handle'],
    ['github_id', null], ['facebook_id', null],
    ['linkedin_url', 'social_profile_url'], ['facebook_url', 'social_profile_url'], ['twitter_url', 'social_profile_url'],
    ['github_url', 'social_profile_url'], ['profile_url', 'social_profile_url'], ['website', 'social_profile_url'],
    ['license_plate', 'license_plate'], ['plate_number', 'license_plate'], ['immatriculation', 'license_plate'],
    ['vin', 'vin_number'], ['vin_number', 'vin_number'],

    // Common columns that carry no personal data
    ['id', null], ['user_id', null], ['created_at', null], ['updated_at', null], ['deleted_at', null], ['amount', null], ['price', null],
    ['quantity', null], ['slug', null], ['type', null], ['uuid', null], ['sku', null], ['is_active', null], ['sort_order', null],
]);

it('writes the null strategy with literal quotes so clonio generates a string', function () {
    $content = file_get_contents(resource_path('app/clonio.pii-matchers.yaml'));
    $nulls = array_filter(piiMatchers(), fn (array $matcher): bool => $matcher['transformation']['strategy'] === '"null"');

    expect($content)->not->toContain("strategy: 'null'")
        ->not->toContain('strategy: null')
        ->not->toContain('strategy: ~')
        ->and($nulls)->toHaveCount(17)
        ->and(substr_count($content, "{ strategy: '\"null\"' }"))->toBe(17);
});

it('reads the quoted null strategy of a matcher as the null strategy', function () {
    $catalog = PiiMatcherCatalog::fromRaw(piiMatchersFile());
    $match = $catalog->match('session_id');

    expect($match->matcher->transformation)->toBe(['strategy' => 'null'])
        ->and($match->toArray()['suggestedTransformation'])->toBe(['strategy' => 'null']);
});

it('accepts every way of writing the null strategy in a matcher', function (mixed $strategy) {
    $catalog = PiiMatcherCatalog::fromRaw(['groups' => ['digital_identity' => ['matchers' => ['session_id' => [
        'name' => 'Session ID',
        'sensitivity' => 'high',
        'patterns' => ['session_id'],
        'transformation' => ['strategy' => $strategy],
    ]]]]]);

    expect($catalog->match('session_id')->matcher->transformation['strategy'])->toBe('null');
})->with([
    'quoted for clonio' => ['"null"'],
    'plain string' => ['null'],
    'yaml null' => [null],
]);

it('has no disabled matcher', function () {
    $disabled = array_keys(array_filter(piiMatchers(), fn (array $matcher): bool => $matcher['enabled'] !== true));

    expect($disabled)->toBe([]);
});

it('never leaves a critical secret matcher disabled', function () {
    $matchers = piiMatchers();

    foreach (['password', 'oauth_token', 'api_token', 'private_key', 'pin_code', 'two_factor_secret', 'security_answer', 'cvv_security_code'] as $key) {
        expect($matchers[$key]['enabled'])->toBeTrue("Matcher [{$key}]");
    }
});

it('translates every matcher and group', function (string $locale) {
    foreach (piiMatchersFile()['groups'] as $groupKey => $group) {
        expect(Lang::has("cloning.groups.{$groupKey}", $locale, false))->toBeTrue("Group [{$groupKey}] in [{$locale}]");

        foreach (array_keys($group['matchers']) as $key) {
            expect(Lang::has("cloning.matchers.{$key}", $locale, false))->toBeTrue("Matcher [{$key}] in [{$locale}]");
        }
    }
})->with(['fr', 'en']);
