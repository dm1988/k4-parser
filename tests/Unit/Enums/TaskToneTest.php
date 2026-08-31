<?php

namespace Tests\Unit\Enums;

use App\Enums\TaskTone;
use PHPUnit\Framework\TestCase;

class TaskToneTest extends TestCase
{
    public function test_it_exposes_badge_and_dot_classes_for_each_tone(): void
    {
        $this->assertSame(
            'bg-emerald-100 text-emerald-900 dark:bg-emerald-400/15 dark:text-emerald-200',
            TaskTone::Success->badgeColor(),
        );
        $this->assertSame('bg-emerald-500 dark:bg-emerald-400', TaskTone::Success->dotColor());
        $this->assertSame(
            'bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-200',
            TaskTone::Danger->badgeColor(),
        );
        $this->assertSame('bg-red-500 dark:bg-red-400', TaskTone::Danger->dotColor());
        $this->assertSame(
            'bg-amber-100 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200',
            TaskTone::Warning->badgeColor(),
        );
        $this->assertSame('bg-amber-400 dark:bg-amber-300', TaskTone::Warning->dotColor());
        $this->assertSame(
            'bg-[#1B365D]/10 text-[#1B365D] dark:bg-blue-400/15 dark:text-blue-200',
            TaskTone::Neutral->badgeColor(),
        );
        $this->assertSame('bg-[#1B365D] dark:bg-blue-300', TaskTone::Neutral->dotColor());
    }
}
