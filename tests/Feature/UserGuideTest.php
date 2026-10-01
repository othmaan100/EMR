<?php

namespace Tests\Feature;

use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class UserGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_guide_is_a_valid_word_document_with_generated_tables(): void
    {
        $this->markInstalled();
        $path = tempnam(sys_get_temp_dir(), 'guide').'.docx';

        $this->artisan('emr:user-guide', ['--output' => $path])->assertSuccessful();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/\.(xml|rels)$/', $name)) {
                $this->assertTrue((new DOMDocument)->loadXML($zip->getFromIndex($i)), "{$name} must be well-formed XML");
            }
        }

        $text = strip_tags(str_replace('</w:p>', "\n", $zip->getFromName('word/document.xml')));
        $this->assertStringContainsString('User & Administrator Guide', html_entity_decode($text));
        $this->assertStringContainsString('Storekeeper', $text);               // role summary / matrix
        $this->assertStringContainsString('Unpaid claims ageing', $text);      // generated reports table
        $this->assertStringContainsString('Patients', $text);                  // generated import table
        $this->assertStringNotContainsString('{{', $text);                     // every placeholder replaced
        @unlink($path);
    }
}
