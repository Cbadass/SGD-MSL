<?php
// test_mail.php
// Script de prueba para enviar un correo usando Azure Communication Services - Email

$endpoint = getenv("EMAIL_ENDPOINT");   // Ej: https://comunicationservicemsl.unitedstates.communication.azure.com
$accessKey = getenv("EMAIL_PRIMARY_KEY"); 
$sender = getenv("EMAIL_SENDER");       // Ej: DoNotReply@xxxx.azurecomm.net

// Receptor de prueba
$recipient = "sebastian2000morales@gmail.com";

// Cuerpo del correo
$payload = [
    "senderAddress" => $sender,
    "content" => [
        "subject" => "Prueba desde PHP",
        "plainText" => "Hola! Este es un correo de prueba enviado desde Azure Communication Services.",
        "html" => "<h2>Hola!</h2><p>Este es un <b>correo de prueba</b> enviado desde <i>Azure Communication Services</i>.</p>"
    ],
    "recipients" => [
        "to" => [
            ["address" => $recipient]
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint . "/emails:send?api-version=2023-03-31");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "api-key: " . $accessKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo "Error en cURL: " . curl_error($ch);
} else {
    echo "Código HTTP: $httpCode\n";
    echo "Respuesta: $response\n";
}

curl_close($ch);
?>
