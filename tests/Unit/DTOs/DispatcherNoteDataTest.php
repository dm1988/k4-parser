<?php

namespace Tests\Unit\DTOs;

use App\DTOs\DispatcherNoteData;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DispatcherNoteDataTest extends TestCase
{
    #[Test]
    public function it_normalizes_and_serializes_note_text(): void
    {
        $note = new DispatcherNoteData("  First line\r\nSecond line  ");

        $this->assertSame("First line\nSecond line", $note->text);
        $this->assertSame(['text' => "First line\nSecond line"], $note->toArray());
        $this->assertSame($note->toArray(), $note->jsonSerialize());
        $this->assertSame($note->toArray(), DispatcherNoteData::fromArray($note->toArray())?->toArray());
        $this->assertNull(DispatcherNoteData::fromArray(['text' => '   ']));
        $this->assertNull(DispatcherNoteData::fromArray(['text' => 42]));
    }

    #[Test]
    public function it_rejects_empty_note_text(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DispatcherNoteData('   ');
    }
}
