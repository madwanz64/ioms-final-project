<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Category;
use App\Entity\Warehouse;
use App\Service\CategoryService;
use App\Service\PartyService;
use App\Service\ValidationException;
use App\Service\WarehouseService;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryCategoryRepository;
use Tests\Fake\InMemoryPartyRepository;
use Tests\Fake\InMemoryWarehouseRepository;

/**
 * Area logic: aturan nama unik pada master data kategori & gudang, dan validasi supplier/customer.
 */
final class MasterDataServiceTest extends TestCase
{
    public function testCategoryNameMustBeUniqueCaseInsensitive(): void
    {
        $repo = new InMemoryCategoryRepository();
        $repo->add(new Category(1, 'Elektronik', null));

        $this->expectExceptionObject(new ValidationException(['name' => 'Nama kategori sudah dipakai.']));

        (new CategoryService($repo))->create(['name' => '  elektronik ', 'description' => '']);
    }

    public function testCategoryCanBeSavedWithItsOwnNameAndEmptyDescriptionBecomesNull(): void
    {
        $repo = new InMemoryCategoryRepository();
        $repo->add(new Category(1, 'Elektronik', 'lama'));
        $service = new CategoryService($repo);

        $updated = $service->update(new Category(1, 'Elektronik', 'lama'), ['name' => 'Elektronik', 'description' => '']);

        self::assertNull($updated->description);
    }

    public function testWarehouseRequiresNameLocationAndValidStatus(): void
    {
        try {
            (new WarehouseService(new InMemoryWarehouseRepository()))->create(['name' => '', 'location' => '', 'active' => 'ya']);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertSame(['name', 'location', 'active'], array_keys($e->errors));
        }
    }

    public function testWarehouseNameMustBeUniqueButRenameToFreeNameWorks(): void
    {
        $repo = new InMemoryWarehouseRepository();
        $repo->add(new Warehouse(1, 'Gudang Jakarta', 'Jakarta', true));
        $repo->add(new Warehouse(2, 'Gudang Surabaya', 'Surabaya', true));
        $service = new WarehouseService($repo);

        try {
            $service->update(new Warehouse(2, 'Gudang Surabaya', 'Surabaya', true), ['name' => 'GUDANG JAKARTA', 'location' => 'Surabaya', 'active' => '1']);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertSame('Nama gudang sudah dipakai.', $e->errors['name']);
        }

        $renamed = $service->update(new Warehouse(2, 'Gudang Surabaya', 'Surabaya', true), ['name' => 'Gudang Sidoarjo', 'location' => 'Sidoarjo', 'active' => '0']);
        self::assertSame('Gudang Sidoarjo', $renamed->name);
        self::assertFalse($renamed->active);
    }

    public function testPartyRequiresNameContactAddressWithinLengthAndValidStatus(): void
    {
        $repo = new InMemoryPartyRepository();

        try {
            (new PartyService($repo))->create(['name' => str_repeat('a', 151), 'contact' => ' ', 'address' => '', 'active' => '2']);
            self::fail('Input supplier/customer tidak valid harus ditolak.');
        } catch (ValidationException $e) {
            self::assertSame([
                'name' => 'Nama maksimal 150 karakter.',
                'contact' => 'Kontak wajib diisi.',
                'address' => 'Alamat wajib diisi.',
                'active' => 'Status tidak valid.',
            ], $e->errors);
        }
        self::assertSame([], $repo->all(), 'Tidak ada yang tersimpan bila validasi gagal.');
    }

    public function testPartyIsCreatedTrimmedAndCanBeDeactivated(): void
    {
        $service = new PartyService(new InMemoryPartyRepository());

        $created = $service->create(['name' => ' PT Sumber ', 'contact' => '021-1', 'address' => 'Jakarta', 'active' => '1']);
        $updated = $service->update($created, ['name' => 'PT Sumber Jaya', 'contact' => '021-2', 'address' => 'Bekasi', 'active' => '0']);

        self::assertSame('PT Sumber', $created->name);
        self::assertGreaterThan(0, $created->id);
        self::assertSame([$created->id, 'PT Sumber Jaya', false], [$updated->id, $updated->name, $updated->active]);
        self::assertEquals($updated, $service->find($created->id));
        self::assertCount(1, $service->all());
    }
}
