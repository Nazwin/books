<?php

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface     $validator,
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        try {
            if ($request->isMethod('POST')) {
                return $this->create($request);
            }

            if ($request->isMethod('PUT')) {
                $id = $request->attributes->get('id');
                $book = $this->entityManager->getRepository(Book::class)->find($id);
                if (!$book) {
                    return new JsonResponse(['error' => 'Book not found'], Response::HTTP_NOT_FOUND);
                }

                return $this->update($request, $book);
            }
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['error' => 'Invalid method'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @throws Exception
     */
    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $book = new Book();
        $book->setTitle($data['title']);
        $book->setDescription($data['description']);
        $this->handleImageUpload($data['image'], $book);
        $this->handleAuthors($data['authors'], $book);
        $book->setPublishedAt(new \DateTimeImmutable($data['published_at']));

        $errors = $this->validator->validate($book, null, ['book:create']);

        if (count($errors) > 0) {
            return new JsonResponse($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return new JsonResponse($book, Response::HTTP_CREATED);
    }

    /**
     * @throws Exception
     */
    public function update(Request $request, Book $book): Response
    {
        $data = json_decode($request->getContent(), true);

        $book->setTitle($data['title']);
        $book->setDescription($data['description']);
        $book->setPublishedAt(new \DateTimeImmutable($data['published_at']));

        $image = $book->getImage();
        $oldImage = null;
        if ($data['image'] !== $image) {
            $oldImage = $image;
            $this->handleImageUpload($data['image'], $book);
        }

        $errors = $this->validator->validate($book, null, ['book:update']);

        if (count($errors) > 0) {
            return new JsonResponse($errors, Response::HTTP_BAD_REQUEST);
        }

        if ($oldImage) {
            unlink($this->getParameter('images_directory') . '/' . $oldImage);
        }

        $this->entityManager->flush();

        return new JsonResponse($book, Response::HTTP_OK);
    }

    private function handleImageUpload($base64Image, $book): void
    {
        if ($base64Image) {
            $imageData = $this->decodeBase64Image($base64Image);
            if ($imageData === null) {
                throw new BadRequestHttpException('Invalid base64 image data');
            }

            if (strlen($imageData['data']) > 2 * 1024 * 1024) {
                throw new BadRequestHttpException('Image size exceeds the limit of 2MB');
            }

            $imagesDirectory = $this->getParameter('images_directory');
            if (!is_dir($imagesDirectory)) {
                mkdir($imagesDirectory, 0777, true);
            }

            $newFilename = uniqid() . '.' . $imageData['extension'];

            $imagePath = $imagesDirectory . '/' . $newFilename;
            file_put_contents($imagePath, $imageData['data']);

            $book->setImage($newFilename);
        }
    }

    private function handleAuthors($authors, $book): void
    {
        if ($book->getId() !== null) {
            $book->getAuthors()->clear();
            foreach ($authors as $author) {
                if ($author['id'] !== null) {
                    $entityAuthor = $this->entityManager->getRepository(Author::class)->find($author['id']);
                } else {
                    $entityAuthor = $this->entityManager->getRepository(Author::class)->findOneBy([
                        'name' => $author['name'],
                        'surname' => $author['surname'],
                        'patronymic' => $author['patronymic'] ?? null,
                    ]);
                }
                if (!$entityAuthor) {
                    $entityAuthor = new Author();
                    $entityAuthor->setName($author['name']);
                    $entityAuthor->setSurname($author['surname']);
                    $entityAuthor->setPatronymic($author['patronymic'] ?? null);
                    $this->entityManager->persist($entityAuthor);
                }
                $book->addAuthor($entityAuthor);
            }
        } else {
            foreach ($authors as $author) {
                $entityAuthor = $this->entityManager->getRepository(Author::class)->findOneBy([
                    'name' => $author['name'],
                    'surname' => $author['surname'],
                    'patronymic' => $author['patronymic'] ?? null,
                ]);
                if (!$entityAuthor) {
                    $entityAuthor = new Author();
                    $entityAuthor->setName($author['name']);
                    $entityAuthor->setSurname($author['surname']);
                    $entityAuthor->setPatronymic($author['patronymic'] ?? null);
                    $this->entityManager->persist($entityAuthor);
                }
                $book->addAuthor($entityAuthor);
            }
        }
    }

    private function decodeBase64Image($base64Image): array|null
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $data = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]);

            if (!in_array($type, ['jpeg', 'jpg', 'png'])) {
                return null;
            }

            $data = base64_decode($data);

            if ($data === false) {
                return null;
            }

            return ['data' => $data, 'extension' => $type];
        }

        return null;
    }
}
