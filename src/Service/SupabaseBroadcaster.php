<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Publica eventos en Supabase Realtime mediante la API REST de Broadcast.
 * Si la publicación falla no lanza excepciones: sólo registra el error en el log,
 * porque los datos ya están guardados en nuestra base de datos.
 */
class SupabaseBroadcaster
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * Publica el mismo evento en uno o varios canales con una sola petición.
     *
     * @param string[] $topics  Canales en los que publicar
     * @param string   $event   Nombre del evento que escuchan los clientes
     * @param array    $data    Datos que recibirán los clientes en envelope.payload
     */
    public function broadcast(array $topics, string $event, array $data): void
    {
        $supabaseUrl = $_ENV['SUPABASE_URL'];
        $supabaseKey = $_ENV['SUPABASE_ANON_KEY'];

        $topics = array_values(array_unique($topics));

        $payload = ['messages' => []];
        foreach ($topics as $topic) {
            $payload['messages'][] = [
                'topic'   => $topic,
                'event'   => $event,
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
            $this->logger->error('Supabase broadcast: error de conexión', ['error' => $error, 'topics' => $topics]);
        } elseif ($status < 200 || $status >= 300) {
            $this->logger->error('Supabase broadcast: respuesta inesperada', [
                'status'   => $status,
                'response' => $response,
                'topics'   => $topics,
            ]);
        }
    }
}
