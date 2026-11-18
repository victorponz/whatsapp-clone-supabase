<?php

namespace App\DataFixtures;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppFixtures extends Fixture
{
    private const PASSWORD = '123456';
    private const NUM_MESSAGES = 100;
    private const FALLBACK_IMAGE = 'avatar.webp';

    /** [username, info, URL de la foto de perfil] */
    private const USERS = [
        ['ana',    'Disponible',                 'https://randomuser.me/api/portraits/women/44.jpg'],
        ['carlos', 'En el gimnasio 💪',          'https://randomuser.me/api/portraits/men/32.jpg'],
        ['lucia',  'Solo urgencias',             'https://randomuser.me/api/portraits/women/68.jpg'],
        ['javier', 'Hey there! I am using WhatsApp', 'https://randomuser.me/api/portraits/men/75.jpg'],
        ['marta',  'De vacaciones 🌴',           'https://randomuser.me/api/portraits/women/21.jpg'],
    ];

    private const TEXTS = [
        '¡Hola! ¿Qué tal?',
        'Bien, ¿y tú?',
        '¿Quedamos esta tarde?',
        'Vale, ¿a qué hora?',
        'A las seis en la plaza',
        'Perfecto, allí nos vemos',
        '¿Has visto el partido de ayer?',
        'Sí, ¡vaya final más emocionante!',
        'Mañana tengo examen 😩',
        '¡Mucho ánimo! Seguro que te sale bien',
        '¿Me pasas los apuntes de la clase?',
        'Claro, ahora te los envío',
        'Gracias 🙏',
        'De nada',
        '¿Vienes a la cena del sábado?',
        'Creo que sí, te confirmo mañana',
        'Llego 10 minutos tarde, perdona',
        'No pasa nada, te espero',
        '¿Has terminado la práctica?',
        'Me falta la última parte',
        'Jajaja 😂',
        'Qué buena foto',
        '¿Dónde estás?',
        'En casa, ¿por qué?',
        'Te llamo luego',
        'Ok 👍',
        'Feliz cumpleaños 🎉🎂',
        '¡Muchas gracias!',
        '¿Te apetece ir al cine?',
        'Buena idea, ¿qué película?',
        'Buenas noches 😴',
        'Buenos días ☀️',
    ];

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $params,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [];
        foreach (self::USERS as [$username, $info, $imageUrl]) {
            $user = new User();
            $user->setUsername($username);
            $user->setInfo($info);
            $user->setRoles([]);
            $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
            $user->setImage($this->downloadImage($imageUrl, $username));

            $manager->persist($user);
            $users[] = $user;
        }

        // Mensajes repartidos en los últimos 7 días, en orden cronológico
        $start = (new \DateTime())->modify('-7 days')->getTimestamp();
        $end = time();
        $timestamps = [];
        for ($i = 0; $i < self::NUM_MESSAGES; $i++) {
            $timestamps[] = random_int($start, $end);
        }
        sort($timestamps);

        foreach ($timestamps as $ts) {
            [$from, $to] = $this->randomPair($users);

            $message = new Message();
            $message->setFromUser($from);
            $message->setToUser($to);
            $message->setText(self::TEXTS[array_rand(self::TEXTS)]);
            $message->setTimestamp((new \DateTime())->setTimestamp($ts));
            $message->setSended(true);

            $manager->persist($message);
        }

        $manager->flush();
    }

    /**
     * Descarga la imagen a public/img y devuelve el nombre de fichero.
     * Si falla la descarga, usa el avatar por defecto.
     */
    private function downloadImage(string $url, string $username): string
    {
        $directory = rtrim($this->params->get('images_directory'), '/');
        $filename = sprintf('seed-%s.jpg', $username);

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
            if ($response->getStatusCode() !== 200) {
                return self::FALLBACK_IMAGE;
            }
            file_put_contents($directory.'/'.$filename, $response->getContent());

            return $filename;
        } catch (\Throwable) {
            return self::FALLBACK_IMAGE;
        }
    }

    /** @param User[] $users */
    private function randomPair(array $users): array
    {
        $keys = array_rand($users, 2);
        shuffle($keys);

        return [$users[$keys[0]], $users[$keys[1]]];
    }
}