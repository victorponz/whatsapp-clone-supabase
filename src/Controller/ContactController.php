<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;


class ContactController extends AbstractController
{
    #[Route('/contacts/{userId}', name: 'contacts')]
    public function contacts(ManagerRegistry $doctrine, int $userId): JsonResponse
    {
        $repository = $doctrine->getRepository(User::class);
        $contacts = $repository->findContacts($this->getUser()->getId());
        $repositoryMessages =  $doctrine->getRepository(Message::class);        
        $data = [];
        foreach($contacts as $contact){
            $lastMessage = $repositoryMessages->findLastMessageFromUser($contact->getId());
            $lastTimestamp = $lastMessage ? $lastMessage->getTimestamp() : "";
            $data[] = [
                "id"=> $contact->getId(),
                "userName" => $contact->getUserName(),
                "info" => $contact->getInfo(),
                "image" => $contact->getImage(),
                "lastTimestamp" => $lastTimestamp
            ];
        }
        return new JsonResponse($data, Response::HTTP_OK);
    }
   
}
