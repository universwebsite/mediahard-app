<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Required;

class OllamaService
{
    private readonly HttpClientInterface $httpClient;
    private readonly string $ollamaEndpoint;
    private readonly string $model;

    public function __construct(
        #[Required] HttpClientInterface $httpClient,
        #[Required] string $ollamaBaseUrl,
        #[Required] string $ollamaModel
    ) {
        $this->httpClient = $httpClient;
        // Формируем эндпоинт для генерации монолитного текста (не стрим)
        $this->ollamaEndpoint = rtrim($ollamaBaseUrl, '/') . '/api/generate';
        $this->model = $ollamaModel;
    }

    /**
     * Отправляет ТТХ оборудования в локальную модель и возвращает структурированный экспертный паспорт.
     */
    public function generatePassport(string $itemTitle, ?string $itemDescription): ?string
    {
        // Строгий b2b-промпт для экспертного анализа железа
        $prompt = "Ты — экспертный ИИ-модуль платформы MediaHard Enterprise. " .
                  "Проведи глубокий технический анализ следующего ИТ-оборудования.\n" .
                  "Название: {$itemTitle}\n" .
                  "Исходное описание: " . ($itemDescription ?? 'Нет описания') . "\n\n" .
                  "Выдай структурированный отчет по пунктам: \n" .
                  "1. Оценка ликвидности на рынке вычислительной техники 2026 года.\n" .
                  "2. Потенциальные инфраструктурные риски эксплуатации и уязвимости.\n" .
                  "3. Прогноз деградации MTBF. Отвечай строго по существу, профессиональным языком.";

        try {
            $response = $this->httpClient->request('POST', $this->ollamaEndpoint, [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                ],
                'timeout' => 120,
            ]);

            $data = $response->toArray();
            return $data['response'] ?? null;
        } catch (\Exception $e) {
            // Graceful degradation: возвращаем null, чтобы UI показал заглушку
            return null;
        }
    }
}
