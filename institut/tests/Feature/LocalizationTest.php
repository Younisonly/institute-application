<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    private const LANG_FILES = ['general', 'money_words', 'validation', 'auth', 'passwords', 'pagination'];

    public function test_strings_audit_command_finds_no_unlocalized_strings(): void
    {
        $exit = $this->artisan('strings:audit')->run();
        $this->assertSame(0, $exit);
    }

    public function test_published_filament_locale_files_were_not_blanked(): void
    {
        $pairs = [
            ['vendor/filament-panels/en/layout.php', 'vendor/filament-panels/ar/layout.php'],
            ['vendor/filament-panels/en/pages/auth/login.php', 'vendor/filament-panels/ar/pages/auth/login.php'],
            ['vendor/filament-notifications/en/database.php', 'vendor/filament-notifications/ar/database.php'],
        ];

        foreach ($pairs as [$en, $ar]) {
            $this->assertFileExists(lang_path($en), $en.' is missing');
            $this->assertFileExists(lang_path($ar), $ar.' is missing');
            $this->assertNotEmpty(require lang_path($en), $en.' is empty');
            $this->assertNotEmpty(require lang_path($ar), $ar.' is empty');
        }
    }

    public function test_en_and_ar_lang_files_have_matching_keys(): void
    {
        foreach (self::LANG_FILES as $file) {
            $en = require lang_path("en/{$file}.php");
            $ar = require lang_path("ar/{$file}.php");

            $this->assertSame(
                $this->flattenKeys($en),
                $this->flattenKeys($ar),
                "lang/en/{$file}.php and lang/ar/{$file}.php keys differ"
            );
        }
    }

    public function test_validation_attributes_cover_every_filament_field_name(): void
    {
        $fields = $this->collectFieldNames();

        $en = require lang_path('en/validation.php');
        $ar = require lang_path('ar/validation.php');
        $attrsEn = array_keys($en['attributes']);
        $attrsAr = array_keys($ar['attributes']);

        foreach ($fields as $field) {
            $this->assertContains($field, $attrsEn, "en attributes missing: {$field}");
            $this->assertContains($field, $attrsAr, "ar attributes missing: {$field}");
        }
    }

    public function test_localization_keys_resolve_in_ar_and_en(): void
    {
        $samples = [
            'general.journal_at_least_two_lines',
            'general.journal_not_balanced',
            'general.monthly_billing_processed',
            'money_words.hundreds_out_of_range',
            'validation.required',
            'validation.min.string',
        ];

        foreach ($samples as $key) {
            app()->setLocale('en');
            $en = __($key);
            app()->setLocale('ar');
            $ar = __($key);
            $this->assertNotSame($key, $en, "en has no translation for {$key}");
            $this->assertNotSame($key, $ar, "ar has no translation for {$key}");
            $this->assertNotEmpty($ar, "ar translation empty for {$key}");
        }
    }

    private function flattenKeys(array $arr, string $prefix = ''): array
    {
        $keys = [];
        foreach ($arr as $key => $value) {
            $full = $prefix === '' ? $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $full));
            } else {
                $keys[] = $full;
            }
        }
        sort($keys);

        return $keys;
    }

    private function collectFieldNames(): array
    {
        $names = [];
        $files = File::allFiles(app_path('Filament'));

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match_all("/(?:->name|::make)\('([a-z][a-z0-9_]*)'\)/", $content, $matches)) {
                array_push($names, ...$matches[1]);
            }
        }

        return array_values(array_unique($names));
    }
}