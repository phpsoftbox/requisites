<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Tests\Storage;

use PhpSoftBox\Requisites\Contract\StorageAdapterInterface;
use PhpSoftBox\Requisites\DTO\RequisitesRecord;
use PhpSoftBox\Requisites\DTO\RequisitesSubject;
use PhpSoftBox\Requisites\Storage\ProfileRouterStorageAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProfileRouterStorageAdapter::class)]
final class ProfileRouterStorageAdapterTest extends TestCase
{
    #[Test]
    public function routesFindAndCreateByProfile(): void
    {
        $companyStorage = $this->createMock(StorageAdapterInterface::class);
        $personStorage  = $this->createMock(StorageAdapterInterface::class);
        $subject        = new RequisitesSubject('company', 10);
        $record         = new RequisitesRecord('company', 'default', 1, 'company', 10);

        $companyStorage->expects($this->once())->method('find')->with($subject, 'company')->willReturn($record);
        $companyStorage->expects($this->once())->method('create')->with($subject, 'company')->willReturn($record);
        $personStorage->expects($this->never())->method('find');
        $personStorage->expects($this->never())->method('create');

        $router = new ProfileRouterStorageAdapter(
            adaptersByProfile: [
                'company' => $companyStorage,
                'person'  => $personStorage,
            ],
            defaultProfile: 'company',
        );

        self::assertSame($record, $router->find($subject, 'company'));
        self::assertSame($record, $router->create($subject, 'company'));
    }

    #[Test]
    public function routesSaveByRecordProfileAndUsesDefaultFallback(): void
    {
        $companyStorage = $this->createMock(StorageAdapterInterface::class);
        $personStorage  = $this->createMock(StorageAdapterInterface::class);
        $record         = new RequisitesRecord('person', 'default', 1, 'person', 7);
        $subject        = new RequisitesSubject('unknown', 8);

        $personStorage->expects($this->once())->method('save')->with($record);
        $companyStorage->expects($this->once())->method('find')->with($subject, 'unknown')->willReturn(null);

        $router = new ProfileRouterStorageAdapter(
            adaptersByProfile: [
                'company' => $companyStorage,
                'person'  => $personStorage,
            ],
            defaultProfile: 'company',
        );

        $router->save($record);
        self::assertNull($router->find($subject, 'unknown'));
    }
}
