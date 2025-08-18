<?php
/**
 * mail.php — envío de correos con Azure Communication Services (Email API)
 * Reemplazo drop-in del antiguo PHPMailer/SMTP.
 *
 * Env vars necesarias:
 *   ACS_ENDPOINT, ACS_KEY, ACS_FROM
 *
 * Uso básico:
 *   sendMail('destino@correo.com', 'Asunto', '<b>Hola</b>');
 *   // con CC/BCC y adjuntos:
 *   sendMail(
 *     ['a@a.com','b@b.com'],
 *     'Asunto',
 *     '<p>Hola</p>',
 *     [
 *       'cc'  => ['c@c.com'],
 *       'bcc' => ['oculto@x.com'],
 *       'attachments' => [
 *          // cada adjunto: ['name' => 'archivo.pdf', 'content' => file_get_contents('...'), 'contentType' => 'application/pdf']
 *       ]
 *     ]
 *   );
 */

function sendMail($to, string $subject, string $html, array $opts = []): bool {
  $endpoint = rtrim(getenv('ACS_ENDPOINT') ?: '', '/');
  $accessKeyB64 = getenv('ACS_KEY') ?: '';
  $from = getenv('ACS_FROM') ?: '';

  if (!$endpoint || !$accessKeyB64 || !$from) {
    error_log('ACS mail: faltan ACS_ENDPOINT / ACS_KEY / ACS_FROM');
    return false;
  }

  $apiVersion   = '2023-03-31';
  $uri          = $endpoint . "/emails:send?api-version={$apiVersion}";
  $host         = parse_url($endpoint, PHP_URL_HOST);

  // Normaliza destinatarios
  $toList  = array_values(is_array($to) ? $to : [$to]);
  $ccList  = array_values($opts['cc']  ?? []);
  $bccList = array_values($opts['bcc'] ?? []);

  // Adjuntos (opcionales)
  $attachments = [];
  if (!empty($opts['attachments']) && is_array($opts['attachments'])) {
    foreach ($opts['attachments'] as $att) {
      if (!isset($att['name'], $att['content'])) continue;
      $contentType = $att['contentType'] ?? 'application/octet-stream';
      $attachments[] = [
        'name'        => (string)$att['name'],
        'contentType' => (string)$contentType,
        // la API acepta binario base64
        'contentInBase64' => base64_encode($att['content']),
      ];
    }
  }

  // Construye payload
  $payload = [
    'senderAddress' => $from,
    'content' => [
      'subject'   => $subject,
      'plainText' => trim(strip_tags($html)) ?: ' ',
      'html'      => $html
    ],
    'recipients' => [
      'to'  => array_map(fn($x)=>['address'=>$x], $toList),
    ],
  ];
  if ($ccList)  $payload['recipients']['cc']  = array_map(fn($x)=>['address'=>$x], $ccList);
  if ($bccList) $payload['recipients']['bcc'] = array_map(fn($x)=>['address'=>$x], $bccList);
  if ($attachments) $payload['attachments'] = $attachments;

  $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

  // ---- Firma HMAC requerida por ACS (x-ms-date;host;x-ms-content-sha256) ----
  $xmsDate = gmdate('D, d M Y H:i:s') . ' GMT';              // RFC1123
  $pathAndQuery = '/emails:send?api-version='.$apiVersion;

  // hash SHA256 del body en Base64
  $hashed = base64_encode(hash('sha256', $body, true));

  // cadena a firmar (orden exacto)
  $stringToSign = implode("\n", [
    'POST',
    $pathAndQuery,
    'x-ms-date;host;x-ms-content-sha256',
    $xmsDate,
    $host,
    $hashed
  ]);

  // firma HMAC-SHA256 (la key viene en Base64)
  $keyBin = base64_decode($accessKeyB64, true);
  if ($keyBin === false) {
    error_log('ACS mail: ACS_KEY no parece Base64');
    return false;
  }
  $signature = base64_encode(hash_hmac('sha256', $stringToSign, $keyBin, true));
  $auth = "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature={$signature}";

  // ---- Envío HTTP ----
  $headers = [
    'Authorization: '.$auth,
    'x-ms-date: '.$xmsDate,
    'x-ms-content-sha256: '.$hashed,
    'Content-Type: application/json',
    'Accept: application/json',
    'Host: '.$host,
  ];

  $ch = curl_init($uri);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 30,
  ]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($resp === false) {
    error_log('ACS mail cURL error: '.curl_error($ch));
  }
  curl_close($ch);

  // 202 = aceptado en la cola de envío
  if ($code === 202) return true;

  // Log para depuración
  error_log("ACS mail HTTP $code, response: $resp");
  return false;
}
