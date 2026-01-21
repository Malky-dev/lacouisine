<?php

namespace App\Tests\DTO;

use App\DTO\CategoryWithCountDTO;
use App\DTO\ContactDTO;
use PHPUnit\Framework\TestCase;

final class DtoTest extends TestCase
{
    public function testCategoryWithCountDto(): void
    {
        $dto = new CategoryWithCountDTO(1, 'Cat', 5);
        $this->assertSame(1, $dto->id);
        $this->assertSame('Cat', $dto->name);
        $this->assertSame(5, $dto->recipeCount);
    }

    public function testContactDtoDefaultsAndAssignments(): void
    {
        $dto = new ContactDTO();
        $this->assertSame('', $dto->name);
        $this->assertSame('', $dto->object);
        $this->assertSame('', $dto->email);
        $this->assertSame('', $dto->message);
        $this->assertSame('', $dto->service);

        $dto->name = 'n';
        $dto->object = 'o';
        $dto->email = 'e@test.fr';
        $dto->message = 'm';
        $dto->service = 'support';

        $this->assertSame('n', $dto->name);
        $this->assertSame('o', $dto->object);
        $this->assertSame('e@test.fr', $dto->email);
        $this->assertSame('m', $dto->message);
        $this->assertSame('support', $dto->service);
    }
}




