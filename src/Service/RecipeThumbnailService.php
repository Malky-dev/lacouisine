<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Recipe;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Handler\UploadHandler;

final readonly class RecipeThumbnailService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UploadHandler $uploadHandler,
    ) {
    }

    public function upload(Recipe $recipe, UploadedFile $file): void
    {
        $recipe
            ->setThumbnailFile($file)
            ->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function delete(Recipe $recipe): void
    {
        $this->uploadHandler->remove($recipe, 'thumbnailFile');
        $recipe
            ->setThumbnail(null)
            ->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();
    }
}
