<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class ChatController extends AbstractController
{
    #[Route('/', name: 'chat')]
    public function index(ManagerRegistry $doctrine, Request $request): Response
    {
        $message = new Message();
        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        return $this->render('index.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/post/touser/{toUserId}', name: 'sendMessage')]
    public function post(ManagerRegistry $doctrine, Request $request, LoggerInterface $logger, $toUserId): JsonResponse
    {
        $message = new Message();
        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $repositoryUser = $doctrine->getRepository(User::class);
            $toUser = $repositoryUser->find($toUserId);
            $message = $form->getData();
            $message->setFromUser($this->getUser());
            $message->setToUser($toUser);

            $entityManager = $doctrine->getManager();
            $entityManager->persist($message);
            $entityManager->flush();

            // Publicar en Supabase Realtime via Broadcast
            $this->publishToSupabase($message, $logger);
        }

        return new JsonResponse([], Response::HTTP_OK);
    }

    #[Route('/messages/from/{toUserId}', name: 'messages-from')]
    public function messagesFrom(ManagerRegistry $doctrine, int $toUserId): Response
    {
        $repositoryUser = $doctrine->getRepository(User::class);
        $fromUser = $repositoryUser->find($toUserId);
        $repository = $doctrine->getRepository(Message::class);
        $messages = $repository->findByFromUser($this->getUser(), $fromUser);

        $data = [];
        foreach ($messages as $message) {
            $data[] = [
                'id'           => $message->getId(),
                'fromUserId'   => $message->getFromUser()->getId(),
                'toUserId'     => $message->getToUser()->getId(),
                'text'         => $message->getText(),
                'toUserName'   => $message->getToUser()->getUsername(),
                'fromUserName' => $message->getFromUser()->getUsername(),
                'timestamp'    => $message->getTimestamp(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    /**
     * Publica el mensaje en Supabase Realtime (Broadcast).
     * Cada usuario escucha su canal personal "user:{id}", así que el mensaje
     * se publica en el canal del emisor y en el del receptor. De este modo el
     * receptor se entera aunque tenga abierto otro chat (contador de no leídos).
     */
    private function publishToSupabase(Message $message, LoggerInterface $logger): void
    {
        $supabaseUrl = $_ENV['SUPABASE_URL'];
        $supabaseKey = $_ENV['SUPABASE_ANON_KEY'];

        $fromUser = $message->getFromUser();
        $toUser   = $message->getToUser();

        $data = [
            'id'           => $message->getId(),
            'fromUserId'   => $fromUser->getId(),
            'toUserId'     => $toUser->getId(),
            'fromUserName' => $fromUser->getUsername(),
            'toUserName'   => $toUser->getUsername(),
            'text'         => $message->getText(),
            'timestamp'    => ['date' => $message->getTimestamp()->format('Y-m-d H:i:s')],
            'type'         => 'chatmsg',
        ];

        // array_unique por si alguien se envía un mensaje a sí mismo
        $topics = array_unique(['user:' . $fromUser->getId(), 'user:' . $toUser->getId()]);

        $payload = ['messages' => []];
        foreach ($topics as $topic) {
            $payload['messages'][] = [
                'topic'   => $topic,
                'event'   => 'chatmsg',
                'payload' => $data,
            ];
        }

        $ch = curl_init("{$supabaseUrl}/realtime/v1/api/broadcast");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 5,
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $logger->error('Supabase broadcast: error de conexión', ['error' => $error, 'topics' => $topics]);
        } elseif ($status < 200 || $status >= 300) {
            $logger->error('Supabase broadcast: respuesta inesperada', [
                'status'   => $status,
                'response' => $response,
                'topics'   => $topics,
            ]);
        }
    }
}
