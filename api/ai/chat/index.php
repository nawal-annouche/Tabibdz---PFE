<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
require_once __DIR__ . '/../../_core/ai_knowledge.php';

assertMethod('POST');

$body = readJsonBody();
$message = trim((string) ($body['message'] ?? ''));
$researchEffort = trim((string) ($body['research_effort'] ?? 'lite'));

if ($message === '') {
    jsonResponse(['error' => 'Message requis'], 422);
}

if (mb_strlen($message, 'UTF-8') > 4000) {
    jsonResponse(['error' => 'Message trop long'], 422);
}

$allowedEfforts = ['lite', 'standard', 'deep', 'exhaustive'];
if (!in_array($researchEffort, $allowedEfforts, true)) {
    jsonResponse(['error' => 'research_effort invalide'], 422);
}

$symptomMatch = aiFindSymptomSpecialtyMatch($message);
if ($symptomMatch !== null) {
    jsonResponse([
        'message' => 'Reponse IA generee',
        'provider' => 'tabibdz-local',
        'data' => [
            'content' => (string) $symptomMatch['content'],
            'content_type' => 'text',
            'sources' => [
                [
                    'title' => 'Orientation symptomatique TabibDZ',
                    'url' => null,
                    'snippets' => ['Specialite suggeree: ' . $symptomMatch['specialty']],
                ],
            ],
        ],
    ]);
}

$faqMatch = aiFindFaqMatch($message);
if ($faqMatch !== null) {
    jsonResponse([
        'message' => 'Reponse IA generee',
        'provider' => 'tabibdz-local',
        'data' => [
            'content' => (string) $faqMatch['answer'],
            'content_type' => 'text',
            'sources' => [
                [
                    'title' => 'Base de connaissances TabibDZ',
                    'url' => null,
                    'snippets' => [$faqMatch['question']],
                ],
            ],
        ],
    ]);
}

$apiKey = env('GEMINI_API_KEY', '');
if ($apiKey === '') {
    jsonResponse([
        'error' => 'GEMINI_API_KEY manquante',
        'hint' => 'Ajoutez GEMINI_API_KEY dans api/.env.local',
    ], 500);
}

$systemContext = trim((string) env('GEMINI_SYSTEM_CONTEXT', ''));
$promptParts = [];
if ($systemContext !== '') {
    $promptParts[] = $systemContext;
}
$projectContext = aiBuildProjectKnowledgeContext();
if ($projectContext !== '') {
    $promptParts[] = $projectContext;
}
$promptParts[] = "Consigne importante: si l'utilisateur decrit des symptomes, reponds comme un assistant d'orientation medicale non diagnostique. Propose la specialite la plus adaptee, puis eventuellement 1 ou 2 alternatives, explique brievement pourquoi, et signale clairement les signes d'urgence. Ne pretends jamais confirmer une maladie.";
$systemPrompt = implode("\n\n", $promptParts);

$model = env('GEMINI_MODEL', 'gemini-2.5-flash');
$payload = json_encode([
    'systemInstruction' => [
        'parts' => [
            ['text' => $systemPrompt],
        ],
    ],
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => $message],
            ],
        ],
    ],
    'generationConfig' => [
        'temperature' => 0.3,
        'maxOutputTokens' => 800,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    jsonResponse(['error' => 'Encodage JSON impossible'], 500);
}

$geminiBaseUrl = rtrim((string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/');
$ch = curl_init($geminiBaseUrl . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey));
if ($ch === false) {
    jsonResponse(['error' => 'Initialisation HTTP impossible'], 500);
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: TabibDZ/1.0 Gemini Client',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($responseBody === false) {
    jsonResponse([
        'error' => 'Appel Gemini impossible',
        'details' => appDebug() ? $curlError : null,
    ], 502);
}

$response = json_decode($responseBody, true);
if (!is_array($response)) {
    jsonResponse([
        'error' => 'Reponse Gemini invalide',
        'details' => appDebug() ? $responseBody : null,
    ], 502);
}

if ($statusCode >= 400) {
    $fallbackMessage = null;
    $responseText = lowerUtf8((string) json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if (str_contains($responseText, 'api key') || str_contains($responseText, 'permission') || str_contains($responseText, 'model')) {
        $fallbackMessage = "Le service Gemini a refuse la requete. Verifiez la cle API, le modele choisi et les permissions du projet Google AI Studio.";
    }

    jsonResponse([
        'error' => $fallbackMessage ?? 'Gemini a refuse la requete',
        'status' => $statusCode,
        'details' => $response,
    ], $statusCode);
}

$candidates = $response['candidates'] ?? null;
if (!is_array($candidates) || !isset($candidates[0]['content']['parts'])) {
    jsonResponse([
        'error' => 'Format de reponse inattendu',
        'details' => appDebug() ? $response : null,
    ], 502);
}

$parts = $candidates[0]['content']['parts'];
$contentParts = [];
foreach ($parts as $part) {
    if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
        $contentParts[] = $part['text'];
    }
}
$content = trim(implode("\n", $contentParts));
if ($content === '') {
    jsonResponse([
        'message' => 'Reponse IA generee',
        'provider' => 'tabibdz-local',
        'data' => [
            'content' => "Je n'ai pas trouve de reponse externe assez utile pour cette demande. Essayez de decrire les symptomes avec plus de precision, par exemple la zone du corps, depuis quand, l'intensite, l'age du patient et les signes associes. Je pourrai alors vous orienter vers la specialite la plus adaptee.",
            'content_type' => 'text',
            'sources' => [],
        ],
    ]);
}

jsonResponse([
    'message' => 'Reponse IA generee',
    'provider' => 'gemini',
    'data' => [
        'content' => $content,
        'content_type' => 'text',
        'sources' => [],
    ],
]);
