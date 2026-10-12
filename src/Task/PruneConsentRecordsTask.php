<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Task;

use SilverShop\CookieConsent\ConsentState;
use SilverShop\CookieConsent\Model\ConsentRecord;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Prune ConsentRecord rows older than the configured retention horizon
 * (ConsentState.log_retention_days). Disabled by default (0 = keep forever).
 *
 * Run via: vendor/bin/sake tasks:SilverShop-CookieConsent-PruneConsentRecords
 *
 * If symbiote/silverstripe-queuedjobs is installed you can wrap this in a
 * scheduled job; it intentionally has no hard dependency on it.
 */
class PruneConsentRecordsTask extends BuildTask
{
    protected static string $commandName = 'SilverShop-CookieConsent-PruneConsentRecords';

    protected static string $description = 'Delete consent log records older than the configured retention horizon';

    protected string $title = 'Cookie consent: prune consent records';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $days = (int) ConsentState::config()->get('log_retention_days');
        if ($days <= 0) {
            $output->writeln('Retention is disabled (log_retention_days <= 0); nothing to prune.');
            return Command::SUCCESS;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $stale = ConsentRecord::get()->filter('Created:LessThan', $cutoff);
        $count = (int) $stale->count();

        foreach ($stale as $record) {
            $record->delete();
        }

        $output->writeln(sprintf('Pruned %d consent record(s) older than %d days.', $count, $days));
        return Command::SUCCESS;
    }
}
