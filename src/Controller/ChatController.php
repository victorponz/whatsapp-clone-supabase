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
use App\Service\SupabaseBroadcaster;

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
    public function post(ManagerRegistry $doctrine, Request $request, SupabaseBroadcaster $broadcaster, $toUserId): JsonResponse
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

            // Publicar en Supabase Realtime: en el canal personal del emisor y en el del receptor
            $broadcaster->broadcast(
                ['user:' . $message->getFromUser()->getId(), 'user:' . $toUser->getId()],
                'chatmsg',
                [
                    'id'           => $message->getId(),
                    'fromUserId'   => $message->getFromUser()->getId(),
                    'toUserId'     => $toUser->getId(),
                    'fromUserName' => $message->getFromUser()->getUsername(),
                    'toUserName'   => $toUser->getUsername(),
                    'text'         => $message->getText(),
                    'timestamp'    => ['date' => $message->getTimestamp()->format('Y-m-d H:i:s')],
                    'type'         => 'chatmsg',
                ]
            );
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
}
