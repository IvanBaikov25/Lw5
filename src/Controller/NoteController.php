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
#[OA\Tag(name: 'Notes', description: 'Управление заметками')]
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
        tags: ['Notes'],
        parameters: [
            new OA\QueryParameter(name: 'search', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'sort', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'order', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список заметок',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'title', type: 'string', example: 'Заголовок'),
                            new OA\Property(property: 'content', type: 'string', example: 'Текст заметки'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2023-10-01T12:00:00+00:00'),
                        ]
                    )
                )
            )
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
        tags: ['Notes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Заметка найдена',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'title', type: 'string', example: 'Заголовок'),
                        new OA\Property(property: 'content', type: 'string', example: 'Текст заметки'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2023-10-01T12:00:00+00:00'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Заметка не найдена',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Note not found')
                    ]
                )
            )
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
        tags: ['Notes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['title', 'content'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Новая заметка'),
                    new OA\Property(property: 'content', type: 'string', example: 'Текст заметки'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Заметка создана',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'title', type: 'string', example: 'Новая заметка'),
                        new OA\Property(property: 'content', type: 'string', example: 'Текст заметки'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2023-10-01T12:00:00+00:00'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'errors', type: 'string', example: 'title: Это значение не должно быть пустым.')
                    ]
                )
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $note = $this->serializer->deserialize($request->getContent(), Note::class, 'json');
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
        tags: ['Notes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Обновленный заголовок'),
                    new OA\Property(property: 'content', type: 'string', example: 'Обновленный текст'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Заметка обновлена',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'title', type: 'string', example: 'Обновленный заголовок'),
                        new OA\Property(property: 'content', type: 'string', example: 'Обновленный текст'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2023-10-01T12:00:00+00:00'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Заметка не найдена',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Note not found')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'errors', type: 'string', example: 'title: Это значение не должно быть пустым.')
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $note = $this->noteRepository->find($id);
        if (!$note) {
            throw new NotFoundHttpException('Note not found');
        }
        $this->serializer->deserialize($request->getContent(), Note::class, 'json', ['object_to_populate' => $note]);
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
        tags: ['Notes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Заметка удалена'
            ),
            new OA\Response(
                response: 404,
                description: 'Заметка не найдена',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Note not found')
                    ]
                )
            )
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