<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Recipe;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

final class RecipeTest extends TestCase
{
    public function testGettersSetters(): void
    {
        $recipe = new Recipe();

        $this->assertNull($recipe->getId());
        $this->assertSame('', $recipe->getTitle());
        $this->assertNull($recipe->getSlug());
        $this->assertSame('', $recipe->getContent());
        $this->assertNull($recipe->getCreatedAt());
        $this->assertNull($recipe->getUpdatedAt());
        $this->assertNull($recipe->getDuration());
        $this->assertNull($recipe->getCategory());
        $this->assertNull($recipe->getThumbnail());
        $this->assertNull($recipe->getThumbnailFile());

        $now = new \DateTimeImmutable();
        $cat = new Category();

        $recipe
            ->setTitle('Cake')
            ->setSlug('cake')
            ->setContent('Yum')
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setDuration(30)
            ->setCategory($cat)
            ->setThumbnail('a.jpg');

        $this->assertSame('Cake', $recipe->getTitle());
        $this->assertSame('cake', $recipe->getSlug());
        $this->assertSame('Yum', $recipe->getContent());
        $this->assertSame($now, $recipe->getCreatedAt());
        $this->assertSame($now, $recipe->getUpdatedAt());
        $this->assertSame(30, $recipe->getDuration());
        $this->assertSame($cat, $recipe->getCategory());
        $this->assertSame('a.jpg', $recipe->getThumbnail());

        $recipe->setDuration(null)->setCategory(null)->setThumbnail(null)->setSlug(null);
        $this->assertNull($recipe->getDuration());
        $this->assertNull($recipe->getCategory());
        $this->assertNull($recipe->getThumbnail());
        $this->assertNull($recipe->getSlug());

        $tmp = tempnam(sys_get_temp_dir(), 'thumb');
        file_put_contents($tmp, 'x');
        $file = new File($tmp);
        $recipe->setThumbnailFile($file);
        $this->assertSame($file, $recipe->getThumbnailFile());
    }
}




