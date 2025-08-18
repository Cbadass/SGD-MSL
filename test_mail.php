<?php
function enviarCorreoAzure($toEmail, $subject, $plainText, $htmlContent) {
    // Configura tus credenciales de Azure Communication Services
    $endpoint = "https://comunicationservicemsl.unitedstates.communication.azure.com";
    $apiKey   = "TU_PRIMARY_KEY"; // usa la PrimaryKey del Communication Service
    $from     = "DoNotReply@df911fb6-1209-42ac-aef0-47f50ab712cf.azurecomm.net";

    $url = $endpoint . "/emails:send?api-version=2023-03-31";

    $data = [
        "senderAddress" => $from,
        "recipients" => [
            "to" => [
                ["address" => $toEmail]
            ]
        ],
        "content" => [
            "subject"   => $subject,
            "plainText" => $plainText,
            "html"      => $htmlContent
        ]
    ];

    $payload = json_encode($data);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "api-key: $apiKey" // 🔑 Header correcto para ACS
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_VERBOSE => true
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "❌ Error en cURL: " . curl_error($ch);
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo "✅ Código HTTP: $httpCode\n";
        echo "📩 Respuesta: $response\n";
    }

    curl_close($ch);
}
enviarCorreoAzure("sebastian2000morales@gmail.com", "Prueba PHP", "Hola desde ACS!", "<b>Hola desde ACS!</b>");
