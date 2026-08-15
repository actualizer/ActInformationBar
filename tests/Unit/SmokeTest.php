<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit;

use Act\InformationBar\ActInformationBar;
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testPluginClassIsAutoloadable(): void
    {
        self::assertTrue(class_exists(ActInformationBar::class));
    }
}
