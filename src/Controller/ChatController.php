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
    public function post(ManagerRegistry $doctrine, Request $request, $toUserId): JsonResponse
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
            $this->publishToSupabase($message);
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
     * Publica el mensaje en el canal de Supabase Realtime (Broadcast).
     * El canal se nombra como "chat:{menorId}-{mayorId}" para que ambos
     * usuarios compartan siempre el mismo canal independientemente de quién envía.
     */
    private function publishToSupabase(Message $message): void
    {
        $supabaseUrl = $_ENV['SUPABASE_URL'];
        $supabaseKey = $_ENV['SUPABASE_ANON_KEY'];

        $fromUser = $message->getFromUser();
        $toUser   = $message->getToUser();

        $channelName = 'chat:' . min($fromUser->getId(), $toUser->getId())
                               . '-'
                               . max($fromUser->getId(), $toUser->getId());

        $payload = [
            'messages' => [
                [
                    'type'    => 'broadcast',
                    'event'   => 'chatmsg',
                    'payload' => [
                        'id'           => $message->getId(),
                        'fromUserId'   => $fromUser->getId(),
                        'toUserId'     => $toUser->getId(),
                        'fromUserName' => $fromUser->getUsername(),
                        'toUserName'   => $toUser->getUsername(),
                        'text'         => $message->getText(),
                        'timestamp'    => ['date' => $message->getTimestamp()->format('Y-m-d H:i:s')],
                        'type'         => 'chatmsg',
                    ],
                ],
            ],
        ];

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

        curl_exec($ch);
        curl_close($ch);
    }
}
