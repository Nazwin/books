<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Controller\BookController;
use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

const BOOK_SCHEMA = [
    'type' => 'object',
    'properties' => [
        'title' => ['type' => 'string'],
        'description' => ['type' => 'string'],
        'image' => ['type' => 'string', 'format' => 'base64'],
        'authors' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'minItems' => 1,
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'surname' => ['type' => 'string'],
                    'patronymic' => ['type' => 'string'],
                ],
                'required' => ['name', 'surname'],
            ]
        ],
        'published_at' => ['type' => 'string', 'format' => 'date-time'],
    ],
    'required' => ['title', 'image', 'authors', 'published_at'],
];

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            controller: BookController::class,
            openapiContext: [
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'schema' => BOOK_SCHEMA
                        ],
                    ],
                ],
            ],
            validationContext: ['groups' => ['book:create']],
            deserialize: false,
        ),
        new Put(
            controller: BookController::class,
            openapiContext: [
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'schema' => BOOK_SCHEMA
                        ],
                    ],
                ],
            ],
            validationContext: ['groups' => ['book:update']],
            deserialize: false,
        ),
        new Delete,
    ],
    normalizationContext: ['groups' => ['book:read']],
    denormalizationContext: ['groups' => ['book:write']],
)]
#[ORM\HasLifecycleCallbacks]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['book:read', 'author:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['book:read', 'book:write', 'author:read'])]
    #[NotBlank]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['book:read', 'book:write', 'author:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[NotBlank]
    #[Groups(['book:read', 'book:write', 'author:read'])]
    private ?string $image = null;

    #[ORM\ManyToMany(targetEntity: Author::class)]
    #[ORM\JoinTable(name: 'book_author')]
    #[Groups(['book:read', 'book:write'])]
    #[NotBlank]
    #[Count(min: 1, minMessage: 'At least one author must be specified')]
    private Collection $authors;

    #[ORM\Column]
    #[Groups(['book:read', 'book:write', 'author:read'])]
    #[NotBlank]
    private ?\DateTimeImmutable $published_at = null;

    #[ORM\Column]
    #[Groups(['book:read', 'author:read'])]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    #[Groups(['book:read', 'author:read'])]
    private ?\DateTimeImmutable $updated_at = null;

    public function __construct()
    {
        $this->authors = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(Author $author): static
    {
        if (!$this->authors->contains($author)) {
            $this->authors[] = $author;
        }

        return $this;
    }

    public function removeAuthor(Author $author): static
    {
        $this->authors->removeElement($author);

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->published_at;
    }

    public function setPublishedAt(\DateTimeImmutable $published_at): static
    {
        $this->published_at = $published_at;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->created_at = new \DateTimeImmutable();
        $this->setUpdatedAtValue();
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updated_at = new \DateTimeImmutable();
    }
}
