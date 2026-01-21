<?php

namespace App\Tests\Normalizer;

use App\Entity\Recipe;
use App\Normalizer\PaginationNormalizer;
use Knp\Component\Pager\Pagination\PaginationInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class PaginationNormalizerTest extends TestCase
{
    public function testSupportsNormalization(): void
    {
        $inner = $this->createMock(NormalizerInterface::class);
        $normalizer = new PaginationNormalizer($inner);

        $this->assertTrue($normalizer->supportsNormalization($this->createMock(PaginationInterface::class)));
        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes(): void
    {
        $inner = $this->createMock(NormalizerInterface::class);
        $normalizer = new PaginationNormalizer($inner);

        $types = $normalizer->getSupportedTypes(null);
        $this->assertSame([PaginationInterface::class => true], $types);
    }

    public function testNormalizeThrowsWhenNotPagination(): void
    {
        $inner = $this->createMock(NormalizerInterface::class);
        $normalizer = new PaginationNormalizer($inner);

        $this->expectException(\RuntimeException::class);
        $normalizer->normalize(new \stdClass());
    }

    public function testNormalizeMapsItemsAndMeta(): void
    {
        $r1 = new Recipe();
        $r1->setTitle('A');
        $r2 = new Recipe();
        $r2->setTitle('B');

        $pagination = $this->createMock(PaginationInterface::class);
        $pagination->method('getItems')->willReturn([$r1, $r2]);
        $pagination->method('getTotalItemCount')->willReturn(21);
        $pagination->method('getCurrentPageNumber')->willReturn(2);
        $pagination->method('getItemNumberPerPage')->willReturn(10);

        $inner = $this->createMock(NormalizerInterface::class);
        $inner->expects($this->exactly(2))
            ->method('normalize')
            ->with($this->isInstanceOf(Recipe::class), 'json', ['x' => 'y'])
            ->willReturnOnConsecutiveCalls(['t' => 'A'], ['t' => 'B']);

        $normalizer = new PaginationNormalizer($inner);
        $out = $normalizer->normalize($pagination, 'json', ['x' => 'y']);

        $this->assertSame(
            [
                'items' => [['t' => 'A'], ['t' => 'B']],
                'total' => 21,
                'page' => 2,
                'lastPage' => 3.0,
            ],
            $out
        );
    }
}




