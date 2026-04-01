<?php

namespace App\Controller;

use App\Entity\Note;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/notes')]
#[OA\Tag(name: 'Notes', description: 'API для управления заметками')]
class NoteController extends AbstractController
{
    public function __construct(
        private NoteRepository $noteRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private SerializerInterface $serializer
    ) {}

    #[Route('', name: 'api_notes_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Список заметок',
        parameters: [
            new OA\QueryParameter(name: 'search', description: 'Поиск по заголовку или содержимому', required: false),
            new OA\QueryParameter(name: 'sort', description: 'Поле для сортировки (createdAt, title)', required: false),
            new OA\QueryParameter(name: 'order', description: 'Порядок (asc, desc)', required: false),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Успешный список'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $search = $request->query->get('search');
        $sort = $request->query->get('sort', 'createdAt');
        $order = $request->query->get('order', 'desc');

        $notes = $this->noteRepository->findWithFilters($search, $sort, $order);

        return $this->json($notes, Response::HTTP_OK, [], ['groups' => 'note:read']);
    }

    #[Route('/{id}', name: 'api_notes_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Одна заметка',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Заметка найдена'),
            new OA\Response(response: 404, description: 'Заметка не найдена'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $note = $this->noteRepository->find($id);

        if (!$note) {
            throw new NotFoundHttpException('Note not found');
        }

        return $this->json($note, Response::HTTP_OK, [], ['groups' => 'note:read']);
    }

    #[Route('', name: 'api_notes_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Создать заметку',
        requestBody: new OA\RequestBody(
            description: 'Данные заметки',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'title', type: 'string', example: 'Моя заметка'),
                        new OA\Property(property: 'content', type: 'string', example: 'Текст заметки'),
                    ],
                    required: ['title', 'content']
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Заметка создана'),
            new OA\Response(response: 400, description: 'Ошибка валидации'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $note = $this->serializer->deserialize($request->getContent(), Note::class, 'json');
        
        // Валидация
        $errors = $this->validator->validate($note, null, ['create']);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return $this->json($note, Response::HTTP_CREATED, [], ['groups' => 'note:read']);
    }

    #[Route('/{id}', name: 'api_notes_update', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Обновить заметку',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            description: 'Данные для обновления',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'content', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Заметка обновлена'),
            new OA\Response(response: 404, description: 'Заметка не найдена'),
            new OA\Response(response: 400, description: 'Ошибка валидации'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $note = $this->noteRepository->find($id);
        if (!$note) {
            throw new NotFoundHttpException('Note not found');
        }

        $this->serializer->deserialize($request->getContent(), Note::class, 'json', [
            'object_to_populate' => $note,
            'ignore_extra_attributes' => true,
        ]);

        $errors = $this->validator->validate($note, null, ['update']);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($note, Response::HTTP_OK, [], ['groups' => 'note:read']);
    }

    #[Route('/{id}', name: 'api_notes_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Удалить заметку',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Заметка удалена'),
            new OA\Response(response: 404, description: 'Заметка не найдена'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $note = $this->noteRepository->find($id);
        if (!$note) {
            throw new NotFoundHttpException('Note not found');
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}