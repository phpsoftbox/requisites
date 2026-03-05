<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Cli;

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\CliApp\Command\CommandRegistryInterface;
use PhpSoftBox\CliApp\Command\OptionDefinition;
use PhpSoftBox\CliApp\Loader\CommandProviderInterface;

final class RequisitesCommandProvider implements CommandProviderInterface
{
    public function register(CommandRegistryInterface $registry): void
    {
        $registry->register(Command::define(
            name: 'requisites:migrate',
            description: 'Backfill payload-миграций реквизитов',
            signature: [
                new OptionDefinition(
                    name: 'profile',
                    short: 'p',
                    description: 'Профиль реквизитов',
                    required: true,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'selector',
                    short: 's',
                    description: 'Ограничить миграцию по selector',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'from',
                    short: 'f',
                    description: 'Нижняя граница schema_version',
                    required: false,
                    default: null,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'to',
                    short: 't',
                    description: 'Целевая schema_version (если не задано — из resolver)',
                    required: false,
                    default: null,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'batch-size',
                    short: 'b',
                    description: 'Размер батча для обработки',
                    required: false,
                    default: 100,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'dry-run',
                    short: 'd',
                    description: 'Показать план миграции без записи',
                    flag: true,
                ),
            ],
            handler: RequisitesMigrateHandler::class,
        ));
    }
}
