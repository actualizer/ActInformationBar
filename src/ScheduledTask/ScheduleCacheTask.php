<?php declare(strict_types=1);

namespace Act\InformationBar\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduleCacheTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'act_information_bar.schedule_cache';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY * 5;
    }
}
