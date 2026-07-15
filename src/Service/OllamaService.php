<?php

namespace App\Service;

use App\Entity\Items;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;

class OllamaService
{
    public function __construct(
        private string $ollamaBaseUrl = 'http://127.0.0.1:11434',
        private string $ollamaModel = 'llama3:latest',
        private HttpClientInterface $httpClient,
    ) {}

    public function generatePassport(Items $item): string
    {
        // 1. Формируем промпт на основе данных товара
        $prompt = sprintf(
            'Напиши краткий ИИ‑паспорт для оборудования. Обязательно включи: название "%s", цену "%s ₽", 3–5 ключевых характеристик и 2–3 строки рекомендаций по применению. Оформи как структурированный текст без Markdown‑разметки и без заголовков типа "Ответ".',
            $item->getTitle(),
            $item->getPrice()
        );

        try {
            // 2. Отправляем запрос к Ollama /api/generate
            $response = $this->httpClient->request('POST', "{$this->ollamaBaseUrl}/api/generate", [
                'json' => [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                ],
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                throw new \RuntimeException('Ollama вернул ошибку: ' . $response->getContent());
            }

            $data = $response->toArray(false);
            $aiText = $data['response'] ?? 'Не удалось получить описание от ИИ.';

            // 3. Оборачиваем ответ модели в красивый HTML‑блок
            return <<<HTML
<div class="ai-passport" style="border: 1px dashed #f97316; background: #111827; padding: 20px; border-radius: 8px; margin-top: 15px; color: #f3f4f6;">
    <h3 style="color: #f97316; margin-top: 0; font-size: 1.2rem;">🤖 ИИ‑паспорт оборудования MediaHard</h3>
    <p><strong>Название компонента:</strong> {$item->getTitle()}</p>
    <p><strong>Базовая цена лота:</strong> {$item->getPrice()} ₽</p>
    <p><strong>Уникальный ID в СУБД:</strong> #{$item->getId()}</p>
    <hr style="border: 0; border-top: 1px solid #374151; margin: 15px 0;">
    <div style="line-height: 1.5;">
        {$aiText}
    </div>
    <p style="color: #9ca3af; font-size: 0.85em; margin-top: 12px; margin-bottom: 0;">Паспорт сгенерирован моделью "{$this->ollamaModel}" на локальной инфраструктуре MediaHard.</p>
</div>
HTML;
        } catch (\Exception $e) {
            // Если Ollama недоступен — возвращаем понятный блок с ошибкой, а не ломаем страницу
            return <<<HTML
<div class="ai-passport ai-passport-error" style="border: 1px dashed #ef4444; background: #1e1b24; padding: 20px; border-radius: 8px; margin-top: 15px; color: #fca5a5;">
    <h3 style="color: #ef4444; margin-top: 0;">⚠️ ИИ‑модуль временно недоступен</h3>
    <p>Не удалось сгенерировать паспорт: {$e->getMessage()}</p>
    <p style="font-size: 0.9em; color: #9ca3af;">Выведены базовые ТТХ. ИИ‑паспорт будет сформирован при восстановлении связи.</p>
</div>
HTML;
        }
    }
}
