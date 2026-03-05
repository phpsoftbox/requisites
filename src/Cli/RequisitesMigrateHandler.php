<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Requisites\Migration\BackfillMigrationRunner;
use Throwable;

use function is_int;
use function is_string;

final class RequisitesMigrateHandler implements HandlerInterface
{
    public function __construct(
        private readonly BackfillMigrationRunner $runnerService,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $profile = $runner->request()->option('profile');
        if (!is_string($profile) || $profile === '') {
            $runner->io()->writeln('Опция --profile обязательна.', 'error');

            return Response::FAILURE;
        }

        $selector = $runner->request()->option('selector');
        if (!is_string($selector) || $selector === '') {
            $selector = null;
        }

        $fromVersion = $runner->request()->option('from');
        if ($fromVersion !== null && !is_int($fromVersion)) {
            $runner->io()->writeln('Опция --from должна быть integer.', 'error');

            return Response::FAILURE;
        }

        $toVersion = $runner->request()->option('to');
        if ($toVersion !== null && !is_int($toVersion)) {
            $runner->io()->writeln('Опция --to должна быть integer.', 'error');

            return Response::FAILURE;
        }

        $batchSize = $runner->request()->option('batch-size', 100);
        if (!is_int($batchSize)) {
            $runner->io()->writeln('Опция --batch-size должна быть integer.', 'error');

            return Response::FAILURE;
        }

        $dryRun = (bool) $runner->request()->option('dry-run', false);

        try {
            $report = $this->runnerService->run(
                profile: $profile,
                selector: $selector,
                fromVersion: $fromVersion,
                toVersion: $toVersion,
                dryRun: $dryRun,
                batchSize: $batchSize,
            );
        } catch (Throwable $exception) {
            $runner->io()->writeln('Ошибка миграции: ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('Backfill payload-миграций завершен.');
        $runner->io()->writeln('Режим: ' . ($dryRun ? 'dry-run' : 'write'));
        $runner->io()->writeln('Обработано: ' . $report->processed);
        $runner->io()->writeln('Мигрировано: ' . $report->migrated);
        $runner->io()->writeln('Пропущено: ' . $report->skipped);
        $runner->io()->writeln('Ошибок: ' . $report->failed);

        foreach ($report->errors as $error) {
            $runner->io()->writeln(' - ' . $error, 'error');
        }

        return $report->hasFailures() ? Response::FAILURE : Response::SUCCESS;
    }
}
