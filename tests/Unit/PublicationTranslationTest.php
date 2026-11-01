<?php

namespace Tests\Unit;

use App\Models\PublicationTranslation;
use Tests\TestCase;

class PublicationTranslationTest extends TestCase
{
    public function test_paragraphs_become_text_blocks(): void
    {
        $blocks = $this->translation("Первый абзац.\n\nВторой абзац.")->blocks();

        $this->assertCount(2, $blocks);
        $this->assertSame(['list' => false, 'lines' => [], 'text' => 'Первый абзац.'], $blocks[0]);
        $this->assertSame('Второй абзац.', $blocks[1]['text']);
    }

    public function test_lines_of_one_paragraph_are_joined(): void
    {
        $blocks = $this->translation("Первая строка\nвторая строка")->blocks();

        $this->assertCount(1, $blocks);
        $this->assertSame('Первая строка вторая строка', $blocks[0]['text']);
    }

    public function test_dash_lines_become_a_list(): void
    {
        $blocks = $this->translation("Абзац.\n\n- Вода — 2\n- Электричество — 1")->blocks();

        $this->assertCount(2, $blocks);
        $this->assertTrue($blocks[1]['list']);
        $this->assertSame(['Вода — 2', 'Электричество — 1'], $blocks[1]['lines']);
        $this->assertSame('', $blocks[1]['text']);
    }

    public function test_blank_paragraphs_are_dropped(): void
    {
        $blocks = $this->translation("\n\nАбзац.\n\n\n\n- Газ — 0\n\n")->blocks();

        $this->assertCount(2, $blocks);
        $this->assertSame('Абзац.', $blocks[0]['text']);
        $this->assertSame(['Газ — 0'], $blocks[1]['lines']);
    }

    public function test_empty_body_has_no_blocks(): void
    {
        $this->assertSame([], $this->translation('')->blocks());
        $this->assertSame([], $this->translation('   ')->blocks());
    }

    private function translation(string $body): PublicationTranslation
    {
        return new PublicationTranslation(['locale' => 'ru', 'title' => 'Заголовок', 'excerpt' => 'Описание', 'body' => $body]);
    }
}
